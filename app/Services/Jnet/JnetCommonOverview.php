<?php

declare(strict_types=1);

namespace App\Services\Jnet;

use App\Support\JnetSpaceTree;

/**
 * Calculs purs de la « Vue d'ensemble » de l'espace commun et de son bandeau (testés sans base) : accusés attendus du
 * lecteur, pouls des unités, liste « À traiter », prochaine opération et fil d'activité. Tout est tiré de lectures déjà
 * faites (échanges visibles, lecteurs, lignes du tableau des unités, événements) : aucune donnée n'est inventée et rien
 * n'est lu ici.
 */
final class JnetCommonOverview
{
    /** Nombre de tuiles du pouls des unités (le tableau des unités les montre toutes). */
    public const PULSE_LIMIT = 48;

    /** Nombre d'éléments du fil d'activité. */
    public const FEED_LIMIT = 7;

    /** Nombre d'actions de la liste « À traiter ». */
    public const TODO_LIMIT = 6;

    /**
     * Ordres et consignes à accuser dont le lecteur est destinataire et qu'il n'a pas encore lus (ceux qu'il a publiés
     * lui-même ne comptent pas). Plus récents d'abord, comme les échanges fournis.
     *
     * @param list<array<string, mixed>> $exchanges échanges visibles du lecteur
     * @param array<int, list<int>> $readers lecteurs par échange (accusés de lecture)
     * @return list<array<string, mixed>>
     */
    public static function pendingForViewer(JnetSpaceTree $tree, array $exchanges, array $readers, int $userId): array
    {
        $out = [];
        $members = [];
        foreach ($exchanges as $e) {
            if (empty($e['requires_ack']) || (int) ($e['author_user_id'] ?? 0) === $userId) {
                continue;
            }
            if (in_array($userId, $readers[(int) $e['id']] ?? [], true)) {
                continue;
            }
            $addressed = false;
            foreach (array_map('intval', (array) ($e['targets'] ?? [])) as $target) {
                $members[$target] ??= array_flip($tree->memberIdsInSubtree($target));
                if (isset($members[$target][$userId])) {
                    $addressed = true;
                    break;
                }
            }
            if ($addressed) {
                $out[] = $e;
            }
        }

        return $out;
    }

    /**
     * Unités actives sur la période : celles dont le dernier échange a moins de $days jours.
     *
     * @param list<array<string, mixed>> $rows lignes du tableau des unités
     */
    public static function activeUnits(array $rows, int $now, int $days = JnetUnitBoard::RECENT_DAYS): int
    {
        $from = $now - $days * 86400;

        return count(array_filter($rows, static fn (array $r): bool => (int) ($r['lastTs'] ?? 0) >= $from && (int) ($r['lastTs'] ?? 0) > 0));
    }

    /**
     * Pouls des unités : celles qui demandent de l'attention d'abord (retards, puis accusés attendus), puis les plus
     * récemment actives. Même gabarit que le tableau, limité aux $limit premières.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function pulse(array $rows, int $limit = self::PULSE_LIMIT): array
    {
        $rank = ['late' => 0, 'pending' => 1, 'active' => 2, 'calm' => 3];
        $indexed = [];
        foreach ($rows as $i => $r) {
            $indexed[] = [$rank[(string) $r['state']] ?? 3, -(int) $r['lastTs'], $i, $r];
        }
        usort($indexed, static fn (array $a, array $b): int => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);

        return array_slice(array_map(static fn (array $x): array => $x[3], $indexed), 0, max(0, $limit));
    }

    /**
     * L'« ordre du jour » : le dernier ordre à accuser par le lecteur ; à défaut, le dernier ordre de l'organisation.
     *
     * @param list<array<string, mixed>> $items échanges de l'organisation décorés, plus récents d'abord
     * @return array<string, mixed>|null
     */
    public static function orderOfTheDay(array $items): ?array
    {
        $first = null;
        foreach ($items as $item) {
            if ((string) ($item['kind'] ?? '') !== 'ordre') {
                continue;
            }
            if (!empty($item['requiresAck']) && empty($item['readByMe'])) {
                return $item;
            }
            $first ??= $item;
        }

        return $first;
    }

    /**
     * Prochaine opération (type « operation »), ou à défaut le prochain événement, à partir des événements à venir.
     *
     * @param list<array<string, mixed>> $events événements normalisés (JnetAgenda::normalize), du plus proche au plus lointain
     * @return array<string, mixed>|null
     */
    public static function nextOperation(array $events, int $now): ?array
    {
        $upcoming = array_values(array_filter($events, static fn (array $e): bool => (int) $e['endTs'] >= $now));
        foreach ($upcoming as $e) {
            if ((string) $e['typeKey'] === 'operation') {
                return $e + ['days' => JnetAgenda::daysUntil((int) $e['startTs'], $now)];
            }
        }

        return null;
    }

