<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Ui;

use BAGArt\TelegramBot\TgApi\Types\DTO\InlineKeyboardButtonTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\InlineKeyboardMarkupTypeDTO;
use BAGArt\TelegramBotSummarizer\Llm\LlmProviderRegistry;
use BAGArt\TelegramBotSummarizer\Models\SummarizerToken;
use BAGArt\TelegramBotSummarizer\Prompt\PromptTemplateRegistry;
use BAGArt\TelegramBotSummarizer\Settings\SummarizerSettings;

/**
 * Builds admin-menu texts + inline keyboards. Pure formatting — no I/O.
 */
class AdminMenuRenderer
{
    public function __construct(
        private readonly LlmProviderRegistry $providers,
        private readonly PromptTemplateRegistry $templates,
    ) {
    }

    /**
     * @param  list<SummarizerToken>  $tokens
     * @return array{text: string, keyboard: InlineKeyboardMarkupTypeDTO}
     */
    public function main(int $chatId, SummarizerSettings $settings, array $tokens): array
    {
        $tokenLabel = $this->activeTokenLabel($settings, $tokens);
        $providerName = $this->providerName($settings->providerKey);

        $text = "⚙️ <b>Chat Summarizer</b>\n"
            ."Status: ".($settings->enabled ? '✅ ON' : '⛔️ OFF')."\n"
            ."Interval: every {$this->formatInterval($settings->intervalMinutes)}\n"
            ."Provider: {$providerName}\n"
            ."Active token: {$tokenLabel}\n"
            ."Template: ".($settings->customTemplate !== null
                ? '✍️ custom'
                : ($this->templates->get($settings->templateId)?->name ?? $settings->templateId))."\n"
            ."Min messages to trigger: {$settings->minMessages}\n\n"
            ."Bot must be able to read group messages (disable privacy mode or make it an admin).";

        $rows = [
            [$this->button($settings->enabled ? '⛔️ Turn OFF' : '✅ Turn ON', $chatId, $settings->enabled ? CallbackRoute::VERB_DISABLE : CallbackRoute::VERB_ENABLE)],
            [$this->button('⏱ Interval', $chatId, CallbackRoute::VERB_PAGE_INTERVALS)],
            [$this->button('🧠 LLM provider', $chatId, CallbackRoute::VERB_PAGE_PROVIDERS)],
            [$this->button('🔑 Tokens', $chatId, CallbackRoute::VERB_PAGE_TOKENS)],
            [$this->button('📝 Template', $chatId, CallbackRoute::VERB_PAGE_TEMPLATES)],
            [
                $this->button('🔢 Min messages', $chatId, CallbackRoute::VERB_MIN_MESSAGES),
                $this->button('▶️ Run now', $chatId, CallbackRoute::VERB_RUN_NOW),
            ],
            [$this->button('✖️ Close', $chatId, CallbackRoute::VERB_CLOSE)],
        ];

        return ['text' => $text, 'keyboard' => new InlineKeyboardMarkupTypeDTO(inlineKeyboard: $rows)];
    }

    /**
     * @param  list<SummarizerToken>  $tokens
     * @return array{text: string, keyboard: InlineKeyboardMarkupTypeDTO}
     */
    public function intervals(int $chatId, SummarizerSettings $settings): array
    {
        $rows = [];
        $row = [];

        foreach (SummarizerSettings::INTERVAL_CHOICES as $minutes) {
            $label = $this->formatInterval($minutes).($settings->intervalMinutes === $minutes ? ' ●' : '');
            $row[] = $this->button($label, $chatId, CallbackRoute::VERB_SET_INTERVAL, (string) $minutes);

            if (count($row) === 4) {
                $rows[] = $row;
                $row = [];
            }
        }

        if ($row !== []) {
            $rows[] = $row;
        }

        $rows[] = [$this->button('⬅️ Back', $chatId, CallbackRoute::VERB_MENU)];

        return [
            'text' => '⏱ <b>Digest interval</b>'."\nHow often a digest is produced for this chat.",
            'keyboard' => new InlineKeyboardMarkupTypeDTO(inlineKeyboard: $rows),
        ];
    }

    /**
     * @return array{text: string, keyboard: InlineKeyboardMarkupTypeDTO}
     */
    public function providers(int $chatId, SummarizerSettings $settings): array
    {
        $rows = [];

        foreach ($this->providers->all() as $preset) {
            $marker = $settings->providerKey === $preset->key ? ' ●' : '';
            $needsKey = $preset->needsToken ? '' : ' (no key)';
            $rows[] = [$this->button("{$preset->name}{$needsKey}{$marker}", $chatId, CallbackRoute::VERB_SET_PROVIDER, $preset->key)];
        }

        $customMarker = $settings->providerKey === LlmProviderRegistry::CUSTOM_KEY ? ' ●' : '';
        $rows[] = [$this->button("🛠 Custom (JSON editor){$customMarker}", $chatId, CallbackRoute::VERB_CUSTOM_PROVIDER)];

        return [
            'text' => "🧠 <b>LLM provider</b>\nCurrent: ".$this->providerName($settings->providerKey)
                ."\nPick a preset, or edit a pre-generated JSON config for anything else.",
            'keyboard' => new InlineKeyboardMarkupTypeDTO(inlineKeyboard: $rows),
        ];
    }

