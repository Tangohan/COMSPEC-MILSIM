<?php

declare(strict_types=1);

namespace App\Services\Jnet;

use App\Support\JnetSpaceTree;

/**
 * Règles pures de l'espace commun JNET (« centre de commandement » à la Discord), testées sans base : les salons, leur
 * adresse, le groupement des fils par jour et la voie hiérarchique lisible (arbre indenté + cartes de contact).
 *
 * Aucun droit n'est décidé ici : les salons réservés au renseignement sont seulement marqués (« sse ») ; la porte
 * (JnetDashboardService::mayReadIntelligence) est appliquée par le service et la vue. L'arbre fourni est déjà celui du
 * lecteur (confidentialité appliquée).
 */
final class JnetCommonRules
{
    public const DEFAULT_SALON = 'apercu';

    /** Rubriques de la barre des salons, dans l'ordre (un filet sépare les salons du renseignement des autres). */
    public const SECTIONS = [
        'commandement' => 'Commandement',
        'reperes' => 'Repères',
        'renseignement' => 'Renseignement',
    ];

    /**
     * Les salons, dans l'ordre de la barre : clé => titre court affiché sous le bandeau, nom du salon, description, rubrique, porte du renseignement, icône.
     *
     * @var array<string, array{tag: string, label: string, desc: string, section: string, sse: bool, icon: string}>
     */
    public const SALONS = [
        'apercu' => [
            'tag' => '',
            'label' => 'Vue d’ensemble',
            'desc' => 'Ce qui demande une action, le pouls des unités et les prochaines échéances, en un coup d’œil.',
            'section' => 'commandement',
            'sse' => false,
            'icon' => 'overview',
        ],
        'annonces' => [
            'tag' => 'Ce qui descend.',
            'label' => 'Annonces & ordres du jour',
            'desc' => 'Les ordres, informations et documents diffusés à l’organisation, du plus récent au plus ancien.',
            'section' => 'commandement',
            'sse' => false,
            'icon' => 'announce',
        ],
        'taches' => [
            'tag' => 'Qui suit quoi.',
            'label' => 'Ordres et tâches par unité',
            'desc' => 'Pour chaque unité visible : ordres reçus, accusés attendus, tâches en cours et dernier échange.',
            'section' => 'commandement',
            'sse' => false,
            'icon' => 'tasks',
        ],
        'voie' => [
            'tag' => 'À qui s’adresser.',
            'label' => 'Voie hiérarchique & contacts',
            'desc' => 'L’organigramme lisible, avec le chef et l’adjoint de chaque unité et le moyen de les joindre.',
            'section' => 'commandement',
            'sse' => false,
            'icon' => 'tree',
        ],
        'agenda' => [
            'tag' => 'Les dates qui comptent.',
            'label' => 'Agenda',
            'desc' => 'Briefings, opérations et formations à venir de la communauté, avec votre réponse.',
            'section' => 'commandement',
            'sse' => false,
            'icon' => 'agenda',
        ],
        'epingles' => [
            'tag' => 'Sous la main.',
            'label' => 'Épinglés',
            'desc' => 'Les éléments que l’encadrement garde sous la main, dans l’ordre qu’il a choisi.',
            'section' => 'reperes',
            'sse' => false,
            'icon' => 'pin',
        ],
        'renseignements' => [
            'tag' => 'Renseignements.',
            'label' => 'Renseignements',
            'desc' => 'Les événements et fiches terrain récents du bureau SSE.',
            'section' => 'renseignement',
            'sse' => true,
            'icon' => 'intel',
        ],
        'cibles' => [
            'tag' => 'Cibles.',
            'label' => 'Cibles',
            'desc' => 'Les dossiers d’intérêt suivis par le bureau SSE, avec leur priorité et leur dernière mise à jour.',
            'section' => 'renseignement',
            'sse' => true,
            'icon' => 'target',
        ],
    ];

    /** Types d'échange de l'organisation montrés dans « Annonces & ordres du jour ». */
    public const ANNOUNCE_KINDS = ['ordre', 'info', 'document'];

    /** Un salon connu, ou le salon par défaut. */
    public static function salon(string $raw): string
    {
        $raw = strtolower(trim($raw));

        return isset(self::SALONS[$raw]) ? $raw : self::DEFAULT_SALON;
    }

    /**
     * Phrase à trous : « Ordres des %1 derniers jours » avec les valeurs données (« %1 », « %2 »…). Le modèle reste un seul
     * texte pour la traduction ; les valeurs sont celles du serveur.
     */
    public static function fmt(string $template, int|string ...$values): string
    {
        $out = $template;
        foreach ($values as $i => $value) {
            $out = str_replace('%' . ($i + 1), (string) $value, $out);
        }

        return $out;
    }

