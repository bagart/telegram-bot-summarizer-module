<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Processing;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\Outbound\TgSenderContract;
use BAGArt\TelegramBot\Contracts\Processing\Processors\TgModuleProcessorContract;
use BAGArt\TelegramBot\Contracts\TgApi\TgApiTypeDTOContract;
use BAGArt\TelegramBot\Processing\BotProcessorContext;
use BAGArt\TelegramBot\Processing\ErrorHandling\ProcessorErrorContext;
use BAGArt\TelegramBot\TgApi\Methods\DTO\AnswerCallbackQueryMethodDTO;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\CallbackQueryTypeDTO;
use BAGArt\TelegramBotSummarizer\Llm\LlmProviderRegistry;
use BAGArt\TelegramBotSummarizer\Models\SummarizerToken;
use BAGArt\TelegramBotSummarizer\ModuleFactory;
use BAGArt\TelegramBotSummarizer\Prompt\PromptTemplateRegistry;
use BAGArt\TelegramBotSummarizer\Settings\SummarizerSettingsService;
use BAGArt\TelegramBotSummarizer\Ui\AdminMenuRenderer;
use BAGArt\TelegramBotSummarizer\Ui\CallbackRoute;
use BAGArt\TelegramBotSummarizer\Ui\PendingInputService;
use Throwable;

/**
 * Inline-keyboard router for the /summarizer admin menu. Every press is
 * re-authorized; menus are sent as fresh messages (the parsed CallbackQuery
 * DTO carries no usable originating-message id to edit).
 */
class AdminMenuProcessor implements TgModuleProcessorContract
{
    private function __construct(
        private readonly TgSenderContract $sender,
        private readonly SummarizerSettingsService $settings,
        private readonly AdminMenuRenderer $menu,
        private readonly PendingInputService $pending,
    ) {
    }

    public static function moduleId(): string
    {
        return 'summarizer';
    }

    public static function build(BotProcessorContext $context): self
    {
        return new self(
            sender: $context->tgSender,
            settings: ModuleFactory::settings(),
            menu: ModuleFactory::menu(),
            pending: ModuleFactory::pending(),
        );
    }

    public function support(
        TgApiTypeDTOContract $dto,
        TgBotConfig $botConfig,
        ?string $action = null,
    ): bool {
        return $dto instanceof CallbackQueryTypeDTO
            && $dto->data !== null
            && CallbackRoute::decode($dto->data) !== null;
    }

    public function isStrictOrdered(
        TgApiTypeDTOContract $dto,
        TgBotConfig $botConfig,
        ?string $action = null,
    ): bool {
        return false;
    }

    public function process(
        TgApiTypeDTOContract $dto,
        TgBotConfig $botConfig,
        ?string $action = null,
    ): void {
        assert($dto instanceof CallbackQueryTypeDTO);

        if (! ModuleFactory::inLaravel()) {
            return;
        }

        $route = CallbackRoute::decode($dto->data);
        $chatId = $route['chatId'] ?? 0;
        $verb = $route['verb'] ?? '';
        $arg = $route['arg'] ?? null;
        $botId = (string) $botConfig->botId;

        try {
            if (! ModuleFactory::access()->canManage($botConfig, $chatId, $dto->from)) {
                $this->answer(
                    $dto,
                    'Only chat admins who can delete others\' messages or the user who added the bot may open this panel.',
                    alert: true,
                );

                return;
            }

            $this->dispatchVerb($dto, $botConfig, $chatId, $verb, $arg);
        } catch (Throwable $e) {
            $this->answer($botConfig, $dto, 'Menu error: '.$e->getMessage(), alert: true);
        }
    }

