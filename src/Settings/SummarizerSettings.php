<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Settings;

/**
 * Effective per-chat summarizer settings resolved from
 * tg_module_enablements.module_settings with platform defaults applied.
 */
final readonly class SummarizerSettings
{
    public const DEFAULT_INTERVAL_MINUTES = 360;

    public const DEFAULT_MIN_MESSAGES = 10;

    public const INTERVAL_CHOICES = [30, 60, 120, 180, 360, 720, 1440];

    /**
     * @param  array<string, mixed>|null  $customProvider  validated custom provider config
     */
    public function __construct(
        public bool $enabled,
        public int $intervalMinutes,
        public string $providerKey,
        public ?string $activeTokenId,
        public string $templateId,
        public ?string $customTemplate,
        public int $minMessages,
        public ?array $customProvider,
    ) {
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            enabled: (bool) ($raw['enabled'] ?? false),
            intervalMinutes: self::clampInterval((int) ($raw['interval_minutes'] ?? self::DEFAULT_INTERVAL_MINUTES)),
            providerKey: (string) ($raw['provider_key'] ?? 'openai'),
            activeTokenId: isset($raw['active_token_id']) ? (string) $raw['active_token_id'] : null,
            templateId: (string) ($raw['template_id'] ?? 'witty'),
            customTemplate: isset($raw['custom_template']) && $raw['custom_template'] !== '' ? (string) $raw['custom_template'] : null,
            minMessages: max(1, min(5000, (int) ($raw['min_messages'] ?? self::DEFAULT_MIN_MESSAGES))),
            customProvider: is_array($raw['custom_provider'] ?? null) ? $raw['custom_provider'] : null,
        );
    }

    private static function clampInterval(int $minutes): int
    {
        return max(15, min(10080, $minutes));
    }
}
