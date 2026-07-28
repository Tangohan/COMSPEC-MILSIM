<?php

declare(strict_types=1);

namespace App\Services\Community;

/**
 * Référentiel des unités de forces spéciales réelles (assistant création communauté).
 * Triées par rattachement logique : commandement supérieur → composantes → unités → sous-unités.
 */
final class RealUnitAffiliationCatalog
{
    /** @return array<string, string> code ISO => libellé pays */
    public static function countryLabels(): array
    {
        return [
            'FR' => 'France',
            'US' => 'États-Unis',
            'DE' => 'Allemagne',
            'BE' => 'Belgique',
            'ES' => 'Espagne',
        ];
    }

    /** @return list<string> */
    public static function allowedCountryCodes(): array
    {
        return array_keys(self::countryLabels());
    }

    /**
     * @return list<array{id: string, name: string, tier: string, tier_order: int, indent: int}>
     */
    public static function unitsForCountry(string $countryCode): array
    {
        $code = strtoupper(trim($countryCode));
        $all = self::catalog();

        return $all[$code] ?? [];
    }

    /** @return array{id: string, name: string, tier: string, tier_order: int, indent: int}|null */
    public static function findUnit(string $countryCode, string $unitId): ?array
    {
        $id = trim($unitId);
        if ($id === '') {
            return null;
        }
        foreach (self::unitsForCountry($countryCode) as $u) {
            if (($u['id'] ?? '') === $id) {
                return $u;
            }
        }

        return null;
    }

    /**
     * @param list<string> $unitIds
     * @return list<array{id: string, name: string}>
     */
    public static function resolveSelectedUnits(string $countryCode, array $unitIds): array
    {
        $out = [];
        $seen = [];
        foreach ($unitIds as $raw) {
            if (!is_string($raw)) {
                continue;
            }
            $id = trim($raw);
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $u = self::findUnit($countryCode, $id);
            if ($u === null) {
                continue;
            }
            $seen[$id] = true;
            $out[] = ['id' => $u['id'], 'name' => $u['name']];
        }

        return $out;
    }

    /**
     * Payload JSON pour le front (assistant création).
     *
     * @return array{countries: array<string, string>, units: array<string, list<array<string, mixed>>>}
     */
    public static function frontendPayload(): array
    {
        $units = [];
        foreach (self::allowedCountryCodes() as $code) {
            $units[$code] = self::unitsForCountry($code);
        }

        return [
            'countries' => self::countryLabels(),
            'units' => $units,
        ];
    }

    /**
     * @return array<string, list<array{id: string, name: string, tier: string, tier_order: int, indent: int}>>
     */
    private static function catalog(): array
    {
        return [
            'FR' => self::franceUnits(),
            'US' => self::usaUnits(),
            'DE' => self::germanyUnits(),
            'BE' => self::belgiumUnits(),
            'ES' => self::spainUnits(),
        ];
    }

    /** @return list<array{id: string, name: string, tier: string, tier_order: int, indent: int}> */
    private static function franceUnits(): array
    {
        return self::sortUnits([
            ['id' => 'fr-cos', 'name' => 'Commandement des opérations spéciales (COS)', 'tier' => 'command', 'tier_order' => 0, 'indent' => 0],
            ['id' => 'fr-cfor', 'name' => 'Centre de formation des forces spéciales (CFOR)', 'tier' => 'component', 'tier_order' => 1, 'indent' => 1],
            ['id' => 'fr-1rpima', 'name' => '1er régiment de parachutistes d’infanterie de marine (1er RPIMa)', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 1],
            ['id' => 'fr-13rdp', 'name' => '13e régiment de dragons parachutistes (13e RDP)', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 1],
            ['id' => 'fr-forfusco', 'name' => 'Force maritime des fusiliers marins et commandos (FORFUSCO)', 'tier' => 'component', 'tier_order' => 1, 'indent' => 1],
            ['id' => 'fr-cdo-hubert', 'name' => 'Commando Hubert', 'tier' => 'subunit', 'tier_order' => 3, 'indent' => 2],
            ['id' => 'fr-cdo-jaubert', 'name' => 'Commando Jaubert', 'tier' => 'subunit', 'tier_order' => 3, 'indent' => 2],
            ['id' => 'fr-cdo-kieffer', 'name' => 'Commando Kieffer', 'tier' => 'subunit', 'tier_order' => 3, 'indent' => 2],
            ['id' => 'fr-cdo-ponchardier', 'name' => 'Commando Ponchardier', 'tier' => 'subunit', 'tier_order' => 3, 'indent' => 2],
            ['id' => 'fr-cdo-penfentenyo', 'name' => 'Commando de Penfentenyo', 'tier' => 'subunit', 'tier_order' => 3, 'indent' => 2],
            ['id' => 'fr-cdo-trepel', 'name' => 'Commando Trepel', 'tier' => 'subunit', 'tier_order' => 3, 'indent' => 2],
            ['id' => 'fr-cdo-montfort', 'name' => 'Commando de Montfort', 'tier' => 'subunit', 'tier_order' => 3, 'indent' => 2],
            ['id' => 'fr-cpa10', 'name' => 'Composante air — CPA 10 (forces spéciales)', 'tier' => 'component', 'tier_order' => 1, 'indent' => 1],
            ['id' => 'fr-gign', 'name' => 'Groupe d’intervention de la Gendarmerie nationale (GIGN)', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 1],
        ]);
    }

