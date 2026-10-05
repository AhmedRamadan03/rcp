<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ClaimStatus;
use App\Enums\Roles;
use App\Services\Dashboard\StatusReportService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StatusReportServiceTest extends TestCase
{
    private const DAY = '2026-06-15';

    private StatusReportService $service;

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
            $table->string('status');
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

        $this->service = new StatusReportService();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function role_mode_returns_exactly_three_channels(): void
    {
        $result = $this->service->build($this->request());

        $this->assertCount(3, $result['details']);
        $this->assertSame(['internal', 'external', 'legal'], array_column($result['details'], 'name'));
        $this->assertSame([1, 2, 3], array_column($result['details'], 'id'));
    }

    #[Test]
    public function empty_data_returns_zero_filled_details_and_summary(): void
    {
        $result = $this->service->build($this->request());

        foreach ($result['details'] as $row) {
            $this->assertSame(0, $row['cleansed_count']);
            $this->assertEquals(0.0, $row['cleansed_amount']);
            $this->assertSame(0, $row['collected_count']);
            $this->assertEquals(0.0, $row['collected_amount']);
            $this->assertSame(0, $row['outstanding_count']);
            $this->assertEquals(0.0, $row['outstanding_amount']);
        }

        $this->assertEquals(0.0, $result['summary']['total_amount']);
        $this->assertSame(0, $result['summary']['total_count']);
    }

    #[Test]
    public function collector_and_senior_collector_map_to_internal(): void
    {
        $collector = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $senior = $this->insertUser('Sam', Roles::SENIOR_COLLECTOR->value);

        $claimA = $this->insertClaim(ClaimStatus::REGISTERED->value, 40, self::DAY);
        $this->insertAssigning($claimA, $collector);

        $claimB = $this->insertClaim(ClaimStatus::REGISTERED->value, 60, self::DAY);
        $this->insertAssigning($claimB, $senior);

        $result = $this->service->build($this->request());
        $internal = $this->channel($result, 'internal');

        $this->assertSame(2, $internal['outstanding_count']);
        $this->assertEquals(100.0, $internal['outstanding_amount']);
        $this->assertSame(0, $this->channel($result, 'external')['outstanding_count']);
        $this->assertSame(0, $this->channel($result, 'legal')['outstanding_count']);
    }

    #[Test]
    public function external_and_legal_channels_are_separated(): void
    {
        $external = $this->insertUser('Bob', Roles::EXTERNAL_OFFICE->value);
        $legal = $this->insertUser('Lara', Roles::LEGAL_DEPARTMENT->value);

        $claimA = $this->insertClaim(ClaimStatus::REGISTERED->value, 25, self::DAY);
        $this->insertAssigning($claimA, $external);

        $claimB = $this->insertClaim(ClaimStatus::CLOSED->value, 75, self::DAY);
        $this->insertAssigning($claimB, $legal);
        $this->insertPayment($claimB, 75, 0);

        $result = $this->service->build($this->request());

        $this->assertEquals(25.0, $this->channel($result, 'external')['outstanding_amount']);
        $this->assertSame(1, $this->channel($result, 'legal')['cleansed_count']);
        $this->assertEquals(75.0, $this->channel($result, 'legal')['cleansed_amount']);
    }

    #[Test]
    public function summary_equals_sum_of_details(): void
    {
        $collector = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $external = $this->insertUser('Bob', Roles::EXTERNAL_OFFICE->value);

        $closed = $this->insertClaim(ClaimStatus::CLOSED->value, 100, self::DAY);
        $this->insertAssigning($closed, $collector);
        $this->insertPayment($closed, 100, 0);

        $collected = $this->insertClaim(ClaimStatus::APPROVED->value, 200, self::DAY);
        $this->insertAssigning($collected, $external);
        $this->insertPayment($collected, 200, 50);

        $result = $this->service->build($this->request());

        $this->assertSummaryMatchesDetails($result);
    }

    #[Test]
    public function user_mode_returns_rows_by_assignee(): void
    {
        $alice = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $bob = $this->insertUser('Bob', Roles::EXTERNAL_OFFICE->value);

        $claimA = $this->insertClaim(ClaimStatus::REGISTERED->value, 40, self::DAY);
        $this->insertAssigning($claimA, $alice);

        $claimB = $this->insertClaim(ClaimStatus::REGISTERED->value, 60, self::DAY);
        $this->insertAssigning($claimB, $bob);

        $result = $this->service->build($this->request([
            'entity' => 'user',
        ]));

        $this->assertCount(2, $result['details']);
        $this->assertSame($bob, $result['details'][0]['id']);
        $this->assertSame('Bob', $result['details'][0]['name']);
        $this->assertEquals(60.0, $result['details'][0]['outstanding_amount']);
        $this->assertSame($alice, $result['details'][1]['id']);
        $this->assertSummaryMatchesDetails($result);
    }

    #[Test]
    public function user_mode_filters_by_entity_ids(): void
    {
        $alice = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $bob = $this->insertUser('Bob', Roles::COLLECTOR->value);

        $claimA = $this->insertClaim(ClaimStatus::REGISTERED->value, 40, self::DAY);
        $this->insertAssigning($claimA, $alice);

        $claimB = $this->insertClaim(ClaimStatus::REGISTERED->value, 60, self::DAY);
        $this->insertAssigning($claimB, $bob);

        $result = $this->service->build($this->request([
            'entity' => 'user',
            'entity_ids' => [$bob],
        ]));

        $this->assertCount(1, $result['details']);
        $this->assertSame($bob, $result['details'][0]['id']);
    }

    #[Test]
    public function user_mode_limits_details_to_ten_rows(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $user = $this->insertUser("User{$i}", Roles::COLLECTOR->value);
            $claim = $this->insertClaim(ClaimStatus::REGISTERED->value, $i * 10, self::DAY);
            $this->insertAssigning($claim, $user);
        }

        $result = $this->service->build($this->request([
            'entity' => 'user',
        ]));

        $this->assertCount(10, $result['details']);
        $this->assertEquals(120.0, $result['details'][0]['outstanding_amount']);
        $this->assertEquals(30.0, $result['details'][9]['outstanding_amount']);
    }

    #[Test]
    public function role_mode_entity_ids_omit_unselected_channels(): void
    {
        $collector = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $external = $this->insertUser('Bob', Roles::EXTERNAL_OFFICE->value);
        $legal = $this->insertUser('Lara', Roles::LEGAL_DEPARTMENT->value);

        $claimA = $this->insertClaim(ClaimStatus::REGISTERED->value, 40, self::DAY);
        $this->insertAssigning($claimA, $collector);

        $claimB = $this->insertClaim(ClaimStatus::REGISTERED->value, 90, self::DAY);
        $this->insertAssigning($claimB, $external);

        $claimC = $this->insertClaim(ClaimStatus::REGISTERED->value, 15, self::DAY);
        $this->insertAssigning($claimC, $legal);

        $result = $this->service->build($this->request([
            'entity_ids' => ['external'],
        ]));

        $this->assertCount(1, $result['details']);
        $this->assertSame(['external'], array_column($result['details'], 'name'));
        $this->assertEquals(90.0, $result['details'][0]['outstanding_amount']);
        $this->assertSame(1, $result['summary']['total_outstanding_count']);
    }

    #[Test]
    public function status_filter_limits_full_response_buckets(): void
    {
        $collector = $this->insertUser('Alice', Roles::COLLECTOR->value);

        $closed = $this->insertClaim(ClaimStatus::CLOSED->value, 100, self::DAY);
        $this->insertAssigning($closed, $collector);

        $collected = $this->insertClaim(ClaimStatus::APPROVED->value, 200, self::DAY);
        $this->insertAssigning($collected, $collector);
        $this->insertPayment($collected, 200, 60);

        $result = $this->service->build($this->request([
            'status' => ['cleansed'],
        ]));

        $internal = $this->channel($result, 'internal');
        $this->assertSame(1, $internal['cleansed_count']);
        $this->assertSame(0, $internal['collected_count']);
        $this->assertSame(1, $result['summary']['total_cleansed_count']);
        $this->assertSame(0, $result['summary']['total_collected_count']);
    }

    #[Test]
    public function role_mode_orders_channels_by_total_amount_desc(): void
    {
        $collector = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $external = $this->insertUser('Bob', Roles::EXTERNAL_OFFICE->value);
        $legal = $this->insertUser('Lara', Roles::LEGAL_DEPARTMENT->value);

        $claimInternal = $this->insertClaim(ClaimStatus::REGISTERED->value, 10, self::DAY);
        $this->insertAssigning($claimInternal, $collector);

        $claimExternal = $this->insertClaim(ClaimStatus::REGISTERED->value, 90, self::DAY);
        $this->insertAssigning($claimExternal, $external);

        $claimLegal = $this->insertClaim(ClaimStatus::REGISTERED->value, 40, self::DAY);
        $this->insertAssigning($claimLegal, $legal);

        $result = $this->service->build($this->request());

        $this->assertSame(['external', 'legal', 'internal'], array_column($result['details'], 'name'));
    }

    #[Test]
    public function unmapped_roles_do_not_create_extra_channels(): void
    {
        $admin = $this->insertUser('Admin', Roles::SUPER_ADMIN->value);
        $claim = $this->insertClaim(ClaimStatus::REGISTERED->value, 999, self::DAY);
        $this->insertAssigning($claim, $admin);

        $result = $this->service->build($this->request());

        $this->assertCount(3, $result['details']);
        $this->assertSame(0, $result['summary']['total_count']);
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

    private function assertSummaryMatchesDetails(array $result): void
    {
        $details = $result['details'];
        $summary = $result['summary'];

        $this->assertEquals(
            array_sum(array_column($details, 'cleansed_amount'))
                + array_sum(array_column($details, 'collected_amount'))
                + array_sum(array_column($details, 'outstanding_amount')),
            $summary['total_amount']
        );
        $this->assertSame(
            array_sum(array_column($details, 'cleansed_count'))
                + array_sum(array_column($details, 'collected_count'))
                + array_sum(array_column($details, 'outstanding_count')),
            $summary['total_count']
        );
        $this->assertEquals(array_sum(array_column($details, 'cleansed_amount')), $summary['total_cleansed_amount']);
        $this->assertSame(array_sum(array_column($details, 'cleansed_count')), $summary['total_cleansed_count']);
        $this->assertEquals(array_sum(array_column($details, 'collected_amount')), $summary['total_collected_amount']);
        $this->assertSame(array_sum(array_column($details, 'collected_count')), $summary['total_collected_count']);
        $this->assertEquals(array_sum(array_column($details, 'outstanding_amount')), $summary['total_outstanding_amount']);
        $this->assertSame(array_sum(array_column($details, 'outstanding_count')), $summary['total_outstanding_count']);
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
        string $status,
        float $amount,
        string $accidentDate,
        ?string $intimationDate = null
    ): int {
        return (int) DB::table('claims')->insertGetId([
            'status' => $status,
            'amount' => $amount,
            'intimation_date' => $intimationDate,
            'accident_date' => $accidentDate,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }

    private function insertAssigning(int $claimId, int $userId): void
    {
        DB::table('assignings')->insert([
            'claim_id' => $claimId,
            'to' => $userId,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }

    private function insertPayment(int $claimId, float $amount, float $paidAmount): void
    {
        DB::table('payments')->insert([
            'claim_id' => $claimId,
            'amount' => $amount,
            'paid_amount' => $paidAmount,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }
}
