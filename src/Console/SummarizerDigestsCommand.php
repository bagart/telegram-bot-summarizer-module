<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Console;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBotManagement\Models\TgBot;
use BAGArt\TelegramBotManagement\Models\TgModuleEnablement;
use BAGArt\TelegramBotSummarizer\ModuleFactory;
use BAGArt\TelegramBotSummarizer\Models\SummarizerMessage;
use BAGArt\TelegramBotSummarizer\Models\SummarizerRun;
use BAGArt\TelegramBotSummarizer\Settings\SummarizerModuleId;
use BAGArt\TelegramBotSummarizer\Settings\SummarizerSettingsService;
use BAGArt\TelegramBotSummarizer\Ui\PendingInputService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Cron entry: scans enabled summarizer chats and produces due digests.
 * A chat is due when its interval has elapsed since the last run (any
 * status, so failures back off naturally) AND enough messages collected.
 */
class SummarizerDigestsCommand extends Command
{
    protected $signature = 'summarizer:digests {--chat= : Only this chat id}';

    protected $description = 'Produce due chat digests for the summarizer module';

    public function handle(SummarizerSettingsService $settingsService): int
    {
        PendingInputService::pruneExpired();
        $this->pruneRetention();

        $candidates = TgModuleEnablement::query()
            ->where('module_id', SummarizerModuleId::ID)
            ->where('is_enabled', true)
            ->whereNotNull('bot_id')
            ->whereNotNull('chat_id')
            ->when($this->option('chat') !== null, fn ($q) => $q->where('chat_id', (int) $this->option('chat')))
            ->get(['bot_id', 'chat_id']);

        $produced = 0;
        $skipped = 0;

        foreach ($candidates as $row) {
            $bot = TgBot::withTrashed()->find($row->bot_id);

            if ($bot === null) {
                continue;
            }

            $settings = $settingsService->get($row->bot_id, (int) $row->chat_id);

            if (! $this->isDue($row->bot_id, (int) $row->chat_id, $settings->intervalMinutes)) {
                $skipped++;

                continue;
            }

            try {
                $runner = ModuleFactory::digestRunnerSync();
                $outcome = $runner->run(new TgBotConfig(token: $bot->token, botId: $bot->bot_id), (int) $row->chat_id);

                $outcome->isSuccess() ? $produced++ : $skipped++;
            } catch (Throwable $e) {
                $skipped++;
                Log::error('Summarizer: cron digest failed', [
                    'bot_id' => $row->bot_id,
                    'chat_id' => $row->chat_id,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Summarizer: {$produced} digest(s) produced, {$skipped} skipped.");

        return self::SUCCESS;
    }

    private function isDue(string $botId, int $chatId, int $intervalMinutes): bool
    {
        $lastRunAt = SummarizerRun::query()
            ->where('bot_id', $botId)
            ->where('chat_id', $chatId)
            ->max('created_at');

        if ($lastRunAt === null) {
            // Never ran: wait until at least one full interval of traffic exists
            return true;
        }

        return $lastRunAt->getTimestamp() + $intervalMinutes * 60 <= time();
    }

    private function pruneRetention(): void
    {
        $retentionDays = (int) config('summarizer.retention_days', 14);
        $cutoff = time() - $retentionDays * 86400;

        SummarizerMessage::query()
            ->where('sent_at', '<', $cutoff)->delete();

        $expiredRuns = SummarizerRun::query()
            ->where('period_to', '<', $cutoff)
            ->get(['id', 'transcript_path']);

        foreach ($expiredRuns as $run) {
            if (is_string($run->transcript_path)) {
                Storage::disk('local')->delete($run->transcript_path);
            }
        }

        SummarizerRun::query()
            ->where('period_to', '<', $cutoff)->delete();
    }
}
