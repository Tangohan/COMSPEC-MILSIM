<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\SseDocumentRepository;
use App\Repositories\SseFieldNoteRepository;
use App\Repositories\SseIdCheckRepository;
use App\Repositories\SseInterestCaseRepository;
use App\Repositories\SseSuggestionQueueRepository;
use App\Repositories\SseTriageEventRepository;
use App\Repositories\SseTriageRuleRepository;
use App\Repositories\SseTriageStateRepository;
use App\Repositories\SseWantedDetectionRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Support\SseAccessCatalog;
use App\Support\SseSignalIdentity;
use App\Support\SseTriagePresets;
use App\Support\SseTriageSchema;
use App\Support\ShortCache;

/**
 * Boîte « À trier » du bureau SSE : tout ce qui attend une décision humaine, agrégé depuis les files qui existent déjà
 * (aucune logique recopiée : chaque source appelle le dépôt ou le service de sa page).
 *
 * - Une source = une entrée de la navigation (nav), une section de droits (déduite de la page), un chargeur de 100 éléments au plus
 *   (au-delà, le compteur affiche « 99+ ») et un ton.
 * - Droits : une source dont la section n'est pas lisible par la session n'est ni comptée ni listée. Le service ne connaît que
 *   les sources qu'on lui autorise (la navigation visible) ; les droits de lecture sont revérifiés ici.
 * - Dédoublonnage : un élément est identifié par « type:id » ; le total compte les éléments distincts, pas la somme des files. Les
 *   signaux du moteur sont de plus dédoublonnés par identité (dossier, type, règle, variante), même avant le rattrapage des doublons.
 * - Sujet : chaque élément porte le sujet concerné (dossier, personnes, identité), masqué selon les droits (SseTriageSubjects).
 * - État : acquitté, en veille ou assigné (sse_triage_state, par communauté). Les compteurs et la pastille de la barre latérale ne
 *   comptent que les éléments ni acquittés ni en veille. Sans les tables de l'étape 4740 : mode dégradé (« degraded »), liste
 *   regroupée en lecture seule.
 * - Règles Cerbère-Tri : les règles actives de la communauté traitent, au calcul de la liste, les éléments qui n'ont aucun état ;
 *   chaque action est journalisée et annulable (SseTriageRules).
 * - Priorité : score calculé (SseTriagePriority) ; tri par priorité décroissante, puis par ancienneté.
 * - Cache : 30 s par communauté, compte et jeu de sources autorisées ; toute écriture réussie du portail le périme (invalidateAfter).
 * - Ton d'une file : rouge si un élément est critique (priorité 2), ambre si le plus ancien a plus de 7 jours, neutre sinon.
 * Ouvrir un élément mène à sa page, qui garde la décision métier ; ici on ne décide que de l'ordre et de la visibilité de la liste.
 */
final class SseTriageInbox
{
    public const TTL = 30;
    public const LIMIT = 100;
    public const AMBER_DAYS = 7;
    public const NS = 'sse_triage';

    public const TONE_NEUTRAL = 'neutral';
    public const TONE_WARN = 'warn';
    public const TONE_BAD = 'bad';

    /** @var list<array{key: string, nav: string, label: string, tab: string, path: string, load: \Closure}>|null */
    private ?array $definitions;

    /** Les services d'état, de règles et de sujets ne sont branchés d'office que sur les vraies files (pas sur des définitions de test). */
    private bool $automations;

    /**
     * @param list<array{key: string, nav: string, label: string, tab: string, path: string, load: \Closure}>|null $definitions
     * @param (\Closure(string, string): bool)|null $allows (section, niveau) : la session peut-elle lire ?
     * @param (\Closure(): int)|null $clock
     */
    public function __construct(
        ?array $definitions = null,
        private ?\Closure $allows = null,
        private ?\Closure $clock = null,
        private ?SseTriageStateRepository $states = null,
        private ?SseTriageRuleRepository $rules = null,
        private ?SseTriageEventRepository $events = null,
        private ?SseTriageSubjects $subjects = null,
    ) {
        $this->definitions = $definitions;
        $this->automations = $definitions === null || $states !== null || $subjects !== null;
    }

