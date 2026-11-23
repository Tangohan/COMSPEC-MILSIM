<?php

declare(strict_types=1);

$modpackSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$modpackSchema->runSqlFile($root . '/migrations/2026_11_24_100_public_modpack.sql');
