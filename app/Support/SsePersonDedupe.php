<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Déduplication des fiches personnes SSE (terminal SEEK → Athena).
 */
final class SsePersonDedupe
{
    public const KINDS = ['empreintes', 'iris', 'adn'];

    public static function normalizeKind(string $raw): string
    {
        $k = strtolower(trim($raw));
        $map = [
            'empreinte' => 'empreintes',
            'empreintes' => 'empreintes',
            'fingerprint' => 'empreintes',
            'fingerprints' => 'empreintes',
            'iris' => 'iris',
            'adn' => 'adn',
            'dna' => 'adn',
        ];

        return $map[$k] ?? 'empreintes';
    }

    /**
     * Clé d’identité (nom + prénom + alias), insensible à la casse.
     * Vide si aucune identité exploitable.
     */
    public static function identityKey(string $lastName, string $firstName, string $alias): string
    {
        $norm = static function (string $s): string {
            $s = trim(mb_strtolower($s));
            $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

            return $s;
        };

        $last = $norm($lastName);
        $first = $norm($firstName);
        $aka = $norm($alias);
        if ($last === '' && $first === '' && $aka === '') {
            return '';
        }

        return $last . "\n" . $first . "\n" . $aka;
    }

    /**
     * @param list<array<string, mixed>> $samples
     * @return list<string>
     */
    public static function kindsFromSamples(array $samples): array
    {
        $kinds = [];
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                continue;
            }
            $kind = self::normalizeKind((string) ($sample['kind'] ?? ''));
            if (!in_array($kind, self::KINDS, true)) {
                continue;
            }
            $kinds[$kind] = true;
        }

        return array_keys($kinds);
    }

    /**
     * Modalités vraiment nouvelles (absentes de la fiche déjà enregistrée).
     *
     * @param list<string>|list<array<string, mixed>> $existingKindsOrSamples
     * @param list<array<string, mixed>> $incomingSamples
     * @return list<string>
     */
    public static function newModalities(array $existingKindsOrSamples, array $incomingSamples, bool $genericBiometrics = false): array
    {
        $existing = [];
        foreach ($existingKindsOrSamples as $item) {
            if (is_string($item)) {
                $existing[self::normalizeKind($item)] = true;
                continue;
            }
            if (is_array($item)) {
                $existing[self::normalizeKind((string) ($item['kind'] ?? ''))] = true;
            }
        }

        $incoming = self::kindsFromSamples($incomingSamples);
        if ($genericBiometrics && $incoming === []) {
            $incoming[] = 'empreintes';
        }

        $fresh = [];
        foreach ($incoming as $kind) {
            if (!isset($existing[$kind])) {
                $fresh[] = $kind;
            }
        }

        return $fresh;
    }

    /**
     * Garde la plus ancienne fiche par identité (id le plus petit).
     *
     * @param list<array<string, mixed>> $persons
     * @return list<array<string, mixed>>
     */
    public static function collapseList(array $persons): array
    {
        $best = [];
        $order = [];
        foreach ($persons as $person) {
            if (!is_array($person)) {
                continue;
            }
            $key = self::identityKey(
                (string) ($person['last_name'] ?? ''),
                (string) ($person['first_name'] ?? ''),
                (string) ($person['alias'] ?? '')
            );
            if ($key === '') {
                $net = trim((string) ($person['target_unit_netid'] ?? ''));
                $key = $net !== '' ? 'net:' . $net : 'id:' . (int) ($person['id'] ?? 0);
            }
            $id = (int) ($person['id'] ?? 0);
            if (!isset($best[$key])) {
                $order[] = $key;
                $best[$key] = $person;
                continue;
            }
            $kept = $best[$key];
            if ($id < (int) ($kept['id'] ?? PHP_INT_MAX)) {
                $best[$key] = self::mergeCollapsed($person, $kept);
            } else {
                $best[$key] = self::mergeCollapsed($kept, $person);
            }
        }

        $out = [];
        foreach ($order as $key) {
            if (isset($best[$key])) {
                $out[] = $best[$key];
            }
        }

        return $out;
    }

    /**
     * Conserve la fiche la plus ancienne, en y rassemblant biométrie et photo des doublons.
     *
     * @param array<string, mixed> $kept
     * @param array<string, mixed> $other
     * @return array<string, mixed>
     */
    private static function mergeCollapsed(array $kept, array $other): array
    {
        $seen = [];
        $kinds = [];
        foreach (array_merge(
            is_array($kept['biometric_kinds'] ?? null) ? $kept['biometric_kinds'] : [],
            is_array($other['biometric_kinds'] ?? null) ? $other['biometric_kinds'] : []
        ) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $kind = self::normalizeKind((string) ($item['kind'] ?? ''));
            if ($kind === '' || isset($seen[$kind])) {
                continue;
            }
            $seen[$kind] = true;
            $kinds[] = $item + ['kind' => $kind];
        }
        $kept['biometric_kinds'] = $kinds;
        if (!empty($other['biometrics_simulated'])) {
            $kept['biometrics_simulated'] = true;
        }
        if (empty($kept['primary_photo']) && !empty($other['primary_photo'])) {
            $kept['primary_photo'] = $other['primary_photo'];
        }

        return $kept;
    }
}
