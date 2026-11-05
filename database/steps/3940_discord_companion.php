<?php

declare(strict_types=1);

$migrateCompanion = require $root . '/bootstrap/discord_companion_migration.php';
$migrateCompanion($pdo);
$migrationEnsurePdo();
