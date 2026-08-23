<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Summarizer module schema: LLM token vault, collected chat messages,
 * digest runs, chat access (inviters) and pending admin-input states.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('summarizer_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('bot_id', 20);
            $table->string('provider_key', 30);
            $table->string('label', 100);
            // Stored with Laravel 'encrypted' cast; never returned in full via API/UI
            $table->text('token');
            $table->unsignedBigInteger('created_by_tg_id');
            $table->string('created_by_username', 64)->nullable();
            $table->timestampsTz();

            $table->foreign('bot_id')
                ->references('bot_id')->on('tg_bots')
                ->cascadeOnDelete();

            $table->index(['bot_id', 'provider_key']);
        });

        Schema::create('summarizer_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('bot_id', 20);
            $table->bigInteger('chat_id');
            $table->bigInteger('message_id');
            $table->integer('thread_id')->nullable();
            $table->unsignedBigInteger('user_tg_id')->nullable();
            $table->string('username', 64)->nullable();
            $table->string('display_name', 128)->nullable();
            $table->text('text')->nullable();
            $table->boolean('is_bot')->default(false);
            $table->unsignedBigInteger('sent_at');

            $table->foreign('bot_id')
                ->references('bot_id')->on('tg_bots')
                ->cascadeOnDelete();

            // Webhook at-least-once delivery: one row per message max
            $table->unique(['bot_id', 'chat_id', 'message_id']);
            $table->index(['bot_id', 'chat_id', 'sent_at']);
        });

        Schema::create('summarizer_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('bot_id', 20);
            $table->bigInteger('chat_id');
            $table->unsignedBigInteger('period_from');
            $table->unsignedBigInteger('period_to');
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('participant_count')->default(0);
            // success | failed
            $table->string('status', 20);
            $table->text('error')->nullable();
            $table->longText('summary_text')->nullable();
            $table->string('transcript_path')->nullable();
            $table->string('provider_key', 30)->nullable();
            $table->string('model', 100)->nullable();
            $table->uuid('token_id')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestampTz('created_at')->nullable();

            $table->foreign('bot_id')
                ->references('bot_id')->on('tg_bots')
                ->cascadeOnDelete();

            $table->index(['bot_id', 'chat_id', 'period_to']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('summarizer_chat_access', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('bot_id', 20);
            $table->bigInteger('chat_id');
            // Telegram user who added the bot to the chat ("inviter" role)
            $table->unsignedBigInteger('inviter_tg_id');
            $table->string('inviter_username', 64)->nullable();
            $table->unsignedBigInteger('invited_at');

            $table->foreign('bot_id')
                ->references('bot_id')->on('tg_bots')
                ->cascadeOnDelete();

            $table->unique(['bot_id', 'chat_id', 'inviter_tg_id']);
        });

        Schema::create('summarizer_pending_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('bot_id', 20);
            $table->bigInteger('chat_id');
            $table->unsignedBigInteger('user_tg_id');
            // token_input | template_input | provider_json | min_messages
            $table->string('action', 30);
            $table->json('payload')->nullable();
            $table->unsignedBigInteger('expires_at');

            $table->foreign('bot_id')
                ->references('bot_id')->on('tg_bots')
                ->cascadeOnDelete();

            $table->unique(['bot_id', 'chat_id', 'user_tg_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summarizer_pending_actions');
        Schema::dropIfExists('summarizer_chat_access');
        Schema::dropIfExists('summarizer_runs');
        Schema::dropIfExists('summarizer_messages');
        Schema::dropIfExists('summarizer_tokens');
    }
};
