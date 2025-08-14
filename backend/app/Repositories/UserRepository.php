<?php

namespace App\Repositories;

use App\Models\User;
use App\Services\CachingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserRepository
{
    protected CachingService $cachingService;

    public function __construct(CachingService $cachingService)
    {
        $this->cachingService = $cachingService;
    }

    /**
     * Get optimized user list with eager loading and caching.
     */
    public function getOptimizedUserList(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $cacheKey = 'users_optimized_' . md5(serialize($filters) . $perPage);
        
        return $this->cachingService->remember(
            $cacheKey,
            CachingService::SHORT_CACHE,
            function () use ($filters, $perPage) {
                $query = $this->buildOptimizedUserQuery();
                $this->applyFilters($query, $filters);
                
                return $query->paginate($perPage);
            },
            [CachingService::TAG_USERS]
        );
    }

    /**
     * Get users with salary information using optimized query.
     */
    public function getUsersWithSalaryOptimized(): Collection
    {
        return $this->cachingService->remember(
            'users_with_salary_optimized',
            CachingService::MEDIUM_CACHE,
            function () {
                return User::select([
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.created_at'
                ])
                ->join('salaries', 'users.id', '=', 'salaries.user_id')
                ->addSelect([
                    'salaries.salary_euros',
                    'salaries.commission',
                    'salaries.displayed_salary',
                    'salaries.local_currency_code'
                ])
                ->orderBy('salaries.salary_euros', 'desc')
                ->get();
            },
            [CachingService::TAG_USERS, CachingService::TAG_SALARIES]
        );
    }

    /**
     * Get user statistics with caching.
     */
    public function getUserStatistics(): array
    {
        return $this->cachingService->remember(
            'user_statistics_detailed',
            CachingService::LONG_CACHE,
            function () {
                return [
                    'total_users' => User::count(),
                    'active_users' => User::whereNull('deleted_at')->count(),
                    'users_with_salary' => User::whereHas('salary')->count(),
                    'users_without_salary' => User::whereDoesntHave('salary')->count(),
                    'recent_registrations' => User::where('created_at', '>=', now()->subDays(30))->count(),
                    'avg_account_age_days' => DB::table('users')
                        ->whereNull('deleted_at')
                        ->selectRaw('AVG(DATEDIFF(NOW(), created_at)) as avg_age')
                        ->value('avg_age'),
                    'registration_trend' => $this->getRegistrationTrend(),
                ];
            },
            [CachingService::TAG_USERS, CachingService::TAG_STATISTICS]
        );
    }

    /**
     * Search users with optimized full-text search.
     */
    public function searchUsers(string $search, int $limit = 50): Collection
    {
        $cacheKey = 'user_search_' . md5($search . $limit);
        
        return $this->cachingService->remember(
            $cacheKey,
            CachingService::SHORT_CACHE,
            function () use ($search, $limit) {
                // Use full-text search if available (MySQL)
                if (DB::getDriverName() === 'mysql') {
                    return User::select([
                        'id', 'name', 'email', 'created_at',
                        DB::raw('MATCH(name) AGAINST(? IN BOOLEAN MODE) as relevance')
                    ])
                    ->whereRaw('MATCH(name) AGAINST(? IN BOOLEAN MODE)', [$search])
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orderBy('relevance', 'desc')
                    ->limit($limit)
                    ->get();
                }
                
                // Fallback for other databases
                return User::select(['id', 'name', 'email', 'created_at'])
                    ->where(function ($query) use ($search) {
                        $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                              ->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($search) . '%']);
                    })
                    ->limit($limit)
                    ->get();
            },
            [CachingService::TAG_USERS]
        );
    }

    /**
     * Get users by salary range with optimized query.
     */
    public function getUsersBySalaryRange(float $minSalary, float $maxSalary): Collection
    {
        $cacheKey = "users_salary_range_{$minSalary}_{$maxSalary}";
        
        return $this->cachingService->remember(
            $cacheKey,
            CachingService::MEDIUM_CACHE,
            function () use ($minSalary, $maxSalary) {
                return User::select([
                    'users.id',
                    'users.name',
                    'users.email',
                    'salaries.salary_euros',
                    'salaries.commission',
                    'salaries.displayed_salary'
                ])
                ->join('salaries', 'users.id', '=', 'salaries.user_id')
                ->whereBetween('salaries.salary_euros', [$minSalary, $maxSalary])
                ->orderBy('salaries.salary_euros', 'desc')
                ->get();
            },
            [CachingService::TAG_USERS, CachingService::TAG_SALARIES]
        );
    }

    /**
     * Build optimized base query for users.
     */
    protected function buildOptimizedUserQuery(): Builder
    {
        return User::select([
            'users.id',
            'users.name',
            'users.email',
            'users.created_at',
            'users.updated_at',
            'users.deleted_at'
        ])
        ->with([
            'salary' => function ($query) {
                $query->select([
                    'id', 'user_id', 'salary_local_currency', 'local_currency_code',
                    'salary_euros', 'commission', 'displayed_salary', 'effective_date'
                ]);
            }
        ])
        ->withCount([
            'salaryHistory',
            'uploadedDocuments'
        ]);
    }

    /**
     * Apply filters to user query efficiently.
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(users.name) LIKE ?', ['%' . strtolower($search) . '%'])
                  ->orWhereRaw('LOWER(users.email) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        if (!empty($filters['has_salary'])) {
            if ($filters['has_salary'] === 'yes') {
                $query->whereHas('salary');
            } elseif ($filters['has_salary'] === 'no') {
                $query->whereDoesntHave('salary');
            }
        }

        if (!empty($filters['currency'])) {
            $query->whereHas('salary', function ($q) use ($filters) {
                $q->where('local_currency_code', $filters['currency']);
            });
        }

        if (!empty($filters['salary_min'])) {
            $query->whereHas('salary', function ($q) use ($filters) {
                $q->where('salary_euros', '>=', $filters['salary_min']);
            });
        }

        if (!empty($filters['salary_max'])) {
            $query->whereHas('salary', function ($q) use ($filters) {
                $q->where('salary_euros', '<=', $filters['salary_max']);
            });
        }

        if (!empty($filters['created_after'])) {
            $query->where('users.created_at', '>=', $filters['created_after']);
        }

        if (!empty($filters['created_before'])) {
            $query->where('users.created_at', '<=', $filters['created_before']);
        }

        if (!empty($filters['sort_by']) && !empty($filters['sort_direction'])) {
            $sortBy = $filters['sort_by'];
            $sortDirection = $filters['sort_direction'];
            
            if (in_array($sortBy, ['salary_euros', 'commission', 'displayed_salary'])) {
                $query->leftJoin('salaries', 'users.id', '=', 'salaries.user_id')
                      ->orderBy("salaries.{$sortBy}", $sortDirection)
                      ->select('users.*');
            } else {
                $query->orderBy("users.{$sortBy}", $sortDirection);
            }
        } else {
            $query->orderBy('users.created_at', 'desc');
        }
    }

    /**
     * Get registration trend data.
     */
    protected function getRegistrationTrend(): array
    {
        return DB::table('users')
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            ])
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Invalidate user-related caches.
     */
    public function invalidateCache(?int $userId = null): void
    {
        $this->cachingService->invalidateUserCaches($userId);
    }
}