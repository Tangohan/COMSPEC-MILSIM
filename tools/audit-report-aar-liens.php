<?php

declare(strict_types=1);

/**
 * RD-19 — Liens entre événements et comptes rendus (AAR) faits par titre.
 *
 * Le registre des opérations (Agenda) rattache un compte rendu à un événement en comparant des intitulés
 * normalisés (AarReportRepository::normalizeOperationKey : minuscules, espaces réduits) : le titre de l'événement, puis
 * son étiquette de campagne, contre l'intitulé d'opération, le titre du compte rendu ou le titre de sa mission. Renommer
 * l'un des deux casse le lien sans avertir. Ce rapport montre, par communauté :
 *   1. les événements passés qui n'ont aucun compte rendu lié (affichés « Manquant ») ;
 *   2. les liens ambigus : plusieurs événements de même titre (ils partagent le même compte rendu), ou plusieurs comptes
 *      rendus de même intitulé (l'un masque l'autre dans l'index) ;
 *   3. les quasi-correspondances : même titre à l'accent, à la ponctuation ou aux espaces près, donc lien cassé alors
 *      qu'un compte rendu semble exister ;
 *   4. les comptes rendus qu'aucun événement n'atteint (orphelins du registre).
 *
 * LECTURE SEULE : le lot 3.1 rapproche les données « par titre et date, avec revue manuelle des cas douteux » ; ce
 * rapport prépare cette revue, il ne rattache rien.
 *
 *   php tools/audit-report-aar-liens.php [--tenant=ID] [--format=text|csv|json] [--limit=N]
 */

require_once __DIR__ . '/lib/audit-report-kit.php';

