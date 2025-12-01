<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WaitingListEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CreateAccountsFromWaitingListCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_accounts_from_pending_entries(): void
    {
        $entry = WaitingListEntry::factory()->create([
            'email' => 'test@example.com',
            'name' => 'Test User',
            'status' => 'pending',
        ]);

        Artisan::call('waiting-list:create-accounts');

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);

        $entry->refresh();
        $this->assertEquals('account_created', $entry->status);
        $this->assertNotNull($entry->account_created_at);
    }

    /** @test */
    public function it_skips_entries_without_pending_status(): void
    {
        WaitingListEntry::factory()->create([
            'status' => 'account_created',
        ]);

        WaitingListEntry::factory()->create([
            'status' => 'cancelled',
        ]);

        Artisan::call('waiting-list:create-accounts');

        // Should not create any users since entries are not pending
        $this->assertEquals(0, User::count());
    }

    /** @test */
    public function it_links_to_existing_user_if_email_exists(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $entry = WaitingListEntry::factory()->create([
            'email' => 'existing@example.com',
            'status' => 'pending',
        ]);

        Artisan::call('waiting-list:create-accounts');

        $entry->refresh();
        $this->assertEquals('account_created', $entry->status);
        $this->assertNotNull($entry->account_created_at);
    }

    /** @test */
    public function it_respects_limit_option(): void
    {
        WaitingListEntry::factory()->count(5)->create([
            'status' => 'pending',
        ]);

        Artisan::call('waiting-list:create-accounts', ['--limit' => 2]);

        $createdUsers = User::whereIn('email', WaitingListEntry::pluck('email'))->count();
        $this->assertEquals(2, $createdUsers);
    }

    /** @test */
    public function it_creates_account_for_specific_email(): void
    {
        WaitingListEntry::factory()->create([
            'email' => 'specific@example.com',
            'status' => 'pending',
        ]);

        WaitingListEntry::factory()->create([
            'email' => 'other@example.com',
            'status' => 'pending',
        ]);

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
        $entry = WaitingListEntry::factory()->create([
            'status' => 'pending',
        ]);

        Artisan::call('waiting-list:create-accounts', ['--dry-run' => true]);

        $this->assertDatabaseMissing('users', [
            'email' => $entry->email,
        ]);

        $entry->refresh();
        $this->assertEquals('pending', $entry->status); // Should not change
    }
}

