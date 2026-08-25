<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use BAGArt\TelegramBotSummarizer\Models\SummarizerMessage;

/** @extends Factory<SummarizerMessage> */
class SummarizerMessageFactory extends Factory
{
    protected $model = SummarizerMessage::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        self::$sequence++;

        return [
            'bot_id' => 'bot_a',
            'chat_id' => -100100,
            'message_id' => self::$sequence,
            'user_tg_id' => $this->faker->numberBetween(10, 99),
            'username' => $this->faker->userName(),
            'display_name' => $this->faker->firstName(),
            'text' => $this->faker->sentence(),
            'is_bot' => false,
            'sent_at' => time() - random_int(0, 3600),
        ];
    }

    public function inChat(string $botId, int $chatId): static
    {
        return $this->state(fn (): array => [
            'bot_id' => $botId,
            'chat_id' => $chatId,
        ]);
    }
}
