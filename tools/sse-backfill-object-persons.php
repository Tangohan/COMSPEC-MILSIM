<?php

declare(strict_types=1);

/** php tools/sse-backfill-object-persons.php [--tenant=7] [--apply] (simulation par défaut). */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/bootstrap/autoload.php';

$opts = getopt('', ['tenant:', 'apply']);
if (isset($opts['tenant']) && (!ctype_digit((string) $opts['tenant']) || (int) $opts['tenant'] < 1)) {
    fwrite(STDERR, "--tenant attend un entier positif.\n");
    exit(2);
}
$persons = new App\Repositories\SsePersonRepository();
$meshes = new App\Repositories\SseMeshRepository();
$pdo = App\Core\Database::getPdo();
$query = $pdo->prepare("SELECT id, tenant_id FROM sse_mesh_nodes WHERE kind = 'person'
    AND (ref_type IS NULL OR ref_type = '') AND (ref_id IS NULL OR ref_id = 0)"
    . (isset($opts['tenant']) ? ' AND tenant_id = :t' : '') . ' ORDER BY id');
$query->execute(isset($opts['tenant']) ? ['t' => (int) $opts['tenant']] : []);
$rows = $query->fetchAll(PDO::FETCH_ASSOC);
echo isset($opts['apply']) ? "Application du rattrapage.\n" : "Simulation, aucune écriture.\n";
$count = 0;
foreach ($rows as $row) {
    if (!isset($opts['apply'])) {
        ++$count;
        continue;
    }
    try {
        $pdo->beginTransaction();
        // Relecture verrouillée : une seconde exécution ne crée pas une seconde personne.
        $lock = $pdo->prepare("SELECT * FROM sse_mesh_nodes WHERE id = :id AND tenant_id = :t
            AND kind = 'person' AND (ref_type IS NULL OR ref_type = '')
            AND (ref_id IS NULL OR ref_id = 0) FOR UPDATE");
        $lock->execute(['id' => $row['id'], 't' => $row['tenant_id']]);
        $node = $lock->fetch(PDO::FETCH_ASSOC);
        if (!$node) {
            $pdo->rollBack();
            continue;
        }
        $meta = json_decode((string) ($node['meta_json'] ?? ''), true);
        $meta = is_array($meta) ? $meta : [];
        $meta['precision'] ??= (string) ($node['detail'] ?? '');
        $personId = $persons->create(App\Support\SseObjectPerson::fields(
            (int) $node['tenant_id'], (string) $node['label'], $meta
        ));
        if (!empty($meta['image_path'])) {
            $persons->addPhoto($personId, (int) $node['tenant_id'], ['image_path' => $meta['image_path']]);
        }
        $update = $pdo->prepare("UPDATE sse_mesh_nodes SET ref_type = 'person', ref_id = :p WHERE id = :id AND tenant_id = :t");
        $update->execute(['p' => $personId, 'id' => $node['id'], 't' => $node['tenant_id']]);
        $pdo->commit();
        ++$count;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, 'Échec du nœud ' . $row['id'] . ': ' . $e->getMessage() . "\n");
        exit(1);
    }
}
echo $count . " personne(s) concernée(s).\n";
