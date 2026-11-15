<?php

declare(strict_types=1);

namespace App\Services\Sse\Cerbere;

use App\Repositories\SseTriageStateRepository;
use App\Services\Sse\Links\SseLinkGraph;
use App\Services\Sse\Links\SseLinkViewer;
use App\Services\Sse\SseCerbereReport;
use App\Services\Sse\SseTriageInbox;
use App\Support\SseCerbere;
use App\Support\SseLinkCatalog as C;
use App\Support\SseTriageBadges;

/**
 * Widget Cerbère : le contenu du bouton flottant du portail SSE, pour UNE page et UN lecteur.
 *
 * Rien n'est recalculé ici : les liens et leur provenance viennent du graphe de liaison (SseLinkGraph : droits de section,
 * habilitation, identités de membres), les éléments en attente de la boîte « À trier » (SseTriageInbox : files lisibles par la
 * session, sujets masqués au-dessus de l'habilitation), l'état des automatismes du rapport « Cerbères » (SseCerbereReport).
 * Le lecteur (SseLinkViewer) porte la communauté de la SESSION : aucun identifiant de communauté ne vient de la requête.
 * Un élément illisible ou d'une autre communauté répond comme un élément qui n'existe pas (panel() renvoie null).
 *
 * Le badge du bouton compte ce qui attend sur CETTE page : les éléments en attente qui concernent l'élément affiché, ceux des
 * files de la liste affichée, ou le total ailleurs ; il ne coûte aucune requête de plus (la boîte est déjà lue, 30 s de cache,
 * pour la barre latérale).
 */
final class SseCerbereWidget
{
    /** Lignes au plus par bloc : le tiroir résume, la page de l'élément ou « À trier » détaille. */
    public const MAX_ROWS = 25;

    /** Éléments « À trier » dont la clé est celle de l'élément lui-même (type de liaison => préfixe de clé). */
    private const KEY_PREFIX = ['note' => 'note', 'check' => 'check', 'interest' => 'interest', 'document' => 'document', 'notice' => 'notice'];

    /** Éléments « À trier » rattachés par leur sujet (type de liaison => type de sujet). */
    private const SUBJECT_TYPE = ['case' => 'case', 'identity' => 'identity'];

    /** Raccourcis proposés ailleurs : identifiant de navigation => phrase. */
    private const SHORTCUTS = [
        'rapprochements' => 'Accepter ou écarter ce que Cerbère-Validation propose.',
        'avis-detections' => 'Identités sous avis de recherche repérées par Cerbère-Détection.',
        'croisements' => 'Correspondances trouvées par Cerbère-Corrélation.',
        'anomalies' => 'Écarts et incohérences relevés par le bureau.',
        'cerberes' => 'Tout ce que fait Cerbère : état, règles et derniers événements.',
    ];

    /** @var array{total: int, sources: array<string, array<string, mixed>>, items: list<array<string, mixed>>, counts: array<string, int>, degraded: bool}|null */
    private ?array $snap = null;

    /**
     * @param list<array<string, mixed>> $nav navigation visible de la session (SsePortalNav::forSession)
     * @param (\Closure(): SseLinkGraph)|null $graph graphe de liaison du lecteur
     * @param (\Closure(int): array<string, mixed>)|null $report rapport des Cerbères d'une communauté
     */
    public function __construct(
        private SseLinkViewer $viewer,
        private array $nav = [],
        private ?SseTriageInbox $inbox = null,
        private ?\Closure $graph = null,
        private ?\Closure $report = null,
    ) {
        $this->inbox ??= SseTriageInbox::forSession();
    }

