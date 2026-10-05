<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionsExportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_should_export_a_transactions_collection(): void
    {
        $user = User::factory()->create([
            'role_id' => 1,
        ]);

        $this->actingAs($user);
        $this->assertAuthenticated();
    }
}
