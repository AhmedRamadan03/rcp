<?php

namespace Tests\Unit\Dashboard\StatusReport;

use App\Services\Dashboard\StatusReport\StatusReportFilters;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StatusReportFiltersTest extends TestCase
{
    private StatusReportFilters $filters;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filters = new StatusReportFilters();
        Carbon::setTestNow(Carbon::create(2026, 7, 15, 12, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function defaults_to_current_year_role_entity_and_empty_lists(): void
    {
        $result = $this->filters->resolve(Request::create('/'));

        $this->assertSame('2026-01-01', $result['from']);
        $this->assertSame('2026-12-31', $result['to']);
        $this->assertSame('role', $result['entity']);
        $this->assertSame([], $result['entity_ids']);
        $this->assertSame([], $result['statuses']);
    }

    #[Test]
    public function respects_explicit_from_and_to(): void
    {
        $result = $this->filters->resolve(Request::create('/', 'GET', [
            'from' => '2025-03-01',
            'to' => '2025-06-30',
        ]));

        $this->assertSame('2025-03-01', $result['from']);
        $this->assertSame('2025-06-30', $result['to']);
    }

    #[Test]
    public function accepts_user_entity(): void
    {
        $result = $this->filters->resolve(Request::create('/', 'GET', [
            'entity' => 'user',
        ]));

        $this->assertSame('user', $result['entity']);
    }

    #[Test]
    public function invalid_entity_falls_back_to_role(): void
    {
        $result = $this->filters->resolve(Request::create('/', 'GET', [
            'entity' => 'team',
        ]));

        $this->assertSame('role', $result['entity']);
    }

    #[Test]
    public function malformed_dates_fall_back_to_current_year(): void
    {
        $result = $this->filters->resolve(Request::create('/', 'GET', [
            'from' => 'not-a-date',
            'to' => '',
        ]));

        $this->assertSame('2026-01-01', $result['from']);
        $this->assertSame('2026-12-31', $result['to']);
    }

    #[Test]
    public function role_entity_ids_accept_channel_names(): void
    {
        $result = $this->filters->resolve(Request::create('/', 'GET', [
            'entity' => 'role',
            'entity_ids' => ['internal', 'LEGAL', 'unknown', 3],
        ]));

        $this->assertSame(['internal', 'legal'], $result['entity_ids']);
    }

    #[Test]
    public function user_entity_ids_normalize_to_integers(): void
    {
        $result = $this->filters->resolve(Request::create('/', 'GET', [
            'entity' => 'user',
            'entity_ids' => ['3', 8, 'x', null],
        ]));

        $this->assertSame([3, 8], $result['entity_ids']);
    }

    #[Test]
    public function role_entity_ids_drop_numeric_role_ids(): void
    {
        $result = $this->filters->resolve(Request::create('/', 'GET', [
            'entity' => 'role',
            'entity_ids' => [3, 8, 'external'],
        ]));

        $this->assertSame(['external'], $result['entity_ids']);
    }

    #[Test]
    public function non_array_entity_ids_become_empty(): void
    {
        $result = $this->filters->resolve(Request::create('/', 'GET', [
            'entity_ids' => 'internal',
        ]));

        $this->assertSame([], $result['entity_ids']);
    }

    #[Test]
    public function collect_status_alias_becomes_collected(): void
    {
        $result = $this->filters->resolve(Request::create('/', 'GET', [
            'status' => ['collect'],
        ]));

        $this->assertSame(['collected'], $result['statuses']);
    }

    #[Test]
    public function keeps_allowed_statuses_and_drops_invalid_ones(): void
    {
        $result = $this->filters->resolve(Request::create('/', 'GET', [
            'status' => ['cleansed', 'outstanding', 'unknown', 'collect', 'collected'],
        ]));

        $this->assertSame(['cleansed', 'outstanding', 'collected'], $result['statuses']);
    }

    #[Test]
    public function non_array_status_becomes_empty(): void
    {
        $result = $this->filters->resolve(Request::create('/', 'GET', [
            'status' => 'cleansed',
        ]));

        $this->assertSame([], $result['statuses']);
    }
}
