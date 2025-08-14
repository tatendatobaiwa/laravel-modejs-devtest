<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class QueryOptimizationService
{
    protected CachingService $cachingService;

    public function __construct(CachingService $cachingService)
    {
        $this->cachingService = $cachingService;
    }

    /**
     * Apply eager loading optimization to a query.
     */
    public function applyEagerLoading(Builder $query, array $relations): Builder
    {
        $optimizedRelations = [];
        
        foreach ($relations as $relation => $callback) {
            if (is_string($callback)) {
                // Simple relation name
                $optimizedRelations[$callback] = function ($q) {
                    $this->optimizeRelationQuery($q, $callback);
                };
            } elseif (is_callable($callback)) {
                // Relation with callback
                $optimizedRelations[$relation] = function ($q) use ($callback) {
                    $callback($q);
                    $this->optimizeRelationQuery($q, $relation);
                };
            }
        }

        return $query->with($optimizedRelations);
    }

    /**
     * Optimize a relation query with selective fields and indexes.
     */
    protected function optimizeRelationQuery(Builder $query, string $relation): void
    {
        switch ($relation) {
            case 'salary':
                $query->select([
                    'id', 'user_id', 'salary_local_currency', 'local_currency_code',
                    'salary_euros', 'commission', 'displayed_salary', 'effective_date',
                    'updated_at'
                ]);
                break;
                
            case 'salaryHistory':
                $query->select([
                    'id', 'user_id', 'old_salary_euros', 'new_salary_euros',
                    'old_commission', 'new_commission', 'changed_by', 'change_reason',
                    'created_at'
                ])->orderBy('created_at', 'desc')->limit(10);
                break;
                
            case 'uploadedDocuments':
                $query->select([
                    'id', 'user_id', 'original_filename', 'file_size',
                    'document_type', 'is_verified', 'created_at'
                ])->orderBy('created_at', 'desc');
                break;
                
            case 'user':
                $query->select(['id', 'name', 'email', 'created_at']);
                break;
        }
    }

    /**
     * Apply database-specific query optimizations.
     */
    public function applyDatabaseOptimizations(Builder $query): Builder
    {
        $driver = DB::getDriverName();
        
        switch ($driver) {
            case 'mysql':
                return $this->applyMySQLOptimizations($query);
            case 'pgsql':
                return $this->applyPostgreSQLOptimizations($query);
            case 'sqlite':
                return $this->applySQLiteOptimizations($query);
            default:
                return $query;
        }
    }

    /**
     * Apply MySQL-specific optimizations.
     */
    protected function applyMySQLOptimizations(Builder $query): Builder
    {
        // Use SQL_CALC_FOUND_ROWS for better pagination performance
        $query->getQuery()->selectRaw('SQL_CALC_FOUND_ROWS *');
        
        // Add query hints for better index usage
        $query->getQuery()->from(DB::raw($query->getModel()->getTable() . ' USE INDEX (PRIMARY)'));
        
        return $query;
    }

    /**
     * Apply PostgreSQL-specific optimizations.
     */
    protected function applyPostgreSQLOptimizations(Builder $query): Builder
    {
        // Enable parallel query execution for large datasets
        DB::statement('SET max_parallel_workers_per_gather = 4');
        
        // Use EXPLAIN ANALYZE for query optimization insights
        if (config('app.debug')) {
            $sql = $query->toSql();
            $bindings = $query->getBindings();
            Log::debug('PostgreSQL Query Plan', [
                'sql' => $sql,
                'bindings' => $bindings
            ]);
        }
        
        return $query;
    }

    /**
     * Apply SQLite-specific optimizations.
     */
    protected function applySQLiteOptimizations(Builder $query): Builder
    {
        // Enable query planner optimizations
        DB::statement('PRAGMA optimize');
        
        return $query;
    }

    /**
     * Create optimized subqueries for complex operations.
     */
    public function createOptimizedSubquery(string $type, array $parameters = []): Builder
    {
        switch ($type) {
            case 'users_with_recent_salary_changes':
                return $this->createUsersWithRecentSalaryChangesSubquery($parameters);
                
            case 'top_earners_by_currency':
                return $this->createTopEarnersByCurrencySubquery($parameters);
                
            case 'salary_growth_analysis':
                return $this->createSalaryGrowthAnalysisSubquery($parameters);
                
            default:
                throw new \InvalidArgumentException("Unknown subquery type: {$type}");
        }
    }

    /**
     * Create subquery for users with recent salary changes.
     */
    protected function createUsersWithRecentSalaryChangesSubquery(array $parameters): Builder
    {
        $days = $parameters['days'] ?? 30;
        
        return DB::table('salary_histories')
            ->select('user_id')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 0');
    }

    /**
     * Create subquery for top earners by currency.
     */
    protected function createTopEarnersByCurrencySubquery(array $parameters): Builder
    {
        $currency = $parameters['currency'] ?? 'EUR';
        $limit = $parameters['limit'] ?? 10;
        
        return DB::table('salaries')
            ->select(['user_id', 'salary_euros', 'commission', 'displayed_salary'])
            ->where('local_currency_code', $currency)
            ->orderBy('displayed_salary', 'desc')
            ->limit($limit);
    }

    /**
     * Create subquery for salary growth analysis.
     */
    protected function createSalaryGrowthAnalysisSubquery(array $parameters): Builder
    {
        $months = $parameters['months'] ?? 12;
        
        return DB::table('salary_histories')
            ->select([
                'user_id',
                DB::raw('AVG(new_salary_euros - old_salary_euros) as avg_growth'),
                DB::raw('COUNT(*) as change_count')
            ])
            ->where('created_at', '>=', now()->subMonths($months))
            ->whereRaw('new_salary_euros > old_salary_euros')
            ->groupBy('user_id')
            ->having('change_count', '>', 0);
    }

    /**
     * Optimize query with strategic indexes.
     */
    public function optimizeWithIndexHints(Builder $query, array $indexHints): Builder
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql' && !empty($indexHints)) {
            $table = $query->getModel()->getTable();
            $hints = implode(', ', $indexHints);
            $query->from(DB::raw("{$table} USE INDEX ({$hints})"));
        }
        
        return $query;
    }

    /**
     * Apply query result caching with intelligent cache keys.
     */
    public function applyCaching(Builder $query, array $cacheConfig): mixed
    {
        $cacheKey = $this->generateIntelligentCacheKey($query, $cacheConfig);
        $duration = $cacheConfig['duration'] ?? CachingService::MEDIUM_CACHE;
        $tags = $cacheConfig['tags'] ?? [];
        
        return $this->cachingService->remember(
            $cacheKey,
            $duration,
            function () use ($query) {
                return $query->get();
            },
            $tags
        );
    }

    /**
     * Generate intelligent cache key based on query structure.
     */
    protected function generateIntelligentCacheKey(Builder $query, array $config): string
    {
        $baseKey = $config['base_key'] ?? 'query';
        $sql = $query->toSql();
        $bindings = $query->getBindings();
        
        // Create a hash of the query structure
        $queryHash = md5($sql . serialize($bindings));
        
        // Include relevant parameters
        $params = [];
        if (isset($config['user_id'])) {
            $params[] = 'user_' . $config['user_id'];
        }
        if (isset($config['date_range'])) {
            $params[] = 'range_' . $config['date_range'];
        }
        if (isset($config['filters'])) {
            $params[] = 'filters_' . md5(serialize($config['filters']));
        }
        
        $paramString = empty($params) ? '' : '_' . implode('_', $params);
        
        return "{$baseKey}_{$queryHash}{$paramString}";
    }

    /**
     * Analyze query performance and provide optimization suggestions.
     */
    public function analyzeQueryPerformance(Builder $query): array
    {
        $driver = DB::getDriverName();
        $analysis = [
            'driver' => $driver,
            'query' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'suggestions' => [],
            'performance_metrics' => []
        ];
        
        try {
            switch ($driver) {
                case 'mysql':
                    $analysis = array_merge($analysis, $this->analyzeMySQLQuery($query));
                    break;
                case 'pgsql':
                    $analysis = array_merge($analysis, $this->analyzePostgreSQLQuery($query));
                    break;
                default:
                    $analysis['suggestions'][] = 'Query analysis not available for this database driver';
            }
        } catch (\Exception $e) {
            $analysis['error'] = $e->getMessage();
        }
        
        return $analysis;
    }

    /**
     * Analyze MySQL query performance.
     */
    protected function analyzeMySQLQuery(Builder $query): array
    {
        $sql = $query->toSql();
        $bindings = $query->getBindings();
        
        // Get query execution plan
        $explain = DB::select("EXPLAIN {$sql}", $bindings);
        
        $analysis = [
            'execution_plan' => $explain,
            'suggestions' => []
        ];
        
        // Analyze execution plan for optimization opportunities
        foreach ($explain as $row) {
            if ($row->type === 'ALL') {
                $analysis['suggestions'][] = "Full table scan detected on {$row->table}. Consider adding indexes.";
            }
            
            if ($row->Extra && str_contains($row->Extra, 'Using filesort')) {
                $analysis['suggestions'][] = "Filesort detected. Consider adding composite indexes for ORDER BY clauses.";
            }
            
            if ($row->Extra && str_contains($row->Extra, 'Using temporary')) {
                $analysis['suggestions'][] = "Temporary table usage detected. Consider query restructuring.";
            }
        }
        
        return $analysis;
    }

    /**
     * Analyze PostgreSQL query performance.
     */
    protected function analyzePostgreSQLQuery(Builder $query): array
    {
        $sql = $query->toSql();
        $bindings = $query->getBindings();
        
        // Get query execution plan
        $explain = DB::select("EXPLAIN ANALYZE {$sql}", $bindings);
        
        return [
            'execution_plan' => $explain,
            'suggestions' => [
                'Consider using EXPLAIN ANALYZE for detailed performance metrics',
                'Review index usage in the execution plan',
                'Consider using partial indexes for filtered queries'
            ]
        ];
    }

    /**
     * Batch process queries for better performance.
     */
    public function batchProcess(array $queries, int $batchSize = 100): array
    {
        $results = [];
        $batches = array_chunk($queries, $batchSize);
        
        foreach ($batches as $batch) {
            DB::beginTransaction();
            
            try {
                $batchResults = [];
                
                foreach ($batch as $query) {
                    if ($query instanceof Builder) {
                        $batchResults[] = $query->get();
                    } elseif (is_callable($query)) {
                        $batchResults[] = $query();
                    }
                }
                
                DB::commit();
                $results = array_merge($results, $batchResults);
                
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Batch processing failed', [
                    'error' => $e->getMessage(),
                    'batch_size' => count($batch)
                ]);
                throw $e;
            }
        }
        
        return $results;
    }

    /**
     * Optimize memory usage for large result sets.
     */
    public function optimizeMemoryUsage(Builder $query, callable $processor): void
    {
        // Use chunking to process large datasets without memory issues
        $query->chunk(1000, function ($records) use ($processor) {
            $processor($records);
            
            // Force garbage collection after each chunk
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        });
    }
}