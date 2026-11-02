<?php

declare(strict_types=1);

namespace App\Support\QualificationCatalog;

use App\Support\Migrations\SchemaInspector;
use PDO;

/**
 * Étape 3680 : colonnes country_code, location, website_url, description_en, issuer_key et issuer_type sur
 * qualification_issuers, puis report des détails écrits en lignes dans la présentation (IssuerDetails::split). Les
 * émetteurs importés du catalogue réel (mention « Émetteur de jeu inspiré… ») y sont recoupés par nom ou sigle :
 * issuer_key posé, champs vides complétés. Une valeur déjà présente n’est jamais écrasée ; seules les lignes reconnues
 * quittent la présentation ; une relance ne change rien.
 */
final class IssuerFieldsMigration
{
    public const COLUMNS = [
        'issuer_key' => 'VARCHAR(80) DEFAULT NULL',
        'issuer_type' => 'VARCHAR(30) DEFAULT NULL',
        'country_code' => 'VARCHAR(8) DEFAULT NULL',
        'location' => 'VARCHAR(200) DEFAULT NULL',
        'website_url' => 'VARCHAR(300) DEFAULT NULL',
        'description_en' => 'TEXT DEFAULT NULL',
    ];

    private const IMPORTED = '/(?:Émetteur de jeu inspiré d’un organisme réel|In-game issuer inspired by a real)/u';

    /** @return array{added: list<string>, migrated: int}|null null : table absente */
    public static function run(PDO $pdo): ?array
    {
        $schema = new SchemaInspector($pdo);
        if (!$schema->tableExists('qualification_issuers')) {
            return null;
        }
        $added = [];
        foreach (self::COLUMNS as $col => $def) {
            if ($schema->addColumnIfMissing('qualification_issuers', $col, $def)) {
                $added[] = $col;
            }
        }
        if ($schema->addIndexIfMissing('qualification_issuers', 'idx_qual_issuer_country', ['tenant_id', 'country_code'])) {
            $added[] = 'idx_qual_issuer_country';
        }
        if ($schema->addIndexIfMissing('qualification_issuers', 'idx_qual_issuer_key', ['tenant_id', 'issuer_key'])) {
            $added[] = 'idx_qual_issuer_key';
        }
        $hasDesc = $schema->columnExists('qualification_issuers', 'description');

        return ['added' => $added, 'migrated' => self::backfill($pdo, $hasDesc)];
    }

    private static function backfill(PDO $pdo, bool $hasDesc): int
    {
        $fields = array_keys(self::COLUMNS);
        $rows = $pdo->query('SELECT id, name, short_name, ' . ($hasDesc ? 'description' : 'NULL AS description') . ', ' . implode(', ', $fields) . ' FROM qualification_issuers ORDER BY id')
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $migrated = 0;
        foreach ($rows as $row) {
            $desc = (string) ($row['description'] ?? '');
            $split = IssuerDetails::split($desc);
            $found = [
                'country_code' => $split['country_code'],
                'location' => $split['location'],
                'website_url' => $split['website_url'],
                'description_en' => $split['description_en'],
                'issuer_key' => '',
                'issuer_type' => '',
            ];
            if (preg_match(self::IMPORTED, $desc) === 1 || trim((string) $row['issuer_key']) !== '') {
                $cat = trim((string) $row['issuer_key']) !== ''
                    ? QualificationIssuerCatalog::find((string) $row['issuer_key'])
                    : QualificationIssuerCatalog::matchCatalog($row);
                if ($cat !== null) {
                    $l = QualificationIssuerCatalog::localize($cat, 'fr');
                    $found['issuer_key'] = (string) $cat['key'];
                    $found['issuer_type'] = (string) ($cat['type'] ?? '');
                    $found['country_code'] = $found['country_code'] ?: (string) ($cat['country'] ?? '');
                    $found['location'] = $found['location'] ?: $l['location'];
                    $found['website_url'] = $found['website_url'] ?: $l['website'];
                    $found['description_en'] = $found['description_en'] ?: QualificationCatalog::pick($cat['description'] ?? '', 'en');
                }
            }
            $set = [];
            $vals = [];
            foreach ($found as $col => $v) {
                if ($v !== '' && trim((string) ($row[$col] ?? '')) === '') {
                    $set[] = $col . ' = ?';
                    $vals[] = $v;
                }
            }
            if ($hasDesc && $desc !== '' && $split['description'] !== trim($desc)) {
                $set[] = 'description = ?';
                $vals[] = $split['description'] !== '' ? $split['description'] : null;
            }
            if ($set === []) {
                continue;
            }
            $vals[] = (int) $row['id'];
            $pdo->prepare('UPDATE qualification_issuers SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($vals);
            $migrated++;
        }

        return $migrated;
    }
}
