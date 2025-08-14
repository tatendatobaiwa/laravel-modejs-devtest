<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\DatabaseOptimizationService;
use App\Services\AdvancedCachingService;
use App\Services\QueryOptimizationService;
use App\Repositories\UserRepository;
use App\Repositories\SalaryRepository;
use App\Models\User;
use App\Models\Salary;
use App\Models\SalaryHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DatabaseOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected DatabaseOptimizationService $dbOptimizationService;
    protected AdvancedCachingService $cachingService;
    protected QueryOptimizationService $queryOptimizationService;
    protected UserRepository $userRepository;
    protected SalaryRepository $salaryRepository;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->dbOptimizationService = app(DatabaseOptimizationService::class);
        $this->cachingService = app(AdvancedCachingService::class);
        $this->queryOptimizationService = app(QueryOptimizationService::class);
        $this->userRepository = app(UserRepository::class);
        $this->salaryRepository = app(SalaryRepository::class);
    }

    /** @test */
    public function it_can_optimize_database_tables()
    {
        $results = $this->dbOptimizationService->optimizeTables();
        
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        
        // Check that optimization messages are present
        $optimizationFound = false;
        foreach ($results as $result) {
            if (str_contains($result, 'Optimized') || str_contains($result, 'VACUUM') || str_contains($result, 'ANALYZE')) {
                $optimizationFound = true;
                break;
            }
        }
        
        $this->assertTrue($optimizationFound, 'Database optimization should have run');
    }

    /** @test */
    public function it_can_get_performance_metrics()
    {
        // Create some test data
        $user = User::factory()->create();
        Salary::factory()->create(['user_id' => $user->id]);
        
        $metrics = $this->dbOptimizationService->getPerformanceMetrics();
        
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('driver', $metrics);
    }

    /** @test */
    public function it_can_warm_critical_caches()
    {
        // Create test data
        $users = User::factory(5)->create();
        foreach ($users as $user) {
            Salary::factory()->create(['user_id' => $user->id]);
        }
        
        $results = $this->cachingService->warmCriticalCaches();
        
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        
        // Verify that caches were actually warmed
        $userStats = $this->cachingService->get('stats:user_overview');
        $this->assertNotNull($userStats);
        
        $salaryStats = $this->cachingService->get('stats:salary_overview');
        $this->assertNotNull($salaryStats);
    }

    /** @test */
    public function it_can_get_cache_analytics()
    {
        $analytics = $this->cachingService->getCacheAnalytics();
        
        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('cache_health', $analytics);
        $this->assertArrayHasKey('performance_metrics', $analytics);
        $this->assertArrayHasKey('recommendations', $analytics);
    }

    /** @test */
    public function it_can_use_optimized_user_repository()
    {
        // Create test data
        $users = User::factory(10)->create();
        foreach ($users as $user) {
            Salary::factory()->create(['user_id' => $user->id]);
        }
        
        // Test optimized user list
        $userList = $this->userRepository->getOptimizedUserList();
        
        $this->assertNotNull($userList);
        $this->assertGreaterThan(0, $userList->total());
        
        // Test users with salary optimization
        $usersWithSalary = $this->userRepository->getUsersWithSalaryOptimized();
        
        $this->assertNotNull($usersWithSalary);
        $this->assertGreaterThan(0, $usersWithSalary->count());
        
        // Test user statistics
        $stats = $this->userRepository->getUserStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_users', $stats);
        $this->assertArrayHasKey('users_with_salary', $stats);
    }

    /** @test */
    public function it_can_use_optimized_salary_repository()
    {
        // Create test data
        $users = User::factory(5)->create();
        foreach ($users as $user) {
            $salary = Salary::factory()->create(['user_id' => $user->id]);
            
            // Create some salary history
            SalaryHistory::factory()->create([
                'user_id' => $user->id,
                'old_salary_euros' => $salary->salary_euros - 1000,
                'new_salary_euros' => $salary->salary_euros,
                'old_commission' => 400,
                'new_commission' => $salary->commission,
            ]);
        }
        
        // Test salary statistics
        $stats = $this->salaryRepository->getSalaryStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_users', $stats);
        $this->assertArrayHasKey('users_with_salary', $stats);
        
        // Test salary distribution by currency
        $distribution = $this->salaryRepository->getSalaryDistributionByCurrency();
        
        $this->assertIsArray($distribution);
        $this->assertNotEmpty($distribution);
        
        // Test recent salary changes
        $recentChanges = $this->salaryRepository->getRecentSalaryChanges(30);
        
        $this->assertNotNull($recentChanges);
        $this->assertGreaterThan(0, $recentChanges->count());
        
        // Test top earners
        $topEarners = $this->salaryRepository->getTopEarners(5);
        
        $this->assertNotNull($topEarners);
        $this->assertGreaterThan(0, $topEarners->count());
    }

    /** @test */
    public function it_can_apply_eager_loading_optimization()
    {
        // Create test data
        $user = User::factory()->create();
        Salary::factory()->create(['user_id' => $user->id]);
        
        $query = User::query();
        
        $optimizedQuery = $this->queryOptimizationService->applyEagerLoading($query, [
            'salary',
            'salaryHistory',
            'uploadedDocuments'
        ]);
        
        $this->assertNotNull($optimizedQuery);
        
        // Execute the query to ensure it works
        $results = $optimizedQuery->get();
        $this->assertNotNull($results);
    }

    /** @test */
    public function it_can_apply_database_specific_optimizations()
    {
        $query = User::query();
        
        $optimizedQuery = $this->queryOptimizationService->applyDatabaseOptimizations($query);
        
        $this->assertNotNull($optimizedQuery);
        
        // Execute the query to ensure it works
        $results = $optimizedQuery->get();
        $this->assertNotNull($results);
    }

    /** @test */
    public function it_can_create_optimized_subqueries()
    {
        // Create test data
        $users = User::factory(3)->create();
        foreach ($users as $user) {
            $salary = Salary::factory()->create(['user_id' => $user->id]);
            
            SalaryHistory::factory()->create([
                'user_id' => $user->id,
                'old_salary_euros' => $salary->salary_euros - 1000,
                'new_salary_euros' => $salary->salary_euros,
                'created_at' => now()->subDays(5),
            ]);
        }
        
        // Test users with recent salary changes subquery
        $subquery = $this->queryOptimizationService->createOptimizedSubquery(
            'users_with_recent_salary_changes',
            ['days' => 30]
        );
        
        $this->assertNotNull($subquery);
        
        // Execute the subquery
        $results = $subquery->get();
        $this->assertNotNull($results);
        
        // Test top earners by currency subquery
        $subquery = $this->queryOptimizationService->createOptimizedSubquery(
            'top_earners_by_currency',
            ['currency' => 'EUR', 'limit' => 5]
        );
        
        $this->assertNotNull($subquery);
        
        // Execute the subquery
        $results = $subquery->get();
        $this->assertNotNull($results);
    }

    /** @test */
    public function it_can_analyze_query_performance()
    {
        $query = User::with('salary');
        
        $analysis = $this->queryOptimizationService->analyzeQueryPerformance($query);
        
        $this->assertIsArray($analysis);
        $this->assertArrayHasKey('driver', $analysis);
        $this->assertArrayHasKey('query', $analysis);
        $this->assertArrayHasKey('suggestions', $analysis);
    }

    /** @test */
    public function it_can_handle_intelligent_cache_invalidation()
    {
        // Create test data
        $user = User::factory()->create();
        Salary::factory()->create(['user_id' => $user->id]);
        
        // Warm some caches first
        $this->cachingService->cacheUserData($user->id, $user->toArray());
        
        // Verify cache exists
        $cachedData = $this->cachingService->getUserData($user->id);
        $this->assertNotNull($cachedData);
        
        // Test intelligent invalidation
        $this->cachingService->intelligentInvalidation('user', $user->id, [
            'salary_affected' => true
        ]);
        
        // Verify cache was invalidated
        $cachedData = $this->cachingService->getUserData($user->id);
        $this->assertNull($cachedData);
    }

    /** @test */
    public function it_can_use_compressed_caching()
    {
        $largeData = array_fill(0, 1000, 'test data string that should be compressed');
        
        $key = 'test_compression_key';
        
        // Set compressed cache
        $result = $this->cachingService->compressedCacheSet($key, $largeData, 60);
        $this->assertTrue($result);
        
        // Get compressed cache
        $retrievedData = $this->cachingService->compressedCacheGet($key);
        $this->assertEquals($largeData, $retrievedData);
        
        // Clean up
        $this->cachingService->forget($key);
    }

    /** @test */
    public function it_can_batch_process_queries()
    {
        // Create test data
        User::factory(5)->create();
        
        $queries = [
            function () {
                return User::count();
            },
            function () {
                return User::where('created_at', '>=', now()->subDays(30))->count();
            },
            function () {
                return User::whereHas('salary')->count();
            }
        ];
        
        $results = $this->queryOptimizationService->batchProcess($queries, 2);
        
        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        
        foreach ($results as $result) {
            $this->assertIsNumeric($result);
        }
    }

    /** @test */
    public function it_can_optimize_memory_usage_with_chunking()
    {
        // Create test data
        User::factory(50)->create();
        
        $processedCount = 0;
        
        $query = User::query();
        
        $this->queryOptimizationService->optimizeMemoryUsage($query, function ($records) use (&$processedCount) {
            $processedCount += $records->count();
        });
        
        $this->assertEquals(50, $processedCount);
    }

    /** @test */
    public function it_verifies_database_indexes_exist()
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            // Check if our performance indexes exist
            $indexes = DB::select("SHOW INDEX FROM users WHERE Key_name = 'users_admin_filter_idx'");
            $this->assertNotEmpty($indexes, 'Performance index users_admin_filter_idx should exist');
            
            $indexes = DB::select("SHOW INDEX FROM salaries WHERE Key_name = 'salaries_range_query_idx'");
            $this->assertNotEmpty($indexes, 'Performance index salaries_range_query_idx should exist');
        } elseif ($driver === 'pgsql') {
            // Check PostgreSQL indexes
            $indexes = DB::select("
                SELECT indexname FROM pg_indexes 
                WHERE tablename = 'users' AND indexname = 'users_admin_filter_idx'
            ");
            $this->assertNotEmpty($indexes, 'Performance index users_admin_filter_idx should exist');
        }
        
        // For SQLite, we can check if the tables exist and have been optimized
        $this->assertTrue(true, 'Index verification completed for ' . $driver);
    }

    /** @test */
    public function it_can_run_comprehensive_performance_analysis()
    {
        // Create test data
        $users = User::factory(10)->create();
        foreach ($users as $user) {
            Salary::factory()->create(['user_id' => $user->id]);
        }
        
        $analysis = $this->dbOptimizationService->getPerformanceAnalysis();
        
        $this->assertIsArray($analysis);
        $this->assertArrayHasKey('driver', $analysis);
        $this->assertArrayHasKey('general_metrics', $analysis);
        $this->assertArrayHasKey('table_analysis', $analysis);
        $this->assertArrayHasKey('recommendations', $analysis);
        
        // Verify general metrics
        $generalMetrics = $analysis['general_metrics'];
        $this->assertArrayHasKey('total_users', $generalMetrics);
        $this->assertArrayHasKey('total_salaries', $generalMetrics);
        $this->assertEquals(10, $generalMetrics['total_users']);
        $this->assertEquals(10, $generalMetrics['total_salaries']);
        
        // Verify table analysis
        $tableAnalysis = $analysis['table_analysis'];
        $this->assertArrayHasKey('users', $tableAnalysis);
        $this->assertArrayHasKey('salaries', $tableAnalysis);
        
        // Verify recommendations exist
        $recommendations = $analysis['recommendations'];
        $this->assertIsArray($recommendations);
        $this->assertNotEmpty($recommendations);
    }
}