    /**
     * Données du bouton pour la coque du portail (une seule inclusion, dans le layout). Jamais bloquant : sans communauté ou en cas
     * d'erreur, le bouton n'est pas rendu et la page s'affiche comme avant.
     *
     * @param list<array<string, mixed>> $nav navigation visible de la session
     * @return array{enabled: bool, ctx: array<string, string>, badge: array{count: int, tone: string, label: string, aria: string}, url: string, quiet: bool}
     */
    public static function shell(array $nav, string $requestPath): array
    {
        $ctx = SseCerbereContext::fromPath($requestPath);
        $off = ['enabled' => false, 'ctx' => $ctx, 'badge' => ['count' => 0, 'tone' => 'neutral', 'label' => '', 'aria' => ''], 'url' => '', 'quiet' => false];
        try {
            $viewer = new \App\Services\Sse\Links\SseLinkSessionViewer();
            if ($viewer->tenantId() < 1) {
                return $off;
            }

            return [
                'enabled' => true, 'ctx' => $ctx, 'badge' => (new self($viewer, $nav))->badge($ctx),
                'url' => function_exists('url') ? (string) url('atak/sse/cerbere/panneau') : '/atak/sse/cerbere/panneau',
                // Lecture d'un papier : le bouton se fait discret pour ne pas gêner la lecture.
                'quiet' => $ctx['type'] === 'paper',
            ];
        } catch (\Throwable $e) {
            error_log('Cerbère : bouton indisponible : ' . $e->getMessage());

            return $off;
        }
    }

    /** @return list<string> */
    private function navIds(): array
    {
        $ids = [];
        foreach ($this->nav as $section) {
            foreach ($section['items'] as $item) {
                $ids[] = (string) $item['id'];
            }
        }

        return $ids;
    }

    /** @return array{total: int, sources: array<string, array<string, mixed>>, items: list<array<string, mixed>>, counts: array<string, int>, degraded: bool} */
    private function snapshot(): array
    {
        if ($this->snap !== null) {
            return $this->snap;
        }
        try {
            return $this->snap = $this->inbox->snapshot($this->viewer->tenantId(), (int) $this->viewer->userId(), $this->navIds());
        } catch (\Throwable $e) {
            error_log('Cerbère : boîte « À trier » illisible : ' . $e->getMessage());

            return $this->snap = ['total' => 0, 'sources' => [], 'items' => [], 'counts' => ['open' => 0, 'mine' => 0, 'snoozed' => 0, 'acknowledged' => 0], 'degraded' => true];
        }
    }

    /**
     * Ce que Cerbère a à signaler sur cette page.
     *
     * @param array{kind: string, type: string, id: string, nav: string, label: string, section: string} $ctx
     * @return array{count: int, tone: string, label: string, aria: string}
     */
    public function badge(array $ctx): array
    {
        $items = $this->pending($ctx);
        $tone = SseTriageInbox::TONE_NEUTRAL;
        foreach ($items as $it) {
            $p = (int) ($it['priority'] ?? 0);
            $tone = SseTriageBadges::worst($tone, $p >= 2 ? SseTriageInbox::TONE_BAD : ($p === 1 ? SseTriageInbox::TONE_WARN : SseTriageInbox::TONE_NEUTRAL));
        }
        $n = count($items);

        return ['count' => $n, 'tone' => $tone, 'label' => SseTriageBadges::label($n), 'aria' => SseTriageBadges::aria($n)];
    }

    /**
     * Éléments « À trier » ouverts qui concernent cette page.
     *
     * @param array<string, string> $ctx
     * @return list<array<string, mixed>>
     */
    public function pending(array $ctx): array
    {
        $open = array_values(array_filter($this->snapshot()['items'], static fn (array $i): bool => ($i['triage']['status'] ?? SseTriageStateRepository::OPEN) === SseTriageStateRepository::OPEN));
        switch ($ctx['kind']) {
            case SseCerbereContext::KIND_ELEMENT:
                if (!$this->readable($ctx['section'])) {
                    return [];
                }

                return array_values(array_filter($open, fn (array $i): bool => $this->concerns($i, $ctx['type'], $ctx['id'])));
            case SseCerbereContext::KIND_LIST:
                $sources = [];
                foreach ($this->snapshot()['sources'] as $key => $s) {
                    if ($s['nav'] === $ctx['nav']) {
                        $sources[$key] = true;
                    }
                }
                $subject = ['dossiers' => 'case', 'identites' => 'identity', 'papiers-identites' => 'identity'][$ctx['nav']] ?? '';

                return array_values(array_filter($open, static fn (array $i): bool => isset($sources[(string) $i['source']]) || ($subject !== '' && (string) ($i['subject_type'] ?? '') === $subject)));
            default:
                return $ctx['kind'] === SseCerbereContext::KIND_TRIAGE ? [] : $open;
        }
    }

    private function readable(string $section): bool
    {
        return $section === '' || $this->viewer->canRead($section);
    }

