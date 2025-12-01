<?php

namespace Tests\Unit;

use App\Models\Coupon;
use App\Models\WaitingListEntry;
use App\Services\CouponService;
use App\Services\WaitingListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WaitingListServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WaitingListService $waitingListService;
    protected CouponService $couponService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->couponService = new CouponService();
        $this->waitingListService = new WaitingListService($this->couponService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_registers_new_waiting_list_entry(): void
    {
        $result = $this->waitingListService->register([
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('waiting_list_entries', [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function it_registers_entry_with_coupon(): void
    {
        $coupon = Coupon::factory()->create([
            'code' => 'TEST50',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addYear(),
            'is_active' => true,
        ]);

        $result = $this->waitingListService->register([
            'email' => 'test@example.com',
            'name' => 'Test User',
            'coupon_code' => 'TEST50',
        ]);

        $this->assertTrue($result['success']);
        
        $entry = WaitingListEntry::where('email', 'test@example.com')->first();
        $this->assertNotNull($entry->coupon_id);
        $this->assertEquals('TEST50', $entry->coupon_code);
    }

    /** @test */
    public function it_rejects_duplicate_email(): void
    {
        WaitingListEntry::factory()->create([
            'email' => 'test@example.com',
        ]);

        $result = $this->waitingListService->register([
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already registered', $result['error']);
    }

    /** @test */
    public function it_rejects_invalid_coupon(): void
    {
        $result = $this->waitingListService->register([
            'email' => 'test@example.com',
            'name' => 'Test User',
            'coupon_code' => 'INVALID',
        ]);

        $this->assertFalse($result['success']);
    }

    /** @test */
    public function it_gets_entry_by_token(): void
    {
        $entry = WaitingListEntry::factory()->create([
            'email' => 'test@example.com',
            'verification_token' => 'test-token-123',
        ]);

        $result = $this->waitingListService->getEntryByToken('test@example.com', 'test-token-123');

        $this->assertNotNull($result);
        $this->assertEquals($entry->id, $result->id);
    }

    /** @test */
    public function it_returns_null_for_invalid_token(): void
    {
        $result = $this->waitingListService->getEntryByToken('test@example.com', 'invalid-token');

        $this->assertNull($result);
    }
}

