<?php

namespace App\Console\Commands;

use App\Models\EmailCampaign;
use App\Models\WaitingListEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class TestWaitingListFeatures extends Command
{
    protected $signature = 'waiting-list:test-features 
                            {--seed : Seed test data first}
                            {--test-endpoints : Test API endpoints}';

    protected $description = 'Test waiting list admin panel features with sample data';

    public function handle()
    {
        if ($this->option('seed')) {
            $this->seedTestData();
        }

        if ($this->option('test-endpoints')) {
            $this->testEndpoints();
        }

        if (!$this->option('seed') && !$this->option('test-endpoints')) {
            $this->info('Use --seed to create test data or --test-endpoints to test API endpoints');
            $this->info('Or use both: php artisan waiting-list:test-features --seed --test-endpoints');
        }
    }

    protected function seedTestData()
    {
        $this->info('Seeding test data...');

        // Create entries with different dates for daily signups
        $this->info('Creating waiting list entries...');
        $now = now();
        $states = ['CA', 'NY', 'TX', 'FL', 'IL', 'PA', 'OH', 'GA', 'NC', 'MI', 'NJ', 'VA', 'WA', 'AZ', 'MA', 'TN', 'IN', 'MO', 'MD', 'WI', 'CO', 'MN', 'SC', 'AL', 'LA', 'KY', 'OR', 'OK', 'CT', 'IA', 'UT', 'AR', 'NV', 'MS', 'KS', 'NM', 'NE', 'WV', 'ID', 'HI', 'NH', 'ME', 'RI', 'MT', 'DE', 'SD', 'ND', 'AK', 'VT', 'WY'];
        
        for ($i = 0; $i < 150; $i++) {
            $daysAgo = rand(0, 35);
            $createdAt = $now->copy()->subDays($daysAgo);
            
            $roleType = rand(1, 3);
            $roles = [];
            if ($roleType === 1) {
                $roles = ['investor'];
            } elseif ($roleType === 2) {
                $roles = ['wholesaler'];
            } else {
                $roles = ['wholesaler', 'investor'];
            }

            $status = ['pending', 'pending', 'pending', 'account_created', 'cancelled'][rand(0, 4)];

            WaitingListEntry::factory()->create([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'selected_roles' => $roles,
                'status' => $status,
                'state' => $states[array_rand($states)],
                'ip_address' => fake()->ipv4(),
            ]);
        }

        $this->info("✓ Created 150 waiting list entries");

        // Create email campaigns
        $this->info('Creating email campaigns...');
        
        EmailCampaign::create([
            'name' => 'Product Launch Announcement',
            'subject' => 'Exciting News: Our Platform is Launching Soon!',
            'content' => 'We are thrilled to announce that our platform will be launching soon. Stay tuned for more updates!',
            'status' => 'sent',
            'recipients_count' => 150,
            'sent_count' => 150,
            'sent_at' => Carbon::now()->subDays(10),
        ]);

        EmailCampaign::create([
            'name' => 'Feature Update - Q1 2024',
            'subject' => 'New Features Coming in Q1 2024',
            'content' => 'We have exciting new features coming in Q1 2024. Check them out!',
            'status' => 'sent',
            'recipients_count' => 200,
            'sent_count' => 198,
            'sent_at' => Carbon::now()->subDays(5),
        ]);

        EmailCampaign::create([
            'name' => 'Investor Newsletter',
            'subject' => 'Monthly Investor Newsletter',
            'content' => 'This is a draft newsletter for investors.',
            'status' => 'draft',
            'recipients_count' => 0,
            'sent_count' => 0,
        ]);

        $this->info("✓ Created 3 email campaigns");

        // Display summary
        $this->newLine();
        $this->info('Summary:');
        $this->line("  Total entries: " . WaitingListEntry::count());
        $this->line("  By status:");
        $this->line("    - Pending: " . WaitingListEntry::where('status', 'pending')->count());
        $this->line("    - Account Created: " . WaitingListEntry::where('status', 'account_created')->count());
        $this->line("    - Cancelled: " . WaitingListEntry::where('status', 'cancelled')->count());
        $this->line("  By role:");
        $this->line("    - Investors: " . WaitingListEntry::whereJsonContains('selected_roles', 'investor')->whereJsonDoesntContain('selected_roles', 'wholesaler')->count());
        $this->line("    - Wholesalers: " . WaitingListEntry::whereJsonContains('selected_roles', 'wholesaler')->whereJsonDoesntContain('selected_roles', 'investor')->count());
        $this->line("    - Both: " . WaitingListEntry::whereJsonContains('selected_roles', 'wholesaler')->whereJsonContains('selected_roles', 'investor')->count());
        $this->line("  States: " . WaitingListEntry::whereNotNull('state')->distinct('state')->count());
        $this->line("  Email campaigns: " . EmailCampaign::count());
    }

    protected function testEndpoints()
    {
        $this->info('Testing API endpoints...');
        $this->newLine();

        // Note: This would require authentication token
        // In a real scenario, you'd get a token from login endpoint first
        
        $this->warn('Note: Endpoint testing requires authentication.');
        $this->warn('To test endpoints manually:');
        $this->line('1. Get admin token: POST /api/v1/login');
        $this->line('2. Use token in Authorization header: Bearer {token}');
        $this->newLine();
        $this->line('Endpoints to test:');
        $this->line('  GET  /api/v1/admin/waiting-list');
        $this->line('  GET  /api/v1/admin/waiting-list/stats');
        $this->line('  GET  /api/v1/admin/waiting-list/daily-signups');
        $this->line('  GET  /api/v1/admin/waiting-list/geographic-distribution');
        $this->line('  GET  /api/v1/admin/waiting-list/export/csv');
        $this->line('  GET  /api/v1/admin/waiting-list/export/excel');
        $this->line('  GET  /api/v1/admin/waiting-list/export/pdf');
        $this->line('  GET  /api/v1/admin/email-campaigns');
        $this->line('  GET  /api/v1/admin/email-campaigns/stats');
        $this->line('  POST /api/v1/admin/email-campaigns');
    }
}
