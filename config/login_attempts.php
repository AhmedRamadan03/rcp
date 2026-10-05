<?php

return [
    'login_max_attempts_per_ip' => env('LOGIN_MAX_ATTEMPTS_PER_IP', 5),


    'lock_durations_minutes' => [
        1 => 5,
        2 => 10,
    ],

    'login_lock_duration_default_minutes' => env('LOGIN_LOCK_DURATION_DEFAULT_MINUTES', 60),
];