<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Dossier;

/**
 * Vue équipe du suivi : une ligne par membre avec sa progression, sa prochaine étape et ses retards, filtrable et triable.
 * Lecture seule : les étapes viennent de MemberDossierTimeline::forUsers (mêmes adaptateurs que la fiche).
 */
final class MemberDossierTeamBoard
{
    public const SORTS = ['nom', 'progression', 'retard', 'echeance'];
    public const PER_PAGE = 50;

    public function __construct(private MemberDossierTimeline $timeline, private DossierStore $store)
    {
    }

    /**
     * @param list<array{id: int, name: string, unit_id?: int, unit_name?: string, grade?: string}> $members
     * @return list<array<string, mixed>>
     */
    public function rows(int $tenantId, array $members, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $ids = array_map(static fn (array $m): int => (int) $m['id'], $members);
        $stepsByUser = $this->timeline->forUsers($tenantId, $ids, $today);
        $reminded = $this->store->lastRemindByUser($tenantId, $ids);
        $rows = [];
        foreach ($members as $m) {
            $id = (int) $m['id'];
            $steps = $stepsByUser[$id] ?? [];
            $summary = MemberDossierTimeline::summarize($steps);
            $open = array_values(array_filter($steps, static fn (array $s): bool => DossierCatalog::isOpenState((string) $s['state'])));
            $rows[] = [
                'id' => $id,
                'name' => (string) $m['name'],
                'unit_id' => (int) ($m['unit_id'] ?? 0),
                'unit_name' => (string) ($m['unit_name'] ?? ''),
                'grade' => (string) ($m['grade'] ?? ''),
                'summary' => $summary,
                'next' => $summary['next'],
                'tracks' => array_values(array_unique(array_map(static fn (array $s): string => (string) $s['track'], $open))),
                'owner_ids' => array_values(array_unique(array_filter(array_map(static fn (array $s): int => (int) $s['owner_user_id'], $open)))),
                'owner_kinds' => array_values(array_unique(array_map(static fn (array $s): string => (string) $s['owner_kind'], $open))),
                'remindable' => count(array_filter($open, static fn (array $s): bool => in_array(DossierCatalog::ACTION_REMIND, $s['actions'], true))),
                'last_remind' => $reminded[$id] ?? null,
            ];
        }
        // Nom du responsable de la prochaine étape, résolu en une seule lecture pour toute la liste.
        $index = [];
        $nexts = [];
        foreach ($rows as $i => $r) {
            if ($r['next'] !== null) {
                $index[] = $i;
                $nexts[] = $r['next'];
            }
        }
        foreach ($this->timeline->withOwnerLabels($tenantId, $nexts) as $n => $labelled) {
            $rows[$index[$n]]['next'] = $labelled;
            $rows[$index[$n]]['summary']['next'] = $labelled;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array{track?: string, owner?: string, etat?: string, unit?: int, q?: string} $f
     * @return list<array<string, mixed>>
     */
    public static function filter(array $rows, array $f): array
    {
        $track = (string) ($f['track'] ?? '');
        $owner = (string) ($f['owner'] ?? '');
        $etat = (string) ($f['etat'] ?? '');
        $unit = (int) ($f['unit'] ?? 0);
        $q = mb_strtolower(trim((string) ($f['q'] ?? '')));

        return array_values(array_filter($rows, static function (array $r) use ($track, $owner, $etat, $unit, $q): bool {
            if ($track !== '' && !in_array($track, $r['tracks'], true)) {
                return false;
            }
            if ($owner !== '' && !(ctype_digit($owner) ? in_array((int) $owner, $r['owner_ids'], true) : in_array($owner, $r['owner_kinds'], true))) {
                return false;
            }
            if ($unit > 0 && $r['unit_id'] !== $unit) {
                return false;
            }
            if ($etat === 'ouvert' && $r['summary']['open'] < 1) {
                return false;
            }
            if ($etat === 'retard' && $r['summary']['late'] < 1) {
                return false;
            }
            if ($etat === 'bloque' && $r['summary']['blocked'] < 1) {
                return false;
            }

            return $q === '' || str_contains(mb_strtolower($r['name'] . ' ' . $r['unit_name']), $q);
        }));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function sort(array $rows, string $sort, bool $desc = false): array
    {
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'retard';
        $key = static function (array $r) use ($sort): array {
            $due = $r['next']['due_on'] ?? null;

            return match ($sort) {
                'nom' => [mb_strtolower($r['name'])],
                'progression' => [$r['summary']['total'] === 0 ? 101 : $r['summary']['percent'], mb_strtolower($r['name'])],
                'echeance' => [$due === null ? '9999-99-99' : $due, mb_strtolower($r['name'])],
                default => [-$r['summary']['late'], -$r['summary']['blocked'], $r['summary']['open'] === 0 ? 1 : 0, mb_strtolower($r['name'])],
            };
        };
        usort($rows, static fn (array $a, array $b): int => $desc ? $key($b) <=> $key($a) : $key($a) <=> $key($b));

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{members: int, late: int, blocked: int, open: int, complete: int}
     */
    public static function totals(array $rows): array
    {
        $t = ['members' => count($rows), 'late' => 0, 'blocked' => 0, 'open' => 0, 'complete' => 0];
        foreach ($rows as $r) {
            $t['late'] += $r['summary']['late'] > 0 ? 1 : 0;
            $t['blocked'] += $r['summary']['blocked'] > 0 ? 1 : 0;
            $t['open'] += $r['summary']['open'] > 0 ? 1 : 0;
            $t['complete'] += $r['summary']['total'] > 0 && $r['summary']['open'] === 0 ? 1 : 0;
        }

        return $t;
    }

    /**
     * Export CSV de la vue équipe (mêmes protections que le fil d'un membre).
     *
     * @param list<array<string, mixed>> $rows
     */
    public static function csv(array $rows): string
    {
        $cell = static function (mixed $v): string {
            $t = str_replace(["\r", "\n"], ' ', trim((string) $v));
            if ($t !== '' && in_array($t[0], ['=', '+', '-', '@', "\t"], true)) {
                $t = "'" . $t;
            }

            return '"' . str_replace('"', '""', $t) . '"';
        };
        $lines = [implode(';', array_map($cell, ['Membre', 'Unité', 'Étapes terminées', 'Étapes', 'Progression (%)', 'En retard', 'Prochaine étape', 'Responsable', 'Échéance']))];
        foreach ($rows as $r) {
            $n = $r['next'];
            $lines[] = implode(';', array_map($cell, [
                $r['name'], $r['unit_name'], $r['summary']['done'], $r['summary']['total'], $r['summary']['percent'], $r['summary']['late'],
                $n['title'] ?? '', $n['owner_label'] ?? '', $n['due_on'] ?? '',
            ]));
        }

        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }
}
