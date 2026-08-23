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

        // Composer-installed module discovery (config/telegram.php contract)
        $providers = (array) Config::get('telegram.modules_providers', []);
        Config::set('telegram.modules_providers', array_values(array_unique(array_merge(
            $providers,
            [SummarizerModule::class],
        ))));

        $this->commands([
            Console\SummarizerDigestsCommand::class,
        ]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
