<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

$schema = new SchemaInspector($pdo);
$schema->addColumnIfMissing('platform_account_bans', 'appeal_closed', 'TINYINT(1) NOT NULL DEFAULT 0');
