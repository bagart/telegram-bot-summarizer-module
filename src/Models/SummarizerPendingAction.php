<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Awaiting-user-input state (token paste, template editor, ...). One active
 * action per (bot, chat, user); expired rows are garbage-collected lazily.
 */
class SummarizerPendingAction extends Model
{
    use HasFactory;
    use HasUuids;

    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', time());
    }

    protected $fillable = [
        'bot_id',
        'chat_id',
        'user_tg_id',
        'action',
        'payload',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'chat_id' => 'integer',
            'user_tg_id' => 'integer',
            'payload' => 'array',
            'expires_at' => 'integer',
        ];
    }
}