    /**
     * @param  list<SummarizerToken>  $tokens
     * @return array{text: string, keyboard: InlineKeyboardMarkupTypeDTO}
     */
    public function tokens(int $chatId, SummarizerSettings $settings, array $tokens): array
    {
        $lines = ["🔑 <b>Tokens</b>", "Add keys once; any chat admin can select the active one."];
        $rows = [];

        foreach ($tokens as $token) {
            $active = $settings->activeTokenId === $token->id;
            $label = sprintf('%s%s', $active ? '● ' : '', $token->masked());
            $rows[] = [
                $this->button($active ? $label.' (active)' : $label, $chatId, CallbackRoute::VERB_SELECT_TOKEN, $token->id),
                $this->button('🗑', $chatId, CallbackRoute::VERB_DELETE_TOKEN, $token->id),
            ];
            $lines[] = sprintf('%s · %s · added by %s', $token->masked(), $this->providerName($token->provider_key), $token->created_by_username ?: ('id'.$token->created_by_tg_id));
        }

        if ($tokens === []) {
            $lines[] = "\nNo tokens yet.";
        }

        foreach (array_keys($this->providers->all()) as $presetKey) {
            $preset = $this->providers->get($presetKey);
            $rows[] = [$this->button('➕ Add '.$preset->name.' key', $chatId, CallbackRoute::VERB_ADD_TOKEN, $presetKey)];
        }
        $rows[] = [$this->button('➕ Add custom-provider key', $chatId, CallbackRoute::VERB_ADD_TOKEN, LlmProviderRegistry::CUSTOM_KEY)];

        $rows[] = [$this->button('⬅️ Back', $chatId, CallbackRoute::VERB_MENU)];

        return ['text' => implode("\n", $lines), 'keyboard' => new InlineKeyboardMarkupTypeDTO(inlineKeyboard: $rows)];
    }

    /**
     * @return array{text: string, keyboard: InlineKeyboardMarkupTypeDTO}
     */
    public function templates(int $chatId, SummarizerSettings $settings): array
    {
        $rows = [];

        foreach ($this->templates->all() as $template) {
            $marker = $settings->customTemplate === null && $settings->templateId === $template->id ? ' ●' : '';
            $rows[] = [$this->button($template->name.$marker, $chatId, CallbackRoute::VERB_SET_TEMPLATE, $template->id)];
        }

        $customMarker = $settings->customTemplate !== null ? ' ●' : '';
        $rows[] = [$this->button("✍️ Write custom…{$customMarker}", $chatId, CallbackRoute::VERB_CUSTOM_TEMPLATE)];
        $rows[] = [$this->button('⬅️ Back', $chatId, CallbackRoute::VERB_MENU)];

        $placeholderDoc = "Placeholders: {period}, {stats}, {language}";

        return [
            'text' => "📝 <b>Summary template</b>\nThe instruction block sent to the LLM above the safety preamble.\n{$placeholderDoc}",
            'keyboard' => new InlineKeyboardMarkupTypeDTO(inlineKeyboard: $rows),
        ];
    }

    private function button(string $label, int $chatId, string $verb, ?string $arg = null): InlineKeyboardButtonTypeDTO
    {
        return new InlineKeyboardButtonTypeDTO(text: $label, callbackData: CallbackRoute::encode($chatId, $verb, $arg));
    }

    /**
     * @param  list<SummarizerToken>  $tokens
     */
    private function activeTokenLabel(SummarizerSettings $settings, array $tokens): string
    {
        if ($settings->activeTokenId === null) {
            return '⚠️ none';
        }

        foreach ($tokens as $token) {
            if ($token->id === $settings->activeTokenId) {
                return $token->masked().' ('.$this->providerName($token->provider_key).')';
            }
        }

        return '⚠️ missing';
    }

    private function providerName(string $key): string
    {
        if ($key === LlmProviderRegistry::CUSTOM_KEY) {
            return 'Custom';
        }

        return $this->providers->get($key)?->name ?? $key;
    }

    private function formatInterval(int $minutes): string
    {
        return match (true) {
            $minutes % 1440 === 0 => intdiv($minutes, 1440).'d',
            $minutes % 60 === 0 => intdiv($minutes, 60).'h',
            default => $minutes.'m',
        };
    }
}
