<?php

namespace Database\Factories;

use App\Models\EmailCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailCampaign>
 */
class EmailCampaignFactory extends Factory
{
    protected $model = EmailCampaign::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true) . ' Campaign',
            'subject' => fake()->sentence(),
            'content' => fake()->paragraph(3),
            'status' => 'draft',
            'recipients_count' => 0,
            'sent_count' => 0,
            'scheduled_at' => null,
            'sent_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'recipients_count' => fake()->numberBetween(50, 500),
            'sent_count' => fake()->numberBetween(45, 495),
            'sent_at' => Carbon::now()->subDays(rand(1, 30)),
        ]);
    }
}

