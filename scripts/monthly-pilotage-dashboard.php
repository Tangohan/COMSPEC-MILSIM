<?php

declare(strict_types=1);

use App\Core\Database;

require_once __DIR__ . '/../app/Core/Database.php';

$options = getopt('', ['month::', 'tenant-id::', 'output::']);
$monthArg = isset($options['month']) && is_string($options['month']) ? trim($options['month']) : '';
$tenantId = isset($options['tenant-id']) ? max(0, (int) $options['tenant-id']) : 0;

if ($monthArg !== '' && !preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $monthArg)) {
    fwrite(STDERR, "Format --month attendu: YYYY-MM\n");
    exit(1);
}

$tz = new DateTimeZone('UTC');
$month = $monthArg !== '' ? DateTimeImmutable::createFromFormat('!Y-m', $monthArg, $tz) : (new DateTimeImmutable('first day of last month', $tz));
if (!$month) {
    fwrite(STDERR, "Impossible de parser le mois.\n");
    exit(1);
}

$start = $month->setTime(0, 0, 0);
$end = $start->modify('first day of next month');
$label = $start->format('Y-m');

$output = isset($options['output']) && is_string($options['output']) && trim($options['output']) !== ''
    ? trim($options['output'])
    : __DIR__ . '/../storage/intel/pilotage-mensuel-' . $label . '.md';

$pdo = Database::getPdo();

$whereTenant = $tenantId > 0 ? ' AND tenant_id = :tenant_id' : '';
$params = [
    ':start' => $start->format('Y-m-d H:i:s'),
    ':end' => $end->format('Y-m-d H:i:s'),
];
if ($tenantId > 0) {
    $params[':tenant_id'] = $tenantId;
}

$telemetryExists = (bool) $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'request_telemetry' LIMIT 1")->fetchColumn();

$errors4xx = 0;
$errors5xx = 0;
$routeP95 = [];
$keyRoutes = ['/dashboard', '/formations', '/formations/enroll', '/courrier', '/courrier/documents/{id}/sign'];
if ($telemetryExists) {
    $sqlErr = "SELECT
        SUM(CASE WHEN status_code BETWEEN 400 AND 499 THEN 1 ELSE 0 END) AS errors_4xx,
        SUM(CASE WHEN status_code BETWEEN 500 AND 599 THEN 1 ELSE 0 END) AS errors_5xx
      FROM request_telemetry
      WHERE created_at >= :start AND created_at < :end" . $whereTenant;
    $stErr = $pdo->prepare($sqlErr);
    $stErr->execute($params);
    $rowErr = $stErr->fetch(PDO::FETCH_ASSOC) ?: [];
    $errors4xx = (int) ($rowErr['errors_4xx'] ?? 0);
    $errors5xx = (int) ($rowErr['errors_5xx'] ?? 0);

    foreach ($keyRoutes as $route) {
        $sqlRoute = "SELECT duration_ms
          FROM request_telemetry
          WHERE route_path = :route
            AND created_at >= :start AND created_at < :end" . $whereTenant . '
          ORDER BY duration_ms ASC';
        $stRoute = $pdo->prepare($sqlRoute);
        $stRoute->bindValue(':route', $route);
        $stRoute->bindValue(':start', $params[':start']);
        $stRoute->bindValue(':end', $params[':end']);
        if ($tenantId > 0) {
            $stRoute->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        }
        $stRoute->execute();
        $durations = array_map(static fn (array $r): int => (int) $r['duration_ms'], $stRoute->fetchAll(PDO::FETCH_ASSOC));
        $count = count($durations);
        if ($count === 0) {
            $routeP95[$route] = null;
            continue;
        }
        $index = (int) ceil(0.95 * $count) - 1;
        $index = max(0, min($count - 1, $index));
        $routeP95[$route] = $durations[$index];
    }
}

$sqlConv = "SELECT
    SUM(CASE WHEN category = 'recruitment' AND name = 'enlistment_form_open' THEN 1 ELSE 0 END) AS opens,
    SUM(CASE WHEN category = 'recruitment' AND name = 'enlistment_submitted' THEN 1 ELSE 0 END) AS submitted
  FROM usage_analytics_events
  WHERE created_at >= :start AND created_at < :end" . $whereTenant;
