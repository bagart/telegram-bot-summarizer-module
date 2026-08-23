<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Telegram;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\Contracts\Outbound\TgSenderContract;
use BAGArt\TelegramBot\Contracts\TgApi\TgApiMethodDTOContract;

/**
 * TgSenderContract adapter that executes requests synchronously through the
 * typed API client. Used outside the webhook pipeline (cron digest runs)
 * where there is no outbound daemon to queue through.
 */
final class SyncTgSender implements TgSenderContract
{
    public function __construct(
        private readonly TgBotApiDTOClientContract $apiClient,
    ) {
    }

    public function send(TgBotConfig $botConfig, TgApiMethodDTOContract $dto): void
    {
        $this->apiClient->request($botConfig, $dto);
    }
}
