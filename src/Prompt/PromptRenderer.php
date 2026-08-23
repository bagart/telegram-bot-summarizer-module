<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Prompt;

use BAGArt\TelegramBotSummarizer\Settings\SummarizerSettings;

/**
 * Renders the final system prompt + user content pair for one digest call.
 * Custom templates replace only the instruction block; the safety preamble
 * and transcript delimiters are always enforced by this class.
 */
class PromptRenderer
{
    private const TRANSCRIPT_OPEN = '<<<TRANSCRIPT>>>';

    private const TRANSCRIPT_CLOSE = '<<<END TRANSCRIPT>>>';

    public function __construct(
        private readonly PromptTemplateRegistry $templates,
    ) {
    }

    /**
     * @return array{system: string, user: string}
     */
    public function render(
        SummarizerSettings $settings,
        string $period,
        string $stats,
        string $languageHint,
        string $transcript,
    ): array {
        $instruction = $this->instructionFor($settings);
        $placeholders = [
            '{period}' => $period,
            '{stats}' => $stats,
            '{language}' => $languageHint,
        ];

        $system = strtr(PromptTemplateRegistry::SAFETY_PREAMBLE, ['{language}' => $languageHint])
            ."\n\n"
            .strtr($instruction, $placeholders);

        $user = "Produce the digest for the following chat transcript.\n"
            .self::TRANSCRIPT_OPEN."\n"
            .$transcript."\n"
            .self::TRANSCRIPT_CLOSE;

        return ['system' => $system, 'user' => $user];
    }

    private function instructionFor(SummarizerSettings $settings): string
    {
        if ($settings->customTemplate !== null) {
            return $settings->customTemplate;
        }

        return ($this->templates->get($settings->templateId) ?? $this->templates->all()[$this->defaultId()])->instruction;
    }

    private function defaultId(): string
    {
        return 'witty';
    }
}
