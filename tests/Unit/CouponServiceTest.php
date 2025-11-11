<?php

namespace Tests\Unit;

use App\Models\Coupon;
use App\Models\SubscriptionPlan;
use App\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CouponService $couponService;
    protected SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->couponService = new CouponService();
        $this->plan = SubscriptionPlan::factory()->create([
            'price' => 100.00,
        ]);
    }

    /** @test */
    public function it_validates_valid_coupon(): void
    {
        $coupon = Coupon::factory()->create([
            'code' => 'VALID50',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addYear(),
            'is_active' => true,
        ]);

        $result = $this->couponService->validateCoupon('VALID50', $this->plan->id);

        $this->assertTrue($result['valid']);
        $this->assertEquals($coupon->id, $result['coupon']->id);
    }

    /** @test */
    public function it_rejects_invalid_coupon_code(): void
    {
        $result = $this->couponService->validateCoupon('INVALID', $this->plan->id);

        $this->assertFalse($result['valid']);
        $this->assertEquals('Coupon code not found', $result['error']);
    }

    /** @test */
    public function it_rejects_expired_coupon(): void
    {
        Coupon::factory()->create([
            'code' => 'EXPIRED',
            'valid_from' => now()->subYear(),
            'valid_until' => now()->subDay(),
            'is_active' => true,
        ]);

        $result = $this->couponService->validateCoupon('EXPIRED', $this->plan->id);

        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function it_rejects_inactive_coupon(): void
    {
        Coupon::factory()->create([
            'code' => 'INACTIVE',
            'is_active' => false,
        ]);

        $result = $this->couponService->validateCoupon('INACTIVE', $this->plan->id);

        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function it_calculates_percentage_discount_correctly(): void
    {
        $coupon = Coupon::factory()->create([
            'discount_type' => 'percentage',
            'discount_value' => 25,
        ]);

        $result = $this->couponService->calculateDiscount($this->plan, $coupon);

        $this->assertEquals(100.00, $result['original_price']);
        $this->assertEquals(25.00, $result['discount_amount']);
        $this->assertEquals(75.00, $result['discounted_price']);
    }

    /** @test */
    public function it_calculates_fixed_amount_discount_correctly(): void
    {
        $coupon = Coupon::factory()->create([
            'discount_type' => 'fixed_amount',
            'discount_value' => 30.00,
        ]);

        $result = $this->couponService->calculateDiscount($this->plan, $coupon);

        $this->assertEquals(100.00, $result['original_price']);
        $this->assertEquals(30.00, $result['discount_amount']);
        $this->assertEquals(70.00, $result['discounted_price']);
    }

    /** @test */
    public function it_respects_maximum_discount_limit(): void
    {
        $coupon = Coupon::factory()->create([
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'maximum_discount' => 20.00,
        ]);

        $result = $this->couponService->calculateDiscount($this->plan, $coupon);

        $this->assertEquals(20.00, $result['discount_amount']); // Capped at max
        $this->assertEquals(80.00, $result['discounted_price']);
    }

    /** @test */
    public function it_checks_minimum_amount_requirement(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'price' => 50.00,
        ]);

        $coupon = Coupon::factory()->create([
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'minimum_amount' => 100.00,
        ]);

        $result = $this->couponService->calculateDiscount($plan, $coupon);

        $this->assertArrayHasKey('error', $result);
    }

    /** @test */
    public function it_validates_and_calculates_in_one_call(): void
    {
        $coupon = Coupon::factory()->create([
            'code' => 'TEST50',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'maximum_discount' => null, // No cap
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addYear(),
            'is_active' => true,
            'usage_limit' => 100,
            'usage_count' => 0,
            'applicable_plans' => null,
        ]);

        $result = $this->couponService->validateAndCalculate('TEST50', $this->plan->id);

        $this->assertTrue($result['valid']);
        $this->assertEquals(100.00, $result['original_price']);
        $this->assertEquals(50.00, $result['discount_amount']);
        $this->assertEquals(50.00, $result['discounted_price']);
    }

    /** @test */
    public function it_increments_coupon_usage(): void
    {
        $coupon = Coupon::factory()->create([
            'usage_count' => 5,
        ]);

        $this->couponService->applyCoupon($coupon);

        $coupon->refresh();
        $this->assertEquals(6, $coupon->usage_count);
    }
}

