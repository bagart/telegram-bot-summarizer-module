<?php

declare(strict_types=1);

require_once __DIR__.'/helpers.php';

use BAGArt\TelegramBotManagement\Models\TgBot;
use Illuminate\Support\Facades\Storage;
use BAGArt\TelegramBotSummarizer\Digest\DigestBuilder;
use BAGArt\TelegramBotSummarizer\Models\SummarizerMessage;

beforeEach(function () {
    config('telegram.modules');
    Storage::fake('local');

    TgBot::create(['bot_id' => 'test_bot', 'token' => '123:test']);
});

function smBuilder(int $budget = 120000): DigestBuilder
{
    return new DigestBuilder(
        budgetChars: $budget,
        maxMessages: 2000,
        maxCharsPerMessage: 1000,
    );
}

it('returns null when no messages were collected for the period', function () {
    expect(smBuilder()->build('test_bot', -100100, time() - 600, time()))->toBeNull();
});

it('builds a transcript with authors, timestamps and persists the file', function () {
    SummarizerMessage::factory()->inChat('test_bot', -100100)->create([
        'message_id' => 1,
        'user_tg_id' => 11,
        'username' => 'alice',
        'display_name' => 'Alice',
        'text' => 'hello world',
        'sent_at' => time() - 120,
    ]);

    SummarizerMessage::factory()->inChat('test_bot', -100100)->create([
        'message_id' => 2,
        'user_tg_id' => 22,
        'username' => null,
        'display_name' => 'Bob',
        'text' => 'hi alice',
        'sent_at' => time() - 60,
    ]);

    $digest = smBuilder()->build('test_bot', -100100, time() - 600, time());

    expect($digest)->not->toBeNull()
        ->and($digest->transcript)->toContain('Alice (@alice): hello world')
        ->and($digest->transcript)->toMatch('/\[\d{2}\.\d{2} \d{2}:\d{2}\] Alice \(@alice\): hello world/')
        ->and($digest->transcript)->toContain('Bob: hi alice')
        ->and($digest->messageCount)->toBe(2)
        ->and($digest->participantCount)->toBe(2)
        ->and($digest->truncated)->toBeFalse()
        ->and(Storage::disk('local')->exists($digest->filePath))->toBeTrue()
        ->and(Storage::disk('local')->get($digest->filePath))->toContain('hello world');
});

it('marks media-only messages and counts anonymous senders', function () {
    SummarizerMessage::factory()->inChat('test_bot', -100100)->create([
        'message_id' => 5,
        'user_tg_id' => null,
        'username' => null,
        'display_name' => 'Channel Post',
        'text' => null,
        'sent_at' => time() - 30,
    ]);

    $digest = smBuilder()->build('test_bot', -100100, time() - 600, time());

    expect($digest->transcript)->toContain('[media]')
        ->and($digest->participantCount)->toBe(1);
});

it('truncates oversized transcripts with head and tail preserved', function () {
    foreach (range(1, 40) as $i) {
        SummarizerMessage::factory()->inChat('test_bot', -100100)->create([
            'message_id' => $i,
            'text' => 'msg-'.$i.' '.str_repeat('x', 300),
            'sent_at' => time() - (41 - $i) * 10,
        ]);
    }

    $digest = smBuilder(budget: 2000)->build('test_bot', -100100, time() - 600, time());

    expect($digest->truncated)->toBeTrue()
        ->and($digest->transcript)->toContain('middle of transcript truncated')
        ->and(mb_strlen($digest->transcript))->toBeLessThanOrEqual(2100)
        ->and($digest->transcript)->toContain('msg-1 ')      // head kept
        ->and($digest->transcript)->toContain('msg-40 ');   // tail kept
});

it('caps per-message length', function () {
    SummarizerMessage::factory()->inChat('test_bot', -100100)->create([
        'message_id' => 9,
        'text' => str_repeat('a', 5000),
        'sent_at' => time() - 30,
    ]);

    $builder = new DigestBuilder(budgetChars: 100000, maxMessages: 100, maxCharsPerMessage: 200);
    $digest = $builder->build('test_bot', -100100, time() - 600, time());

    expect($digest->transcript)->toContain('[cut]');
});
