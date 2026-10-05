<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * bootstrap/otel.php (via bootstrap/otel-env.php) must export OTEL_* values from
 * .env into the process environment before the OpenTelemetry SDK boots, without
 * touching anything else and without overriding values the operator set on the process.
 */
class OtelEnvShimTest extends TestCase
{
    private string $file;

    /** @var string[] */
    private array $touched = [];

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2).'/bootstrap/otel-env.php';
        $this->file = tempnam(sys_get_temp_dir(), 'otel-env-');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        foreach ($this->touched as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
        parent::tearDown();
    }

    private function write(string $contents): void
    {
        file_put_contents($this->file, $contents);
    }

    private function track(string ...$keys): void
    {
        array_push($this->touched, ...$keys);
    }

    public function test_exports_only_otel_keys_and_strips_quotes(): void
    {
        $this->track('OTEL_SHIMTEST_A', 'OTEL_SHIMTEST_B', 'OTEL_SHIMTEST_C', 'OTEL_SHIMTEST_D', 'SHIMTEST_NOT_OTEL');
        $this->write(<<<'ENV'
SHIMTEST_NOT_OTEL=Should-Not-Export
OTEL_SHIMTEST_A=true
OTEL_SHIMTEST_B="art-rcp-api-live"
OTEL_SHIMTEST_C='http://10.0.0.5:4318'
OTEL_SHIMTEST_D=deployment.environment=live,tenant=art # inline comment
export OTEL_SHIMTEST_E=exported-form
ENV);
        $this->track('OTEL_SHIMTEST_E');

        $exported = otel_export_env_from_file($this->file);

        $this->assertSame([
            'OTEL_SHIMTEST_A' => 'true',
            'OTEL_SHIMTEST_B' => 'art-rcp-api-live',
            'OTEL_SHIMTEST_C' => 'http://10.0.0.5:4318',
            'OTEL_SHIMTEST_D' => 'deployment.environment=live,tenant=art',
            'OTEL_SHIMTEST_E' => 'exported-form',
        ], $exported);

        $this->assertSame('art-rcp-api-live', getenv('OTEL_SHIMTEST_B'));
        $this->assertSame('art-rcp-api-live', $_SERVER['OTEL_SHIMTEST_B']);
        $this->assertSame('art-rcp-api-live', $_ENV['OTEL_SHIMTEST_B']);
        $this->assertFalse(getenv('SHIMTEST_NOT_OTEL'));
        $this->assertArrayNotHasKey('SHIMTEST_NOT_OTEL', $_ENV);
        $this->assertArrayNotHasKey('SHIMTEST_NOT_OTEL', $_SERVER);
    }

    public function test_process_environment_wins_over_dotenv(): void
    {
        $this->track('OTEL_SHIMTEST_PRESET');
        putenv('OTEL_SHIMTEST_PRESET=from-php-fpm');
        $this->write("OTEL_SHIMTEST_PRESET=from-dotenv\n");

        $exported = otel_export_env_from_file($this->file);

        $this->assertSame([], $exported);
        $this->assertSame('from-php-fpm', getenv('OTEL_SHIMTEST_PRESET'));
    }

    public function test_missing_file_is_a_noop(): void
    {
        $this->assertSame([], otel_export_env_from_file('/nonexistent/.env'));
    }

    public function test_ignores_malformed_keys(): void
    {
        $this->track('OTEL_SHIMTEST_OK');
        $this->write("OTEL_bad-key=1\nOTEL_=1\nOTEL_SHIMTEST_OK=1\nOTEL_NOEQUALS\n");

        $this->assertSame(['OTEL_SHIMTEST_OK' => '1'], otel_export_env_from_file($this->file));
    }
}
