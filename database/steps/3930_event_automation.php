<?php

declare(strict_types=1);

$migrateEventAutomation = require $root . '/bootstrap/event_automation_migration.php';
$migrateEventAutomation($pdo);
$migrationEnsurePdo();
