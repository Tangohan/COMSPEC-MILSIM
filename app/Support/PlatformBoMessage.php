<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Messages des administrateurs de la plateforme aux back-offices des communautés : règles pures (assainissement,
 * liens, ciblage, langue, fenêtre de diffusion). Aucun accès base : tout est testable seul.
 *
 * Le texte saisi est du texte brut : les balises sont retirées à l'enregistrement, tout est échappé à l'affichage,
 * et seuls les liens http, https et mailto valides sont rendus cliquables.
 */
final class PlatformBoMessage
{
    public const LEVELS = ['info', 'important', 'urgent'];

    public const MODE_ALL = 'all';
    public const MODE_TENANTS = 'tenants';
    public const MODE_PLANS = 'plans';
    public const MODE_TYPES = 'types';
    public const MODES = [self::MODE_ALL, self::MODE_TENANTS, self::MODE_PLANS, self::MODE_TYPES];

    public const MAX_ACTIVE = 10;
    public const MAX_TENANTS = 500;
    public const TITLE_MAX = 120;
    public const BODY_MAX = 800;
    public const CTA_LABEL_MAX = 60;
    public const URL_MAX = 500;

    public static function level(?string $raw): string
    {
        $raw = strtolower(trim((string) $raw));

        return in_array($raw, self::LEVELS, true) ? $raw : 'info';
    }

    /** Texte brut : sans balises ni caractères de contrôle, longueur bornée. */
    public static function cleanText(mixed $raw, int $max, bool $multiline = false): string
    {
        $s = is_scalar($raw) ? (string) $raw : '';
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
        $s = strip_tags($s);
        if ($multiline) {
            $s = preg_replace("/[ \t]+/u", ' ', $s) ?? $s;
            $s = preg_replace("/\n{3,}/", "\n\n", $s) ?? $s;
        } else {
            $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        }
        $s = trim($s);

        return mb_substr($s, 0, $max);
    }

    /**
     * Adresse sûre ou null. Boutons : http et https. Texte : aussi mailto.
     *
     * @param list<string> $schemes
     */
    public static function safeUrl(mixed $raw, array $schemes = ['https', 'http']): ?string
    {
        $url = is_scalar($raw) ? trim((string) $raw) : '';
        if ($url === '' || strlen($url) > self::URL_MAX || preg_match('/[\s\x00-\x1F\x7F<>"\'\\\\]/', $url) === 1) {
            return null;
        }
        $p = parse_url($url);
        if (!is_array($p) || !isset($p['scheme'])) {
            return null;
        }
        $scheme = strtolower((string) $p['scheme']);
        if (!in_array($scheme, $schemes, true)) {
            return null;
        }
        if ($scheme === 'mailto') {
            $addr = (string) ($p['path'] ?? '');

            return filter_var($addr, FILTER_VALIDATE_EMAIL) !== false ? 'mailto:' . $addr : null;
        }
        $host = (string) ($p['host'] ?? '');
        if ($host === '' || isset($p['user']) || isset($p['pass'])
            || preg_match('/^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$/', $host) !== 1) {
            return null;
        }

        return $url;
    }