    /** @return list<array{id: string, name: string, tier: string, tier_order: int, indent: int}> */
    private static function usaUnits(): array
    {
        return self::sortUnits([
            ['id' => 'us-ussocom', 'name' => 'United States Special Operations Command (USSOCOM)', 'tier' => 'command', 'tier_order' => 0, 'indent' => 0],
            ['id' => 'us-jsoc', 'name' => 'Joint Special Operations Command (JSOC)', 'tier' => 'command', 'tier_order' => 0, 'indent' => 1],
            ['id' => 'us-delta', 'name' => '1st Special Forces Operational Detachment-Delta (Delta Force)', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 2],
            ['id' => 'us-devgru', 'name' => 'Naval Special Warfare Development Group (DEVGRU / SEAL Team Six)', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 2],
            ['id' => 'us-usasoc', 'name' => 'U.S. Army Special Operations Command (USASOC)', 'tier' => 'component', 'tier_order' => 1, 'indent' => 1],
            ['id' => 'us-1sfg', 'name' => '1st Special Forces Group (Airborne)', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 2],
            ['id' => 'us-3sfg', 'name' => '3rd Special Forces Group (Airborne)', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 2],
            ['id' => 'us-5sfg', 'name' => '5th Special Forces Group (Airborne)', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 2],
            ['id' => 'us-7sfg', 'name' => '7th Special Forces Group (Airborne)', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 2],
            ['id' => 'us-10sfg', 'name' => '10th Special Forces Group (Airborne)', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 2],
            ['id' => 'us-75rr', 'name' => '75th Ranger Regiment', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 2],
            ['id' => 'us-75rr-1bn', 'name' => '75th Ranger Regiment — 1st Battalion', 'tier' => 'subunit', 'tier_order' => 3, 'indent' => 3],
            ['id' => 'us-75rr-2bn', 'name' => '75th Ranger Regiment — 2nd Battalion', 'tier' => 'subunit', 'tier_order' => 3, 'indent' => 3],
            ['id' => 'us-75rr-3bn', 'name' => '75th Ranger Regiment — 3rd Battalion', 'tier' => 'subunit', 'tier_order' => 3, 'indent' => 3],
            ['id' => 'us-nswc', 'name' => 'Naval Special Warfare Command (NSWC)', 'tier' => 'component', 'tier_order' => 1, 'indent' => 1],
            ['id' => 'us-seal-team-1', 'name' => 'SEAL Team 1', 'tier' => 'subunit', 'tier_order' => 3, 'indent' => 2],
            ['id' => 'us-seal-team-2', 'name' => 'SEAL Team 2', 'tier' => 'subunit', 'tier_order' => 3, 'indent' => 2],
            ['id' => 'us-seal-team-3', 'name' => 'SEAL Team 3', 'tier' => 'subunit', 'tier_order' => 3, 'indent' => 2],
            ['id' => 'us-seal-team-4', 'name' => 'SEAL Team 4', 'tier' => 'subunit', 'tier_order' => 3, 'indent' => 2],
            ['id' => 'us-seal-team-5', 'name' => 'SEAL Team 5', 'tier' => 'subunit', 'tier_order' => 3, 'indent' => 2],
            ['id' => 'us-seal-team-7', 'name' => 'SEAL Team 7', 'tier' => 'subunit', 'tier_order' => 3, 'indent' => 2],
            ['id' => 'us-seal-team-8', 'name' => 'SEAL Team 8', 'tier' => 'subunit', 'tier_order' => 3, 'indent' => 2],
            ['id' => 'us-seal-team-10', 'name' => 'SEAL Team 10', 'tier' => 'subunit', 'tier_order' => 3, 'indent' => 2],
            ['id' => 'us-afsoc', 'name' => 'Air Force Special Operations Command (AFSOC)', 'tier' => 'component', 'tier_order' => 1, 'indent' => 1],
            ['id' => 'us-24sow', 'name' => '24th Special Operations Wing', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 2],
            ['id' => 'us-marsoc', 'name' => 'Marine Forces Special Operations Command (MARSOC / Raiders)', 'tier' => 'component', 'tier_order' => 1, 'indent' => 1],
        ]);
    }

