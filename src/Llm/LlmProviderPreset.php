<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Llm;

/**
 * Built-in provider preset shown in the admin menu for one-click selection.
 */
final readonly class LlmProviderPreset
{
    public function __construct(
        public string $key,
        public string $name,
        public string $baseUrl,
        public string $model,
        public LlmApiStyle $apiStyle,
        public bool $needsToken = true,
    ) {
    }
}
