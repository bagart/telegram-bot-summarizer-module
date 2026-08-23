# bagart/telegram-bot-summarizer-module

Chat summarizer module for the [Telegram bot platform](../..): collects group
messages, produces scheduled LLM digests (themes, positions, witty
mini-summaries) and ships an in-chat admin panel for interval / provider /
token / template management.

Disabled by default per chat — nothing is collected until a chat admin enables
it via `/summarizer`.

## What it provides

- `SummarizerModule` — `TgModuleContract` plugin (processors for messages,
  chat-member updates and callback queries; `/summarizer` and
  `/summarizer_cancel` commands).
- `summarizer:digests` Artisan command — cron entry scanning enabled chats and
  producing due digests (schedule it from the host app, see
  `routes/console.php` there).
- `config/summarizer.php` — platform defaults and operational limits
  (env-driven).
- Migrations for `summarizer_tokens`, `summarizer_messages`, `summarizer_runs`,
  `summarizer_chat_access`, `summarizer_pending_actions`.

## Install (host app)

1. Add a path repository and PSR-4 mapping in the host `composer.json`:

   ```json
   "repositories": [{ "type": "path", "url": "misc/BAGArt/telegram-bot-summarizer-module" }],
   "autoload": { "psr-4": { "BAGArt\\TelegramBotSummarizer\\": "misc/BAGArt/telegram-bot-summarizer-module/src/" } }
   ```

2. Register the provider in `bootstrap/providers.php`:

   ```php
   BAGArt\TelegramBotSummarizer\TelegramBotSummarizerServiceProvider::class,
   ```

3. `composer dump-autoload && php artisan migrate`.

## Tests

```bash
composer test   # from this directory; uses the host app's PHPUnit
```
