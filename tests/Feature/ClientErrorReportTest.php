<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ClientErrorReportTest extends TestCase
{
    public function test_accepts_a_browser_error_and_logs_it(): void
    {
        Log::spy();

        $this->postJson('/api/v1/client-errors', [
            'message' => 'Cannot read properties of undefined',
            'stack' => "TypeError: Cannot read properties of undefined\n    at ClaimsGrid",
            'source' => 'error-boundary',
            'url' => 'https://rcp.example/claims/12',
            'app_version' => 'abc123',
            'trace_id' => str_repeat('a', 32),
            'extra' => ['component' => 'ClaimsGrid'],
        ])->assertNoContent();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'frontend: Cannot read properties of undefined'
                    && $context['source'] === 'error-boundary'
                    && $context['url'] === 'https://rcp.example/claims/12'
                    && $context['app_version'] === 'abc123'
                    && $context['server_trace_id'] === str_repeat('a', 32)
                    && $context['extra'] === ['component' => 'ClaimsGrid'];
            });
    }

    public function test_strips_control_characters_from_the_message_before_logging(): void
    {
        Log::spy();

        $this->postJson('/api/v1/client-errors', [
            'message' => "boom\n[2026-01-01] production.CRITICAL: forged line\t\x07",
        ])->assertNoContent();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message) => $message === 'frontend: boom [2026-01-01] production.CRITICAL: forged line');
    }

    public function test_strips_control_characters_from_every_logged_field(): void
    {
        Log::spy();

        $forged = "\n[2026-01-01] production.CRITICAL: forged";

        $this->withHeader('User-Agent', "Mozilla\x01forged")->postJson('/api/v1/client-errors', [
            'message' => 'boom',
            'stack' => 'Error: boom'.$forged,
            'source' => 'window'.$forged,
            'url' => 'https://rcp.example/'.$forged,
            'app_version' => 'abc'.$forged,
            'extra' => ['key'.$forged => 'value'.$forged, 'count' => 3],
        ])->assertNoContent();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                array_walk_recursive($context, function ($value, $key) {
                    $this->assertDoesNotMatchRegularExpression('/[\x00-\x1F\x7F]/', (string) $key);
                    $this->assertDoesNotMatchRegularExpression('/[\x00-\x1F\x7F]/', (string) $value);
                });

                return $context['stack'] === 'Error: boom [2026-01-01] production.CRITICAL: forged'
                    && $context['user_agent'] === 'Mozilla forged'
                    && ($context['extra']['key [2026-01-01] production.CRITICAL: forged'] ?? null) === 'value [2026-01-01] production.CRITICAL: forged'
                    && $context['extra']['count'] === 3;
            });
    }

    public function test_rejects_oversized_extra_keys_and_values(): void
    {
        $this->postJson('/api/v1/client-errors', [
            'message' => 'boom',
            'extra' => ['big' => str_repeat('a', 2001)],
        ])->assertStatus(422);

        $this->postJson('/api/v1/client-errors', [
            'message' => 'boom',
            'extra' => [str_repeat('k', 101) => 'v'],
        ])->assertStatus(422);

        $this->postJson('/api/v1/client-errors', [
            'message' => 'boom',
            'extra' => [str_repeat('k', 100) => str_repeat('a', 2000)],
        ])->assertNoContent();
    }

    public function test_rejects_non_scalar_extra_values(): void
    {
        $this->postJson('/api/v1/client-errors', [
            'message' => 'x',
            'extra' => ['nested' => ['a' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors(['extra.nested']);
    }

    public function test_rejects_reports_without_a_message_or_with_a_bad_trace_id(): void
    {
        $this->postJson('/api/v1/client-errors', [])->assertStatus(422);

        $this->postJson('/api/v1/client-errors', [
            'message' => 'x',
            'trace_id' => 'not-a-trace-id',
        ])->assertStatus(422)->assertJsonValidationErrors(['trace_id']);
    }

    public function test_is_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->postJson('/api/v1/client-errors', ['message' => "noise $i"])->assertNoContent();
        }

        $this->postJson('/api/v1/client-errors', ['message' => 'one too many'])->assertStatus(429);
    }
}
