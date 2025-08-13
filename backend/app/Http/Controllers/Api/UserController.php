<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\FileUploadRequest;
use App\Models\User;
use App\Models\Salary;
use App\Services\SalaryService;
use App\Services\FileUploadService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    protected SalaryService $salaryService;
    protected FileUploadService $fileUploadService;
    protected AuditService $auditService;

    public function __construct(
        SalaryService $salaryService,
        FileUploadService $fileUploadService,
        AuditService $auditService
    ) {
        $this->salaryService = $salaryService;
        $this->fileUploadService = $fileUploadService;
        $this->auditService = $auditService;
    }

    /**
     * Store a new user or update existing user with unique email handling.
     * 
     * @param StoreUserRequest $request
     * @return JsonResponse
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->getSanitizedData();
            $isUpdate = $request->isUpdate();
            
            if ($isUpdate) {
                // Update existing user
                $user = User::find($request->getExistingUserId());
                $user->update(['name' => $data['name']]);
                
                Log::info('User updated via form submission', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'updated_fields' => ['name']
                ]);
            } else {
                // Create new user
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => bcrypt(str()->random(32)), // Generate secure random password
                ]);
                
                Log::info('New user created via form submission', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
            }

            // Handle file upload if provided
            $documentPath = null;
            if ($request->hasFile('document')) {
                $documentPath = $this->fileUploadService->uploadFile(
                    $request->file('document'),
                    $user->id,
                    'salary_document'
                );
            }

            // Create or update salary record using service
            $salary = $this->salaryService->createOrUpdateSalary($user->id, [
                'salary_local_currency' => $data['salary_local_currency'],
                'local_currency_code' => $data['local_currency_code'],
                'commission' => $data['commission'],
                'notes' => $data['notes'] ?? null,
                'document_path' => $documentPath,
            ], $isUpdate ? 'User form update' : 'Initial user registration');

            // Log audit trail
            $this->auditService->logUserAction($user->id, $isUpdate ? 'user_updated' : 'user_created', [
                'salary_data' => $salary->toArray(),
                'document_uploaded' => !is_null($documentPath),
                'is_update' => $isUpdate,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $isUpdate ? 'User information updated successfully' : 'User registered successfully',
                'data' => [
                    'user' => $user->load(['salary', 'uploadedDocuments']),
                    'is_update' => $isUpdate,
                ]
            ], $isUpdate ? 200 : 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error in user store operation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['document'])
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display a paginated listing of users with search and filtering.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Use optimized scope for admin queries
            $query = User::forAdmin();

            // Search functionality - case insensitive with optimized query
            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->search($search);
            }

            // Filter by salary range
            if ($request->filled('min_salary')) {
                $query->whereHas('salary', function($q) use ($request) {
                    $q->where('salary_euros', '>=', $request->min_salary);
                });
            }

            if ($request->filled('max_salary')) {
                $query->whereHas('salary', function($q) use ($request) {
                    $q->where('salary_euros', '<=', $request->max_salary);
                });
            }

            // Filter by commission range
            if ($request->filled('min_commission')) {
                $query->whereHas('salary', function($q) use ($request) {
                    $q->where('commission', '>=', $request->min_commission);
                });
            }

            if ($request->filled('max_commission')) {
                $query->whereHas('salary', function($q) use ($request) {
                    $q->where('commission', '<=', $request->max_commission);
                });
            }

            // Filter by currency
            if ($request->filled('currency')) {
                $query->whereHas('salary', function($q) use ($request) {
                    $q->where('local_currency_code', $request->currency);
                });
            }

            // Filter by date range
            if ($request->filled('created_from')) {
                $query->where('created_at', '>=', $request->created_from);
            }

            if ($request->filled('created_to')) {
                $query->where('created_at', '<=', $request->created_to . ' 23:59:59');
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            
            // Validate sort parameters
            $allowedSortFields = ['name', 'email', 'created_at', 'updated_at'];
            $allowedDirections = ['asc', 'desc'];
            
            if (!in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'created_at';
            }
            
            if (!in_array($sortDirection, $allowedDirections)) {
                $sortDirection = 'desc';
            }

            // Optimized sorting with proper indexing
            if (in_array($sortBy, ['salary_euros', 'commission', 'displayed_salary'])) {
                $query->leftJoin('salaries', 'users.id', '=', 'salaries.user_id')
                      ->orderBy("salaries.{$sortBy}", $sortDirection)
                      ->select('users.id', 'users.name', 'users.email', 'users.created_at', 'users.updated_at', 'users.deleted_at');
            } else {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Pagination with caching for repeated requests
            $perPage = min($request->get('per_page', 20), 100); // Max 100 per page
            $cacheKey = 'users_index_' . md5(serialize($request->all()));
            
            $users = Cache::remember($cacheKey, 5, function() use ($query, $perPage) {
                return $query->paginate($perPage);
            });

            // Transform the data to include calculated fields
            $transformedUsers = $users->getCollection()->map(function ($user) {
                $userData = $user->toArray();
                
                if ($user->salary) {
                    $userData['salary']['displayed_salary'] = $user->salary->salary_euros + $user->salary->commission;
                }
                
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
                'filters' => [
                    'search' => $request->search,
                    'min_salary' => $request->min_salary,
                    'max_salary' => $request->max_salary,
                    'min_commission' => $request->min_commission,
                    'max_commission' => $request->max_commission,
                    'currency' => $request->currency,
                    'created_from' => $request->created_from,
                    'created_to' => $request->created_to,
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in user index operation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving users.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified user with full details.
     * 
     * @param User $user
     * @return JsonResponse
     */
    public function show(User $user): JsonResponse
    {
        try {
            $user->load([
                'salary',
                'salaryHistory' => function($q) {
                    $q->orderBy('created_at', 'desc')->limit(10); // Last 10 changes
                },
                'uploadedDocuments' => function($q) {
                    $q->orderBy('created_at', 'desc');
                }
            ]);

            // Calculate additional fields
            $userData = $user->toArray();
            
            if ($user->salary) {
                $userData['salary']['displayed_salary'] = $user->salary->salary_euros + $user->salary->commission;
            }

            // Add statistics
            $userData['statistics'] = [
                'total_salary_changes' => $user->salaryHistory()->count(),
                'documents_uploaded' => $user->uploadedDocuments()->count(),
                'account_age_days' => $user->created_at->diffInDays(now()),
                'last_updated' => $user->updated_at,
            ];

            return response()->json([
                'success' => true,
                'data' => $userData
            ]);

        } catch (\Exception $e) {
            Log::error('Error in user show operation', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving user details.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update the specified user (name only - salary updates handled by SalaryController).
     * 
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|min:2|max:255|regex:/^[a-zA-Z\s\-\'\.]+$/',
        ], [
            'name.required' => 'The name field is required.',
            'name.min' => 'The name must be at least 2 characters long.',
            'name.max' => 'The name may not be greater than 255 characters.',
            'name.regex' => 'The name may only contain letters, spaces, hyphens, apostrophes, and dots.',
        ]);

        try {
            DB::beginTransaction();

            $oldName = $user->name;
            $user->update([
                'name' => trim($request->name)
            ]);

            // Log audit trail
            $this->auditService->logUserAction($user->id, 'user_name_updated', [
                'old_name' => $oldName,
                'new_name' => $user->name,
                'updated_by' => auth()->id() ?? 'system',
            ]);

            DB::commit();

            $user->load(['salary', 'uploadedDocuments']);

            Log::info('User name updated', [
                'user_id' => $user->id,
                'old_name' => $oldName,
                'new_name' => $user->name,
                'updated_by' => auth()->id() ?? 'system'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User name updated successfully',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error in user update operation', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the user.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified user from storage (soft delete).
     * 
     * @param User $user
     * @return JsonResponse
     */
    public function destroy(User $user): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Store user data for audit log before deletion
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'salary_data' => $user->salary?->toArray(),
                'documents_count' => $user->uploadedDocuments()->count(),
            ];

            // Clean up uploaded files using service
            foreach ($user->uploadedDocuments as $document) {
                $this->fileUploadService->deleteFile($document->file_path);
            }

            // Log audit trail before deletion
            $this->auditService->logUserAction($user->id, 'user_deleted', [
                'user_data' => $userData,
                'deleted_by' => auth()->id() ?? 'system',
                'deletion_reason' => 'Admin deletion',
            ]);

            // Soft delete user (this will cascade to related models if configured)
            $user->delete();

            DB::commit();

            Log::info('User deleted', [
                'user_id' => $user->id,
                'email' => $user->email,
                'deleted_by' => auth()->id() ?? 'system'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error in user delete operation', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the user.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Upload a document for a specific user.
     * 
     * @param FileUploadRequest $request
     * @param User $user
     * @return JsonResponse
     */
    public function uploadDocument(FileUploadRequest $request, User $user): JsonResponse
    {
        try {
            DB::beginTransaction();

            $filePath = $this->fileUploadService->uploadFile(
                $request->file('file'),
                $user->id,
                $request->input('file_type', 'other'),
                $request->input('description')
            );

            // Log audit trail
            $this->auditService->logUserAction($user->id, 'document_uploaded', [
                'file_type' => $request->input('file_type'),
                'file_path' => $filePath,
                'description' => $request->input('description'),
                'uploaded_by' => auth()->id() ?? 'system',
            ]);

            DB::commit();

            $user->load('uploadedDocuments');

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => [
                    'file_path' => $filePath,
                    'user' => $user
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error in document upload operation', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while uploading the document.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get user statistics and summary data.
     * 
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_users' => User::count(),
                'users_with_salary' => User::whereHas('salary')->count(),
                'users_without_salary' => User::whereDoesntHave('salary')->count(),
                'total_documents' => \App\Models\UploadedDocument::count(),
                'average_salary_euros' => Salary::avg('salary_euros'),
                'average_commission' => Salary::avg('commission'),
                'currency_distribution' => Salary::select('local_currency_code')
                    ->selectRaw('COUNT(*) as count')
                    ->groupBy('local_currency_code')
                    ->get(),
                'recent_registrations' => User::where('created_at', '>=', now()->subDays(30))->count(),
                'salary_ranges' => [
                    'under_30k' => Salary::where('salary_euros', '<', 30000)->count(),
                    '30k_to_50k' => Salary::whereBetween('salary_euros', [30000, 50000])->count(),
                    '50k_to_75k' => Salary::whereBetween('salary_euros', [50000, 75000])->count(),
                    'over_75k' => Salary::where('salary_euros', '>', 75000)->count(),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error in user statistics operation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving statistics.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