    /** Texte échappé, retours à la ligne conservés, liens valides cliquables. */
    public static function renderBody(string $text): string
    {
        $parts = preg_split('#((?:https?://|mailto:)[^\s<>"\']+)#iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $out = '';
        foreach ($parts as $i => $part) {
            if ($i % 2 === 1) {
                $trail = '';
                if (preg_match('/[.,;:!?)]+$/', $part, $m) === 1) {
                    $trail = $m[0];
                    $part = substr($part, 0, -strlen($trail));
                }
                $safe = self::safeUrl($part, ['https', 'http', 'mailto']);
                if ($safe !== null) {
                    $out .= '<a href="' . self::e($safe) . '" target="_blank" rel="noopener noreferrer nofollow">' . self::e($part) . '</a>' . self::e($trail);
                    continue;
                }
                $part .= $trail;
            }
            $out .= self::e($part);
        }

        return nl2br($out, false);
    }

    /** Champ localisé avec repli sur l'autre langue. */
    public static function localized(array $row, string $field, string $locale): string
    {
        $primary = $locale === 'fr' ? 'fr' : 'en';
        $other = $primary === 'fr' ? 'en' : 'fr';
        $v = trim((string) ($row[$field . '_' . $primary] ?? ''));

        return $v !== '' ? $v : trim((string) ($row[$field . '_' . $other] ?? ''));
    }

    public static function parseDateTime(mixed $raw): ?string
    {
        $s = is_scalar($raw) ? trim((string) $raw) : '';
        if ($s === '') {
            return null;
        }
        $t = strtotime($s);

        return $t === false ? null : date('Y-m-d H:i:s', $t);
    }

    /** Diffusé maintenant : actif et dans la fenêtre de dates. */
    public static function isLive(array $row, ?int $now = null): bool
    {
        if (empty($row['is_active'])) {
            return false;
        }
        $now ??= time();
        $s = isset($row['starts_at']) && $row['starts_at'] !== '' ? strtotime((string) $row['starts_at']) : null;
        $e = isset($row['ends_at']) && $row['ends_at'] !== '' ? strtotime((string) $row['ends_at']) : null;

        return ($s === null || $s === false || $s <= $now) && ($e === null || $e === false || $e >= $now);
    }

    /**
     * Ciblage validé côté serveur : seuls les identifiants, formules et profils connus sont gardés.
     *
     * @param array<int|string, mixed> $tenantIds
     * @param array<int|string, mixed> $plans
     * @param array<int|string, mixed> $types
     * @param list<int> $knownTenantIds
     * @param list<string> $knownPlans
     * @param list<string> $knownTypes
     * @return array{mode: string, values: list<int|string>, error: ?string}
     */
    public static function normalizeTarget(
        mixed $mode,
        array $tenantIds,
        array $plans,
        array $types,
        array $knownTenantIds,
        array $knownPlans,
        array $knownTypes
    ): array {
        $mode = is_string($mode) && in_array($mode, self::MODES, true) ? $mode : self::MODE_ALL;
        if ($mode === self::MODE_ALL) {
            return ['mode' => $mode, 'values' => [], 'error' => null];
        }
        if ($mode === self::MODE_TENANTS) {
            $ids = array_values(array_unique(array_map('intval', array_filter($tenantIds, 'is_scalar'))));
            $ids = array_values(array_intersect($ids, $knownTenantIds));
            if ($ids === []) {
                return ['mode' => $mode, 'values' => [], 'error' => 'Choisissez au moins une communauté existante.'];
            }
            if (count($ids) > self::MAX_TENANTS) {
                return ['mode' => $mode, 'values' => [], 'error' => 'Trop de communautés : utilisez plutôt un ciblage par formule ou par type.'];
            }
            sort($ids);

            return ['mode' => $mode, 'values' => $ids, 'error' => null];
        }
        $raw = $mode === self::MODE_PLANS ? $plans : $types;
        $known = $mode === self::MODE_PLANS ? $knownPlans : $knownTypes;
        $vals = array_values(array_unique(array_map('strval', array_filter($raw, 'is_scalar'))));
        $vals = array_values(array_intersect($vals, $known));
        if ($vals === []) {
            return ['mode' => $mode, 'values' => [], 'error' => $mode === self::MODE_PLANS
                ? 'Choisissez au moins une formule existante.'
                : 'Choisissez au moins un type de communauté existant.'];
        }
        sort($vals);

        return ['mode' => $mode, 'values' => $vals, 'error' => null];
    }

    /** La communauté (identifiant, formule, type) est-elle visée par ce message ? Seule porte d'entrée du ciblage. */
    public static function targets(array $row, int $tenantId, string $planSlug, string $tenantType): bool
    {
        if ($tenantId < 1) {
            return false;
        }
        $mode = (string) ($row['target_mode'] ?? self::MODE_ALL);
        $values = self::decodeValues($row['target_json'] ?? null);
        switch ($mode) {
            case self::MODE_ALL:
                return true;
            case self::MODE_TENANTS:
                return in_array($tenantId, array_map('intval', $values), true);
            case self::MODE_PLANS:
                return in_array($planSlug, array_map('strval', $values), true);
            case self::MODE_TYPES:
                return in_array($tenantType, array_map('strval', $values), true);
            default:
                return false;
        }
    }

    /** @return list<mixed> */
    public static function decodeValues(mixed $json): array
    {
        if (is_array($json)) {
            return array_values($json);
        }
        $d = is_string($json) && $json !== '' ? json_decode($json, true) : null;

        return is_array($d) ? array_values($d) : [];
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
