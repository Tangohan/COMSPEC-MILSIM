<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use RuntimeException;

/**
 * Nomenclatures publiques versionnées. Le chargement est explicite dans l'étape 4910 :
 * aucune création de schéma à la lecture et aucune attribution de spécialité à une personne.
 * L'import ajoute les références absentes sans réécrire les corrections ou archivages d'un administrateur.
 */
final class MilitarySpecialtyCatalog
{
    public const FILE = 'config/military_specialties/catalog.json';

    /** @return array<string, mixed> */
    public static function data(): array
    {
        $path = dirname(__DIR__, 2) . '/' . self::FILE;
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !is_array($data['sources'] ?? null) || !is_array($data['systems'] ?? null)) {
            throw new RuntimeException('Le catalogue des nomenclatures militaires est invalide.');
        }

        return $data;
    }

    /** @return list<array<string, mixed>> */
    public static function entries(): array
    {
        $entries = [];
        foreach (self::data()['systems'] as $system) {
            foreach ($system['entries'] as $entry) {
                $entries[] = $entry + [
                    'system_key' => $system['system_key'],
                    'system_name' => $system['name'],
                    'country' => $system['country'],
                    'service_code' => $system['service_code'],
                ];
            }
        }

        return $entries;
    }

    /** Ajouts seulement. Les attributions, emplois, grades et capacités existants ne sont jamais touchés. */
    public static function seed(PDO $pdo): int
    {
        $data = self::data();
        $added = 0;
        $sources = [];
        foreach ($data['sources'] as $key => $source) {
            $sources[$key] = self::insertMissing($pdo, 'military_sources', ['url' => $source['url']], [
                'name' => $source['name'],
                'publisher' => $source['publisher'],
                'url' => $source['url'],
                'source_type' => 'institutional',
                'published_at' => $source['published_at'],
                'checked_at' => $data['checked_at'],
            ], $added);
        }

        $countries = [];
        foreach ([['US', 'USA', 'États-Unis', 'United States'], ['FR', 'FRA', 'France', 'France'], ['GB', 'GBR', 'Royaume-Uni', 'United Kingdom']] as [$iso2, $iso3, $fr, $en]) {
            $countries[$iso2] = self::insertMissing($pdo, 'countries', ['iso2' => $iso2], [
                'iso2' => $iso2, 'iso3' => $iso3, 'name_fr' => $fr, 'name_en' => $en,
            ], $added);
        }
        $serviceNames = [
            'us-airforce' => 'United States Air Force', 'us-army' => 'United States Army',
            'fr-terre' => 'Armée de Terre', 'fr-aae' => 'Armée de l’Air et de l’Espace',
            'fr-marine' => 'Marine nationale', 'gb-raf' => 'Royal Air Force',
        ];
        foreach ($data['systems'] as $system) {
            $serviceId = self::insertMissing($pdo, 'military_services', ['code' => $system['service_code']], [
                'country_id' => $countries[$system['country']],
                'code' => $system['service_code'],
                'name' => $serviceNames[$system['service_code']],
            ], $added);
            // Une référence déjà présente avec un pays différent exige une correction explicite.
            $service = $pdo->prepare('SELECT country_id FROM military_services WHERE id = ?');
            $service->execute([$serviceId]);
            if ((int) $service->fetchColumn() !== $countries[$system['country']]) {
                throw new RuntimeException('Le pays du service militaire est incohérent : ' . $system['service_code']);
            }
            $systemId = self::insertMissing($pdo, 'military_specialty_systems', ['system_key' => $system['system_key']], [
                'service_id' => $serviceId,
                'system_key' => $system['system_key'],
                'name' => $system['name'],
                'personnel_category' => $system['personnel_category'],
                'source_id' => $sources[$system['source']],
            ], $added);
            $storedSystem = $pdo->prepare('SELECT service_id FROM military_specialty_systems WHERE id = ?');
            $storedSystem->execute([$systemId]);
            if ((int) $storedSystem->fetchColumn() !== $serviceId) {
                throw new RuntimeException('Le service de la nomenclature militaire est incohérent : ' . $system['system_key']);
            }
            foreach ($system['entries'] as $entry) {
                $row = array_intersect_key($entry, array_flip([
                    'reference_key', 'code', 'official_title', 'label_fr', 'label_en', 'kind', 'description',
                    'source_locator', 'verification_status', 'verified_at',
                ]));
                $row['is_pattern'] = !empty($entry['is_pattern']) ? 1 : 0;
                $row['system_id'] = $systemId;
                $row['source_id'] = $sources[$entry['source']];
                $codeId = self::insertMissing($pdo, 'military_specialty_codes', ['reference_key' => $entry['reference_key']], $row, $added);
                $storedCode = $pdo->prepare('SELECT system_id, code FROM military_specialty_codes WHERE id = ?');
                $storedCode->execute([$codeId]);
                $stored = $storedCode->fetch(PDO::FETCH_ASSOC);
                if (!$stored || (int) $stored['system_id'] !== $systemId || $stored['code'] !== $entry['code']) {
                    // Une correction d'identité est conservée, sans lui appliquer les niveaux de l'ancien métier.
                    continue;
                }
                foreach ($entry['levels'] as $level) {
                    $levelRow = array_intersect_key($level, array_flip([
                        'level_code', 'official_title', 'label_fr', 'label_en', 'awarded_code', 'sort_order',
                        'source_locator', 'verification_status', 'verified_at',
                    ]));
                    $levelRow['specialty_code_id'] = $codeId;
                    $levelRow['source_id'] = $sources[$level['source']];
                    self::insertMissing($pdo, 'military_specialty_levels', [
                        'specialty_code_id' => $codeId, 'level_code' => $level['level_code'],
                    ], $levelRow, $added);
                }
            }
        }

        return $added;
    }

    /**
     * @param array<string, int|string> $identity
     * @param array<string, mixed> $row
     */
    private static function insertMissing(PDO $pdo, string $table, array $identity, array $row, int &$added): int
    {
        // Noms de tables/colonnes exclusivement internes à ce fichier et au catalogue versionné.
        $where = implode(' AND ', array_map(static fn (string $column): string => '`' . $column . '` = ?', array_keys($identity)));
        $find = $pdo->prepare('SELECT id FROM `' . $table . '` WHERE ' . $where . ' ORDER BY id LIMIT 1');
        $find->execute(array_values($identity));
        $id = $find->fetchColumn();
        $find->closeCursor();
        if ($id !== false) {
            return (int) $id;
        }
        $columns = '`' . implode('`, `', array_keys($row)) . '`';
        $placeholders = implode(', ', array_fill(0, count($row), '?'));
        $insert = $pdo->prepare('INSERT INTO `' . $table . '` (' . $columns . ') VALUES (' . $placeholders . ')');
        $insert->execute(array_values($row));
        $added++;

        return (int) $pdo->lastInsertId();
    }
}
