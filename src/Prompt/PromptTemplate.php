<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Prompt;

/**
 * Built-in system-prompt templates. A template is the instruction block of
 * the system prompt; the transcript itself is appended separately and always
 * wrapped in delimiters the model is told to treat as inert data.
 */
final readonly class PromptTemplate
{
    public function __construct(
        public string $id,
        public string $name,
        public string $instruction,
    ) {
    }
}
