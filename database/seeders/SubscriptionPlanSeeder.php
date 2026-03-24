<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Free Plan
        SubscriptionPlan::firstOrCreate(
            ['slug' => 'free'],
            [
            'name' => 'Free',
            'slug' => 'free',
            'description' => 'Basic plan for getting started',
            'price' => 0,
            'billing_interval' => 'monthly',
            'features' => [
                'View up to 5 properties',
                'Basic messaging',
                'Property search',
            ],
            'max_properties' => 5,
            'max_messages' => 10,
            'has_ai_estimates' => false,
            'has_api_access' => false,
            'is_active' => false,
        ]);

        // Premium Plan
        SubscriptionPlan::firstOrCreate(
            ['slug' => 'premium'],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'Unlimited properties and AI estimates',
                'price' => 49.99,
                'billing_interval' => 'monthly',
                'features' => [
                    'Unlimited property viewing',
                    'Unlimited messaging',
                    'AI rehab cost estimates',
                    'Priority support',
                    'Advanced analytics',
                ],
                'max_properties' => null,
                'max_messages' => null,
                'has_ai_estimates' => true,
                'has_api_access' => false,
                'is_active' => false,
                'stripe_price_id' => env('STRIPE_PRICE_PREMIUM'),
            ]
        );

        // VIP Plan
        SubscriptionPlan::firstOrCreate(
            ['slug' => 'vip'],
            [
                'name' => 'VIP',
                'slug' => 'vip',
                'description' => 'Full access with API integration',
                'price' => 199.99,
                'billing_interval' => 'monthly',
                'features' => [
                    'Everything in Premium',
                    'API access',
                    'Custom integrations',
                    'Dedicated support',
                    'White-label options',
                ],
                'max_properties' => null,
                'max_messages' => null,
                'has_ai_estimates' => true,
                'has_api_access' => true,
                'is_active' => false,
                'stripe_price_id' => env('STRIPE_PRICE_VIP'),
            ]
        );

        // Basic Plan — $99/month base subscription
        SubscriptionPlan::updateOrCreate(
            ['slug' => 'basic'],
            [
                'name' => 'Basic',
                'description' => 'Base subscription for wholesalers. Publish properties for a one-time $199 fee each.',
                'price' => 99.00,
                'billing_interval' => 'monthly',
                'features' => [
                    'Unlimited property listings',
                    'Unlimited messaging',
                    'AI rehab cost estimates',
                    'Property analytics',
                    'Investor matching',
                ],
                'max_properties' => null,
                'max_messages' => null,
                'has_ai_estimates' => true,
                'has_api_access' => false,
                'is_active' => true,
                'stripe_price_id' => env('STRIPE_PRICE_BASIC'),
            ]
        );

        $this->command->info('Created subscription plans: Free (inactive), Premium (inactive), VIP (inactive), Basic (active)');
    }
}

