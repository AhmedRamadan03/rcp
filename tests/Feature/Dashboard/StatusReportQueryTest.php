<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ClaimStatus;
use App\Enums\Roles;
use App\Services\Dashboard\StatusReport\StatusReportQuery;
use App\Services\Dashboard\StatusReport\StatusReportChannels;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StatusReportQueryTest extends TestCase
{
    private const DAY = '2026-06-15';

    private StatusReportQuery $query;

    private array $defaultFilters;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->query = new StatusReportQuery();
        $this->defaultFilters = [
            'from' => '2026-01-01',
            'to' => '2026-12-31',
            'entity' => 'role',
            'entity_ids' => [],
            'statuses' => [],
        ];
    }

    #[Test]
    public function aggregates_cleansed_collected_and_outstanding_buckets(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);

        $closed = $this->insertClaim(ClaimStatus::CLOSED->value, 100, self::DAY);
        $this->insertAssigning($closed, $user);
        $this->insertPayment($closed, amount: 100, paidAmount: 0);

        $collected = $this->insertClaim(ClaimStatus::APPROVED->value, 200, self::DAY);
        $this->insertAssigning($collected, $user);
        $this->insertPayment($collected, amount: 200, paidAmount: 50);

        $outstanding = $this->insertClaim(ClaimStatus::REGISTERED->value, 75, self::DAY);
        $this->insertAssigning($outstanding, $user);

        $metrics = $this->query->aggregateForRoles(StatusReportChannels::internal(), $this->defaultFilters);

        $this->assertSame(1, $metrics['cleansed_count']);
        $this->assertEquals(100.0, $metrics['cleansed_amount']);
        $this->assertSame(1, $metrics['collected_count']);
        $this->assertEquals(50.0, $metrics['collected_amount']);
        $this->assertSame(1, $metrics['outstanding_count']);
        // 75 unpaid claim + 150 residual of the 200/50 collected claim
        $this->assertEquals(225.0, $metrics['outstanding_amount']);
    }

    #[Test]
    public function closed_with_paid_amount_is_cleansed_first(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $claim = $this->insertClaim(ClaimStatus::CLOSED->value, 100, self::DAY);
        $this->insertAssigning($claim, $user);
        $this->insertPayment($claim, amount: 100, paidAmount: 40);

        $metrics = $this->query->aggregateForRoles(StatusReportChannels::internal(), $this->defaultFilters);

        $this->assertSame(1, $metrics['cleansed_count']);
        $this->assertEquals(100.0, $metrics['cleansed_amount']);
        $this->assertSame(0, $metrics['collected_count']);
        $this->assertEquals(0.0, $metrics['collected_amount']);
        $this->assertSame(0, $metrics['outstanding_count']);
        $this->assertEquals(0.0, $metrics['outstanding_amount']);
    }

    #[Test]
    public function partially_paid_claim_contributes_residual_to_outstanding_amount(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $claim = $this->insertClaim(ClaimStatus::APPROVED->value, 200, self::DAY);
        $this->insertAssigning($claim, $user);
        $this->insertPayment($claim, amount: 200, paidAmount: 50);

        $metrics = $this->query->aggregateForRoles(StatusReportChannels::internal(), $this->defaultFilters);

        $this->assertSame(0, $metrics['cleansed_count']);
        $this->assertSame(1, $metrics['collected_count']);
        $this->assertEquals(50.0, $metrics['collected_amount']);
        $this->assertSame(0, $metrics['outstanding_count']);
        $this->assertEquals(150.0, $metrics['outstanding_amount']);
    }

    #[Test]
    public function overpaid_claim_yields_zero_outstanding_amount(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $claim = $this->insertClaim(ClaimStatus::APPROVED->value, 100, self::DAY);
        $this->insertAssigning($claim, $user);
        $this->insertPayment($claim, amount: 100, paidAmount: 150);

        $metrics = $this->query->aggregateForRoles(StatusReportChannels::internal(), $this->defaultFilters);

        $this->assertSame(1, $metrics['collected_count']);
        $this->assertEquals(150.0, $metrics['collected_amount']);
        $this->assertSame(0, $metrics['outstanding_count']);
        $this->assertEquals(0.0, $metrics['outstanding_amount']);
    }

    #[Test]
    public function rejected_status_counts_as_cleansed(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $claim = $this->insertClaim(ClaimStatus::REJECTED->value, 120, self::DAY);
        $this->insertAssigning($claim, $user);
        $this->insertPayment($claim, amount: 120, paidAmount: 0);

        $metrics = $this->query->aggregateForRoles(StatusReportChannels::internal(), $this->defaultFilters);

        $this->assertSame(1, $metrics['cleansed_count']);
        $this->assertEquals(120.0, $metrics['cleansed_amount']);
    }

    #[Test]
    public function cleansed_amount_uses_payment_amount_when_present(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $claim = $this->insertClaim(ClaimStatus::CLOSED->value, 999, self::DAY);
        $this->insertAssigning($claim, $user);
        $this->insertPayment($claim, amount: 150, paidAmount: 0);

        $metrics = $this->query->aggregateForRoles(StatusReportChannels::internal(), $this->defaultFilters);

        $this->assertEquals(150.0, $metrics['cleansed_amount']);
    }

    #[Test]
    public function outstanding_amount_falls_back_to_claim_amount_without_payment(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $claim = $this->insertClaim(ClaimStatus::REGISTERED->value, 80, self::DAY);
        $this->insertAssigning($claim, $user);

        $metrics = $this->query->aggregateForRoles(StatusReportChannels::internal(), $this->defaultFilters);

        $this->assertEquals(80.0, $metrics['outstanding_amount']);
    }

    #[Test]
    public function excludes_claims_outside_date_range(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);

        $inside = $this->insertClaim(ClaimStatus::REGISTERED->value, 50, '2026-06-15');
        $this->insertAssigning($inside, $user);

        $outside = $this->insertClaim(ClaimStatus::REGISTERED->value, 90, '2025-06-15');
        $this->insertAssigning($outside, $user);

        $metrics = $this->query->aggregateForRoles(StatusReportChannels::internal(), $this->defaultFilters);

        $this->assertSame(1, $metrics['outstanding_count']);
        $this->assertEquals(50.0, $metrics['outstanding_amount']);
    }

    #[Test]
    public function prefers_intimation_date_over_accident_date_for_range(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);

        $included = $this->insertClaim(
            ClaimStatus::REGISTERED->value,
            60,
            '2025-01-01',
            intimationDate: '2026-06-15'
        );
        $this->insertAssigning($included, $user);

        $excluded = $this->insertClaim(
            ClaimStatus::REGISTERED->value,
            80,
            '2026-06-15',
            intimationDate: '2025-01-01'
        );
        $this->insertAssigning($excluded, $user);

        $metrics = $this->query->aggregateForRoles(StatusReportChannels::internal(), $this->defaultFilters);

        $this->assertSame(1, $metrics['outstanding_count']);
        $this->assertEquals(60.0, $metrics['outstanding_amount']);
    }

    #[Test]
    public function orders_users_by_total_amount_desc(): void
    {
        $alice = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $bob = $this->insertUser('Bob', Roles::COLLECTOR->value);

        $claimA = $this->insertClaim(ClaimStatus::REGISTERED->value, 20, self::DAY);
        $this->insertAssigning($claimA, $alice);

        $claimB = $this->insertClaim(ClaimStatus::REGISTERED->value, 80, self::DAY);
        $this->insertAssigning($claimB, $bob);

        $rows = $this->query->aggregateForUsers($this->defaultFilters);

        $this->assertSame($bob, $rows[0]['id']);
        $this->assertSame($alice, $rows[1]['id']);
        $this->assertEquals(80.0, $rows[0]['outstanding_amount']);
        $this->assertEquals(20.0, $rows[1]['outstanding_amount']);
    }

    #[Test]
    public function excludes_inactive_assignings(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $claim = $this->insertClaim(ClaimStatus::REGISTERED->value, 50, self::DAY);
        $this->insertAssigning($claim, $user, isActive: 0);

        $metrics = $this->query->aggregateForRoles(StatusReportChannels::internal(), $this->defaultFilters);

        $this->assertSame(0, $metrics['outstanding_count']);
    }

    #[Test]
    public function counts_each_claim_once_using_latest_active_assigning(): void
    {
        $alice = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $bob = $this->insertUser('Bob', Roles::EXTERNAL_OFFICE->value);
        $claim = $this->insertClaim(ClaimStatus::REGISTERED->value, 100, self::DAY);

        $this->insertAssigning($claim, $alice);
        $this->insertAssigning($claim, $bob);

        $internal = $this->query->aggregateForRoles(StatusReportChannels::internal(), $this->defaultFilters);
        $external = $this->query->aggregateForRoles(StatusReportChannels::external(), $this->defaultFilters);

        $this->assertSame(0, $internal['outstanding_count']);
        $this->assertEquals(0.0, $internal['outstanding_amount']);
        $this->assertSame(1, $external['outstanding_count']);
        $this->assertEquals(100.0, $external['outstanding_amount']);
    }

    #[Test]
    public function excludes_soft_deleted_claims(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $claim = $this->insertClaim(ClaimStatus::REGISTERED->value, 50, self::DAY, deleted: true);
        $this->insertAssigning($claim, $user);

        $metrics = $this->query->aggregateForRoles(StatusReportChannels::internal(), $this->defaultFilters);

        $this->assertSame(0, $metrics['outstanding_count']);
    }

    #[Test]
    public function soft_deleted_payment_falls_back_to_claim_amount(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $claim = $this->insertClaim(ClaimStatus::CLOSED->value, 70, self::DAY);
        $this->insertAssigning($claim, $user);
        $this->insertPayment($claim, amount: 200, paidAmount: 0, deleted: true);

        $metrics = $this->query->aggregateForRoles(StatusReportChannels::internal(), $this->defaultFilters);

        $this->assertEquals(70.0, $metrics['cleansed_amount']);
    }

    #[Test]
    public function empty_role_ids_return_zero_metrics(): void
    {
        $metrics = $this->query->aggregateForRoles([], $this->defaultFilters);

        $this->assertSame(0, $metrics['cleansed_count']);
        $this->assertEquals(0.0, $metrics['collected_amount']);
        $this->assertSame(0, $metrics['outstanding_count']);
    }

    #[Test]
    public function paid_amount_zero_is_not_collected(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $claim = $this->insertClaim(ClaimStatus::APPROVED->value, 100, self::DAY);
        $this->insertAssigning($claim, $user);
        $this->insertPayment($claim, amount: 100, paidAmount: 0);

        $metrics = $this->query->aggregateForRoles(StatusReportChannels::internal(), $this->defaultFilters);

        $this->assertSame(0, $metrics['collected_count']);
        $this->assertSame(1, $metrics['outstanding_count']);
    }

    #[Test]
    public function status_filter_limits_to_selected_buckets(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);

        $closed = $this->insertClaim(ClaimStatus::CLOSED->value, 100, self::DAY);
        $this->insertAssigning($closed, $user);

        $collected = $this->insertClaim(ClaimStatus::APPROVED->value, 200, self::DAY);
        $this->insertAssigning($collected, $user);
        $this->insertPayment($collected, amount: 200, paidAmount: 60);

        $filters = [...$this->defaultFilters, 'statuses' => ['cleansed']];
        $metrics = $this->query->aggregateForRoles(StatusReportChannels::internal(), $filters);

        $this->assertSame(1, $metrics['cleansed_count']);
        $this->assertSame(0, $metrics['collected_count']);
        $this->assertSame(0, $metrics['outstanding_count']);
    }

    #[Test]
    public function aggregate_for_users_groups_by_assignee(): void
    {
        $alice = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $bob = $this->insertUser('Bob', Roles::EXTERNAL_OFFICE->value);

        $claimA = $this->insertClaim(ClaimStatus::REGISTERED->value, 40, self::DAY);
        $this->insertAssigning($claimA, $alice);

        $claimB = $this->insertClaim(ClaimStatus::REGISTERED->value, 60, self::DAY);
        $this->insertAssigning($claimB, $bob);

        $rows = $this->query->aggregateForUsers($this->defaultFilters);

        $this->assertCount(2, $rows);
        $this->assertSame($bob, $rows[0]['id']);
        $this->assertSame('Bob', $rows[0]['name']);
        $this->assertEquals(60.0, $rows[0]['outstanding_amount']);
        $this->assertSame($alice, $rows[1]['id']);
        $this->assertEquals(40.0, $rows[1]['outstanding_amount']);
    }

    #[Test]
    public function aggregate_for_users_filters_by_entity_ids(): void
    {
        $alice = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $bob = $this->insertUser('Bob', Roles::COLLECTOR->value);

        $claimA = $this->insertClaim(ClaimStatus::REGISTERED->value, 40, self::DAY);
        $this->insertAssigning($claimA, $alice);

        $claimB = $this->insertClaim(ClaimStatus::REGISTERED->value, 60, self::DAY);
        $this->insertAssigning($claimB, $bob);

        $rows = $this->query->aggregateForUsers([
            ...$this->defaultFilters,
            'entity_ids' => [$bob],
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame($bob, $rows[0]['id']);
    }

    #[Test]
    public function only_matching_role_ids_are_included(): void
    {
        $collector = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $external = $this->insertUser('Bob', Roles::EXTERNAL_OFFICE->value);

        $claimA = $this->insertClaim(ClaimStatus::REGISTERED->value, 40, self::DAY);
        $this->insertAssigning($claimA, $collector);

        $claimB = $this->insertClaim(ClaimStatus::REGISTERED->value, 90, self::DAY);
        $this->insertAssigning($claimB, $external);

        $metrics = $this->query->aggregateForRoles(StatusReportChannels::external(), $this->defaultFilters);

        $this->assertSame(1, $metrics['outstanding_count']);
        $this->assertEquals(90.0, $metrics['outstanding_amount']);
    }

    #[Test]
    public function ignores_malicious_status_values_instead_of_injecting_them(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);

        $closed = $this->insertClaim(ClaimStatus::CLOSED->value, 100, self::DAY);
        $this->insertAssigning($closed, $user);

        $outstanding = $this->insertClaim(ClaimStatus::REGISTERED->value, 50, self::DAY);
        $this->insertAssigning($outstanding, $user);

        $metrics = $this->query->aggregateForRoles(StatusReportChannels::internal(), [
            ...$this->defaultFilters,
            'statuses' => ["cleansed') OR 1=1 --", 'collected'],
        ]);

        // Malicious value dropped; only allowlisted "collected" would apply — none collected here → all zeros.
        $this->assertSame(0, $metrics['cleansed_count']);
        $this->assertSame(0, $metrics['collected_count']);
        $this->assertSame(0, $metrics['outstanding_count']);
    }

    #[Test]
    public function non_numeric_role_ids_are_ignored(): void
    {
        $user = $this->insertUser('Alice', Roles::COLLECTOR->value);
        $claim = $this->insertClaim(ClaimStatus::REGISTERED->value, 50, self::DAY);
        $this->insertAssigning($claim, $user);

        $metrics = $this->query->aggregateForRoles(
            ["3; DROP TABLE users;--", 'not-a-number'],
            $this->defaultFilters
        );

        $this->assertSame(0, $metrics['outstanding_count']);
    }

    private function insertUser(string $name, int $roleId): int
    {
        DB::table('roles')->updateOrInsert(
            ['id' => $roleId],
            ['title' => 'role-'.$roleId, 'created_at' => now(), 'updated_at' => now()]
        );

        return (int) DB::table('users')->insertGetId([
            'name' => $name,
            'email' => strtolower($name).'@example.com',
            'role_id' => $roleId,
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertClaim(
        string $status,
        float $amount,
        ?string $accidentDate,
        bool $deleted = false,
        ?string $intimationDate = null
    ): int {
        return (int) DB::table('claims')->insertGetId([
            'status' => $status,
            'amount' => $amount,
            'intimation_date' => $intimationDate,
            'accident_date' => $accidentDate,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => $deleted ? now() : null,
        ]);
    }

    private function insertAssigning(int $claimId, int $userId, int $isActive = 1): void
    {
        DB::table('assignings')->insert([
            'claim_id' => $claimId,
            'to' => $userId,
            'is_active' => $isActive,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }

    private function insertPayment(int $claimId, float $amount, float $paidAmount, bool $deleted = false): void
    {
        DB::table('payments')->insert([
            'claim_id' => $claimId,
            'amount' => $amount,
            'paid_amount' => $paidAmount,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => $deleted ? now() : null,
        ]);
    }
}