    /** Icône de salon ou d'action (traits de 1,8 px, couleur du texte) : jamais d'emoji. */
    public static function icon(string $name): string
    {
        $paths = [
            'overview' => '<rect x="3.5" y="3.5" width="7" height="7"/><rect x="13.5" y="3.5" width="7" height="7"/><rect x="3.5" y="13.5" width="7" height="7"/><rect x="13.5" y="13.5" width="7" height="7"/>',
            'agenda' => '<rect x="3.5" y="4.5" width="17" height="16" rx="2"/><path d="M16 2.5v4M8 2.5v4M3.5 10h17"/>',
            'announce' => '<path d="M4 10v4h3l7 4V6L7 10zM17 9a4 4 0 0 1 0 6"/>',
            'tasks' => '<path d="M5 6h14M5 12h14M5 18h9M3.5 6l.8.8L6 5M3.5 12l.8.8L6 11"/>',
            'tree' => '<rect x="9" y="3" width="6" height="4" rx="1"/><rect x="3" y="17" width="6" height="4" rx="1"/><rect x="15" y="17" width="6" height="4" rx="1"/><path d="M12 7v5M6 17v-5h12v5"/>',
            'intel' => '<circle cx="11" cy="11" r="6"/><path d="m16 16 4 4M11 8v3l2 1"/>',
            'target' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3.5"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>',
            'pin' => '<path d="M9 4h6l-1 6 3 3H7l3-3zM12 13v7"/>',
            'lock' => '<rect x="5" y="11" width="14" height="9" rx="1.5"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
            'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
            'up' => '<path d="m6 14 6-6 6 6"/>',
            'down' => '<path d="m6 10 6 6 6-6"/>',
            'panel' => '<rect x="3.5" y="4.5" width="17" height="15" rx="1.5"/><path d="M15 4.5v15"/>',
            'caret' => '<path d="m9 6 6 6-6 6"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/>',
            'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-4 3-6 7-6s7 2 7 6"/>',
            'grip' => '<path d="M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01"/>',
            'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
            'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
            'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
            'alert' => '<path d="M12 3.5 2.5 20h19z"/><path d="M12 10v4.5M12 17.5v.01"/>',
            'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'copy' => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/>',
            'msg' => '<path d="M20.5 15a2 2 0 0 1-2 2H8l-4.5 4V6a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2z"/>',
            'doc' => '<path d="M14 3.5H7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8.5z"/><path d="M14 3.5v5h5"/>',
        ];

        return '<svg class="jc-i" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
    }

