<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\PerformanceMonitoringService;
use App\Services\CachingService;
use App\Services\DatabaseOptimizationService;

class PerformanceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(CachingService::class);
        $this->app->singleton(DatabaseOptimizationService::class);
        $this->app->singleton(PerformanceMonitoringService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Enable query performance monitoring in non-production environments
        if (!$this->app->environment('production') || config('app.debug')) {
            $performanceService = $this->app->make(PerformanceMonitoringService::class);
            $performanceService->monitorQueryPerformance();
        }

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\OptimizeDatabaseCommand::class,
            ]);
        }
    }
}