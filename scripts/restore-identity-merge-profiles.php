#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Reprend les dossiers (RH, fiche civile, fiche communauté) restés sur les comptes
 * réunis lors de la fusion « un e-mail = un compte ».
 *
 * N’écrase jamais une valeur déjà remplie. Peut être relancé sans danger.
 *
 *   php scripts/restore-identity-merge-profiles.php
 */

$root = dirname(__DIR__);
require_once $root . '/bootstrap/autoload.php';
require_once $root . '/bootstrap/app.php';

use App\Core\Database;
use App\Services\Identity\UserIdentityProfileRestoreService;

$pdo = Database::getPdo();
$restore = new UserIdentityProfileRestoreService($pdo);
$out = $restore->restoreAll();

echo 'Fusions lues : ' . (int) ($out['merges'] ?? 0) . "\n";
echo 'Dossiers RH repris : ' . (int) ($out['personnel'] ?? 0) . "\n";
echo 'Compléments RH repris : ' . (int) ($out['extras'] ?? 0) . "\n";
echo 'Fiches civiles reprises : ' . (int) ($out['user_profiles'] ?? 0) . "\n";
echo 'Identités légales reprises : ' . (int) ($out['legal'] ?? 0) . "\n";
echo 'Fiches communauté reprises : ' . (int) ($out['community_profiles'] ?? 0) . "\n";

exit(0);
