<?php

declare(strict_types=1);

return [
    'session_lifetime' => (int) env('SESSION_LIFETIME', 120),
    'session_secure_cookie' => filter_var(env('SESSION_SECURE_COOKIE', false), FILTER_VALIDATE_BOOL),
    'password_algo' => PASSWORD_ARGON2ID,
    'login_max_attempts' => 5,
    'login_lockout_minutes' => 15,
];
