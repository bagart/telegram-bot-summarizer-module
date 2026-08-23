<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Processing;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\Outbound\TgSenderContract;
use BAGArt\TelegramBot\Contracts\Processing\Processors\TgModuleProcessorContract;
use BAGArt\TelegramBot\Contracts\TgApi\TgApiTypeDTOContract;
use BAGArt\TelegramBot\Modules\TgCommandRegistry;
use BAGArt\TelegramBot\Processing\BotProcessorContext;
use BAGArt\TelegramBot\Processing\ErrorHandling\ProcessorErrorContext;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;
use BAGArt\TelegramBotSummarizer\ModuleFactory;

/**
 * "/summarizer_cancel" — aborts an in-flight input flow (token paste,
 * template editor, provider JSON editor).
 */
class SummarizerCancelCommandProcessor implements TgModuleProcessorContract
{
    public const NAME = 'summarizer_cancel';

    private function __construct(
        private readonly TgSenderContract $sender,
    ) {
    }

    public static function moduleId(): string
    {
        return 'summarizer';
    }

    public static function build(BotProcessorContext $context): self
    {
        return new self(sender: $context->tgSender);
    }

    public function support(
        TgApiTypeDTOContract $dto,
        TgBotConfig $botConfig,
        ?string $action = null,
    ): bool {
        return $dto instanceof MessageTypeDTO
            && $dto->text !== null
            && TgCommandRegistry::parseCommandName($dto->text) === self::NAME;
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
        assert($dto instanceof MessageTypeDTO);

        if (! ModuleFactory::inLaravel() || $dto->from === null) {
            return;
        }

        $chatId = (int) $dto->chat->id;
        $cancelled = ModuleFactory::pending()->cancel(
            (string) $botConfig->botId,
            $chatId,
            (int) $dto->from->id,
        );

        $this->sender->send($botConfig, new SendMessageMethodDTO(
            chatId: (string) $chatId,
            text: $cancelled > 0
                ? '↩️ Input cancelled.'
                : 'Nothing to cancel.',
        ));
    }

    public function onException(ProcessorErrorContext $context): void
    {
    }
}
