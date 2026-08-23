<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/**
 * LLM API token owned by a bot's admin group. Full value is only readable
 * server-side for outgoing LLM calls; UI must use masked().
 */
class SummarizerToken extends Model
{
    use HasFactory;
    use HasUuids;

    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return \Database\Factories\SummarizerTokenFactory::new();
    }

    protected $fillable = [
        'bot_id',
        'provider_key',
        'label',
        'token',
        'created_by_tg_id',
        'created_by_username',
    ];

    /** @var list<string> */
    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'created_by_tg_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function masked(): string
    {
        return self::mask($this->token);
    }

    public static function mask(
        #[SensitiveParameter]
        string $token,
    ): string {
        $length = mb_strlen($token);

        if ($length <= 8) {
            return str_repeat('•', max(0, $length - 0));
        }

        return mb_substr($token, 0, 4).'…'.mb_substr($token, -4);
    }
}
