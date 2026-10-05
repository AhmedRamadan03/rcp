<?php

namespace Tests\Unit;

use Facade\Tests\ModelFactories\UserModelFactory;
use PhpOffice\PhpSpreadsheet\Style\Supervisor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TargetTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    #[Test]
    public function supervisor_can_create_target_for_his_colloctors(): void
    {
        $collector = UserModelFactory::create();

        $supervisor = $collector->supervisor;

        $this->assertEquals($supervisor->id, $collector->supervisor_id);

        //        $this->assertTrue($collectors->where('supervisor_id', $supervisor->id)->isEmpty());
    }
}
