<?php

namespace Tests\Feature\Dashboard;

use App\Enums\Roles;
use App\Services\Dashboard\AssignedClaimsReportService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssignedClaimsReportServiceTest extends TestCase
{
    private const DAY = '2026-06-15';

    private AssignedClaimsReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 7, 15, 12, 0, 0));

        config()->set('database.connections.memory_testing.database', ':memory:');
        DB::purge('memory_testing');
        DB::reconnect('memory_testing');
        config()->set('database.default', 'memory_testing');

        Schema::dropIfExists('payments');
        Schema::dropIfExists('assignings');
        Schema::dropIfExists('claims');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->unsignedBigInteger('role_id');
            $table->string('password')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('claims', function (Blueprint $table): void {
            $table->id();
            $table->double('amount', 8, 2);
            $table->date('intimation_date')->nullable();
            $table->date('accident_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('assignings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('claim_id');
            $table->unsignedBigInteger('to');
            $table->dateTime('assign_date');
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('claim_id');
            $table->double('amount', 8, 2)->nullable();
            $table->double('paid_amount', 8, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        $this->service = new AssignedClaimsReportService();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function role_mode_returns_three_channels_by_default(): void
    {
        $result = $this->service->build($this->request());

        $this->assertCount(3, $result['details']);
        $this->assertSame(['internal', 'external', 'legal'], array_column($result['details'], 'name'));
    }

    #[Test]
    public function aggregates_assigned_count_and_amount_for_channel(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);

        $claimA = $this->insertClaim(100);
        $this->insertAssigning($claimA, $user, self::DAY);

        $claimB = $this->insertClaim(50);
        $this->insertAssigning($claimB, $user, self::DAY);

        $result = $this->service->build($this->request());
        $internal = $this->channel($result, 'internal');

        $this->assertSame(2, $internal['assigned_count']);
        $this->assertEquals(150.0, $internal['assigned_amount']);
        $this->assertSame(2, $result['summary']['total_count']);
        $this->assertEquals(150.0, $result['summary']['total_amount']);
    }

    #[Test]
    public function user_mode_returns_rows_by_assignee(): void
    {
        $alice = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $bob = $this->insertUser('Bob', Roles::EXTERNAL_OFFICE->value);

        $claimA = $this->insertClaim(40);
        $this->insertAssigning($claimA, $alice, self::DAY);

        $claimB = $this->insertClaim(90);
        $this->insertAssigning($claimB, $bob, self::DAY);

        $result = $this->service->build($this->request(['entity' => 'user']));

        $this->assertCount(2, $result['details']);
        $this->assertSame($bob, $result['details'][0]['id']);
        $this->assertEquals(90.0, $result['details'][0]['assigned_amount']);
        $this->assertSame($alice, $result['details'][1]['id']);
    }

    #[Test]
    public function user_mode_limits_details_to_ten_rows(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $user = $this->insertUser("User{$i}", Roles::COLLECTOR->value);
            $claim = $this->insertClaim($i * 10);
            $this->insertAssigning($claim, $user, self::DAY);
        }

        $result = $this->service->build($this->request(['entity' => 'user']));

        $this->assertCount(10, $result['details']);
        $this->assertEquals(120.0, $result['details'][0]['assigned_amount']);
        $this->assertEquals(30.0, $result['details'][9]['assigned_amount']);
    }

    #[Test]
    public function role_mode_entity_ids_omit_unselected_channels(): void
    {
        $collector = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $external = $this->insertUser('Bob', Roles::EXTERNAL_OFFICE->value);

        $claimA = $this->insertClaim(40);
        $this->insertAssigning($claimA, $collector, self::DAY);

        $claimB = $this->insertClaim(90);
        $this->insertAssigning($claimB, $external, self::DAY);

        $result = $this->service->build($this->request([
            'entity_ids' => ['external'],
        ]));

        $this->assertCount(1, $result['details']);
        $this->assertSame('external', $result['details'][0]['name']);
        $this->assertEquals(90.0, $result['details'][0]['assigned_amount']);
    }

    #[Test]
    public function excludes_assignments_outside_date_range(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);

        $inside = $this->insertClaim(50, intimationDate: '2026-06-15');
        $this->insertAssigning($inside, $user, '2026-06-15');

        $outside = $this->insertClaim(90, intimationDate: '2025-06-15');
        $this->insertAssigning($outside, $user, '2025-06-15');

        $result = $this->service->build($this->request());
        $internal = $this->channel($result, 'internal');

        $this->assertSame(1, $internal['assigned_count']);
        $this->assertEquals(50.0, $internal['assigned_amount']);
    }

    #[Test]
    public function uses_accident_date_when_intimation_date_is_null(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);

        $byAccident = $this->insertClaim(75, intimationDate: null, accidentDate: '2026-03-01');
        $this->insertAssigning($byAccident, $user, self::DAY);

        $outside = $this->insertClaim(40, intimationDate: null, accidentDate: '2025-03-01');
        $this->insertAssigning($outside, $user, self::DAY);

        $result = $this->service->build($this->request(['entity' => 'user', 'entity_ids' => [$user]]));

        $this->assertSame(1, $result['details'][0]['assigned_count']);
        $this->assertEquals(75.0, $result['details'][0]['assigned_amount']);
    }

    #[Test]
    public function prefers_intimation_date_over_accident_date(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);

        // Intimation in range, accident outside — should still include.
        $claim = $this->insertClaim(55, intimationDate: '2026-06-15', accidentDate: '2025-01-01');
        $this->insertAssigning($claim, $user, self::DAY);

        $result = $this->service->build($this->request(['entity' => 'user', 'entity_ids' => [$user]]));

        $this->assertSame(1, $result['details'][0]['assigned_count']);
        $this->assertEquals(55.0, $result['details'][0]['assigned_amount']);
    }

    #[Test]
    public function excludes_inactive_assignments(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $claim = $this->insertClaim(50);
        $this->insertAssigning($claim, $user, self::DAY, isActive: 0);

        $result = $this->service->build($this->request());

        $this->assertSame(0, $result['summary']['total_count']);
    }

    #[Test]
    public function excludes_soft_deleted_assignings_and_claims(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);

        $liveClaim = $this->insertClaim(40);
        $this->insertAssigning($liveClaim, $user, self::DAY);

        $softDeletedClaim = $this->insertClaim(60, deletedAt: self::DAY.' 12:00:00');
        $this->insertAssigning($softDeletedClaim, $user, self::DAY);

        $anotherClaim = $this->insertClaim(25);
        $this->insertAssigning($anotherClaim, $user, self::DAY, deletedAt: self::DAY.' 12:00:00');

        $result = $this->service->build($this->request(['entity' => 'user', 'entity_ids' => [$user]]));

        $this->assertCount(1, $result['details']);
        $this->assertSame(1, $result['details'][0]['assigned_count']);
        $this->assertEquals(40.0, $result['details'][0]['assigned_amount']);
    }

    #[Test]
    public function prefers_payment_amount_when_present(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $claim = $this->insertClaim(999);
        $this->insertAssigning($claim, $user, self::DAY);
        $this->insertPayment($claim, amount: 150);

        $result = $this->service->build($this->request(['entity' => 'user', 'entity_ids' => [$user]]));

        $this->assertSame(1, $result['details'][0]['assigned_count']);
        $this->assertEquals(150.0, $result['details'][0]['assigned_amount']);
    }

    #[Test]
    public function soft_deleted_payment_falls_back_to_claim_amount(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $claim = $this->insertClaim(70);
        $this->insertAssigning($claim, $user, self::DAY);
        $this->insertPayment($claim, amount: 200, deleted: true);

        $result = $this->service->build($this->request(['entity' => 'user', 'entity_ids' => [$user]]));

        $this->assertSame(1, $result['details'][0]['assigned_count']);
        $this->assertEquals(70.0, $result['details'][0]['assigned_amount']);
    }

    #[Test]
    public function excludes_orphan_assignings_without_a_claim(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);

        $claim = $this->insertClaim(80);
        $this->insertAssigning($claim, $user, self::DAY);
        $this->insertAssigning(99999, $user, self::DAY);

        $result = $this->service->build($this->request(['entity' => 'user', 'entity_ids' => [$user]]));

        $this->assertCount(1, $result['details']);
        $this->assertSame(1, $result['details'][0]['assigned_count']);
        $this->assertEquals(80.0, $result['details'][0]['assigned_amount']);
    }

    #[Test]
    public function counts_each_claim_once_when_multiple_assignings_exist(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $claim = $this->insertClaim(100);

        $this->insertAssigning($claim, $user, self::DAY);
        $this->insertAssigning($claim, $user, self::DAY);

        $result = $this->service->build($this->request(['entity' => 'user', 'entity_ids' => [$user]]));

        $this->assertCount(1, $result['details']);
        $this->assertSame(1, $result['details'][0]['assigned_count']);
        $this->assertEquals(100.0, $result['details'][0]['assigned_amount']);
    }

    #[Test]
    public function counts_each_claim_once_across_users_using_latest_assignee(): void
    {
        $alice = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $bob = $this->insertUser('Bob', Roles::EXTERNAL_OFFICE->value);
        $claim = $this->insertClaim(100);

        $this->insertAssigning($claim, $alice, self::DAY);
        $this->insertAssigning($claim, $bob, self::DAY);

        $result = $this->service->build($this->request(['entity' => 'user']));

        $this->assertCount(1, $result['details']);
        $this->assertSame($bob, $result['details'][0]['id']);
        $this->assertSame(1, $result['details'][0]['assigned_count']);
        $this->assertEquals(100.0, $result['details'][0]['assigned_amount']);
        $this->assertSame(1, $result['summary']['total_count']);
        $this->assertEquals(100.0, $result['summary']['total_amount']);
    }

    private function request(array $input = []): Request
    {
        return Request::create('/', 'GET', $input);
    }

    private function channel(array $result, string $name): array
    {
        foreach ($result['details'] as $row) {
            if ($row['name'] === $name) {
                return $row;
            }
        }

        $this->fail("Channel [{$name}] not found");
    }

    private function insertUser(string $name, int $roleId): int
    {
        DB::table('roles')->updateOrInsert(
            ['id' => $roleId],
            ['title' => 'role-'.$roleId, 'created_at' => now(), 'updated_at' => now()]
        );

        return (int) DB::table('users')->insertGetId([
            'name' => $name,
            'email' => strtolower($name).uniqid('', true).'@example.com',
            'role_id' => $roleId,
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertClaim(
        float $amount,
        ?string $deletedAt = null,
        ?string $intimationDate = self::DAY,
        ?string $accidentDate = null
    ): int {
        return (int) DB::table('claims')->insertGetId([
            'amount' => $amount,
            'intimation_date' => $intimationDate,
            'accident_date' => $accidentDate,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => $deletedAt,
        ]);
    }

    private function insertAssigning(
        int $claimId,
        int $userId,
        string $day,
        int $isActive = 1,
        ?string $deletedAt = null
    ): void {
        DB::table('assignings')->insert([
            'claim_id' => $claimId,
            'to' => $userId,
            'assign_date' => $day.' 10:00:00',
            'is_active' => $isActive,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => $deletedAt,
        ]);
    }

    private function insertPayment(int $claimId, float $amount, bool $deleted = false): void
    {
        DB::table('payments')->insert([
            'claim_id' => $claimId,
            'amount' => $amount,
            'paid_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => $deleted ? now() : null,
        ]);
    }
}
