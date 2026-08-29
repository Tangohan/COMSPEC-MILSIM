<?php

declare(strict_types=1);

namespace App\Services\Rank;

/**
 * Valide les codes OTAN et la matrice de référence FR ARMY.
 * Ne dérive JAMAIS un code depuis hierarchy_order.
 */
final class RankReferenceValidator
{
    public const NATO_OF_PATTERN = '/^OF-[1-9]$/';

    public const NATO_OR_PATTERN = '/^OR-[1-9]$/';

    public const US_O_PATTERN = '/^O-(1[0]|[1-9])$/';

    public const US_E_PATTERN = '/^E-[1-9]$/';

    /**
     * Matrice FR ARMY attendue — codes OTAN explicites (pas calculés).
     *
     * @return array<string, array{nato_code: string, category: string, hierarchy_order: int, short_name: string}>
     */
    public static function expectedFrArmy(): array
    {
        return [
            'Soldat' => ['nato_code' => 'OR-1', 'category' => 'ENLISTED', 'hierarchy_order' => 10, 'short_name' => 'Sdt'],
            'Soldat de 2e classe' => ['nato_code' => 'OR-1', 'category' => 'ENLISTED', 'hierarchy_order' => 10, 'short_name' => 'Sdt 2'],
            'Soldat de 1re classe' => ['nato_code' => 'OR-2', 'category' => 'ENLISTED', 'hierarchy_order' => 20, 'short_name' => 'Sdt 1'],
            'Caporal' => ['nato_code' => 'OR-3', 'category' => 'ENLISTED', 'hierarchy_order' => 30, 'short_name' => 'Cpl'],
            'Caporal-chef' => ['nato_code' => 'OR-4', 'category' => 'ENLISTED', 'hierarchy_order' => 40, 'short_name' => 'Cch'],
            'Sergent' => ['nato_code' => 'OR-5', 'category' => 'NCO', 'hierarchy_order' => 50, 'short_name' => 'Sgt'],
            'Sergent-chef' => ['nato_code' => 'OR-6', 'category' => 'NCO', 'hierarchy_order' => 60, 'short_name' => 'Sch'],
            'Adjudant' => ['nato_code' => 'OR-7', 'category' => 'NCO', 'hierarchy_order' => 70, 'short_name' => 'Adj'],
            'Adjudant-chef' => ['nato_code' => 'OR-8', 'category' => 'SENIOR_NCO', 'hierarchy_order' => 80, 'short_name' => 'Adc'],
            'Major' => ['nato_code' => 'OR-9', 'category' => 'SENIOR_NCO', 'hierarchy_order' => 90, 'short_name' => 'Maj'],
            'Sous-lieutenant' => ['nato_code' => 'OF-1', 'category' => 'OFFICER', 'hierarchy_order' => 110, 'short_name' => 'Slt'],
            'Lieutenant' => ['nato_code' => 'OF-1', 'category' => 'OFFICER', 'hierarchy_order' => 120, 'short_name' => 'Lt'],
            'Capitaine' => ['nato_code' => 'OF-2', 'category' => 'OFFICER', 'hierarchy_order' => 130, 'short_name' => 'Cne'],
            'Commandant' => ['nato_code' => 'OF-3', 'category' => 'OFFICER', 'hierarchy_order' => 140, 'short_name' => 'Cdt'],
            'Lieutenant-colonel' => ['nato_code' => 'OF-4', 'category' => 'OFFICER', 'hierarchy_order' => 150, 'short_name' => 'Lcl'],
            'Colonel' => ['nato_code' => 'OF-5', 'category' => 'OFFICER', 'hierarchy_order' => 160, 'short_name' => 'Col'],
            'Général de brigade' => ['nato_code' => 'OF-6', 'category' => 'GENERAL_OFFICER', 'hierarchy_order' => 170, 'short_name' => 'Gén. bde'],
            'Général de division' => ['nato_code' => 'OF-7', 'category' => 'GENERAL_OFFICER', 'hierarchy_order' => 180, 'short_name' => 'Gén. div.'],
            'Général de corps d’armée' => ['nato_code' => 'OF-8', 'category' => 'GENERAL_OFFICER', 'hierarchy_order' => 190, 'short_name' => 'Gén. c. a.'],
            'Général d’armée' => ['nato_code' => 'OF-9', 'category' => 'GENERAL_OFFICER', 'hierarchy_order' => 200, 'short_name' => 'Gén. armée'],
        ];
    }

