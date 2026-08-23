<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Digest;

/** Result of one DigestRunner attempt, safe to show back to admins. */
final readonly class DigestOutcome
{
    private const STATUS_SUCCESS = 'success';

    private const STATUS_FAILED = 'failed';

    private const STATUS_SKIPPED = 'skipped';

    private const STATUS_BUSY = 'busy';

    private function __construct(
        public string $status,
        public ?string $error = null,
        public int $messageCount = 0,
    ) {
    }

    public static function success(int $messageCount): self
    {
        return new self(self::STATUS_SUCCESS, messageCount: $messageCount);
    }

    public static function failed(string $error): self
    {
        return new self(self::STATUS_FAILED, error: $error);
    }

    public static function skipped(string $reason): self
    {
        return new self(self::STATUS_SKIPPED, error: $reason);
    }

    public static function busy(): self
    {
        return new self(self::STATUS_BUSY);
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }
}
