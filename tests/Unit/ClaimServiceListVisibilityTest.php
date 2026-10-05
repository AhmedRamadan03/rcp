<?php

namespace Tests\Unit;

use App\Enums\Roles;
use App\Models\Assigning;
use App\Models\City;
use App\Models\Claim;
use App\Models\Client;
use App\Models\Delegation;
use App\Models\Role;
use App\Models\User;
use App\Services\ClaimService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClaimServiceListVisibilityTest extends TestCase
{
    private int $clientId;

    private int $otherClientId;

    private int $cityId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15 12:00:00');

        config()->set('database.connections.memory_testing.database', ':memory:');
        DB::purge('memory_testing');
        DB::reconnect('memory_testing');
        config()->set('database.default', 'memory_testing');

        Schema::dropIfExists('permissions');
        Schema::dropIfExists('delegations');
        Schema::dropIfExists('assignings');
        Schema::dropIfExists('claims');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('clients');

        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

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
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->timestamps();
        });

        Schema::create('claims', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('city_id')->nullable();
            $table->double('amount', 8, 2)->default(100);
            $table->string('claim_number')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('assignings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('claim_id');
            $table->unsignedBigInteger('to');
            $table->unsignedBigInteger('from')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('delegations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('from');
            $table->unsignedBigInteger('to');
            $table->tinyInteger('is_active')->default(1);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        foreach ([
            Roles::SUPER_ADMIN->value => 'Super admin',
            Roles::COLLECTOR->value => 'Collector',
            Roles::EXTERNAL_OFFICE->value => 'External office',
            Roles::LEGAL_DEPARTMENT->value => 'Legal',
            Roles::SENIOR_COLLECTOR->value => 'Senior collector',
        ] as $id => $title) {
            Role::query()->forceCreate([
                'id' => $id,
                'title' => $title,
            ]);
        }

        $this->clientId = Client::query()->create(['title' => 'Client A'])->id;
        $this->otherClientId = Client::query()->create(['title' => 'Client B'])->id;
        $this->cityId = City::query()->create(['name' => 'Riyadh'])->id;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function restrictedRoleIds(): array
    {
        return [
            'collector' => [Roles::COLLECTOR->value],
            'external office' => [Roles::EXTERNAL_OFFICE->value],
            'legal' => [Roles::LEGAL_DEPARTMENT->value],
        ];
    }

    #[Test]
    #[DataProvider('restrictedRoleIds')]
    public function restricted_role_sees_active_assignment_to_self(int $roleId): void
    {
        $viewer = $this->createUser($roleId);
        $other = $this->createUser(Roles::COLLECTOR->value);
        $assigned = $this->createClaim($viewer);
        $foreign = $this->createClaim($other);
        $this->assign($assigned, $viewer);
        $this->assign($foreign, $other);

        $this->assertListedIds($viewer, [$assigned->id]);
        $this->assertTrue($this->canAccess($viewer, $assigned));
        $this->assertFalse($this->canAccess($viewer, $foreign));
    }

    #[Test]
    #[DataProvider('restrictedRoleIds')]
    public function restricted_role_sees_unassigned_claim_they_created(int $roleId): void
    {
        $viewer = $this->createUser($roleId);
        $draft = $this->createClaim($viewer);
        $otherDraft = $this->createClaim($this->createUser(Roles::COLLECTOR->value));

        $this->assertListedIds($viewer, [$draft->id]);
        $this->assertTrue($this->canAccess($viewer, $draft));
        $this->assertFalse($this->canAccess($viewer, $otherDraft));
    }

    #[Test]
    #[DataProvider('restrictedRoleIds')]
    public function restricted_role_does_not_see_claim_they_created_once_assigned_to_someone_else(int $roleId): void
    {
        $viewer = $this->createUser($roleId);
        $assignee = $this->createUser(Roles::COLLECTOR->value);
        $claim = $this->createClaim($viewer);
        $this->assign($claim, $assignee);

        $this->assertListedIds($viewer, []);
        $this->assertFalse($this->canAccess($viewer, $claim));
    }

    #[Test]
    #[DataProvider('restrictedRoleIds')]
    public function restricted_role_sees_claim_delegated_by_active_assignee(int $roleId): void
    {
        $viewer = $this->createUser($roleId);
        $assignee = $this->createUser(Roles::COLLECTOR->value);
        $claim = $this->createClaim($assignee);
        $this->assign($claim, $assignee);
        $this->delegate($assignee, $viewer, isActive: true);

        $this->assertListedIds($viewer, [$claim->id]);
        $this->assertTrue($this->canAccess($viewer, $claim));
    }

    #[Test]
    #[DataProvider('restrictedRoleIds')]
    public function restricted_role_ignores_inactive_assignment_and_inactive_delegation(int $roleId): void
    {
        $viewer = $this->createUser($roleId);
        $assignee = $this->createUser(Roles::COLLECTOR->value);

        $inactiveAssignment = $this->createClaim($assignee);
        $this->assign($inactiveAssignment, $viewer, isActive: false);

        $inactiveDelegation = $this->createClaim($assignee);
        $this->assign($inactiveDelegation, $assignee);
        $this->delegate($assignee, $viewer, isActive: false);

        $this->assertListedIds($viewer, []);
        $this->assertFalse($this->canAccess($viewer, $inactiveAssignment));
        $this->assertFalse($this->canAccess($viewer, $inactiveDelegation));
    }

    #[Test]
    #[DataProvider('restrictedRoleIds')]
    public function restricted_role_ignores_delegation_from_user_who_is_not_the_active_assignee(int $roleId): void
    {
        $viewer = $this->createUser($roleId);
        $assignee = $this->createUser(Roles::COLLECTOR->value);
        $other = $this->createUser(Roles::COLLECTOR->value);
        $claim = $this->createClaim($assignee);
        $this->assign($claim, $assignee);
        $this->delegate($other, $viewer, isActive: true);

        $this->assertListedIds($viewer, []);
        $this->assertFalse($this->canAccess($viewer, $claim));
    }

    #[Test]
    public function collector_id_filter_does_not_expand_visibility(): void
    {
        $viewer = $this->createUser(Roles::COLLECTOR->value);
        $other = $this->createUser(Roles::COLLECTOR->value);
        $mine = $this->createClaim($viewer);
        $theirs = $this->createClaim($other);
        $this->assign($mine, $viewer);
        $this->assign($theirs, $other);

        $this->assertListedIds($viewer, [$mine->id]);
        $this->assertListedIds($viewer, [], ['collector_id' => $other->id]);
        $this->assertFalse($this->canAccess($viewer, $theirs));
    }

    #[Test]
    public function legal_cannot_open_unrelated_unassigned_claim(): void
    {
        $legal = $this->createUser(Roles::LEGAL_DEPARTMENT->value);
        $strangerClaim = $this->createClaim($this->createUser(Roles::COLLECTOR->value));

        $this->assertListedIds($legal, []);
        $this->assertFalse($this->canAccess($legal, $strangerClaim));
    }

    #[Test]
    public function senior_collector_sees_self_and_subordinate_assignments_and_delegations(): void
    {
        $senior = $this->createUser(Roles::SENIOR_COLLECTOR->value);
        $subordinate = $this->createUser(Roles::COLLECTOR->value, supervisorId: $senior->id);
        $delegatingAssignee = $this->createUser(Roles::COLLECTOR->value);
        $unrelatedAssignee = $this->createUser(Roles::COLLECTOR->value);

        $ownAssigned = $this->createClaim($senior);
        $this->assign($ownAssigned, $senior);

        $subAssigned = $this->createClaim($subordinate);
        $this->assign($subAssigned, $subordinate);

        $delegatedToSub = $this->createClaim($delegatingAssignee);
        $this->assign($delegatedToSub, $delegatingAssignee);
        $this->delegate($delegatingAssignee, $subordinate, isActive: true);

        $foreign = $this->createClaim($unrelatedAssignee);
        $this->assign($foreign, $unrelatedAssignee);

        $this->assertListedIds($senior, [$ownAssigned->id, $subAssigned->id, $delegatedToSub->id]);
        $this->assertTrue($this->canAccess($senior, $ownAssigned));
        $this->assertTrue($this->canAccess($senior, $subAssigned));
        $this->assertTrue($this->canAccess($senior, $delegatedToSub));
        $this->assertFalse($this->canAccess($senior, $foreign));
    }

    #[Test]
    public function senior_collector_sees_own_unassigned_created_claim_but_not_subordinate_drafts(): void
    {
        $senior = $this->createUser(Roles::SENIOR_COLLECTOR->value);
        $subordinate = $this->createUser(Roles::COLLECTOR->value, supervisorId: $senior->id);
        $ownDraft = $this->createClaim($senior);
        $subDraft = $this->createClaim($subordinate);

        $this->assertListedIds($senior, [$ownDraft->id]);
        $this->assertTrue($this->canAccess($senior, $ownDraft));
        $this->assertFalse($this->canAccess($senior, $subDraft));
    }

    #[Test]
    public function super_admin_sees_other_assignee_claims_in_the_same_client_only(): void
    {
        $admin = $this->createUser(Roles::SUPER_ADMIN->value);
        $collector = $this->createUser(Roles::COLLECTOR->value);
        $sameClient = $this->createClaim($collector);
        $this->assign($sameClient, $collector);

        $otherClientUser = $this->createUser(Roles::COLLECTOR->value, clientId: $this->otherClientId);
        $otherClientClaim = $this->createClaim($otherClientUser, $this->otherClientId);
        $this->assign($otherClientClaim, $otherClientUser);

        $this->assertListedIds($admin, [$sameClient->id]);
        $this->assertTrue($this->canAccess($admin, $sameClient));
        $this->assertTrue($this->canAccess($admin, $otherClientClaim));
    }

    #[Test]
    public function active_flag_does_not_override_expired_delegation_dates(): void
    {
        $viewer = $this->createUser(Roles::COLLECTOR->value);
        $assignee = $this->createUser(Roles::COLLECTOR->value);
        $claim = $this->createClaim($assignee);
        $this->assign($claim, $assignee);
        $this->delegate(
            $assignee,
            $viewer,
            isActive: true,
            startDate: '2020-01-01 00:00:00',
            endDate: '2020-01-02 00:00:00',
        );

        $this->assertListedIds($viewer, []);
        $this->assertFalse($this->canAccess($viewer, $claim));
    }

    #[Test]
    public function active_flag_does_not_override_future_delegation_dates(): void
    {
        $viewer = $this->createUser(Roles::COLLECTOR->value);
        $assignee = $this->createUser(Roles::COLLECTOR->value);
        $claim = $this->createClaim($assignee);
        $this->assign($claim, $assignee);
        $this->delegate(
            $assignee,
            $viewer,
            isActive: true,
            startDate: '2027-01-01 00:00:00',
            endDate: '2027-12-31 00:00:00',
        );

        $this->assertListedIds($viewer, []);
        $this->assertFalse($this->canAccess($viewer, $claim));
    }

    #[Test]
    public function active_open_ended_delegation_grants_access(): void
    {
        $viewer = $this->createUser(Roles::COLLECTOR->value);
        $assignee = $this->createUser(Roles::COLLECTOR->value);
        $claim = $this->createClaim($assignee);
        $this->assign($claim, $assignee);
        $this->delegate(
            $assignee,
            $viewer,
            isActive: true,
            startDate: '2026-01-01 00:00:00',
            endDate: null,
        );

        $this->assertListedIds($viewer, [$claim->id]);
        $this->assertTrue($this->canAccess($viewer, $claim));
    }

    #[Test]
    public function delegation_window_includes_exact_start_and_end_instant(): void
    {
        $viewer = $this->createUser(Roles::COLLECTOR->value);
        $assignee = $this->createUser(Roles::COLLECTOR->value);
        $claim = $this->createClaim($assignee);
        $this->assign($claim, $assignee);
        $this->delegate(
            $assignee,
            $viewer,
            isActive: true,
            startDate: '2026-06-15 12:00:00',
            endDate: '2026-06-15 12:00:00',
        );

        $this->assertListedIds($viewer, [$claim->id]);
        $this->assertTrue($this->canAccess($viewer, $claim));
    }

    #[Test]
    public function helper_denies_null_claim_and_unauthenticated_user(): void
    {
        $this->assertFalse(can_user_access_claim(null));

        $claim = $this->createClaim($this->createUser(Roles::COLLECTOR->value));
        Auth::logout();
        $this->assertFalse(can_user_access_claim($claim));
    }

    private function assertListedIds(User $user, array $expectedIds, array $filters = []): void
    {
        $this->actingAs($user);

        $ids = (new ClaimService)
            ->list($filters, false, 10, true, $user)
            ->pluck('id')
            ->all();

        sort($ids);
        $expected = $expectedIds;
        sort($expected);

        $this->assertSame($expected, $ids);
    }

    private function canAccess(User $user, Claim $claim): bool
    {
        $this->actingAs($user);

        return can_user_access_claim($claim->fresh());
    }

    private function createUser(int $roleId, ?int $clientId = null, ?int $supervisorId = null): User
    {
        return User::query()->create([
            'name' => 'User '.$roleId.' '.uniqid(),
            'email' => uniqid('user', true).'@example.test',
            'password' => 'password',
            'role_id' => $roleId,
            'client_id' => $clientId ?? $this->clientId,
            'supervisor_id' => $supervisorId,
        ]);
    }

    private function createClaim(User $creator, ?int $clientId = null): Claim
    {
        return Claim::query()->create([
            'client_id' => $clientId ?? $this->clientId,
            'user_id' => $creator->id,
            'city_id' => $this->cityId,
            'amount' => 150,
            'claim_number' => 'C-'.uniqid(),
            'status' => 'registered',
        ]);
    }

    private function assign(Claim $claim, User $to, bool $isActive = true): Assigning
    {
        return Assigning::query()->create([
            'claim_id' => $claim->id,
            'to' => $to->id,
            'from' => $to->id,
            'is_active' => $isActive ? 1 : 0,
        ]);
    }

    private function delegate(
        User $from,
        User $to,
        bool $isActive,
        ?string $startDate = '2026-01-01 00:00:00',
        ?string $endDate = '2026-12-31 00:00:00',
    ): Delegation {
        return Delegation::query()->create([
            'from' => $from->id,
            'to' => $to->id,
            'is_active' => $isActive ? 1 : 0,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }
}
