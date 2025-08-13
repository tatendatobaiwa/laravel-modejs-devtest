<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Salary;
use App\Models\SalaryHistory;
use App\Models\UploadedDocument;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AdminController extends Controller
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Get comprehensive admin dashboard data aggregation.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'refresh_cache' => 'sometimes|boolean',
                'date_range' => 'sometimes|string|in:7d,30d,90d,1y,all',
            ]);

            $cacheKey = 'admin_dashboard_' . ($request->get('date_range', '30d'));
            $refreshCache = $request->boolean('refresh_cache', false);

            if ($refreshCache) {
                Cache::forget($cacheKey);
            }

            $dashboardData = Cache::remember($cacheKey, 300, function () use ($request) { // 5 minutes cache
                $dateRange = $request->get('date_range', '30d');
                $fromDate = $this->getDateFromRange($dateRange);

                return [
                    'overview' => $this->getOverviewStats($fromDate),
                    'user_metrics' => $this->getUserMetrics($fromDate),
                    'salary_metrics' => $this->getSalaryMetrics($fromDate),
                    'activity_metrics' => $this->getActivityMetrics($fromDate),
                    'document_metrics' => $this->getDocumentMetrics($fromDate),
                    'recent_activities' => $this->getRecentActivities(20),
                    'alerts' => $this->getSystemAlerts(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $dashboardData,
                'meta' => [
                    'generated_at' => now(),
                    'date_range' => $request->get('date_range', '30d'),
                    'cached' => !$refreshCache,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in admin dashboard operation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while loading dashboard data.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get advanced user management data with filtering.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function users(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'search' => 'sometimes|string|max:255',
                'status' => 'sometimes|string|in:active,inactive,all',
                'has_salary' => 'sometimes|boolean',
                'salary_min' => 'sometimes|numeric|min:0',
                'salary_max' => 'sometimes|numeric|min:0',
                'currency' => 'sometimes|string|in:USD,EUR,GBP,CAD,AUD,JPY',
                'created_from' => 'sometimes|date',
                'created_to' => 'sometimes|date|after_or_equal:created_from',
                'sort_by' => 'sometimes|string|in:name,email,created_at,salary_euros,commission',
                'sort_direction' => 'sometimes|string|in:asc,desc',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'include_deleted' => 'sometimes|boolean',
            ]);

            $query = User::with([
                'salary',
                'uploadedDocuments' => function($q) {
                    $q->latest()->limit(1);
                },
                'salaryHistory' => function($q) {
                    $q->latest()->limit(3);
                }
            ]);

            // Include soft deleted users if requested
            if ($request->boolean('include_deleted')) {
                $query->withTrashed();
            }

            // Search functionality
            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where(function($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                      ->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($search) . '%']);
                });
            }

            // Status filtering
            if ($request->filled('status') && $request->status !== 'all') {
                if ($request->status === 'active') {
                    $query->whereNull('deleted_at');
                } elseif ($request->status === 'inactive') {
                    $query->whereNotNull('deleted_at');
                }
            }

            // Salary existence filtering
            if ($request->has('has_salary')) {
                if ($request->boolean('has_salary')) {
                    $query->whereHas('salary');
                } else {
                    $query->whereDoesntHave('salary');
                }
            }

            // Salary range filtering
            if ($request->filled('salary_min')) {
                $query->whereHas('salary', function($q) use ($request) {
                    $q->where('salary_euros', '>=', $request->salary_min);
                });
            }

            if ($request->filled('salary_max')) {
                $query->whereHas('salary', function($q) use ($request) {
                    $q->where('salary_euros', '<=', $request->salary_max);
                });
            }

            // Currency filtering
            if ($request->filled('currency')) {
                $query->whereHas('salary', function($q) use ($request) {
                    $q->where('local_currency_code', $request->currency);
                });
            }

            // Date range filtering
            if ($request->filled('created_from')) {
                $query->where('created_at', '>=', $request->created_from);
            }

            if ($request->filled('created_to')) {
                $query->where('created_at', '<=', $request->created_to . ' 23:59:59');
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            if (in_array($sortBy, ['salary_euros', 'commission'])) {
                $query->leftJoin('salaries', 'users.id', '=', 'salaries.user_id')
                      ->orderBy("salaries.{$sortBy}", $sortDirection)
                      ->select('users.*');
            } else {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Pagination
            $perPage = min($request->get('per_page', 20), 100);
            $users = $query->paginate($perPage);

            // Transform data with additional calculations
            $transformedUsers = $users->getCollection()->map(function ($user) {
                $userData = $user->toArray();
                
                if ($user->salary) {
                    $userData['salary']['displayed_salary'] = $user->salary->salary_euros + $user->salary->commission;
                }
                
                $userData['statistics'] = [
                    'salary_changes_count' => $user->salaryHistory()->count(),
                    'documents_count' => $user->uploadedDocuments()->count(),
                    'account_age_days' => $user->created_at->diffInDays(now()),
                    'last_activity' => $user->updated_at,
                ];
                
                return $userData;
            });

            return response()->json([
                'success' => true,
                'data' => $transformedUsers,
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
                'filters' => $request->only([
                    'search', 'status', 'has_salary', 'salary_min', 'salary_max',
                    'currency', 'created_from', 'created_to', 'sort_by', 'sort_direction'
                ])
            ]);

        } catch (\Exception $e) {
            Log::error('Error in admin users operation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving user data.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get comprehensive system statistics and reporting.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'report_type' => 'sometimes|string|in:summary,detailed,export',
                'date_range' => 'sometimes|string|in:7d,30d,90d,1y,all',
                'group_by' => 'sometimes|string|in:day,week,month,quarter,year',
            ]);

            $dateRange = $request->get('date_range', '30d');
            $fromDate = $this->getDateFromRange($dateRange);
            $reportType = $request->get('report_type', 'summary');

            $statistics = [
                'period' => [
                    'from' => $fromDate,
                    'to' => now(),
                    'range' => $dateRange,
                ],
                'users' => $this->getUserStatistics($fromDate),
                'salaries' => $this->getSalaryStatistics($fromDate),
                'documents' => $this->getDocumentStatistics($fromDate),
                'activities' => $this->getActivityStatistics($fromDate),
            ];

            if ($reportType === 'detailed') {
                $statistics['trends'] = $this->getTrendAnalysis($fromDate, $request->get('group_by', 'week'));
                $statistics['comparisons'] = $this->getComparativeAnalysis($fromDate);
            }

            return response()->json([
                'success' => true,
                'data' => $statistics,
                'meta' => [
                    'generated_at' => now(),
                    'report_type' => $reportType,
                    'date_range' => $dateRange,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in admin statistics operation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating statistics.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get system health and performance metrics.
     * 
     * @return JsonResponse
     */
    public function health(): JsonResponse
    {
        try {
            $health = [
                'status' => 'healthy',
                'timestamp' => now(),
                'database' => $this->checkDatabaseHealth(),
                'storage' => $this->checkStorageHealth(),
                'performance' => $this->getPerformanceMetrics(),
                'system' => $this->getSystemMetrics(),
            ];

            // Determine overall health status
            $issues = collect($health)->filter(function($value, $key) {
                return is_array($value) && isset($value['status']) && $value['status'] !== 'healthy';
            });

            if ($issues->count() > 0) {
                $health['status'] = $issues->contains(function($value) {
                    return $value['status'] === 'critical';
                }) ? 'critical' : 'warning';
            }

            return response()->json([
                'success' => true,
                'data' => $health
            ]);

        } catch (\Exception $e) {
            Log::error('Error in admin health check', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during health check.',
                'data' => [
                    'status' => 'critical',
                    'error' => $e->getMessage(),
                    'timestamp' => now(),
                ]
            ], 500);
        }
    }

    /**
     * Get overview statistics for dashboard.
     */
    private function getOverviewStats($fromDate): array
    {
        return [
            'total_users' => User::count(),
            'new_users' => User::where('created_at', '>=', $fromDate)->count(),
            'active_users' => User::whereNull('deleted_at')->count(),
            'users_with_salary' => User::whereHas('salary')->count(),
            'total_salaries' => Salary::count(),
            'total_documents' => UploadedDocument::count(),
            'recent_activities' => SalaryHistory::where('created_at', '>=', $fromDate)->count(),
        ];
    }

    /**
     * Get user metrics for dashboard.
     */
    private function getUserMetrics($fromDate): array
    {
        return [
            'registration_trend' => User::where('created_at', '>=', $fromDate)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            'status_distribution' => [
                'active' => User::whereNull('deleted_at')->count(),
                'inactive' => User::whereNotNull('deleted_at')->count(),
            ],
            'completion_rate' => [
                'with_salary' => User::whereHas('salary')->count(),
                'without_salary' => User::whereDoesntHave('salary')->count(),
            ],
        ];
    }

    /**
     * Get salary metrics for dashboard.
     */
    private function getSalaryMetrics($fromDate): array
    {
        return [
            'average_salary' => round(Salary::avg('salary_euros'), 2),
            'average_commission' => round(Salary::avg('commission'), 2),
            'salary_distribution' => [
                'under_30k' => Salary::where('salary_euros', '<', 30000)->count(),
                '30k_to_60k' => Salary::whereBetween('salary_euros', [30000, 60000])->count(),
                '60k_to_100k' => Salary::whereBetween('salary_euros', [60000, 100000])->count(),
                'over_100k' => Salary::where('salary_euros', '>', 100000)->count(),
            ],
            'currency_distribution' => Salary::select('local_currency_code')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('local_currency_code')
                ->get(),
        ];
    }

    /**
     * Get activity metrics for dashboard.
     */
    private function getActivityMetrics($fromDate): array
    {
        return [
            'salary_changes' => SalaryHistory::where('created_at', '>=', $fromDate)->count(),
            'document_uploads' => UploadedDocument::where('created_at', '>=', $fromDate)->count(),
            'daily_activity' => SalaryHistory::where('created_at', '>=', $fromDate)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
        ];
    }

    /**
     * Get document metrics for dashboard.
     */
    private function getDocumentMetrics($fromDate): array
    {
        return [
            'total_uploads' => UploadedDocument::where('created_at', '>=', $fromDate)->count(),
            'total_size' => UploadedDocument::where('created_at', '>=', $fromDate)->sum('file_size'),
            'file_types' => UploadedDocument::where('created_at', '>=', $fromDate)
                ->selectRaw('file_extension, COUNT(*) as count')
                ->groupBy('file_extension')
                ->get(),
        ];
    }

    /**
     * Get recent activities for dashboard.
     */
    private function getRecentActivities($limit = 20): array
    {
        return SalaryHistory::with(['user:id,name,email', 'changedByUser:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($history) {
                return [
                    'id' => $history->id,
                    'type' => 'salary_change',
                    'user' => $history->user,
                    'changed_by' => $history->changedByUser,
                    'changes' => [
                        'salary_change' => $history->new_salary_euros - $history->old_salary_euros,
                        'commission_change' => $history->new_commission - $history->old_commission,
                    ],
                    'reason' => $history->change_reason,
                    'created_at' => $history->created_at,
                ];
            })
            ->toArray();
    }

    /**
     * Get system alerts and warnings.
     */
    private function getSystemAlerts(): array
    {
        $alerts = [];

        // Check for users without salaries
        $usersWithoutSalary = User::whereDoesntHave('salary')->count();
        if ($usersWithoutSalary > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$usersWithoutSalary} users don't have salary information",
                'action' => 'Review incomplete profiles',
            ];
        }

        // Check for large salary changes
        $largeSalaryChanges = SalaryHistory::where('created_at', '>=', now()->subDays(7))
            ->whereRaw('ABS(new_salary_euros - old_salary_euros) > 10000')
            ->count();
        
        if ($largeSalaryChanges > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "{$largeSalaryChanges} large salary changes in the last 7 days",
                'action' => 'Review recent changes',
            ];
        }

        return $alerts;
    }

    /**
     * Get date from range string.
     */
    private function getDateFromRange(string $range): \Carbon\Carbon
    {
        return match($range) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            '1y' => now()->subYear(),
            default => now()->subDays(30),
        };
    }

    /**
     * Check database health.
     */
    private function checkDatabaseHealth(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'connections' => DB::select('SHOW STATUS LIKE "Threads_connected"')[0]->Value ?? 'unknown',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check storage health.
     */
    private function checkStorageHealth(): array
    {
        try {
            $totalSize = UploadedDocument::sum('file_size');
            $totalFiles = UploadedDocument::count();

            return [
                'status' => 'healthy',
                'total_files' => $totalFiles,
                'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get performance metrics.
     */
    private function getPerformanceMetrics(): array
    {
        return [
            'status' => 'healthy',
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
        ];
    }

    /**
     * Get system metrics.
     */
    private function getSystemMetrics(): array
    {
        return [
            'status' => 'healthy',
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => config('app.env'),
        ];
    }

    /**
     * Get user statistics.
     */
    private function getUserStatistics($fromDate): array
    {
        return [
            'total' => User::count(),
            'new_in_period' => User::where('created_at', '>=', $fromDate)->count(),
            'active' => User::whereNull('deleted_at')->count(),
            'with_salary' => User::whereHas('salary')->count(),
            'without_salary' => User::whereDoesntHave('salary')->count(),
        ];
    }

    /**
     * Get salary statistics.
     */
    private function getSalaryStatistics($fromDate): array
    {
        return [
            'total_records' => Salary::count(),
            'average_salary' => round(Salary::avg('salary_euros'), 2),
            'median_salary' => $this->getMedianSalary(),
            'average_commission' => round(Salary::avg('commission'), 2),
            'changes_in_period' => SalaryHistory::where('created_at', '>=', $fromDate)->count(),
        ];
    }

    /**
     * Get document statistics.
     */
    private function getDocumentStatistics($fromDate): array
    {
        return [
            'total_documents' => UploadedDocument::count(),
            'uploads_in_period' => UploadedDocument::where('created_at', '>=', $fromDate)->count(),
            'total_size_mb' => round(UploadedDocument::sum('file_size') / 1024 / 1024, 2),
        ];
    }

    /**
     * Get activity statistics.
     */
    private function getActivityStatistics($fromDate): array
    {
        return [
            'salary_changes' => SalaryHistory::where('created_at', '>=', $fromDate)->count(),
            'document_uploads' => UploadedDocument::where('created_at', '>=', $fromDate)->count(),
            'user_registrations' => User::where('created_at', '>=', $fromDate)->count(),
        ];
    }

    /**
     * Get trend analysis.
     */
    private function getTrendAnalysis($fromDate, $groupBy): array
    {
        // This would implement trend analysis based on the groupBy parameter
        // For now, returning a simple structure
        return [
            'user_growth' => [],
            'salary_trends' => [],
            'activity_trends' => [],
        ];
    }

    /**
     * Get comparative analysis.
     */
    private function getComparativeAnalysis($fromDate): array
    {
        // This would implement comparative analysis
        // For now, returning a simple structure
        return [
            'period_comparison' => [],
            'year_over_year' => [],
        ];
    }

    /**
     * Calculate median salary.
     */
    private function getMedianSalary(): float
    {
        $salaries = Salary::pluck('salary_euros')->sort()->values();
        $count = $salaries->count();
        
        if ($count === 0) {
            return 0;
        }
        
        if ($count % 2 === 0) {
            return ($salaries[$count / 2 - 1] + $salaries[$count / 2]) / 2;
        }
        
        return $salaries[floor($count / 2)];
    }
}