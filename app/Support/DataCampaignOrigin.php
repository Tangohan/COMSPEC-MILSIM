<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Préambule de la page « Fiabilisation des données » : qui demande la vérification, pourquoi, pour quoi faire, entre quelles
 * dates et à qui poser une question. Fonctions pures, sans base ni session : tout vient de la ligne de campagne
 * (auto_created, forced_on, opens_on, opened_at, due_on, unit_ids, blocks, title) et d'un contexte fourni par l'appelant
 * (nom de l'organisation, noms des unités, fuseau, langue, jour courant). Ne change ni la logique de la campagne ni les droits.
 *
 * Les phrases sont rendues directement dans la langue demandée (fr | en) : elles contiennent des noms et des dates qui ne
 * se traduisent pas par correspondance de texte, et servent aussi à l'e-mail et à la notification.
 *
 * Confidentialité : par défaut on ne nomme jamais une personne, seulement « un responsable de l'organisation ». Un nom n'est
 * écrit que si l'appelant l'autorise (`name_requester`) et le fournit (`requester_name`) ; un compte supprimé n'est jamais nommé.
 */
final class DataCampaignOrigin
{
    public const ANNUAL = 'athena_annual';
    public const FORCED = 'athena_forced';
    public const ORGANIZATION = 'organization';

    public const UPCOMING = 'upcoming';
    public const ONGOING = 'ongoing';
    public const LAST_DAY = 'last_day';
    public const OVERDUE = 'overdue';
    public const UNDATED = 'undated';

    /** Durée annoncée aux membres. */
    public const MINUTES = 2;

    private const MONTHS_FR = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    private const MONTHS_EN = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    private const MONTHS_EN_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    /** Noms d'unités cités au plus ; les autres sont comptés. */
    private const MAX_UNITS = 6;

    /** Libellé que l'application met à la place d'un compte supprimé. */
    private const DELETED_ACCOUNT = 'Compte supprimé';

    /**
     * Origine : Athena chaque année (ouverture automatique), Athena à la demande de l'organisation (ouverture forcée avant la
     * période automatique) ou l'organisation elle-même (campagne ouverte à la main par un responsable).
     *
     * @param array<string, mixed> $campaign
     */
    public static function origin(array $campaign): string
    {
        if (!empty($campaign['auto_created'])) {
            return trim((string) ($campaign['forced_on'] ?? '')) !== '' ? self::FORCED : self::ANNUAL;
        }

        return self::ORGANIZATION;
    }

    /**
     * @param array<string, mixed> $campaign ligne de campagne (DataCampaignRepository::hydrateCampaign)
     * @param array{tenant_name?: string, unit_names?: list<string>, lang?: string, timezone?: string, today?: string, now?: \DateTimeInterface, requester_name?: string, name_requester?: bool, block_labels?: list<string>} $ctx
     * @return array{
     *   key: string, origin: string, state: string, title: string, tenant_name: string, lang: string,
     *   who: string, who_short: string, why: string, what: string, scope: string, when: string, duration: string,
     *   visibility: string, changes: string, contact: string, contact_link: string, summary: string,
     *   start: string, end: string, start_iso: string, end_iso: string, period: string, period_short: string,
     *   days_total: ?int, days_left: ?int, overdue: bool, bullets: list<string>, minutes: int
     * }
     */
    public static function describe(array $campaign, array $ctx = []): array
    {
        $lang = ($ctx['lang'] ?? 'fr') === 'en' ? 'en' : 'fr';
        $org = self::clean((string) ($ctx['tenant_name'] ?? ''));
        $origin = self::origin($campaign);
        $tz = self::zone((string) ($ctx['timezone'] ?? ''));
        $today = self::today($ctx, $tz);

        $startIso = self::startIso($campaign, $tz);
        $endIso = self::isoDate((string) ($campaign['due_on'] ?? ''));
        $state = self::state($startIso, $endIso, $today);
        $daysLeft = $endIso !== '' ? self::diffDays($today, $endIso) : null;
        $daysTotal = $startIso !== '' && $endIso !== '' && $endIso >= $startIso ? self::diffDays($startIso, $endIso) + 1 : null;

        $requester = '';
        if (!empty($ctx['name_requester'])) {
            $requester = self::clean((string) ($ctx['requester_name'] ?? ''));
            if (mb_strtolower($requester) === mb_strtolower(self::DELETED_ACCOUNT)) {
                $requester = '';
            }
        }

        $labels = array_values(array_filter(array_map(static fn (mixed $l): string => self::clean((string) $l), (array) ($ctx['block_labels'] ?? []))));
        $unitNames = array_values(array_filter(array_map(static fn (mixed $n): string => self::clean((string) $n), (array) ($ctx['unit_names'] ?? []))));
        $hasScope = !empty($campaign['unit_ids']) || $unitNames !== [];

        $period = self::period($startIso, $endIso, $lang, false);
        $periodShort = self::period($startIso, $endIso, $lang, true);
        $whoShort = self::whoShort($origin, $org, $lang);

        $out = [
            'key' => 'dc-' . (int) ($campaign['id'] ?? 0),
            'origin' => $origin,
            'state' => $state,
            'title' => self::clean((string) ($campaign['title'] ?? '')),
            'tenant_name' => $org,
            'lang' => $lang,
            'who' => self::who($origin, $org, $requester, $lang),
            'who_short' => $whoShort,
            'why' => self::t($lang, 'Pour garder les fiches exactes : effectifs, accès, qualifications et sécurité. L’organisation et la plateforme en ont besoin pour fonctionner correctement.', 'To keep records accurate: rosters, access, qualifications and safety. The organization and the platform need this to run properly.'),
            'what' => self::what($labels, $lang),
            'scope' => $hasScope ? self::scope($unitNames, $lang) : '',
            'when' => self::whenText($state, $startIso, $endIso, $daysLeft, $daysTotal, $lang),
            'duration' => self::t($lang, 'Environ ' . self::MINUTES . ' minutes.', 'About ' . self::MINUTES . ' minutes.'),
            'visibility' => self::t($lang, 'Vos réponses sont vues par les responsables habilités de l’organisation, pas par les autres membres.', 'Your answers are seen by the organization’s authorized managers, not by other members.'),
            'changes' => self::t($lang, 'Rien n’est modifié sans validation : une correction demandée passe par la validation habituelle.', 'Nothing is changed without approval: a requested correction goes through the usual approval.'),
            'contact' => self::t($lang, 'Une question ? Écrivez à l’organisation par la messagerie du site, ou parlez-en à votre responsable d’unité.', 'A question? Write to the organization through the site messaging, or ask your unit manager.'),
            'contact_link' => self::t($lang, 'Poser une question', 'Ask a question'),
            'start' => self::longDate($startIso, $lang),
            'end' => self::longDate($endIso, $lang),
            'start_iso' => $startIso,
            'end_iso' => $endIso,
            'period' => $period,
            'period_short' => $periodShort,
            'days_total' => $daysTotal,
            'days_left' => $daysLeft,
            'overdue' => $state === self::OVERDUE,
            'minutes' => self::MINUTES,
        ];
        $out['summary'] = implode(' · ', array_filter([
            self::t($lang, 'Demandé par ', 'Requested by ') . $whoShort,
            $periodShort,
            self::t($lang, '~' . self::MINUTES . ' min', '~' . self::MINUTES . ' min'),
        ], static fn (string $s): bool => $s !== ''));
        $out['bullets'] = array_values(array_filter([
            $out['who'],
            $out['why'],
            $out['what'] . ($out['scope'] !== '' ? ' ' . $out['scope'] : ''),
            $out['when'] . ' ' . $out['duration'],
            $out['visibility'] . ' ' . $out['changes'],
        ], static fn (string $s): bool => trim($s) !== ''));

        return $out;
    }

    /** « Athena » | « Athena, à la demande de <org> » | « <org> » (court, pour la ligne de résumé et les notifications). */
    public static function whoShort(string $origin, string $org, string $lang = 'fr'): string
    {
        $lang = $lang === 'en' ? 'en' : 'fr';
        $org = self::clean($org);

        return match ($origin) {
            self::ANNUAL => 'Athena',
            self::FORCED => $org !== ''
                ? self::t($lang, 'Athena, à la demande de ' . $org, 'Athena, at the request of ' . $org)
                : self::t($lang, 'Athena, à la demande de l’organisation', 'Athena, at the request of the organization'),
            default => $org !== '' ? $org : self::t($lang, 'votre organisation', 'your organization'),
        };
    }

    /**
     * Phrase d'origine complète, la même sur la page, dans l'e-mail de rappel et dans la notification.
     */
    public static function who(string $origin, string $org, string $requester = '', string $lang = 'fr'): string
    {
        $lang = $lang === 'en' ? 'en' : 'fr';
        $org = self::clean($org);
        $requester = self::clean($requester);
        if ($origin === self::ANNUAL) {
            return self::t($lang, 'Cette vérification est demandée par Athena, la plateforme, comme chaque année.', 'This check is requested by Athena, the platform, as every year.');
        }
        if ($origin === self::FORCED) {
            return $org !== ''
                ? self::t($lang, 'Cette vérification est demandée par Athena, la plateforme, à la demande de l’organisation ' . $org . '.', 'This check is requested by Athena, the platform, at the request of the organization ' . $org . '.')
                : self::t($lang, 'Cette vérification est demandée par Athena, la plateforme, à la demande de l’organisation.', 'This check is requested by Athena, the platform, at the request of the organization.');
        }
        $by = $requester !== ''
            ? self::t($lang, ', ouverte par ' . $requester . ', responsable de l’organisation', ', opened by ' . $requester . ', an organization manager')
            : self::t($lang, ', ouverte par un responsable de l’organisation', ', opened by an organization manager');

        return $org !== ''
            ? self::t($lang, 'Cette vérification est demandée par l’organisation ' . $org . $by . '.', 'This check is requested by the organization ' . $org . $by . '.')
            : self::t($lang, 'Cette vérification est demandée par votre organisation' . $by . '.', 'This check is requested by your organization' . $by . '.');
    }

    /**
     * « du 15 novembre au 31 décembre 2026 » ; format court : « du 15/11 au 31/12/2026 ». Une seule date : « jusqu’au … »
     * ou « à partir du … » ; aucune : chaîne vide.
     */
    public static function period(string $startIso, string $endIso, string $lang = 'fr', bool $short = false): string
    {
        $lang = $lang === 'en' ? 'en' : 'fr';
        $s = self::parts($startIso);
        $e = self::parts($endIso);
        if ($s === null && $e === null) {
            return '';
        }
        $fmt = static fn (array $p, bool $withYear): string => $short ? self::shortDate($p, $lang, $withYear) : self::longParts($p, $lang, $withYear);
        if ($s !== null && $e !== null) {
            $sameYear = $s[0] === $e[0];
            $from = $fmt($s, !$sameYear);
            $to = $fmt($e, true);

            return $lang === 'fr' ? 'du ' . $from . ' au ' . $to : 'from ' . $from . ' to ' . $to;
        }
        if ($e !== null) {
            return $lang === 'fr' ? 'jusqu’au ' . $fmt($e, true) : 'until ' . $fmt($e, true);
        }

        return $lang === 'fr' ? 'à partir du ' . $fmt($s, true) : 'from ' . $fmt($s, true);
    }

    /** « 31 décembre 2026 » / « December 31, 2026 » (vide si la date manque). */
    public static function longDate(string $iso, string $lang = 'fr'): string
    {
        $p = self::parts($iso);

        return $p === null ? '' : self::longParts($p, $lang === 'en' ? 'en' : 'fr', true);
    }

    /**
     * Libellé de l'état de la campagne (court), pour une pastille ou un texte : à venir, en cours, dernier jour, dépassée.
     */
    public static function stateLabel(string $state, string $lang = 'fr'): string
    {
        $lang = $lang === 'en' ? 'en' : 'fr';

        return match ($state) {
            self::UPCOMING => self::t($lang, 'À venir', 'Upcoming'),
            self::LAST_DAY => self::t($lang, 'Dernier jour', 'Last day'),
            self::OVERDUE => self::t($lang, 'Date de fin dépassée', 'Past the end date'),
            self::UNDATED => self::t($lang, 'Sans date de fin', 'No end date'),
            default => self::t($lang, 'En cours', 'Open'),
        };
    }

    /** État selon les dates (AAAA-MM-JJ) et le jour courant : à venir, en cours, dernier jour, dépassée, sans date de fin. */
    public static function state(string $startIso, string $endIso, string $today): string
    {
        if ($startIso !== '' && $today < $startIso) {
            return self::UPCOMING;
        }
        if ($endIso === '') {
            return self::UNDATED;
        }
        if ($today > $endIso) {
            return self::OVERDUE;
        }

        return $today === $endIso ? self::LAST_DAY : self::ONGOING;
    }

    // ------------------------------------------------------------------ phrases

    /** @param list<string> $labels */
    private static function what(array $labels, string $lang): string
    {
        $tail = self::t($lang, 'Seules les informations de votre propre fiche sont concernées.', 'Only the information on your own record is concerned.');
        if ($labels === []) {
            return self::t($lang, 'Vous relisez votre fiche, partie par partie. ', 'You review your record, part by part. ') . $tail;
        }

        return self::t($lang, 'Vous relirez : ', 'You will review: ') . implode(', ', $labels) . '. ' . $tail;
    }

    /** @param list<string> $names */
    private static function scope(array $names, string $lang): string
    {
        if ($names === []) {
            return self::t($lang, 'Cette campagne concerne les membres de certaines unités.', 'This campaign concerns the members of certain units.');
        }
        $shown = array_slice($names, 0, self::MAX_UNITS);
        $more = count($names) - count($shown);
        $list = implode(', ', $shown) . ($more > 0 ? self::t($lang, ' et ' . $more . ' autre' . ($more > 1 ? 's' : ''), ' and ' . $more . ' more') : '');

        return self::t($lang, 'Cette campagne concerne les membres de : ' . $list . '.', 'This campaign concerns the members of: ' . $list . '.');
    }

    private static function whenText(string $state, string $startIso, string $endIso, ?int $daysLeft, ?int $daysTotal, string $lang): string
    {
        $period = self::period($startIso, $endIso, $lang, false);
        $head = $period !== ''
            ? self::t($lang, 'La campagne court ' . $period . ($daysTotal !== null ? ' (' . $daysTotal . ' jour' . ($daysTotal > 1 ? 's' : '') . ')' : '') . '.', 'The campaign runs ' . $period . ($daysTotal !== null ? ' (' . $daysTotal . ' day' . ($daysTotal > 1 ? 's' : '') . ')' : '') . '.')
            : self::t($lang, 'Aucune date n’est indiquée pour cette campagne.', 'No dates are given for this campaign.');
        $left = $daysLeft ?? 0;
        $tail = match ($state) {
            self::UPCOMING => self::t($lang, 'Elle n’est pas encore ouverte : vous pouvez déjà relire votre fiche.', 'It is not open yet: you can already review your record.'),
            self::LAST_DAY => self::t($lang, 'C’est le dernier jour : deux minutes suffisent.', 'Today is the last day: two minutes are enough.'),
            self::OVERDUE => self::t($lang, 'La date de fin est dépassée de ' . (-$left) . ' jour' . ($left < -1 ? 's' : '') . ' : il n’est pas trop tard et rien n’est bloqué, mais merci de le faire dès maintenant. Des rappels continuent tant que la fiche n’est pas confirmée.', 'The end date passed ' . (-$left) . ' day' . ($left < -1 ? 's' : '') . ' ago: it is not too late and nothing is blocked, but please do it now. Reminders continue until your record is confirmed.'),
            self::UNDATED => self::t($lang, 'Aucune date de fin n’est fixée.', 'No end date is set.'),
            default => self::t($lang, 'Il vous reste ' . $left . ' jour' . ($left > 1 ? 's' : '') . '. Après la date de fin, rien n’est bloqué : vous recevez des rappels.', $left . ' day' . ($left > 1 ? 's' : '') . ' left. After the end date nothing is blocked: you receive reminders.'),
        };

        return $period === '' ? $head : $head . ' ' . $tail;
    }

    // ------------------------------------------------------------------ dates

    private static function t(string $lang, string $fr, string $en): string
    {
        return $lang === 'en' ? $en : $fr;
    }

    private static function clean(string $v): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $v));
    }

    private static function zone(string $name): \DateTimeZone
    {
        try {
            return new \DateTimeZone($name !== '' ? $name : 'Europe/Paris');
        } catch (\Throwable) {
            return new \DateTimeZone('Europe/Paris');
        }
    }

    /** @param array<string, mixed> $ctx */
    private static function today(array $ctx, \DateTimeZone $tz): string
    {
        $given = trim((string) ($ctx['today'] ?? ''));
        if ($given !== '' && self::isoDate($given) !== '') {
            return self::isoDate($given);
        }
        $now = $ctx['now'] ?? new \DateTimeImmutable('now');

        return \DateTimeImmutable::createFromInterface($now)->setTimezone($tz)->format('Y-m-d');
    }

    /** Début : opens_on, sinon le jour d'ouverture réelle (opened_at, converti dans le fuseau de la communauté). */
    private static function startIso(array $campaign, \DateTimeZone $tz): string
    {
        $opens = self::isoDate((string) ($campaign['opens_on'] ?? ''));
        if ($opens !== '') {
            return $opens;
        }
        $opened = trim((string) ($campaign['opened_at'] ?? ''));
        if ($opened === '' || str_starts_with($opened, '0000')) {
            return '';
        }
        try {
            $dt = new \DateTimeImmutable($opened, new \DateTimeZone(date_default_timezone_get()));

            return $dt->setTimezone($tz)->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }

    /** AAAA-MM-JJ valide, sinon chaîne vide (accepte une date-heure : seule la date est gardée). */
    private static function isoDate(string $raw): string
    {
        $raw = trim($raw);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || $m[1] === '0000') {
            return '';
        }

        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }

    /** @return array{0: int, 1: int, 2: int}|null */
    private static function parts(string $iso): ?array
    {
        $iso = self::isoDate($iso);

        return $iso === '' ? null : [(int) substr($iso, 0, 4), (int) substr($iso, 5, 2), (int) substr($iso, 8, 2)];
    }

    /** Jours entiers entre deux dates AAAA-MM-JJ (sans effet d'heure d'été). */
    private static function diffDays(string $from, string $to): int
    {
        $a = new \DateTimeImmutable($from . ' 00:00:00', new \DateTimeZone('UTC'));
        $b = new \DateTimeImmutable($to . ' 00:00:00', new \DateTimeZone('UTC'));

        return (int) $a->diff($b)->format('%r%a');
    }

    /** @param array{0: int, 1: int, 2: int} $p */
    private static function longParts(array $p, string $lang, bool $withYear): string
    {
        if ($lang === 'en') {
            return self::MONTHS_EN[$p[1] - 1] . ' ' . $p[2] . ($withYear ? ', ' . $p[0] : '');
        }

        return ($p[2] === 1 ? '1er' : (string) $p[2]) . ' ' . self::MONTHS_FR[$p[1] - 1] . ($withYear ? ' ' . $p[0] : '');
    }

    /** @param array{0: int, 1: int, 2: int} $p */
    private static function shortDate(array $p, string $lang, bool $withYear): string
    {
        if ($lang === 'en') {
            return self::MONTHS_EN_SHORT[$p[1] - 1] . ' ' . $p[2] . ($withYear ? ', ' . $p[0] : '');
        }

        return sprintf('%02d/%02d', $p[2], $p[1]) . ($withYear ? '/' . $p[0] : '');
    }
}
