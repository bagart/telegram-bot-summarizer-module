<?php

declare(strict_types=1);

require_once __DIR__.'/helpers.php';

use BAGArt\TelegramBotManagement\Models\TgBot;
use BAGArt\TelegramBotSummarizer\Settings\SummarizerSettingsService;

beforeEach(function () {
    config('telegram.modules'); // force the module scan (require_once of sources)

    TgBot::create(['bot_id' => 'bot_x', 'token' => 'a:token']);
    TgBot::create(['bot_id' => 'bot_y', 'token' => 'b:token']);
});

it('applies defaults when no enablement rows exist', function () {
    $settings = app(SummarizerSettingsService::class)->get('bot_x', 100);

    expect($settings->enabled)->toBeFalse()
        ->and($settings->intervalMinutes)->toBe(360)
        ->and($settings->providerKey)->toBe('openai')
        ->and($settings->activeTokenId)->toBeNull()
        ->and($settings->templateId)->toBe('witty')
        ->and($settings->customTemplate)->toBeNull()
        ->and($settings->minMessages)->toBe(10)
        ->and($settings->customProvider)->toBeNull()
        // module discovery is always on; the per-chat flag is what stays off
        ->and(app(SummarizerSettingsService::class)->isEnabled('bot_x', 100))->toBeTrue();
});

it('persists chat-level patches and reflects them back', function () {
    $service = app(SummarizerSettingsService::class);

    $service->patch('bot_x', 100, [
        'interval_minutes' => 60,
        'min_messages' => 25,
        'custom_template' => 'Custom instructions {period}',
    ]);

    $settings = $service->get('bot_x', 100);

    expect($settings->intervalMinutes)->toBe(60)
        ->and($settings->minMessages)->toBe(25)
        ->and($settings->customTemplate)->toBe('Custom instructions {period}');
});

it('toggles module enablement together with settings', function () {
    $service = app(SummarizerSettingsService::class);
    $botId = 'bot_x';
    $chatId = 100;

    // discovered by default (no explicit row yet)
    expect($service->isEnabled($botId, $chatId))->toBeTrue();

    $service->patch($botId, $chatId, ['enabled' => true]);
    expect($service->isEnabled($botId, $chatId))->toBeTrue();

    $service->patch($botId, $chatId, ['enabled' => false]);
    expect($service->isEnabled($botId, $chatId))->toBeFalse();
});

it('keeps chat scopes isolated', function () {
    $service = app(SummarizerSettingsService::class);

    $service->patch('bot_x', 100, ['interval_minutes' => 30]);

    expect($service->get('bot_x', 200)->intervalMinutes)->toBe(360)
        ->and($service->get('bot_y', 100)->intervalMinutes)->toBe(360);
});
