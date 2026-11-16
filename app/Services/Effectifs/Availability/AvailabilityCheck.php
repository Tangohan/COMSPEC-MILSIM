<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Availability;

use App\Services\Personnel\PlayerSessionService as Sessions;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Vérification de disponibilité d'un membre : étapes de recherche et conclusion proposée (service pur : aucune base, aucune
 * horloge cachée). Les données arrivent déjà relevées par AvailabilityFactsCollector ; ce service les rend lisibles
 * (une étape = ce qui a été cherché, son résultat, sa source et sa date) et applique les règles de la conclusion.
 *
 * Règles de la conclusion, dans l'ordre (la première qui s'applique l'emporte) :
 *  1. Indisponible (absence déclarée) : une absence déclarée couvre aujourd'hui, ou la position du membre ne l'engage pas
 *     en service (« En service non actif » ou position de la communauté sans service).
 *  2. Avec les sessions de jeu (suivi actif et Steam relié) :
 *     Disponible si au moins 1 session qui compte sur 7 jours ou 2 sur 30 jours ; Peu disponible s'il en reste au moins
 *     une sur 90 jours ; Inactif sinon.
 *  3. Sans session détaillée (aucune enregistrée sur 90 jours, ou suivi inactif) mais avec des heures de jeu remontées par
 *     le mod (cumul, user_arma_playtime) : l'activité du cumul décide, voir PlaytimeAvailability. Une activité récente
 *     remplace « Inactif » par « Disponible » ou « Peu disponible » ; un cumul ancien ou absent laisse les règles suivantes.
 *  4. Sans sessions de jeu : Disponible si présent à une convocation sur 30 jours ; Peu disponible si connecté au site
 *     sur 30 jours ; Inactif sinon.
 */
final class AvailabilityCheck
{
    public const AVAILABLE = 'available';
    public const LOW = 'low';
    public const ABSENT = 'absent';
    public const INACTIVE = 'inactive';

    public const STATE_OK = 'ok';
    public const STATE_WARN = 'warn';
    public const STATE_NONE = 'none';

    /** Seuils des règles (en jours ou en nombre de sessions qui comptent). */
    public const AVAILABLE_MIN_7 = 1;
    public const AVAILABLE_MIN_30 = 2;
    public const RECENT_DAYS = 30;

    /** @return array<string, string> */
    public static function conclusionLabels(): array
    {
        return [
            self::AVAILABLE => 'Disponible',
            self::LOW => 'Peu disponible',
            self::ABSENT => 'Indisponible (absence déclarée)',
            self::INACTIVE => 'Inactif',
        ];
    }

    public static function conclusionLabel(string $key): string
    {
        return self::conclusionLabels()[$key] ?? 'Inconnue';
    }

    public static function isConclusion(string $key): bool
    {
        return isset(self::conclusionLabels()[$key]);
    }

    /** @return array<string, string> */
    public static function stateLabels(): array
    {
        return [
            self::STATE_OK => 'OK',
            self::STATE_WARN => 'Attention',
            self::STATE_NONE => 'Aucune donnée',
        ];
    }

