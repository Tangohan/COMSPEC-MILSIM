<?php

declare(strict_types=1);

namespace App\Support;

/**
 * File des candidatures : vue (tableau en colonnes ou liste), filtres rapides et recherche.
 * Calcul pur à partir des lignes déjà enrichies (EnlistmentStaffRowEnricher), pour rester testable sans base.
 *
 * Paramètres d’URL reconnus :
 *   vue    = tableau | liste (défaut : tableau, sauf si un filtre de statut ou d’étape est demandé)
 *   filtre = mine (mes dossiers) | libre (sans recruteur) | retard (action en retard)
 *   q      = recherche sur le nom, l’indicatif, l’e-mail ou le n° de dossier
 */
final class EnlistmentPipelineBoard
{
    public const FILTERS = [
        'mine' => 'Mes dossiers',
        'libre' => 'Sans recruteur',
        'retard' => 'En retard',
    ];

    /**
     * @param array<string, mixed> $query paramètres d’URL
     */
    public static function view(array $query): string
    {
        $v = strtolower(trim((string) ($query['vue'] ?? '')));
        if ($v === 'liste' || $v === 'tableau') {
            return $v;
        }
        $hasStatus = trim((string) ($query['status'] ?? '')) !== '' || trim((string) ($query['stage'] ?? '')) !== '';

        return $hasStatus ? 'liste' : 'tableau';
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function filter(array $query): string
    {
        $f = strtolower(trim((string) ($query['filtre'] ?? '')));

        return isset(self::FILTERS[$f]) ? $f : '';
    }

    public static function search(array $query): string
    {
        return mb_substr(trim((string) ($query['q'] ?? '')), 0, 80);
    }

    /**
     * @param array<string, mixed> $row ligne portant `next_action`
     */
    public static function matches(array $row, string $filter, string $search, int $viewerUserId): bool
    {
        if ($filter === 'mine' && ($viewerUserId < 1 || (int) ($row['reviewed_by'] ?? 0) !== $viewerUserId)) {
            return false;
        }
        if ($filter === 'libre' && (($row['next_action']['column'] ?? '') !== 'take')) {
            return false;
        }
        if ($filter === 'retard' && (($row['next_action']['urgency'] ?? '') !== 'late')) {
            return false;
        }
        if ($search === '') {
            return true;
        }
        $needle = mb_strtolower(ltrim($search, '#'));
        if (ctype_digit($needle) && (int) $needle === (int) ($row['id'] ?? 0)) {
            return true;
        }
        $hay = mb_strtolower(implode(' ', [
            (string) ($row['first_name'] ?? ''),
            (string) ($row['last_name'] ?? ''),
            (string) ($row['callsign'] ?? ''),
            (string) ($row['discord_username'] ?? ''),
            (string) ($row['email'] ?? ''),
        ]));

        return str_contains($hay, $needle);
    }

    /**
     * Compteurs des filtres rapides, calculés sur l’ensemble des dossiers (avant filtrage).
     *
     * @param list<array<string, mixed>> $rows
     * @return array{mine: int, libre: int, retard: int, active: int, interviews: int, retro: int}
     */
    public static function counts(array $rows, int $viewerUserId): array
    {
        $c = ['mine' => 0, 'libre' => 0, 'retard' => 0, 'active' => 0, 'interviews' => 0, 'retro' => 0];
        foreach ($rows as $row) {
            $a = $row['next_action'] ?? null;
            if (!is_array($a) || $a['column'] === null) {
                continue;
            }
            $c['active']++;
            if ($viewerUserId > 0 && (int) ($row['reviewed_by'] ?? 0) === $viewerUserId) {
                $c['mine']++;
            }
            if ($a['column'] === 'take') {
                $c['libre']++;
            }
            if ($a['urgency'] === 'late') {
                $c['retard']++;
            }
            if ($a['column'] === 'interview') {
                $c['interviews']++;
            }
            if ($a['column'] === 'retro') {
                $c['retro']++;
            }
        }

        return $c;
    }

    /**
     * Lien de la file qui conserve la vue, le filtre et la recherche, en remplaçant les clés données.
     *
     * @param array<string, string> $state état courant (vue, filtre, q, status, stage)
     * @param array<string, string|null> $override null retire la clé
     */
    public static function link(string $base, array $state, array $override = []): string
    {
        $params = array_merge($state, $override);
        $params = array_filter($params, static fn ($v): bool => $v !== null && $v !== '');
        if (($params['vue'] ?? '') === 'tableau') {
            // Le tableau est la vue par défaut : URL plus courte, sauf si un statut forcerait la liste.
            if (!isset($params['status']) && !isset($params['stage'])) {
                unset($params['vue']);
            }
        }

        return $params === [] ? $base : $base . '?' . http_build_query($params);
    }

    /**
     * Variables de vue de la file. `$rows` : dossiers enrichis (tous statuts pour le tableau).
     *
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public static function viewVars(array $rows, array $query, int $viewerUserId, int $slaHours, ?int $now = null): array
    {
        $rows = EnlistmentNextAction::annotate($rows, $slaHours, $viewerUserId, $now);
        $view = self::view($query);
        $filter = self::filter($query);
        $search = self::search($query);
        $counts = self::counts($rows, $viewerUserId);
        $visible = array_values(array_filter(
            $rows,
            static fn (array $r): bool => self::matches($r, $filter, $search, $viewerUserId)
        ));
        $closedOrDone = count(array_filter($rows, static fn (array $r): bool => ($r['next_action']['column'] ?? null) === null));

        return [
            'enlistments' => $visible,
            'pipelineView' => $view,
            'pipelineFilter' => $filter,
            'pipelineSearch' => $search,
            'pipelineCounts' => $counts,
            'pipelineColumns' => $view === 'tableau' ? EnlistmentNextAction::columns($visible) : [],
            'pipelineOffBoard' => $closedOrDone,
            'viewerUserId' => $viewerUserId,
        ];
    }
}
