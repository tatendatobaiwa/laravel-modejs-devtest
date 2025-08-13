<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSalaryRequest;
use App\Models\User;
use App\Models\Salary;
use App\Models\SalaryHistory;
use App\Services\SalaryService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SalaryController extends Controller
{
    protected SalaryService $salaryService;
    protected AuditService $auditService;

    public function __construct(SalaryService $salaryService, AuditService $auditService)
    {
        $this->salaryService = $salaryService;
        $this->auditService = $auditService;
    }

    /**
     * Update salary for a specific user with automatic calculation.
     * 
     * @param UpdateSalaryRequest $request
     * @param User $user
     * @return JsonResponse
     */
    public function update(UpdateSalaryRequest $request, User $user): JsonResponse
    {
        try {
            DB::beginTransaction();

            if (!$user->salary) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not have a salary record. Please create one first.'
                ], 404);
            }

            // Validate business rules
            $businessRuleErrors = $request->validateBusinessRules();
            if (!empty($businessRuleErrors)) {
                throw ValidationException::withMessages($businessRuleErrors);
            }

            $data = $request->getSanitizedData();
            $oldSalary = $user->salary->toArray();

            // Update salary using service
            $updatedSalary = $this->salaryService->updateSalary(
                $user->salary->id,
                $data,
                $request->input('change_reason', 'Admin update')
            );

            // Log audit trail
            $this->auditService->logUserAction($user->id, 'salary_updated', [
                'old_salary' => $oldSalary,
                'new_salary' => $updatedSalary->toArray(),
                'updated_by' => auth()->id() ?? 'system',
                'change_reason' => $request->input('change_reason'),
            ]);

            DB::commit();

            $user->load(['salary', 'salaryHistory' => function($q) {
                $q->orderBy('created_at', 'desc')->limit(5);
            }]);

            Log::info('Salary updated', [
                'user_id' => $user->id,
                'salary_id' => $updatedSalary->id,
                'updated_by' => auth()->id() ?? 'system',
                'changes' => array_diff_assoc($updatedSalary->toArray(), $oldSalary)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Salary updated successfully',
                'data' => [
                    'user' => $user,
                    'salary' => $updatedSalary,
                    'displayed_salary' => $updatedSalary->salary_euros + $updatedSalary->commission,
                ]
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error in salary update operation', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['change_reason'])
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the salary.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get salary history for a specific user with pagination.
     * 
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     */
    public function history(Request $request, User $user): JsonResponse
    {
        try {
            $request->validate([
                'per_page' => 'sometimes|integer|min:1|max:100',
                'sort_by' => 'sometimes|string|in:created_at,old_salary_euros,new_salary_euros,old_commission,new_commission',
                'sort_direction' => 'sometimes|string|in:asc,desc',
                'from_date' => 'sometimes|date',
                'to_date' => 'sometimes|date|after_or_equal:from_date',
            ]);

            $query = SalaryHistory::where('user_id', $user->id)
                ->with(['changedByUser:id,name,email']);

            // Date filtering
            if ($request->filled('from_date')) {
                $query->where('created_at', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->where('created_at', '<=', $request->to_date . ' 23:59:59');
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Pagination
            $perPage = min($request->get('per_page', 20), 100);
            $history = $query->paginate($perPage);

            // Transform data to include calculated changes
            $transformedHistory = $history->getCollection()->map(function ($record) {
                $data = $record->toArray();
                
                $data['changes'] = [
                    'salary_change' => $record->new_salary_euros - $record->old_salary_euros,
                    'commission_change' => $record->new_commission - $record->old_commission,
                    'total_change' => ($record->new_salary_euros + $record->new_commission) - 
                                    ($record->old_salary_euros + $record->old_commission),
                ];
                
                return $data;
            });

            return response()->json([
                'success' => true,
                'data' => $transformedHistory,
                'pagination' => [
                    'current_page' => $history->currentPage(),
                    'last_page' => $history->lastPage(),
                    'per_page' => $history->perPage(),
                    'total' => $history->total(),
                    'from' => $history->firstItem(),
                    'to' => $history->lastItem(),
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in salary history operation', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving salary history.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Bulk update salaries for multiple users.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'salaries' => 'required|array|min:1|max:50', // Limit to 50 for performance
            'salaries.*.user_id' => 'required|integer|exists:users,id',
            'salaries.*.salary_local_currency' => 'sometimes|numeric|min:0|max:999999.99',
            'salaries.*.salary_euros' => 'sometimes|numeric|min:0|max:999999.99',
            'salaries.*.commission' => 'sometimes|numeric|min:0|max:50000.00',
            'salaries.*.change_reason' => 'required|string|min:10|max:255',
        ], [
            'salaries.required' => 'At least one salary update is required.',
            'salaries.max' => 'Cannot update more than 50 salaries at once.',
            'salaries.*.user_id.required' => 'User ID is required for each salary update.',
            'salaries.*.user_id.exists' => 'One or more users do not exist.',
            'salaries.*.change_reason.required' => 'Change reason is required for each salary update.',
            'salaries.*.change_reason.min' => 'Change reason must be at least 10 characters.',
        ]);

        try {
            DB::beginTransaction();

            $results = [
                'successful' => [],
                'failed' => [],
                'total_processed' => 0,
            ];

            foreach ($request->salaries as $salaryData) {
                try {
                    $user = User::with('salary')->find($salaryData['user_id']);
                    
                    if (!$user || !$user->salary) {
                        $results['failed'][] = [
                            'user_id' => $salaryData['user_id'],
                            'error' => 'User or salary record not found'
                        ];
                        continue;
                    }

                    $oldSalary = $user->salary->toArray();

                    // Update salary using service
                    $updatedSalary = $this->salaryService->updateSalary(
                        $user->salary->id,
                        array_filter($salaryData, function($key) {
                            return in_array($key, ['salary_local_currency', 'salary_euros', 'commission']);
                        }, ARRAY_FILTER_USE_KEY),
                        $salaryData['change_reason']
                    );

                    // Log audit trail
                    $this->auditService->logUserAction($user->id, 'salary_bulk_updated', [
                        'old_salary' => $oldSalary,
                        'new_salary' => $updatedSalary->toArray(),
                        'updated_by' => auth()->id() ?? 'system',
                        'change_reason' => $salaryData['change_reason'],
                    ]);

                    $results['successful'][] = [
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'salary' => $updatedSalary,
                        'displayed_salary' => $updatedSalary->salary_euros + $updatedSalary->commission,
                    ];

                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'user_id' => $salaryData['user_id'],
                        'error' => $e->getMessage()
                    ];
                }

                $results['total_processed']++;
            }

            DB::commit();

            Log::info('Bulk salary update completed', [
                'total_processed' => $results['total_processed'],
                'successful' => count($results['successful']),
                'failed' => count($results['failed']),
                'updated_by' => auth()->id() ?? 'system'
            ]);

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    'Bulk update completed. %d successful, %d failed out of %d total.',
                    count($results['successful']),
                    count($results['failed']),
                    $results['total_processed']
                ),
                'data' => $results
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error in bulk salary update operation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during bulk salary update.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get salary statistics and analytics.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'currency' => 'sometimes|string|in:USD,EUR,GBP,CAD,AUD,JPY',
                'from_date' => 'sometimes|date',
                'to_date' => 'sometimes|date|after_or_equal:from_date',
            ]);

            $query = Salary::query();

            // Filter by currency if specified
            if ($request->filled('currency')) {
                $query->where('local_currency_code', $request->currency);
            }

            // Date filtering for salary creation
            if ($request->filled('from_date')) {
                $query->where('created_at', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->where('created_at', '<=', $request->to_date . ' 23:59:59');
            }

            $stats = [
                'total_salaries' => $query->count(),
                'salary_statistics' => [
                    'average_salary_euros' => round($query->avg('salary_euros'), 2),
                    'median_salary_euros' => $this->getMedianSalary($query),
                    'min_salary_euros' => $query->min('salary_euros'),
                    'max_salary_euros' => $query->max('salary_euros'),
                ],
                'commission_statistics' => [
                    'average_commission' => round($query->avg('commission'), 2),
                    'median_commission' => $this->getMedianCommission($query),
                    'min_commission' => $query->min('commission'),
                    'max_commission' => $query->max('commission'),
                ],
                'currency_distribution' => Salary::select('local_currency_code')
                    ->selectRaw('COUNT(*) as count, AVG(salary_euros) as avg_salary')
                    ->groupBy('local_currency_code')
                    ->get(),
                'salary_ranges' => [
                    'under_30k' => $query->clone()->where('salary_euros', '<', 30000)->count(),
                    '30k_to_50k' => $query->clone()->whereBetween('salary_euros', [30000, 50000])->count(),
                    '50k_to_75k' => $query->clone()->whereBetween('salary_euros', [50000, 75000])->count(),
                    '75k_to_100k' => $query->clone()->whereBetween('salary_euros', [75000, 100000])->count(),
                    'over_100k' => $query->clone()->where('salary_euros', '>', 100000)->count(),
                ],
                'recent_changes' => SalaryHistory::where('created_at', '>=', now()->subDays(30))
                    ->count(),
                'top_earners' => Salary::with('user:id,name,email')
                    ->orderBy('salary_euros', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function($salary) {
                        return [
                            'user' => $salary->user,
                            'salary_euros' => $salary->salary_euros,
                            'commission' => $salary->commission,
                            'displayed_salary' => $salary->salary_euros + $salary->commission,
                        ];
                    }),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'filters' => [
                    'currency' => $request->currency,
                    'from_date' => $request->from_date,
                    'to_date' => $request->to_date,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in salary statistics operation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving salary statistics.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Calculate median salary from query.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return float
     */
    private function getMedianSalary($query): float
    {
        $salaries = $query->pluck('salary_euros')->sort()->values();
        $count = $salaries->count();
        
        if ($count === 0) {
            return 0;
        }
        
        if ($count % 2 === 0) {
            return ($salaries[$count / 2 - 1] + $salaries[$count / 2]) / 2;
        }
        
        return $salaries[floor($count / 2)];
    }

    /**
     * Calculate median commission from query.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return float
     */
    private function getMedianCommission($query): float
    {
        $commissions = $query->pluck('commission')->sort()->values();
        $count = $commissions->count();
        
        if ($count === 0) {
            return 0;
        }
        
        if ($count % 2 === 0) {
            return ($commissions[$count / 2 - 1] + $commissions[$count / 2]) / 2;
        }
        
        return $commissions[floor($count / 2)];
    }
}