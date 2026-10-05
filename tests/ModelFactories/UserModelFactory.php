<?php

namespace Tests\ModelFactories;

use App\Models\User;

class UserModelFactory
{
    public $collectors = 0;

    public function collectors(int $count)
    {
        $this->collectors = $count;

        return $this;
    }

    public function create()
    {
        $supervisor = User::factory()->create([

        ]);

        return User::factory()->count($this->collectors)->create([
            'supervisor_id' => $supervisor->id,
        ]);
    }
}