    private static ?self $session = null;

    public static function forSession(): self
    {
        return self::$session ?? new self();
    }

    /** Boîte imposée à la session (tests, démonstration) ; null la retire. */
    public static function useForSession(?self $inbox): void
    {
        self::$session = $inbox;
    }

    /**
     * Compteurs et liste unifiée pour les sources autorisées.
     *
     * @param list<string> $navIds entrées de navigation visibles de la session (seules leurs files comptent)
     * @return array{total: int, sources: array<string, array{key: string, nav: string, label: string, tab: string, href: string, count: int, tone: string, oldest_days: int}>, items: list<array<string, mixed>>, counts: array{open: int, mine: int, snoozed: int, acknowledged: int}, degraded: bool}
     */
    public function snapshot(int $tenantId, int $userId, array $navIds): array
    {
        $empty = ['total' => 0, 'sources' => [], 'items' => [], 'counts' => ['open' => 0, 'mine' => 0, 'snoozed' => 0, 'acknowledged' => 0], 'degraded' => false];
        if ($tenantId < 1) {
            return $empty;
        }
        $defs = array_values(array_filter(
            $this->defs($userId),
            fn (array $d): bool => in_array($d['nav'], $navIds, true) && $this->canRead($d['path'], $tenantId)
        ));
        if ($defs === []) {
            return $empty;
        }
        $keys = array_map(static fn (array $d): string => $d['key'], $defs);
        sort($keys);
        $cacheKey = self::NS . ':' . $tenantId . ':' . $userId . ':' . substr(sha1(implode(',', $keys)), 0, 12) . ':' . ShortCache::gen(self::NS);

        return ShortCache::remember($cacheKey, self::TTL, fn (): array => $this->build($tenantId, $userId, $defs));
    }

