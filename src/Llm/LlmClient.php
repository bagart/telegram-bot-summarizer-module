<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotSummarizer\Llm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Minimal, deliberately constrained LLM HTTP client.
 *
 * Safety contract (module requirement: "the LLM must do nothing beyond
 * analyzing the transcript"):
 * - only the chat/messages endpoint of the configured provider is called;
 * - no tools / function-calling / JSON-mode parameters are ever sent;
 * - response body is size-capped and only the assistant text is returned;
 * - the API token never appears in exceptions or logs.
 */
class LlmClient
{
    private const MAX_ATTEMPTS = 2;

    private const RETRY_DELAY_MS = 1500;

    private const RETRY_AFTER_CAP_SECONDS = 30;

    public function __construct(
        private readonly int $maxResponseBytes,
    ) {
    }

    public function complete(LlmProviderConfig $config, string $systemPrompt, string $userContent): string
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return $this->attempt($config, $systemPrompt, $userContent);
            } catch (LlmRetryableException $e) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw new LlmCallException($e->getMessage(), 0, $e);
                }

                Sleep::for(min($e->retryAfterSeconds, self::RETRY_AFTER_CAP_SECONDS))->seconds();
            } catch (ConnectionException $e) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw LlmCallException::fromThrowable($e, 'LLM connection failed');
                }

                Sleep::for(self::RETRY_DELAY_MS)->milliseconds();
            } catch (Throwable $e) {
                throw LlmCallException::fromThrowable($e, 'LLM call failed');
            }
        }
    }

    private function attempt(LlmProviderConfig $config, string $systemPrompt, string $userContent): string
    {
        $request = Http::baseUrl($config->baseUrl)
            ->connectTimeout(10)
            ->timeout($config->timeoutSeconds)
            ->asJson();

        if ($config->apiStyle === LlmApiStyle::Anthropic) {
            $request = $request->withHeaders(array_filter([
                'x-api-key' => $config->token ?? '',
                'anthropic-version' => '2023-06-01',
            ]));
        } elseif ($config->token !== null && $config->token !== '') {
            $request = $request->withToken($config->token);
        }

        $response = $request->post($this->endpoint($config), $this->buildPayload($config, $systemPrompt, $userContent));

        if ($response->tooManyRequests()) {
            throw new LlmRetryableException(
                sprintf('LLM rate limited (HTTP 429)'),
                (int) $response->header('retry-after'),
            );
        }

        if ($response->failed()) {
            throw LlmCallException::httpError($response->status(), (string) $response->body());
        }

        $body = (string) $response->body();

        if (strlen($body) > $this->maxResponseBytes) {
            throw new LlmCallException('LLM response exceeds size limit');
        }

        return $this->extractText($config->apiStyle, $body);
    }

    private function endpoint(LlmProviderConfig $config): string
    {
        return $config->apiStyle === LlmApiStyle::Anthropic ? '/messages' : '/chat/completions';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(LlmProviderConfig $config, string $systemPrompt, string $userContent): array
    {
        if ($config->apiStyle === LlmApiStyle::Anthropic) {
            return [
                'model' => $config->model,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userContent],
                ],
                'max_tokens' => $config->maxTokens,
                'temperature' => $config->temperature,
                'stream' => false,
            ];
        }

        return [
            'model' => $config->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userContent],
            ],
            'temperature' => $config->temperature,
            'max_tokens' => $config->maxTokens,
            'stream' => false,
        ];
    }

    private function extractText(LlmApiStyle $apiStyle, string $body): string
    {
        try {
            $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw LlmCallException::fromThrowable($e, 'LLM returned non-JSON body');
        }

        $text = $apiStyle === LlmApiStyle::Anthropic
            ? $this->extractAnthropicText($data)
            : $this->extractOpenAiText($data);

        if (! is_string($text) || trim($text) === '') {
            throw new LlmCallException('LLM returned an empty completion');
        }

        return trim($text);
    }

    /**
     * @param  mixed  $data
     */
    private function extractOpenAiText($data): ?string
    {
        $choices = is_array($data) ? ($data['choices'] ?? null) : null;

        if (! is_array($choices) || $choices === []) {
            return null;
        }

        $message = $choices[0]['message'] ?? null;

        return is_array($message) && is_string($message['content'] ?? null)
            ? $message['content']
            : null;
    }

    /**
     * @param  mixed  $data
     */
    private function extractAnthropicText($data): ?string
    {
        $content = is_array($data) ? ($data['content'] ?? null) : null;

        if (! is_array($content)) {
            return null;
        }

        $parts = [];
        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $parts[] = $block['text'];
            }
        }

        return $parts === [] ? null : implode("\n", $parts);
    }
}
