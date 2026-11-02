<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Met en forme les données du tableau de bord de pilotage (back-office communauté) pour la vue.
 *
 * Aucune requête ici : le contrôleur fournit les données brutes, ce présentateur calcule libellés,
 * pourcentages et liens autorisés. Ce qui n’a pas de donnée réelle n’est pas inventé : le bloc
 * correspondant reçoit un état vide.
 */
final class PilotageDashboardPresenter
{

    /**
     * @param array<string, mixed> $in
     * @return array<string, mixed>
     */
    public static function build(array $in): array
    {
        $now = (int) ($in['now'] ?? time());
        $allowed = $in['link_allowed'] ?? null;
        $can = static function (string $href) use ($allowed): bool {
            return !is_callable($allowed) || (bool) $allowed($href);
        };
        $link = static fn (string $href): ?string => $can($href) ? $href : null;

        $kpis = self::kpiMap(is_array($in['kpis'] ?? null) ? $in['kpis'] : []);
        if (!isset($in['moderation_open']) && isset($kpis['moderation_open'])) {
            $in['moderation_open'] = (int) $kpis['moderation_open'];
        }
        if (!isset($in['training_expiring']) && isset($kpis['training_expiring'])) {
            $in['training_expiring'] = (int) $kpis['training_expiring'];
        }
        if (!isset($in['expired_invitations']) && isset($kpis['invites_expired'])) {
            $in['expired_invitations'] = (int) $kpis['invites_expired'];
        }
        $activeMembers = isset($kpis['members_active']) ? (int) $kpis['members_active'] : max(0, (int) ($in['active_members'] ?? 0));
        $inactiveMembers = isset($kpis['members_inactive']) ? (int) $kpis['members_inactive'] : null;

        $links = [
            'effectifs' => $link(effectifs_workspace_url()),
            'affectations' => $link(effectifs_workspace_url('affectations')),
            'access_rights' => $link(effectifs_workspace_url('droits')),
            'events' => $link(url('back-office/events')),
            'recruitments' => $link(url('back-office/recruitments')),
            'elevations' => $link(effectifs_workspace_url('elevations')),
            'readiness' => $link(url('back-office/organisation/disponibilite')),
            'journal' => $link(url('back-office/audit')),
            'training' => $link(training_lms_admin_url()),
            'invitations' => $link(url('back-office/invitations')),
            'reports' => $link(url('back-office/signalements')),
            'messages' => url('messages'),
        ];

        $upcoming = is_array($in['upcoming'] ?? null) ? array_values($in['upcoming']) : [];
        $rsvp = is_array($in['rsvp'] ?? null) ? $in['rsvp'] : [];
        $heroEvent = self::pickHeroEvent($upcoming);
        $hero = $heroEvent !== null ? self::hero($heroEvent, $rsvp, $activeMembers, $now, $links) : null;

        $decisions = self::decisions($in, $links, $now);
        $participation = self::participation(is_array($in['participation'] ?? null) ? $in['participation'] : []);

        return [
            'hello' => self::hello($in, $now, $activeMembers, $inactiveMembers, $hero, $decisions['total'], $links),
            'hero' => $hero,
            'decisions' => $decisions,
            'kpis' => self::kpiCards($kpis, $activeMembers, $inactiveMembers, $in, $participation, $now, $links),
            'participation' => $participation,
            'units' => self::units(is_array($in['units'] ?? null) ? $in['units'] : []),
            'pipeline' => $links['recruitments'] !== null ? self::pipeline($in, $links, $now) : null,
            'agenda' => self::agenda($upcoming, $rsvp, $heroEvent, $now, $links['events'] !== null),
            'feed' => self::feed(is_array($in['activity'] ?? null) ? $in['activity'] : [], $now),
            'links' => $links,
        ];
    }