    /**
     * Chemin d'un salon, relatif à l'application (le lecteur l'enveloppe dans url()). Les paramètres sont retenus d'après
     * une liste blanche : jamais une adresse reprise de la requête.
     *
     * @param array<string, scalar> $params
     */
    public static function path(string $salon, array $params = []): string
    {
        $allowed = ['type', 'portee', 'q', 'echelon', 'etat', 'retard', 'tri', 'sens', 'unite', 'priorite', 'branche', 'genre'];
        $query = ['commun' => '1', 'salon' => self::salon($salon)];
        foreach ($params as $key => $value) {
            if (in_array($key, $allowed, true) && (string) $value !== '') {
                $query[$key] = (string) $value;
            }
        }

        return 'jnet?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Intitulé d'un jour de fil : « Aujourd’hui », « Hier » ou la date « 09/11/2026 » (sans nom de mois : valable dans
     * toutes les langues).
     */
    public static function dayLabel(int $ts, int $now): string
    {
        $day = date('Y-m-d', $ts);
        if ($day === date('Y-m-d', $now)) {
            return 'Aujourd’hui';
        }
        if ($day === date('Y-m-d', $now - 86400)) {
            return 'Hier';
        }

        return date('d/m/Y', $ts);
    }

    /**
     * Regroupe des éléments déjà triés (du plus récent au plus ancien) par jour : une rubrique par date, comme un fil de
     * discussion. Un élément sans date lisible va dans le dernier groupe « Sans date ».
     *
     * @param list<array<string, mixed>> $items
     * @return list<array{label: string, items: list<array<string, mixed>>}>
     */
    public static function groupByDay(array $items, string $dateKey, int $now): array
    {
        $groups = [];
        $index = [];
        foreach ($items as $item) {
            $ts = strtotime((string) ($item[$dateKey] ?? ''));
            $label = $ts ? self::dayLabel($ts, $now) : 'Sans date';
            if (!isset($index[$label])) {
                $index[$label] = count($groups);
                $groups[] = ['label' => $label, 'items' => []];
            }
            $groups[$index[$label]]['items'][] = $item;
        }

        return $groups;
    }

    /**
     * Voie hiérarchique : toutes les unités de l'arbre du lecteur dans l'ordre de l'ORBAT (parcours en profondeur), chacune
     * avec son chemin « Organisation › … », son chef et son adjoint (fiches liées seulement si l'identité n'est pas masquée)
     * et son effectif. Une recherche garde les unités qui correspondent (nom, code, chef, adjoint, indicatif) et leurs
     * ascendants, pour que la voie reste lisible.
     *
     * @param array<int, array<string, mixed>> $people fiches du personnel par identifiant (name, callsign, grade, function, href)
     * @param array<int, array{total: int, available: int, off?: int}> $strength effectif par unité
     * @param \Closure(string): string $echelonLabel libellé d'un échelon
     * @return list<array<string, mixed>>
     */
    public static function hierarchy(JnetSpaceTree $tree, array $people, array $strength, string $query, \Closure $echelonLabel): array
    {
        $needle = self::fold($query);
        $order = [];
        $walk = static function (int $id) use (&$walk, &$order, $tree): void {
            foreach ($tree->childrenOf($id) as $child) {
                $order[] = $child;
                $walk($child);
            }
        };
        $walk(JnetSpaceTree::ORG);

        $rows = [];
        $matched = [];
        foreach ($order as $id) {
            $n = (array) $tree->node($id);
            $leader = self::person((int) ($n['leaderId'] ?? 0), (string) ($n['leader'] ?? ''), $people);
            $deputy = self::person((int) ($n['deputyId'] ?? 0), (string) ($n['deputy'] ?? ''), $people);
            $chain = array_map(static fn (int $c): string => (string) ($tree->node($c)['label'] ?? ''), $tree->chain($id));
            $parent = $tree->parentOf($id);
            $row = [
                'id' => $id,
                'label' => (string) ($n['label'] ?? ''),
                'code' => (string) ($n['code'] ?? ''),
                'accent' => (string) ($n['accent'] ?? ''),
                'badge' => is_string($n['badge'] ?? null) ? (string) $n['badge'] : '',
                'echelon' => (string) ($n['echelon'] ?? ''),
                'echelonLabel' => $echelonLabel((string) ($n['echelon'] ?? '')),
                'depth' => max(0, (int) ($n['depth'] ?? 1) - 1),
                'chain' => $chain,
                'parentId' => $parent !== null && $parent !== JnetSpaceTree::ORG ? $parent : 0,
                'parentLabel' => $parent !== null && $parent !== JnetSpaceTree::ORG ? (string) ($tree->node($parent)['label'] ?? '') : '',
                'leader' => $leader,
                'deputy' => $deputy,
                'strength' => ['total' => (int) ($strength[$id]['total'] ?? 0), 'available' => (int) ($strength[$id]['available'] ?? 0)],
                'children' => count($tree->childrenOf($id)),
                'match' => $needle === '',
            ];
            if ($needle !== '') {
                $hay = self::fold(implode(' ', [
                    $row['label'], $row['code'], $row['echelonLabel'],
                    $leader['name'], $leader['callsign'], $deputy['name'], $deputy['callsign'],
                ]));
                $row['match'] = str_contains($hay, $needle);
                if ($row['match']) {
                    foreach ($tree->ancestors($id) as $a) {
                        $matched[$a] = true;
                    }
                    $matched[$id] = true;
                }
            }
            $rows[] = $row;
        }
        if ($needle === '') {
            return $rows;
        }

        return array_values(array_filter($rows, static fn (array $r): bool => isset($matched[(int) $r['id']])));
    }

    /**
     * « Ma branche » : les unités du lecteur, leurs ascendants et tout ce qui est dessous, indexées par identifiant.
     *
     * @param list<int> $viewerUnits
     * @return array<int, true>
     */
    public static function branchIds(JnetSpaceTree $tree, array $viewerUnits): array
    {
        $ids = [];
        foreach ($viewerUnits as $v) {
            if (!$tree->has($v)) {
                continue;
            }
            foreach (array_merge($tree->chain($v), $tree->descendants($v)) as $id) {
                if ($id !== JnetSpaceTree::ORG) {
                    $ids[$id] = true;
                }
            }
        }

        return $ids;
    }

    /**
     * L'unité la plus basse de l'arbre parmi celles du lecteur (point de départ de « Ma voie »), 0 s'il n'en a aucune.
     *
     * @param list<int> $viewerUnits
     */
    public static function lowestUnit(JnetSpaceTree $tree, array $viewerUnits): int
    {
        $best = 0;
        $depth = -1;
        foreach ($viewerUnits as $v) {
            if (!$tree->has($v)) {
                continue;
            }
            $d = count($tree->ancestors($v));
            if ($d > $depth) {
                $best = $v;
                $depth = $d;
            }
        }

        return $best;
    }

    /**
     * Une personne de la voie : nom lisible, indicatif et fiche, ou seulement le libellé quand l'identité est masquée.
     *
     * @param array<int, array<string, mixed>> $people
     * @return array{id: int, name: string, callsign: string, grade: string, function: string, href: string}
     */
    public static function person(int $id, string $label, array $people): array
    {
        $card = $id > 0 ? ($people[$id] ?? null) : null;
        $label = trim($label);
        $name = $card !== null && trim((string) ($card['name'] ?? '')) !== '' ? trim((string) $card['name']) : $label;

        return [
            'id' => $card !== null ? $id : 0,
            'name' => $name,
            'callsign' => $card !== null ? trim((string) ($card['callsign'] ?? '')) : '',
            'grade' => $card !== null ? trim((string) ($card['grade'] ?? '')) : '',
            'function' => $card !== null ? trim((string) ($card['function'] ?? '')) : '',
            'href' => $card !== null ? (string) ($card['href'] ?? '') : '',
        ];
    }

    /** Texte comparable : minuscules sans accents ni espaces superflus. */
    public static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $map = ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe', '’' => "'"];

        return (string) preg_replace('/\s+/u', ' ', strtr($text, $map));
    }
}