    /**
     * « À traiter » : accusés attendus, retards des unités du lecteur, événements sans réponse. Chaque action est
     * [ton, icône, titre, détail, adresse, salon d'arrivée, unité à ouvrir dans le tiroir (0 = aucune)].
     *
     * @param list<array<string, mixed>> $pending accusés attendus (pendingForViewer)
     * @param list<array<string, mixed>> $lateRows lignes du tableau des unités du lecteur ayant des tâches en retard
     * @param list<array<string, mixed>> $events événements normalisés
     * @param \Closure(string): string $href adresse d'un salon (clé) avec ses paramètres déjà ajoutés par l'appelant
     * @return list<array{tone: string, icon: string, title: string, sub: string, href: string, salon: string, unit: int}>
     */
    public static function todo(array $pending, array $lateRows, array $events, int $now, \Closure $href): array
    {
        $out = [];
        foreach (array_slice($pending, 0, 3) as $e) {
            $out[] = [
                'tone' => 'bad',
                'icon' => 'alert',
                'title' => JnetCommonRules::fmt('Accuser réception : %1', (string) $e['title']),
                'sub' => JnetSpaceService::relativeTime((string) $e['created_at']),
                'href' => $href('annonces') . '#ex-' . (int) $e['id'],
                'salon' => 'annonces',
                'unit' => 0,
            ];
        }
        foreach (array_slice($lateRows, 0, 2) as $r) {
            $late = (int) $r['tasks']['late'];
            $out[] = [
                'tone' => 'warn',
                'icon' => 'clock',
                'title' => JnetCommonRules::fmt($late > 1 ? '%1 tâches en retard' : '%1 tâche en retard', $late),
                'sub' => (string) $r['label'],
                'href' => $href('taches') . '&unite=' . (int) $r['id'] . '#jc-unit-card',
                'salon' => 'taches',
                'unit' => (int) $r['id'],
            ];
        }
        $asked = 0;
        foreach ($events as $e) {
            if ((int) $e['endTs'] < $now || (string) $e['rsvp'] !== '' || $asked >= 2) {
                continue;
            }
            $asked++;
            $out[] = [
                'tone' => 'info',
                'icon' => 'agenda',
                'title' => JnetCommonRules::fmt('Répondre : %1', (string) $e['title']),
                'sub' => ((string) $e['dayLabel'] !== '' ? (string) $e['dayLabel'] : (string) $e['dateFr']) . ' · ' . (string) $e['time'],
                'href' => $href('agenda') . '#ev-' . (int) $e['id'],
                'salon' => 'agenda',
                'unit' => 0,
            ];
        }

        return array_slice($out, 0, self::TODO_LIMIT);
    }

    /**
     * Fil d'activité : les derniers échanges visibles (diffusions), avec l'unité émettrice. Rien d'autre n'est tracé
     * par le site : les accusés ne sont pas datés.
     *
     * @param list<array<string, mixed>> $exchanges échanges visibles, plus récents d'abord
     * @return list<array{id: int, unit: int, label: string, accent: string, badge: string, author: string, title: string, when: string, orgLevel: bool}>
     */
    public static function feed(array $exchanges, JnetSpaceTree $tree, int $limit = self::FEED_LIMIT): array
    {
        $out = [];
        foreach ($exchanges as $e) {
            $unit = (int) ($e['from_unit_id'] ?? 0);
            $n = (array) $tree->node($unit);
            $author = trim((string) ($e['author_callsign'] ?? ''));
            if ($author === '') {
                $author = trim((string) ($e['author_display_name'] ?? ''));
            }
            $targets = array_map('intval', (array) ($e['targets'] ?? []));
            $out[] = [
                'id' => (int) $e['id'],
                'unit' => $unit,
                'label' => (string) ($n['label'] ?? ''),
                'accent' => (string) ($n['accent'] ?? ''),
                'badge' => is_string($n['badge'] ?? null) ? (string) $n['badge'] : '',
                'author' => $author !== '' ? $author : 'Membre',
                'title' => (string) ($e['title'] ?? ''),
                'when' => JnetSpaceService::relativeTime((string) ($e['created_at'] ?? '')),
                'orgLevel' => $unit === JnetSpaceTree::ORG || in_array(JnetSpaceTree::ORG, $targets, true),
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }
}
