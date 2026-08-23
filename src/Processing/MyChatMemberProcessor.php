<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Processing;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\Processing\Processors\TgModuleProcessorContract;
use BAGArt\TelegramBot\Contracts\TgApi\TgApiTypeDTOContract;
use BAGArt\TelegramBot\Processing\BotProcessorContext;
use BAGArt\TelegramBot\Processing\ErrorHandling\ProcessorErrorContext;
use BAGArt\TelegramBot\TgApi\Types\DTO\ChatMemberUpdatedTypeDTO;
use Illuminate\Support\Facades\Log;
use BAGArt\TelegramBotSummarizer\Models\SummarizerChatAccess;
use BAGArt\TelegramBotSummarizer\ModuleFactory;
use Throwable;

/**
 * Tracks the bot's own chat membership: whoever performed the join/add
 * becomes the "inviter" and gains summarizer admin rights for the chat.
 */
class MyChatMemberProcessor implements TgModuleProcessorContract
{
    private function __construct()
    {
    }

    public static function moduleId(): string
    {
        return 'summarizer';
    }

    public static function build(BotProcessorContext $context): self
    {
        return new self();
    }

    public function support(
        TgApiTypeDTOContract $dto,
        TgBotConfig $botConfig,
        ?string $action = null,
    ): bool {
        return $dto instanceof ChatMemberUpdatedTypeDTO && $action === 'my_chat_member';
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
        assert($dto instanceof ChatMemberUpdatedTypeDTO);

        if (! ModuleFactory::inLaravel()) {
            return;
        }

        $botUserId = ModuleFactory::access()->botUserId($botConfig);
        $newStatus = $dto->newChatMember->status ?? '';

        if ($botUserId === null
            || $dto->newChatMember->user->id !== $botUserId
            || $dto->from->id === $botUserId
            || ! in_array($newStatus, ['creator', 'administrator', 'member', 'restricted'], true)
        ) {
            return;
        }

        try {
            SummarizerChatAccess::query()->updateOrCreate(
                [
                    'bot_id' => (string) $botConfig->botId,
                    'chat_id' => (int) $dto->chat->id,
                    'inviter_tg_id' => (int) $dto->from->id,
                ],
                [
                    'inviter_username' => $dto->from->username,
                    'invited_at' => $dto->date,
                ],
            );
        } catch (Throwable $e) {
            Log::warning('Summarizer: failed to record inviter (my_chat_member)', [
                'bot_id' => (string) $botConfig->botId,
                'chat_id' => (int) $dto->chat->id,
                'exception' => $e::class,
            ]);
        }
    }

    public function onException(ProcessorErrorContext $context): void
    {
    }
}