    /**
     * Étapes de recherche, dans l'ordre d'exécution. Une étape dont la source n'existe pas sur ce site n'apparaît pas.
     *
     * @param array<string, mixed> $facts relevé de AvailabilityFactsCollector::collect
     * @return list<array{key: string, label: string, state: string, status_label: string, detail: list<string>, source: array{label: string, date: string}}>
     */
    public static function steps(array $facts): array
    {
        $now = (int) ($facts['now'] ?? time());
        $tz = self::zone((string) ($facts['tz'] ?? 'Europe/Paris'));
        $today = self::fmt($now, $tz, 'd/m/Y');
        $out = [];
        // Le détail est une liste de phrases : chacune se traduit seule (dictionnaire de la page anglaise).
        $add = static function (string $key, string $label, string $state, string|array $detail, string $source, string $date) use (&$out): void {
            $out[] = [
                'key' => $key,
                'label' => $label,
                'state' => $state,
                'status_label' => self::stateLabels()[$state] ?? '',
                'detail' => array_values(array_filter(is_array($detail) ? $detail : [$detail], static fn (string $d): bool => $d !== '')),
                'source' => ['label' => $source, 'date' => $date],
            ];
        };

        // 1. Sessions de jeu qui comptent sur 7, 30 et 90 jours.
        $s = self::sessions($facts);
        $sessionSource = 'Serveur de jeu de la communauté (sessions de jeu)';
        $threshold = Sessions::thresholdLabel((int) ($facts['rules']['min_minutes'] ?? Sessions::DEFAULT_MIN_MINUTES));
        if ($s['state'] === 'off') {
            $add('sessions', 'Sessions de jeu qui comptent', self::STATE_NONE, 'Le suivi des sessions de jeu n’est pas actif sur ce site.', $sessionSource, $today);
        } elseif ($s['state'] === 'unlinked') {
            $add('sessions', 'Sessions de jeu qui comptent', self::STATE_NONE, 'Compte Steam non relié : aucune session ne peut être attribuée à ce membre.', $sessionSource, $today);
        } else {
            $state = $s['s7'] >= self::AVAILABLE_MIN_7 || $s['s30'] >= self::AVAILABLE_MIN_30 ? self::STATE_OK : self::STATE_WARN;
            $detail = self::tpl('%1 sur 7 jours, %2 sur 30 jours, %3 sur 90 jours (sessions de %4).', [$s['s7'], $s['s30'], $s['s90'], $threshold]);
            $add('sessions', 'Sessions de jeu qui comptent', $state, $detail, $sessionSource, $today);
        }

        // 2. Dernière session qui compte.
        if ($s['state'] === 'ready') {
            $last = $s['last'];
            if ($last === null) {
                $add('last_session', 'Dernière session qui compte', self::STATE_WARN, 'Aucune sur les 90 derniers jours.', $sessionSource, $today);
            } else {
                $ago = self::daysAgo((int) $last['started_at'], $now, $tz);
                $detail = self::tpl('Le %1, %2 (%3).', [self::fmt((int) $last['started_at'], $tz, 'd/m/Y H:i'), Sessions::durationLabel((int) $last['duration_s']), self::agoLabel($ago)]);
                $add('last_session', 'Dernière session qui compte', $ago <= self::RECENT_DAYS ? self::STATE_OK : self::STATE_WARN, $detail, $sessionSource, self::fmt((int) $last['started_at'], $tz, 'd/m/Y'));
            }
        }

        // 2 bis. Heures de jeu remontées par le mod (cumul), si la table existe.
        if (is_array($facts['playtime'] ?? null)) {
            self::playtimeStep($facts, $s, $add, $now, $tz, $today);
        }

        // 3. Dernière connexion au site.
        $loginTs = self::ts($facts['last_login_at'] ?? null);
        $accountSource = 'Compte sur le site (dernière connexion)';
        if ($loginTs === null) {
            $add('site_login', 'Dernière connexion au site', self::STATE_NONE, 'Jamais connecté au site, ou date inconnue.', $accountSource, $today);
        } else {
            $ago = self::daysAgo($loginTs, $now, $tz);
            $add('site_login', 'Dernière connexion au site', $ago <= self::RECENT_DAYS ? self::STATE_OK : self::STATE_WARN, self::tpl('Le %1 (%2).', [self::fmt($loginTs, $tz, 'd/m/Y H:i'), self::agoLabel($ago)]), $accountSource, self::fmt($loginTs, $tz, 'd/m/Y'));
        }

        // 4. Activité récente sur le site (journal des actions), si le journal existe.
        if (array_key_exists('site_activity_at', $facts) && $facts['site_activity_at'] !== false) {
            $actTs = self::ts($facts['site_activity_at']);
            $journal = 'Journal des actions du site';
            if ($actTs === null) {
                $add('site_activity', 'Activité sur le site', self::STATE_NONE, 'Aucune action enregistrée au nom du membre.', $journal, $today);
            } else {
                $ago = self::daysAgo($actTs, $now, $tz);
                $add('site_activity', 'Activité sur le site', $ago <= self::RECENT_DAYS ? self::STATE_OK : self::STATE_WARN, self::tpl('Dernière action enregistrée le %1 (%2).', [self::fmt($actTs, $tz, 'd/m/Y'), self::agoLabel($ago)]), $journal, self::fmt($actTs, $tz, 'd/m/Y'));
            }
        }

        // 5. Absences déclarées (Suivi RH), si le module existe.
        if (is_array($facts['absences'] ?? null)) {
            $abs = self::absenceToday($facts);
            $next = self::absenceUpcoming($facts);
            $absSource = 'Suivi RH, absences déclarées';
            if ($abs !== null) {
                $end = self::day((string) ($abs['ends_on'] ?? ''));
                $detail = $end !== ''
                    ? self::tpl('Absence en cours jusqu’au %1 : %2.', [self::dmy($end), self::reason($abs)])
                    : self::tpl('Absence en cours, sans date de fin : %1.', [self::reason($abs)]);
                $add('absences', 'Absences déclarées', self::STATE_WARN, $detail, $absSource, self::dmy(self::day((string) ($abs['starts_on'] ?? ''))));
            } elseif ($next !== null) {
                $add('absences', 'Absences déclarées', self::STATE_OK, self::tpl('Aucune absence aujourd’hui ; une absence est prévue à partir du %1.', [self::dmy(self::day((string) $next['starts_on']))]), $absSource, $today);
            } else {
                $add('absences', 'Absences déclarées', self::STATE_OK, 'Aucune absence en cours ni prévue.', $absSource, $today);
            }
        }

        // 6. Position et statut.
        $position = is_array($facts['position'] ?? null) ? $facts['position'] : null;
        $accountActive = (string) ($facts['account_status'] ?? 'active') === 'active';
        $posSource = 'Fiche du membre, Position et statut';
        if ($position === null) {
            $add('position', 'Position et statut', $accountActive ? self::STATE_NONE : self::STATE_WARN, $accountActive ? 'Aucune position administrative enregistrée.' : 'Aucune position enregistrée et compte inactif.', $posSource, $today);
        } else {
            $engaged = self::positionEngaged($position);
            $detail = [self::tpl('Position : %1.', [(string) $position['label']]), $accountActive ? '' : 'Le compte est inactif.'];
            $add('position', 'Position et statut', $engaged && $accountActive ? self::STATE_OK : self::STATE_WARN, $detail, $posSource, $today);
        }

        // 7. Réponses aux convocations (calendrier), seulement si la communauté a des événements récents.
        $ev = is_array($facts['events'] ?? null) ? $facts['events'] : null;
        if ($ev !== null && (int) ($ev['sessions_90'] ?? 0) > 0) {
            $present = (int) ($ev['present_90'] ?? 0);
            $total = (int) ($ev['sessions_90'] ?? 0);
            $lastPresent = self::ts($ev['last_present_at'] ?? null);
            $detail = [
                self::tpl('Présent à %1 convocation(s) sur %2 en 90 jours.', [$present, $total]),
                $lastPresent !== null ? self::tpl('Dernière présence le %1.', [self::fmt($lastPresent, $tz, 'd/m/Y')]) : '',
            ];
            $state = $present > 0 ? self::STATE_OK : self::STATE_WARN;
            $add('events', 'Réponses aux convocations', $state, $detail, 'Calendrier de la communauté (réponses et pointages)', $lastPresent !== null ? self::fmt($lastPresent, $tz, 'd/m/Y') : $today);
        }

        // 8. Sanctions et suivi RH ouvert.
        $sanctions = is_array($facts['sanctions'] ?? null) ? $facts['sanctions'] : null;
        $followup = is_array($facts['followup'] ?? null) ? $facts['followup'] : null;
        if ($sanctions !== null || $followup !== null) {
            $bits = [];
            $warn = false;
            if ($sanctions !== null) {
                $active = (int) ($sanctions['active'] ?? 0);
                $warn = $warn || $active > 0;
                $bits[] = $active > 0 ? self::tpl('%1 mesure(s) disciplinaire(s) en vigueur.', [$active]) : 'Aucune mesure disciplinaire en vigueur.';
            }
            if ($followup !== null) {
                $late = (int) ($followup['late'] ?? 0);
                $open = (int) ($followup['open'] ?? 0);
                $warn = $warn || $late > 0;
                $bits[] = $open > 0 ? self::tpl('Suivi du dossier : %1 étape(s) ouverte(s), dont %2 en retard.', [$open, $late]) : 'Suivi du dossier : rien d’ouvert.';
            }
            $add('followup', 'Sanctions et suivi RH', $warn ? self::STATE_WARN : self::STATE_OK, $bits, 'Suivi RH et Suivi du dossier', $today);
        }

        return $out;
    }