    /** @param array<string, mixed> $item */
    private function concerns(array $item, string $type, string $id): bool
    {
        if (isset(self::KEY_PREFIX[$type]) && (string) $item['key'] === self::KEY_PREFIX[$type] . ':' . $id) {
            return true;
        }
        if (isset(self::SUBJECT_TYPE[$type])) {
            return (string) ($item['subject_type'] ?? '') === self::SUBJECT_TYPE[$type] && (string) (int) ($item['subject_id'] ?? 0) === $id;
        }

        return false;
    }

    /**
     * Contenu du tiroir, ou null quand l'élément est introuvable pour ce lecteur (autre communauté, section ou habilitation
     * insuffisante, droit retiré) : la réponse est alors celle d'un élément qui n'existe pas.
     *
     * @param array{kind: string, type: string, id: string, nav: string, label: string, section: string} $ctx
     * @return array<string, mixed>|null
     */
    public function panel(array $ctx): ?array
    {
        $tenant = $this->viewer->tenantId();
        if ($tenant < 1) {
            return null;
        }
        $out = ['ctx' => $ctx, 'subject' => null, 'tabs' => [], 'canDecide' => $this->canDecide(), 'degraded' => (bool) $this->snapshot()['degraded'], 'badge' => $this->badge($ctx)];
        $analyse = $this->viewer->canRead('analyse');
        if ($ctx['kind'] === SseCerbereContext::KIND_ELEMENT) {
            if (!$this->readable($ctx['section'])) {
                return null;
            }
            $g = $this->resolve($ctx['type'], $ctx['id']);
            if ($g === null) {
                return null;
            }
            $root = $g['nodes'][$g['root']];
            $out['subject'] = ['title' => !empty($root['restricted']) ? 'Élément restreint' : (string) ($root['title'] !== '' ? $root['title'] : $root['ref']), 'type_label' => (string) $root['type_label'], 'ref' => (string) $root['ref']];
            [$done, $origin] = $this->links($g);
            $out['tabs'][] = ['id' => 'done', 'label' => 'Fait par Cerbère', 'desc' => 'Ce que Cerbère a rattaché, validé ou détecté sur cet élément, avec la règle appliquée.', 'groups' => $done, 'count' => $this->countRows($done)];
            $out['tabs'][] = ['id' => 'todo', 'label' => 'À décider', 'desc' => 'Ce qui attend une décision humaine sur cet élément.', 'rows' => $this->rows($this->pending($ctx)), 'count' => count($this->pending($ctx)), 'all' => $this->triageUrl('')];
            $out['tabs'][] = ['id' => 'origin', 'label' => 'Provenance', 'desc' => 'D’où vient cet élément et qui l’a relié aux autres.', 'rows' => $origin['rows'], 'chains' => $origin['chains'], 'count' => count($origin['rows']), 'truncated' => (bool) ($g['truncated'] ?? false)];
        } elseif ($ctx['kind'] === SseCerbereContext::KIND_LIST) {
            $out['tabs'][] = ['id' => 'todo', 'label' => 'À traiter ici', 'desc' => 'Ce qui attend une décision dans cette liste, avec l’accès direct à « À trier ».', 'rows' => $this->rows($this->pending($ctx)), 'sources' => $this->sources($ctx['nav']), 'count' => count($this->pending($ctx)), 'all' => $this->triageUrl($this->firstSource($ctx['nav']))];
        } else {
            $out['tabs'][] = ['id' => 'overview', 'label' => 'Aperçu', 'desc' => $ctx['kind'] === SseCerbereContext::KIND_TRIAGE ? 'Vous êtes dans la boîte « À trier » : voici le détail des files et les pages utiles.' : 'Ce que Cerbère signale dans tout le bureau et les pages utiles pour le traiter.', 'rows' => $this->rows(array_slice($this->pending($ctx), 0, 5)), 'sources' => $this->sources(''), 'shortcuts' => $this->shortcuts(), 'total' => $this->snapshot()['total'], 'count' => $ctx['kind'] === SseCerbereContext::KIND_TRIAGE ? 0 : count($this->pending($ctx)), 'all' => $this->triageUrl('')];
        }
        if ($analyse) {
            $out['tabs'][] = ['id' => 'auto', 'label' => 'Cerbères', 'desc' => 'Les traitements automatiques de Cerbère : actifs ou non, et ce qu’ils ont fait.', 'treatments' => $this->treatments(), 'count' => 0, 'link' => $this->url('atak/sse/cerberes')];
        }

        return $out;
    }