    private function dispatchVerb(
        CallbackQueryTypeDTO $query,
        TgBotConfig $botConfig,
        int $chatId,
        string $verb,
        ?string $arg,
    ): void {
        switch ($verb) {
            case CallbackRoute::VERB_MENU:
                $this->renderPage($botConfig, $chatId, fn ($s, $t) => $this->menu->main($chatId, $s, $t));
                $this->answer($botConfig, $query);

                return;

            case CallbackRoute::VERB_PAGE_INTERVALS:
                $this->renderPage($botConfig, $chatId, fn ($s, $t) => $this->menu->intervals($chatId, $s));
                $this->answer($botConfig, $query);

                return;

            case CallbackRoute::VERB_PAGE_PROVIDERS:
                $this->renderPage($botConfig, $chatId, fn ($s, $t) => $this->menu->providers($chatId, $s));
                $this->answer($botConfig, $query);

                return;

            case CallbackRoute::VERB_PAGE_TOKENS:
                $this->renderPage($botConfig, $chatId, fn ($s, $t) => $this->menu->tokens($chatId, $s, $t));
                $this->answer($botConfig, $query);

                return;

            case CallbackRoute::VERB_PAGE_TEMPLATES:
                $this->renderPage($botConfig, $chatId, fn ($s, $t) => $this->menu->templates($chatId, $s));
                $this->answer($botConfig, $query);

                return;

            case CallbackRoute::VERB_ENABLE:
            case CallbackRoute::VERB_DISABLE:
                $enabled = $verb === CallbackRoute::VERB_ENABLE;
                $this->settings->patch((string) $botConfig->botId, $chatId, ['enabled' => $enabled]);
                $this->answer($botConfig, $query, $enabled ? 'Enabled' : 'Disabled');
                $this->renderPage($botConfig, $chatId, fn ($s, $t) => $this->menu->main($chatId, $s, $t));

                return;

            case CallbackRoute::VERB_SET_INTERVAL:
                $minutes = (int) $arg;

                if (! in_array($minutes, [30, 60, 120, 180, 360, 720, 1440], true)) {
                    $this->answer($botConfig, $query, 'Unknown interval', alert: true);

                    return;
                }

                $this->settings->patch((string) $botConfig->botId, $chatId, ['interval_minutes' => $minutes]);
                $this->answer($botConfig, $query, 'Interval updated');
                $this->renderPage($botConfig, $chatId, fn ($s, $t) => $this->menu->intervals($chatId, $s));

                return;

            case CallbackRoute::VERB_SET_PROVIDER:
                $this->selectProvider($query, $botConfig, $chatId, (string) $arg);

                return;

            case CallbackRoute::VERB_CUSTOM_PROVIDER:
                $this->startCustomProviderEditor($query, $botConfig, $chatId);

                return;

            case CallbackRoute::VERB_ADD_TOKEN:
                $this->startTokenInput($query, $botConfig, $chatId, (string) $arg);

                return;

            case CallbackRoute::VERB_SELECT_TOKEN:
                $this->selectToken($query, $botConfig, $chatId, (string) $arg);

                return;

            case CallbackRoute::VERB_DELETE_TOKEN:
                $this->deleteToken($query, $botConfig, $chatId, (string) $arg);

                return;

            case CallbackRoute::VERB_SET_TEMPLATE:
                $templates = new PromptTemplateRegistry();

                if (! $templates->has((string) $arg)) {
                    $this->answer($botConfig, $query, 'Unknown template', alert: true);

                    return;
                }

                $this->settings->patch((string) $botConfig->botId, $chatId, ['template_id' => (string) $arg, 'custom_template' => null]);
                $this->answer($botConfig, $query, 'Template updated');
                $this->renderPage($botConfig, $chatId, fn ($s, $t) => $this->menu->templates($chatId, $s));

                return;

            case CallbackRoute::VERB_CUSTOM_TEMPLATE:
                ModuleFactory::pending()->start(
                    (string) $botConfig->botId,
                    $chatId,
                    (int) $query->from->id,
                    PendingInputService::ACTION_TEMPLATE,
                );
                $this->answer($botConfig, $query, 'Waiting for template text');
                $this->sendText($botConfig, $chatId, implode("\n", [
                    "✍️ <b>Custom template</b>",
                    "Send the instruction block for the LLM as your next message.",
                    "Placeholders available: <code>{period}</code>, <code>{stats}</code>, <code>{language}</code>.",
                    "The safety preamble and transcript delimiters are always enforced and cannot be overridden.",
                    "Cancel: /summarizer_cancel",
                ]));

                return;

            case CallbackRoute::VERB_MIN_MESSAGES:
                ModuleFactory::pending()->start(
                    (string) $botConfig->botId,
                    $chatId,
                    (int) $query->from->id,
                    PendingInputService::ACTION_MIN_MESSAGES,
                );
                $this->answer($botConfig, $query, 'Waiting for a number');
                $this->sendText($botConfig, $chatId, "🔢 Send the minimum number of messages for a digest to trigger (1–5000).\nCancel: /summarizer_cancel");

                return;

            case CallbackRoute::VERB_RUN_NOW:
                $this->answer($botConfig, $query, 'Digest started…');
                $outcome = ModuleFactory::digestRunner($this->sender)->run($botConfig, $chatId);
                $this->sendText($botConfig, $chatId, $outcome->isSuccess()
                    ? sprintf('✅ Digest posted (%d messages analyzed).', $outcome->messageCount)
                    : sprintf('⚠️ Digest not produced: %s', $outcome->error ?? 'unknown reason'));

                return;

            case CallbackRoute::VERB_CLOSE:
                $this->answer($botConfig, $query, 'Closed');

                return;

            default:
                $this->answer($botConfig, $query, 'Unsupported action', alert: true);
        }
    }