    /**
     * Étape « Heures de jeu remontées » : cumul du mod, date de la dernière mise à jour, écart depuis la dernière vérification
     * et limite de la source.
     *
     * @param array<string, mixed> $facts
     * @param array{state: string, has_data: bool} $s
     */
    private static function playtimeStep(array $facts, array $s, callable $add, int $now, DateTimeZone $tz, string $today): void
    {
        $pt = $facts['playtime'];
        $source = 'Heures de jeu remontées par le mod (user_arma_playtime)';
        $label = 'Heures de jeu remontées';
        $total = (int) ($pt['total_seconds'] ?? 0);
        if ($total <= 0) {
            $add('playtime', $label, self::STATE_NONE, 'Aucune heure de jeu remontée par le mod pour ce membre.', $source, $today);

            return;
        }
        $min = max(60, (int) ($facts['rules']['min_minutes'] ?? Sessions::DEFAULT_MIN_MINUTES) * 60);
        $previous = is_array($pt['previous'] ?? null) ? $pt['previous'] : null;
        $a = PlaytimeAvailability::activity($pt, $min, $now, (string) ($facts['tz'] ?? 'Europe/Paris'), $previous);
        $detail = [self::tpl('Cumul : %1 de jeu remontées par le mod (serveur : %2, Zeus : %3, éditeur : %4).', [
            Sessions::durationLabel($total),
            Sessions::durationLabel((int) ($pt['server_seconds'] ?? 0)),
            Sessions::durationLabel((int) ($pt['zeus_seconds'] ?? 0)),
            Sessions::durationLabel((int) ($pt['editor_seconds'] ?? 0)),
        ])];
        if ($a['last_at'] !== null) {
            $detail[] = self::tpl('Dernière mise à jour du cumul le %1 (%2).', [self::fmt($a['last_at'], $tz, 'd/m/Y'), self::agoLabel((int) $a['ago'])]);
        } else {
            $detail[] = 'Date de la dernière mise à jour du cumul inconnue.';
        }
        if ($a['gained'] !== null && $a['previous_at'] !== null) {
            $detail[] = $a['gained'] > 0
                ? self::tpl('Depuis la vérification du %1 : +%2 de jeu.', [self::fmt($a['previous_at'], $tz, 'd/m/Y'), Sessions::durationLabel($a['gained'])])
                : self::tpl('Cumul inchangé depuis la vérification du %1.', [self::fmt($a['previous_at'], $tz, 'd/m/Y')]);
        }
        $detail[] = 'Limite de cette source : un cumul sans détail par session, il ne dit ni quand ni combien de temps d’affilée le membre a joué.';
        $detail[] = !empty($s['has_data'])
            ? 'Des sessions détaillées existent : elles restent prioritaires pour la conclusion.'
            : 'Sans session détaillée, ce cumul sert à la conclusion proposée.';
        $state = match ($a['key']) {
            AvailabilityCheck::AVAILABLE => self::STATE_OK,
            null => self::STATE_NONE,
            default => self::STATE_WARN,
        };
        $add('playtime', $label, $state, $detail, $source, $a['last_at'] !== null ? self::fmt($a['last_at'], $tz, 'd/m/Y') : $today);
    }

