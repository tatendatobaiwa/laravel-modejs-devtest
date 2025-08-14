<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AdvancedCachingService extends CachingService
{
    /**
     * Cache warming strategies for frequently accessed data.
     */
    public function warmCriticalCaches(): array
    {
        $results = [];
        
        try {
            // Warm up user statistics
            $userStats = $this->warmUserStatistics();
            $results[] = "User statistics warmed: {$userStats} entries";
            
            // Warm up salary data
            $salaryStats = $this->warmSalaryStatistics();
            $results[] = "Salary statistics warmed: {$salaryStats} entries";
            
            // Warm up recent activities
            $activities = $this->warmRecentActivities();
            $results[] = "Recent activities warmed: {$activities} entries";
            
            // Warm up top performers
            $topPerformers = $this->warmTopPerformers();
            $results[] = "Top performers warmed: {$topPerformers} entries";
            
            // Warm up currency distributions
            $currencies = $this->warmCurrencyDistribution();
            $results[] = "Currency distributions warmed: {$currencies} entries";
            
        } catch (\Exception $e) {
            $results[] = "Error warming caches: " . $e->getMessage();
            Log::error('Cache warming failed', ['error' => $e->getMessage()]);
        }
        
        return $results;
    }

    /**
     * Implement intelligent cache invalidation based on data relationships.
     */
    public function intelligentInvalidation(string $entityType, int $entityId, array $context = []): void
    {
        switch ($entityType) {
            case 'user':
                $this->invalidateUserRelatedCaches($entityId, $context);
                break;
                
            case 'salary':
                $this->invalidateSalaryRelatedCaches($entityId, $context);
                break;
                
            case 'salary_history':
                $this->invalidateSalaryHistoryRelatedCaches($entityId, $context);
                break;
                
            case 'document':
                $this->invalidateDocumentRelatedCaches($entityId, $context);
                break;
        }
    }

    /**
     * Implement cache preloading for predictive performance.
     */
    public function preloadPredictiveCaches(array $userBehaviorData): array
    {
        $results = [];
        
        // Analyze user behavior patterns
        $patterns = $this->analyzeUserPatterns($userBehaviorData);
        
        foreach ($patterns as $pattern) {
            switch ($pattern['type']) {
                case 'frequent_user_lookup':
                    $this->preloadUserData($pattern['user_ids']);
                    $results[] = "Preloaded user data for {$pattern['count']} users";
                    break;
                    
                case 'salary_range_queries':
                    $this->preloadSalaryRangeData($pattern['ranges']);
                    $results[] = "Preloaded salary range data for {$pattern['count']} ranges";
                    break;
                    
                case 'statistics_requests':
                    $this->preloadStatisticsData($pattern['types']);
                    $results[] = "Preloaded statistics for {$pattern['count']} types";
                    break;
            }
        }
        
        return $results;
    }

    /**
     * Implement distributed caching with Redis clustering.
     */
    public function distributedCacheSet(string $key, $value, int $ttl, array $options = []): bool
    {
        if (!$this->isRedisAvailable()) {
            return $this->put($key, $value, $ttl);
        }
        
        try {
            $redis = Redis::connection();
            
            // Use Redis pipeline for better performance
            $pipeline = $redis->pipeline();
            
            // Set main cache entry
            $pipeline->setex($key, $ttl * 60, serialize($value));
            
            // Set backup entries on multiple nodes if clustering is enabled
            if ($options['replicate'] ?? false) {
                $backupKey = $key . ':backup';
                $pipeline->setex($backupKey, $ttl * 60, serialize($value));
            }
            
            // Add to cache index for easier management
            if ($options['index'] ?? true) {
                $indexKey = 'cache_index:' . ($options['category'] ?? 'general');
                $pipeline->sadd($indexKey, $key);
                $pipeline->expire($indexKey, ($ttl + 3600) * 60); // Index lives longer
            }
            
            $results = $pipeline->execute();
            
            return $results[0] === 'OK';
            
        } catch (\Exception $e) {
            Log::warning('Distributed cache set failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to regular cache
            return $this->put($key, $value, $ttl);
        }
    }

    /**
     * Implement cache compression for large datasets.
     */
    public function compressedCacheSet(string $key, $value, int $ttl, array $options = []): bool
    {
        $serialized = serialize($value);
        $originalSize = strlen($serialized);
        
        // Only compress if data is larger than threshold
        $compressionThreshold = $options['compression_threshold'] ?? 1024; // 1KB
        
        if ($originalSize > $compressionThreshold) {
            $compressed = gzcompress($serialized, $options['compression_level'] ?? 6);
            $compressedSize = strlen($compressed);
            
            if ($compressedSize < $originalSize * 0.8) { // Only use if 20%+ savings
                $cacheData = [
                    'compressed' => true,
                    'data' => base64_encode($compressed),
                    'original_size' => $originalSize,
                    'compressed_size' => $compressedSize,
                ];
                
                Log::debug('Cache compression applied', [
                    'key' => $key,
                    'original_size' => $originalSize,
                    'compressed_size' => $compressedSize,
                    'savings' => round((1 - $compressedSize / $originalSize) * 100, 2) . '%'
                ]);
                
                return $this->put($key, $cacheData, $ttl);
            }
        }
        
        // Store uncompressed
        return $this->put($key, ['compressed' => false, 'data' => $value], $ttl);
    }

    /**
     * Get compressed cache data.
     */
    public function compressedCacheGet(string $key, $default = null)
    {
        $cacheData = $this->get($key);
        
        if (!$cacheData) {
            return $default;
        }
        
        if (!is_array($cacheData) || !isset($cacheData['compressed'])) {
            return $cacheData; // Legacy cache entry
        }
        
        if ($cacheData['compressed']) {
            try {
                $compressed = base64_decode($cacheData['data']);
                $decompressed = gzuncompress($compressed);
                return unserialize($decompressed);
            } catch (\Exception $e) {
                Log::warning('Cache decompression failed', [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
                return $default;
            }
        }
        
        return $cacheData['data'];
    }

    /**
     * Implement cache analytics and monitoring.
     */
    public function getCacheAnalytics(): array
    {
        $analytics = [
            'cache_health' => $this->getCacheHealth(),
            'hit_rate' => $this->calculateHitRate(),
            'memory_usage' => $this->getCacheMemoryUsage(),
            'key_distribution' => $this->getCacheKeyDistribution(),
            'performance_metrics' => $this->getCachePerformanceMetrics(),
            'recommendations' => $this->getCacheRecommendations(),
        ];
        
        return $analytics;
    }

    /**
     * Warm user statistics cache.
     */
    private function warmUserStatistics(): int
    {
        $stats = [
            'total_users' => DB::table('users')->count(),
            'active_users' => DB::table('users')->whereNull('deleted_at')->count(),
            'users_with_salary' => DB::table('users')->whereExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('salaries')
                      ->whereRaw('salaries.user_id = users.id');
            })->count(),
            'recent_registrations' => DB::table('users')
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
        ];
        
        $this->put(self::PREFIX_STATS . 'user_overview', $stats, self::LONG_CACHE, [self::TAG_STATISTICS]);
        
        return count($stats);
    }

    /**
     * Warm salary statistics cache.
     */
    private function warmSalaryStatistics(): int
    {
        $stats = DB::table('salaries')
            ->selectRaw('
                COUNT(*) as total_salaries,
                AVG(salary_euros) as avg_salary,
                MIN(salary_euros) as min_salary,
                MAX(salary_euros) as max_salary,
                AVG(commission) as avg_commission,
                SUM(displayed_salary) as total_compensation
            ')
            ->first();
        
        $this->put(self::PREFIX_STATS . 'salary_overview', $stats, self::LONG_CACHE, [self::TAG_STATISTICS]);
        
        return 6; // Number of statistics
    }

    /**
     * Warm recent activities cache.
     */
    private function warmRecentActivities(): int
    {
        $activities = DB::table('salary_histories')
            ->join('users', 'salary_histories.user_id', '=', 'users.id')
            ->select([
                'salary_histories.id',
                'salary_histories.user_id',
                'users.name as user_name',
                'salary_histories.old_salary_euros',
                'salary_histories.new_salary_euros',
                'salary_histories.change_reason',
                'salary_histories.created_at'
            ])
            ->where('salary_histories.created_at', '>=', now()->subDays(7))
            ->orderBy('salary_histories.created_at', 'desc')
            ->limit(50)
            ->get();
        
        $this->put(self::PREFIX_STATS . 'recent_activities', $activities, self::SHORT_CACHE, [self::TAG_STATISTICS]);
        
        return $activities->count();
    }

    /**
     * Warm top performers cache.
     */
    private function warmTopPerformers(): int
    {
        $topPerformers = DB::table('salaries')
            ->join('users', 'salaries.user_id', '=', 'users.id')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'salaries.salary_euros',
                'salaries.commission',
                'salaries.displayed_salary'
            ])
            ->whereNull('users.deleted_at')
            ->orderBy('salaries.displayed_salary', 'desc')
            ->limit(20)
            ->get();
        
        $this->put(self::PREFIX_STATS . 'top_performers', $topPerformers, self::MEDIUM_CACHE, [self::TAG_STATISTICS]);
        
        return $topPerformers->count();
    }

    /**
     * Warm currency distribution cache.
     */
    private function warmCurrencyDistribution(): int
    {
        $distribution = DB::table('salaries')
            ->select([
                'local_currency_code',
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(salary_euros) as avg_salary')
            ])
            ->groupBy('local_currency_code')
            ->orderBy('count', 'desc')
            ->get();
        
        $this->put(self::PREFIX_STATS . 'currency_distribution', $distribution, self::LONG_CACHE, [self::TAG_STATISTICS]);
        
        return $distribution->count();
    }

    /**
     * Invalidate user-related caches intelligently.
     */
    private function invalidateUserRelatedCaches(int $userId, array $context): void
    {
        // Direct user caches
        $this->forget(self::PREFIX_USER . "data:{$userId}");
        $this->forget(self::PREFIX_USER . "salary:{$userId}");
        
        // Related statistics caches
        $this->flushByTags([self::TAG_USERS, self::TAG_STATISTICS]);
        
        // If salary was affected, invalidate salary caches too
        if ($context['salary_affected'] ?? false) {
            $this->flushByTags([self::TAG_SALARIES]);
        }
        
        Log::info('Intelligent cache invalidation completed', [
            'entity_type' => 'user',
            'entity_id' => $userId,
            'context' => $context
        ]);
    }

    /**
     * Invalidate salary-related caches intelligently.
     */
    private function invalidateSalaryRelatedCaches(int $salaryId, array $context): void
    {
        // Get user ID for targeted invalidation
        $userId = $context['user_id'] ?? null;
        
        if ($userId) {
            $this->forget(self::PREFIX_USER . "salary:{$userId}");
            $this->forget(self::PREFIX_SALARY . "history:{$userId}");
        }
        
        // Invalidate statistics and salary-related caches
        $this->flushByTags([self::TAG_SALARIES, self::TAG_STATISTICS]);
        
        Log::info('Salary cache invalidation completed', [
            'salary_id' => $salaryId,
            'user_id' => $userId
        ]);
    }

    /**
     * Invalidate salary history related caches.
     */
    private function invalidateSalaryHistoryRelatedCaches(int $historyId, array $context): void
    {
        $userId = $context['user_id'] ?? null;
        
        if ($userId) {
            $this->forget(self::PREFIX_SALARY . "history:{$userId}");
        }
        
        // Invalidate recent activities and statistics
        $this->forget(self::PREFIX_STATS . 'recent_activities');
        $this->flushByTags([self::TAG_STATISTICS]);
    }

    /**
     * Invalidate document related caches.
     */
    private function invalidateDocumentRelatedCaches(int $documentId, array $context): void
    {
        $userId = $context['user_id'] ?? null;
        
        if ($userId) {
            $this->forget(self::PREFIX_USER . "documents:{$userId}");
        }
        
        // Document statistics might be affected
        $this->forget(self::PREFIX_STATS . 'document_overview');
    }

    /**
     * Analyze user behavior patterns for predictive caching.
     */
    private function analyzeUserPatterns(array $userBehaviorData): array
    {
        $patterns = [];
        
        // Analyze frequent user lookups
        if (isset($userBehaviorData['user_lookups'])) {
            $frequentUsers = array_filter($userBehaviorData['user_lookups'], function ($count) {
                return $count > 5; // Looked up more than 5 times
            });
            
            if (!empty($frequentUsers)) {
                $patterns[] = [
                    'type' => 'frequent_user_lookup',
                    'user_ids' => array_keys($frequentUsers),
                    'count' => count($frequentUsers)
                ];
            }
        }
        
        // Analyze salary range queries
        if (isset($userBehaviorData['salary_queries'])) {
            $patterns[] = [
                'type' => 'salary_range_queries',
                'ranges' => $userBehaviorData['salary_queries'],
                'count' => count($userBehaviorData['salary_queries'])
            ];
        }
        
        return $patterns;
    }

    /**
     * Preload user data based on patterns.
     */
    private function preloadUserData(array $userIds): void
    {
        $users = DB::table('users')
            ->whereIn('id', $userIds)
            ->with(['salary', 'uploadedDocuments'])
            ->get();
        
        foreach ($users as $user) {
            $this->cacheUserData($user->id, $user->toArray());
        }
    }

    /**
     * Preload salary range data.
     */
    private function preloadSalaryRangeData(array $ranges): void
    {
        foreach ($ranges as $range) {
            $key = "salary_range_{$range['min']}_{$range['max']}";
            
            $salaries = DB::table('salaries')
                ->join('users', 'salaries.user_id', '=', 'users.id')
                ->whereBetween('salary_euros', [$range['min'], $range['max']])
                ->get();
            
            $this->put($key, $salaries, self::MEDIUM_CACHE, [self::TAG_SALARIES]);
        }
    }

    /**
     * Preload statistics data.
     */
    private function preloadStatisticsData(array $types): void
    {
        foreach ($types as $type) {
            switch ($type) {
                case 'user_stats':
                    $this->warmUserStatistics();
                    break;
                case 'salary_stats':
                    $this->warmSalaryStatistics();
                    break;
                case 'currency_distribution':
                    $this->warmCurrencyDistribution();
                    break;
            }
        }
    }

    /**
     * Check if Redis is available.
     */
    private function isRedisAvailable(): bool
    {
        try {
            Redis::connection()->ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Calculate cache hit rate.
     */
    private function calculateHitRate(): array
    {
        if (!$this->isRedisAvailable()) {
            return ['message' => 'Hit rate calculation requires Redis'];
        }
        
        try {
            $redis = Redis::connection();
            $info = $redis->info();
            
            $hits = $info['keyspace_hits'] ?? 0;
            $misses = $info['keyspace_misses'] ?? 0;
            $total = $hits + $misses;
            
            return [
                'hits' => $hits,
                'misses' => $misses,
                'total_requests' => $total,
                'hit_rate_percentage' => $total > 0 ? round(($hits / $total) * 100, 2) : 0
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get cache memory usage.
     */
    private function getCacheMemoryUsage(): array
    {
        if (!$this->isRedisAvailable()) {
            return $this->getFileCacheMemoryUsage();
        }
        
        try {
            $redis = Redis::connection();
            $info = $redis->info();
            
            return [
                'used_memory' => $info['used_memory_human'] ?? 'Unknown',
                'used_memory_peak' => $info['used_memory_peak_human'] ?? 'Unknown',
                'memory_fragmentation_ratio' => $info['mem_fragmentation_ratio'] ?? 'Unknown',
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get file cache memory usage.
     */
    private function getFileCacheMemoryUsage(): array
    {
        $cachePath = storage_path('framework/cache/data');
        $totalSize = 0;
        $fileCount = 0;
        
        if (is_dir($cachePath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cachePath)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $totalSize += $file->getSize();
                    $fileCount++;
                }
            }
        }
        
        return [
            'total_size' => $this->formatBytes($totalSize),
            'file_count' => $fileCount,
            'cache_path' => $cachePath
        ];
    }

    /**
     * Get cache key distribution.
     */
    private function getCacheKeyDistribution(): array
    {
        if (!$this->isRedisAvailable()) {
            return ['message' => 'Key distribution analysis requires Redis'];
        }
        
        try {
            $redis = Redis::connection();
            $keys = $redis->keys('*');
            
            $distribution = [];
            foreach ($keys as $key) {
                $prefix = explode(':', $key)[0] ?? 'unknown';
                $distribution[$prefix] = ($distribution[$prefix] ?? 0) + 1;
            }
            
            arsort($distribution);
            
            return [
                'total_keys' => count($keys),
                'distribution' => $distribution
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get cache performance metrics.
     */
    private function getCachePerformanceMetrics(): array
    {
        $startTime = microtime(true);
        
        // Test cache write performance
        $testKey = 'performance_test_' . time();
        $testData = ['test' => 'data', 'timestamp' => time()];
        
        $writeStart = microtime(true);
        $this->put($testKey, $testData, 1); // 1 minute TTL
        $writeTime = (microtime(true) - $writeStart) * 1000;
        
        // Test cache read performance
        $readStart = microtime(true);
        $retrievedData = $this->get($testKey);
        $readTime = (microtime(true) - $readStart) * 1000;
        
        // Clean up test key
        $this->forget($testKey);
        
        $totalTime = (microtime(true) - $startTime) * 1000;
        
        return [
            'write_time_ms' => round($writeTime, 2),
            'read_time_ms' => round($readTime, 2),
            'total_test_time_ms' => round($totalTime, 2),
            'cache_driver' => config('cache.default'),
        ];
    }

    /**
     * Get cache optimization recommendations.
     */
    private function getCacheRecommendations(): array
    {
        $recommendations = [];
        
        $driver = config('cache.default');
        
        if ($driver === 'file') {
            $recommendations[] = 'Consider upgrading to Redis or Memcached for better performance';
        }
        
        if ($this->isRedisAvailable()) {
            $hitRate = $this->calculateHitRate();
            if (isset($hitRate['hit_rate_percentage']) && $hitRate['hit_rate_percentage'] < 80) {
                $recommendations[] = 'Cache hit rate is below 80%. Consider reviewing cache strategies';
            }
        }
        
        $recommendations[] = 'Regularly monitor cache memory usage to prevent evictions';
        $recommendations[] = 'Use cache tags for efficient bulk invalidation';
        $recommendations[] = 'Implement cache warming for critical data';
        
        return $recommendations;
    }

    /**
     * Format bytes for display.
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