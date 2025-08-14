<?php

namespace App\Repositories;

use App\Models\Salary;
use App\Models\SalaryHistory;
use App\Services\CachingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SalaryRepository
{
    protected CachingService $cachingService;

    public function __construct(CachingService $cachingService)
    {
        $this->cachingService = $cachingService;
    }

    /**
     * Get optimized salary statistics with caching.
     */
    public function getSalaryStatistics(): array
    {
        return $this->cachingService->remember(
            'salary_statistics_comprehensive',
            CachingService::LONG_CACHE,
            function () {
                // Try to use the database view first for better performance
                try {
                    $viewStats = DB::table('salary_statistics')->first();
                    if ($viewStats) {
                        return $this->formatStatisticsFromView($viewStats);
                    }
                } catch (\Exception $e) {
                    // View doesn't exist, fall back to direct queries
                }

                return $this->calculateStatisticsDirectly();
            },
            [CachingService::TAG_SALARIES, CachingService::TAG_STATISTICS]
        );
    }

    /**
     * Get salary distribution by currency with caching.
     */
    public function getSalaryDistributionByCurrency(): array
    {
        return $this->cachingService->remember(
            'salary_distribution_by_currency',
            CachingService::LONG_CACHE,
            function () {
                return DB::table('salaries')
                    ->select([
                        'local_currency_code',
                        DB::raw('COUNT(*) as count'),
                        DB::raw('AVG(salary_local_currency) as avg_local_salary'),
                        DB::raw('MIN(salary_local_currency) as min_local_salary'),
                        DB::raw('MAX(salary_local_currency) as max_local_salary'),
                        DB::raw('AVG(salary_euros) as avg_salary_euros'),
                        DB::raw('AVG(commission) as avg_commission'),
                        DB::raw('AVG(displayed_salary) as avg_total_compensation')
                    ])
                    ->groupBy('local_currency_code')
                    ->orderBy('count', 'desc')
                    ->get()
                    ->toArray();
            },
            [CachingService::TAG_SALARIES, CachingService::TAG_STATISTICS]
        );
    }

    /**
     * Get salary trends over time with optimized query.
     */
    public function getSalaryTrends(int $months = 12): array
    {
        $cacheKey = "salary_trends_{$months}_months";
        
        return $this->cachingService->remember(
            $cacheKey,
            CachingService::LONG_CACHE,
            function () use ($months) {
                return DB::table('salaries')
                    ->select([
                        DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                        DB::raw('COUNT(*) as salary_count'),
                        DB::raw('AVG(salary_euros) as avg_salary_euros'),
                        DB::raw('MIN(salary_euros) as min_salary_euros'),
                        DB::raw('MAX(salary_euros) as max_salary_euros'),
                        DB::raw('AVG(commission) as avg_commission'),
                        DB::raw('AVG(displayed_salary) as avg_total_compensation'),
                        DB::raw('STDDEV(salary_euros) as salary_std_dev')
                    ])
                    ->where('created_at', '>=', now()->subMonths($months))
                    ->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
                    ->orderBy('month')
                    ->get()
                    ->toArray();
            },
            [CachingService::TAG_SALARIES, CachingService::TAG_STATISTICS]
        );
    }

    /**
     * Get recent salary changes with optimized query.
     */
    public function getRecentSalaryChanges(int $days = 7, int $limit = 100): Collection
    {
        $cacheKey = "recent_salary_changes_{$days}d_{$limit}";
        
        return $this->cachingService->remember(
            $cacheKey,
            CachingService::SHORT_CACHE,
            function () use ($days, $limit) {
                // Try to use the database view first
                try {
                    return collect(DB::table('recent_salary_changes')
                        ->where('changed_at', '>=', now()->subDays($days))
                        ->orderBy('changed_at', 'desc')
                        ->limit($limit)
                        ->get());
                } catch (\Exception $e) {
                    // Fallback to direct query with optimized joins
                    return $this->getRecentChangesDirectQuery($days, $limit);
                }
            },
            [CachingService::TAG_SALARIES, CachingService::TAG_STATISTICS]
        );
    }

    /**
     * Get salary history with optimized pagination.
     */
    public function getSalaryHistoryOptimized(int $userId = null, int $perPage = 20): LengthAwarePaginator
    {
        $cacheKey = "salary_history_optimized_{$userId}_{$perPage}";
        
        return $this->cachingService->remember(
            $cacheKey,
            CachingService::SHORT_CACHE,
            function () use ($userId, $perPage) {
                $query = SalaryHistory::select([
                    'salary_histories.id',
                    'salary_histories.user_id',
                    'salary_histories.old_salary_euros',
                    'salary_histories.new_salary_euros',
                    'salary_histories.old_commission',
                    'salary_histories.new_commission',
                    'salary_histories.changed_by',
                    'salary_histories.change_reason',
                    'salary_histories.change_type',
                    'salary_histories.created_at'
                ])
                ->with([
                    'user:id,name,email',
                    'changedBy:id,name,email'
                ]);

                if ($userId) {
                    $query->where('user_id', $userId);
                }

                return $query->orderBy('created_at', 'desc')->paginate($perPage);
            },
            [CachingService::TAG_SALARIES]
        );
    }

    /**
     * Get top earners with optimized query.
     */
    public function getTopEarners(int $limit = 10): Collection
    {
        $cacheKey = "top_earners_{$limit}";
        
        return $this->cachingService->remember(
            $cacheKey,
            CachingService::MEDIUM_CACHE,
            function () use ($limit) {
                return Salary::select([
                    'salaries.id',
                    'salaries.user_id',
                    'salaries.salary_euros',
                    'salaries.commission',
                    'salaries.displayed_salary',
                    'salaries.local_currency_code',
                    'users.name as user_name',
                    'users.email as user_email'
                ])
                ->join('users', 'salaries.user_id', '=', 'users.id')
                ->whereNull('users.deleted_at')
                ->orderBy('salaries.displayed_salary', 'desc')
                ->limit($limit)
                ->get();
            },
            [CachingService::TAG_SALARIES, CachingService::TAG_USERS]
        );
    }

    /**
     * Get salary ranges distribution.
     */
    public function getSalaryRangesDistribution(): array
    {
        return $this->cachingService->remember(
            'salary_ranges_distribution',
            CachingService::LONG_CACHE,
            function () {
                $ranges = [
                    'under_30k' => ['min' => 0, 'max' => 30000],
                    '30k_to_50k' => ['min' => 30000, 'max' => 50000],
                    '50k_to_75k' => ['min' => 50000, 'max' => 75000],
                    '75k_to_100k' => ['min' => 75000, 'max' => 100000],
                    '100k_to_150k' => ['min' => 100000, 'max' => 150000],
                    'over_150k' => ['min' => 150000, 'max' => PHP_INT_MAX],
                ];

                $distribution = [];
                foreach ($ranges as $key => $range) {
                    $count = Salary::whereBetween('salary_euros', [$range['min'], $range['max']])
                        ->count();
                    
                    $distribution[$key] = [
                        'count' => $count,
                        'percentage' => 0, // Will be calculated after getting total
                        'range' => $range,
                    ];
                }

                // Calculate percentages
                $total = array_sum(array_column($distribution, 'count'));
                if ($total > 0) {
                    foreach ($distribution as &$range) {
                        $range['percentage'] = round(($range['count'] / $total) * 100, 2);
                    }
                }

                return $distribution;
            },
            [CachingService::TAG_SALARIES, CachingService::TAG_STATISTICS]
        );
    }

    /**
     * Get commission analysis data.
     */
    public function getCommissionAnalysis(): array
    {
        return $this->cachingService->remember(
            'commission_analysis',
            CachingService::LONG_CACHE,
            function () {
                return [
                    'total_commission_paid' => Salary::sum('commission'),
                    'average_commission' => round(Salary::avg('commission'), 2),
                    'median_commission' => $this->calculateMedianCommission(),
                    'commission_distribution' => $this->getCommissionDistribution(),
                    'commission_vs_salary_ratio' => $this->getCommissionSalaryRatio(),
                ];
            },
            [CachingService::TAG_SALARIES, CachingService::TAG_STATISTICS]
        );
    }

    /**
     * Bulk update salaries with optimized transaction.
     */
    public function bulkUpdateSalaries(array $updates): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];
        
        DB::beginTransaction();
        
        try {
            // Preload all salaries to avoid N+1 queries
            $salaryIds = array_column($updates, 'id');
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
            $this->invalidateCache();
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $results;
    }

    /**
     * Format statistics from database view.
     */
    protected function formatStatisticsFromView($viewStats): array
    {
        return [
            'total_users' => (int) $viewStats->total_users,
            'users_with_salary' => (int) $viewStats->users_with_salary,
            'avg_salary_euros' => round((float) $viewStats->avg_salary_euros, 2),
            'min_salary_euros' => round((float) $viewStats->min_salary_euros, 2),
            'max_salary_euros' => round((float) $viewStats->max_salary_euros, 2),
            'avg_commission' => round((float) $viewStats->avg_commission, 2),
            'total_compensation' => round((float) $viewStats->total_compensation, 2),
            'currency_count' => (int) $viewStats->currency_count,
        ];
    }

    /**
     * Calculate statistics directly from tables.
     */
    protected function calculateStatisticsDirectly(): array
    {
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

        return $this->formatStatisticsFromView($stats);
    }

    /**
     * Get recent changes with direct query.
     */
    protected function getRecentChangesDirectQuery(int $days, int $limit): Collection
    {
        return SalaryHistory::select([
            'salary_histories.*',
            'users.name as user_name',
            'users.email as user_email',
            'changers.name as changed_by_name'
        ])
        ->join('users', 'salary_histories.user_id', '=', 'users.id')
        ->leftJoin('users as changers', 'salary_histories.changed_by', '=', 'changers.id')
        ->where('salary_histories.created_at', '>=', now()->subDays($days))
        ->orderBy('salary_histories.created_at', 'desc')
        ->limit($limit)
        ->get();
    }

    /**
     * Calculate median commission.
     */
    protected function calculateMedianCommission(): float
    {
        $commissions = Salary::pluck('commission')->sort()->values();
        $count = $commissions->count();
        
        if ($count === 0) {
            return 0;
        }
        
        if ($count % 2 === 0) {
            return ($commissions[$count / 2 - 1] + $commissions[$count / 2]) / 2;
        }
        
        return $commissions[floor($count / 2)];
    }

    /**
     * Get commission distribution.
     */
    protected function getCommissionDistribution(): array
    {
        return DB::table('salaries')
            ->select([
                DB::raw('
                    CASE 
                        WHEN commission < 250 THEN "under_250"
                        WHEN commission BETWEEN 250 AND 499 THEN "250_to_499"
                        WHEN commission = 500 THEN "exactly_500"
                        WHEN commission BETWEEN 501 AND 1000 THEN "501_to_1000"
                        ELSE "over_1000"
                    END as commission_range
                '),
                DB::raw('COUNT(*) as count')
            ])
            ->groupBy('commission_range')
            ->get()
            ->pluck('count', 'commission_range')
            ->toArray();
    }

    /**
     * Get commission to salary ratio analysis.
     */
    protected function getCommissionSalaryRatio(): array
    {
        return DB::table('salaries')
            ->selectRaw('
                AVG(commission / salary_euros * 100) as avg_commission_percentage,
                MIN(commission / salary_euros * 100) as min_commission_percentage,
                MAX(commission / salary_euros * 100) as max_commission_percentage
            ')
            ->where('salary_euros', '>', 0)
            ->first();
    }

    /**
     * Invalidate salary-related caches.
     */
    public function invalidateCache(): void
    {
        $this->cachingService->invalidateSalaryCaches();
    }
}