<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Access;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\TgApi\Methods\DTO\GetChatAdministratorsMethodDTO;
use BAGArt\TelegramBot\TgApi\Methods\DTO\GetMeMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\ChatMemberAdministratorTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\ChatMemberOwnerTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use BAGArt\TelegramBotSummarizer\Models\SummarizerChatAccess;
use Throwable;

/**
 * Decides who may open the summarizer admin panel in a chat:
 * - Telegram group admins whose rights include "delete messages of others";
 * - the user who added the bot to this chat ("inviter");
 * - platform superadmins (SUMMARIZER_SUPERADMIN_TG_IDS).
 */
class SummarizerAccessService
{
    private const ADMIN_LIST_TTL = 300;

    private const BOT_ID_TTL = 3600;

    public function __construct(
        private readonly TgBotApiDTOClientContract $api,
    ) {
    }

    public function isSuperadmin(int|string $userTgId): bool
    {
        return in_array((string) $userTgId, config('summarizer.superadmins', []), true);
    }

    public function isInviter(string $botId, int $chatId, int|string $userTgId): bool
    {
        return SummarizerChatAccess::query()
            ->where('bot_id', $botId)
            ->where('chat_id', $chatId)
            ->where('inviter_tg_id', (int) $userTgId)
            ->exists();
    }

    public function canManage(TgBotConfig $botConfig, int $chatId, UserTypeDTO $user): bool
    {
        if ($this->isSuperadmin($user->id)) {
            return true;
        }

        $botId = (string) $botConfig->botId;

        if ($this->isInviter($botId, $chatId, $user->id)) {
            return true;
        }

        return $this->hasTelegramDeleteRights($botConfig, $chatId, (int) $user->id);
    }

    /**
     * Live Telegram check (cached): member must be owner or an administrator
     * holding can_delete_messages.
     */
    public function hasTelegramDeleteRights(TgBotConfig $botConfig, int $chatId, int $userTgId): bool
    {
        $admins = $this->administrators($botConfig, $chatId);

        if ($admins === null) {
            // API failure: fail closed for privilege grants
            return false;
        }

        foreach ($admins as $member) {
            if ((int) $member->user->id !== $userTgId) {
                continue;
            }

            if ($member instanceof ChatMemberOwnerTypeDTO) {
                return true;
            }

            return $member instanceof ChatMemberAdministratorTypeDTO && $member->canDeleteMessages;
        }

        return false;
    }

    /**
     * Telegram user id of the bot itself (resolved once per token).
     */
    public function botUserId(TgBotConfig $botConfig): ?string
    {
        $cacheKey = 'summarizer:bot-user:'.sha1($botConfig->token);

        try {
            $cached = $this->cacheGet($cacheKey);

            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        } catch (Throwable) {
            // cache unavailable — fall through to a direct call
        }

        try {
            $response = $this->api->request($botConfig, new GetMeMethodDTO());

            if (! $response->ok || ! $response->result instanceof UserTypeDTO) {
                return null;
            }

            $this->cachePut($cacheKey, $response->result->id, self::BOT_ID_TTL);

            return $response->result->id;
        } catch (Throwable $e) {
            Log::warning('Summarizer: getMe failed', ['exception' => $e::class]);

            return null;
        }
    }

    /**
     * @return list<ChatMemberOwnerTypeDTO|ChatMemberAdministratorTypeDTO>|null
     */
    private function administrators(TgBotConfig $botConfig, int $chatId): ?array
    {
        $cacheKey = sprintf('summarizer:admins:%s:%d', (string) $botConfig->botId, $chatId);

        try {
            $cached = $this->cacheGet($cacheKey);

            if (is_array($cached)) {
                return $cached;
            }
        } catch (Throwable) {
            // cache unavailable — fetch without caching below
        }

        try {
            $response = $this->api->request($botConfig, new GetChatAdministratorsMethodDTO(chatId: (string) $chatId));
        } catch (Throwable $e) {
            Log::warning('Summarizer: getChatAdministrators failed', [
                'chat_id' => $chatId,
                'exception' => $e::class,
            ]);

            return null;
        }

        if (! $response->ok || ! is_array($response->result)) {
            return null;
        }

        $members = [];

        foreach ($response->result as $member) {
            if ($member instanceof ChatMemberOwnerTypeDTO || $member instanceof ChatMemberAdministratorTypeDTO) {
                $members[] = $member;
            }
        }

        $this->cachePut($cacheKey, $members, self::ADMIN_LIST_TTL);

        return $members;
    }

    private function cacheAvailable(): bool
    {
        return app()->bound('cache');
    }

    /**
     * @return mixed
     */
    private function cacheGet(string $key)
    {
        if (! $this->cacheAvailable()) {
            return null;
        }

        return Cache::get($key);
    }

    private function cachePut(string $key, mixed $value, int $ttl): void
    {
        if ($this->cacheAvailable()) {
            Cache::put($key, $value, $ttl);
        }
    }
}
