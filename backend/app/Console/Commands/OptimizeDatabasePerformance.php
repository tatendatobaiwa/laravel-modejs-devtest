<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DatabaseOptimizationService;
use App\Services\AdvancedCachingService;
use App\Services\QueryOptimizationService;
use Illuminate\Support\Facades\Log;

class OptimizeDatabasePerformance extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'db:optimize-performance 
                            {--tables : Optimize database tables}
                            {--cache : Warm up caches}
                            {--indexes : Analyze and optimize indexes}
                            {--analyze : Run performance analysis}
                            {--all : Run all optimizations}
                            {--force : Force optimization even in production}';

    /**
     * The console command description.
     */
    protected $description = 'Optimize database performance with various strategies';

    protected DatabaseOptimizationService $dbOptimizationService;
    protected AdvancedCachingService $cachingService;
    protected QueryOptimizationService $queryOptimizationService;

    public function __construct(
        DatabaseOptimizationService $dbOptimizationService,
        AdvancedCachingService $cachingService,
        QueryOptimizationService $queryOptimizationService
    ) {
        parent::__construct();
        $this->dbOptimizationService = $dbOptimizationService;
        $this->cachingService = $cachingService;
        $this->queryOptimizationService = $queryOptimizationService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting Database Performance Optimization');
        $this->newLine();

        // Check if running in production without force flag
        if (app()->environment('production') && !$this->option('force')) {
            if (!$this->confirm('You are running this in production. Are you sure you want to continue?')) {
                $this->warn('Operation cancelled.');
                return Command::FAILURE;
            }
        }

        $startTime = microtime(true);
        $operations = [];

        try {
            // Determine which operations to run
            if ($this->option('all')) {
                $operations = ['tables', 'cache', 'indexes', 'analyze'];
            } else {
                if ($this->option('tables')) $operations[] = 'tables';
                if ($this->option('cache')) $operations[] = 'cache';
                if ($this->option('indexes')) $operations[] = 'indexes';
                if ($this->option('analyze')) $operations[] = 'analyze';
            }

            // If no specific options, ask user
            if (empty($operations)) {
                $operations = $this->choice(
                    'Which optimizations would you like to run?',
                    ['tables', 'cache', 'indexes', 'analyze', 'all'],
                    null,
                    null,
                    true
                );
                
                if (in_array('all', $operations)) {
                    $operations = ['tables', 'cache', 'indexes', 'analyze'];
                }
            }

            // Run selected operations
            foreach ($operations as $operation) {
                $this->runOperation($operation);
                $this->newLine();
            }

            $totalTime = round((microtime(true) - $startTime), 2);
            $this->info("✅ All optimizations completed successfully in {$totalTime} seconds");

            // Log the optimization run
            Log::info('Database performance optimization completed', [
                'operations' => $operations,
                'duration_seconds' => $totalTime,
                'environment' => app()->environment()
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Optimization failed: ' . $e->getMessage());
            
            Log::error('Database performance optimization failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'operations' => $operations ?? []
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Run a specific optimization operation.
     */
    protected function runOperation(string $operation): void
    {
        $startTime = microtime(true);

        switch ($operation) {
            case 'tables':
                $this->optimizeTables();
                break;
                
            case 'cache':
                $this->optimizeCache();
                break;
                
            case 'indexes':
                $this->optimizeIndexes();
                break;
                
            case 'analyze':
                $this->runPerformanceAnalysis();
                break;
                
            default:
                $this->warn("Unknown operation: {$operation}");
                return;
        }

        $duration = round((microtime(true) - $startTime), 2);
        $this->info("Operation '{$operation}' completed in {$duration} seconds");
    }

    /**
     * Optimize database tables.
     */
    protected function optimizeTables(): void
    {
        $this->info('🔧 Optimizing database tables...');
        
        $bar = $this->output->createProgressBar(4);
        $bar->setFormat('verbose');
        
        $bar->setMessage('Analyzing table structure...');
        $bar->advance();
        
        $bar->setMessage('Running table optimization...');
        $results = $this->dbOptimizationService->optimizeTables();
        $bar->advance();
        
        $bar->setMessage('Updating statistics...');
        $bar->advance();
        
        $bar->setMessage('Finalizing...');
        $bar->advance();
        
        $bar->finish();
        $this->newLine();
        
        foreach ($results as $result) {
            $this->line("  • {$result}");
        }
    }

    /**
     * Optimize cache performance.
     */
    protected function optimizeCache(): void
    {
        $this->info('💾 Optimizing cache performance...');
        
        $bar = $this->output->createProgressBar(3);
        $bar->setFormat('verbose');
        
        $bar->setMessage('Warming critical caches...');
        $warmResults = $this->cachingService->warmCriticalCaches();
        $bar->advance();
        
        $bar->setMessage('Analyzing cache health...');
        $healthResults = $this->cachingService->getCacheHealth();
        $bar->advance();
        
        $bar->setMessage('Generating cache analytics...');
        $analytics = $this->cachingService->getCacheAnalytics();
        $bar->advance();
        
        $bar->finish();
        $this->newLine();
        
        // Display cache warming results
        $this->line('Cache Warming Results:');
        foreach ($warmResults as $result) {
            $this->line("  • {$result}");
        }
        
        // Display cache health
        $this->newLine();
        $this->line('Cache Health Status:');
        $this->line("  • Driver: {$healthResults['driver']}");
        $this->line("  • Status: {$healthResults['status']}");
        
        if (isset($healthResults['memory_usage'])) {
            $this->line("  • Memory Usage: {$healthResults['memory_usage']}");
        }
        
        // Display hit rate if available
        if (isset($analytics['hit_rate']['hit_rate_percentage'])) {
            $hitRate = $analytics['hit_rate']['hit_rate_percentage'];
            $this->line("  • Hit Rate: {$hitRate}%");
            
            if ($hitRate < 80) {
                $this->warn("  ⚠️  Cache hit rate is below 80%. Consider reviewing cache strategies.");
            }
        }
    }

    /**
     * Optimize database indexes.
     */
    protected function optimizeIndexes(): void
    {
        $this->info('📊 Analyzing and optimizing indexes...');
        
        $bar = $this->output->createProgressBar(2);
        $bar->setFormat('verbose');
        
        $bar->setMessage('Analyzing index usage...');
        $analysis = $this->dbOptimizationService->getPerformanceAnalysis();
        $bar->advance();
        
        $bar->setMessage('Generating recommendations...');
        $recommendations = $analysis['recommendations'] ?? [];
        $bar->advance();
        
        $bar->finish();
        $this->newLine();
        
        // Display index analysis results
        if (isset($analysis['index_analysis'])) {
            $this->line('Index Analysis:');
            foreach ($analysis['index_analysis'] as $table => $indexes) {
                if (is_array($indexes)) {
                    $indexCount = count($indexes);
                    $this->line("  • Table '{$table}': {$indexCount} indexes");
                }
            }
        }
        
        // Display recommendations
        if (!empty($recommendations)) {
            $this->newLine();
            $this->line('Optimization Recommendations:');
            foreach ($recommendations as $recommendation) {
                $this->line("  • {$recommendation}");
            }
        }
    }

    /**
     * Run comprehensive performance analysis.
     */
    protected function runPerformanceAnalysis(): void
    {
        $this->info('📈 Running comprehensive performance analysis...');
        
        $bar = $this->output->createProgressBar(4);
        $bar->setFormat('verbose');
        
        $bar->setMessage('Analyzing database metrics...');
        $analysis = $this->dbOptimizationService->getPerformanceAnalysis();
        $bar->advance();
        
        $bar->setMessage('Checking query performance...');
        $queryMetrics = $analysis['query_performance'] ?? [];
        $bar->advance();
        
        $bar->setMessage('Analyzing table statistics...');
        $tableAnalysis = $analysis['table_analysis'] ?? [];
        $bar->advance();
        
        $bar->setMessage('Generating report...');
        $bar->advance();
        
        $bar->finish();
        $this->newLine();
        
        // Display general metrics
        if (isset($analysis['general_metrics'])) {
            $this->line('Database Overview:');
            $metrics = $analysis['general_metrics'];
            $this->line("  • Total Users: " . number_format($metrics['total_users'] ?? 0));
            $this->line("  • Total Salaries: " . number_format($metrics['total_salaries'] ?? 0));
            $this->line("  • Total History Records: " . number_format($metrics['total_salary_histories'] ?? 0));
            $this->line("  • Total Documents: " . number_format($metrics['total_documents'] ?? 0));
            
            if (isset($metrics['database_size'])) {
                $this->line("  • Database Size: {$metrics['database_size']}");
            }
        }
        
        // Display table analysis
        if (!empty($tableAnalysis)) {
            $this->newLine();
            $this->line('Table Analysis:');
            foreach ($tableAnalysis as $table => $stats) {
                $rowCount = number_format($stats['row_count'] ?? 0);
                $size = $stats['size'] ?? 'Unknown';
                $this->line("  • {$table}: {$rowCount} rows, {$size}");
            }
        }
        
        // Display query performance metrics
        if (!empty($queryMetrics) && !isset($queryMetrics['error'])) {
            $this->newLine();
            $this->line('Query Performance:');
            
            if (isset($queryMetrics['queries_per_second'])) {
                $qps = round($queryMetrics['queries_per_second'], 2);
                $this->line("  • Queries per second: {$qps}");
            }
            
            if (isset($queryMetrics['slow_queries'])) {
                $slowQueries = $queryMetrics['slow_queries'];
                $this->line("  • Slow queries: {$slowQueries}");
                
                if ($slowQueries > 0) {
                    $this->warn("  ⚠️  {$slowQueries} slow queries detected. Consider optimization.");
                }
            }
            
            if (isset($queryMetrics['connections'])) {
                $connections = $queryMetrics['connections'];
                $this->line("  • Active connections: {$connections}");
            }
        }
        
        // Display driver-specific information
        $driver = $analysis['driver'] ?? 'unknown';
        $this->newLine();
        $this->line("Database Driver: {$driver}");
        
        if (isset($analysis['mysql_specific'])) {
            $this->displayMySQLSpecificInfo($analysis['mysql_specific']);
        } elseif (isset($analysis['postgresql_specific'])) {
            $this->displayPostgreSQLSpecificInfo($analysis['postgresql_specific']);
        }
    }

    /**
     * Display MySQL-specific information.
     */
    protected function displayMySQLSpecificInfo(array $mysqlInfo): void
    {
        $this->line('MySQL Configuration:');
        
        if (isset($mysqlInfo['innodb_buffer_pool_size'])) {
            $bufferPool = $this->formatBytes((int) $mysqlInfo['innodb_buffer_pool_size']);
            $this->line("  • InnoDB Buffer Pool: {$bufferPool}");
        }
        
        if (isset($mysqlInfo['max_connections'])) {
            $this->line("  • Max Connections: {$mysqlInfo['max_connections']}");
        }
        
        if (isset($mysqlInfo['query_cache_size'])) {
            $queryCache = $this->formatBytes((int) $mysqlInfo['query_cache_size']);
            $this->line("  • Query Cache Size: {$queryCache}");
        }
    }

    /**
     * Display PostgreSQL-specific information.
     */
    protected function displayPostgreSQLSpecificInfo(array $pgInfo): void
    {
        $this->line('PostgreSQL Configuration:');
        
        foreach ($pgInfo as $setting => $value) {
            if (!isset($pgInfo['error'])) {
                $this->line("  • " . ucwords(str_replace('_', ' ', $setting)) . ": {$value}");
            }
        }
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
}