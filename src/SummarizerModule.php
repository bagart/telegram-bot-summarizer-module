<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer;

use BAGArt\TelegramBot\Modules\TgModuleCapability;
use BAGArt\TelegramBot\Modules\TgModuleContract;
use BAGArt\TelegramBot\Modules\TgModuleDescriptor;
use BAGArt\TelegramBot\Modules\TgModuleRegistrar;
use BAGArt\TelegramBot\TgApi\Types\DTO\CallbackQueryTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\ChatMemberUpdatedTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;
use BAGArt\TelegramBotSummarizer\Processing\AdminMenuProcessor;
use BAGArt\TelegramBotSummarizer\Processing\CollectMessageProcessor;
use BAGArt\TelegramBotSummarizer\Processing\MyChatMemberProcessor;
use BAGArt\TelegramBotSummarizer\Processing\SummarizerCancelCommandProcessor;
use BAGArt\TelegramBotSummarizer\Processing\SummarizerCommandProcessor;
use BAGArt\TelegramBotSummarizer\Web\SummarizerUiHandler;
use BAGArt\TelegramBotSummarizer\Web\SummarizerWebUi;

/**
 * Chat summarizer module: collects group messages, produces scheduled LLM
 * digests (themes, positions, witty mini-summaries) and ships an in-chat
 * admin panel for interval/provider/token/template management.
 *
 * Disabled by default per chat — nothing is collected until a chat admin
 * enables it via /summarizer.
 */
class SummarizerModule implements TgModuleContract
{
    public static function descriptor(): TgModuleDescriptor
    {
        return new TgModuleDescriptor(
            id: 'summarizer',
            name: 'Chat Summarizer',
            version: '1.0.0',
            capabilities: [
                TgModuleCapability::Processor,
                TgModuleCapability::Command,
                TgModuleCapability::Ui,
            ],
            // The module (menu commands) is discoverable in every chat; actual
            // collection/digests stay off until the chat admin opts in via
            // /summarizer (per-chat 'enabled' flag in module_settings).
            defaultEnabled: true,
        );
    }

    public static function register(TgModuleRegistrar $registrar): void
    {
        $registrar->processor(
            MessageTypeDTO::class,
            CollectMessageProcessor::class,
        );

        $registrar->processor(
            ChatMemberUpdatedTypeDTO::class,
            MyChatMemberProcessor::class,
        );

        $registrar->processor(
            CallbackQueryTypeDTO::class,
            AdminMenuProcessor::class,
        );

        $registrar->command(
            SummarizerCommandProcessor::NAME,
            SummarizerCommandProcessor::class,
        );

        $registrar->command(
            SummarizerCancelCommandProcessor::NAME,
            SummarizerCancelCommandProcessor::class,
        );

        $registrar->webUi(SummarizerWebUi::class)->webApi(SummarizerUiHandler::class);
    }
}