    /**
     * @param list<array{key: string, nav: string, label: string, tab: string, path: string, load: \Closure}> $defs
     * @return array{total: int, sources: array<string, array<string, mixed>>, items: list<array<string, mixed>>, counts: array<string, int>, degraded: bool}
     */
    private function build(int $tenantId, int $userId, array $defs): array
    {
        $now = $this->now();
        $perSource = [];
        $distinct = [];
        foreach ($defs as $d) {
            try {
                $items = ($d['load'])($tenantId);
            } catch (\Throwable) {
                $items = [];
            }
            $items = array_slice(array_values(array_filter($items, 'is_array')), 0, self::LIMIT);
            $perSource[$d['key']] = [];
            foreach ($items as $it) {
                $it['source'] = $d['key'];
                $it['source_label'] = $d['tab'];
                $it['type_key'] = (string) ($it['type_key'] ?? $d['key']);
                $it['type_label'] = (string) ($it['type_label'] ?? $d['label']);
                $k = (string) ($it['key'] ?? '');
                if ($k === '') {
                    $k = $d['key'] . ':' . count($distinct);
                }
                $perSource[$d['key']][] = $k;
                // Le premier détenteur garde l'élément ; la priorité la plus haute l'emporte.
                if (!isset($distinct[$k]) || (int) ($it['priority'] ?? 0) > (int) ($distinct[$k]['priority'] ?? 0)) {
                    $distinct[$k] = $it + ['key' => $k];
                }
            }
        }
        $list = array_values($distinct);

        if ($this->automations) {
            try {
                $list = ($this->subjects ?? new SseTriageSubjects())->enrich($tenantId, $list);
            } catch (\Throwable) {
                // Les sujets sont un confort de lecture : sans eux la liste reste celle des files.
            }
        }
        $degraded = $this->automations && !SseTriageSchema::ready();
        $states = [];
        if ($this->automations && !$degraded) {
            try {
                $states = $this->applyRules($tenantId, $list, $now);
            } catch (\Throwable $e) {
                error_log('À trier : états et règles indisponibles : ' . $e->getMessage());
                $degraded = true;
            }
        }

        $linked = [];
        foreach ($list as $it) {
            $sk = (string) ($it['subject']['key'] ?? '');
            if ($sk !== '') {
                $linked[$sk] = ($linked[$sk] ?? 0) + 1;
            }
        }
        $counts = ['open' => 0, 'mine' => 0, 'snoozed' => 0, 'acknowledged' => 0];
        foreach ($list as &$it) {
            $it['triage'] = self::triageOf($states[(string) $it['key']] ?? null, $now);
            $days = self::ageDays((string) ($it['at'] ?? ''), $now);
            $sk = (string) ($it['subject']['key'] ?? '');
            $rank = SseTriagePriority::score($it, $days, $sk !== '' ? $linked[$sk] - 1 : 0, (int) $it['triage']['boost']);
            $it['score'] = $rank['score'];
            $it['why'] = $rank['why'];
            $it['severity'] = SseTriagePriority::severityOf($it);
            $st = $it['triage']['status'];
            if ($st === SseTriageStateRepository::OPEN) {
                $counts['open']++;
                if ($userId > 0 && (int) $it['triage']['assigned_to'] === $userId) {
                    $counts['mine']++;
                }
            } elseif ($st === SseTriageStateRepository::SNOOZED) {
                $counts['snoozed']++;
            } else {
                $counts['acknowledged']++;
            }
        }
        unset($it);
        usort($list, static fn (array $a, array $b): int => ((int) $b['score'] <=> (int) $a['score'])
            ?: strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? ''))
            ?: strcmp((string) $a['key'], (string) $b['key']));

        $byKey = [];
        foreach ($list as $it) {
            $byKey[(string) $it['key']] = $it;
        }
        $sources = [];
        foreach ($defs as $d) {
            $actionable = [];
            foreach ($perSource[$d['key']] as $k) {
                if (isset($byKey[$k]) && $byKey[$k]['triage']['status'] === SseTriageStateRepository::OPEN) {
                    $actionable[] = $byKey[$k];
                }
            }
            $oldest = 0;
            foreach ($actionable as $it) {
                $oldest = max($oldest, self::ageDays((string) ($it['at'] ?? ''), $now));
            }
            $sources[$d['key']] = [
                'key' => $d['key'],
                'nav' => $d['nav'],
                'label' => $d['label'],
                'tab' => $d['tab'],
                'href' => function_exists('url') ? (string) url($d['path']) : $d['path'],
                'count' => count($actionable),
                'tone' => self::tone($actionable, $now),
                'oldest_days' => $oldest,
            ];
        }

        return ['total' => $counts['open'], 'sources' => $sources, 'items' => $list, 'counts' => $counts, 'degraded' => $degraded];
    }

    /**
     * Lit l'état des éléments et déclenche les règles actives sur ceux qui n'ont aucun état.
     *
     * @param list<array<string, mixed>> $list
     * @return array<string, array<string, mixed>> état par clé d'élément
     */
    private function applyRules(int $tenantId, array $list, int $now): array
    {
        $stateRepo = $this->states ?? new SseTriageStateRepository();
        $keys = array_map(static fn (array $it): string => (string) $it['key'], $list);
        $states = $stateRepo->forKeys($tenantId, $keys);
        $ruleRepo = $this->rules ?? new SseTriageRuleRepository();
        $active = array_values(array_filter($ruleRepo->enabled($tenantId), static fn (array $r): bool => (string) ($r['preset_key'] ?? '') !== SseTriagePresets::ENGINE_AUTO_RESOLVE));
        if ($active === []) {
            return $states;
        }
        $candidates = [];
        foreach ($list as $it) {
            $it['triage'] = ['exists' => isset($states[(string) $it['key']])];
            $candidates[] = $it;
        }
        $fired = SseTriageRules::fire($tenantId, $active, $candidates, $now, $stateRepo, $this->events ?? new SseTriageEventRepository(), $ruleRepo, gmdate('Y-m-d H:i:s', $now));
        if ($fired !== []) {
            $states = $stateRepo->forKeys($tenantId, $keys);
        }

        return $states;
    }

    /**
     * État effectif d'un élément : une veille échue redevient « open ».
     *
     * @param array<string, mixed>|null $row
     * @return array{exists: bool, status: string, snoozed_until: ?string, assigned_to: int, acknowledged_by: int, acknowledged_at: ?string, note: string, rule_id: int, boost: int}
     */
    public static function triageOf(?array $row, int $now): array
    {
        if ($row === null) {
            return ['exists' => false, 'status' => SseTriageStateRepository::OPEN, 'snoozed_until' => null, 'assigned_to' => 0, 'acknowledged_by' => 0, 'acknowledged_at' => null, 'note' => '', 'rule_id' => 0, 'boost' => 0];
        }
        $status = (string) ($row['status'] ?? SseTriageStateRepository::OPEN);
        $until = ($row['snoozed_until'] ?? null) !== null ? (string) $row['snoozed_until'] : null;
        if ($status === SseTriageStateRepository::SNOOZED) {
            $ts = $until !== null ? strtotime($until . ' UTC') : false;
            if ($ts === false || $ts <= $now) {
                $status = SseTriageStateRepository::OPEN;
            }
        }

        return [
            'exists' => true,
            'status' => in_array($status, [SseTriageStateRepository::OPEN, SseTriageStateRepository::ACKNOWLEDGED, SseTriageStateRepository::SNOOZED], true) ? $status : SseTriageStateRepository::OPEN,
            'snoozed_until' => $until,
            'assigned_to' => (int) ($row['assigned_to'] ?? 0),
            'acknowledged_by' => (int) ($row['acknowledged_by'] ?? 0),
            'acknowledged_at' => ($row['acknowledged_at'] ?? null) !== null ? (string) $row['acknowledged_at'] : null,
            'note' => (string) ($row['note'] ?? ''),
            'rule_id' => (int) ($row['rule_id'] ?? 0),
            'boost' => (int) ($row['priority_boost'] ?? 0),
        ];
    }

    /**
     * Ton d'une file : rouge (un élément critique), ambre (le plus ancien a plus de 7 jours), sinon neutre.
     *
     * @param list<array<string, mixed>> $items
     */
    public static function tone(array $items, int $now): string
    {
        $tone = self::TONE_NEUTRAL;
        foreach ($items as $it) {
            if ((int) ($it['priority'] ?? 0) >= 2) {
                return self::TONE_BAD;
            }
            if (self::ageDays((string) ($it['at'] ?? ''), $now) > self::AMBER_DAYS) {
                $tone = self::TONE_WARN;
            }
        }

        return $tone;
    }

    public static function ageDays(string $utc, int $now): int
    {
        $ts = $utc !== '' ? strtotime($utc . (preg_match('/(Z|[+-]\d\d:?\d\d)$/', $utc) === 1 ? '' : ' UTC')) : false;

        return $ts === false || $ts > $now ? 0 : intdiv($now - $ts, 86400);
    }

    /** Écriture réussie du portail : les files ont pu changer, le cache de 30 s est périmé. */
    public static function invalidate(): void
    {
        ShortCache::bump(self::NS);
    }

    public static function invalidateAfter(Request $request, Response $response): void
    {
        if (in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true) || $response->statusCode() >= 400) {
            return;
        }
        if (preg_match('#^/(atak/sse/|api/sse/)#', $request->path()) === 1) {
            self::invalidate();
        }
    }

    private function now(): int
    {
        return $this->clock !== null ? (int) ($this->clock)() : time();
    }

    private function canRead(string $path, int $tenantId): bool
    {
        $section = SseAccessCatalog::sectionForPath($path);
        if ($section === null) {
            return true;
        }
        if ($this->allows !== null) {
            return (bool) ($this->allows)($section, SseAccessCatalog::LEVEL_READ);
        }
        try {
            return SseAccessPolicy::sessionAllows($section, SseAccessCatalog::LEVEL_READ, ['tenant_id' => $tenantId]);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array{key: string, nav: string, label: string, tab: string, path: string, load: \Closure}> */
    private function defs(int $userId): array
    {
        return $this->definitions ?? self::defaultDefinitions($userId);
    }

    /** Phrase de raison d'une lacune ou d'un signal : le détail du moteur sans son préfixe de catégorie, sur une ligne. */
    public static function reasonOf(?string $detail): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $detail));
        $text = (string) preg_replace('/^(LACUNE IDENTIFIÉE|BESOIN PRIORITAIRE|BESOIN|CRITÈRE DE CONFIRMATION)\s+—\s+/u', '', $text);

        return mb_strlen($text) > 200 ? rtrim(mb_substr($text, 0, 199)) . '…' : $text;
    }

    /**
     * Les files réelles du bureau, chacune lue par le dépôt de sa page.
     *
     * @return list<array{key: string, nav: string, label: string, tab: string, path: string, load: \Closure}>
     */
    public static function defaultDefinitions(int $userId): array
    {
        $item = static fn (string $key, string $title, string $meta, ?string $at, string $path, int $priority = 0, array $extra = []): array => $extra + [
            'key' => $key, 'title' => $title, 'meta' => $meta, 'at' => (string) $at, 'href' => function_exists('url') ? (string) url($path) : $path, 'priority' => $priority,
        ];

        return [
            ['key' => 'fiches', 'nav' => 'fiches', 'label' => 'Fiches de renseignement', 'tab' => 'Fiches', 'path' => 'atak/sse/fiches',
                'load' => static function (int $t) use ($item): array {
                    $out = [];
                    foreach ((new SseFieldNoteRepository())->listForTenant($t, ['status' => 'transmise', 'limit' => self::LIMIT]) as $n) {
                        $urgency = (string) ($n['urgency'] ?? '');
                        $out[] = $item('note:' . (int) $n['id'], (string) ($n['title'] ?: ($n['excerpt'] ?? 'Fiche')), (string) ($n['note_kind_label'] ?? ''), (string) ($n['observed_at'] ?? $n['created_at'] ?? ''), 'atak/sse/fiches/' . (int) $n['id'], $urgency === 'critique' ? 2 : ($urgency === 'urgent' ? 1 : 0));
                    }

                    return $out;
                }],
            // Remontées « À traiter » (badge de l'entrée Remontées) : référence et type seulement, jamais le texte (caviardages).
            ['key' => 'remontees', 'nav' => 'remontees', 'label' => 'Remontée à traiter', 'tab' => 'Remontées', 'path' => 'atak/sse/remontees',
                'load' => static function (int $t) use ($item): array {
                    $intake = new \App\Services\Intel\IntelIntakeService();
                    if (!$intake->schemaReady() || !\App\Controllers\Web\SseIntakeController::sessionRights(false, $t)['treat']) {
                        return [];
                    }
                    $out = [];
                    foreach ($intake->listItems($t, ['state' => 'a_traiter'], 0, self::LIMIT) as $r) {
                        $level = \App\Services\Intel\IntelIntakePresenter::urgencyLevel((string) $r['priority']);
                        $out[] = $item(
                            ($r['source'] === 'fiche' ? 'note:' : 'cr:') . (int) $r['id'],
                            (string) $r['ref'] . ' — ' . (string) $r['type_label'],
                            \App\Services\Intel\IntelIntakeService::SOURCES[$r['source']] ?? '',
                            (string) $r['at'],
                            'atak/sse/remontees/' . $r['source'] . '/' . (int) $r['id'],
                            $level === 'critical' ? 2 : ($level === 'high' ? 1 : 0)
                        );
                    }

                    return $out;
                }],
            ['key' => 'frm', 'nav' => 'papiers-frm', 'label' => 'FRM à valider', 'tab' => 'FRM', 'path' => 'atak/sse/papiers/tableau',
                'load' => static function (int $t) use ($item): array {
                    $out = [];
                    foreach ((new SseIdCheckInsightService())->pendingDrafts($t, null, null, self::LIMIT) as $d) {
                        $out[] = $item('note:' . (int) $d['note_id'], (string) ($d['title'] !== '' ? $d['title'] : $d['check_ref']), trim($d['who'] . ' · ' . $d['verdict_label'], ' ·'), (string) $d['created_at'], 'atak/sse/fiches/' . (int) $d['note_id'], (string) $d['verdict_tone'] === 'bad' ? 1 : 0);
                    }

                    return $out;
                }],
            ['key' => 'controles', 'nav' => 'papiers-controles', 'label' => 'Contrôles reçus', 'tab' => 'Contrôles', 'path' => 'atak/sse/papiers/controles',
                'load' => static function (int $t) use ($item): array {
                    $out = [];
                    foreach ((new SseIdCheckRepository())->list($t, ['unlinked' => true, 'limit' => self::LIMIT]) as $c) {
                        $out[] = $item('check:' . (int) $c['id'], (string) ($c['ref'] ?? 'Contrôle'), trim((string) ($c['presented_last'] ?? '') . ' · ' . (string) ($c['controller_callsign'] ?? ''), ' ·'), (string) ($c['created_at'] ?? ''), 'atak/sse/papiers/controles/' . (int) $c['id']);
                    }

                    return $out;
                }],
            ['key' => 'detections', 'nav' => 'avis-detections', 'label' => 'Détections', 'tab' => 'Détections', 'path' => 'atak/sse/avis/detections',
                'load' => static function (int $t) use ($item): array {
                    if (!SseWantedNoticeRepository::ready()) {
                        return [];
                    }
                    $out = [];
                    foreach ((new SseWantedDetectionRepository())->list($t, ['status' => 'new', 'limit' => self::LIMIT]) as $d) {
                        $out[] = $item('detection:' . (int) $d['id'], (string) ($d['identity_name'] !== '' ? $d['identity_name'] : 'Identité #' . (int) $d['identity_id']), (string) $d['notice_ref'], (string) $d['created_at'], 'atak/sse/avis/detections', 1, [
                            'subject_type' => 'identity', 'subject_id' => (int) $d['identity_id'], 'subject_name' => (string) $d['identity_name'], 'reason' => 'Détection sur une identité sous avis de recherche.', 'severity' => 'high',
                        ]);
                    }

                    return $out;
                }],
            ['key' => 'avis_valider', 'nav' => 'avis-recherche', 'label' => 'Avis à valider', 'tab' => 'Avis', 'path' => 'atak/sse/avis?statut=a_valider',
                'load' => static function (int $t) use ($item): array {
                    // Circuit de validation : avis proposés par un analyste, en attente d'un responsable (vide sans l'étape « processus des avis »).
                    $ids = (new \App\Repositories\SseWantedMetaRepository())->pendingIds($t, self::LIMIT);
                    if ($ids === [] || !SseWantedNoticeRepository::ready()) {
                        return [];
                    }
                    $out = [];
                    foreach ((new SseWantedNoticeRepository())->list($t, ['status' => 'active', 'ids' => $ids, 'limit' => self::LIMIT]) as $n) {
                        $out[] = $item('notice:' . (int) $n['id'], (string) ($n['identity_name'] !== '' ? $n['identity_name'] : 'Identité #' . (int) $n['identity_id']), (string) $n['ref'], (string) $n['created_at'], 'atak/sse/papiers/identites/' . (int) $n['identity_id'] . '#avis', 1);
                    }

                    return $out;
                }],
            // Signalements reçus sur une alerte (site ou jeu) et propositions d'alerte venues du jeu : vides sans l'étape « alertes SSE ».
            ['key' => 'signalements', 'nav' => 'avis-recherche', 'label' => 'Signalement', 'tab' => 'Signalements', 'path' => 'atak/sse/avis/signalements',
                'load' => static function (int $t) use ($item): array {
                    if (!\App\Repositories\SseAlertRepository::ready()) {
                        return [];
                    }
                    $out = [];
                    foreach ((new \App\Repositories\SseAlertRepository())->listReports($t, 'new', self::LIMIT) as $r) {
                        $out[] = $item('report:' . (int) $r['id'], (string) $r['ref'], ($r['link_doc'] === 'vigilance' ? 'Fiche de vigilance' : 'Avis de recherche') . ' · ' . mb_substr((string) $r['body'], 0, 80), (string) $r['created_at'], 'atak/sse/avis/signalements', $r['certainty'] === 'high' ? 1 : 0, ['reason' => 'Signalement reçu, pas encore pris en compte.']);
                    }

                    return $out;
                }],
            ['key' => 'propositions', 'nav' => 'avis-recherche', 'label' => 'Proposition d’alerte', 'tab' => 'Propositions', 'path' => 'atak/sse/avis/signalements?onglet=a-valider',
                'load' => static function (int $t) use ($item): array {
                    if (!\App\Repositories\SseAlertRepository::ready()) {
                        return [];
                    }
                    $out = [];
                    foreach ((new \App\Repositories\SseAlertRepository())->listProposals($t, 'pending', self::LIMIT) as $p) {
                        $subject = (string) ($p['payload']['subject'] ?? '') !== '' ? (string) $p['payload']['subject'] : 'Sujet inconnu';
                        $out[] = $item('proposal:' . (int) $p['id'], (string) $p['ref'], ($p['doc'] === 'vigilance' ? 'Fiche de vigilance' : 'Avis de recherche') . ' · ' . $subject, (string) $p['created_at'], 'atak/sse/avis/signalements?onglet=a-valider', 1, ['reason' => 'Alerte proposée, en attente d’un responsable.']);
                    }

                    return $out;
                }],
            ['key' => 'suggestions', 'nav' => 'rapprochements', 'label' => 'Propositions de Cerbère-Validation', 'tab' => 'Suggestions', 'path' => 'atak/sse/rapprochements',
                'load' => static function (int $t) use ($item): array {
                    $out = [];
                    foreach ((new SseSuggestionQueueRepository())->listSuggestions($t, ['limit' => self::LIMIT]) as $s) {
                        $kind = (string) ($s['kind'] ?? '');
                        $out[] = $item('suggestion:' . (int) $s['id'], (string) ($s['title'] ?? 'Suggestion'), (string) (SseSuggestionQueueRepository::KINDS[$kind] ?? ''), (string) ($s['created_at'] ?? $s['updated_at'] ?? ''), 'atak/sse/rapprochements', (string) ($s['confidence'] ?? '') === 'confirme_candidat' ? 1 : 0, [
                            'type_key' => 'suggestion:' . $kind, 'type_label' => (string) (SseSuggestionQueueRepository::KINDS[$kind] ?? 'Suggestion'),
                            'subject_type' => ((int) ($s['case_id'] ?? 0)) > 0 ? 'case' : '', 'subject_id' => (int) ($s['case_id'] ?? 0), 'reason' => self::reasonOf((string) ($s['reason'] ?? '')),
                        ]);
                    }

                    return $out;
                }],
            ['key' => 'anomalies', 'nav' => 'anomalies', 'label' => 'Anomalies', 'tab' => 'Anomalies', 'path' => 'atak/sse/anomalies',
                'load' => static function (int $t) use ($item): array {
                    $out = [];
                    foreach ((new SseSuggestionQueueRepository())->listOpenSignalsDeduped($t, self::LIMIT) as $s) {
                        $sev = (string) ($s['severity'] ?? '');
                        $type = (string) ($s['signal_type'] ?? '');
                        $out[] = $item('signal:' . (int) $s['id'], (string) ($s['title'] ?? 'Signal'), (string) ($s['signal_type_label'] ?? ''), (string) ($s['created_at'] ?? ''), 'atak/sse/anomalies', $sev === 'critical' ? 2 : ($sev === 'high' ? 1 : 0), [
                            'type_key' => 'signal:' . $type . ($type === 'gap_auto' ? ':' . SseSignalIdentity::variant($type, (string) ($s['title'] ?? ''), null) : ''),
                            'type_label' => SseSignalIdentity::shortTitle($type, (string) ($s['title'] ?? 'Signal')),
                            'severity' => in_array($sev, ['critical', 'high', 'medium', 'info'], true) ? $sev : 'info',
                            'subject_type' => ((int) ($s['case_id'] ?? 0)) > 0 ? 'case' : '', 'subject_id' => (int) ($s['case_id'] ?? 0),
                            'reason' => self::reasonOf((string) ($s['detail'] ?? '')), 'merged' => (int) ($s['duplicates'] ?? 0),
                        ]);
                    }

                    return $out;
                }],
            ['key' => 'interet', 'nav' => 'interet', 'label' => 'Dossiers d’intérêt', 'tab' => 'Intérêt', 'path' => 'atak/sse/interet',
                'load' => static function (int $t) use ($item, $userId): array {
                    $repo = new SseInterestCaseRepository();
                    $bypass = SseAccessPolicy::sessionAllows('dossiers', SseAccessCatalog::LEVEL_MANAGE, ['tenant_id' => $t]);
                    $out = [];
                    foreach (['signalement_recu', 'a_qualifier', 'en_validation'] as $status) {
                        foreach ($repo->listVisibleForUser($t, $userId > 0 ? $userId : null, $bypass, ['status' => $status]) as $c) {
                            $lvl = (string) ($c['interest_level'] ?? '');
                            $out[] = $item('interest:' . (int) $c['id'], (string) ($c['temporary_designation'] ?? $c['reference_code'] ?? 'Dossier'), (string) ($c['status_label'] ?? ''), (string) ($c['updated_at'] ?? $c['created_at'] ?? ''), 'atak/sse/interet/' . (int) $c['id'], $lvl === 'critique' ? 2 : ($lvl === 'prioritaire' ? 1 : 0));
                        }
                    }

                    return array_slice($out, 0, self::LIMIT);
                }],
            ['key' => 'validation', 'nav' => 'validation', 'label' => 'Files de validation', 'tab' => 'Validation', 'path' => 'atak/sse/validation',
                'load' => static function (int $t) use ($item): array {
                    $out = [];
                    foreach ((new SseDocumentRepository())->listForTenant($t, ['status' => 'en_relecture']) as $d) {
                        $out[] = $item('document:' . (int) $d['id'], (string) ($d['title'] ?? 'Document'), (string) ($d['reference_code'] ?? ''), (string) ($d['updated_at'] ?? $d['created_at'] ?? ''), 'atak/sse/documents/' . (int) $d['id']);
                    }

                    return array_slice($out, 0, self::LIMIT);
                }],
            ['key' => 'avis', 'nav' => 'avis-recherche', 'label' => 'Avis de recherche', 'tab' => 'Avis', 'path' => 'atak/sse/avis',
                'load' => static function (int $t) use ($item): array {
                    if (!SseWantedNoticeRepository::ready()) {
                        return [];
                    }
                    $out = [];
                    $now = time();
                    foreach ((new SseWantedNoticeRepository())->list($t, ['status' => 'active', 'limit' => 300]) as $n) {
                        $exp = (string) ($n['expires_at'] ?? '');
                        $ts = $exp !== '' ? strtotime($exp . ' UTC') : false;
                        if ($ts === false || $ts - $now > 3 * 86400) {
                            continue;
                        }
                        $out[] = $item('notice:' . (int) $n['id'], (string) ($n['ref'] ?? 'Avis'), 'Expire ' . gmdate('d/m H:i', $ts) . 'Z', (string) ($n['created_at'] ?? gmdate('Y-m-d H:i:s', $now)), 'atak/sse/avis', $ts - $now < 86400 ? 2 : 1, [
                            'subject_type' => 'identity', 'subject_id' => (int) ($n['identity_id'] ?? 0), 'reason' => 'Avis de recherche proche de son expiration.',
                        ]);
                    }

                    return array_slice($out, 0, self::LIMIT);
                }],
            ['key' => 'images', 'nav' => 'fichiers-acces-images', 'label' => 'Accès aux images', 'tab' => 'Images', 'path' => 'atak/sse/fichiers/acces-images',
                'load' => static function (int $t) use ($item, $userId): array {
                    if ($userId < 1) {
                        return [];
                    }
                    $out = [];
                    foreach (SseMemberImageGate::forSession()->decidableRequests($t, $userId) as $r) {
                        $out[] = $item('image-request:' . (int) $r['id'], 'Demande d’accès aux images', '', (string) ($r['created_at'] ?? $r['requested_at'] ?? ''), 'atak/sse/fichiers/acces-images');
                    }

                    return array_slice($out, 0, self::LIMIT);
                }],
        ];
    }
}
