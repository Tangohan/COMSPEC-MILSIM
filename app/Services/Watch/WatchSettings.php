<?php

declare(strict_types=1);

namespace App\Services\Watch;

/**
 * Réglages de la surveillance (détecteurs du mod, mesure de charge, priorité sous charge).
 *
 * Tout vit dans platform_settings (administration plateforme) ; ce fichier ne porte que des valeurs par défaut
 * modestes et les bornes acceptées. Le ralentissement sous charge est désactivé par défaut.
 */
final class WatchSettings
{
    /** Ralentissement par palier sous saturation avérée : désactivé par défaut. */
    public const SHED_ENABLED = 'load_shed_enabled';
    public const SHED_PERCENT = 'load_shed_percent';
    public const ALERT_P95_MS = 'load_alert_p95_ms';
    public const ALERT_ERROR_PCT = 'load_alert_error_pct';
    public const ALERT_LOAD_PER_CORE = 'load_alert_load_per_core';

    public const DETECT_ENABLED = 'abuse_detect_enabled';
    public const SPEED_MPS = 'abuse_speed_mps';
    public const SPEED_HITS = 'abuse_speed_hits';
    public const POS_LIMIT_M = 'abuse_pos_limit_m';
    public const VOLUME_PER_MIN = 'abuse_volume_per_min';
    public const CRED_STEAMS = 'abuse_cred_steams';
    public const REFUSALS_PER_MIN = 'abuse_refusals_per_min';
    public const VERSION_CHANGES = 'abuse_version_changes';

    /**
     * clé => [défaut, minimum, maximum] ; les booléens valent 0 ou 1.
     *
     * @var array<string, array{0: float|int, 1: float|int, 2: float|int}>
     */
    private const DEFS = [
        self::SHED_ENABLED => [0, 0, 1],
        self::SHED_PERCENT => [60, 10, 100],
        self::ALERT_P95_MS => [2500, 500, 60000],
        self::ALERT_ERROR_PCT => [8, 1, 90],
        self::ALERT_LOAD_PER_CORE => [2.0, 0.5, 50.0],
        self::DETECT_ENABLED => [1, 0, 1],
        self::SPEED_MPS => [500, 100, 5000],
        self::SPEED_HITS => [3, 1, 50],
        self::POS_LIMIT_M => [45000, 10000, 400000],
        self::VOLUME_PER_MIN => [240, 30, 5000],
        self::CRED_STEAMS => [3, 2, 20],
        self::REFUSALS_PER_MIN => [30, 5, 1000],
        self::VERSION_CHANGES => [3, 2, 20],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::DEFS);
    }

    /** @return array<string, float|int> */
    public static function defaults(): array
    {
        $out = [];
        foreach (self::DEFS as $key => [$default]) {
            $out[$key] = $default;
        }

        return $out;
    }

    /**
     * Valeurs saines à partir de lignes platform_settings (texte) : absent ou illisible = défaut, hors bornes = borne.
     *
     * @param array<string, mixed> $raw
     * @return array<string, float|int>
     */
    public static function fromMap(array $raw): array
    {
        $out = [];
        foreach (self::DEFS as $key => [$default, $min, $max]) {
            $value = $raw[$key] ?? null;
            if (!is_scalar($value) || trim((string) $value) === '' || !is_numeric(str_replace(',', '.', trim((string) $value)))) {
                $out[$key] = $default;
                continue;
            }
            $number = (float) str_replace(',', '.', trim((string) $value));
            $number = max((float) $min, min((float) $max, $number));
            $out[$key] = is_float($default) ? round($number, 2) : (int) round($number);
        }

        return $out;
    }

    /**
     * Lecture des réglages enregistrés. Base ou table indisponible : valeurs par défaut (le ralentissement reste coupé).
     *
     * @return array<string, float|int>
     */
    public static function load(?\PDO $pdo = null): array
    {
        try {
            $pdo ??= \App\Core\Database::getPdo();
            $placeholders = implode(',', array_fill(0, count(self::DEFS), '?'));
            $st = $pdo->prepare('SELECT setting_key, value FROM platform_settings WHERE setting_key IN (' . $placeholders . ')');
            $st->execute(self::keys());
            $raw = [];
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $raw[(string) $row['setting_key']] = (string) $row['value'];
            }

            return self::fromMap($raw);
        } catch (\Throwable) {
            return self::defaults();
        }
    }

    /**
     * Valeurs d'un formulaire d'administration : champs absents = inchangés, cases décochées = 0.
     *
     * @param array<string, mixed> $input
     * @param array<string, float|int> $current
     * @param list<string> $checkboxes clés de cases à cocher du formulaire (absentes de la requête = décochées)
     * @return array<string, float|int>
     */
    public static function fromForm(array $input, array $current, array $checkboxes): array
    {
        $raw = [];
        foreach (self::keys() as $key) {
            if (in_array($key, $checkboxes, true)) {
                $raw[$key] = !empty($input[$key]) ? '1' : '0';
            } elseif (array_key_exists($key, $input)) {
                $raw[$key] = $input[$key];
            } else {
                $raw[$key] = $current[$key] ?? null;
            }
        }

        return self::fromMap($raw);
    }

    /** @return array{0: float|int, 1: float|int, 2: float|int} défaut, minimum, maximum */
    public static function bounds(string $key): array
    {
        return self::DEFS[$key] ?? [0, 0, 0];
    }
}