    /**
     * @param  callable(SummarizerSettings, list<SummarizerToken>): array{text: string, keyboard: \BAGArt\TelegramBot\TgApi\Types\DTO\InlineKeyboardMarkupTypeDTO}  $pageBuilder
     */
    private function renderPage(TgBotConfig $botConfig, int $chatId, callable $pageBuilder): void
    {
        $botId = (string) $botConfig->botId;
        $settings = $this->settings->get($botId, $chatId);
        $tokens = $this->tokensOf($botId);

        $page = $pageBuilder($settings, $tokens);

        $this->sender->send($botConfig, new SendMessageMethodDTO(
            chatId: (string) $chatId,
            text: $page['text'],
            parseMode: \BAGArt\TelegramBot\TgApi\Methods\Enum\ParseModeEnum::HTML,
            replyMarkup: $page['keyboard'],
        ));
    }

    private function selectProvider(CallbackQueryTypeDTO $query, TgBotConfig $botConfig, int $chatId, string $key): void
    {
        $providers = new LlmProviderRegistry();
        $botId = (string) $botConfig->botId;

        if (! $providers->has($key)) {
            $this->answer($botConfig, $query, 'Unknown provider', alert: true);

            return;
        }

        // Keep the active token only when it belongs to the chosen provider.
        $patch = ['provider_key' => $key];
        $active = $this->activeToken($botId, $this->settings->get($botId, $chatId));

        if ($active !== null && $active->provider_key !== $key) {
            $patch['active_token_id'] = null;
        }

        $this->settings->patch($botId, $chatId, $patch);
        $this->answer($botConfig, $query, 'Provider selected');
        $this->renderPage($botConfig, $chatId, fn ($s, $t) => $this->menu->providers($chatId, $s));
    }

    private function startCustomProviderEditor(CallbackQueryTypeDTO $query, TgBotConfig $botConfig, int $chatId): void
    {
        ModuleFactory::pending()->start(
            (string) $botConfig->botId,
            $chatId,
            (int) $query->from->id,
            PendingInputService::ACTION_PROVIDER_JSON,
        );

        $templateJson = ModuleFactory::providers()->customTemplateJson();

        $this->answer($botConfig, $query, 'Waiting for provider JSON');
        $this->sendText($botConfig, $chatId, implode("\n", [
            "🛠 <b>Custom provider</b>",
            "Edit this JSON and send it back as your next message:",
            "<pre>".htmlspecialchars($templateJson).'</pre>',
            "http base_url is allowed only for local addresses. Cancel: /summarizer_cancel",
        ]));
    }

