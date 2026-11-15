<?php

declare(strict_types=1);

/**
 * Identités des membres : qui n'a pas de prénom et de nom de personnage renseignés.
 *
 * Le nom affiché partout (fiche, annuaire, JNET, messagerie, notifications) suit une règle unique
 * (App\Support\MemberIdentity, docs/identites-membres.md) : prénom + nom du personnage, puis indicatif, puis nom du
 * compte, puis « Membre ». Ce rapport liste les membres actifs dont le nom affiché ne vient PAS du prénom et du nom
 * (donc probablement incomplets), pour que la gestion les corrige depuis la fiche (État civil du personnage).
 *
 * Sections :
 *  1. sans prénom ni nom : le nom affiché est l'indicatif, le nom du compte ou « Membre » ;
 *  2. avec un prénom seulement ou un nom seulement ;
 *  3. nom du compte qui est une adresse e-mail (jamais affiché, mais à corriger) ;
 *  4. prénom et nom du personnage différents de l'identité légale enregistrée (compte seulement : aucun nom légal
 *     n'est affiché dans le rapport).
 *
 * Rien n'est écrit : lecture seule, voir tools/lib/audit-report-kit.php. Ne pas lancer contre la production sans
 * l'accord de la gestion.
 *
 *   php tools/audit-report-identites.php [--tenant=ID] [--format=text|csv|json] [--limit=N]
 */

require_once __DIR__ . '/lib/audit-report-kit.php';

/** @param array<string, mixed> $row */
function audit_identites_source(array $row): string
{
    return \App\Support\MemberName::source([
        'first_name' => $row['first_name'] ?? '',
        'last_name' => $row['last_name'] ?? '',
        'callsign' => $row['callsign'] ?? '',
        'display_name' => $row['display_name'] ?? '',
    ]);
}

function audit_report_identites(AuditReportKit $kit, AuditReadOnlyDb $db): int
{
    $tenant = $kit->options['tenant'];

    if (!$db->hasTable('users')) {
        fwrite(STDERR, "Table absente : users\n");
        return 1;
    }
    $hasProfiles = $db->hasTable('user_profiles');
    $hasLegal = $db->hasTable('user_legal_identities');
    $params = [];
    $where = "u.status = 'active'";
    if ($tenant > 0) {
        $where .= ' AND u.tenant_id = ?';
        $params[] = $tenant;
    }
    $rows = $db->rows(
        'SELECT u.id AS user_id, u.tenant_id, u.display_name, u.callsign, '
        . ($hasProfiles ? 'up.first_name, up.last_name' : 'NULL AS first_name, NULL AS last_name')
        . ($hasLegal ? ', ul.first_name AS legal_first_name, ul.last_name AS legal_last_name' : ', NULL AS legal_first_name, NULL AS legal_last_name')
        . ' FROM users u'
        . ($hasProfiles ? ' LEFT JOIN user_profiles up ON up.user_id = u.id' : '')
        . ($hasLegal ? ' LEFT JOIN user_legal_identities ul ON ul.user_id = u.id' : '')
        . ' WHERE ' . $where . ' ORDER BY u.tenant_id, u.id',
        $params
    );

    $labels = [
        'callsign' => 'indicatif',
        'account' => 'nom du compte',
        'fallback' => '« Membre » (rien d’autre)',
    ];
    $noCivil = [];
    $partial = [];
    $emailAccount = [];
    $divergent = [];
    $complete = 0;
    foreach ($rows as $row) {
        $first = trim((string) ($row['first_name'] ?? ''));
        $last = trim((string) ($row['last_name'] ?? ''));
        // L'identité légale ne sort jamais dans le rapport (CSV, JSON ou texte) : seule la comparaison est gardée.
        $lf = trim((string) ($row['legal_first_name'] ?? ''));
        $ll = trim((string) ($row['legal_last_name'] ?? ''));
        unset($row['legal_first_name'], $row['legal_last_name']);
        $row['shown_from'] = $labels[audit_identites_source($row)] ?? 'prénom et nom';
        if (\App\Support\MemberName::looksLikeEmail(trim((string) ($row['display_name'] ?? '')))) {
            $emailAccount[] = $row;
        }
        if ($first === '' && $last === '') {
            $row['problem'] = 'ni prénom ni nom';
            $noCivil[] = $row;
            continue;
        }
        if ($first === '' || $last === '') {
            $row['problem'] = $first === '' ? 'prénom manquant' : 'nom manquant';
            $partial[] = $row;
            continue;
        }
        ++$complete;
        if (($lf !== '' || $ll !== '') && (mb_strtolower($lf, 'UTF-8') !== mb_strtolower($first, 'UTF-8') || mb_strtolower($ll, 'UTF-8') !== mb_strtolower($last, 'UTF-8'))) {
            $row['problem'] = 'prénom/nom du personnage différents de l’identité légale';
            $divergent[] = $row;
        }
    }

    $kit->say(count($rows) . ' membre(s) actif(s) : ' . $complete . ' avec prénom et nom, ' . count($partial) . ' avec un seul des deux, ' . count($noCivil) . ' sans état civil du personnage.');
    $columns = ['tenant_id' => 'communauté', 'user_id' => 'membre', 'problem' => 'écart', 'shown_from' => 'nom affiché depuis', 'callsign' => 'indicatif', 'display_name' => 'nom du compte'];
    $kit->table('Membres sans prénom ni nom (nom affiché probablement incomplet)', $columns, $noCivil, 'À corriger depuis la fiche membre : « État civil du personnage ».');
    $kit->table('Membres avec un prénom ou un nom seulement', $columns, $partial);
    $kit->table('Nom du compte qui est une adresse e-mail', ['tenant_id' => 'communauté', 'user_id' => 'membre', 'shown_from' => 'nom affiché depuis'], $emailAccount, 'Le nom du compte n’est jamais affiché s’il ressemble à une adresse e-mail.');
    if ($hasLegal) {
        $kit->table('Prénom/nom du personnage différents de l’identité légale', ['tenant_id' => 'communauté', 'user_id' => 'membre', 'problem' => 'écart'], $divergent, 'Aucun nom légal n’est affiché ici. Le site n’affiche jamais l’identité légale comme nom de membre.');
    }

    return ($noCivil === [] && $partial === []) ? 0 : 3;
}

if (isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $kit = new AuditReportKit(
        'audit-report-identites',
        'membres actifs sans prénom ni nom de personnage renseignés (nom affiché probablement incomplet)',
        'docs/identites-membres.md ; campagne de fiabilisation des données'
    );
    exit(audit_report_identites($kit, $kit->start($argv)));
}
