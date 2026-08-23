<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Llm;

enum LlmApiStyle: string
{
    case Openai = 'openai';

    case Anthropic = 'anthropic';
}
