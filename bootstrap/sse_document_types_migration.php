<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;
use App\Support\SseDocumentTypes;

/**
 * Papiers d'identité : types de papiers repris du mod SSE (titre de séjour, certificat d'immatriculation).
 *
 * Les pays réels déjà importés (sse_id_countries, real_sim = 1) gardent la liste de papiers enregistrée à l'import
 * (doc_types, clés séparées par des virgules) : on y ajoute les types nouveaux que le mod délivre pour ce pays
 * (SseDocumentTypes::servedBy, miroir de countries.json). Le titre de séjour prend la durée de validité de la carte
 * d'identité du pays (ou du passeport), comme à l'import d'un pays réel. Rien d'autre n'est touché : un type retiré à
 * la main reste retiré, les pays fictifs et les pays créés par la communauté ne changent pas, un pays sans liste
 * enregistrée suit déjà le registre du mod à la lecture.
 *
 * Idempotente (un type déjà présent n'est pas rajouté) ; MySQL, MariaDB et sqlite (tests). Rend le nombre de pays modifiés.
 */
return static function (PDO $pdo, ?callable $log = null): int {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    // Types apparus avec le miroir du mod : les seuls que cette étape ajoute.
    $new = ['residence_permit', 'vehicle_registration'];
    $schema = new SchemaInspector($pdo);
    if (!$schema->tableExists('sse_id_countries') || !$schema->columnExists('sse_id_countries', 'real_sim') || !$schema->columnExists('sse_id_countries', 'doc_types')) {
        $log("  [OK] sse_id_countries : table ou colonnes absentes, rien à faire\n");

        return 0;
    }
    // La colonne a été créée en VARCHAR(80) : trop courte dès qu'un pays porte les types du mod (erreur 1406).
    if (in_array($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), ['mysql', 'mariadb'], true)) {
        $pdo->exec("ALTER TABLE sse_id_countries MODIFY doc_types VARCHAR(255) NOT NULL DEFAULT 'id_card'");
    }
    $rows = $pdo->query("SELECT id, code, doc_types, validity_json FROM sse_id_countries WHERE real_sim = 1 AND doc_types IS NOT NULL AND doc_types <> ''")->fetchAll(PDO::FETCH_ASSOC);
    $update = $pdo->prepare('UPDATE sse_id_countries SET doc_types = :types, validity_json = :validity WHERE id = :id');
    $changed = 0;
    foreach ($rows as $row) {
        $types = array_values(array_filter(array_map('trim', explode(',', (string) $row['doc_types'])), static fn (string $t): bool => $t !== ''));
        $add = array_values(array_diff(array_intersect(SseDocumentTypes::servedBy((string) $row['code']), $new), $types));
        if ($add === []) {
            continue;
        }
        $validity = json_decode((string) ($row['validity_json'] ?? ''), true);
        $years = is_array($validity) ? ($validity['id_card'] ?? $validity['passport'] ?? null) : null;
        if (in_array('residence_permit', $add, true) && $years !== null && !isset($validity['residence_permit'])) {
            $validity['residence_permit'] = (int) $years;
        }
        $update->execute([
            'types' => implode(',', array_merge($types, $add)),
            'validity' => is_array($validity) && $validity !== [] ? json_encode($validity) : ($row['validity_json'] ?? null),
            'id' => (int) $row['id'],
        ]);
        $changed++;
    }
    $log("  [OK] sse_id_countries : {$changed} pays réel(s) complété(s) (titre de séjour, certificat d'immatriculation)\n");

    return $changed;
};
