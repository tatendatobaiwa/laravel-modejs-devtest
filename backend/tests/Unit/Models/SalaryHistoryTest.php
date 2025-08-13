<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use App\Models\Salary;
use App\Models\SalaryHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Collection;

class SalaryHistoryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_correct_table_name()
    {
        $history = new SalaryHistory();
        $this->assertEquals('salary_histories', $history->getTable());
    }

    /** @test */
    public function it_has_fillable_attributes()
    {
        $fillable = [
            'user_id',
            'old_salary_local_currency',
            'new_salary_local_currency',
            'old_salary_euros',
            'new_salary_euros',
            'old_commission',
            'new_commission',
            'old_displayed_salary',
            'new_displayed_salary',
            'changed_by',
            'change_reason',
            'change_type',
            'metadata',
        ];
        
        $history = new SalaryHistory();
        $this->assertEquals($fillable, $history->getFillable());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $history = SalaryHistory::factory()->create([
            'old_salary_euros' => '40000.50',
            'new_salary_euros' => '45000.75',
            'old_commission' => '500.25',
            'new_commission' => '600.50',
            'metadata' => ['ip_address' => '127.0.0.1', 'user_agent' => 'Test Agent'],
        ]);

        $this->assertIsFloat($history->old_salary_euros);
        $this->assertIsFloat($history->new_salary_euros);
        $this->assertIsFloat($history->old_commission);
        $this->assertIsFloat($history->new_commission);
        $this->assertIsArray($history->metadata);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $history->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $history->updated_at);
    }

    /** @test */
    public function it_belongs_to_user()
    {
        $user = User::factory()->create();
        $history = SalaryHistory::factory()->forUser($user)->create();

        $this->assertInstanceOf(User::class, $history->user);
        $this->assertEquals($user->id, $history->user->id);
    }

    /** @test */
    public function it_belongs_to_changed_by_user()
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $history = SalaryHistory::factory()->forUser($user)->changedBy($admin)->create();

        $this->assertInstanceOf(User::class, $history->changedBy);
        $this->assertEquals($admin->id, $history->changedBy->id);
    }

    /** @test */
    public function it_prevents_updates_to_history_records()
    {
        $history = SalaryHistory::factory()->create();
        
        $result = $history->update(['change_reason' => 'Updated reason']);
        
        $this->assertFalse($result);
        $this->assertNotEquals('Updated reason', $history->fresh()->change_reason);
    }

    /** @test */
    public function it_prevents_deletion_of_history_records()
    {
        $history = SalaryHistory::factory()->create();
        $historyId = $history->id;
        
        $result = $history->delete();
        
        $this->assertFalse($result);
        $this->assertDatabaseHas('salary_histories', ['id' => $historyId]);
    }

    /** @test */
    public function it_sets_change_type_automatically_on_creation()
    {
        $user = User::factory()->create();
        
        $history = SalaryHistory::create([
            'user_id' => $user->id,
            'old_salary_euros' => 40000,
            'new_salary_euros' => 45000,
            'old_commission' => 500,
            'new_commission' => 500,
            'changed_by' => $user->id,
            'change_reason' => 'Test change',
        ]);

        $this->assertEquals('salary_change', $history->change_type);
    }

    /** @test */
    public function it_determines_commission_change_type()
    {
        $user = User::factory()->create();
        
        $history = SalaryHistory::create([
            'user_id' => $user->id,
            'old_salary_euros' => 40000,
            'new_salary_euros' => 40000,
            'old_commission' => 500,
            'new_commission' => 600,
            'changed_by' => $user->id,
            'change_reason' => 'Commission adjustment',
        ]);

        $this->assertEquals('commission_change', $history->change_type);
    }

    /** @test */
    public function it_determines_general_update_type()
    {
        $user = User::factory()->create();
        
        $history = SalaryHistory::create([
            'user_id' => $user->id,
            'old_salary_euros' => 40000,
            'new_salary_euros' => 40000,
            'old_commission' => 500,
            'new_commission' => 500,
            'changed_by' => $user->id,
            'change_reason' => 'General update',
        ]);

        $this->assertEquals('general_update', $history->change_type);
    }

    /** @test */
    public function it_can_scope_for_specific_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $history1 = SalaryHistory::factory()->forUser($user1)->create();
        $history2 = SalaryHistory::factory()->forUser($user2)->create();

        $userHistories = SalaryHistory::forUser($user1->id)->get();
        
        $this->assertCount(1, $userHistories);
        $this->assertEquals($history1->id, $userHistories->first()->id);
    }

    /** @test */
    public function it_can_scope_recent_history()
    {
        $oldHistory = SalaryHistory::factory()->create(['created_at' => now()->subDays(5)]);
        $newHistory = SalaryHistory::factory()->create(['created_at' => now()->subDays(1)]);

        $recentHistories = SalaryHistory::recent()->get();
        
        $this->assertEquals($newHistory->id, $recentHistories->first()->id);
        $this->assertEquals($oldHistory->id, $recentHistories->last()->id);
    }

    /** @test */
    public function it_can_scope_between_dates()
    {
        $oldHistory = SalaryHistory::factory()->create(['created_at' => '2024-01-01']);
        $middleHistory = SalaryHistory::factory()->create(['created_at' => '2024-06-01']);
        $newHistory = SalaryHistory::factory()->create(['created_at' => '2024-12-01']);

        $historiesInRange = SalaryHistory::betweenDates('2024-05-01', '2024-11-30')->get();
        
        $this->assertCount(1, $historiesInRange);
        $this->assertEquals($middleHistory->id, $historiesInRange->first()->id);
    }

    /** @test */
    public function it_can_scope_by_change_type()
    {
        $salaryChange = SalaryHistory::factory()->withChangeType('salary_change')->create();
        $commissionChange = SalaryHistory::factory()->withChangeType('commission_change')->create();

        $salaryChanges = SalaryHistory::byChangeType('salary_change')->get();
        
        $this->assertCount(1, $salaryChanges);
        $this->assertEquals($salaryChange->id, $salaryChanges->first()->id);
    }

    /** @test */
    public function it_can_scope_changed_by_user()
    {
        $admin1 = User::factory()->create();
        $admin2 = User::factory()->create();
        
        $history1 = SalaryHistory::factory()->changedBy($admin1)->create();
        $history2 = SalaryHistory::factory()->changedBy($admin2)->create();

        $admin1Changes = SalaryHistory::changedBy($admin1->id)->get();
        
        $this->assertCount(1, $admin1Changes);
        $this->assertEquals($history1->id, $admin1Changes->first()->id);
    }

    /** @test */
    public function it_can_scope_salary_increases()
    {
        $increase = SalaryHistory::factory()->salaryIncrease()->create();
        $decrease = SalaryHistory::factory()->salaryDecrease()->create();

        $increases = SalaryHistory::salaryIncreases()->get();
        
        $this->assertCount(1, $increases);
        $this->assertEquals($increase->id, $increases->first()->id);
    }

    /** @test */
    public function it_can_scope_salary_decreases()
    {
        $increase = SalaryHistory::factory()->salaryIncrease()->create();
        $decrease = SalaryHistory::factory()->salaryDecrease()->create();

        $decreases = SalaryHistory::salaryDecreases()->get();
        
        $this->assertCount(1, $decreases);
        $this->assertEquals($decrease->id, $decreases->first()->id);
    }

    /** @test */
    public function it_can_scope_commission_changes()
    {
        $salaryChange = SalaryHistory::factory()->create([
            'old_salary_euros' => 40000,
            'new_salary_euros' => 45000,
            'old_commission' => 500,
            'new_commission' => 500,
        ]);
        
        $commissionChange = SalaryHistory::factory()->create([
            'old_salary_euros' => 40000,
            'new_salary_euros' => 40000,
            'old_commission' => 500,
            'new_commission' => 600,
        ]);

        $commissionChanges = SalaryHistory::commissionChanges()->get();
        
        $this->assertCount(1, $commissionChanges);
        $this->assertEquals($commissionChange->id, $commissionChanges->first()->id);
    }

    /** @test */
    public function it_can_scope_with_relations()
    {
        $history = SalaryHistory::factory()->create();

        $historiesWithRelations = SalaryHistory::withRelations()->get();
        
        $this->assertTrue($historiesWithRelations->first()->relationLoaded('user'));
        $this->assertTrue($historiesWithRelations->first()->relationLoaded('changedBy'));
    }

    /** @test */
    public function it_calculates_salary_change_amount()
    {
        $history = SalaryHistory::factory()->create([
            'old_salary_euros' => 40000,
            'new_salary_euros' => 45000,
        ]);

        $this->assertEquals(5000.00, $history->salary_change_amount);
    }

    /** @test */
    public function it_calculates_commission_change_amount()
    {
        $history = SalaryHistory::factory()->create([
            'old_commission' => 500,
            'new_commission' => 750,
        ]);

        $this->assertEquals(250.00, $history->commission_change_amount);
    }

    /** @test */
    public function it_calculates_total_change_amount()
    {
        $history = SalaryHistory::factory()->create([
            'old_displayed_salary' => 40500,
            'new_displayed_salary' => 45750,
        ]);

        $this->assertEquals(5250.00, $history->total_change_amount);
    }

    /** @test */
    public function it_returns_null_for_change_amounts_when_values_missing()
    {
        $history = SalaryHistory::factory()->create([
            'old_salary_euros' => null,
            'new_salary_euros' => 45000,
        ]);

        $this->assertNull($history->salary_change_amount);
    }

    /** @test */
    public function it_identifies_salary_increases()
    {
        $increase = SalaryHistory::factory()->create([
            'old_salary_euros' => 40000,
            'new_salary_euros' => 45000,
        ]);
        
        $decrease = SalaryHistory::factory()->create([
            'old_salary_euros' => 45000,
            'new_salary_euros' => 40000,
        ]);

        $this->assertTrue($increase->isSalaryIncrease());
        $this->assertFalse($decrease->isSalaryIncrease());
    }

    /** @test */
    public function it_identifies_salary_decreases()
    {
        $increase = SalaryHistory::factory()->create([
            'old_salary_euros' => 40000,
            'new_salary_euros' => 45000,
        ]);
        
        $decrease = SalaryHistory::factory()->create([
            'old_salary_euros' => 45000,
            'new_salary_euros' => 40000,
        ]);

        $this->assertFalse($increase->isSalaryDecrease());
        $this->assertTrue($decrease->isSalaryDecrease());
    }

    /** @test */
    public function it_formats_salary_change_for_display()
    {
        $increase = SalaryHistory::factory()->create([
            'old_salary_euros' => 40000,
            'new_salary_euros' => 45000,
        ]);
        
        $decrease = SalaryHistory::factory()->create([
            'old_salary_euros' => 45000,
            'new_salary_euros' => 40000,
        ]);

        $this->assertEquals('+€5,000.00', $increase->formatted_salary_change);
        $this->assertEquals('-€5,000.00', $decrease->formatted_salary_change);
    }

    /** @test */
    public function it_formats_commission_change_for_display()
    {
        $increase = SalaryHistory::factory()->create([
            'old_commission' => 500,
            'new_commission' => 750,
        ]);

        $this->assertEquals('+€250.00', $increase->formatted_commission_change);
    }

    /** @test */
    public function it_formats_total_change_for_display()
    {
        $history = SalaryHistory::factory()->create([
            'old_displayed_salary' => 40500,
            'new_displayed_salary' => 45750,
        ]);

        $this->assertEquals('+€5,250.00', $history->formatted_total_change);
    }

    /** @test */
    public function it_returns_na_for_formatted_changes_when_null()
    {
        $history = SalaryHistory::factory()->create([
            'old_salary_euros' => null,
            'new_salary_euros' => 45000,
        ]);

        $this->assertEquals('N/A', $history->formatted_salary_change);
    }

    /** @test */
    public function it_generates_change_summary()
    {
        $salaryAndCommissionChange = SalaryHistory::factory()->create([
            'old_salary_euros' => 40000,
            'new_salary_euros' => 45000,
            'old_commission' => 500,
            'new_commission' => 750,
        ]);
        
        $salaryOnlyChange = SalaryHistory::factory()->create([
            'old_salary_euros' => 40000,
            'new_salary_euros' => 45000,
            'old_commission' => 500,
            'new_commission' => 500,
        ]);
        
        $noChange = SalaryHistory::factory()->create([
            'old_salary_euros' => 40000,
            'new_salary_euros' => 40000,
            'old_commission' => 500,
            'new_commission' => 500,
        ]);

        $this->assertStringContains('Salary: +€5,000.00', $salaryAndCommissionChange->change_summary);
        $this->assertStringContains('Commission: +€250.00', $salaryAndCommissionChange->change_summary);
        
        $this->assertEquals('Salary: +€5,000.00', $salaryOnlyChange->change_summary);
        $this->assertEquals('No changes', $noChange->change_summary);
    }

    /** @test */
    public function it_creates_comprehensive_history_from_salary_change()
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $salary = Salary::factory()->forUser($user)->create([
            'salary_euros' => 45000,
            'commission' => 750,
        ]);
        
        $originalValues = [
            'salary_local_currency' => 50000,
            'salary_euros' => 40000,
            'commission' => 500,
        ];

        $history = SalaryHistory::createFromSalaryChange(
            $salary,
            $originalValues,
            $admin->id,
            'Annual review increase'
        );

        $this->assertDatabaseHas('salary_histories', [
            'user_id' => $user->id,
            'old_salary_euros' => 40000,
            'new_salary_euros' => 45000,
            'old_commission' => 500,
            'new_commission' => 750,
            'changed_by' => $admin->id,
            'change_reason' => 'Annual review increase',
        ]);
        
        $this->assertIsArray($history->metadata);
        $this->assertArrayHasKey('ip_address', $history->metadata);
        $this->assertArrayHasKey('user_agent', $history->metadata);
        $this->assertArrayHasKey('timestamp', $history->metadata);
    }
}