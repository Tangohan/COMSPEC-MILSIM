<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;
use App\Support\SseIdRealCountryCatalog;

/**
 * COMSPEC IDENTITY — pays réels stylisés (univers « Réaliste — simulation »).
 *
 * - sse_id_countries.real_sim : pays importé du jeu stylisé (SseIdRealCountryCatalog), code ISO réel accepté.
 * - Pays réels ajoutés d'office par une version précédente (autorité « Bureau des documents de simulation ») : passés
 *   en pays réels stylisés (univers réaliste-simulation, code MRZ OACI — « D » pour l'Allemagne —, autorité, langues,
 *   types, durées et couleurs du jeu). Leur format de numéro n'est remplacé que s'ils n'ont encore émis aucun papier.
 *   Ils restent actifs (une communauté peut les archiver) ; les nouvelles communautés ne les reçoivent plus d'office.
 *
 * Idempotente (colonne ajoutée si elle manque, conversion limitée aux anciennes lignes) ; MySQL et sqlite (tests).
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    if (!$schema->tableExists('sse_id_countries')) {
        $log("  [ATTENTION] pays émetteurs SSE absents — pays réels stylisés reportés\n");

        return;
    }
    if ($schema->addColumnIfMissing('sse_id_countries', 'real_sim', 'TINYINT(1) NOT NULL DEFAULT 0')) {
        $log("  [OK] sse_id_countries.real_sim\n");
    }
    if (!$schema->columnExists('sse_id_countries', 'theme')) {
        return;
    }

    $legacy = 'Bureau des documents de simulation';
    $codes = array_keys(SseIdRealCountryCatalog::COUNTRIES);
    $in = implode(',', array_fill(0, count($codes), '?'));
    $st = $pdo->prepare("SELECT id, tenant_id, code FROM sse_id_countries WHERE real_sim = 0 AND authority = ? AND code IN ($in)");
    $st->execute(array_merge([$legacy], $codes));
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $hasDocs = $schema->tableExists('sse_id_documents');
    $count = $hasDocs ? $pdo->prepare('SELECT COUNT(*) FROM sse_id_documents WHERE tenant_id = ? AND country_code = ?') : null;
    $upd = $pdo->prepare(
        'UPDATE sse_id_countries SET real_sim = 1, fictional = 0, theme = ?, name_en = ?, mrz_code = ?, number_format = ?, authority = ?,
            region = ?, languages = ?, doc_types = ?, validity_json = ?, validity_years = ?, color_primary = ?, color_secondary = ?
         WHERE id = ?'
    );
    foreach ($rows as $r) {
        $code = (string) $r['code'];
        $d = SseIdRealCountryCatalog::countryData($code);
        $fmt = $d['number_format'];
        if ($count !== null) {
            $count->execute([(int) $r['tenant_id'], $code]);
            if ((int) $count->fetchColumn() > 0) {
                $cur = $pdo->prepare('SELECT number_format FROM sse_id_countries WHERE id = ?');
                $cur->execute([(int) $r['id']]);
                $fmt = (string) $cur->fetchColumn();
            }
        }
        $upd->execute([
            $d['theme'], $d['name_en'], $d['mrz_code'], $fmt, $d['authority'], $d['region'],
            implode(',', $d['languages']), implode(',', $d['doc_types']), json_encode($d['validity']), max($d['validity']),
            $d['color_primary'], $d['color_secondary'], (int) $r['id'],
        ]);
    }
    if ($rows !== []) {
        $log('  [OK] ' . count($rows) . " pays réels passés en pays stylisés (univers réaliste-simulation)\n");
    }
};
