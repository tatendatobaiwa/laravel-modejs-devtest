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
        $commission = Commission::factory()->create([
            'amount' => '750.50',
            'is_active' => '1',
        ]);

        $this->assertIsFloat($commission->amount);
        $this->assertIsBool($commission->is_active);
    }

    /** @test */
    public function it_can_create_commission_with_valid_data()
    {
        $commission = Commission::create([
            'amount' => 600.00,
            'is_active' => true,
            'description' => 'Standard commission rate',
        ]);

        $this->assertEquals(600.00, $commission->amount);
        $this->assertTrue($commission->is_active);
        $this->assertEquals('Standard commission rate', $commission->description);
    }

    /** @test */
    public function it_gets_default_commission()
    {
        // Test when no commission exists
        $defaultCommission = Commission::getDefaultCommission();
        
        $this->assertInstanceOf(Commission::class, $defaultCommission);
        $this->assertEquals(500.00, $defaultCommission->amount);
        $this->assertTrue($defaultCommission->is_active);
        $this->assertEquals('Default commission rate', $defaultCommission->description);
    }

    /** @test */
    public function it_gets_existing_active_commission()
    {
        $existingCommission = Commission::create([
            'amount' => 750.00,
            'is_active' => true,
            'description' => 'Custom commission rate',
        ]);

        $defaultCommission = Commission::getDefaultCommission();
        
        $this->assertEquals($existingCommission->id, $defaultCommission->id);
        $this->assertEquals(750.00, $defaultCommission->amount);
        $this->assertEquals('Custom commission rate', $defaultCommission->description);
    }

    /** @test */
    public function it_creates_default_when_only_inactive_commissions_exist()
    {
        Commission::create([
            'amount' => 750.00,
            'is_active' => false,
            'description' => 'Inactive commission rate',
        ]);

        $defaultCommission = Commission::getDefaultCommission();
        
        $this->assertEquals(500.00, $defaultCommission->amount);
        $this->assertTrue($defaultCommission->is_active);
        $this->assertEquals('Default commission rate', $defaultCommission->description);
    }

    /** @test */
    public function it_updates_commission_amount()
    {
        $commission = Commission::create([
            'amount' => 500.00,
            'is_active' => true,
            'description' => 'Initial commission',
        ]);

        $updatedCommission = Commission::updateCommission(800.00);
        
        $this->assertEquals($commission->id, $updatedCommission->id);
        $this->assertEquals(800.00, $updatedCommission->amount);
        $this->assertTrue($updatedCommission->is_active);
    }

    /** @test */
    public function it_creates_new_commission_when_updating_and_none_exists()
    {
        $updatedCommission = Commission::updateCommission(650.00);
        
        $this->assertInstanceOf(Commission::class, $updatedCommission);
        $this->assertEquals(650.00, $updatedCommission->amount);
        $this->assertTrue($updatedCommission->is_active);
    }

    /** @test */
    public function it_handles_decimal_amounts_correctly()
    {
        $commission = Commission::create([
            'amount' => 599.99,
            'is_active' => true,
            'description' => 'Decimal commission',
        ]);

        $this->assertEquals(599.99, $commission->amount);
        
        // Test that it's stored with proper precision
        $retrieved = Commission::find($commission->id);
        $this->assertEquals(599.99, $retrieved->amount);
    }

    /** @test */
    public function it_can_have_multiple_commissions_but_only_one_active()
    {
        $activeCommission = Commission::create([
            'amount' => 600.00,
            'is_active' => true,
            'description' => 'Active commission',
        ]);

        $inactiveCommission1 = Commission::create([
            'amount' => 500.00,
            'is_active' => false,
            'description' => 'Old commission 1',
        ]);

        $inactiveCommission2 = Commission::create([
            'amount' => 550.00,
            'is_active' => false,
            'description' => 'Old commission 2',
        ]);

        $defaultCommission = Commission::getDefaultCommission();
        
        $this->assertEquals($activeCommission->id, $defaultCommission->id);
        $this->assertEquals(600.00, $defaultCommission->amount);
    }

    /** @test */
    public function it_validates_amount_is_numeric()
    {
        $commission = new Commission();
        $commission->amount = 'invalid';
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        $commission->save();
    }

    /** @test */
    public function it_can_store_null_description()
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

        // Laravel will use the database default or null, which should be false
        $this->assertFalse($commission->is_active);
    }

    /** @test */
    public function it_can_query_active_commissions()
    {
        Commission::create(['amount' => 500.00, 'is_active' => true, 'description' => 'Active 1']);
        Commission::create(['amount' => 600.00, 'is_active' => true, 'description' => 'Active 2']);
        Commission::create(['amount' => 700.00, 'is_active' => false, 'description' => 'Inactive']);

        $activeCommissions = Commission::where('is_active', true)->get();
        
        $this->assertCount(2, $activeCommissions);
    }

    /** @test */
    public function it_can_query_inactive_commissions()
    {
        Commission::create(['amount' => 500.00, 'is_active' => true, 'description' => 'Active']);
        Commission::create(['amount' => 600.00, 'is_active' => false, 'description' => 'Inactive 1']);
        Commission::create(['amount' => 700.00, 'is_active' => false, 'description' => 'Inactive 2']);

        $inactiveCommissions = Commission::where('is_active', false)->get();
        
        $this->assertCount(2, $inactiveCommissions);
    }

    /** @test */
    public function it_handles_zero_amount()
    {
        $commission = Commission::create([
            'amount' => 0.00,
            'is_active' => true,
            'description' => 'Zero commission',
        ]);

        $this->assertEquals(0.00, $commission->amount);
    }

    /** @test */
    public function it_handles_large_amounts()
    {
        $largeAmount = 99999.99;
        $commission = Commission::create([
            'amount' => $largeAmount,
            'is_active' => true,
            'description' => 'Large commission',
        ]);

        $this->assertEquals($largeAmount, $commission->amount);
    }

    /** @test */
    public function it_maintains_precision_for_decimal_amounts()
    {
        $preciseAmount = 123.45;
        $commission = Commission::create([
            'amount' => $preciseAmount,
            'is_active' => true,
            'description' => 'Precise commission',
        ]);

        // Verify precision is maintained
        $this->assertEquals($preciseAmount, $commission->amount);
        $this->assertEquals('123.45', number_format($commission->amount, 2));
    }

    /** @test */
    public function it_can_update_existing_commission_status()
    {
        $commission = Commission::create([
            'amount' => 500.00,
            'is_active' => true,
            'description' => 'Test commission',
        ]);

        $commission->update(['is_active' => false]);
        
        $this->assertFalse($commission->fresh()->is_active);
    }

    /** @test */
    public function it_can_update_commission_description()
    {
        $commission = Commission::create([
            'amount' => 500.00,
            'is_active' => true,
            'description' => 'Original description',
        ]);

        $commission->update(['description' => 'Updated description']);
        
        $this->assertEquals('Updated description', $commission->fresh()->description);
    }
}