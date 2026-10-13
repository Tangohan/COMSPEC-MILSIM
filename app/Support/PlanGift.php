<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Formule offerte à une communauté (administration de la plateforme).
 *
 * L'offre en cours est recopiée dans tenants.settings sous la clé plan_gift :
 *   {"gift_id": 12, "plan_slug": "pro", "ends_at": "2026-12-31 23:59:59" | null}
 * ends_at null = offerte sans limite. Une fois la date passée, l'offre ne compte plus (aucune tâche planifiée).
 *
 * Les textes de l'e-mail et de la fenêtre sont écrits ici en français et en anglais : la fenêtre est marquée
 * translate="no" pour que HtmlTranslator ne retraduise pas la version anglaise.
 */
final class PlanGift
{
    public const SETTINGS_KEY = 'plan_gift';

    /** Durées proposées dans le formulaire (jours ; 0 = sans limite). */
    public const DURATIONS = [30, 90, 180, 365, 0];

    /**
     * Offre active d'après les réglages de la communauté (tableau décodé ou JSON brut).
     *
     * @param array<string, mixed>|string|null $settings
     * @return array{gift_id: int, plan_slug: string, ends_at: ?string}|null
     */
    public static function activeFromSettings(array|string|null $settings, ?int $now = null): ?array
    {
        if (is_string($settings)) {
            $settings = trim($settings) !== '' ? json_decode($settings, true) : [];
        }
        if (!is_array($settings)) {
            return null;
        }
        $gift = $settings[self::SETTINGS_KEY] ?? null;
        if (!is_array($gift)) {
            return null;
        }
        $slug = strtolower(trim((string) ($gift['plan_slug'] ?? '')));
        if ($slug === '') {
            return null;
        }
        $endsAt = isset($gift['ends_at']) && is_string($gift['ends_at']) && trim($gift['ends_at']) !== ''
            ? trim($gift['ends_at'])
            : null;
        if ($endsAt !== null) {
            $ts = strtotime($endsAt);
            if ($ts === false || $ts <= ($now ?? time())) {
                return null;
            }
        }

        return [
            'gift_id' => (int) ($gift['gift_id'] ?? 0),
            'plan_slug' => $slug,
            'ends_at' => $endsAt,
        ];
    }

    /**
     * Date de fin à partir d'une durée en jours, ou d'une date saisie (AAAA-MM-JJ) qui prime. null = sans limite.
     * Retourne false si la date saisie est invalide ou passée.
     */
    public static function resolveEndsAt(int $days, string $customDate, ?int $now = null): string|false|null
    {
        $now ??= time();
        $customDate = trim($customDate);
        if ($customDate !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $customDate)) {
                return false;
            }
            $ts = strtotime($customDate . ' 23:59:59');
            if ($ts === false || $ts <= $now) {
                return false;
            }

            return date('Y-m-d H:i:s', $ts);
        }
        if ($days <= 0) {
            return null;
        }

        return date('Y-m-d 23:59:59', $now + $days * 86400);
    }

    public static function locale(?string $locale): string
    {
        return strtolower(substr((string) $locale, 0, 2)) === 'en' ? 'en' : 'fr';
    }

    public static function formatDate(?string $dateTime, string $locale): string
    {
        if ($dateTime === null || $dateTime === '') {
            return '';
        }
        $ts = strtotime($dateTime);
        if ($ts === false) {
            return $dateTime;
        }

        return self::locale($locale) === 'en' ? date('F j, Y', $ts) : date('d/m/Y', $ts);
    }

    /** Message de l'administrateur dans la langue du lecteur, sinon dans l'autre. */
    public static function message(?string $fr, ?string $en, string $locale): string
    {
        $fr = trim((string) $fr);
        $en = trim((string) $en);
        if (self::locale($locale) === 'en') {
            return $en !== '' ? $en : $fr;
        }

        return $fr !== '' ? $fr : $en;
    }

    /**
     * Textes de l'e-mail et de la fenêtre.
     *
     * @return array<string, string>
     */
    public static function copy(string $locale, string $tenantName, string $planName, ?string $endsAt): array
    {
        $until = self::formatDate($endsAt, $locale);
        if (self::locale($locale) === 'en') {
            return [
                'subject' => 'A plan has been gifted to ' . $tenantName,
                'title' => 'A plan has been gifted to you',
                'kicker' => 'Athena gift',
                'lead' => 'The Athena team is offering the ' . $planName . ' plan to ' . $tenantName . '.',
                'duration' => $until !== ''
                    ? 'Included at no cost until ' . $until . '. Your community will then go back to its current plan.'
                    : 'Included at no cost, with no end date.',
                'immediate' => 'All of its modules and limits are already available to your members, nothing to do on your side.',
                'message_label' => 'Message from the Athena team',
                'cta' => 'Open management',
                'close' => 'Got it',
                'hello' => 'Hello',
            ];
        }

        return [
            'subject' => 'Une formule est offerte à ' . $tenantName,
            'title' => 'Une formule vous est offerte',
            'kicker' => 'Cadeau Athena',
            'lead' => 'L’équipe Athena offre la formule ' . $planName . ' à ' . $tenantName . '.',
            'duration' => $until !== ''
                ? 'Incluse gratuitement jusqu’au ' . $until . '. Votre communauté reviendra ensuite à sa formule actuelle.'
                : 'Incluse gratuitement, sans date de fin.',
            'immediate' => 'Tous ses modules et plafonds sont déjà disponibles pour vos membres, rien à faire de votre côté.',
            'message_label' => 'Message de l’équipe Athena',
            'cta' => 'Ouvrir la gestion',
            'close' => 'J’ai compris',
            'hello' => 'Bonjour',
        ];
    }
}
