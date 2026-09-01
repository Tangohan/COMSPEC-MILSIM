<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Libellés et résumés d’affichage pour les alertes plateforme (back-office).
 */
final class PlatformAlertPresentation
{
    /** @var array<string, string> */
    private const KIND_LABELS = [
        'info' => 'Info',
        'novelty' => 'Nouveauté',
        'discount' => 'Promo / remise',
        'urgent' => 'Urgent',
    ];

    /** @var list<string> */
    private const MONTHS_FR = [
        'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin',
        'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.',
    ];

    /**
     * @return array<string, string> value => label (ordre d’affichage formulaire)
     */
    public static function kindOptions(): array
    {
        return self::KIND_LABELS;
    }

    public static function kindLabel(string $kind): string
    {
        return self::KIND_LABELS[$kind] ?? $kind;
    }

    /**
     * Badge Tailwind (couleur discrète par type).
     *
     * @return array{class: string, ring: string}
     */
    public static function kindBadgeClasses(string $kind): array
    {
        return match ($kind) {
            'urgent' => ['class' => 'bg-rose-100 text-rose-900 ring-rose-200/80', 'ring' => 'ring-1'],
            'discount' => ['class' => 'bg-amber-100 text-amber-950 ring-amber-200/80', 'ring' => 'ring-1'],
            'novelty' => ['class' => 'bg-emerald-50 text-emerald-900 ring-emerald-200/80', 'ring' => 'ring-1'],
            default => ['class' => 'bg-slate-100 text-slate-800 ring-slate-200/80', 'ring' => 'ring-1'],
        };
    }

    /**
     * Aligné sur {@see \App\Repositories\PlatformAlertRepository::listActiveForDisplay()}.
     *
     * @param array<string, mixed> $row
     */
    public static function isPublishedVisibleNow(array $row, ?string $nowDatetime = null): bool
    {
        $now = $nowDatetime ?? date('Y-m-d H:i:s');
        if (empty($row['is_active'])) {
            return false;
        }
        $starts = self::normalizeSqlDatetime($row['starts_at'] ?? null);
        $ends = self::normalizeSqlDatetime($row['ends_at'] ?? null);
        if ($starts !== null && $starts > $now) {
            return false;
        }
        if ($ends !== null && $ends < $now) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function scheduleSummary(?string $startsAt, ?string $endsAt): string
    {
        $s = self::normalizeSqlDatetime($startsAt);
        $e = self::normalizeSqlDatetime($endsAt);
        if ($s === null && $e === null) {
            return 'Pas de dates : diffusion selon le statut et l’audience.';
        }
        if ($s !== null && $e !== null) {
            return 'Du ' . self::formatFr($s) . ' au ' . self::formatFr($e);
        }
        if ($s !== null) {
            return 'À partir du ' . self::formatFr($s);
        }

        return 'Jusqu’au ' . self::formatFr($e);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function availabilityLabel(array $row, ?string $nowDatetime = null): string
    {
        $now = $nowDatetime ?? date('Y-m-d H:i:s');
        if (empty($row['is_active'])) {
            return 'Publication désactivée';
        }
        $starts = self::normalizeSqlDatetime($row['starts_at'] ?? null);
        $ends = self::normalizeSqlDatetime($row['ends_at'] ?? null);
        if ($ends !== null && $ends < $now) {
            return 'Période terminée';
        }
        if ($starts !== null && $starts > $now) {
            return 'Pas encore diffusée';
        }

        return 'Diffusée actuellement';
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return 'live'|'scheduled'|'ended'|'inactive'
     */
    public static function availabilityKey(array $row, ?string $nowDatetime = null): string
    {
        $now = $nowDatetime ?? date('Y-m-d H:i:s');
        if (empty($row['is_active'])) {
            return 'inactive';
        }
        $starts = self::normalizeSqlDatetime($row['starts_at'] ?? null);
        $ends = self::normalizeSqlDatetime($row['ends_at'] ?? null);
        if ($ends !== null && $ends < $now) {
            return 'ended';
        }
        if ($starts !== null && $starts > $now) {
            return 'scheduled';
        }

        return 'live';
    }

    /**
     * @return list<string> libellés courts pour puces
     */
    public static function audienceSummary(mixed $audienceJson): array
    {
        $aud = self::decodeAudience($audienceJson);
        $out = [];
        if (! empty($aud['guest'])) {
            $out[] = 'Visiteurs non connectés';
        }
        if (! empty($aud['authenticated'])) {
            $out[] = 'Utilisateurs connectés';
        }
        if (! empty($aud['free'])) {
            $out[] = 'Communautés sans abonnement payant actif';
        }
        if (! empty($aud['paid'])) {
            $out[] = 'Communautés avec abonnement payant ou période d’essai';
        }

        return $out;
    }

    public static function truncateBody(?string $body, int $max = 160): string
    {
        $t = trim((string) $body);
        if ($t === '') {
            return '';
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($t) <= $max) {
                return $t;
            }

            return rtrim(mb_substr($t, 0, $max - 1)) . '…';
        }
        if (strlen($t) <= $max) {
            return $t;
        }

        return rtrim(substr($t, 0, $max - 1)) . '…';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function enrichRowForAdmin(array $row, ?string $nowDatetime = null): array
    {
        $now = $nowDatetime ?? date('Y-m-d H:i:s');
        $kind = (string) ($row['kind'] ?? 'info');

        return array_merge($row, [
            '_kind_label' => self::kindLabel($kind),
            '_display_style_label' => implode(' · ', \App\Support\AlertDisplayStyle::labelsForPlatformList(
                isset($row['display_style']) ? (string) $row['display_style'] : \App\Support\AlertDisplayStyle::CLASSIC
            )),
            '_badge' => self::kindBadgeClasses($kind),
            '_schedule' => self::scheduleSummary(
                isset($row['starts_at']) ? (string) $row['starts_at'] : null,
                isset($row['ends_at']) ? (string) $row['ends_at'] : null
            ),
            '_availability' => self::availabilityLabel($row, $now),
            '_availability_key' => self::availabilityKey($row, $now),
            '_visible_now' => self::isPublishedVisibleNow($row, $now),
            '_audience' => self::audienceSummary($row['audience_json'] ?? null),
            '_body_preview' => self::truncateBody(isset($row['body']) ? (string) $row['body'] : null),
        ]);
    }

    private static function decodeAudience(mixed $raw): array
    {
        $defaults = [
            'guest' => true,
            'authenticated' => true,
            'free' => true,
            'paid' => true,
        ];
        if ($raw === null || $raw === '') {
            return $defaults;
        }
        if (is_array($raw)) {
            return array_merge($defaults, $raw);
        }
        if (is_string($raw)) {
            $d = json_decode($raw, true);

            return is_array($d) ? array_merge($defaults, $d) : $defaults;
        }

        return $defaults;
    }

    private static function normalizeSqlDatetime(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }

        return strlen($s) >= 19 ? substr($s, 0, 19) : $s;
    }

    private static function formatFr(string $sqlDatetime): string
    {
        $t = strtotime($sqlDatetime);
        if ($t === false) {
            return $sqlDatetime;
        }
        $m = (int) date('n', $t);
        $month = self::MONTHS_FR[$m - 1] ?? date('M', $t);

        return (int) date('j', $t) . ' ' . $month . ' ' . date('Y', $t) . ' — ' . date('H:i', $t);
    }
}
