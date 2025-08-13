<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\User;
use App\Models\Salary;
use App\Models\SalaryHistory;
use App\Services\SalaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SalaryServiceTest extends TestCase
{
    use RefreshDatabase;

    private SalaryService $salaryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->salaryService = new SalaryService();
    }

    /** @test */
    public function it_has_correct_constants()
    {
        $this->assertEquals(500.00, SalaryService::DEFAULT_COMMISSION);
        $this->assertEquals(1000.00, SalaryService::MIN_SALARY_EUROS);
        $this->assertEquals(500000.00, SalaryService::MAX_SALARY_EUROS);
    }

    /** @test */
    public function it_has_exchange_rates_for_supported_currencies()
    {
        $expectedRates = [
            'USD' => 0.85,
            'GBP' => 1.15,
            'EUR' => 1.00,
            'CAD' => 0.65,
            'AUD' => 0.60,
            'JPY' => 0.0065,
            'CHF' => 0.95,
            'SEK' => 0.085,
            'NOK' => 0.082,
            'DKK' => 0.134,
        ];

        $this->assertEquals($expectedRates, SalaryService::EXCHANGE_RATES);
    }

    /** @test */
    public function it_creates_new_salary_for_user()
    {
        $user = User::factory()->create();

        $salary = $this->salaryService->createOrUpdateSalary(
            $user,
            50000,
            'USD',
            600,
            'Initial salary'
        );

        $this->assertInstanceOf(Salary::class, $salary);
        $this->assertEquals($user->id, $salary->user_id);
        $this->assertEquals(50000, $salary->salary_local_currency);
        $this->assertEquals('USD', $salary->local_currency_code);
        $this->assertEquals(42500, $salary->salary_euros); // 50000 * 0.85
        $this->assertEquals(600, $salary->commission);
        $this->assertEquals(43100, $salary->displayed_salary); // 42500 + 600
        $this->assertEquals('Initial salary', $salary->notes);
    }

    /** @test */
    public function it_updates_existing_salary_for_user()
    {
        $user = User::factory()->create();
        $existingSalary = Salary::factory()->forUser($user)->withAmounts(40000, 34000, 500)->create();

        $updatedSalary = $this->salaryService->createOrUpdateSalary(
            $user,
            60000,
            'USD',
            700,
            'Salary increase'
        );

        $this->assertEquals($existingSalary->id, $updatedSalary->id);
        $this->assertEquals(60000, $updatedSalary->salary_local_currency);
        $this->assertEquals(51000, $updatedSalary->salary_euros); // 60000 * 0.85
        $this->assertEquals(700, $updatedSalary->commission);
        $this->assertEquals(51700, $updatedSalary->displayed_salary);
    }

    /** @test */
    public function it_creates_history_record_when_updating_salary()
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $existingSalary = Salary::factory()->forUser($user)->withAmounts(40000, 34000, 500)->create();

        $this->salaryService->createOrUpdateSalary(
            $user,
            60000,
            'USD',
            700,
            'Salary increase',
            $admin->id
        );

        $this->assertDatabaseHas('salary_histories', [
            'user_id' => $user->id,
            'old_salary_euros' => 34000,
            'new_salary_euros' => 51000,
            'old_commission' => 500,
            'new_commission' => 700,
            'changed_by' => $admin->id,
        ]);
    }

    /** @test */
    public function it_uses_default_commission_when_not_provided()
    {
        $user = User::factory()->create();

        $salary = $this->salaryService->createOrUpdateSalary($user, 50000, 'USD');

        $this->assertEquals(500.00, $salary->commission);
    }

    /** @test */
    public function it_uses_eur_as_default_currency()
    {
        $user = User::factory()->create();

        $salary = $this->salaryService->createOrUpdateSalary($user, 50000);

        $this->assertEquals('EUR', $salary->local_currency_code);
        $this->assertEquals(50000, $salary->salary_euros);
    }

    /** @test */
    public function it_validates_salary_data_before_processing()
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Salary must be greater than zero');

        $this->salaryService->createOrUpdateSalary($user, -1000, 'USD');
    }

    /** @test */
    public function it_validates_unsupported_currency()
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported currency code: XYZ');

        $this->salaryService->createOrUpdateSalary($user, 50000, 'XYZ');
    }

    /** @test */
    public function it_validates_minimum_salary_threshold()
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Salary must be at least €1,000.00');

        $this->salaryService->createOrUpdateSalary($user, 500, 'EUR');
    }

    /** @test */
    public function it_validates_maximum_salary_threshold()
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Salary cannot exceed €500,000.00');

        $this->salaryService->createOrUpdateSalary($user, 1000000, 'EUR');
    }

    /** @test */
    public function it_updates_commission_for_existing_salary()
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $salary = Salary::factory()->forUser($user)->withAmounts(50000, 42500, 500)->create();

        $updatedSalary = $this->salaryService->updateCommission(
            $salary,
            750,
            $admin->id,
            'Performance bonus'
        );

        $this->assertEquals(750, $updatedSalary->commission);
        $this->assertEquals(43250, $updatedSalary->displayed_salary); // 42500 + 750
    }

    /** @test */
    public function it_creates_history_when_updating_commission()
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $salary = Salary::factory()->forUser($user)->withAmounts(50000, 42500, 500)->create();

        $this->salaryService->updateCommission($salary, 750, $admin->id, 'Performance bonus');

        $this->assertDatabaseHas('salary_histories', [
            'user_id' => $user->id,
            'old_commission' => 500,
            'new_commission' => 750,
            'changed_by' => $admin->id,
            'change_reason' => 'Performance bonus',
        ]);
    }

    /** @test */
    public function it_validates_commission_amount()
    {
        $salary = Salary::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Commission cannot be negative');

        $this->salaryService->updateCommission($salary, -100);
    }

    /** @test */
    public function it_validates_maximum_commission_amount()
    {
        $salary = Salary::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Commission cannot exceed €50,000');

        $this->salaryService->updateCommission($salary, 60000);
    }

    /** @test */
    public function it_converts_currency_to_euros_correctly()
    {
        $this->assertEquals(42500.00, $this->salaryService->convertToEuros(50000, 'USD'));
        $this->assertEquals(57500.00, $this->salaryService->convertToEuros(50000, 'GBP'));
        $this->assertEquals(50000.00, $this->salaryService->convertToEuros(50000, 'EUR'));
        $this->assertEquals(32500.00, $this->salaryService->convertToEuros(50000, 'CAD'));
        $this->assertEquals(30000.00, $this->salaryService->convertToEuros(50000, 'AUD'));
        $this->assertEquals(325.00, $this->salaryService->convertToEuros(50000, 'JPY'));
    }

    /** @test */
    public function it_throws_exception_for_unsupported_currency_conversion()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported currency code: XYZ');

        $this->salaryService->convertToEuros(50000, 'XYZ');
    }

    /** @test */
    public function it_calculates_displayed_salary_correctly()
    {
        $result = $this->salaryService->calculateDisplayedSalary(42500, 750);
        $this->assertEquals(43250.00, $result);
    }

    /** @test */
    public function it_gets_exchange_rate_for_currency()
    {
        $this->assertEquals(0.85, $this->salaryService->getExchangeRate('USD'));
        $this->assertEquals(1.15, $this->salaryService->getExchangeRate('GBP'));
        $this->assertEquals(1.00, $this->salaryService->getExchangeRate('EUR'));
    }

    /** @test */
    public function it_throws_exception_for_unsupported_exchange_rate()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported currency code: XYZ');

        $this->salaryService->getExchangeRate('XYZ');
    }

    /** @test */
    public function it_returns_supported_currencies()
    {
        $currencies = $this->salaryService->getSupportedCurrencies();
        
        $this->assertIsArray($currencies);
        $this->assertContains('USD', $currencies);
        $this->assertContains('EUR', $currencies);
        $this->assertContains('GBP', $currencies);
        $this->assertCount(10, $currencies);
    }

    /** @test */
    public function it_calculates_salary_statistics()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        
        Salary::factory()->forUser($user1)->withAmounts(50000, 40000, 500)->create();
        Salary::factory()->forUser($user2)->withAmounts(60000, 50000, 600)->create();
        Salary::factory()->forUser($user3)->withAmounts(70000, 60000, 700)->create();

        $stats = $this->salaryService->getSalaryStatistics();

        $this->assertEquals(3, $stats['total_employees']);
        $this->assertEquals(50000.00, $stats['average_salary_euros']);
        $this->assertEquals(50000.00, $stats['median_salary_euros']);
        $this->assertEquals(40000.00, $stats['min_salary_euros']);
        $this->assertEquals(60000.00, $stats['max_salary_euros']);
        $this->assertEquals(1800.00, $stats['total_commission_paid']);
        $this->assertEquals(600.00, $stats['average_commission']);
    }

    /** @test */
    public function it_returns_empty_statistics_when_no_salaries()
    {
        $stats = $this->salaryService->getSalaryStatistics();

        $this->assertEquals(0, $stats['total_employees']);
        $this->assertEquals(0, $stats['average_salary_euros']);
        $this->assertEquals(0, $stats['median_salary_euros']);
        $this->assertEquals(0, $stats['min_salary_euros']);
        $this->assertEquals(0, $stats['max_salary_euros']);
        $this->assertEquals(0, $stats['total_commission_paid']);
        $this->assertEquals(0, $stats['average_commission']);
        $this->assertEquals([], $stats['currency_distribution']);
    }

    /** @test */
    public function it_calculates_median_correctly_for_odd_number_of_salaries()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        
        Salary::factory()->forUser($user1)->withAmounts(50000, 30000, 500)->create();
        Salary::factory()->forUser($user2)->withAmounts(60000, 40000, 500)->create();
        Salary::factory()->forUser($user3)->withAmounts(70000, 50000, 500)->create();

        $stats = $this->salaryService->getSalaryStatistics();

        $this->assertEquals(40000.00, $stats['median_salary_euros']);
    }

    /** @test */
    public function it_calculates_median_correctly_for_even_number_of_salaries()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $user4 = User::factory()->create();
        
        Salary::factory()->forUser($user1)->withAmounts(50000, 30000, 500)->create();
        Salary::factory()->forUser($user2)->withAmounts(60000, 40000, 500)->create();
        Salary::factory()->forUser($user3)->withAmounts(70000, 50000, 500)->create();
        Salary::factory()->forUser($user4)->withAmounts(80000, 60000, 500)->create();

        $stats = $this->salaryService->getSalaryStatistics();

        $this->assertEquals(45000.00, $stats['median_salary_euros']); // (40000 + 50000) / 2
    }

    /** @test */
    public function it_performs_bulk_salary_updates()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $admin = User::factory()->create();

        $updates = [
            [
                'user_id' => $user1->id,
                'salary_local_currency' => 55000,
                'local_currency_code' => 'USD',
                'commission' => 600,
                'notes' => 'Bulk update 1',
            ],
            [
                'user_id' => $user2->id,
                'salary_local_currency' => 65000,
                'local_currency_code' => 'EUR',
                'commission' => 700,
                'notes' => 'Bulk update 2',
            ],
        ];

        $results = $this->salaryService->bulkUpdateSalaries($updates, $admin->id);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[1]['success']);
        $this->assertEquals($user1->id, $results[0]['user_id']);
        $this->assertEquals($user2->id, $results[1]['user_id']);
    }

    /** @test */
    public function it_handles_bulk_update_failures_gracefully()
    {
        $admin = User::factory()->create();

        $updates = [
            [
                'user_id' => 999, // Non-existent user
                'salary_local_currency' => 55000,
                'local_currency_code' => 'USD',
            ],
            [
                'user_id' => 998, // Non-existent user
                'salary_local_currency' => -1000, // Invalid salary
                'local_currency_code' => 'USD',
            ],
        ];

        $results = $this->salaryService->bulkUpdateSalaries($updates, $admin->id);

        $this->assertCount(2, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertFalse($results[1]['success']);
        $this->assertArrayHasKey('error', $results[0]);
        $this->assertArrayHasKey('error', $results[1]);
    }

    /** @test */
    public function it_logs_salary_updates()
    {
        Log::fake();
        
        $user = User::factory()->create();
        $admin = User::factory()->create();

        $this->salaryService->createOrUpdateSalary(
            $user,
            50000,
            'USD',
            600,
            'Test salary',
            $admin->id
        );

        Log::assertLogged('info', function ($message, $context) use ($user, $admin) {
            return $message === 'Salary updated' &&
                   $context['user_id'] === $user->id &&
                   $context['salary_euros'] === 42500.0 &&
                   $context['commission'] === 600.0 &&
                   $context['displayed_salary'] === 43100.0 &&
                   $context['changed_by'] === $admin->id;
        });
    }

    /** @test */
    public function it_logs_commission_updates()
    {
        Log::fake();
        
        $salary = Salary::factory()->withAmounts(50000, 42500, 500)->create();
        $admin = User::factory()->create();

        $this->salaryService->updateCommission($salary, 750, $admin->id, 'Performance bonus');

        Log::assertLogged('info', function ($message, $context) use ($salary, $admin) {
            return $message === 'Commission updated' &&
                   $context['salary_id'] === $salary->id &&
                   $context['user_id'] === $salary->user_id &&
                   $context['old_commission'] === 500.0 &&
                   $context['new_commission'] === 750.0 &&
                   $context['changed_by'] === $admin->id;
        });
    }

    /** @test */
    public function it_uses_database_transactions()
    {
        DB::shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $user = User::factory()->create();
        $this->salaryService->createOrUpdateSalary($user, 50000, 'USD');
    }

    /** @test */
    public function it_rounds_currency_conversion_to_two_decimal_places()
    {
        // Test with a value that would have more than 2 decimal places
        $result = $this->salaryService->convertToEuros(33333.33, 'USD');
        
        // 33333.33 * 0.85 = 28333.3305, should be rounded to 28333.33
        $this->assertEquals(28333.33, $result);
    }

    /** @test */
    public function it_rounds_displayed_salary_calculation_to_two_decimal_places()
    {
        $result = $this->salaryService->calculateDisplayedSalary(42500.999, 750.555);
        
        // Should round to 43251.55
        $this->assertEquals(43251.55, $result);
    }
}