<?php

namespace Database\Seeders;

use App\Models\WaitingListEntry;
use App\Models\EmailCampaign;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class WaitingListTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating test waiting list entries...');

        // Create entries with different dates for daily signups testing
        $now = now();
        for ($i = 0; $i < 150; $i++) {
            $createdAt = $now->copy()->subDays(rand(0, 35));
            
            $roles = [];
            $roleType = rand(1, 3);
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
                'state' => $this->getRandomState(),
                'ip_address' => fake()->ipv4(),
            ]);
        }

        $this->command->info('Created 150 waiting list entries with varied dates, roles, and states');

        // Create some email campaigns
        $this->command->info('Creating test email campaigns...');
        
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

        $this->command->info('Created 3 email campaigns (2 sent, 1 draft)');
    }

    private function getRandomState(): string
    {
        $states = ['AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'];
        return $states[array_rand($states)];
    }
}
