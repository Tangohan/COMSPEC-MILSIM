<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Instantané de ce qu'un membre a vu et confirmé : pour chaque bloc, les lignes affichées (libellé, valeur) et leur
 * empreinte. Comparer l'empreinte d'hier à celle d'aujourd'hui dit si la donnée a changé depuis la confirmation,
 * sans rien écrire dans la fiche.
 */
final class DataCampaignSnapshot
{
    /**
     * Lignes d'un bloc ramenées à un texte stable : libellé et valeur nettoyés, espaces réduits, longueur bornée.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array{label: string, value: string}>
     */
    public static function canonicalRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $label = self::clean($row['label'] ?? '');
            if ($label === '') {
                continue;
            }
            $out[] = ['label' => $label, 'value' => self::clean($row['value'] ?? '')];
        }

        return $out;
    }

    /** @param list<array<string, mixed>> $rows */
    public static function hash(array $rows): string
    {
        return hash('sha256', (string) json_encode(self::canonicalRows($rows), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array<string, list<array<string, mixed>>> $blocks lignes par bloc
     * @return array<string, array{hash: string, rows: list<array{label: string, value: string}>}>
     */
    public static function build(array $blocks): array
    {
        $out = [];
        foreach ($blocks as $key => $rows) {
            $canon = self::canonicalRows($rows);
            $out[(string) $key] = ['hash' => self::hash($canon), 'rows' => $canon];
        }

        return $out;
    }

    /**
     * Empreinte d'ensemble d'un instantané (ordre des blocs sans effet).
     *
     * @param array<string, mixed> $snapshot
     */
    public static function overallHash(array $snapshot): string
    {
        $hashes = [];
        foreach ($snapshot as $key => $block) {
            $hashes[(string) $key] = is_array($block) ? (string) ($block['hash'] ?? '') : '';
        }
        ksort($hashes);

        return hash('sha256', (string) json_encode($hashes));
    }

    /**
     * Blocs dont la donnée a changé depuis l'instantané. Les blocs « volatils » (heures) évoluent seuls et sont ignorés.
     *
     * @param array<string, mixed> $stored instantané enregistré (bloc => {hash, rows})
     * @param array<string, string> $currentHashes empreintes actuelles par bloc
     * @return list<string>
     */
    public static function changedBlocks(array $stored, array $currentHashes): array
    {
        $changed = [];
        foreach ($stored as $key => $block) {
            $key = (string) $key;
            if (DataCampaignBlocks::isVolatile($key) || !isset($currentHashes[$key]) || !is_array($block)) {
                continue;
            }
            if ((string) ($block['hash'] ?? '') !== $currentHashes[$key]) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    private static function clean(mixed $v): string
    {
        $s = is_scalar($v) ? (string) $v : '';
        $s = preg_replace('/\s+/u', ' ', trim($s)) ?? '';

        return mb_substr($s, 0, 500);
    }
}