    /**
     * Alias de noms (legacy seeds) → nom canonique.
     *
     * @return array<string, string>
     */
    public static function nameAliases(): array
    {
        return [
            'Soldat de 2e classe' => 'Soldat de 2e classe',
            'Sdt 2' => 'Soldat de 2e classe',
            'Sdt 1' => 'Soldat de 1re classe',
            'Cpl' => 'Caporal',
            'Cch' => 'Caporal-chef',
            'Sgt' => 'Sergent',
            'Sch' => 'Sergent-chef',
            'Adj' => 'Adjudant',
            'Adc' => 'Adjudant-chef',
            'Maj' => 'Major',
            'SL' => 'Sous-lieutenant',
            'Slt' => 'Sous-lieutenant',
            'LT' => 'Lieutenant',
            'Lt' => 'Lieutenant',
            'CNE' => 'Capitaine',
            'Cne' => 'Capitaine',
            'CDT' => 'Commandant',
            'Cdt' => 'Commandant',
            'LCL' => 'Lieutenant-colonel',
            'Lcl' => 'Lieutenant-colonel',
            'Lieutenant colonel' => 'Lieutenant-colonel',
            'COL' => 'Colonel',
            'Col' => 'Colonel',
            'GBR' => 'Général de brigade',
            'Gén. bde' => 'Général de brigade',
            'GDV' => 'Général de division',
            'Gén. div.' => 'Général de division',
            'GCA' => 'Général de corps d’armée',
            'Gén. c. a.' => 'Général de corps d’armée',
            'GAR' => 'Général d’armée',
            'Gén. armée' => 'Général d’armée',
            "General de corps d'armee" => 'Général de corps d’armée',
            "General d'armee" => 'Général d’armée',
        ];
    }

    public function isValidNatoCode(?string $code, bool $allowUsDomestic = false): bool
    {
        if ($code === null || trim($code) === '') {
            return true; /* null autorisé (UNVERIFIED) */
        }
        $c = strtoupper(trim($code));
        if (preg_match(self::NATO_OF_PATTERN, $c) || preg_match(self::NATO_OR_PATTERN, $c)) {
            return true;
        }
        if ($allowUsDomestic && (preg_match(self::US_O_PATTERN, $c) || preg_match(self::US_E_PATTERN, $c))) {
            return true;
        }
        if ($c === 'WO' || preg_match('/^WO-[1-5]$/', $c)) {
            return true;
        }

        return false;
    }

    /**
     * Interdit toute invention OF-{n} depuis un ordre numérique.
     */
    public function assertNatoNotDerivedFromOrder(?string $natoCode, int $hierarchyOrder): bool
    {
        if ($natoCode === null || $natoCode === '') {
            return true;
        }
        if (preg_match('/^(OF|OR)-(\d+)$/i', $natoCode, $m)) {
            $n = (int) $m[2];
            /* Heuristique anti-dérivation : OF-4 ne doit pas « venir » de order=4. */
            if ($n === $hierarchyOrder) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{status: string, expected_nato: ?string, canonical_name: ?string, message: string}
     */
    public function evaluateFrArmyRow(string $nameOrCode, ?string $storedNato): array
    {
        $canonical = $this->resolveCanonicalName($nameOrCode);
        if ($canonical === null) {
            return [
                'status' => 'UNVERIFIED',
                'expected_nato' => null,
                'canonical_name' => null,
                'message' => 'Grade hors matrice FR ARMY de référence.',
            ];
        }
        $expected = self::expectedFrArmy()[$canonical];
        $stored = $storedNato !== null && $storedNato !== '' ? strtoupper(trim($storedNato)) : null;
        $want = strtoupper($expected['nato_code']);
        if ($stored === null) {
            return [
                'status' => 'UNVERIFIED',
                'expected_nato' => $want,
                'canonical_name' => $canonical,
                'message' => 'Code OTAN manquant — attendu ' . $want . '.',
            ];
        }
        if ($stored !== $want) {
            return [
                'status' => 'INVALID',
                'expected_nato' => $want,
                'canonical_name' => $canonical,
                'message' => $canonical . ' : stocké ' . $stored . ', attendu ' . $want . '.',
            ];
        }

        return [
            'status' => 'VERIFIED',
            'expected_nato' => $want,
            'canonical_name' => $canonical,
            'message' => 'Correspondance OTAN conforme.',
        ];
    }

    public function resolveCanonicalName(string $raw): ?string
    {
        $t = trim($raw);
        if ($t === '') {
            return null;
        }
        $expected = self::expectedFrArmy();
        if (isset($expected[$t])) {
            return $t;
        }
        $aliases = self::nameAliases();
        if (isset($aliases[$t])) {
            return $aliases[$t];
        }
        foreach ($expected as $name => $_) {
            if (strcasecmp($name, $t) === 0) {
                return $name;
            }
        }

        return null;
    }
}
