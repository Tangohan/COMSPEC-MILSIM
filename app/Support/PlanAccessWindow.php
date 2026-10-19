<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Fin des formules à durée limitée (essai fondateur Pro, formule offerte) : avertissement avant la fin
 * et période de grâce après la fin.
 *
 * Calculs purs à partir d'une ligne tenants (settings en JSON ou décodé). La formule appliquée reste celle
 * de PlanGift::effectivePlanSlug ; la période de grâce ne fait que prolonger, pendant N jours, les modules
 * de la formule qui vient de se terminer (voir FeatureGateService::allows).
 *
 * Réglages plateforme (platform_settings) :
 *   plan_grace_enabled        0|1   (0 par défaut : comportement inchangé, coupure le jour de la fin)
 *   plan_grace_days           entier, 7 par défaut
 *   plan_end_warning_days     entier, 7 par défaut (bandeau et e-mail N jours avant la fin)
 *   plan_end_warning_email    0|1   (0 par défaut : e-mail aux gestionnaires désactivé)
 */
final class PlanAccessWindow
{
    public const SETTING_GRACE_ENABLED = 'plan_grace_enabled';
    public const SETTING_GRACE_DAYS = 'plan_grace_days';
    public const SETTING_WARNING_DAYS = 'plan_end_warning_days';
    public const SETTING_WARNING_EMAIL = 'plan_end_warning_email';

    public const DEFAULT_GRACE_DAYS = 7;
    public const DEFAULT_WARNING_DAYS = 7;
    public const MAX_DAYS = 60;

    /** Clé tenants.settings : dernière fin annoncée par e-mail (« trial|2026-10-20T… »). */
    public const WARNED_KEY = 'plan_end_warning_sent';

    /** Formule appliquée par l'essai fondateur. */
    public const TRIAL_PLAN = 'pro';

    /**
     * Ce qui donne à la communauté sa formule actuelle, et quand cela se termine.
     *
     * @param array<string, mixed> $tenant
     * @return array{kind: 'gift'|'paid'|'trial'|'base', plan_slug: string, ends_at: ?string}
     */
    public static function source(array $tenant, ?int $now = null): array
    {
        $now ??= time();
        $gift = PlanGift::activeFromSettings($tenant['settings'] ?? null, $now);
        if ($gift !== null) {
            return ['kind' => 'gift', 'plan_slug' => $gift['plan_slug'], 'ends_at' => $gift['ends_at']];
        }
        $base = (string) ($tenant['plan_slug'] ?? 'free');
        $base = $base !== '' ? $base : 'free';
        if (in_array((string) ($tenant['subscription_status'] ?? 'none'), ['active', 'trialing'], true)) {
            return ['kind' => 'paid', 'plan_slug' => $base, 'ends_at' => null];
        }
        $trialEnd = self::trialEndsAt($tenant);
        if ($trialEnd !== null && ($ts = strtotime($trialEnd)) !== false && $ts > $now) {
            return ['kind' => 'trial', 'plan_slug' => self::TRIAL_PLAN, 'ends_at' => $trialEnd];
        }

        return ['kind' => 'base', 'plan_slug' => $base, 'ends_at' => null];
    }

    /**
     * Formule à durée limitée qui se termine dans les $days prochains jours (essai fondateur ou formule offerte).
     *
     * @param array<string, mixed> $tenant
     * @return array{kind: 'gift'|'trial', plan_slug: string, ends_at: string, days_left: int}|null
     */
    public static function endingSoon(array $tenant, int $days, ?int $now = null): ?array
    {
        $now ??= time();
        if ($days < 1) {
            return null;
        }
        $src = self::source($tenant, $now);
        if (!in_array($src['kind'], ['gift', 'trial'], true) || $src['ends_at'] === null) {
            return null;
        }
        $ts = strtotime($src['ends_at']);
        if ($ts === false || $ts <= $now || $ts > $now + $days * 86400) {
            return null;
        }

        return [
            'kind' => $src['kind'],
            'plan_slug' => $src['plan_slug'],
            'ends_at' => $src['ends_at'],
            'days_left' => max(0, (int) ceil(($ts - $now) / 86400)),
        ];
    }

