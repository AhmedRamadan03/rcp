<?php

namespace Tests\Unit\Imports;

use PHPUnit\Framework\TestCase;
use App\Imports\ClaimImportWithActionMapper;
use App\Models\User;
use App\Models\UploadedClaimsFile;

class ParseDateTodmYTest extends TestCase
{
    private $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $user = $this->createMock(User::class);
        $uploadedFile = $this->createMock(UploadedClaimsFile::class);

        $this->mapper = new class($user, $uploadedFile) extends ClaimImportWithActionMapper {
            public function parseDateToYmdPublic(mixed $value): ?string
            {
                return $this->parseDateToYmd($value);
            }
        };
    }

    // ── Null / Empty ────────────────────────────────────────────────

    public function test_returns_null_for_null(): void
    {
        $this->assertNull($this->mapper->parseDateToYmdPublic(null));
    }

    public function test_returns_null_for_empty_string(): void
    {
        $this->assertNull($this->mapper->parseDateToYmdPublic(''));
    }

    // ── Excel Serial Numbers ────────────────────────────────────────

    public function test_parses_excel_serial_number(): void
    {
        // 45350 = 2024-02-28 in Excel serial
        $result = $this->mapper->parseDateToYmdPublic(45350);
        $this->assertEquals('2024-02-28', $result);
    }

    public function test_parses_excel_serial_as_string(): void
    {
        $result = $this->mapper->parseDateToYmdPublic('45350');
        $this->assertEquals('2024-02-28', $result);
    }

    // ── d/m/Y String Format ─────────────────────────────────────────

    public function test_parses_valid_dmY_string(): void
    {
        $result = $this->mapper->parseDateToYmdPublic('28/02/2026');
        $this->assertEquals('2026-02-28', $result);
    }

    public function test_parses_dmY_with_leading_zeros(): void
    {
        $result = $this->mapper->parseDateToYmdPublic('01/01/2025');
        $this->assertEquals('2025-01-01', $result);
    }

    public function test_does_not_swap_day_and_month(): void
    {
        // 05/03/2026 must stay 05 March, not 03 May
        $result = $this->mapper->parseDateToYmdPublic('05/03/2026');
        $this->assertEquals('2026-03-05', $result);
    }

    // ── Fallback Formats ────────────────────────────────────────────

    public function test_parses_Y_m_d_format(): void
    {
        $result = $this->mapper->parseDateToYmdPublic('2026-02-28');
        $this->assertEquals('2026-02-28', $result);
    }

    public function test_parses_with_surrounding_whitespace(): void
    {
        $result = $this->mapper->parseDateToYmdPublic('  28/02/2026  ');
        $this->assertEquals('2026-02-28', $result);
    }

    // ── Invalid Values (must throw) ─────────────────────────────────

    public function test_throws_for_invalid_string(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Unable to parse date value/');

        $this->mapper->parseDateToYmdPublic('not-a-date');
    }

    public function test_throws_for_random_text(): void
    {
        $this->expectException(\Exception::class);

        $this->mapper->parseDateToYmdPublic('hello world');
    }
}
