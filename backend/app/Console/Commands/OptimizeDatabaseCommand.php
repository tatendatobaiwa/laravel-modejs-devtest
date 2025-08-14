<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DatabaseOptimizationService;
use App\Services\CachingService;
use App\Services\PerformanceMonitoringService;

class OptimizeDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'db:optimize 
                            {--tables : Optimize database tables}
                            {--cache : Warm up application cache}
                            {--analyze : Analyze query performance}
                            {--all : Run all optimizations}';

    /**
     * The console command description.
     */
    protected $description = 'Optimize database performance with various strategies';

    protected DatabaseOptimizationService $dbService;
    protected CachingService $cacheService;
    protected PerformanceMonitoringService $performanceService;

    public function __construct(
        DatabaseOptimizationService $dbService,
        CachingService $cacheService,
        PerformanceMonitoringService $performanceService
    ) {
        parent::__construct();
        $this->dbService = $dbService;
        $this->cacheService = $cacheService;
        $this->performanceService = $performanceService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting database optimization...');
        
        $runAll = $this->option('all');
        
        if ($runAll || $this->option('tables')) {
            $this->optimizeTables();
        }
        
        if ($runAll || $this->option('cache')) {
            $this->warmUpCache();
        }
        
        if ($runAll || $this->option('analyze')) {
            $this->analyzePerformance();
        }
        
        if (!$runAll && !$this->option('tables') && !$this->option('cache') && !$this->option('analyze')) {
            $this->showHelp();
        }
        
        $this->info('✅ Database optimization completed!');
        
        return Command::SUCCESS;
    }

    /**
     * Optimize database tables.
     */
    protected function optimizeTables(): void
    {
        $this->info('🔧 Optimizing database tables...');
        
        $results = $this->dbService->optimizeTables();
        
        foreach ($results as $result) {
            $this->line("  • {$result}");
        }
        
        $this->info('✅ Table optimization completed');
    }

    /**
     * Warm up application cache.
     */
    protected function warmUpCache(): void
    {
        $this->info('🔥 Warming up application cache...');
        
        $results = $this->cacheService->warmUpCaches();
        
        foreach ($results as $result) {
            $this->line("  • {$result}");
        }
        
        // Also run intelligent warm-up
        $this->info('🧠 Running intelligent cache warm-up...');
        $intelligentResults = $this->cacheService->intelligentWarmUp();
        
        foreach ($intelligentResults as $result) {
            $this->line("  • {$result}");
        }
        
        $this->info('✅ Cache warm-up completed');
    }

    /**
     * Analyze database performance.
     */
    protected function analyzePerformance(): void
    {
        $this->info('📊 Analyzing database performance...');
        
        $report = $this->performanceService->generatePerformanceReport();
        
        $this->displayPerformanceReport($report);
        
        $this->info('✅ Performance analysis completed');
    }

    /**
     * Display performance report.
     */
    protected function displayPerformanceReport(array $report): void
    {
        $this->newLine();
        $this->info('📈 Performance Report');
        $this->line('Generated at: ' . $report['timestamp']);
        $this->line('Health Score: ' . $report['health_score'] . '/100');
        
        if ($report['health_score'] >= 90) {
            $this->info('🟢 Excellent performance');
        } elseif ($report['health_score'] >= 70) {
            $this->warn('🟡 Good performance with room for improvement');
        } else {
            $this->error('🔴 Performance issues detected');
        }
        
        $this->newLine();
        
        // Database metrics
        if (isset($report['metrics']['database'])) {
            $this->displayDatabaseMetrics($report['metrics']['database']);
        }
        
        // Cache metrics
        if (isset($report['metrics']['cache'])) {
            $this->displayCacheMetrics($report['metrics']['cache']);
        }
        
        // Memory metrics
        if (isset($report['metrics']['memory'])) {
            $this->displayMemoryMetrics($report['metrics']['memory']);
        }
        
        // Slow queries
        if (isset($report['metrics']['slow_queries'])) {
            $this->displaySlowQueryMetrics($report['metrics']['slow_queries']);
        }
        
        // Recommendations
        if (!empty($report['recommendations'])) {
            $this->newLine();
            $this->warn('💡 Recommendations:');
            foreach ($report['recommendations'] as $recommendation) {
                $this->line("  • {$recommendation}");
            }
        }
    }

    /**
     * Display database metrics.
     */
    protected function displayDatabaseMetrics(array $metrics): void
    {
        $this->info('🗄️  Database Metrics:');
        $this->line("  Driver: {$metrics['driver']}");
        
        if (isset($metrics['connections'])) {
            $this->line("  Active Connections: {$metrics['connections']['current'] ?? $metrics['connections']['active'] ?? 'N/A'}");
        }
        
        if (isset($metrics['queries'])) {
            $this->line("  Total Queries: {$metrics['queries']['total']}");
            $this->line("  Slow Queries: {$metrics['queries']['slow']}");
            $this->line("  Queries per Second: {$metrics['queries']['qps']}");
        }
        
        if (isset($metrics['innodb']['buffer_pool_hit_rate'])) {
            $hitRate = $metrics['innodb']['buffer_pool_hit_rate'];
            $color = $hitRate >= 95 ? 'info' : ($hitRate >= 90 ? 'warn' : 'error');
            $this->$color("  Buffer Pool Hit Rate: {$hitRate}%");
        }
        
        if (isset($metrics['cache_hit_rate'])) {
            $hitRate = $metrics['cache_hit_rate'];
            $color = $hitRate >= 95 ? 'info' : ($hitRate >= 90 ? 'warn' : 'error');
            $this->$color("  Cache Hit Rate: {$hitRate}%");
        }
    }

    /**
     * Display cache metrics.
     */
    protected function displayCacheMetrics(array $metrics): void
    {
        $this->info('💾 Cache Metrics:');
        $this->line("  Driver: {$metrics['driver']}");
        $this->line("  Status: {$metrics['status']}");
        
        if (isset($metrics['memory_usage'])) {
            $this->line("  Memory Usage: {$metrics['memory_usage']}");
        }
        
        if (isset($metrics['key_count'])) {
            $this->line("  Cached Keys: {$metrics['key_count']}");
        }
    }

    /**
     * Display memory metrics.
     */
    protected function displayMemoryMetrics(array $metrics): void
    {
        $this->info('🧠 Memory Metrics:');
        $this->line("  Current Usage: {$metrics['current_usage_formatted']}");
        $this->line("  Peak Usage: {$metrics['peak_usage_formatted']}");
        $this->line("  Memory Limit: {$metrics['memory_limit']}");
    }

    /**
     * Display slow query metrics.
     */
    protected function displaySlowQueryMetrics(array $metrics): void
    {
        $this->info('🐌 Slow Query Metrics:');
        $this->line("  Total Slow Queries: {$metrics['count']}");
        
        if (isset($metrics['recent_count'])) {
            $color = $metrics['recent_count'] == 0 ? 'info' : ($metrics['recent_count'] < 5 ? 'warn' : 'error');
            $this->$color("  Recent Slow Queries (1h): {$metrics['recent_count']}");
        }
        
        if (isset($metrics['average_time'])) {
            $this->line("  Average Execution Time: " . round($metrics['average_time'], 2) . "ms");
        }
    }

    /**
     * Show help information.
     */
    protected function showHelp(): void
    {
        $this->info('Database Optimization Tool');
        $this->newLine();
        $this->line('Available options:');
        $this->line('  --tables   Optimize database tables and update statistics');
        $this->line('  --cache    Warm up application cache with frequently accessed data');
        $this->line('  --analyze  Analyze database performance and generate report');
        $this->line('  --all      Run all optimization strategies');
        $this->newLine();
        $this->line('Examples:');
        $this->line('  php artisan db:optimize --all');
        $this->line('  php artisan db:optimize --tables --cache');
        $this->line('  php artisan db:optimize --analyze');
    }
}