    private function canDecide(): bool
    {
        return !$this->viewer->isGuest() && $this->viewer->canWrite('dossiers');
    }

    /** @return array<string, mixed>|null */
    private function resolve(string $type, string $id): ?array
    {
        try {
            $graph = $this->graph !== null ? ($this->graph)() : null;
            if ($graph === null) {
                return null;
            }
            $g = $graph->resolve($type, $id);

            return empty($g['found']) ? null : $g;
        } catch (\Throwable $e) {
            error_log('Cerbère : liens illisibles : ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Liens directs de l'élément : ceux que Cerbère a posés (groupés par traitement), puis tous les autres (provenance).
     *
     * @param array<string, mixed> $g
     * @return array{0: list<array{treatment: string, rows: list<array<string, mixed>>}>, 1: array{rows: list<array<string, mixed>>, chains: list<string>}}
     */
    private function links(array $g): array
    {
        $byTreatment = [];
        $others = [];
        foreach ($g['direct'] as $list) {
            foreach ($list as $d) {
                $e = $g['edges'][$d['edge']] ?? null;
                $n = $g['nodes'][$d['node']] ?? null;
                if ($e === null || $n === null) {
                    continue;
                }
                $restricted = !empty($n['restricted']);
                $row = [
                    'relation' => (string) ($d['forward'] ? $e['label'] : $e['inverse']),
                    'type_label' => (string) $n['type_label'],
                    'title' => $restricted ? 'Élément restreint' : (string) ($n['ref'] !== '' ? $n['ref'] : $n['title']),
                    'summary' => $restricted || $n['title'] === '' || $n['ref'] === '' || str_contains((string) $n['title'], (string) $n['ref']) ? '' : (string) $n['title'],
                    'href' => $restricted ? '' : (string) $n['href'],
                    'reason' => (string) $e['reason'],
                    'origin' => (string) $e['origin_label'],
                    'rule' => (string) ($e['rule'] ?? ''),
                    'rule_label' => (string) (SseCerbere::RULES[(string) ($e['rule'] ?? '')]['label'] ?? ''),
                    'actor' => (string) $e['actor'],
                    'when' => self::when((string) $e['at']),
                    'at' => (string) $e['at'],
                    'restricted' => $restricted,
                ];
                if ((string) $e['treatment'] !== '') {
                    $byTreatment[(string) $e['treatment']][] = $row;
                } else {
                    $others[] = $row;
                }
            }
        }
        $groups = [];
        foreach (SseCerbere::TREATMENTS as $def) {
            if (isset($byTreatment[$def['label']])) {
                $groups[] = ['treatment' => $def['label'], 'rows' => $this->newest($byTreatment[$def['label']])];
                unset($byTreatment[$def['label']]);
            }
        }
        foreach ($byTreatment as $label => $rows) {
            $groups[] = ['treatment' => (string) $label, 'rows' => $this->newest($rows)];
        }

        return [$groups, ['rows' => $this->newest($others), 'chains' => array_slice(array_values(array_map('strval', $g['chains'] ?? [])), 0, 3)]];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function newest(array $rows): array
    {
        usort($rows, static fn (array $a, array $b): int => strcmp((string) $b['at'], (string) $a['at']));

        return array_slice($rows, 0, self::MAX_ROWS);
    }

    /** @param list<array{treatment: string, rows: list<array<string, mixed>>}> $groups */
    private function countRows(array $groups): int
    {
        return array_sum(array_map(static fn (array $g): int => count($g['rows']), $groups));
    }

    /**
     * Lignes « À trier » prêtes à afficher : le sujet masqué par la boîte remplace le titre, et un sujet masqué n'a pas de lien.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function rows(array $items): array
    {
        $out = [];
        foreach (array_slice($items, 0, self::MAX_ROWS) as $it) {
            $subject = is_array($it['subject'] ?? null) ? $it['subject'] : null;
            $masked = $subject !== null && !empty($subject['masked']);
            $hasSubject = (string) ($it['subject_type'] ?? '') !== '';
            $title = $subject !== null ? (string) $subject['label'] : ($hasSubject && (string) ($it['source'] ?? '') === 'detections' ? 'Détection' : (string) $it['title']);
            $id = 0;
            if ((string) ($it['source'] ?? '') === 'suggestions' && preg_match('/^suggestion:(\d+)$/', (string) $it['key'], $m) === 1) {
                $id = (int) $m[1];
            }
            $p = (int) ($it['priority'] ?? 0);
            $out[] = [
                'key' => (string) $it['key'],
                'title' => $title,
                'meta' => (string) ($it['source_label'] ?? ''),
                'what' => (string) ($it['type_label'] ?? ''),
                'reason' => (string) ($it['reason'] ?? ''),
                'href' => $masked ? '' : (string) ($it['href'] ?? ''),
                'when' => self::when((string) ($it['at'] ?? '')),
                'tone' => $p >= 2 ? SseTriageInbox::TONE_BAD : ($p === 1 ? SseTriageInbox::TONE_WARN : SseTriageInbox::TONE_NEUTRAL),
                'suggestion' => $id,
                'case_id' => !$masked && (string) ($it['subject_type'] ?? '') === 'case' ? (int) ($it['subject_id'] ?? 0) : 0,
            ];
        }

        return $out;
    }

    /**
     * Files de « À trier » à afficher : celles de la page (entrée de navigation) ou toutes (ailleurs).
     *
     * @return list<array{label: string, count: int, tone: string, href: string}>
     */
    private function sources(string $nav): array
    {
        $out = [];
        foreach ($this->snapshot()['sources'] as $key => $s) {
            if ($nav !== '' && $s['nav'] !== $nav) {
                continue;
            }
            if ((int) $s['count'] < 1) {
                continue;
            }
            $out[] = ['label' => (string) (\App\Support\SseCerbere::LEGACY_LABELS[(string) $s['label']] ?? $s['label']), 'count' => (int) $s['count'], 'tone' => (string) $s['tone'], 'href' => $this->triageUrl((string) $key)];
        }
        usort($out, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($out, 0, 12);
    }

    private function firstSource(string $nav): string
    {
        foreach ($this->snapshot()['sources'] as $key => $s) {
            if ($s['nav'] === $nav) {
                return (string) $key;
            }
        }

        return '';
    }

    private function triageUrl(string $source): string
    {
        return $this->url('atak/sse/a-trier') . ($source !== '' ? '?source=' . rawurlencode($source) : '');
    }

    private function url(string $path): string
    {
        return function_exists('url') ? (string) url($path) : '/' . $path;
    }

    /** @return list<array{label: string, hint: string, href: string}> */
    private function shortcuts(): array
    {
        $byId = [];
        foreach ($this->nav as $section) {
            foreach ($section['items'] as $item) {
                $byId[(string) $item['id']] = $item;
            }
        }
        $out = [];
        if ($this->snapshot()['sources'] !== []) {
            $out[] = ['label' => 'À trier', 'hint' => 'Tout ce qui attend une décision, toutes files confondues.', 'href' => $this->triageUrl('')];
        }
        foreach (self::SHORTCUTS as $id => $hint) {
            if (isset($byId[$id])) {
                $out[] = ['label' => (string) $byId[$id]['label'], 'hint' => $hint, 'href' => $this->url((string) $byId[$id]['path'])];
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function treatments(): array
    {
        try {
            $report = $this->report !== null ? ($this->report)($this->viewer->tenantId()) : (new SseCerbereReport())->build($this->viewer->tenantId());
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($report['treatments'] ?? [] as $t) {
            $out[] = [
                'label' => (string) $t['label'], 'does' => (string) $t['does'], 'active' => (bool) $t['active'], 'why' => (string) $t['why'],
                'total' => (int) $t['total'], 'last' => self::when((string) ($t['last'] ?? '')),
            ];
        }

        return $out;
    }

    /** « 15/11 12:30 » (UTC) ou chaîne vide. */
    private static function when(string $utc): string
    {
        $ts = $utc !== '' ? strtotime($utc . (preg_match('/(Z|[+-]\d\d:?\d\d)$/', $utc) === 1 ? '' : ' UTC')) : false;

        return $ts === false ? '' : gmdate('d/m H:i', $ts) . 'Z';
    }
}
