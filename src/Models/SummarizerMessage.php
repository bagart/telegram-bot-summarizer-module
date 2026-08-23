<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One collected chat message. Insert-only (edits/deletes are ignored on
 * purpose — the transcript reflects what was said at the time).
 */
class SummarizerMessage extends Model
{
    use HasFactory;

    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return \Database\Factories\SummarizerMessageFactory::new();
    }

    protected $fillable = [
        'bot_id',
        'chat_id',
        'message_id',
        'thread_id',
        'user_tg_id',
        'username',
        'display_name',
        'text',
        'is_bot',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'chat_id' => 'integer',
            'message_id' => 'integer',
            'thread_id' => 'integer',
            'user_tg_id' => 'integer',
            'is_bot' => 'boolean',
            'sent_at' => 'integer',
        ];
    }
}