function audit_report_aar_liens(AuditReportKit $kit, AuditReadOnlyDb $db): int
{
    $tenant = $kit->options['tenant'];

    foreach (['community_events', 'aar_reports'] as $table) {
        if (!$db->hasTable($table)) {
            fwrite(STDERR, "Table absente : $table\n");
            return 1;
        }
    }
    $hasCycles = $db->hasTable('theatre_mission_cycles');

    /** Même normalisation que AarReportRepository::normalizeOperationKey(). */
    $key = static function (mixed $label): string {
        $label = mb_strtolower(trim((string) $label), 'UTF-8');

        return (string) (preg_replace('/\s+/u', ' ', $label) ?? $label);
    };
    /** Clé « souple » : sans accent, sans ponctuation, pour repérer un lien cassé par une retouche de titre. */
    $loose = static function (mixed $label) use ($key): string {
        $s = strtr($key($label), [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae', 'ñ' => 'n',
        ]);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $s));
    };

    $events = $db->rows(
        'SELECT e.id, e.tenant_id, e.title, e.campaign_tag, e.starts_at, e.cancelled_at
         FROM community_events e' . ($tenant > 0 ? ' WHERE e.tenant_id = ?' : '') . ' ORDER BY e.tenant_id, e.starts_at',
        $tenant > 0 ? [$tenant] : []
    );
    $reports = $db->rows(
        'SELECT r.id, r.tenant_id, r.title, r.operation_label, r.status, ' . ($hasCycles ? 'm.title' : 'NULL') . ' AS mission_title
         FROM aar_reports r ' . ($hasCycles ? 'LEFT JOIN theatre_mission_cycles m ON m.id = r.mission_cycle_id AND m.tenant_id = r.tenant_id ' : '')
        . ($tenant > 0 ? 'WHERE r.tenant_id = ? ' : '') . 'ORDER BY r.tenant_id, r.id',
        $tenant > 0 ? [$tenant] : []
    );

    // Index par communauté : clé exacte (celle de l'application) et clé souple => comptes rendus.
    $exact = [];
    $softIdx = [];
    foreach ($reports as $r) {
        $t = (int) $r['tenant_id'];
        $labels = array_unique(array_filter([$key($r['operation_label']), $key($r['title']), $key($r['mission_title'])], static fn (string $s): bool => $s !== ''));
        foreach ($labels as $k) {
            $exact[$t][$k][(int) $r['id']] = $r;
        }
        foreach (array_unique(array_filter([$loose($r['operation_label']), $loose($r['title']), $loose($r['mission_title'])], static fn (string $s): bool => $s !== '')) as $k) {
            $softIdx[$t][$k][(int) $r['id']] = $r;
        }
    }

    $now = date('Y-m-d H:i:s');
    $missing = [];
    $ambiguousEvents = [];
    $near = [];
    $reached = []; // compte rendu atteint par au moins un événement
    $byTitle = [];
    foreach ($events as $e) {
        $byTitle[(int) $e['tenant_id']][$key($e['title'])][] = $e;
    }
    foreach ($events as $e) {
        $t = (int) $e['tenant_id'];
        $title = $key($e['title']);
        $tag = $key($e['campaign_tag']);
        $hit = $title !== '' ? ($exact[$t][$title] ?? null) : null;
        $hitBy = 'titre';
        if ($hit === null && $tag !== '') {
            $hit = $exact[$t][$tag] ?? null;
            $hitBy = 'étiquette de campagne';
        }
        if ($hit !== null) {
            foreach (array_keys($hit) as $rid) {
                $reached[$t][$rid] = true;
            }
            if (count($byTitle[$t][$title] ?? []) > 1 && $hitBy === 'titre') {
                $e['problem'] = count($byTitle[$t][$title]) . ' événements portent ce titre : ils partagent le même compte rendu';
                $e['reports'] = implode(', ', array_map(static fn (int $id): string => '#' . $id, array_keys($hit)));
                $ambiguousEvents[] = $e;
            } elseif (count($hit) > 1) {
                $e['problem'] = count($hit) . ' comptes rendus portent cet intitulé : un seul est retenu par l’index';
                $e['reports'] = implode(', ', array_map(static fn (int $id): string => '#' . $id, array_keys($hit)));
                $ambiguousEvents[] = $e;
            }
            continue;
        }
        $past = $e['cancelled_at'] === null && (string) $e['starts_at'] < $now;
        $softHit = $softIdx[$t][$loose($e['title'])] ?? ($tag !== '' ? ($softIdx[$t][$loose($e['campaign_tag'])] ?? null) : null);
        if ($softHit !== null && $loose($e['title']) !== '') {
            foreach (array_keys($softHit) as $rid) {
                $reached[$t][$rid] = true;
            }
            $e['problem'] = 'titre presque identique (accents, ponctuation) : lien cassé';
            $e['reports'] = implode(', ', array_map(static fn (array $r): string => '#' . $r['id'] . ' « ' . $r['title'] . ' »', $softHit));
            $near[] = $e;
        } elseif ($past) {
            $e['problem'] = 'aucun compte rendu lié';
            $missing[] = $e;
        }
    }
    $orphans = [];
    foreach ($reports as $r) {
        if (!isset($reached[(int) $r['tenant_id']][(int) $r['id']])) {
            $r['problem'] = 'aucun événement ne porte ce titre ni cette étiquette';
            $orphans[] = $r;
        }
    }

    $kit->say(count($events) . ' événement(s), ' . count($reports) . ' compte(s) rendu(s). Lien par titre normalisé : une retouche de titre le casse.');
    $cols = ['tenant_id' => 'communauté', 'id' => 'événement', 'title' => 'titre', 'starts_at' => 'début', 'problem' => 'écart', 'reports' => 'comptes rendus'];
    $kit->table('1. Événements passés sans compte rendu lié', $cols, $missing, 'Ils s’affichent « Manquant » dans l’Agenda.');
    $kit->table('2. Liens ambigus (même titre)', $cols, $ambiguousEvents);
    $kit->table('3. Quasi-correspondances : lien cassé par une retouche de titre', $cols, $near, 'À rattacher à la main lors du lot 3.1 (revue manuelle).');
    $kit->table(
        '4. Comptes rendus qu’aucun événement n’atteint',
        ['tenant_id' => 'communauté', 'id' => 'compte rendu', 'title' => 'titre', 'operation_label' => 'opération', 'mission_title' => 'mission', 'status' => 'statut', 'problem' => 'écart'],
        $orphans
    );
    return ($missing === [] && $ambiguousEvents === [] && $near === [] && $orphans === []) ? 0 : 3;
}

if (isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $kit = new AuditReportKit(
        'audit-report-aar-liens',
        'liens événement ↔ compte rendu (AAR) faits par titre',
        'docs/audit-backoffice RD-19 ; plan lot 3.1 et 7.8'
    );
    exit(audit_report_aar_liens($kit, $kit->start($argv)));
}
