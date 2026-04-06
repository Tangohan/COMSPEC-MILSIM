<?php

declare(strict_types=1);

/**
 * Exporte la formation slug « parcours-portail » au format échange Studio (JSON).
 *
 * Usage : php scripts/export-parcours-portail-json.php [tenant_id]
 * Sortie : formation-parcours-portail.json à la racine du dépôt.
 *
 * Nécessite une base à jour (migrations + seed onboarding pour ce tenant).
 */

$root = dirname(__DIR__);

require_once $root . '/bootstrap/autoload.php';
require_once $root . '/bootstrap/env.php';
load_env($root);
require_once $root . '/bootstrap/app.php';

use App\Core\Container;
use App\Core\Database;
use App\Services\Training\TrainingCourseExchangeService;

$pdo = Database::getPdo();
$tenantId = isset($argv[1]) ? (int) $argv[1] : 0;
if ($tenantId < 1) {
    $row = $pdo->query('SELECT id FROM tenants ORDER BY id ASC LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
    $tenantId = $row ? (int) $row['id'] : 0;
}
if ($tenantId < 1) {
    fwrite(STDERR, "Aucun tenant trouvé.\n");
    exit(1);
}

$st = $pdo->prepare('SELECT id FROM training_courses WHERE tenant_id = ? AND slug = ? LIMIT 1');
$st->execute([$tenantId, 'parcours-portail']);
$courseId = (int) $st->fetchColumn();
if ($courseId < 1) {
    fwrite(STDERR, "Formation « parcours-portail » introuvable pour le tenant {$tenantId}.\n");
    exit(1);
}

/** @var TrainingCourseExchangeService $svc */
$svc = Container::get(TrainingCourseExchangeService::class);
$doc = $svc->buildExportDocument($courseId, $tenantId);

$outPath = $root . '/formation-parcours-portail.json';
$json = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($json === false) {
    fwrite(STDERR, "Encodage JSON impossible.\n");
    exit(1);
}

if (file_put_contents($outPath, $json) === false) {
    fwrite(STDERR, "Écriture impossible : {$outPath}\n");
    exit(1);
}

echo "Export OK — tenant {$tenantId}, cours {$courseId}\n";
echo strlen($json) . " octets → {$outPath}\n";
