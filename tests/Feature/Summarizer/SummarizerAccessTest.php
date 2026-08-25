<?php

declare(strict_types=1);

require_once __DIR__.'/helpers.php';

use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBotManagement\Models\TgBot;
use BAGArt\TelegramBotSummarizer\Access\SummarizerAccessService;
use BAGArt\TelegramBotSummarizer\Models\SummarizerChatAccess;

beforeEach(function () {
    config('telegram.modules');
    config(['summarizer.superadmins' => []]);

    TgBot::create(['bot_id' => 'test_bot', 'token' => '123:test']);
});

it('grants superadmins without touching Telegram API', function () {
    config(['summarizer.superadmins' => ['777']]);
    app()->instance(TgBotApiDTOClientContract::class, smFakeApiClient(throw: true));

    $access = new SummarizerAccessService(app(TgBotApiDTOClientContract::class));

    expect($access->canManage(smBotConfig(), -100100, smUser(777)))->toBeTrue()
        ->and($access->canManage(smBotConfig(), -100100, smUser(778)))->toBeFalse();
});

it('grants the inviter who added the bot to the chat', function () {
    app()->instance(TgBotApiDTOClientContract::class, smFakeApiClient(throw: true));

    SummarizerChatAccess::create([
        'bot_id' => 'test_bot',
        'chat_id' => -100100,
        'inviter_tg_id' => 42,
        'invited_at' => time(),
    ]);

    $access = new SummarizerAccessService(app(TgBotApiDTOClientContract::class));

    expect($access->canManage(smBotConfig(), -100100, smUser(42)))->toBeTrue()
        ->and($access->canManage(smBotConfig(), -100100, smUser(43)))->toBeFalse();
});

it('grants telegram admins holding the delete-messages right', function () {
    app()->instance(TgBotApiDTOClientContract::class, smFakeApiClient(
        result: [smAdminMember(42, canDelete: true)],
    ));

    $access = new SummarizerAccessService(app(TgBotApiDTOClientContract::class));

    expect($access->canManage(smBotConfig(), -100100, smUser(42)))->toBeTrue();
});

it('denies telegram admins without the delete-messages right', function () {
    app()->instance(TgBotApiDTOClientContract::class, smFakeApiClient(
        result: [smAdminMember(42, canDelete: false)],
    ));

    $access = new SummarizerAccessService(app(TgBotApiDTOClientContract::class));

    expect($access->canManage(smBotConfig(), -100100, smUser(42)))->toBeFalse();
});

it('grants chat owners regardless of admin rights', function () {
    app()->instance(TgBotApiDTOClientContract::class, smFakeApiClient(
        result: [smOwnerMember(42)],
    ));

    $access = new SummarizerAccessService(app(TgBotApiDTOClientContract::class));

    expect($access->hasTelegramDeleteRights(smBotConfig(), -100100, 42))->toBeTrue();
});

it('fails closed when the Telegram API is unreachable', function () {
    app()->instance(TgBotApiDTOClientContract::class, smFakeApiClient(throw: true));

    $access = new SummarizerAccessService(app(TgBotApiDTOClientContract::class));

    expect($access->canManage(smBotConfig(), -100100, smUser(42)))->toBeFalse();
});

it('resolves the bot telegram id through getMe and caches it', function () {
    $me = smUser(999000111, username: 'digest_bot');
    app()->instance(TgBotApiDTOClientContract::class, smFakeApiClient(result: $me));

    $access = new SummarizerAccessService(app(TgBotApiDTOClientContract::class));

    expect($access->botUserId(smBotConfig()))->toBe('999000111')
        ->and(Cache::get('summarizer:bot-user:'.sha1(smBotConfig()->token)))->toBe('999000111');
});
