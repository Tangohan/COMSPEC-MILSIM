<?php

declare(strict_types=1);

\App\Services\PatchStudio\Schema::migrate($pdo, $root);
$migrationEnsurePdo();
