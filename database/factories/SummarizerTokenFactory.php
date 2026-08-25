<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use BAGArt\TelegramBotSummarizer\Models\SummarizerToken;

/** @extends Factory<SummarizerToken> */
class SummarizerTokenFactory extends Factory
{
    protected $model = SummarizerToken::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'bot_id' => 'bot_a',
            'provider_key' => 'openai',
            'label' => 'Main key',
            'token' => 'sk-test1234567890abcdef1234567890ab',
            'created_by_tg_id' => $this->faker->numberBetween(1000, 9999999),
            'created_by_username' => $this->faker->userName(),
            'created_at' => now(),
        ];
    }
}