    /** @param list<array<string, mixed>> $rows @return array<string, string> */
    private static function kpiMap(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !empty($row['error'])) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            $value = $row['value'] ?? null;
            if ($id !== '' && is_numeric($value)) {
                $out[$id] = (string) $value;
            }
        }

        return $out;
    }

    /**
     * Prochaine opération : le premier créneau de type « opération », sinon le premier créneau à venir.
     *
     * @param list<array<string, mixed>> $upcoming
     * @return array<string, mixed>|null
     */
    private static function pickHeroEvent(array $upcoming): ?array
    {
        foreach ($upcoming as $ev) {
            if (is_array($ev) && (int) ($ev['id'] ?? 0) > 0 && (string) ($ev['event_type'] ?? '') === 'operation') {
                return $ev;
            }
        }
        foreach ($upcoming as $ev) {
            if (is_array($ev) && (int) ($ev['id'] ?? 0) > 0) {
                return $ev;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $ev
     * @param array<int|string, mixed> $rsvp
     * @param array<string, string|null> $links
     * @return array<string, mixed>
     */
    private static function hero(array $ev, array $rsvp, int $activeMembers, int $now, array $links): array
    {
        $id = (int) $ev['id'];
        $start = self::ts((string) ($ev['starts_at'] ?? ''));
        $end = self::ts((string) ($ev['ends_at'] ?? ''));
        $sum = is_array($rsvp[$id] ?? null) ? $rsvp[$id] : [];
        $yes = is_array($sum['yes'] ?? null) ? count($sum['yes']) : 0;
        $maybe = is_array($sum['maybe'] ?? null) ? count($sum['maybe']) : 0;
        $no = is_array($sum['no'] ?? null) ? count($sum['no']) : 0;
        $answered = $yes + $maybe + $no;
        $noReply = max(0, $activeMembers - $answered);
        $base = max(1, $yes + $maybe + $no + $noReply);

        $type = (string) ($ev['event_type'] ?? 'evenement');
        $kicker = match ($type) {
            'operation' => 'Prochaine opération',
            'formation' => 'Prochaine formation',
            default => 'Prochain rendez-vous',
        };
        $campaign = trim((string) ($ev['campaign_tag'] ?? ''));

        $ongoing = $start !== null && $start <= $now;
        $countdown = null;
        if ($start !== null && !$ongoing) {
            $diff = $start - $now;
            $countdown = [
                'days' => intdiv($diff, 86400),
                'hours' => intdiv($diff % 86400, 3600),
                'minutes' => intdiv($diff % 3600, 60),
            ];
        }

        $cover = null;
        if (class_exists(CommunityEventDetails::class)) {
            $cover = CommunityEventDetails::publicCoverUrl(isset($ev['cover_image_path']) ? (string) $ev['cover_image_path'] : null);
        }

        $eventsAllowed = $links['events'] !== null;

        return [
            'id' => $id,
            'kicker' => $kicker,
            'noun' => match ($type) {
                'operation' => 'La prochaine opération',
                'formation' => 'La prochaine formation',
                default => 'Le prochain rendez-vous',
            },
            'campaign' => $campaign,
            'title' => trim((string) ($ev['title'] ?? '')) !== '' ? trim((string) $ev['title']) : 'Créneau sans titre',
            'date_label' => $start !== null ? self::ucfirst(LocalizedCalendar::weekday((int) date('w', $start))) . ' ' . (int) date('j', $start) . ' ' . LocalizedCalendar::month((int) date('n', $start), true) : '',
            'time_label' => $start !== null ? date('H:i', $start) . ($end !== null && $end > $start ? ' – ' . date('H:i', $end) : '') : '',
            'location' => trim((string) ($ev['location'] ?? '')),
            'starts_at_iso' => $start !== null ? date('c', $start) : '',
            'ongoing' => $ongoing,
            'countdown' => $countdown,
            'cover_url' => $cover,
            'rsvp' => [
                'yes' => $yes,
                'maybe' => $maybe,
                'no' => $no,
                'no_reply' => $noReply,
                'base' => $activeMembers > 0 ? $activeMembers : $answered,
                'pct' => [
                    'yes' => round($yes / $base * 100, 1),
                    'maybe' => round($maybe / $base * 100, 1),
                    'no' => round($no / $base * 100, 1),
                ],
            ],
            'href' => $eventsAllowed ? url('back-office/events/' . $id) : null,
            'answers_href' => $eventsAllowed ? url('back-office/events/' . $id . '/reponses-nominatives') : null,
            'starts_in' => self::startsIn($start, $now),
        ];
    }

    private static function startsIn(?int $start, int $now): string
    {
        if ($start === null) {
            return '';
        }
        if ($start <= $now) {
            return 'en cours';
        }
        $days = (int) floor((strtotime(date('Y-m-d', $start)) - strtotime(date('Y-m-d', $now))) / 86400);
        if ($days <= 0) {
            $hours = (int) ceil(($start - $now) / 3600);

            return $hours <= 1 ? 'moins d’une heure' : $hours . ' heures';
        }
        if ($days === 1) {
            return '1 jour';
        }

        return $days . ' jours';
    }

    /**
     * @param array<string, mixed> $in
     * @param array<string, string|null> $links
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    private static function decisions(array $in, array $links, int $now): array
    {
        $items = [];
        $total = 0;

        if ($links['elevations'] !== null) {
            $kindLabels = is_array($in['elevation_kind_labels'] ?? null) ? $in['elevation_kind_labels'] : [];
            $rows = is_array($in['elevations'] ?? null) ? $in['elevations'] : [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $name = self::personName($row['target_display_name'] ?? null, $row['target_email'] ?? null, 'Membre');
                $kind = (string) ($row['kind'] ?? 'general');
                $items[] = [
                    'icon' => 'up',
                    'tone' => 'info',
                    'title' => $name,
                    'meta' => 'Élévation · ' . (string) ($kindLabels[$kind] ?? 'situation RH'),
                    'cta' => 'Examiner',
                    'href' => (string) $links['elevations'],
                ];
            }
            $total += max(count($rows), (int) ($in['elevation_count'] ?? 0));
        }

        if ($links['recruitments'] !== null) {
            $counts = is_array($in['enlistment_counts'] ?? null) ? $in['enlistment_counts'] : [];
            $submitted = (int) ($counts['submitted'] ?? 0);
            $listed = 0;
            foreach (is_array($in['enlistments'] ?? null) ? $in['enlistments'] : [] as $row) {
                if (!is_array($row) || (string) ($row['status'] ?? '') !== 'submitted') {
                    continue;
                }
                $listed++;
                $id = (int) ($row['id'] ?? 0);
                $created = self::ts((string) ($row['created_at'] ?? ''));
                $items[] = [
                    'icon' => 'user',
                    'tone' => 'primary',
                    'title' => self::recruitName($row),
                    'meta' => 'Candidature · ' . ($created !== null ? 'reçue ' . self::ago($created, $now) : 'à instruire'),
                    'cta' => 'Examiner',
                    'href' => $id > 0 ? url('back-office/recruitments/' . $id) : (string) $links['recruitments'],
                ];
            }
            $total += max($listed, $submitted);
        }

        $wq = is_array($in['work_queue'] ?? null) ? $in['work_queue'] : [];
        $aggregates = [
            ['n' => isset($in['training_expiring']) ? (int) $in['training_expiring'] : (is_array($wq['training_expiring'] ?? null) ? count($wq['training_expiring']) : 0), 'link' => $links['training'], 'icon' => 'award', 'tone' => 'warning',
                'one' => '1 formation arrive à échéance', 'many' => '%d formations arrivent à échéance', 'meta' => 'Formations · sous 30 jours', 'cta' => 'Voir'],
            ['n' => (int) ($in['profile_gaps_total'] ?? 0), 'link' => '#dossiers-incomplets', 'icon' => 'file', 'tone' => 'warning',
                'one' => '1 dossier à compléter', 'many' => '%d dossiers à compléter', 'meta' => 'Effectifs · fonction, grade, rôle ou image manquants', 'cta' => 'Voir'],
            ['n' => isset($in['expired_invitations']) ? (int) $in['expired_invitations'] : (is_array($wq['expired_invitations'] ?? null) ? count($wq['expired_invitations']) : 0), 'link' => $links['invitations'], 'icon' => 'mail', 'tone' => 'warning',
                'one' => '1 invitation expirée', 'many' => '%d invitations expirées', 'meta' => 'Accès · à renvoyer ou retirer', 'cta' => 'Gérer'],
            ['n' => is_array($wq['users_without_unit'] ?? null) ? count($wq['users_without_unit']) : 0, 'link' => $links['affectations'], 'icon' => 'user', 'tone' => 'warning',
                'one' => '1 membre sans unité', 'many' => '%d membres sans unité', 'meta' => 'Effectifs · à affecter', 'cta' => 'Affecter'],
            ['n' => is_array($wq['users_without_role'] ?? null) ? count($wq['users_without_role']) : 0, 'link' => $links['access_rights'], 'icon' => 'user', 'tone' => 'warning',
                'one' => '1 membre sans rôle', 'many' => '%d membres sans rôle', 'meta' => 'Accès · droits à attribuer', 'cta' => 'Attribuer'],
            ['n' => (int) ($in['moderation_open'] ?? 0), 'link' => $links['reports'], 'icon' => 'flag', 'tone' => 'danger',
                'one' => '1 signalement à traiter', 'many' => '%d signalements à traiter', 'meta' => 'Modération', 'cta' => 'Traiter'],
        ];
        foreach ($aggregates as $agg) {
            if ($agg['n'] < 1 || $agg['link'] === null) {
                continue;
            }
            $items[] = [
                'icon' => $agg['icon'],
                'tone' => $agg['tone'],
                'title' => $agg['n'] === 1 ? $agg['one'] : sprintf($agg['many'], $agg['n']),
                'meta' => $agg['meta'],
                'cta' => $agg['cta'],
                'href' => (string) $agg['link'],
            ];
            $total++;
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @param array<string, mixed> $in
     * @param array<string, mixed>|null $hero
     * @param array<string, string|null> $links
     * @return array<string, mixed>
     */
    private static function hello(array $in, int $now, int $active, ?int $inactive, ?array $hero, int $decisions, array $links): array
    {
        $hour = (int) date('G', $now);
        $name = trim((string) ($in['viewer_name'] ?? ''));
        $tenant = trim((string) ($in['tenant_name'] ?? ''));

        $actions = [];
        if ($links['effectifs'] !== null) {
            $actions[] = ['label' => 'Effectifs', 'href' => $links['effectifs'], 'accent' => true];
        }

        return [
            'date_label' => LocalizedCalendar::weekday((int) date('w', $now)) . ' ' . (int) date('j', $now) . ' ' . LocalizedCalendar::month((int) date('n', $now)),
            'greeting' => ($hour < 5 || $hour >= 18) ? 'Bonsoir' : 'Bonjour',
            'name' => $name,
            'tenant' => $tenant !== '' ? $tenant : 'Votre communauté',
            'active' => $active,
            'total' => $inactive !== null ? $active + $inactive : null,
            'next_in' => $hero !== null ? (string) $hero['starts_in'] : null,
            'next_noun' => $hero !== null ? (string) $hero['noun'] : 'La prochaine activité',
            'decisions' => $decisions,
            'actions' => $actions,
        ];
    }

    /**
     * @param array<string, string> $kpis
     * @param array<string, mixed> $in
     * @param array<string, mixed> $participation
     * @param array<string, string|null> $links
     * @return list<array<string, mixed>>
     */
    private static function kpiCards(array $kpis, int $active, ?int $inactive, array $in, array $participation, int $now, array $links): array
    {
        $cards = [];

        $active30 = isset($kpis['active_30d']) ? (int) $kpis['active_30d'] : null;
        $cards[] = [
            'id' => 'effectif',
            'label' => 'Effectif actif',
            'value' => (string) $active,
            'unit' => $inactive !== null ? '/ ' . ($active + $inactive) : '',
            'badge' => null,
            'note' => $active30 !== null ? $active30 . ' membre' . ($active30 > 1 ? 's' : '') . ' venu' . ($active30 > 1 ? 's' : '') . ' sur le site en 30 jours' : 'Membres au statut actif',
            'note_href' => null,
            'spark' => null,
            'spark_tone' => 'primary',
        ];

        $presence = is_array($in['presence'] ?? null) ? $in['presence'] : null;
        $series = [];
        foreach ($participation['bars'] ?? [] as $bar) {
            if ((int) $bar['registered'] > 0) {
                $series[] = (int) round(min(1, (int) $bar['present'] / (int) $bar['registered']) * 100);
            }
        }
        if ($presence !== null && (int) $presence['current_checked'] > 0 && (int) $presence['current_yes'] > 0) {
            $rate = (int) round((int) $presence['current_present'] / (int) $presence['current_yes'] * 100);
            $badge = null;
            if ((int) $presence['previous_yes'] > 0) {
                $prev = (int) round((int) $presence['previous_present'] / (int) $presence['previous_yes'] * 100);
                $delta = $rate - $prev;
                $badge = ['text' => ($delta >= 0 ? '+' : '−') . abs($delta) . ' pts', 'tone' => $delta >= 0 ? 'up' : 'down'];
            }
            $cards[] = [
                'id' => 'presence',
                'label' => 'Présence moyenne · 30 j',
                'value' => $rate . ' %',
                'unit' => '',
                'badge' => $badge,
                'note' => 'Confirmés présents au pointage',
                'note_href' => null,
                'spark' => $participation['has_checkins'] ? $series : null,
                'spark_tone' => 'primary',
            ];
        } else {
            $cards[] = [
                'id' => 'presence',
                'label' => 'Présence moyenne · 30 j',
                'value' => '—',
                'unit' => '',
                'badge' => null,
                'note' => 'Aucun pointage sur les 30 derniers jours',
                'note_href' => $links['events'],
                'spark' => null,
                'spark_tone' => 'primary',
            ];
        }

        $month = is_array($in['month_events'] ?? null) ? $in['month_events'] : null;
        $monthName = LocalizedCalendar::month((int) date('n', $now));
        $attendees = [];
        foreach ($participation['bars'] ?? [] as $bar) {
            $attendees[] = $participation['has_checkins'] ? (int) $bar['present'] : (int) $bar['registered'];
        }
        $avg = $attendees !== [] ? (int) round(array_sum($attendees) / count($attendees)) : null;
        $total = $month !== null ? (int) $month['total'] : 0;
        $done = $month !== null ? (int) $month['done'] : 0;
        $cards[] = [
            'id' => 'operations',
            'label' => 'Activités · ' . $monthName,
            'value' => $month !== null ? (string) $total : '—',
            'unit' => $month !== null && $total > 0 ? ($done === $total ? ($total > 1 ? 'toutes terminées' : 'terminée') : 'dont ' . $done . ' terminée' . ($done > 1 ? 's' : '')) : '',
            'badge' => null,
            'note' => $avg !== null
                ? 'Moy. ' . $avg . ' ' . ($participation['has_checkins'] ? 'présent' : 'inscrit') . ($avg > 1 ? 's' : '') . ' par activité'
                : 'Aucune activité terminée pour l’instant',
            'note_href' => null,
            'spark' => count($attendees) >= 2 ? $attendees : null,
            'spark_tone' => 'ink',
        ];

        $gaps = isset($in['profile_gaps_total']) && $in['profile_gaps_total'] !== null ? (int) $in['profile_gaps_total'] : null;
        if ($gaps !== null && $active > 0) {
            $complete = (int) round(max(0, $active - $gaps) / $active * 100);
            $cards[] = [
                'id' => 'dossiers',
                'label' => 'Dossiers complets',
                'value' => $complete . ' %',
                'unit' => '',
                'badge' => $gaps > 0 ? ['text' => $gaps . ' à compléter', 'tone' => 'down'] : ['text' => 'à jour', 'tone' => 'up'],
                'note' => $gaps > 0 ? 'Voir les dossiers' : 'Tous les dossiers sont renseignés',
                'note_href' => $gaps > 0 ? '#dossiers-incomplets' : null,
                'spark' => null,
                'spark_tone' => 'warning',
            ];
        } else {
            $cards[] = [
                'id' => 'dossiers',
                'label' => 'Dossiers complets',
                'value' => '—',
                'unit' => '',
                'badge' => null,
                'note' => 'Indicateur indisponible pour le moment',
                'note_href' => null,
                'spark' => null,
                'spark_tone' => 'warning',
            ];
        }

        return $cards;
    }

    /**
     * @param list<array<string, mixed>> $rows (du plus récent au plus ancien)
     * @return array{bars: list<array<string, mixed>>, scale: int, gridlines: list<int>, has_checkins: bool}
     */
    private static function participation(array $rows): array
    {
        $bars = [];
        $max = 0;
        $hasCheckins = false;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $registered = max(0, (int) ($row['registered'] ?? 0));
            $present = max(0, (int) ($row['present'] ?? 0));
            $hasCheckins = $hasCheckins || $present > 0;
            $max = max($max, $registered, $present);
            $title = trim((string) ($row['title'] ?? ''));
            $start = self::ts((string) ($row['starts_at'] ?? ''));
            $bars[] = [
                'title' => $title !== '' ? $title : 'Sans titre',
                'short' => self::shortTitle($title !== '' ? $title : 'Sans titre'),
                'date' => $start !== null ? date('d/m', $start) : '',
                'registered' => $registered,
                'present' => $present,
            ];
        }
        $step = self::niceStep($max);
        $scale = max($step * 4, 4);
        foreach ($bars as &$bar) {
            $outer = max($bar['registered'], $bar['present']);
            $bar['outer_pct'] = $outer > 0 ? round($outer / $scale * 100, 1) : 0.0;
            $bar['inner_pct'] = $outer > 0 ? round($bar['present'] / $outer * 100, 1) : 0.0;
        }
        unset($bar);

        // Affichage chronologique : la plus ancienne à gauche.
        return [
            'bars' => array_reverse($bars),
            'scale' => $scale,
            'gridlines' => [$step, $step * 2, $step * 3],
            'has_checkins' => $hasCheckins,
        ];
    }

    private static function niceStep(int $max): int
    {
        $raw = max(1, (int) ceil($max / 4));
        foreach ([1, 2, 5, 10, 15, 20, 25, 50, 100, 200, 500] as $s) {
            if ($s >= $raw) {
                return $s;
            }
        }

        return (int) (ceil($raw / 100) * 100);
    }

    private static function shortTitle(string $title): string
    {
        $t = preg_replace('/^(op(é|e)ration|op\.?)\s+/iu', '', $title) ?? $title;

        return trim($t) !== '' ? trim($t) : $title;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function units(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $authorized = max(0, (int) ($row['authorized'] ?? 0));
            $filled = max(0, (int) ($row['filled'] ?? 0));
            if ($authorized === 0 && $filled === 0) {
                continue;
            }
            $score = max(0, min(100, (int) ($row['score'] ?? 0)));
            $vacant = max(0, $authorized - $filled);
            $out[] = [
                'name' => trim((string) ($row['name'] ?? '')) !== '' ? trim((string) $row['name']) : 'Unité',
                'posts' => $authorized > 0
                    ? $filled . '/' . $authorized . ' poste' . ($authorized > 1 ? 's' : '') . ($vacant > 0 ? ' · ' . $vacant . ' vacant' . ($vacant > 1 ? 's' : '') : '')
                    : $filled . ' membre' . ($filled > 1 ? 's' : ''),
                'score' => $score,
                'tone' => $score >= 80 ? 'ok' : ($score >= 70 ? 'warn' : 'bad'),
            ];
        }

        return array_slice($out, 0, 6);
    }

    /**
     * @param array<string, mixed> $in
     * @param array<string, string|null> $links
     * @return array<string, mixed>
     */
    private static function pipeline(array $in, array $links, int $now): array
    {
        $c = is_array($in['enlistment_counts'] ?? null) ? $in['enlistment_counts'] : [];
        $tiles = [
            ['n' => (int) ($c['submitted'] ?? 0), 'label' => 'À instruire', 'hot' => (int) ($c['submitted'] ?? 0) > 0],
            ['n' => (int) ($c['interview_scheduled'] ?? 0), 'label' => 'Entretien', 'hot' => false],
            ['n' => (int) ($c['on_hold'] ?? 0), 'label' => 'En attente', 'hot' => false],
            ['n' => (int) ($c['reviewed'] ?? 0) + (int) ($c['accepted'] ?? 0), 'label' => 'Acceptées', 'hot' => false],
        ];
        $people = [];
        foreach (is_array($in['enlistments'] ?? null) ? $in['enlistments'] : [] as $row) {
            if (!is_array($row) || in_array((string) ($row['status'] ?? ''), ['cancelled', 'rejected', 'blocked'], true)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            $status = (string) ($row['status'] ?? '');
            $created = self::ts((string) ($row['created_at'] ?? ''));
            $updated = self::ts((string) ($row['updated_at'] ?? '')) ?? $created;
            [$tag, $tone] = match ($status) {
                'submitted' => ['Nouvelle', 'warning'],
                'interview_scheduled' => ['Entretien', 'info'],
                'on_hold' => ['En attente', 'muted'],
                'reviewed', 'accepted' => ['Acceptée', 'primary'],
                default => ['En cours', 'muted'],
            };
            $name = self::recruitName($row);
            $people[] = [
                'name' => $name,
                'initials' => self::initials($name),
                'meta' => match ($status) {
                    'submitted' => $created !== null ? 'Candidature reçue le ' . date('d/m', $created) : 'Candidature à instruire',
                    'interview_scheduled' => 'Entretien planifié',
                    default => $updated !== null ? 'Mis à jour ' . self::ago($updated, $now) : 'Dossier en cours',
                },
                'tag' => $tag,
                'tone' => $tone,
                'href' => $id > 0 ? url('back-office/recruitments/' . $id) : (string) $links['recruitments'],
            ];
            if (count($people) >= 3) {
                break;
            }
        }

        return ['tiles' => $tiles, 'people' => $people];
    }

    /**
     * @param list<array<string, mixed>> $upcoming
     * @param array<int|string, mixed> $rsvp
     * @param array<string, mixed>|null $heroEvent
     * @return array{title: string, items: list<array<string, mixed>>}
     */
    private static function agenda(array $upcoming, array $rsvp, ?array $heroEvent, int $now, bool $linkEvents): array
    {
        $weekEnd = $now + 7 * 86400;
        $inWeek = [];
        $later = [];
        foreach ($upcoming as $ev) {
            if (!is_array($ev) || (int) ($ev['id'] ?? 0) < 1) {
                continue;
            }
            $start = self::ts((string) ($ev['starts_at'] ?? ''));
            if ($start === null) {
                continue;
            }
            if ($start <= $weekEnd) {
                $inWeek[] = $ev;
            } else {
                $later[] = $ev;
            }
        }
        $title = 'Cette semaine';
        $list = $inWeek;
        if ($list === []) {
            $title = 'À venir';
            $list = $later;
        }
        $heroId = $heroEvent !== null ? (int) $heroEvent['id'] : 0;
        $items = [];
        foreach (array_slice($list, 0, 4) as $ev) {
            $id = (int) $ev['id'];
            $start = (int) self::ts((string) $ev['starts_at']);
            $sum = is_array($rsvp[$id] ?? null) ? $rsvp[$id] : null;
            $meta = [date('H:i', $start)];
            $loc = trim((string) ($ev['location'] ?? ''));
            if ($loc !== '') {
                $meta[] = $loc;
            }
            if ($sum !== null) {
                $yes = is_array($sum['yes'] ?? null) ? count($sum['yes']) : 0;
                $meta[] = $yes . ' confirmé' . ($yes > 1 ? 's' : '');
            }
            $items[] = [
                'day' => LocalizedCalendar::weekday((int) date('w', $start), true),
                'num' => date('d', $start),
                'title' => trim((string) ($ev['title'] ?? '')) !== '' ? trim((string) $ev['title']) : 'Créneau sans titre',
                'meta' => implode(' · ', $meta),
                'highlight' => $id === $heroId,
                'href' => $linkEvents ? url('back-office/events/' . $id) : null,
            ];
        }

        return ['title' => $title, 'items' => $items];
    }

    /**
     * Fil « Ce qui a bougé » : journal de la communauté, sans les connexions.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function feed(array $rows, int $now): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $action = (string) ($row['action'] ?? '');
            if ($action === '' || str_starts_with($action, 'auth.') || str_contains($action, 'login') || str_contains($action, 'logout')) {
                continue;
            }
            $actor = trim((string) ($row['actor_name'] ?? $row['actor_display_name'] ?? ''));
            if ($actor === '' || $actor === '—') {
                $actor = 'Le système';
            }
            $label = trim((string) ($row['action_label'] ?? ''));
            if ($label === '' && function_exists('audit_action_label_fr')) {
                $label = audit_action_label_fr($action);
            }
            $target = trim((string) ($row['target'] ?? ''));
            $when = self::ts((string) ($row['created_at'] ?? ''));
            $out[] = [
                'actor' => $actor,
                'initials' => self::initials($actor),
                'action' => $label !== '' ? self::lcfirst($label) : 'action enregistrée',
                'target' => $target !== '—' ? $target : '',
                'when' => $when !== null ? self::ucfirst(self::ago($when, $now)) : '',
                'tone' => self::avatarTone($actor),
            ];
            if (count($out) >= 5) {
                break;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $row */
    private static function recruitName(array $row): string
    {
        $name = trim(trim((string) ($row['first_name'] ?? '')) . ' ' . trim((string) ($row['last_name'] ?? '')));
        $callsign = trim((string) ($row['callsign'] ?? ''));
        if ($name === '') {
            return $callsign !== '' ? $callsign : self::personName(null, $row['email'] ?? null, 'Candidat');
        }

        return $name;
    }

    private static function personName(mixed $display, mixed $email, string $fallback): string
    {
        $display = trim((string) $display);
        if ($display !== '') {
            return $display;
        }
        $email = trim((string) $email);

        return $email !== '' ? $email : $fallback;
    }

    private static function initials(string $name): string
    {
        $letters = '';
        foreach (preg_split('/\s+/u', trim($name)) ?: [] as $part) {
            $letters .= mb_strtoupper(mb_substr((string) $part, 0, 1));
            if (mb_strlen($letters) >= 2) {
                break;
            }
        }

        return $letters !== '' ? $letters : '?';
    }

    private static function avatarTone(string $name): string
    {
        $tones = ['a', 'b', 'c', 'd', 'e'];

        return $tones[abs(crc32($name)) % count($tones)];
    }

    public static function ago(int $ts, int $now): string
    {
        $diff = $now - $ts;
        if ($diff < 60) {
            return 'à l’instant';
        }
        if ($diff < 3600) {
            return 'il y a ' . intdiv($diff, 60) . ' min';
        }
        if ($diff < 86400 && date('Y-m-d', $ts) === date('Y-m-d', $now)) {
            return 'il y a ' . intdiv($diff, 3600) . ' h';
        }
        $days = (int) floor((strtotime(date('Y-m-d', $now)) - strtotime(date('Y-m-d', $ts))) / 86400);
        if ($days <= 1) {
            return 'hier';
        }
        if ($days < 7) {
            return 'il y a ' . $days . ' j';
        }

        return 'le ' . date('d/m', $ts);
    }

    private static function ts(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '' || str_starts_with($raw, '0000')) {
            return null;
        }
        $t = strtotime($raw);

        return $t !== false ? $t : null;
    }

    private static function ucfirst(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }

    private static function lcfirst(string $s): string
    {
        // Garde les sigles (ATAK, RH…) intacts.
        $word = (string) (preg_split('/\s+/u', $s)[0] ?? '');
        if (mb_strlen($word) > 1 && $word === mb_strtoupper($word)) {
            return $s;
        }

        return mb_strtolower(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }
}
