<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\Contracts\Outbound\TgSenderContract;
use BAGArt\TelegramBot\Contracts\TgApi\TgApiMethodDTOContract;
use BAGArt\TelegramBot\Http\Pure\TgApiResponse;
use BAGArt\TelegramBot\TgApi\Types\DTO\ChatMemberAdministratorTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\ChatMemberOwnerTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\Enum\ChatPropTypeEnum;
use BAGArt\TelegramBot\TgApi\Types\DTO\ChatTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;

/*
 * Shared fixtures for Summarizer module tests.
 */

function smBotConfig(): TgBotConfig
{
    return new TgBotConfig(token: '123:test', botId: 'test_bot');
}

function smUser(int $id, ?string $username = 'tester'): UserTypeDTO
{
    return new UserTypeDTO(id: (string) $id, isBot: false, firstName: 'Tester', username: $username);
}

function smGroupMessage(int $chatId, int $userId, string $text, int $messageId = 10): MessageTypeDTO
{
    return new MessageTypeDTO(
        messageId: $messageId,
        date: time(),
        chat: new ChatTypeDTO(id: (string) $chatId, type: ChatPropTypeEnum::SUPERGROUP),
        from: smUser($userId),
        text: $text,
    );
}

/** Sender spy recording every pushed method DTO. */
function smSenderSpy(): TgSenderContract
{
    return new class () implements TgSenderContract {
        /** @var list<TgApiMethodDTOContract> */
        public array $sent = [];

        public function send(TgBotConfig $botConfig, TgApiMethodDTOContract $dto): void
        {
            $this->sent[] = $dto;
        }
    };
}

/**
 * API-client stub returning a fixed payload for every request; throws when
 * $throw is set (used to prove no network path is taken / fail-closed).
 */
function smFakeApiClient(mixed $result = null, bool $ok = true, bool $throw = false): TgBotApiDTOClientContract
{
    return new class ($result, $ok, $throw) implements TgBotApiDTOClientContract {
        public function __construct(
            private readonly mixed $result,
            private readonly bool $ok,
            private readonly bool $throw,
        ) {
        }

        public function request(
            BAGArt\TelegramBot\Configs\TgBotConfig $botConfig,
            TgApiMethodDTOContract $dto,
            ?int $timeout = null,
        ): TgApiResponse {
            if ($this->throw) {
                throw new RuntimeException('network disabled in test');
            }

            return new TgApiResponse(ok: $this->ok, possibleResultTypes: [], result: $this->result);
        }

        public function requestAsync(
            BAGArt\TelegramBot\Configs\TgBotConfig $botConfig,
            TgApiMethodDTOContract $dto,
            ?int $timeout = null,
        ): BAGArt\ASKClient\Contracts\Pipeline\ASKFutureContract {
            throw new RuntimeException('not used in tests');
        }

        public function tickable(): array
        {
            return [];
        }
    };
}

function smAdminMember(int $tgId, bool $canDelete): ChatMemberAdministratorTypeDTO
{
    return new ChatMemberAdministratorTypeDTO(
        user: smUser($tgId),
        canBeEdited: false,
        isAnonymous: false,
        canManageChat: true,
        canDeleteMessages: $canDelete,
        canManageVideoChats: false,
        canRestrictMembers: false,
        canPromoteMembers: false,
        canChangeInfo: false,
        canInviteUsers: false,
        canPostStories: false,
        canEditStories: false,
        canDeleteStories: false,
    );
}

function smOwnerMember(int $tgId): ChatMemberOwnerTypeDTO
{
    return new ChatMemberOwnerTypeDTO(user: smUser($tgId), isAnonymous: false);
}
