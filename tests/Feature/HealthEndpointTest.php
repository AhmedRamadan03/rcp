<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_reports_ok_without_authentication(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'checks' => ['database' => 'ok']]);
    }
}
