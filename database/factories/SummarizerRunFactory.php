<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use BAGArt\TelegramBotSummarizer\Models\SummarizerRun;

/** @extends Factory<SummarizerRun> */
class SummarizerRunFactory extends Factory
{
    protected $model = SummarizerRun::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'bot_id' => 'bot_a',
            'chat_id' => -100100,
            'period_from' => time() - 7200,
            'period_to' => time(),
            'message_count' => 42,
            'participant_count' => 7,
            'status' => SummarizerRun::STATUS_SUCCESS,
            'summary_text' => 'A summary happened. It was witty.',
            'provider_key' => 'openai',
            'model' => 'gpt-4o-mini',
            'duration_ms' => 1200,
            'created_at' => now(),
        ];
    }
}
