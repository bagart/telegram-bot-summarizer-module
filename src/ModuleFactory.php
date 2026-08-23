<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer;

use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\Contracts\Outbound\TgSenderContract;
use BAGArt\TelegramBotSummarizer\Access\SummarizerAccessService;
use BAGArt\TelegramBotSummarizer\Digest\DigestBuilder;
use BAGArt\TelegramBotSummarizer\Digest\DigestRunner;
use BAGArt\TelegramBotSummarizer\Llm\LlmClient;
use BAGArt\TelegramBotSummarizer\Llm\LlmConfigResolver;
use BAGArt\TelegramBotSummarizer\Llm\LlmProviderRegistry;
use BAGArt\TelegramBotSummarizer\Prompt\PromptRenderer;
use BAGArt\TelegramBotSummarizer\Prompt\PromptTemplateRegistry;
use BAGArt\TelegramBotSummarizer\Settings\SummarizerSettingsService;
use BAGArt\TelegramBotSummarizer\Telegram\SyncTgSender;
use BAGArt\TelegramBotSummarizer\Ui\AdminMenuRenderer;
use BAGArt\TelegramBotSummarizer\Ui\PendingInputService;

/**
 * Service-graph builder for the module. Module components are stateless, so
 * they are constructed per use instead of hidden global bindings; container-
 * managed contracts (sender, API client, enablement) come from app().
 */
final class ModuleFactory
{
    public static function inLaravel(): bool
    {
        return \function_exists('app') && app()->bound(TgSenderContract::class);
    }

    public static function settings(): SummarizerSettingsService
    {
        return app(SummarizerSettingsService::class);
    }

    public static function access(): SummarizerAccessService
    {
        return new SummarizerAccessService(app(TgBotApiDTOClientContract::class));
    }

    public static function menu(): AdminMenuRenderer
    {
        return new AdminMenuRenderer(self::providers(), new PromptTemplateRegistry());
    }

    public static function providers(): LlmProviderRegistry
    {
        return new LlmProviderRegistry();
    }

    public static function pending(): PendingInputService
    {
        return new PendingInputService((int) config('summarizer.pending_input_ttl_minutes', 15));
    }

    public static function promptRenderer(): PromptRenderer
    {
        return new PromptRenderer(new PromptTemplateRegistry());
    }

    public static function digestBuilder(): DigestBuilder
    {
        return new DigestBuilder(
            budgetChars: (int) config('summarizer.transcript_budget_chars', 120000),
            maxMessages: (int) config('summarizer.max_transcript_messages', 2000),
            maxCharsPerMessage: (int) config('summarizer.max_chars_per_message', 1000),
        );
    }

    public static function llmClient(): LlmClient
    {
        return new LlmClient((int) config('summarizer.llm_max_response_bytes', 2097152));
    }

    public static function llmConfigResolver(): LlmConfigResolver
    {
        return new LlmConfigResolver(
            registry: self::providers(),
            defaultTimeoutSeconds: (int) config('summarizer.llm_timeout_seconds', 90),
        );
    }

    public static function digestRunner(TgSenderContract $sender): DigestRunner
    {
        return new DigestRunner(
            settingsService: self::settings(),
            builder: self::digestBuilder(),
            renderer: self::promptRenderer(),
            client: self::llmClient(),
            configResolver: self::llmConfigResolver(),
            sender: $sender,
        );
    }

    public static function digestRunnerSync(): DigestRunner
    {
        return self::digestRunner(new SyncTgSender(app(TgBotApiDTOClientContract::class)));
    }
}