    /**
     * Dernière formule à durée limitée terminée, quand la communauté est revenue à sa formule de base.
     *
     * @param array<string, mixed> $tenant
     * @return array{kind: 'gift'|'trial', plan_slug: string, ended_at: string}|null
     */
    public static function lapsed(array $tenant, ?int $now = null): ?array
    {
        $now ??= time();
        if (self::source($tenant, $now)['kind'] !== 'base') {
            return null;
        }
        $candidates = [];
        $trialEnd = self::trialEndsAt($tenant);
        if ($trialEnd !== null && ($ts = strtotime($trialEnd)) !== false && $ts <= $now) {
            $candidates[] = ['kind' => 'trial', 'plan_slug' => self::TRIAL_PLAN, 'ended_at' => $trialEnd, 'ts' => $ts];
        }
        $settings = self::settings($tenant);
        $gift = $settings[PlanGift::SETTINGS_KEY] ?? null;
        if (is_array($gift)) {
            $slug = strtolower(trim((string) ($gift['plan_slug'] ?? '')));
            $end = is_string($gift['ends_at'] ?? null) ? trim($gift['ends_at']) : '';
            if ($slug !== '' && $end !== '' && ($ts = strtotime($end)) !== false && $ts <= $now) {
                $candidates[] = ['kind' => 'gift', 'plan_slug' => $slug, 'ended_at' => $end, 'ts' => $ts];
            }
        }
        if ($candidates === []) {
            return null;
        }
        usort($candidates, static fn (array $a, array $b): int => $b['ts'] <=> $a['ts']);
        $last = $candidates[0];

        return ['kind' => $last['kind'], 'plan_slug' => $last['plan_slug'], 'ended_at' => $last['ended_at']];
    }

    /**
     * Période de grâce en cours : la formule terminée depuis moins de $graceDays jours.
     * $graceDays <= 0 : pas de grâce (réglage désactivé).
     *
     * @param array<string, mixed> $tenant
     * @return array{kind: 'gift'|'trial', plan_slug: string, ended_at: string, grace_ends_at: string, days_left: int}|null
     */
    public static function grace(array $tenant, int $graceDays, ?int $now = null): ?array
    {
        $now ??= time();
        if ($graceDays < 1) {
            return null;
        }
        if ((string) ($tenant['subscription_status'] ?? '') === 'past_due') {
            return null;
        }
        $lapsed = self::lapsed($tenant, $now);
        if ($lapsed === null) {
            return null;
        }
        $ended = strtotime($lapsed['ended_at']);
        if ($ended === false) {
            return null;
        }
        $graceEnd = $ended + $graceDays * 86400;
        if ($graceEnd <= $now) {
            return null;
        }

        return $lapsed + [
            'grace_ends_at' => date('c', $graceEnd),
            'days_left' => max(0, (int) ceil(($graceEnd - $now) / 86400)),
        ];
    }

    /** Empreinte d'une fin annoncée, pour n'envoyer l'e-mail qu'une fois par fin. */
    public static function warningFingerprint(string $kind, string $endsAt): string
    {
        return $kind . '|' . $endsAt;
    }

    /** @param array<string, mixed> $tenant */
    public static function alreadyWarned(array $tenant, string $kind, string $endsAt): bool
    {
        $sent = self::settings($tenant)[self::WARNED_KEY] ?? null;

        return is_string($sent) && $sent === self::warningFingerprint($kind, $endsAt);
    }

    /** Nombre de jours borné (0..MAX_DAYS) ; $fallback si la valeur est vide ou invalide. */
    public static function clampDays(mixed $raw, int $fallback): int
    {
        if ($raw === null || (is_string($raw) && trim($raw) === '') || !is_numeric($raw)) {
            return $fallback;
        }

        return max(0, min(self::MAX_DAYS, (int) $raw));
    }

    /** @param array<string, mixed> $tenant */
    public static function trialEndsAt(array $tenant): ?string
    {
        $end = self::settings($tenant)['founder_trial_ends_at'] ?? null;

        return is_string($end) && trim($end) !== '' ? trim($end) : null;
    }

