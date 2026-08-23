<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Llm;

use RuntimeException;

/** Transient LLM failure worth one retry (rate limits). */
class LlmRetryableException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $retryAfterSeconds = 0,
    ) {
        parent::__construct($message);
    }
}
