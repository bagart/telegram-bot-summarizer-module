<?php

declare(strict_types=1);

require_once __DIR__.'/helpers.php';

use BAGArt\TelegramBotManagement\Models\TgBot;
use BAGArt\TelegramBotSummarizer\Models\SummarizerToken;

beforeEach(function () {
    config('telegram.modules');
    TgBot::create(['bot_id' => 'test_bot', 'token' => '123:test']);
});

it('masks tokens showing only first and last 4 characters', function () {
    expect(SummarizerToken::mask('sk-abcdef1234567890qwertyuiop'))->toBe('sk-a…uiop')
        ->and(SummarizerToken::mask('1234567890'))->toBe('1234…7890');
});

it('fully hides short tokens', function () {
    expect(SummarizerToken::mask('short'))->toBe('•••••')
        ->and(SummarizerToken::mask('12345678'))->toBe('••••••••');
});

it('never exposes the raw token through the model', function () {
    $token = SummarizerToken::create([
        'bot_id' => 'test_bot',
        'provider_key' => 'openai',
        'label' => 'L',
        'token' => 'sk-supersecret-value-0987654321',
        'created_by_tg_id' => 42,
        'created_at' => now(),
    ]);

    expect($token->refresh()->masked())->toBe('sk-s…4321')
        ->and($token->toArray())->not->toContain('sk-supersecret-value-0987654321');

    // encrypted at rest
    $raw = DB::table('summarizer_tokens')->where('id', $token->id)->value('token');

    expect($raw)->not->toBe('sk-supersecret-value-0987654321')
        ->and($raw)->not->toContain('supersecret');
});

it('round-trips the encrypted value', function () {
    $token = SummarizerToken::create([
        'bot_id' => 'test_bot',
        'provider_key' => 'deepseek',
        'label' => 'L2',
        'token' => 'ds-live-key-abcdefgh',
        'created_by_tg_id' => 42,
        'created_at' => now(),
    ]);

    $fresh = SummarizerToken::query()->findOrFail($token->id);

    expect($fresh->token)->toBe('ds-live-key-abcdefgh');
});
