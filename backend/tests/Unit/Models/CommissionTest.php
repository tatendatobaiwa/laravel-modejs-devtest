<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Commission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CommissionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_fillable_attributes()
    {
        $fillable = ['amount', 'is_active', 'description'];
        $commission = new Commission();
        
        $this->assertEquals($fillable, $commission->getFillable());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $commission = Commission::create([
            'amount' => '750.50',
            'is_active' => true,
            'description' => 'Test commission',
        ]);

        $this->assertIsFloat($commission->amount);
        $this->assertIsBool($commission->is_active);
        $this->assertEquals(750.50, $commission->amount);
        $this->assertTrue($commission->is_active);
    }

    /** @test */
    public function it_gets_default_commission()
    {
        $commission = Commission::getDefaultCommission();

        $this->assertInstanceOf(Commission::class, $commission);
        $this->assertEquals(500.00, $commission->amount);
        $this->assertTrue($commission->is_active);
        $this->assertEquals('Default commission rate', $commission->description);
    }

    /** @test */
    public function it_returns_existing_active_commission()
    {
        $existingCommission = Commission::create([
            'amount' => 750.00,
            'is_active' => true,
            'description' => 'Existing commission',
        ]);

        $commission = Commission::getDefaultCommission();

        $this->assertEquals($existingCommission->id, $commission->id);
        $this->assertEquals(750.00, $commission->amount);
    }

    /** @test */
    public function it_creates_default_commission_when_none_exists()
    {
        $this->assertDatabaseEmpty('commissions');

        $commission = Commission::getDefaultCommission();

        $this->assertDatabaseHas('commissions', [
            'amount' => 500.00,
            'is_active' => true,
            'description' => 'Default commission rate',
        ]);
    }

    /** @test */
    public function it_updates_commission_amount()
    {
        $commission = Commission::create([
            'amount' => 500.00,
            'is_active' => true,
            'description' => 'Initial commission',
        ]);

        $updatedCommission = Commission::updateCommission(750.00);

        $this->assertEquals($commission->id, $updatedCommission->id);
        $this->assertEquals(750.00, $updatedCommission->amount);
        $this->assertTrue($updatedCommission->is_active);
    }

    /** @test */
    public function it_creates_commission_when_updating_non_existent()
    {
        $this->assertDatabaseEmpty('commissions');

        $commission = Commission::updateCommission(600.00);

        $this->assertDatabaseHas('commissions', [
            'amount' => 600.00,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_handles_decimal_precision_correctly()
    {
        $commission = Commission::create([
            'amount' => 999.99,
            'is_active' => true,
            'description' => 'High precision commission',
        ]);

        $this->assertEquals(999.99, $commission->amount);
        $this->assertEquals('999.99', $commission->getAttributes()['amount']);
    }

    /** @test */
    public function it_can_be_deactivated()
    {
        $commission = Commission::create([
            'amount' => 500.00,
            'is_active' => true,
            'description' => 'Active commission',
        ]);

        $commission->update(['is_active' => false]);

        $this->assertFalse($commission->fresh()->is_active);
    }

    /** @test */
    public function it_only_returns_active_commission_as_default()
    {
        $inactiveCommission = Commission::create([
            'amount' => 600.00,
            'is_active' => false,
            'description' => 'Inactive commission',
        ]);

        $activeCommission = Commission::create([
            'amount' => 750.00,
            'is_active' => true,
            'description' => 'Active commission',
        ]);

        $defaultCommission = Commission::getDefaultCommission();

        $this->assertEquals($activeCommission->id, $defaultCommission->id);
        $this->assertEquals(750.00, $defaultCommission->amount);
    }

    /** @test */
    public function it_validates_amount_is_numeric()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Commission::create([
            'amount' => 'not-a-number',
            'is_active' => true,
            'description' => 'Invalid commission',
        ]);
    }

    /** @test */
    public function it_handles_null_description()
    {
        $commission = Commission::create([
            'amount' => 500.00,
            'is_active' => true,
            'description' => null,
        ]);

        $this->assertNull($commission->description);
    }

    /** @test */
    public function it_defaults_is_active_to_false_when_not_specified()
    {
        $commission = Commission::create([
            'amount' => 500.00,
            'description' => 'Test commission',
        ]);

        $this->assertFalse($commission->is_active);
    }
}