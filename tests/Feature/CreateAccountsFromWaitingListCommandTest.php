<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WaitingListEntry;
use App\Models\WaitingListTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CreateAccountsFromWaitingListCommandTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plan = SubscriptionPlan::factory()->create([
            'price' => 49.99,
            'billing_interval' => 'monthly',
        ]);
    }

    /** @test */
    public function it_creates_accounts_from_payment_completed_entries(): void
    {
        $entry = WaitingListEntry::factory()->paymentCompleted()->create([
            'email' => 'test@example.com',
            'name' => 'Test User',
            'subscription_plan_id' => $this->plan->id,
            'stripe_customer_id' => 'cus_test123',
            'stripe_subscription_id' => 'sub_test123',
        ]);

        WaitingListTransaction::factory()->completed()->create([
            'waiting_list_entry_id' => $entry->id,
            'amount' => 49.99,
        ]);

        Artisan::call('waiting-list:create-accounts');

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'subscription_plan_id' => $this->plan->id,
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'subscription',
            'status' => 'completed',
        ]);

        $entry->refresh();
        $this->assertEquals('account_created', $entry->status);
        $this->assertNotNull($entry->account_created_at);
    }

    /** @test */
    public function it_skips_entries_without_payment_completed_status(): void
    {
        WaitingListEntry::factory()->create([
            'status' => 'pending',
            'subscription_plan_id' => $this->plan->id,
        ]);

        Artisan::call('waiting-list:create-accounts');

        $this->assertDatabaseMissing('users', [
            'email' => 'test@example.com',
        ]);
    }

    /** @test */
    public function it_links_to_existing_user_if_email_exists(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $entry = WaitingListEntry::factory()->paymentCompleted()->create([
            'email' => 'existing@example.com',
            'subscription_plan_id' => $this->plan->id,
        ]);

        WaitingListTransaction::factory()->completed()->create([
            'waiting_list_entry_id' => $entry->id,
        ]);

        Artisan::call('waiting-list:create-accounts');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $existingUser->id,
            'subscription_plan_id' => $this->plan->id,
        ]);

        $entry->refresh();
        $this->assertEquals('account_created', $entry->status);
    }

    /** @test */
    public function it_respects_limit_option(): void
    {
        WaitingListEntry::factory()->count(5)->paymentCompleted()->create([
            'subscription_plan_id' => $this->plan->id,
        ]);

        foreach (WaitingListEntry::all() as $entry) {
            WaitingListTransaction::factory()->completed()->create([
                'waiting_list_entry_id' => $entry->id,
            ]);
        }

        Artisan::call('waiting-list:create-accounts', ['--limit' => 2]);

        $createdUsers = User::whereIn('email', WaitingListEntry::pluck('email'))->count();
        $this->assertEquals(2, $createdUsers);
    }

    /** @test */
    public function it_creates_account_for_specific_email(): void
    {
        WaitingListEntry::factory()->paymentCompleted()->create([
            'email' => 'specific@example.com',
            'subscription_plan_id' => $this->plan->id,
        ]);

        WaitingListEntry::factory()->paymentCompleted()->create([
            'email' => 'other@example.com',
            'subscription_plan_id' => $this->plan->id,
        ]);

        foreach (WaitingListEntry::all() as $entry) {
            WaitingListTransaction::factory()->completed()->create([
                'waiting_list_entry_id' => $entry->id,
            ]);
        }

        Artisan::call('waiting-list:create-accounts', ['--email' => 'specific@example.com']);

        $this->assertDatabaseHas('users', [
            'email' => 'specific@example.com',
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'other@example.com',
        ]);
    }

    /** @test */
    public function it_handles_dry_run_mode(): void
    {
        $entry = WaitingListEntry::factory()->paymentCompleted()->create([
            'subscription_plan_id' => $this->plan->id,
        ]);

        WaitingListTransaction::factory()->completed()->create([
            'waiting_list_entry_id' => $entry->id,
        ]);

        Artisan::call('waiting-list:create-accounts', ['--dry-run' => true]);

        $this->assertDatabaseMissing('users', [
            'email' => $entry->email,
        ]);

        $entry->refresh();
        $this->assertEquals('payment_completed', $entry->status); // Should not change
    }
}