    /**
     * Conclusion proposée et la règle qui l'explique.
     *
     * @param array<string, mixed> $facts
     * @return array{key: string, label: string, detail: string}
     */
    public static function verdict(array $facts): array
    {
        $now = (int) ($facts['now'] ?? time());
        $tz = self::zone((string) ($facts['tz'] ?? 'Europe/Paris'));
        $make = static fn (string $key, string $rule): array => ['key' => $key, 'label' => self::conclusionLabel($key), 'detail' => $rule];

        $abs = self::absenceToday($facts);
        if ($abs !== null) {
            return $make(self::ABSENT, 'Règle : une absence déclarée couvre aujourd’hui.');
        }
        $position = is_array($facts['position'] ?? null) ? $facts['position'] : null;
        if ($position !== null && !self::positionEngaged($position)) {
            return $make(self::ABSENT, 'Règle : la position du membre ne l’engage pas en service.');
        }

        $s = self::sessions($facts);
        // Sans session détaillée, les heures de jeu remontées (cumul) disent si le membre joue encore.
        if (!($s['state'] === 'ready' && $s['has_data']) && is_array($facts['playtime'] ?? null)) {
            $a = PlaytimeAvailability::activity(
                $facts['playtime'],
                max(60, (int) ($facts['rules']['min_minutes'] ?? Sessions::DEFAULT_MIN_MINUTES) * 60),
                $now,
                (string) ($facts['tz'] ?? 'Europe/Paris'),
                is_array($facts['playtime']['previous'] ?? null) ? $facts['playtime']['previous'] : null
            );
            if ($a['key'] === self::AVAILABLE || $a['key'] === self::LOW) {
                return $make($a['key'], $a['rule']);
            }
        }
        if ($s['state'] === 'ready') {
            if ($s['s7'] >= self::AVAILABLE_MIN_7 || $s['s30'] >= self::AVAILABLE_MIN_30) {
                return $make(self::AVAILABLE, 'Règle : au moins 1 session qui compte sur 7 jours ou 2 sur 30 jours.');
            }
            if ($s['s90'] > 0) {
                return $make(self::LOW, 'Règle : moins de 2 sessions qui comptent sur 30 jours, mais au moins une sur 90 jours.');
            }

            return $make(self::INACTIVE, 'Règle : aucune session qui compte sur 90 jours.');
        }

        // Sans données de jeu : convocations, puis connexion au site.
        $ev = is_array($facts['events'] ?? null) ? $facts['events'] : null;
        $lastPresent = self::ts($ev['last_present_at'] ?? null);
        if ($lastPresent !== null && self::daysAgo($lastPresent, $now, $tz) <= self::RECENT_DAYS) {
            return $make(self::AVAILABLE, 'Règle, sans sessions de jeu : présent à une convocation sur les 30 derniers jours.');
        }
        $login = self::ts($facts['last_login_at'] ?? null);
        if ($login !== null && self::daysAgo($login, $now, $tz) <= self::RECENT_DAYS) {
            return $make(self::LOW, 'Règle, sans sessions de jeu : connecté au site sur les 30 derniers jours, sans présence à une convocation.');
        }

        return $make(self::INACTIVE, 'Règle, sans sessions de jeu : ni présence à une convocation ni connexion au site sur 30 jours.');
    }

