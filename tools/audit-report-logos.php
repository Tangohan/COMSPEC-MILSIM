<?php

declare(strict_types=1);

/**
 * RD-44 / RD-45 — Logos de communauté désynchronisés.
 *
 * Le logo existe dans deux colonnes : `tenants.logo_url` et `tenant_branding.logo_url`. Plusieurs écrans écrivent l'une,
 * l'autre ou les deux. Ce rapport classe chaque communauté : identiques, vides, une seule renseignée, ou différentes,
 * et propose pour les différentes la valeur la plus récente (règle du lot 3.10 : « choisir la valeur la plus récente »)
 * d'après `updated_at` de chaque ligne. Rien n'est écrit : la proposition n'est qu'une indication.
 *
 * RD-45 (limites de taille 3 Mo / 12 Mo, code de traitement dupliqué) n'est pas une donnée : il n'a pas de rapport.
 *
 *   php tools/audit-report-logos.php [--tenant=ID] [--format=text|csv|json] [--limit=N]
 */

require_once __DIR__ . '/lib/audit-report-kit.php';

function audit_report_logos(AuditReportKit $kit, AuditReadOnlyDb $db): int
{
    $tenant = $kit->options['tenant'];

    if (!$db->hasTable('tenants') || !$db->hasTable('tenant_branding')) {
        fwrite(STDERR, "Table absente : tenants ou tenant_branding\n");
        return 1;
    }

    $rows = $db->rows(
        'SELECT t.id AS tenant_id, t.slug, t.name, t.logo_url AS tenants_logo, t.updated_at AS tenants_updated_at,
                b.logo_url AS branding_logo, b.updated_at AS branding_updated_at,
                CASE WHEN b.tenant_id IS NULL THEN 0 ELSE 1 END AS has_branding_row
         FROM tenants t
         LEFT JOIN tenant_branding b ON b.tenant_id = t.id' . ($tenant > 0 ? ' WHERE t.id = ?' : '') . '
         ORDER BY t.id',
        $tenant > 0 ? [$tenant] : []
    );

    $counts = ['identiques' => 0, 'sans logo' => 0];
    $gaps = [];
    foreach ($rows as $row) {
        $a = trim((string) $row['tenants_logo']);
        $b = trim((string) $row['branding_logo']);
        if ($a === '' && $b === '') {
            ++$counts['sans logo'];
            continue;
        }
        if ($a === $b) {
            ++$counts['identiques'];
            continue;
        }
        if ($b === '') {
            $row['problem'] = (int) $row['has_branding_row'] === 1 ? 'logo seulement dans tenants (branding vide)' : 'logo seulement dans tenants (pas de ligne branding)';
            $row['suggestion'] = 'tenants.logo_url';
        } elseif ($a === '') {
            $row['problem'] = 'logo seulement dans tenant_branding';
            $row['suggestion'] = 'tenant_branding.logo_url';
        } else {
            $row['problem'] = 'deux valeurs différentes';
            $ta = strtotime((string) $row['tenants_updated_at']) ?: 0;
            $tb = strtotime((string) $row['branding_updated_at']) ?: 0;
            $row['suggestion'] = $ta === $tb ? 'indéterminé (mêmes dates)' : ($ta > $tb ? 'tenants.logo_url (plus récent)' : 'tenant_branding.logo_url (plus récent)');
        }
        $gaps[] = $row;
    }

    $kit->say(count($rows) . ' communauté(s) : ' . $counts['identiques'] . ' avec le même logo des deux côtés, ' . $counts['sans logo'] . ' sans logo, ' . count($gaps) . ' désynchronisée(s).');
    $kit->table(
        'Logos désynchronisés (RD-44)',
        ['tenant_id' => 'communauté', 'slug' => 'slug', 'problem' => 'écart', 'tenants_logo' => 'tenants.logo_url', 'branding_logo' => 'tenant_branding.logo_url',
            'tenants_updated_at' => 'tenants maj', 'branding_updated_at' => 'branding maj', 'suggestion' => 'valeur la plus récente'],
        $gaps,
        'Proposition seulement : le choix de la valeur à garder se fait communauté par communauté, avant le lot 3.10.'
    );
    return $gaps === [] ? 0 : 3;
}

if (isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $kit = new AuditReportKit(
        'audit-report-logos',
        'logos de communauté désynchronisés entre tenants.logo_url et tenant_branding.logo_url',
        'docs/audit-backoffice RD-44 et RD-45 ; plan lot 3.10 et 7.8'
    );
    exit(audit_report_logos($kit, $kit->start($argv)));
}