    /** @return list<array{id: string, name: string, tier: string, tier_order: int, indent: int}> */
    private static function germanyUnits(): array
    {
        return self::sortUnits([
            ['id' => 'de-kdo-sok', 'name' => 'Kommando Spezialkräfte (KSK)', 'tier' => 'command', 'tier_order' => 0, 'indent' => 0],
            ['id' => 'de-ksk-hq', 'name' => 'KSK — État-major et état-major interarmées', 'tier' => 'component', 'tier_order' => 1, 'indent' => 1],
            ['id' => 'de-ksk-kompanien', 'name' => 'KSK — Compagnies opérationnelles', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 1],
            ['id' => 'de-ksm', 'name' => 'Kommando Spezialoperationen der Marine (KSM)', 'tier' => 'command', 'tier_order' => 0, 'indent' => 0],
            ['id' => 'de-ksm-kompanien', 'name' => 'KSM — Compagnies de combat swimmers', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 1],
        ]);
    }

    /** @return list<array{id: string, name: string, tier: string, tier_order: int, indent: int}> */
    private static function belgiumUnits(): array
    {
        return self::sortUnits([
            ['id' => 'be-sf-gp', 'name' => 'Special Forces Group (SF Gp)', 'tier' => 'command', 'tier_order' => 0, 'indent' => 0],
            ['id' => 'be-sf-gp-hq', 'name' => 'SF Gp — État-major', 'tier' => 'component', 'tier_order' => 1, 'indent' => 1],
            ['id' => 'be-sf-gp-1st', 'name' => 'SF Gp — 1re compagnie opérationnelle', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 1],
            ['id' => 'be-sf-gp-2nd', 'name' => 'SF Gp — 2e compagnie opérationnelle', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 1],
            ['id' => 'be-sf-gp-3rd', 'name' => 'SF Gp — 3e compagnie opérationnelle', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 1],
            ['id' => 'be-para-cdo', 'name' => 'Régiment Para-Commando', 'tier' => 'component', 'tier_order' => 1, 'indent' => 0],
            ['id' => 'be-2para', 'name' => '2e Bataillon Para', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 1],
            ['id' => 'be-3para', 'name' => '3e Bataillon Para', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 1],
        ]);
    }

    /** @return list<array{id: string, name: string, tier: string, tier_order: int, indent: int}> */
    private static function spainUnits(): array
    {
        return self::sortUnits([
            ['id' => 'es-moe', 'name' => 'Mando de Operaciones Especiales (MOE)', 'tier' => 'command', 'tier_order' => 0, 'indent' => 0],
            ['id' => 'es-boe-i', 'name' => 'MOE — Bataillon d’opérations spéciales « Órdenes» I', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 1],
            ['id' => 'es-boe-ii', 'name' => 'MOE — Bataillon d’opérations spéciales « Órdenes» II', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 1],
            ['id' => 'es-boe-iii', 'name' => 'MOE — Bataillon d’opérations spéciales « Órdenes» III', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 1],
            ['id' => 'es-uoe', 'name' => 'Unidad de Operaciones Especiales (UOE) — Armada', 'tier' => 'command', 'tier_order' => 0, 'indent' => 0],
            ['id' => 'es-ezapac', 'name' => 'Escuadrón de Apoyo al Despliegue Inmediato (EZAPAC) — Ejército del Aire', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 0],
            ['id' => 'es-grupo-oe', 'name' => 'Grupo de Operaciones Especiales (GOE) — Guardia Civil', 'tier' => 'unit', 'tier_order' => 2, 'indent' => 0],
        ]);
    }

    /**
     * @param list<array{id: string, name: string, tier: string, tier_order: int, indent: int}> $units
     * @return list<array{id: string, name: string, tier: string, tier_order: int, indent: int}>
     */
    private static function sortUnits(array $units): array
    {
        usort($units, static function (array $a, array $b): int {
            $ao = (int) ($a['tier_order'] ?? 99);
            $bo = (int) ($b['tier_order'] ?? 99);
            if ($ao !== $bo) {
                return $ao <=> $bo;
            }

            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $units;
    }
}
