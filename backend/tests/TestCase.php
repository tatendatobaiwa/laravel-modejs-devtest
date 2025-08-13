<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run migrations for each test
        $this->artisan('migrate:fresh');
        
        // Seed any necessary data
        $this->seed();
    }

    /**
     * Create a user for testing.
     */
    protected function createUser(array $attributes = []): \App\Models\User
    {
        return \App\Models\User::factory()->create($attributes);
    }

    /**
     * Create an admin user for testing.
     */
    protected function createAdminUser(array $attributes = []): \App\Models\User
    {
        return \App\Models\User::factory()->create(array_merge([
            'email' => 'admin@example.com',
        ], $attributes));
    }

    /**
     * Create a salary for testing.
     */
    protected function createSalary(array $attributes = []): \App\Models\Salary
    {
        return \App\Models\Salary::factory()->create($attributes);
    }

    /**
     * Create a salary history record for testing.
     */
    protected function createSalaryHistory(array $attributes = []): \App\Models\SalaryHistory
    {
        return \App\Models\SalaryHistory::factory()->create($attributes);
    }

    /**
     * Assert that a model has the expected attributes.
     */
    protected function assertModelHasAttributes($model, array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            $this->assertEquals($value, $model->{$key}, "Expected {$key} to be {$value}, got {$model->{$key}}");
        }
    }

    /**
     * Assert that a database table has a record with the given attributes.
     */
    protected function assertDatabaseHasRecord(string $table, array $attributes): void
    {
        $this->assertDatabaseHas($table, $attributes);
    }

    /**
     * Assert that a database table does not have a record with the given attributes.
     */
    protected function assertDatabaseMissingRecord(string $table, array $attributes): void
    {
        $this->assertDatabaseMissing($table, $attributes);
    }
}