<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Telegram users granted summarizer admin rights because they added the bot
 * to the chat ("inviter" role). Complementary to live can_delete_messages
 * checks; rows are append-only history.
 */
class SummarizerChatAccess extends Model
{
    use HasFactory;
    use HasUuids;

    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $table = 'summarizer_chat_access';

    protected $fillable = [
        'bot_id',
        'chat_id',
        'inviter_tg_id',
        'inviter_username',
        'invited_at',
    ];

    protected function casts(): array
    {
        return [
            'chat_id' => 'integer',
            'inviter_tg_id' => 'integer',
            'invited_at' => 'integer',
        ];
    }
}
