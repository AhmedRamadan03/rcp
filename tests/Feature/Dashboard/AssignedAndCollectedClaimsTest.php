<?php

namespace Tests\Feature\Dashboard;

use App\Services\Dashboard\AssignedClaimService;
use App\Services\Dashboard\CollectedClaimAmount;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class AssignedAndCollectedClaimsTest extends TestCase
{
    private const DAY = '2024-06-12';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.memory_testing.database', ':memory:');
        DB::purge('memory_testing');
        DB::reconnect('memory_testing');
        config()->set('database.default', 'memory_testing');

        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payment_links');
        Schema::dropIfExists('assignings');
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

        Schema::create('assignings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('to');
            $table->dateTime('assign_date');
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
        });

        Schema::create('payment_links', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('collector_id')->nullable();
            $table->string('send_status')->default('not sent');
            $table->timestamps();
        });

        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('link_id');
            $table->double('amount', 8, 2);
            $table->timestamps();
        });
    }

    #[Test]
    public function assigned_claims_by_user_returns_top_users_with_assign_counts(): void
    {
        $collectorRoleId = $this->insertRole('Collector');
        $userA = $this->insertUser('Alice', $collectorRoleId);
        $userB = $this->insertUser('Bob', $collectorRoleId);

        $this->insertAssigning($userA, self::DAY);
        $this->insertAssigning($userA, self::DAY);
        $this->insertAssigning($userB, self::DAY);

        $payload = $this->dayPayload(
            (new AssignedClaimService())->getAssignedClaims('user', 'day', [], self::DAY)
        );

        $this->assertSame($userA, $payload[0]['id']);
        $this->assertSame('Alice', $payload[0]['name']);
        $this->assertEquals(2, $payload[0]['total']);
        $this->assertSame($userB, $payload[1]['id']);
        $this->assertEquals(1, $payload[1]['total']);
    }

    #[Test]
    public function assigned_claims_by_user_filters_by_user_ids(): void
    {
        $collectorRoleId = $this->insertRole('Collector');
        $userA = $this->insertUser('Alice', $collectorRoleId);
        $userB = $this->insertUser('Bob', $collectorRoleId);

        $this->insertAssigning($userA, self::DAY);
        $this->insertAssigning($userA, self::DAY);
        $this->insertAssigning($userB, self::DAY);

        $payload = $this->dayPayload(
            (new AssignedClaimService())->getAssignedClaims('user', 'day', [$userB], self::DAY)
        );

        $this->assertCount(1, $payload);
        $this->assertSame($userB, $payload[0]['id']);
        $this->assertEquals(1, $payload[0]['total']);
    }

    #[Test]
    public function assigned_claims_by_role_aggregates_assign_counts_using_title_as_name(): void
    {
        $collectors = $this->insertRole('Collectors');
        $externals = $this->insertRole('Externals');

        $userA = $this->insertUser('Alice', $collectors);
        $userB = $this->insertUser('Bob', $collectors);
        $userC = $this->insertUser('Carol', $externals);

        $this->insertAssigning($userA, self::DAY);
        $this->insertAssigning($userB, self::DAY);
        $this->insertAssigning($userC, self::DAY);

        $payload = $this->dayPayload(
            (new AssignedClaimService())->getAssignedClaims('role', 'day', [], self::DAY)
        );

        $this->assertSame($collectors, $payload[0]['id']);
        $this->assertSame('Collectors', $payload[0]['name']);
        $this->assertEquals(2, $payload[0]['total']);
        $this->assertSame($externals, $payload[1]['id']);
        $this->assertEquals(1, $payload[1]['total']);
    }

    #[Test]
    public function assigned_claims_by_role_filters_by_role_ids(): void
    {
        $collectors = $this->insertRole('Collectors');
        $externals = $this->insertRole('Externals');

        $userA = $this->insertUser('Alice', $collectors);
        $userC = $this->insertUser('Carol', $externals);

        $this->insertAssigning($userA, self::DAY);
        $this->insertAssigning($userC, self::DAY);

        $payload = $this->dayPayload(
            (new AssignedClaimService())->getAssignedClaims('role', 'day', [$externals], self::DAY)
        );

        $this->assertCount(1, $payload);
        $this->assertSame($externals, $payload[0]['id']);
        $this->assertSame('Externals', $payload[0]['name']);
    }

    #[Test]
    public function collected_amount_by_user_returns_summed_paid_amounts(): void
    {
        $collectorRoleId = $this->insertRole('Collector');
        $userA = $this->insertUser('Alice', $collectorRoleId);
        $userB = $this->insertUser('Bob', $collectorRoleId);

        $this->insertPaidAmount($userA, 100, self::DAY);
        $this->insertPaidAmount($userA, 50, self::DAY);
        $this->insertPaidAmount($userB, 40, self::DAY);

        $payload = $this->dayPayload(
            (new CollectedClaimAmount())->getAssignedClaims('user', 'day', [], self::DAY)
        );

        $this->assertSame($userA, $payload[0]['id']);
        $this->assertEquals(150, (float) $payload[0]['total']);
        $this->assertSame($userB, $payload[1]['id']);
        $this->assertEquals(40, (float) $payload[1]['total']);
    }

    #[Test]
    public function collected_amount_get_by_year_accepts_entity_signature(): void
    {
        $method = new ReflectionMethod(CollectedClaimAmount::class, 'getByYear');
        $parameters = $method->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertSame('entity', $parameters[0]->getName());
        $this->assertSame('string', $parameters[0]->getType()?->getName());
        $this->assertSame('entityIds', $parameters[1]->getName());
        $this->assertSame('array', $parameters[1]->getType()?->getName());
    }

    #[Test]
    public function collected_amount_by_role_sums_paid_amounts_not_assign_counts(): void
    {
        $collectors = $this->insertRole('Collectors');
        $externals = $this->insertRole('Externals');

        $userA = $this->insertUser('Alice', $collectors);
        $userB = $this->insertUser('Bob', $collectors);
        $userC = $this->insertUser('Carol', $externals);

        $this->insertPaidAmount($userA, 100, self::DAY);
        $this->insertPaidAmount($userB, 25, self::DAY);
        $this->insertPaidAmount($userC, 200, self::DAY);

        // Assignings should not affect collected-amount totals.
        $this->insertAssigning($userA, self::DAY);
        $this->insertAssigning($userA, self::DAY);
        $this->insertAssigning($userA, self::DAY);

        $payload = $this->dayPayload(
            (new CollectedClaimAmount())->getAssignedClaims('role', 'day', [], self::DAY)
        );

        $this->assertSame($externals, $payload[0]['id']);
        $this->assertSame('Externals', $payload[0]['name']);
        $this->assertEquals(200, (float) $payload[0]['total']);
        $this->assertSame($collectors, $payload[1]['id']);
        $this->assertEquals(125, (float) $payload[1]['total']);
    }

    #[Test]
    public function collected_amount_by_role_filters_by_role_ids(): void
    {
        $collectors = $this->insertRole('Collectors');
        $externals = $this->insertRole('Externals');

        $userA = $this->insertUser('Alice', $collectors);
        $userC = $this->insertUser('Carol', $externals);

        $this->insertPaidAmount($userA, 100, self::DAY);
        $this->insertPaidAmount($userC, 200, self::DAY);

        $payload = $this->dayPayload(
            (new CollectedClaimAmount())->getAssignedClaims('role', 'day', [$collectors], self::DAY)
        );

        $this->assertCount(1, $payload);
        $this->assertSame($collectors, $payload[0]['id']);
        $this->assertEquals(100, (float) $payload[0]['total']);
    }

    private function dayPayload($response): array
    {
        $dayName = Carbon::parse(self::DAY)->format('l');
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey($dayName, $data['data']);

        return array_values($data['data'][$dayName]);
    }

    private function insertRole(string $title): int
    {
        return (int) DB::table('roles')->insertGetId([
            'title' => $title,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertUser(string $name, int $roleId): int
    {
        return (int) DB::table('users')->insertGetId([
            'name' => $name,
            'email' => strtolower($name).'@example.com',
            'role_id' => $roleId,
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertAssigning(int $userId, string $day): void
    {
        DB::table('assignings')->insert([
            'to' => $userId,
            'assign_date' => $day.' 10:00:00',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertPaidAmount(int $collectorId, float $amount, string $day): void
    {
        $linkId = DB::table('payment_links')->insertGetId([
            'collector_id' => $collectorId,
            'send_status' => 'paid',
            'created_at' => $day.' 10:00:00',
            'updated_at' => $day.' 10:00:00',
        ]);

        DB::table('payment_transactions')->insert([
            'link_id' => $linkId,
            'amount' => $amount,
            'created_at' => $day.' 10:00:00',
            'updated_at' => $day.' 10:00:00',
        ]);
    }
}