    /**
     * Résumé compact pour la liste groupée et l'historique : nombre d'étapes par état.
     *
     * @param list<array{state: string}> $steps
     * @return array{ok: int, warn: int, none: int}
     */
    public static function counts(array $steps): array
    {
        $out = [self::STATE_OK => 0, self::STATE_WARN => 0, self::STATE_NONE => 0];
        foreach ($steps as $st) {
            $k = (string) ($st['state'] ?? '');
            if (isset($out[$k])) {
                $out[$k]++;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $facts
     * @return array{state: string, has_data: bool, s7: int, s30: int, s90: int, last: ?array<string, mixed>}
     */
    private static function sessions(array $facts): array
    {
        $panel = is_array($facts['sessions'] ?? null) ? $facts['sessions'] : null;
        $sum = is_array($panel['summary'] ?? null) ? $panel['summary'] : [];
        $state = match (true) {
            $panel === null || empty($panel['ready']) => 'off',
            empty($sum['linked']) => 'unlinked',
            default => 'ready',
        };
        $w = static fn (int $d): int => (int) ($sum['windows'][$d]['sessions'] ?? 0);

        return [
            'state' => $state,
            'has_data' => !empty($sum['has_data']),
            's7' => $w(7),
            's30' => $w(30),
            's90' => $w(90),
            'last' => is_array($sum['last_qualifying'] ?? null) ? $sum['last_qualifying'] : null,
        ];
    }

    /**
     * Absence déclarée qui couvre aujourd'hui (statut actif), la plus récente.
     *
     * @param array<string, mixed> $facts
     * @return array<string, mixed>|null
     */
    private static function absenceToday(array $facts): ?array
    {
        $today = self::fmt((int) ($facts['now'] ?? time()), self::zone((string) ($facts['tz'] ?? 'Europe/Paris')), 'Y-m-d');
        $found = null;
        foreach (is_array($facts['absences'] ?? null) ? $facts['absences'] : [] as $a) {
            if ((string) ($a['status'] ?? 'active') !== 'active') {
                continue;
            }
            $start = self::day((string) ($a['starts_on'] ?? ''));
            $end = self::day((string) ($a['ends_on'] ?? ''));
            if ($start !== '' && $start <= $today && ($end === '' || $end >= $today) && ($found === null || $start > self::day((string) $found['starts_on']))) {
                $found = $a;
            }
        }

        return $found;
    }

    /**
     * Prochaine absence déclarée dans les 30 jours.
     *
     * @param array<string, mixed> $facts
     * @return array<string, mixed>|null
     */
    private static function absenceUpcoming(array $facts): ?array
    {
        $now = (int) ($facts['now'] ?? time());
        $tz = self::zone((string) ($facts['tz'] ?? 'Europe/Paris'));
        $today = self::fmt($now, $tz, 'Y-m-d');
        $limit = self::fmt($now + self::RECENT_DAYS * 86400, $tz, 'Y-m-d');
        $found = null;
        foreach (is_array($facts['absences'] ?? null) ? $facts['absences'] : [] as $a) {
            $start = self::day((string) ($a['starts_on'] ?? ''));
            if ((string) ($a['status'] ?? 'active') === 'active' && $start > $today && $start <= $limit && ($found === null || $start < self::day((string) $found['starts_on']))) {
                $found = $a;
            }
        }

        return $found;
    }

    /** @param array<string, mixed> $position */
    private static function positionEngaged(array $position): bool
    {
        if ((string) ($position['slug'] ?? '') === \App\Support\AdministrativePositionCatalog::INACTIVE) {
            return false;
        }

        return mb_strtolower(trim((string) ($position['service'] ?? '')), 'UTF-8') !== 'non';
    }

    /** @param array<string, mixed> $absence */
    private static function reason(array $absence): string
    {
        return mb_strtolower(\App\Repositories\PersonnelAbsenceRepository::reasonLabel((string) ($absence['reason'] ?? 'autre')), 'UTF-8');
    }

    private static function agoLabel(int $days): string
    {
        return match (true) {
            $days <= 0 => 'aujourd’hui',
            $days === 1 => 'il y a 1 jour',
            default => self::tpl('il y a %1 jours', [$days]),
        };
    }

    private static function daysAgo(int $ts, int $now, DateTimeZone $tz): int
    {
        $a = new DateTimeImmutable(self::fmt($ts, $tz, 'Y-m-d'), $tz);
        $b = new DateTimeImmutable(self::fmt($now, $tz, 'Y-m-d'), $tz);

        return max(0, (int) $a->diff($b)->format('%r%a'));
    }

    /** @param list<int|string> $values */
    private static function tpl(string $template, array $values): string
    {
        $search = [];
        foreach (array_keys($values) as $i) {
            $search[] = '%' . ($i + 1);
        }

        return str_replace($search, array_map('strval', $values), $template);
    }

    private static function ts(mixed $v): ?int
    {
        if (is_int($v)) {
            return $v > 0 ? $v : null;
        }
        $v = trim((string) $v);
        if ($v === '' || str_starts_with($v, '0000-00-00')) {
            return null;
        }
        $t = strtotime($v);

        return $t === false ? null : $t;
    }

    private static function day(string $raw): string
    {
        return preg_match('/^(\d{4}-\d{2}-\d{2})/', trim($raw), $m) === 1 ? $m[1] : '';
    }

    private static function dmy(string $ymd): string
    {
        return $ymd === '' ? '' : substr($ymd, 8, 2) . '/' . substr($ymd, 5, 2) . '/' . substr($ymd, 0, 4);
    }

    private static function fmt(int $ts, DateTimeZone $tz, string $format): string
    {
        return (new DateTimeImmutable('@' . $ts))->setTimezone($tz)->format($format);
    }

    private static function zone(string $tz): DateTimeZone
    {
        try {
            return new DateTimeZone($tz !== '' ? $tz : 'Europe/Paris');
        } catch (\Throwable) {
            return new DateTimeZone('Europe/Paris');
        }
    }
}