    /**
     * @param array<string, mixed> $tenant
     * @return array<string, mixed>
     */
    private static function settings(array $tenant): array
    {
        $s = $tenant['settings'] ?? null;
        if (is_string($s)) {
            $s = trim($s) !== '' ? json_decode($s, true) : [];
        }

        return is_array($s) ? $s : [];
    }

    /**
     * Textes du bandeau et de l'e-mail, en français et en anglais (translate="no" à l'affichage).
     *
     * @return array<string, string>
     */
    public static function copy(
        string $locale,
        string $tenantName,
        string $planName,
        string $kind,
        string $endsAt,
        bool $atakLost,
        int $graceDays,
    ): array {
        $en = PlanGift::locale($locale) === 'en';
        $date = PlanGift::formatDate($endsAt, $locale);
        if ($en) {
            $what = $kind === 'gift' ? 'The ' . $planName . ' plan gifted to ' . $tenantName : 'The founder trial (' . $planName . ' plan) of ' . $tenantName;
            $lost = $atakLost
                ? 'After that date, photos, drone video and the full tactical map stop (positions, reports and briefing from Arma 3 keep working) and the modules not included in your current plan are locked.'
                : 'After that date, the modules not included in your current plan are locked.';
            $grace = $graceDays > 0
                ? 'A grace period of ' . $graceDays . ' day' . ($graceDays > 1 ? 's' : '') . ' keeps everything running after that date.'
                : '';

            return [
                'subject' => $tenantName . ': your plan ends on ' . $date,
                'title' => 'Your plan ends on ' . $date,
                'kicker' => 'Athena plan',
                'lead' => $what . ' ends on ' . $date . '.',
                'lost' => $lost,
                'grace' => $grace,
                'cta' => 'See the plans',
                'hello' => 'Hello',
                'banner' => $what . ' ends on ' . $date . '.',
                'banner_cta' => 'Keep access',
            ];
        }

        $what = $kind === 'gift' ? 'La formule ' . $planName . ' offerte à ' . $tenantName : 'L’essai fondateur (formule ' . $planName . ') de ' . $tenantName;
        $lost = $atakLost
            ? 'Après cette date, les photos, la vidéo drone et la carte tactique complète s’arrêtent (positions, comptes rendus et briefing envoyés depuis Arma 3 continuent) et les modules absents de votre formule actuelle sont verrouillés.'
            : 'Après cette date, les modules absents de votre formule actuelle sont verrouillés.';
        $grace = $graceDays > 0
            ? 'Une période de grâce de ' . $graceDays . ' jour' . ($graceDays > 1 ? 's' : '') . ' laisse tout fonctionner après cette date.'
            : '';

        return [
            'subject' => $tenantName . ' : votre formule se termine le ' . $date,
            'title' => 'Votre formule se termine le ' . $date,
            'kicker' => 'Formule Athena',
            'lead' => $what . ' se termine le ' . $date . '.',
            'lost' => $lost,
            'grace' => $grace,
            'cta' => 'Voir les formules',
            'hello' => 'Bonjour',
            'banner' => $what . ' se termine le ' . $date . '.',
            'banner_cta' => 'Garder l’accès',
        ];
    }

    /**
     * Bandeau pendant la période de grâce.
     *
     * @return array{text: string, cta: string}
     */
    public static function graceCopy(string $locale, string $planName, string $graceEndsAt): array
    {
        $date = PlanGift::formatDate($graceEndsAt, $locale);
        if (PlanGift::locale($locale) === 'en') {
            return [
                'text' => 'Your ' . $planName . ' plan has ended. Grace period: everything keeps working until ' . $date . ', then photos, drone video, the full map and the locked modules stop.',
                'cta' => 'Keep access',
            ];
        }

        return [
            'text' => 'Votre formule ' . $planName . ' est terminée. Période de grâce : tout fonctionne jusqu’au ' . $date . ', puis les photos, la vidéo drone, la carte complète et les modules verrouillés s’arrêtent.',
            'cta' => 'Garder l’accès',
        ];
    }
}
