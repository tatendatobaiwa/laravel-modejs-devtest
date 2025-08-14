<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PerformanceMonitoringService
{
    protected CachingService $cachingService;

    public function __construct(CachingService $cachingService)
    {
        $this->cachingService = $cachingService;
    }

    /**
     * Monitor query performance and log slow queries.
     */
    public function monitorQueryPerformance(): void
    {
        DB::listen(function ($query) {
            $executionTime = $query->time;
            
            // Log queries that take longer than 1 second
            if ($executionTime > 1000) {
                Log::warning('Slow query detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $executionTime . 'ms',
                    'connection' => $query->connectionName,
                ]);
                
                // Store slow query for analysis
                $this->recordSlowQuery($query->sql, $query->bindings, $executionTime);
            }
            
            // Log extremely slow queries separately
            if ($executionTime > 5000) {
                Log::error('Extremely slow query detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $executionTime . 'ms',
                    'connection' => $query->connectionName,
                    'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10),
                ]);
            }
        });
    }

    /**
     * Record slow query for analysis.
     */
    protected function recordSlowQuery(string $sql, array $bindings, float $executionTime): void
    {
        $slowQueries = Cache::get('slow_queries', []);
        
        $slowQueries[] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'execution_time' => $executionTime,
            'timestamp' => now()->toISOString(),
            'memory_usage' => memory_get_usage(true),
        ];
        
        // Keep only the last 100 slow queries
        if (count($slowQueries) > 100) {
            $slowQueries = array_slice($slowQueries, -100);
        }
        
        Cache::put('slow_queries', $slowQueries, 3600); // 1 hour
    }

    /**
     * Get performance metrics.
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'memory' => $this->getMemoryMetrics(),
            'slow_queries' => $this->getSlowQueryMetrics(),
            'query_patterns' => $this->getQueryPatternAnalysis(),
        ];
    }

    /**
     * Get database performance metrics.
     */
    protected function getDatabaseMetrics(): array
    {
        $driver = DB::getDriverName();
        
        try {
            switch ($driver) {
                case 'mysql':
                    return $this->getMySQLMetrics();
                case 'pgsql':
                    return $this->getPostgreSQLMetrics();
                case 'sqlite':
                    return $this->getSQLiteMetrics();
                default:
                    return ['driver' => $driver, 'status' => 'unsupported'];
            }
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get MySQL performance metrics.
     */
    protected function getMySQLMetrics(): array
    {
        $status = collect(DB::select('SHOW STATUS'))
            ->pluck('Value', 'Variable_name');

        $variables = collect(DB::select('SHOW VARIABLES LIKE "innodb%"'))
            ->pluck('Value', 'Variable_name');

        return [
            'driver' => 'mysql',
            'connections' => [
                'current' => (int) $status->get('Threads_connected', 0),
                'max' => (int) $variables->get('max_connections', 0),
            ],
            'queries' => [
                'total' => (int) $status->get('Questions', 0),
                'slow' => (int) $status->get('Slow_queries', 0),
                'qps' => round((int) $status->get('Questions', 0) / (int) $status->get('Uptime', 1), 2),
            ],
            'innodb' => [
                'buffer_pool_size' => $variables->get('innodb_buffer_pool_size'),
                'buffer_pool_pages_total' => (int) $status->get('Innodb_buffer_pool_pages_total', 0),
                'buffer_pool_pages_free' => (int) $status->get('Innodb_buffer_pool_pages_free', 0),
                'buffer_pool_hit_rate' => $this->calculateInnoDBHitRate($status),
            ],
            'uptime' => (int) $status->get('Uptime', 0),
        ];
    }

    /**
     * Get PostgreSQL performance metrics.
     */
    protected function getPostgreSQLMetrics(): array
    {
        try {
            $stats = DB::select("
                SELECT 
                    numbackends as active_connections,
                    xact_commit as transactions_committed,
                    xact_rollback as transactions_rolled_back,
                    blks_read as blocks_read,
                    blks_hit as blocks_hit,
                    tup_returned as tuples_returned,
                    tup_fetched as tuples_fetched,
                    tup_inserted as tuples_inserted,
                    tup_updated as tuples_updated,
                    tup_deleted as tuples_deleted
                FROM pg_stat_database 
                WHERE datname = current_database()
            ")[0];

            return [
                'driver' => 'pgsql',
                'connections' => [
                    'active' => $stats->active_connections,
                ],
                'transactions' => [
                    'committed' => $stats->transactions_committed,
                    'rolled_back' => $stats->transactions_rolled_back,
                ],
                'cache_hit_rate' => $stats->blocks_hit > 0 
                    ? round(($stats->blocks_hit / ($stats->blocks_hit + $stats->blocks_read)) * 100, 2)
                    : 0,
                'tuples' => [
                    'returned' => $stats->tuples_returned,
                    'fetched' => $stats->tuples_fetched,
                    'inserted' => $stats->tuples_inserted,
                    'updated' => $stats->tuples_updated,
                    'deleted' => $stats->tuples_deleted,
                ],
            ];
        } catch (\Exception $e) {
            return ['driver' => 'pgsql', 'error' => $e->getMessage()];
        }
    }

    /**
     * Get SQLite performance metrics.
     */
    protected function getSQLiteMetrics(): array
    {
        try {
            $pragmas = [
                'cache_size' => DB::select('PRAGMA cache_size')[0]->cache_size,
                'page_size' => DB::select('PRAGMA page_size')[0]->page_size,
                'journal_mode' => DB::select('PRAGMA journal_mode')[0]->journal_mode,
                'synchronous' => DB::select('PRAGMA synchronous')[0]->synchronous,
            ];

            return [
                'driver' => 'sqlite',
                'settings' => $pragmas,
                'database_size' => filesize(database_path('database.sqlite')),
            ];
        } catch (\Exception $e) {
            return ['driver' => 'sqlite', 'error' => $e->getMessage()];
        }
    }

    /**
     * Get cache performance metrics.
     */
    protected function getCacheMetrics(): array
    {
        return $this->cachingService->getCacheHealth();
    }

    /**
     * Get memory usage metrics.
     */
    protected function getMemoryMetrics(): array
    {
        return [
            'current_usage' => memory_get_usage(true),
            'peak_usage' => memory_get_peak_usage(true),
            'current_usage_formatted' => $this->formatBytes(memory_get_usage(true)),
            'peak_usage_formatted' => $this->formatBytes(memory_get_peak_usage(true)),
            'memory_limit' => ini_get('memory_limit'),
        ];
    }

    /**
     * Get slow query metrics.
     */
    protected function getSlowQueryMetrics(): array
    {
        $slowQueries = Cache::get('slow_queries', []);
        
        if (empty($slowQueries)) {
            return ['count' => 0, 'queries' => []];
        }

        $recentQueries = array_filter($slowQueries, function($query) {
            return strtotime($query['timestamp']) > strtotime('-1 hour');
        });

        return [
            'count' => count($slowQueries),
            'recent_count' => count($recentQueries),
            'average_time' => array_sum(array_column($slowQueries, 'execution_time')) / count($slowQueries),
            'slowest_queries' => array_slice(
                array_reverse(array_sort($slowQueries, function($query) {
                    return $query['execution_time'];
                })), 
                0, 
                5
            ),
        ];
    }

    /**
     * Analyze query patterns for optimization opportunities.
     */
    protected function getQueryPatternAnalysis(): array
    {
        $slowQueries = Cache::get('slow_queries', []);
        
        if (empty($slowQueries)) {
            return ['patterns' => [], 'recommendations' => []];
        }

        $patterns = [];
        $recommendations = [];

        foreach ($slowQueries as $query) {
            $sql = strtoupper($query['sql']);
            
            // Analyze query patterns
            if (strpos($sql, 'SELECT') !== false) {
                $patterns['SELECT'] = ($patterns['SELECT'] ?? 0) + 1;
                
                if (strpos($sql, 'ORDER BY') !== false && strpos($sql, 'LIMIT') === false) {
                    $recommendations[] = 'Consider adding LIMIT to ORDER BY queries';
                }
                
                if (strpos($sql, 'LIKE') !== false) {
                    $recommendations[] = 'Consider using full-text search for text searches';
                }
            }
            
            if (strpos($sql, 'JOIN') !== false) {
                $patterns['JOIN'] = ($patterns['JOIN'] ?? 0) + 1;
                $recommendations[] = 'Ensure proper indexes exist for JOIN conditions';
            }
            
            if (strpos($sql, 'GROUP BY') !== false) {
                $patterns['GROUP BY'] = ($patterns['GROUP BY'] ?? 0) + 1;
                $recommendations[] = 'Consider adding indexes for GROUP BY columns';
            }
        }

        return [
            'patterns' => $patterns,
            'recommendations' => array_unique($recommendations),
        ];
    }

    /**
     * Calculate InnoDB buffer pool hit rate.
     */
    protected function calculateInnoDBHitRate($status): float
    {
        $reads = (int) $status->get('Innodb_buffer_pool_reads', 0);
        $readRequests = (int) $status->get('Innodb_buffer_pool_read_requests', 0);
        
        if ($readRequests === 0) {
            return 0;
        }
        
        return round((($readRequests - $reads) / $readRequests) * 100, 2);
    }

    /**
     * Format bytes into human readable format.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Generate performance report.
     */
    public function generatePerformanceReport(): array
    {
        $metrics = $this->getPerformanceMetrics();
        
        return [
            'timestamp' => now()->toISOString(),
            'metrics' => $metrics,
            'health_score' => $this->calculateHealthScore($metrics),
            'recommendations' => $this->generateRecommendations($metrics),
        ];
    }

    /**
     * Calculate overall health score.
     */
    protected function calculateHealthScore(array $metrics): int
    {
        $score = 100;
        
        // Deduct points for slow queries
        if (isset($metrics['slow_queries']['recent_count']) && $metrics['slow_queries']['recent_count'] > 0) {
            $score -= min($metrics['slow_queries']['recent_count'] * 5, 30);
        }
        
        // Deduct points for high memory usage
        if (isset($metrics['memory']['current_usage']) && $metrics['memory']['peak_usage']) {
            $memoryUsageRatio = $metrics['memory']['current_usage'] / $metrics['memory']['peak_usage'];
            if ($memoryUsageRatio > 0.8) {
                $score -= 20;
            }
        }
        
        // Deduct points for low cache hit rate
        if (isset($metrics['database']['innodb']['buffer_pool_hit_rate'])) {
            $hitRate = $metrics['database']['innodb']['buffer_pool_hit_rate'];
            if ($hitRate < 95) {
                $score -= (95 - $hitRate) * 2;
            }
        }
        
        return max($score, 0);
    }

    /**
     * Generate performance recommendations.
     */
    protected function generateRecommendations(array $metrics): array
    {
        $recommendations = [];
        
        if (isset($metrics['slow_queries']['recent_count']) && $metrics['slow_queries']['recent_count'] > 5) {
            $recommendations[] = 'High number of slow queries detected. Consider optimizing database indexes.';
        }
        
        if (isset($metrics['database']['innodb']['buffer_pool_hit_rate']) && $metrics['database']['innodb']['buffer_pool_hit_rate'] < 95) {
            $recommendations[] = 'Low InnoDB buffer pool hit rate. Consider increasing innodb_buffer_pool_size.';
        }
        
        if (isset($metrics['memory']['current_usage']) && $metrics['memory']['peak_usage'])) {
            $memoryUsageRatio = $metrics['memory']['current_usage'] / $metrics['memory']['peak_usage'];
            if ($memoryUsageRatio > 0.8) {
                $recommendations[] = 'High memory usage detected. Consider optimizing memory-intensive operations.';
            }
        }
        
        return $recommendations;
    }
}