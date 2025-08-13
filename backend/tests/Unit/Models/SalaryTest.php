<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use App\Models\Salary;
use App\Models\SalaryHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

class SalaryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_fillable_attributes()
    {
        $fillable = [
            'user_id',
            'salary_local_currency',
            'local_currency_code',
            'salary_euros',
            'commission',
            'displayed_salary',
            'effective_date',
            'document_path',
            'notes',
        ];
        
        $salary = new Salary();
        $this->assertEquals($fillable, $salary->getFillable());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $salary = Salary::factory()->create([
            'salary_local_currency' => '50000.50',
            'salary_euros' => '42500.25',
            'commission' => '600.75',
            'displayed_salary' => '43101.00',
            'effective_date' => '2024-01-01',
        ]);

        $this->assertIsFloat($salary->salary_local_currency);
        $this->assertIsFloat($salary->salary_euros);
        $this->assertIsFloat($salary->commission);
        $this->assertIsFloat($salary->displayed_salary);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $salary->effective_date);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $salary->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $salary->updated_at);
    }

    /** @test */
    public function it_has_default_commission_constant()
    {
        $this->assertEquals(500.00, Salary::DEFAULT_COMMISSION);
    }

    /** @test */
    public function it_belongs_to_user()
    {
        $user = User::factory()->create();
        $salary = Salary::factory()->forUser($user)->create();

        $this->assertInstanceOf(User::class, $salary->user);
        $this->assertEquals($user->id, $salary->user->id);
    }

    /** @test */
    public function it_sets_default_commission_on_creation()
    {
        $user = User::factory()->create();
        
        $salary = Salary::create([
            'user_id' => $user->id,
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'salary_euros' => 42500,
        ]);

        $this->assertEquals(500.00, $salary->commission);
    }

    /** @test */
    public function it_sets_effective_date_on_creation_if_not_provided()
    {
        $user = User::factory()->create();
        
        $salary = Salary::create([
            'user_id' => $user->id,
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'salary_euros' => 42500,
            'commission' => 600,
        ]);

        $this->assertEquals(now()->toDateString(), $salary->effective_date->toDateString());
    }

    /** @test */
    public function it_calculates_displayed_salary_on_creation()
    {
        $user = User::factory()->create();
        
        $salary = Salary::create([
            'user_id' => $user->id,
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'salary_euros' => 42500,
            'commission' => 600,
        ]);

        $this->assertEquals(43100.00, $salary->displayed_salary);
    }

    /** @test */
    public function it_recalculates_displayed_salary_on_update()
    {
        $salary = Salary::factory()->withAmounts(50000, 42500, 500)->create();
        
        $salary->update([
            'salary_euros' => 45000,
            'commission' => 700,
        ]);

        $this->assertEquals(45700.00, $salary->displayed_salary);
    }

    /** @test */
    public function it_creates_history_record_on_update()
    {
        Event::fake();
        
        $salary = Salary::factory()->withAmounts(50000, 42500, 500)->create();
        $originalId = $salary->id;
        
        $salary->update([
            'salary_euros' => 45000,
            'commission' => 700,
        ]);

        // Check that history record was created
        $this->assertDatabaseHas('salary_histories', [
            'user_id' => $salary->user_id,
            'old_salary_euros' => 42500,
            'new_salary_euros' => 45000,
            'old_commission' => 500,
            'new_commission' => 700,
        ]);
    }

    /** @test */
    public function it_calculates_displayed_salary_correctly()
    {
        $salary = new Salary();
        $salary->salary_euros = 50000;
        $salary->commission = 750;

        $result = $salary->calculateDisplayedSalary();

        $this->assertEquals(50750.00, $result);
    }

    /** @test */
    public function it_handles_null_values_in_calculation()
    {
        $salary = new Salary();
        $salary->salary_euros = null;
        $salary->commission = null;

        $result = $salary->calculateDisplayedSalary();

        $this->assertEquals(500.00, $result); // 0 + DEFAULT_COMMISSION
    }

    /** @test */
    public function it_converts_local_currency_to_euros()
    {
        $salary = new Salary();
        $salary->salary_local_currency = 50000;
        $salary->local_currency_code = 'USD';

        $salary->convertToEuros();

        $this->assertEquals(42500.00, $salary->salary_euros); // 50000 * 0.85
    }

    /** @test */
    public function it_converts_with_custom_exchange_rate()
    {
        $salary = new Salary();
        $salary->salary_local_currency = 50000;
        $salary->local_currency_code = 'USD';

        $salary->convertToEuros(0.90);

        $this->assertEquals(45000.00, $salary->salary_euros); // 50000 * 0.90
    }

    /** @test */
    public function it_gets_correct_exchange_rates()
    {
        $salary = new Salary();
        
        $this->assertEquals(0.85, $salary->getExchangeRate('USD'));
        $this->assertEquals(1.15, $salary->getExchangeRate('GBP'));
        $this->assertEquals(1.00, $salary->getExchangeRate('EUR'));
        $this->assertEquals(0.65, $salary->getExchangeRate('CAD'));
        $this->assertEquals(0.60, $salary->getExchangeRate('AUD'));
        $this->assertEquals(0.0065, $salary->getExchangeRate('JPY'));
    }

    /** @test */
    public function it_returns_default_rate_for_unknown_currency()
    {
        $salary = new Salary();
        
        $this->assertEquals(1.00, $salary->getExchangeRate('XYZ'));
    }

    /** @test */
    public function it_returns_displayed_salary_attribute()
    {
        $salary = Salary::factory()->withAmounts(50000, 42500, 600)->create();

        $this->assertEquals(43100.00, $salary->displayed_salary);
    }

    /** @test */
    public function it_returns_formatted_local_salary()
    {
        $salary = Salary::factory()->create([
            'salary_local_currency' => 50000.50,
            'local_currency_code' => 'USD',
        ]);

        $this->assertEquals('USD 50,000.50', $salary->formatted_local_salary);
    }

    /** @test */
    public function it_returns_formatted_euro_salary()
    {
        $salary = Salary::factory()->create([
            'salary_euros' => 42500.75,
        ]);

        $this->assertEquals('€42,500.75', $salary->formatted_euro_salary);
    }

    /** @test */
    public function it_returns_formatted_commission()
    {
        $salary = Salary::factory()->create([
            'commission' => 750.25,
        ]);

        $this->assertEquals('€750.25', $salary->formatted_commission);
    }

    /** @test */
    public function it_returns_formatted_displayed_salary()
    {
        $salary = Salary::factory()->withAmounts(50000, 42500, 750)->create();

        $this->assertEquals('€43,250.00', $salary->formatted_displayed_salary);
    }

    /** @test */
    public function it_can_scope_with_user()
    {
        $salary = Salary::factory()->create();

        $salaries = Salary::withUser()->get();
        
        $this->assertTrue($salaries->first()->relationLoaded('user'));
    }

    /** @test */
    public function it_can_scope_by_currency()
    {
        $usdSalary = Salary::factory()->withCurrency('USD')->create();
        $eurSalary = Salary::factory()->withCurrency('EUR')->create();
        $gbpSalary = Salary::factory()->withCurrency('GBP')->create();

        $usdSalaries = Salary::byCurrency('USD')->get();
        
        $this->assertCount(1, $usdSalaries);
        $this->assertEquals($usdSalary->id, $usdSalaries->first()->id);
    }

    /** @test */
    public function it_can_scope_by_effective_date_range()
    {
        $oldSalary = Salary::factory()->create(['effective_date' => '2023-01-01']);
        $newSalary = Salary::factory()->create(['effective_date' => '2024-06-01']);
        $futureSalary = Salary::factory()->create(['effective_date' => '2024-12-01']);

        $salariesInRange = Salary::effectiveBetween('2024-01-01', '2024-11-30')->get();
        
        $this->assertCount(1, $salariesInRange);
        $this->assertEquals($newSalary->id, $salariesInRange->first()->id);
    }

    /** @test */
    public function it_checks_if_recently_updated()
    {
        $recentSalary = Salary::factory()->create(['updated_at' => now()->subHours(12)]);
        $oldSalary = Salary::factory()->create(['updated_at' => now()->subDays(2)]);

        $this->assertTrue($recentSalary->isRecentlyUpdated());
        $this->assertFalse($oldSalary->isRecentlyUpdated());
    }

    /** @test */
    public function it_returns_total_compensation_attribute()
    {
        $salary = Salary::factory()->withAmounts(50000, 42500, 750)->create();

        $this->assertEquals(43250.00, $salary->total_compensation);
    }

    /** @test */
    public function it_prevents_negative_salary_values()
    {
        $user = User::factory()->create();
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Salary::create([
            'user_id' => $user->id,
            'salary_local_currency' => -1000,
            'local_currency_code' => 'USD',
            'salary_euros' => -850,
            'commission' => 500,
        ]);
    }

    /** @test */
    public function it_rounds_calculated_values_to_two_decimal_places()
    {
        $salary = new Salary();
        $salary->salary_euros = 42500.999;
        $salary->commission = 750.555;

        $result = $salary->calculateDisplayedSalary();

        $this->assertEquals(43251.55, $result);
    }

    /** @test */
    public function it_handles_currency_conversion_edge_cases()
    {
        $salary = new Salary();
        
        // Test with zero amount
        $salary->salary_local_currency = 0;
        $salary->local_currency_code = 'USD';
        $salary->convertToEuros();
        $this->assertEquals(0.00, $salary->salary_euros);
        
        // Test with very small amount
        $salary->salary_local_currency = 0.01;
        $salary->local_currency_code = 'USD';
        $salary->convertToEuros();
        $this->assertEquals(0.01, $salary->salary_euros); // 0.01 * 0.85 = 0.0085, rounded to 0.01
    }
}