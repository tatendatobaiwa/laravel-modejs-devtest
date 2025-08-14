<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CachingService
{
    /**
     * Cache duration constants (in minutes).
     */
    public const SHORT_CACHE = 5;      // 5 minutes
    public const MEDIUM_CACHE = 60;    // 1 hour
    public const LONG_CACHE = 1440;    // 24 hours
    public const EXTENDED_CACHE = 10080; // 1 week

    /**
     * Cache key prefixes for organization.
     */
    public const PREFIX_USER = 'user:';
    public const PREFIX_SALARY = 'salary:';
    public const PREFIX_ADMIN = 'admin:';
    public const PREFIX_STATS = 'stats:';
    public const PREFIX_API = 'api:';

    /**
     * Cache tags for group invalidation.
     */
    public const TAG_USERS = 'users';
    public const TAG_SALARIES = 'salaries';
    public const TAG_STATISTICS = 'statistics';
    public const TAG_ADMIN = 'admin';

    /**
     * Remember a value in cache with automatic key generation.
     */
    public function remember(string $key, int $minutes, callable $callback, array $tags = [])
    {
        $fullKey = $this->generateCacheKey($key);
        
        try {
            if ($this->supportsTagging() && !empty($tags)) {
                return Cache::tags($tags)->remember($fullKey, $minutes, $callback);
            }
            
            return Cache::remember($fullKey, $minutes, $callback);
        } catch (\Exception $e) {
            Log::warning('Cache remember failed', [
                'key' => $fullKey,
                'error' => $e->getMessage(),
            ]);
            
            // Fallback to direct execution
            return $callback();
        }
    }

    /**
     * Store a value in cache.
     */
    public function put(string $key, $value, int $minutes = null, array $tags = []): bool
    {
        $fullKey = $this->generateCacheKey($key);
        $minutes = $minutes ?? self::MEDIUM_CACHE;
        
        try {
            if ($this->supportsTagging() && !empty($tags)) {
                return Cache::tags($tags)->put($fullKey, $value, $minutes);
            }
            
            return Cache::put($fullKey, $value, $minutes);
        } catch (\Exception $e) {
            Log::warning('Cache put failed', [
                'key' => $fullKey,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Get a value from cache.
     */
    public function get(string $key, $default = null)
    {
        $fullKey = $this->generateCacheKey($key);
        
        try {
            return Cache::get($fullKey, $default);
        } catch (\Exception $e) {
            Log::warning('Cache get failed', [
                'key' => $fullKey,
                'error' => $e->getMessage(),
            ]);
            
            return $default;
        }
    }

    /**
     * Forget a cache key.
     */
    public function forget(string $key): bool
    {
        $fullKey = $this->generateCacheKey($key);
        
        try {
            return Cache::forget($fullKey);
        } catch (\Exception $e) {
            Log::warning('Cache forget failed', [
                'key' => $fullKey,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Flush cache by tags.
     */
    public function flushByTags(array $tags): bool
    {
        if (!$this->supportsTagging()) {
            Log::info('Cache tagging not supported, skipping tag flush');
            return false;
        }
        
        try {
            Cache::tags($tags)->flush();
            
            Log::info('Cache flushed by tags', ['tags' => $tags]);
            return true;
        } catch (\Exception $e) {
            Log::warning('Cache flush by tags failed', [
                'tags' => $tags,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Cache user data with appropriate duration and tags.
     */
    public function cacheUserData(int $userId, array $data, int $minutes = null): bool
    {
        $key = self::PREFIX_USER . "data:{$userId}";
        $minutes = $minutes ?? self::MEDIUM_CACHE;
        
        return $this->put($key, $data, $minutes, [self::TAG_USERS]);
    }

    /**
     * Get cached user data.
     */
    public function getUserData(int $userId, $default = null)
    {
        $key = self::PREFIX_USER . "data:{$userId}";
        return $this->get($key, $default);
    }

    /**
     * Cache salary statistics.
     */
    public function cacheSalaryStatistics(array $stats): bool
    {
        $key = self::PREFIX_STATS . 'salary_overview';
        return $this->put($key, $stats, self::LONG_CACHE, [self::TAG_STATISTICS, self::TAG_SALARIES]);
    }

    /**
     * Get cached salary statistics.
     */
    public function getSalaryStatistics($default = null)
    {
        $key = self::PREFIX_STATS . 'salary_overview';
        return $this->get($key, $default);
    }

    /**
     * Cache admin dashboard data.
     */
    public function cacheAdminDashboard(array $data): bool
    {
        $key = self::PREFIX_ADMIN . 'dashboard';
        return $this->put($key, $data, self::MEDIUM_CACHE, [self::TAG_ADMIN, self::TAG_STATISTICS]);
    }

    /**
     * Get cached admin dashboard data.
     */
    public function getAdminDashboard($default = null)
    {
        $key = self::PREFIX_ADMIN . 'dashboard';
        return $this->get($key, $default);
    }

    /**
     * Cache API response with rate limiting consideration.
     */
    public function cacheApiResponse(string $endpoint, array $params, $response, int $minutes = null): bool
    {
        $key = self::PREFIX_API . md5($endpoint . serialize($params));
        $minutes = $minutes ?? self::SHORT_CACHE;
        
        return $this->put($key, $response, $minutes, [self::TAG_API]);
    }

    /**
     * Get cached API response.
     */
    public function getApiResponse(string $endpoint, array $params, $default = null)
    {
        $key = self::PREFIX_API . md5($endpoint . serialize($params));
        return $this->get($key, $default);
    }

    /**
     * Invalidate user-related caches.
     */
    public function invalidateUserCaches(int $userId = null): void
    {
        if ($userId) {
            // Invalidate specific user caches
            $patterns = [
                self::PREFIX_USER . "data:{$userId}",
                self::PREFIX_USER . "salary:{$userId}",
                self::PREFIX_USER . "history:{$userId}",
            ];
            
            foreach ($patterns as $pattern) {
                $this->forget($pattern);
            }
        }
        
        // Invalidate general user caches
        $this->flushByTags([self::TAG_USERS]);
        
        Log::info('User caches invalidated', ['user_id' => $userId]);
    }

    /**
     * Invalidate salary-related caches.
     */
    public function invalidateSalaryCaches(): void
    {
        $this->flushByTags([self::TAG_SALARIES, self::TAG_STATISTICS]);
        
        Log::info('Salary caches invalidated');
    }

    /**
     * Invalidate admin-related caches.
     */
    public function invalidateAdminCaches(): void
    {
        $this->flushByTags([self::TAG_ADMIN, self::TAG_STATISTICS]);
        
        Log::info('Admin caches invalidated');
    }

    /**
     * Get cache statistics and health information.
     */
    public function getCacheHealth(): array
    {
        $driver = config('cache.default');
        $health = [
            'driver' => $driver,
            'status' => 'unknown',
            'memory_usage' => null,
            'hit_rate' => null,
            'key_count' => null,
        ];
        
        try {
            switch ($driver) {
                case 'redis':
                    $health = array_merge($health, $this->getRedisHealth());
                    break;
                    
                case 'memcached':
                    $health = array_merge($health, $this->getMemcachedHealth());
                    break;
                    
                case 'file':
                    $health = array_merge($health, $this->getFileHealth());
                    break;
                    
                default:
                    $health['status'] = 'unsupported';
            }
        } catch (\Exception $e) {
            $health['status'] = 'error';
            $health['error'] = $e->getMessage();
        }
        
        return $health;
    }

    /**
     * Warm up frequently accessed caches.
     */
    public function warmUpCaches(): array
    {
        $results = [];
        
        try {
            // Warm up salary statistics
            $dbService = app(DatabaseOptimizationService::class);
            $stats = $dbService->getSalaryStatistics();
            $this->cacheSalaryStatistics($stats);
            $results[] = 'Salary statistics warmed up';
            
            // Warm up recent changes
            $recentChanges = $dbService->getRecentSalaryChanges(7);
            $this->put(self::PREFIX_STATS . 'recent_changes', $recentChanges, self::MEDIUM_CACHE, [self::TAG_STATISTICS]);
            $results[] = 'Recent changes warmed up';
            
            // Warm up currency distribution
            $distribution = $dbService->getSalaryDistributionByCurrency();
            $this->put(self::PREFIX_STATS . 'currency_distribution', $distribution, self::LONG_CACHE, [self::TAG_STATISTICS]);
            $results[] = 'Currency distribution warmed up';
            
            // Warm up user statistics
            $userStats = $dbService->getUserStatistics();
            $this->put(self::PREFIX_STATS . 'user_overview', $userStats, self::LONG_CACHE, [self::TAG_STATISTICS, self::TAG_USERS]);
            $results[] = 'User statistics warmed up';
            
            // Warm up salary trends
            $trends = $dbService->getSalaryTrends(12);
            $this->put(self::PREFIX_STATS . 'salary_trends_12m', $trends, self::LONG_CACHE, [self::TAG_STATISTICS, self::TAG_SALARIES]);
            $results[] = 'Salary trends warmed up';
            
            // Warm up top earners
            $topEarners = \App\Models\Salary::with('user:id,name,email')
                ->orderBy('displayed_salary', 'desc')
                ->limit(10)
                ->get();
            $this->put(self::PREFIX_STATS . 'top_earners', $topEarners, self::MEDIUM_CACHE, [self::TAG_SALARIES, self::TAG_USERS]);
            $results[] = 'Top earners warmed up';
            
        } catch (\Exception $e) {
            $results[] = 'Error warming up caches: ' . $e->getMessage();
            Log::error('Cache warm-up failed', ['error' => $e->getMessage()]);
        }
        
        return $results;
    }

    /**
     * Cache query results with automatic invalidation.
     */
    public function cacheQuery(string $key, callable $callback, int $minutes = null, array $tags = [])
    {
        $minutes = $minutes ?? self::MEDIUM_CACHE;
        return $this->remember($key, $minutes, $callback, $tags);
    }

    /**
     * Cache paginated results with metadata.
     */
    public function cachePaginatedResults(string $key, callable $callback, int $minutes = null): array
    {
        $minutes = $minutes ?? self::SHORT_CACHE;
        
        return $this->remember($key, $minutes, function() use ($callback) {
            $results = $callback();
            
            if (method_exists($results, 'toArray')) {
                return [
                    'data' => $results->items(),
                    'pagination' => [
                        'current_page' => $results->currentPage(),
                        'last_page' => $results->lastPage(),
                        'per_page' => $results->perPage(),
                        'total' => $results->total(),
                        'from' => $results->firstItem(),
                        'to' => $results->lastItem(),
                    ],
                    'cached_at' => now()->toISOString(),
                ];
            }
            
            return $results;
        }, [self::TAG_API]);
    }

    /**
     * Intelligent cache warming based on usage patterns.
     */
    public function intelligentWarmUp(): array
    {
        $results = [];
        
        try {
            // Get most accessed cache keys from logs or metrics
            $popularKeys = $this->getPopularCacheKeys();
            
            foreach ($popularKeys as $key => $callback) {
                try {
                    $this->remember($key, self::MEDIUM_CACHE, $callback);
                    $results[] = "Warmed up popular key: {$key}";
                } catch (\Exception $e) {
                    $results[] = "Failed to warm up {$key}: " . $e->getMessage();
                }
            }
            
        } catch (\Exception $e) {
            $results[] = 'Error in intelligent warm-up: ' . $e->getMessage();
        }
        
        return $results;
    }

    /**
     * Get popular cache keys based on usage patterns.
     */
    private function getPopularCacheKeys(): array
    {
        // This would typically come from analytics or metrics
        // For now, return commonly used queries
        return [
            'admin_dashboard_30d' => function() {
                return app(\App\Http\Controllers\Api\AdminController::class)->dashboard(request());
            },
            'salary_statistics_overview' => function() {
                return app(\App\Services\DatabaseOptimizationService::class)->getSalaryStatistics();
            },
            'users_with_salary_count' => function() {
                return \App\Models\User::whereHas('salary')->count();
            },
        ];
    }

    /**
     * Generate a consistent cache key.
     */
    private function generateCacheKey(string $key): string
    {
        $prefix = config('cache.prefix', 'laravel_cache');
        return $prefix . ':' . $key;
    }

    /**
     * Check if the current cache driver supports tagging.
     */
    private function supportsTagging(): bool
    {
        $driver = config('cache.default');
        return in_array($driver, ['redis', 'memcached', 'array']);
    }

    /**
     * Get Redis cache health information.
     */
    private function getRedisHealth(): array
    {
        try {
            $redis = Redis::connection();
            $info = $redis->info();
            
            return [
                'status' => 'healthy',
                'memory_usage' => $info['used_memory_human'] ?? null,
                'key_count' => $redis->dbsize(),
                'connected_clients' => $info['connected_clients'] ?? null,
                'uptime' => $info['uptime_in_seconds'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Memcached cache health information.
     */
    private function getMemcachedHealth(): array
    {
        try {
            // This would require additional implementation for Memcached
            return [
                'status' => 'healthy',
                'note' => 'Memcached health check not fully implemented',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get file cache health information.
     */
    private function getFileHealth(): array
    {
        try {
            $cachePath = storage_path('framework/cache');
            $size = 0;
            $count = 0;
            
            if (is_dir($cachePath)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($cachePath)
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $size += $file->getSize();
                        $count++;
                    }
                }
            }
            
            return [
                'status' => 'healthy',
                'cache_size' => $this->formatBytes($size),
                'file_count' => $count,
                'cache_path' => $cachePath,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Format bytes into human readable format.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}