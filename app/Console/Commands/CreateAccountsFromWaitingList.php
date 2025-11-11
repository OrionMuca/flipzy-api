<?php

namespace App\Console\Commands;

use App\Models\WaitingListEntry;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\WaitingListTransaction;
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
    protected $description = 'Create user accounts from waiting list entries with payment completed status';

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
        $query = WaitingListEntry::where('status', 'payment_completed')
            ->with(['plan', 'transactions']);

        if ($email) {
            $query->where('email', $email);
        }

        $entries = $limit ? $query->limit($limit)->get() : $query->get();

        if ($entries->isEmpty()) {
            $this->warn('No waiting list entries found with payment_completed status.');
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
                    $this->warn("User with email {$entry->email} already exists. Linking subscription...");
                    
                    // Link subscription to existing user
                    $this->linkSubscriptionToUser($entry, $existingUser, $dryRun);
                    
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
                    $this->line("  Plan: {$entry->plan->name}");
                    $this->line("  Temp Password: {$tempPassword}");
                } else {
                    // Create user account
                    $user = User::create([
                        'name' => $entry->name,
                        'email' => $entry->email,
                        'password' => Hash::make($tempPassword),
                        'email_verified_at' => now(),
                        'stripe_customer_id' => $entry->stripe_customer_id,
                    ]);

                    // Create subscription
                    $subscription = Subscription::create([
                        'user_id' => $user->id,
                        'subscription_plan_id' => $entry->subscription_plan_id,
                        'status' => 'active',
                        'starts_at' => now(),
                        'ends_at' => $entry->plan->billing_interval === 'monthly' 
                            ? now()->addMonth() 
                            : now()->addYear(),
                        'stripe_subscription_id' => $entry->stripe_subscription_id,
                        'stripe_customer_id' => $entry->stripe_customer_id,
                    ]);

                    // Migrate transactions
                    foreach ($entry->transactions as $waitingListTransaction) {
                        if ($waitingListTransaction->isCompleted()) {
                            Transaction::create([
                                'user_id' => $user->id,
                                'subscription_id' => $subscription->id,
                                'type' => 'subscription',
                                'status' => 'completed',
                                'amount' => $waitingListTransaction->amount,
                                'currency' => $waitingListTransaction->currency,
                                'original_amount' => $waitingListTransaction->original_amount,
                                'stripe_payment_intent_id' => $waitingListTransaction->stripe_payment_intent_id,
                                'stripe_charge_id' => $waitingListTransaction->stripe_charge_id,
                                'stripe_customer_id' => $entry->stripe_customer_id,
                                'description' => $waitingListTransaction->description ?? "Subscription: {$entry->plan->name}",
                                'metadata' => array_merge($waitingListTransaction->metadata ?? [], [
                                    'from_waiting_list' => true,
                                    'waiting_list_entry_id' => $entry->id,
                                ]),
                                'processed_at' => $waitingListTransaction->processed_at,
                            ]);
                        }
                    }

                    // Generate password reset token
                    $token = Password::createToken($user);
                    
                    // Note: AccountCreatedMail will construct the frontend URL itself
                    // We pass the token here, but the Mailable will build the frontend URL

                    // Mark entry as account created
                    $entry->markAccountCreated();

                    // Send account created email
                    try {
                        Mail::to($user->email)->send(new AccountCreatedMail(
                            $user,
                            $tempPassword,
                            $token,
                            '', // Will be constructed in Mailable
                            $entry->plan->name
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
                    $this->line("  Subscription ID: {$subscription->id}");
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

    /**
     * Link subscription to existing user
     */
    protected function linkSubscriptionToUser(WaitingListEntry $entry, User $user, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        // Check if user already has active subscription
        $existingSubscription = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($existingSubscription) {
            $this->warn("  User already has active subscription. Skipping subscription creation.");
            return;
        }

        // Create subscription
        Subscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $entry->subscription_plan_id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => $entry->plan->billing_interval === 'monthly' 
                ? now()->addMonth() 
                : now()->addYear(),
            'stripe_subscription_id' => $entry->stripe_subscription_id,
            'stripe_customer_id' => $entry->stripe_customer_id ?? $user->stripe_customer_id,
        ]);

        // Migrate transactions
        foreach ($entry->transactions as $waitingListTransaction) {
            if ($waitingListTransaction->isCompleted()) {
                Transaction::create([
                    'user_id' => $user->id,
                    'subscription_id' => Subscription::where('user_id', $user->id)->latest()->first()->id,
                    'type' => 'subscription',
                    'status' => 'completed',
                    'amount' => $waitingListTransaction->amount,
                    'currency' => $waitingListTransaction->currency,
                    'original_amount' => $waitingListTransaction->original_amount,
                    'stripe_payment_intent_id' => $waitingListTransaction->stripe_payment_intent_id,
                    'stripe_charge_id' => $waitingListTransaction->stripe_charge_id,
                    'stripe_customer_id' => $entry->stripe_customer_id ?? $user->stripe_customer_id,
                    'description' => $waitingListTransaction->description ?? "Subscription: {$entry->plan->name}",
                    'metadata' => array_merge($waitingListTransaction->metadata ?? [], [
                        'from_waiting_list' => true,
                        'waiting_list_entry_id' => $entry->id,
                    ]),
                    'processed_at' => $waitingListTransaction->processed_at,
                ]);
            }
        }
    }
}
