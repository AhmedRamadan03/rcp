<?php

namespace Tests\Unit\Dashboard\StatusReport;

use App\Enums\Roles;
use App\Services\Dashboard\StatusReport\StatusReportChannels;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StatusReportChannelsTest extends TestCase
{
    #[Test]
    public function internal_includes_collector_and_senior_collector(): void
    {
        $this->assertSame(
            [Roles::COLLECTOR->value, Roles::SENIOR_COLLECTOR->value],
            StatusReportChannels::internal()
        );
    }

    #[Test]
    public function external_includes_external_office_only(): void
    {
        $this->assertSame(
            [Roles::EXTERNAL_OFFICE->value],
            StatusReportChannels::external()
        );
    }

    #[Test]
    public function legal_includes_legal_department_only(): void
    {
        $this->assertSame(
            [Roles::LEGAL_DEPARTMENT->value],
            StatusReportChannels::legal()
        );
    }
}
