<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => fake()->boolean(50) ? Property::factory() : null, // 50% chance of being property-related
            'participant_one_id' => User::factory(),
            'participant_two_id' => User::factory(),
            'last_message_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the conversation is about a property.
     */
    public function aboutProperty(Property $property): static
    {
        return $this->state(fn (array $attributes) => [
            'property_id' => $property->id,
        ]);
    }

    /**
     * Indicate that the conversation is general (not about a property).
     */
    public function general(): static
    {
        return $this->state(fn (array $attributes) => [
            'property_id' => null,
        ]);
    }
}
