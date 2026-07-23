<?php

declare(strict_types=1);

/**
 * Crée (idempotent) les sujets de référence du forum ATAK / COMSPEC.
 *
 * Usage : php scripts/seed-atak-forum-topics.php
 */

$root = dirname(__DIR__);

require_once $root . '/bootstrap/autoload.php';
require_once $root . '/bootstrap/env.php';
load_env($root);
require_once $root . '/bootstrap/app.php';

use App\Core\Database;

$pdo = Database::getPdo();
$seed = require $root . '/bootstrap/atak_forum_channels_seed.php';

echo "Seed sujets forum ATAK / COMSPEC...\n";
$seed($pdo);
echo "Terminé.\n";
