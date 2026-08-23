<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Llm;

use RuntimeException;
use Throwable;

/** LLM call failure. Messages are safe to show in chat: never contain the key. */
class LlmCallException extends RuntimeException
{
    public static function fromThrowable(Throwable $e, string $context): self
    {
        return new self($context.': '.$e->getMessage(), 0, $e);
    }

    public static function httpError(int $status, string $bodySnippet): self
    {
        return new self(sprintf('LLM HTTP %d: %s', $status, mb_substr($bodySnippet, 0, 300)));
    }
}
