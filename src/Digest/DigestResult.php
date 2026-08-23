<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Digest;

/**
 * Immutable output of building a digest transcript for one chat period.
 */
final readonly class DigestResult
{
    public function __construct(
        public string $transcript,
        public string $filePath,
        public int $messageCount,
        public int $participantCount,
        public bool $truncated,
    ) {
    }
}