    private function startTokenInput(CallbackQueryTypeDTO $query, TgBotConfig $botConfig, int $chatId, string $providerKey): void
    {
        $providers = new LlmProviderRegistry();

        if (! $providers->has($providerKey)) {
            $this->answer($botConfig, $query, 'Unknown provider', alert: true);

            return;
        }

        $preset = $providers->get($providerKey);

        ModuleFactory::pending()->start(
            (string) $botConfig->botId,
            $chatId,
            (int) $query->from->id,
            PendingInputService::ACTION_TOKEN,
            ['provider_key' => $providerKey],
        );

        $this->answer($botConfig, $query, 'Waiting for the key');
        $this->sendText($botConfig, $chatId, implode("\n", [
            sprintf('🔑 Send the %s API key as your next message.', $preset?->name ?? $providerKey),
            'It will be stored encrypted and deleted from the chat immediately.',
            'Only the first 4 and last 4 characters are ever displayed afterwards.',
            'Cancel: /summarizer_cancel',
        ]));
    }

    private function selectToken(CallbackQueryTypeDTO $query, TgBotConfig $botConfig, int $chatId, string $tokenId): void
    {
        $botId = (string) $botConfig->botId;
        $token = $this->findToken($botId, $tokenId);

        if ($token === null) {
            $this->answer($botConfig, $query, 'Token not found', alert: true);

            return;
        }

        $this->settings->patch($botId, $chatId, [
            'active_token_id' => $token->id,
            'provider_key' => $token->provider_key,
        ]);

        $this->answer($botConfig, $query, 'Active token set');
        $this->renderPage($botConfig, $chatId, fn ($s, $t) => $this->menu->tokens($chatId, $s, $t));
    }

    private function deleteToken(CallbackQueryTypeDTO $query, TgBotConfig $botConfig, int $chatId, string $tokenId): void
    {
        $botId = (string) $botConfig->botId;
        $access = ModuleFactory::access();
        $token = $this->findToken($botId, $tokenId);

        if ($token === null) {
            $this->answer($botConfig, $query, 'Token not found', alert: true);

            return;
        }

        $isOwner = (int) $token->created_by_tg_id === (int) $query->from->id;

        if (! $isOwner && ! $access->isSuperadmin($query->from->id)) {
            $this->answer($botConfig, $query, 'You can delete only tokens you added (superadmins excepted).', alert: true);

            return;
        }

        $wasActive = $this->settings->get($botId, $chatId)->activeTokenId === $token->id;
        $token->delete();

        if ($wasActive) {
            $this->settings->patch($botId, $chatId, ['active_token_id' => null]);
        }

        $this->answer($botConfig, $query, 'Token deleted');
        $this->renderPage($botConfig, $chatId, fn ($s, $t) => $this->menu->tokens($chatId, $s, $t));
    }

    /**
     * @return list<SummarizerToken>
     */
    private function tokensOf(string $botId): array
    {
        return SummarizerToken::query()->where('bot_id', $botId)->orderByDesc('created_at')->get()->all();
    }

    private function findToken(string $botId, string $tokenId): ?SummarizerToken
    {
        return SummarizerToken::query()->where('bot_id', $botId)->whereKey($tokenId)->first();
    }

    private function activeToken(string $botId, \BAGArt\TelegramBotSummarizer\Settings\SummarizerSettings $settings): ?SummarizerToken
    {
        if ($settings->activeTokenId === null) {
            return null;
        }

        return $this->findToken($botId, $settings->activeTokenId);
    }

    private function answer(TgBotConfig $botConfig, CallbackQueryTypeDTO $query, ?string $text = null, bool $alert = false): void
    {
        $this->sender->send($botConfig, new AnswerCallbackQueryMethodDTO(
            callbackQueryId: $query->id,
            text: $text,
            showAlert: $alert ? true : null,
        ));
    }

    private function sendText(TgBotConfig $botConfig, int $chatId, string $text): void
    {
        $this->sender->send($botConfig, new SendMessageMethodDTO(
            chatId: (string) $chatId,
            text: $text,
            parseMode: \BAGArt\TelegramBot\TgApi\Methods\Enum\ParseModeEnum::HTML,
        ));
    }

    public function onException(ProcessorErrorContext $context): void
    {
    }
}
