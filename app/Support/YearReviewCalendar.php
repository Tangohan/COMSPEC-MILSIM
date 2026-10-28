<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Calendrier de la rétrospective annuelle (le « Wrapped » des joueurs) : quelle année est publiée, depuis quand,
 * et pendant combien de jours elle s'ouvre d'elle-même à la connexion.
 *
 * Réglages plateforme (platform_settings, page Administration > Rétrospective annuelle) :
 *  - year_review_enabled      1 / 0
 *  - year_review_publish_md   date de publication « MM-JJ » (12-01 par défaut, comme Spotify début décembre).
 *                             Avant juillet (01-01, 01-15…) : publiée l'année suivante, sur l'année écoulée entière.
 *  - year_review_popup_days   jours pendant lesquels la rétrospective s'ouvre seule à la connexion (45 par défaut).
 *  - year_review_email        1 / 0 : e-mail « ta rétrospective est prête » aux joueurs à la publication (oui par défaut).
 *
 * Pure : aucune base, l'heure courante est passée en paramètre.
 */
final class YearReviewCalendar
{
    public const SETTING_ENABLED = 'year_review_enabled';
    public const SETTING_PUBLISH_MD = 'year_review_publish_md';
    public const SETTING_POPUP_DAYS = 'year_review_popup_days';
    public const SETTING_EMAIL = 'year_review_email';

    public const DEFAULT_PUBLISH_MD = '12-01';
    public const DEFAULT_POPUP_DAYS = 45;

    /** Jours après la publication pendant lesquels l'e-mail d'annonce part (envois étalés par le quota journalier). */
    public const EMAIL_DAYS = 14;

    /** Premier millésime possible (les remontées du mod commencent en 2026). */
    public const FIRST_YEAR = 2026;

    /** Dates proposées dans le réglage : MM-JJ => libellé. */
    public const PRESETS = [
        '12-01' => '1er décembre (comme Spotify, sur l’année en cours)',
        '12-15' => '15 décembre (sur l’année en cours)',
        '01-01' => '1er janvier (sur l’année écoulée entière)',
        '01-15' => '15 janvier (sur l’année écoulée entière)',
    ];

    public function __construct(
        private bool $enabled = true,
        private string $publishMd = self::DEFAULT_PUBLISH_MD,
        private int $popupDays = self::DEFAULT_POPUP_DAYS,
        private bool $email = true,
    ) {
        $this->publishMd = self::normalizeMd($publishMd) ?? self::DEFAULT_PUBLISH_MD;
        $this->popupDays = max(0, min(366, $popupDays));
    }

    /** @param array<string, string> $settings réglages bruts de platform_settings */
    public static function fromSettings(array $settings): self
    {
        $on = static fn (string $key): bool => in_array(strtolower(trim((string) ($settings[$key] ?? '1'))), ['1', 'true', 'yes', 'on'], true);

        return new self(
            $on(self::SETTING_ENABLED),
            (string) ($settings[self::SETTING_PUBLISH_MD] ?? self::DEFAULT_PUBLISH_MD),
            (int) ($settings[self::SETTING_POPUP_DAYS] ?? self::DEFAULT_POPUP_DAYS),
            $on(self::SETTING_EMAIL),
        );
    }

    /** « 12-01 », « 1-5 » → « 01-05 » ; null si la date n'existe pas (29 février refusé : pas toutes les années). */
    public static function normalizeMd(string $md): ?string
    {
        if (preg_match('/^\s*(\d{1,2})-(\d{1,2})\s*$/', $md, $m) !== 1) {
            return null;
        }
        $month = (int) $m[1];
        $day = (int) $m[2];
        if (!checkdate($month, $day, 2027)) {
            return null;
        }

        return sprintf('%02d-%02d', $month, $day);
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function publishMd(): string
    {
        return $this->publishMd;
    }

    public function popupDays(): int
    {
        return $this->popupDays;
    }

    public function emailEnabled(): bool
    {
        return $this->email;
    }

    /** Publication en fin d'année (sur l'année en cours) plutôt qu'en début d'année suivante. */
    public function publishesInYear(): bool
    {
        return (int) substr($this->publishMd, 0, 2) >= 7;
    }

    /** Date et heure (locale du serveur) où la rétrospective de $year devient visible. */
    public function publishAt(int $year): int
    {
        $pubYear = $this->publishesInYear() ? $year : $year + 1;

        return (int) strtotime(sprintf('%04d-%s 00:00:00', $pubYear, $this->publishMd));
    }

    /** Dernière année publiée à $now (null : rien encore, ou rétrospective désactivée). */
    public function currentYear(int $now): ?int
    {
        if (!$this->enabled) {
            return null;
        }
        $year = (int) date('Y', $now);
        foreach ([$year, $year - 1, $year - 2] as $candidate) {
            if ($candidate >= self::FIRST_YEAR && $now >= $this->publishAt($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function isPublished(int $year, int $now): bool
    {
        return $this->enabled && $year >= self::FIRST_YEAR && $now >= $this->publishAt($year);
    }

    /** Années déjà publiées, la plus récente d'abord. */
    public function publishedYears(int $now): array
    {
        $current = $this->currentYear($now);
        if ($current === null) {
            return [];
        }

        return range($current, self::FIRST_YEAR);
    }

    /** La rétrospective de $year s'ouvre-t-elle encore d'elle-même à la connexion ? */
    public function inPopupWindow(int $year, int $now): bool
    {
        if (!$this->isPublished($year, $now) || $this->popupDays < 1) {
            return false;
        }

        return $now < $this->publishAt($year) + $this->popupDays * 86400;
    }

    /** Année dont l'e-mail d'annonce doit partir à $now (null : réglage coupé, rien de publié ou publication trop ancienne). */
    public function emailYear(int $now): ?int
    {
        $year = $this->currentYear($now);
        if ($year === null || !$this->email || $now >= $this->publishAt($year) + self::EMAIL_DAYS * 86400) {
            return null;
        }

        return $year;
    }

    /**
     * Période couverte : du 1er janvier au 1er janvier suivant, arrêtée à $now pour l'année en cours.
     *
     * @return array{from: string, until: string, partial: bool}
     */
    public static function period(int $year, int $now): array
    {
        $from = sprintf('%04d-01-01 00:00:00', $year);
        $end = sprintf('%04d-01-01 00:00:00', $year + 1);
        $nowStr = date('Y-m-d H:i:s', $now);
        $partial = $nowStr < $end;

        return ['from' => $from, 'until' => $partial ? $nowStr : $end, 'partial' => $partial];
    }

    /** Clé « déjà vue » dans user_ui_tours. */
    public static function seenKey(int $year): string
    {
        return 'year_review_' . $year;
    }

    /** Clé « e-mail d'annonce envoyé » dans user_ui_tours (un seul e-mail par joueur et par année). */
    public static function mailKey(int $year): string
    {
        return 'year_review_mail_' . $year;
    }
}
