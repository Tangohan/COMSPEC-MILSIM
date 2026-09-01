#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Reprise des parcours d’intégration : prévisualisation par défaut, exécution avec --execute.
 *
 * Usage :
 *   php scripts/member-integration-backfill.php --tenant=1 --since=2026-08-01
 *   php scripts/member-integration-backfill.php --tenant=1 --since=2026-08-01 --execute
 *   php scripts/member-integration-backfill.php --tenant=1 --users=12,15 --execute
 */

$root = dirname(__DIR__);
require_once $root . '/bootstrap/autoload.php';
require_once $root . '/bootstrap/app.php';

use App\Core\Container;
use App\Services\MemberIntegration\MemberIntegrationAutomationService;

$tenantId = 0;
$since = null;
$execute = false;
$userIds = [];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--execute') {
        $execute = true;
        continue;
    }
    if (str_starts_with($arg, '--tenant=')) {
        $tenantId = (int) substr($arg, 9);
        continue;
    }
    if (str_starts_with($arg, '--since=')) {
        $since = substr($arg, 8);
        continue;
    }
    if (str_starts_with($arg, '--users=')) {
        $userIds = array_map('intval', explode(',', substr($arg, 8)));
    }
}

if ($tenantId < 1) {
    fwrite(STDERR, "Indiquez --tenant=ID\n");
    exit(1);
}

/** @var MemberIntegrationAutomationService $svc */
$svc = Container::get(MemberIntegrationAutomationService::class);
if (!$execute) {
    $preview = $svc->previewBackfill($tenantId, $since, $userIds);
    echo 'À créer : ' . $preview['would_create'] . ' · déjà suivis : ' . $preview['ignored'] . "\n";
    echo "Relancez avec --execute pour ouvrir les parcours.\n";
    exit(0);
}

$out = $svc->executeBackfill($tenantId, 0, $since, $userIds);
echo 'Créés : ' . $out['created'] . ' · ignorés : ' . $out['ignored'] . ' · erreurs : ' . $out['errors'] . "\n";
foreach ($out['details'] as $line) {
    echo $line . "\n";
}
exit($out['errors'] > 0 ? 1 : 0);