$stConv = $pdo->prepare($sqlConv);
$stConv->execute($params);
$conv = $stConv->fetch(PDO::FETCH_ASSOC) ?: [];
$opens = (int) ($conv['opens'] ?? 0);
$submitted = (int) ($conv['submitted'] ?? 0);
$conversion = $opens > 0 ? round(($submitted / $opens) * 100, 2) : null;

$sqlTraining = "SELECT
    COUNT(*) AS enrollments,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed
  FROM training_enrollments
  WHERE assigned_at >= :start AND assigned_at < :end" . $whereTenant;
$stTraining = $pdo->prepare($sqlTraining);
$stTraining->execute($params);
$train = $stTraining->fetch(PDO::FETCH_ASSOC) ?: [];
$enrollments = (int) ($train['enrollments'] ?? 0);
$completed = (int) ($train['completed'] ?? 0);
$completionRate = $enrollments > 0 ? round(($completed / $enrollments) * 100, 2) : null;

$sqlCourrier = "SELECT
    COUNT(*) AS courrier_total,
    SUM(CASE WHEN status = 'signed' OR signed_at IS NOT NULL THEN 1 ELSE 0 END) AS courrier_signed
  FROM courrier_documents
  WHERE created_at >= :start AND created_at < :end" . $whereTenant;
$stCourrier = $pdo->prepare($sqlCourrier);
$stCourrier->execute($params);
$courrier = $stCourrier->fetch(PDO::FETCH_ASSOC) ?: [];

$sqlSignatures = "SELECT COUNT(*)
  FROM user_signatures
  WHERE created_at >= :start AND created_at < :end" . $whereTenant;
$stSignatures = $pdo->prepare($sqlSignatures);
$stSignatures->execute($params);
$newSignatures = (int) $stSignatures->fetchColumn();

$lines = [];
$lines[] = '# Tableau de bord mensuel — ' . $label;
$lines[] = '';
$lines[] = '- Période: ' . $start->format('Y-m-d') . ' → ' . $end->modify('-1 day')->format('Y-m-d') . ' (UTC)';
$lines[] = '- Scope tenant: ' . ($tenantId > 0 ? (string) $tenantId : 'global');
$lines[] = '';
$lines[] = '## Indicateurs essentiels';
$lines[] = '';
$lines[] = '| Indicateur | Valeur | Source |';
$lines[] = '|---|---:|---|';
$lines[] = '| Erreurs HTTP 4xx | ' . ($telemetryExists ? (string) $errors4xx : 'N/D') . ' | request_telemetry |';
$lines[] = '| Erreurs HTTP 5xx | ' . ($telemetryExists ? (string) $errors5xx : 'N/D') . ' | request_telemetry |';
$lines[] = '| Conversion enrôlement | ' . ($conversion !== null ? $conversion . '%' : 'N/D') . ' | usage_analytics_events |';
$lines[] = '| Taux complétion formations | ' . ($completionRate !== null ? $completionRate . '%' : 'N/D') . ' | training_enrollments |';
$lines[] = '| Courriers signés / total | ' . (int) ($courrier['courrier_signed'] ?? 0) . ' / ' . (int) ($courrier['courrier_total'] ?? 0) . ' | courrier_documents |';
$lines[] = '| Nouvelles signatures opérateurs | ' . $newSignatures . ' | user_signatures |';
$lines[] = '';
$lines[] = '## p95 latence routes clés';
$lines[] = '';
$lines[] = '| Route | p95 (ms) |';
$lines[] = '|---|---:|';
foreach ($keyRoutes as $route) {
    $lines[] = '| `' . $route . '` | ' . ($telemetryExists && $routeP95[$route] !== null ? (string) $routeP95[$route] : 'N/D') . ' |';
}
$lines[] = '';
$lines[] = '## Notes exploitation';
$lines[] = '- Seuil d\'alerte recommandé 5xx: > 1% des requêtes mensuelles.';
$lines[] = '- Seuil p95 recommandé routes clés: < 800ms (hors exports PDF lourds).';
$lines[] = '- Pour suivre finement prod: exécuter `php setup-database.php` avant premier run pour créer `request_telemetry`.';

$dir = dirname($output);
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
file_put_contents($output, implode(PHP_EOL, $lines) . PHP_EOL);

echo "OK: tableau généré => {$output}\n";
