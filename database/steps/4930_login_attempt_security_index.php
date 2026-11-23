<?php

declare(strict_types=1);

$loginAttemptSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$loginAttemptSchema->indexExists('login_attempts', 'idx_login_attempt_success_created')) {
    $loginAttemptSchema->runSqlFile($root . '/migrations/2026_11_24_110_login_attempt_security_index.sql');
}
