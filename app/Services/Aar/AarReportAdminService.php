<?php

declare(strict_types=1);

namespace App\Services\Aar;

/**
 * Logique pure du back-office « Comptes rendus » (sans base de données) :
 * cycle de relecture, filtres, indicateurs, suivi consolidé des actions,
 * missions clôturées sans compte rendu, saisie des actions, export CSV.
 */
final class AarReportAdminService
{
    public const STATUSES = [
        'pending' => 'En attente',
        'in_review' => 'En relecture',
        'validated' => 'Validé',
        'missing' => 'Manquant',
    ];

    /** Transitions autorisées depuis la fiche (statut courant → statuts possibles). */
    public const TRANSITIONS = [
        'pending' => ['in_review', 'validated', 'missing'],
        'in_review' => ['validated', 'pending'],
        'validated' => ['in_review'],
        'missing' => ['pending'],
    ];

    /** Libellé du bouton qui mène à chaque statut. */
    public const TRANSITION_LABELS = [
        'in_review' => 'Passer en relecture',
        'validated' => 'Valider',
        'pending' => 'Renvoyer à l’auteur',
        'missing' => 'Marquer manquant',
    ];

    /** Délai cible entre la fin de mission et le dépôt (heures). */
    public const TARGET_DELAY_HOURS = 72;

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? self::STATUSES['pending'];
    }

    public static function tone(string $status): string
    {
        return match ($status) {
            'validated' => 'ok',
            'in_review' => 'info',
            'missing' => 'bad',
            default => 'warn',
        };
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? self::TRANSITIONS['pending'], true);
    }

    /**
     * @return list<array{status:string,label:string}>
     */
    public static function transitionsFrom(string $status): array
    {
        $out = [];
        foreach (self::TRANSITIONS[$status] ?? self::TRANSITIONS['pending'] as $to) {
            $out[] = ['status' => $to, 'label' => self::TRANSITION_LABELS[$to]];
        }

        return $out;
    }

    /**
     * Filtres de la liste : q (texte), status, author, period (jours), open_actions.
     *
     * @param list<array<string, mixed>> $reports
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public static function filter(array $reports, array $filters, ?int $now = null): array
    {
        $now ??= time();
        $q = self::lower(trim((string) ($filters['q'] ?? '')));
        $status = trim((string) ($filters['status'] ?? ''));
        $author = trim((string) ($filters['author'] ?? ''));
        $period = (int) ($filters['period'] ?? 0);
        $openOnly = !empty($filters['open_actions']);

        return array_values(array_filter($reports, static function (array $r) use ($q, $status, $author, $period, $openOnly, $now): bool {
            if ($status !== '' && (string) ($r['status'] ?? 'pending') !== $status) {
                return false;
            }
            if ($author !== '' && trim((string) ($r['author_name'] ?? '')) !== $author) {
                return false;
            }
            if ($openOnly && (int) ($r['totals']['open_actions'] ?? 0) < 1) {
                return false;
            }
            if ($period > 0) {
                $ts = self::ts($r['reported_at'] ?? null);
                if ($ts === null || $ts < $now - $period * 86400) {
                    return false;
                }
            }
            if ($q !== '') {
                $hay = self::lower(implode(' ', [
                    $r['reference_code'] ?? '', $r['title'] ?? '', $r['operation_label'] ?? '',
                    $r['mission_title'] ?? '', $r['author_name'] ?? '', $r['validator_name'] ?? '',
                ]));
                if (!str_contains($hay, $q)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param list<array<string, mixed>> $reports
     * @return array<string, int>
     */
    public static function statusCounts(array $reports): array
    {
        $out = array_fill_keys(array_keys(self::STATUSES), 0);
        foreach ($reports as $r) {
            $s = (string) ($r['status'] ?? 'pending');
            $out[isset($out[$s]) ? $s : 'pending']++;
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $reports
     * @return list<string>
     */
    public static function authors(array $reports): array
    {
        $names = [];
        foreach ($reports as $r) {
            $n = trim((string) ($r['author_name'] ?? ''));
            if ($n !== '') {
                $names[$n] = true;
            }
        }
        $list = array_map('strval', array_keys($names));
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }

    /**
     * Indicateurs de tête de page.
     *
     * @param list<array<string, mixed>> $reports
     * @param list<array<string, mixed>> $missingMissions
     * @return array<string, mixed>
     */
    public static function kpis(array $reports, array $missingMissions = [], ?int $now = null): array
    {
        $now ??= time();
        $counts = self::statusCounts($reports);
        $total = count($reports);
        $recent = 0;
        $delays = [];
        $late = 0;
        foreach ($reports as $r) {
            $rep = self::ts($r['reported_at'] ?? null);
            if ($rep !== null && $rep >= $now - 365 * 86400) {
                $recent++;
            }
            $end = self::ts($r['mission_ended_at'] ?? null);
            if ($rep !== null && $end !== null && $rep >= $end) {
                $hours = ($rep - $end) / 3600;
                $delays[] = $hours;
                if ($hours > self::TARGET_DELAY_HOURS) {
                    $late++;
                }
            }
        }
        $actions = self::actions($reports, $now);
        $open = array_values(array_filter($actions, static fn (array $a): bool => $a['state'] === 'open'));
        $overdue = count(array_filter($open, static fn (array $a): bool => $a['overdue']));
        $avg = $delays !== [] ? array_sum($delays) / count($delays) : null;

        return [
            'total' => $total,
            'recent' => $recent,
            'to_review' => $counts['pending'] + $counts['in_review'],
            'pending' => $counts['pending'],
            'in_review' => $counts['in_review'],
            'validated' => $counts['validated'],
            'missing' => $counts['missing'] + count($missingMissions),
            'validated_pct' => $total > 0 ? (int) round(100 * $counts['validated'] / $total) : null,
            'avg_delay_hours' => $avg,
            'late' => $late,
            'measured' => count($delays),
            'actions_total' => count($actions),
            'actions_open' => count($open),
            'actions_overdue' => $overdue,
        ];
    }

    /**
     * Toutes les actions décidées, tous comptes rendus confondus.
     *
     * @param list<array<string, mixed>> $reports
     * @return list<array<string, mixed>>
     */
    public static function actions(array $reports, ?int $now = null): array
    {
        $now ??= time();
        $today = strtotime(date('Y-m-d', $now)) ?: $now;
        $out = [];
        foreach ($reports as $r) {
            foreach (['open' => 'open_actions', 'closed' => 'closed_actions'] as $state => $key) {
                foreach (is_array($r[$key] ?? null) ? $r[$key] : [] as $i => $a) {
                    if (!is_array($a) || trim((string) ($a['label'] ?? '')) === '') {
                        continue;
                    }
                    $due = self::ts($a['due_at'] ?? null);
                    $out[] = [
                        'report_id' => (int) ($r['id'] ?? 0),
                        'reference' => (string) ($r['reference_code'] ?? ''),
                        'operation' => trim((string) ($r['operation_label'] ?? '')) !== '' ? (string) $r['operation_label'] : (string) ($r['title'] ?? ''),
                        'report_status' => (string) ($r['status'] ?? 'pending'),
                        'state' => $state,
                        'index' => (int) $i,
                        'label' => trim((string) $a['label']),
                        'owner' => trim((string) ($a['owner'] ?? '')),
                        'due_at' => $due !== null ? date('Y-m-d', $due) : null,
                        'closed_at' => $a['closed_at'] ?? null,
                        'overdue' => $state === 'open' && $due !== null && $due < $today,
                    ];
                }
            }
        }
        // Ouvertes d'abord, en retard en tête, puis échéance la plus proche.
        usort($out, static function (array $a, array $b): int {
            return [$a['state'] === 'open' ? 0 : 1, $a['overdue'] ? 0 : 1, $a['due_at'] ?? '9999', $a['report_id']]
                <=> [$b['state'] === 'open' ? 0 : 1, $b['overdue'] ? 0 : 1, $b['due_at'] ?? '9999', $b['report_id']];
        });

        return $out;
    }

    /**
     * Missions clôturées pour lesquelles aucun compte rendu n'a été déposé.
     *
     * @param list<array<string, mixed>> $missions missions présentées (TheatreMissionCycleRepository::present)
     * @param list<array<string, mixed>> $reports
     * @return list<array<string, mixed>>
     */
    public static function missionsWithoutReport(array $missions, array $reports, ?int $now = null): array
    {
        $now ??= time();
        $byId = [];
        $byLabel = [];
        foreach ($reports as $r) {
            if ((int) ($r['mission_cycle_id'] ?? 0) > 0) {
                $byId[(int) $r['mission_cycle_id']] = true;
            }
            foreach (['operation_label', 'title', 'mission_title'] as $k) {
                $key = self::key((string) ($r[$k] ?? ''));
                if ($key !== '') {
                    $byLabel[$key] = true;
                }
            }
        }
        $out = [];
        foreach ($missions as $m) {
            if ((string) ($m['status'] ?? '') !== 'cloturee' && (string) ($m['status_label'] ?? '') !== 'Clôturée') {
                continue;
            }
            $id = (int) ($m['id'] ?? 0);
            if (isset($byId[$id]) || isset($byLabel[self::key((string) ($m['title'] ?? ''))])) {
                continue;
            }
            $end = self::ts($m['ended_at'] ?? null);
            $m['hours_since_end'] = $end !== null ? max(0, (int) floor(($now - $end) / 3600)) : null;
            $m['late'] = $m['hours_since_end'] !== null && $m['hours_since_end'] > self::TARGET_DELAY_HOURS;
            $out[] = $m;
        }

        return $out;
    }

    /**
     * Ligne libre « Libellé — responsable — échéance » (séparateurs « — », « – », « | » ou « ; »).
     *
     * @return array{label:string,owner:?string,due_at:?string}
     */
    public static function parseActionLine(string $line): array
    {
        $parts = array_values(array_filter(
            array_map('trim', preg_split('/\s+[—–|]\s+|\s*;\s*/u', trim($line)) ?: []),
            static fn (string $p): bool => $p !== ''
        ));
        $due = null;
        if (count($parts) > 1) {
            $last = $parts[count($parts) - 1];
            $date = self::parseDate($last);
            if ($date !== null) {
                $due = $date;
                array_pop($parts);
            }
        }
        $label = (string) array_shift($parts);
        $owner = $parts !== [] ? implode(' — ', $parts) : null;

        return ['label' => $label, 'owner' => $owner, 'due_at' => $due];
    }

    /**
     * Lignes du tableau d'actions du formulaire → listes ouvertes / closes.
     *
     * @param mixed $rows actions[i][label|owner|due_at|status|closed_at]
     * @return array{open: list<array<string, mixed>>, closed: list<array<string, mixed>>}
     */
    public static function actionsFromRows(mixed $rows): array
    {
        $open = [];
        $closed = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $isClosed = (string) ($row['status'] ?? 'open') === 'closed';
            $item = [
                'label' => $label,
                'status' => $isClosed ? 'closed' : 'open',
                'owner' => trim((string) ($row['owner'] ?? '')) ?: null,
                'due_at' => self::parseDate((string) ($row['due_at'] ?? '')),
            ];
            if ($isClosed) {
                $item['closed_at'] = self::parseDate((string) ($row['closed_at'] ?? '')) ?? date('Y-m-d');
                $closed[] = $item;
            } else {
                $open[] = $item;
            }
        }

        return ['open' => $open, 'closed' => $closed];
    }

    /**
     * Bascule une action entre « en cours » et « close ».
     *
     * @param list<array<string, mixed>> $open
     * @param list<array<string, mixed>> $closed
     * @return array{open: list<array<string, mixed>>, closed: list<array<string, mixed>>}|null
     */
    public static function toggleAction(array $open, array $closed, string $state, int $index, ?string $today = null): ?array
    {
        $today ??= date('Y-m-d');
        $open = array_values($open);
        $closed = array_values($closed);
        if ($state === 'open') {
            if (!isset($open[$index]) || !is_array($open[$index])) {
                return null;
            }
            $item = $open[$index];
            array_splice($open, $index, 1);
            $item['status'] = 'closed';
            $item['closed_at'] = $today;
            $closed[] = $item;
        } elseif ($state === 'closed') {
            if (!isset($closed[$index]) || !is_array($closed[$index])) {
                return null;
            }
            $item = $closed[$index];
            array_splice($closed, $index, 1);
            $item['status'] = 'open';
            unset($item['closed_at']);
            $open[] = $item;
        } else {
            return null;
        }

        return ['open' => $open, 'closed' => $closed];
    }

    /**
     * Export tableur (CSV ; séparateur point-virgule, BOM UTF-8 pour Excel).
     *
     * @param list<array<string, mixed>> $reports
     */
    public static function csv(array $reports): string
    {
        $rows = [[
            'Référence', 'Titre', 'Opération', 'Statut', 'Auteur', 'Déposé le', 'Fin de mission',
            'Délai (h)', 'Relecteur', 'Validé le', 'Points forts', 'Points faibles',
            'Actions ouvertes', 'Actions closes', 'Questionnaire',
        ]];
        foreach ($reports as $r) {
            $rep = self::ts($r['reported_at'] ?? null);
            $end = self::ts($r['mission_ended_at'] ?? null);
            $rows[] = [
                (string) ($r['reference_code'] ?? ''),
                (string) ($r['title'] ?? ''),
                (string) ($r['operation_label'] ?? ''),
                self::statusLabel((string) ($r['status'] ?? 'pending')),
                (string) ($r['author_name'] ?? ''),
                $rep !== null ? date('d/m/Y H:i', $rep) : '',
                $end !== null ? date('d/m/Y H:i', $end) : '',
                $rep !== null && $end !== null && $rep >= $end ? (string) (int) round(($rep - $end) / 3600) : '',
                (string) ($r['validator_name'] ?? ''),
                ($v = self::ts($r['validated_at'] ?? null)) !== null ? date('d/m/Y', $v) : '',
                (string) count(is_array($r['strengths'] ?? null) ? $r['strengths'] : []),
                (string) count(is_array($r['weaknesses'] ?? null) ? $r['weaknesses'] : []),
                (string) count(is_array($r['open_actions'] ?? null) ? $r['open_actions'] : []),
                (string) count(is_array($r['closed_actions'] ?? null) ? $r['closed_actions'] : []),
                !empty($r['is_custom']) ? 'Oui' : 'Non',
            ];
        }
        $out = "\xEF\xBB\xBF";
        foreach ($rows as $row) {
            $out .= implode(';', array_map(static function (string $cell): string {
                // Neutralise les formules à l'ouverture dans un tableur.
                if ($cell !== '' && str_contains('=+-@', $cell[0]) && !is_numeric($cell)) {
                    $cell = "'" . $cell;
                }

                return '"' . str_replace('"', '""', $cell) . '"';
            }, $row)) . "\r\n";
        }

        return $out;
    }

    public static function formatDelay(?float $hours): string
    {
        if ($hours === null) {
            return '—';
        }
        if ($hours < 48) {
            return (int) round($hours) . ' h';
        }

        return str_replace('.', ',', (string) round($hours / 24, 1)) . ' j';
    }

    private static function parseDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $raw, $m) === 1) {
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : null;
        }
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})#', $raw, $m) === 1) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]) : null;
        }

        return null;
    }

    private static function ts(mixed $raw): ?int
    {
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $t = strtotime($raw);

        return $t === false ? null : $t;
    }

    private static function lower(string $v): string
    {
        return mb_strtolower($v, 'UTF-8');
    }

    private static function key(string $v): string
    {
        return preg_replace('/\s+/u', ' ', self::lower(trim($v))) ?? '';
    }
}
