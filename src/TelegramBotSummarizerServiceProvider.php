<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

final class TelegramBotSummarizerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/summarizer.php', 'summarizer');

        // The summarizer:digests schedule is declared in
        // config/tg_modules.php (schedule) and registered by the module
        // engine, with schedule-overrides.php user overrides applied.
        // Artisan commands are declared in config/tg_modules.php (commands)
        // and registered by the module engine.
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
