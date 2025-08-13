<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Salary;
use App\Models\SalaryHistory;

class DatabaseOptimizationService
{
    /**
     * Cache duration in minutes.
     */
    private const CACHE_DURATION = 60;
    private const LONG_CACHE_DURATION = 1440; // 24 hours

    /**
     * Get optimized user list with salary information for admin panel.
     */
    public function getOptimizedUserList(array $filters = [], int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $cacheKey = 'users_list_' . md5(serialize($filters) . $perPage);
        
        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($filters, $perPage) {
            $query = User::query()
                ->select([
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.created_at',
                    'users.updated_at'
                ])
                ->with([
                    'salary:id,user_id,salary_local_currency,local_currency_code,salary_euros,commission,displayed_salary,effective_date,updated_at'
                ])
                ->withCount(['salaryHistory', 'uploadedDocuments']);

            // Apply filters efficiently
            $this->applyUserFilters($query, $filters);

            // Optimize ordering
            $query->orderBy('users.created_at', 'desc');

            return $query->paginate($perPage);
        });
    }

    /**
     * Get optimized salary statistics for dashboard.
     */
    public function getSalaryStatistics(): array
    {
        return Cache::remember('salary_statistics', self::LONG_CACHE_DURATION, function () {
            // Use the database view for better performance
            $stats = DB::table('salary_statistics')->first();
            
            if (!$stats) {
                // Fallback to direct query if view doesn't exist
                $stats = DB::table('users')
                    ->leftJoin('salaries', 'users.id', '=', 'salaries.user_id')
                    ->whereNull('users.deleted_at')
                    ->selectRaw('
                        COUNT(users.id) as total_users,
                        COUNT(salaries.id) as users_with_salary,
                        AVG(salaries.salary_euros) as avg_salary_euros,
                        MIN(salaries.salary_euros) as min_salary_euros,
                        MAX(salaries.salary_euros) as max_salary_euros,
                        AVG(salaries.commission) as avg_commission,
                        SUM(salaries.displayed_salary) as total_compensation,
                        COUNT(DISTINCT salaries.local_currency_code) as currency_count
                    ')
                    ->first();
            }

            return [
                'total_users' => (int) $stats->total_users,
                'users_with_salary' => (int) $stats->users_with_salary,
                'avg_salary_euros' => round((float) $stats->avg_salary_euros, 2),
                'min_salary_euros' => round((float) $stats->min_salary_euros, 2),
                'max_salary_euros' => round((float) $stats->max_salary_euros, 2),
                'avg_commission' => round((float) $stats->avg_commission, 2),
                'total_compensation' => round((float) $stats->total_compensation, 2),
                'currency_count' => (int) $stats->currency_count,
            ];
        });
    }

    /**
     * Get optimized salary history with efficient pagination.
     */
    public function getSalaryHistory(int $userId = null, int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $cacheKey = "salary_history_{$userId}_{$perPage}";
        
        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($userId, $perPage) {
            $query = SalaryHistory::query()
                ->select([
                    'salary_histories.id',
                    'salary_histories.user_id',
                    'salary_histories.salary_id',
                    'salary_histories.old_values',
                    'salary_histories.new_values',
                    'salary_histories.changed_by',
                    'salary_histories.change_reason',
                    'salary_histories.action',
                    'salary_histories.changed_at'
                ])
                ->with([
                    'user:id,name,email',
                    'changedBy:id,name,email'
                ]);

            if ($userId) {
                $query->where('user_id', $userId);
            }

            return $query->orderBy('changed_at', 'desc')->paginate($perPage);
        });
    }

    /**
     * Get recent salary changes for monitoring.
     */
    public function getRecentSalaryChanges(int $days = 7): Collection
    {
        $cacheKey = "recent_salary_changes_{$days}";
        
        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($days) {
            // Try to use the database view first
            try {
                return collect(DB::table('recent_salary_changes')
                    ->where('changed_at', '>=', now()->subDays($days))
                    ->orderBy('changed_at', 'desc')
                    ->limit(100)
                    ->get());
            } catch (\Exception $e) {
                // Fallback to direct query
                return SalaryHistory::query()
                    ->select([
                        'salary_histories.*',
                        'users.name as user_name',
                        'users.email as user_email',
                        'changers.name as changed_by_name'
                    ])
                    ->join('users', 'salary_histories.user_id', '=', 'users.id')
                    ->leftJoin('users as changers', 'salary_histories.changed_by', '=', 'changers.id')
                    ->where('salary_histories.changed_at', '>=', now()->subDays($days))
                    ->orderBy('salary_histories.changed_at', 'desc')
                    ->limit(100)
                    ->get();
            }
        });
    }

    /**
     * Get salary distribution by currency.
     */
    public function getSalaryDistributionByCurrency(): array
    {
        return Cache::remember('salary_distribution_currency', self::LONG_CACHE_DURATION, function () {
            return DB::table('salaries')
                ->select([
                    'local_currency_code',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('AVG(salary_local_currency) as avg_salary'),
                    DB::raw('MIN(salary_local_currency) as min_salary'),
                    DB::raw('MAX(salary_local_currency) as max_salary'),
                    DB::raw('AVG(salary_euros) as avg_salary_euros')
                ])
                ->groupBy('local_currency_code')
                ->orderBy('count', 'desc')
                ->get()
                ->toArray();
        });
    }

    /**
     * Get salary trends over time.
     */
    public function getSalaryTrends(int $months = 12): array
    {
        $cacheKey = "salary_trends_{$months}";
        
        return Cache::remember($cacheKey, self::LONG_CACHE_DURATION, function () use ($months) {
            return DB::table('salaries')
                ->select([
                    DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('AVG(salary_euros) as avg_salary'),
                    DB::raw('AVG(commission) as avg_commission'),
                    DB::raw('AVG(displayed_salary) as avg_total_compensation')
                ])
                ->where('created_at', '>=', now()->subMonths($months))
                ->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
                ->orderBy('month')
                ->get()
                ->toArray();
        });
    }

    /**
     * Bulk update salaries with optimized queries.
     */
    public function bulkUpdateSalaries(array $updates): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];
        
        DB::beginTransaction();
        
        try {
            // Prepare bulk update data
            $salaryIds = array_column($updates, 'id');
            
            // Preload all salaries to avoid N+1 queries
            $salaries = Salary::whereIn('id', $salaryIds)
                ->with('user:id,name,email')
                ->get()
                ->keyBy('id');

            foreach ($updates as $update) {
                $salary = $salaries->get($update['id']);
                
                if (!$salary) {
                    $results['failed']++;
                    $results['errors'][] = "Salary ID {$update['id']} not found";
                    continue;
                }

                try {
                    $salary->update($update);
                    $results['success']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Failed to update salary ID {$update['id']}: " . $e->getMessage();
                }
            }

            DB::commit();
            
            // Clear related caches
            $this->clearSalaryCaches();
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $results;
    }

    /**
     * Apply filters to user query efficiently.
     */
    private function applyUserFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'LIKE', "%{$search}%")
                  ->orWhere('users.email', 'LIKE', "%{$search}%");
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
    }

    /**
     * Clear all salary-related caches.
     */
    public function clearSalaryCaches(): void
    {
        $patterns = [
            'users_list_*',
            'salary_statistics',
            'salary_history_*',
            'recent_salary_changes_*',
            'salary_distribution_currency',
            'salary_trends_*'
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                // For Redis cache, we'd need to use SCAN or similar
                // For now, we'll clear specific known keys
                $this->clearCachePattern($pattern);
            } else {
                Cache::forget($pattern);
            }
        }
    }

    /**
     * Clear cache keys matching a pattern.
     */
    private function clearCachePattern(string $pattern): void
    {
        // This is a simplified implementation
        // In production, you might want to use Redis SCAN or similar
        $baseKey = str_replace('*', '', $pattern);
        
        // Clear some common variations
        for ($i = 1; $i <= 100; $i++) {
            Cache::forget($baseKey . $i);
        }
        
        // Clear some common hash variations
        $commonHashes = ['md5', 'sha1'];
        foreach ($commonHashes as $hash) {
            for ($i = 1; $i <= 10; $i++) {
                Cache::forget($baseKey . $hash($i));
            }
        }
    }

    /**
     * Get database performance metrics.
     */
    public function getPerformanceMetrics(): array
    {
        $driver = DB::getDriverName();
        
        switch ($driver) {
            case 'mysql':
                return $this->getMySQLPerformanceMetrics();
            case 'pgsql':
                return $this->getPostgreSQLPerformanceMetrics();
            default:
                return ['driver' => $driver, 'metrics' => 'Not available'];
        }
    }

    /**
     * Get MySQL-specific performance metrics.
     */
    private function getMySQLPerformanceMetrics(): array
    {
        try {
            $status = collect(DB::select('SHOW STATUS LIKE "Slow_queries"'))
                ->merge(DB::select('SHOW STATUS LIKE "Questions"'))
                ->merge(DB::select('SHOW STATUS LIKE "Uptime"'))
                ->pluck('Value', 'Variable_name');

            return [
                'driver' => 'mysql',
                'slow_queries' => (int) $status->get('Slow_queries', 0),
                'total_queries' => (int) $status->get('Questions', 0),
                'uptime' => (int) $status->get('Uptime', 0),
                'slow_query_percentage' => $status->get('Questions', 0) > 0 
                    ? round(($status->get('Slow_queries', 0) / $status->get('Questions', 0)) * 100, 2)
                    : 0,
            ];
        } catch (\Exception $e) {
            return ['driver' => 'mysql', 'error' => $e->getMessage()];
        }
    }

    /**
     * Get PostgreSQL-specific performance metrics.
     */
    private function getPostgreSQLPerformanceMetrics(): array
    {
        try {
            $stats = DB::select("
                SELECT 
                    schemaname,
                    tablename,
                    seq_scan,
                    seq_tup_read,
                    idx_scan,
                    idx_tup_fetch
                FROM pg_stat_user_tables 
                WHERE schemaname = 'public'
            ");

            return [
                'driver' => 'pgsql',
                'table_stats' => $stats,
            ];
        } catch (\Exception $e) {
            return ['driver' => 'pgsql', 'error' => $e->getMessage()];
        }
    }

    /**
     * Optimize database tables (run maintenance).
     */
    public function optimizeTables(): array
    {
        $driver = DB::getDriverName();
        $results = [];

        try {
            switch ($driver) {
                case 'mysql':
                    $tables = ['users', 'salaries', 'salary_histories', 'uploaded_documents'];
                    foreach ($tables as $table) {
                        if (Schema::hasTable($table)) {
                            DB::statement("OPTIMIZE TABLE {$table}");
                            $results[] = "Optimized table: {$table}";
                        }
                    }
                    break;

                case 'pgsql':
                    DB::statement('VACUUM ANALYZE');
                    $results[] = 'Ran VACUUM ANALYZE on all tables';
                    break;

                case 'sqlite':
                    DB::statement('VACUUM');
                    DB::statement('ANALYZE');
                    $results[] = 'Ran VACUUM and ANALYZE';
                    break;

                default:
                    $results[] = "Optimization not implemented for driver: {$driver}";
            }
        } catch (\Exception $e) {
            $results[] = "Error during optimization: " . $e->getMessage();
        }

        return $results;
    }
}