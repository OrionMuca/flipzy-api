<?php

namespace App\Console\Commands;

use App\Models\WaitingListEntry;
use App\Models\User;
use App\Mail\AccountCreatedMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CreateAccountsFromWaitingList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'waiting-list:create-accounts 
                            {--dry-run : Run without making changes}
                            {--limit= : Limit number of accounts to create}
                            {--email= : Create account for specific email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create user accounts from waiting list entries with pending status';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $email = $this->option('email');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }

        // Get entries to process
        $query = WaitingListEntry::where('status', 'pending')
            ->with(['coupon']);

        if ($email) {
            $query->where('email', $email);
        }

        $entries = $limit ? $query->limit($limit)->get() : $query->get();

        if ($entries->isEmpty()) {
            $this->warn('No waiting list entries found with pending status.');
            return \Symfony\Component\Console\Command\Command::SUCCESS;
        }

        $this->info("Found {$entries->count()} entry/entries to process.");

        $created = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($entries as $entry) {
            try {
                DB::beginTransaction();

                // Check if user already exists
                $existingUser = User::where('email', $entry->email)->first();

                if ($existingUser) {
                    $this->warn("User with email {$entry->email} already exists. Skipping...");
                    
                    if (!$dryRun) {
                        $entry->markAccountCreated();
                    }
                    
                    $skipped++;
                    DB::commit();
                    continue;
                }

                // Generate temporary password
                $tempPassword = Str::random(16);

                if ($dryRun) {
                    $this->line("Would create user: {$entry->email}");
                    $this->line("  Name: {$entry->name}");
                    $this->line("  Phone: {$entry->phone_number}");
                    $companyName = $entry->company_name ?? 'N/A';
                    $this->line("  Company: {$companyName}");
                    $roles = !empty($entry->selected_roles) ? implode(', ', $entry->selected_roles) : 'None';
                    $this->line("  Roles: {$roles}");
                    $this->line("  Temp Password: {$tempPassword}");
                } else {
                    // Create user account
                    $user = User::create([
                        'name' => $entry->name,
                        'email' => $entry->email,
                        'phone_number' => $entry->phone_number,
                        'company_name' => $entry->company_name,
                        'password' => Hash::make($tempPassword),
                        'email_verified_at' => now(),
                    ]);

                    // Assign roles from selected_roles array
                    if (!empty($entry->selected_roles) && is_array($entry->selected_roles)) {
                        foreach ($entry->selected_roles as $role) {
                            if (in_array($role, ['wholesaler', 'investor'])) {
                                try {
                                    $user->assignRole($role);
                                } catch (\Exception $e) {
                                    Log::warning('Failed to assign role to user', [
                                        'user_id' => $user->id,
                                        'role' => $role,
                                        'error' => $e->getMessage(),
                                    ]);
                                    // Continue even if role assignment fails
                                }
                            }
                        }
                    }

                    // Generate password reset token
                    $token = Password::createToken($user);

                    // Mark entry as account created
                    $entry->markAccountCreated();

                    // Send account created email
                    try {
                        Mail::to($user->email)->send(new AccountCreatedMail(
                            $user,
                            $tempPassword,
                            $token,
                            '' // Will be constructed in Mailable
                        ));
                    } catch (\Exception $e) {
                        Log::warning('Failed to send account created email', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'error' => $e->getMessage(),
                        ]);
                        // Continue even if email fails
                    }

                    // Get frontend URL for display
                    $frontendUrl = config('app.frontend_url');
                    $resetUrl = rtrim($frontendUrl, '/') . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);

                    $this->info("✓ Created account for: {$entry->email}");
                    $this->line("  User ID: {$user->id}");
                    $this->line("  Temporary Password: {$tempPassword}");
                    $this->line("  Password Reset Link: {$resetUrl}");

                    $created++;
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $errors++;
                $this->error("✗ Failed to create account for {$entry->email}: {$e->getMessage()}");
                Log::error('Failed to create account from waiting list', [
                    'entry_id' => $entry->id,
                    'email' => $entry->email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->line("  Created: {$created}");
        $this->line("  Skipped: {$skipped}");
        $this->line("  Errors: {$errors}");

        if ($dryRun) {
            $this->warn("This was a dry run. No changes were made.");
        }

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }

}
