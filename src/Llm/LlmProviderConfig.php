<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Llm;

/**
 * Fully resolved runtime configuration for one LLM call. The token value is
 * attached only here and never logged or echoed.
 */
final readonly class LlmProviderConfig
{
    /**
     * @param  array<string, string>  $extraHeaders
     */
    public function __construct(
        public string $providerKey,
        public string $name,
        public string $baseUrl,
        public string $model,
        public LlmApiStyle $apiStyle,
        public ?string $token,
        public float $temperature,
        public int $maxTokens,
        public int $timeoutSeconds,
        public array $extraHeaders = [],
    ) {
    }
}
