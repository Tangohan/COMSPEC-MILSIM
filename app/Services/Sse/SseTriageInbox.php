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
use App\Repositories\SseWantedDetectionRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Support\SseAccessCatalog;
use App\Support\ShortCache;

/**
 * Boîte « À trier » du bureau SSE : tout ce qui attend une décision humaine, agrégé depuis les files qui existent déjà
 * (aucune logique recopiée : chaque source appelle le dépôt ou le service de sa page).
 *
 * - Une source = une entrée de la navigation (nav), une section de droits (déduite de la page), un chargeur de 100 éléments au plus
 *   (au-delà, le compteur affiche « 99+ ») et un ton.
 * - Droits : une source dont la section n'est pas lisible par la session n'est ni comptée ni listée. Le service ne connaît que
 *   les sources qu'on lui autorise (la navigation visible) ; les droits de lecture sont revérifiés ici.
 * - Dédoublonnage : un élément est identifié par « type:id » ; le total compte les éléments distincts, pas la somme des files.
 * - Cache : 30 s par communauté, compte et jeu de sources autorisées ; toute écriture réussie du portail le périme (invalidateAfter).
 * - Ton d'une file : rouge si un élément est critique (priorité 2), ambre si le plus ancien a plus de 7 jours, neutre sinon.
 * Aucun stockage : rien n'est « marqué traité » ici, on ouvre l'élément dans sa page, qui garde la décision.
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

    /**
     * @param list<array{key: string, nav: string, label: string, tab: string, path: string, load: \Closure}>|null $definitions
     * @param (\Closure(string, string): bool)|null $allows (section, niveau) : la session peut-elle lire ?
     * @param (\Closure(): int)|null $clock
     */
    public function __construct(
        ?array $definitions = null,
        private ?\Closure $allows = null,
        private ?\Closure $clock = null,
    ) {
        $this->definitions = $definitions;
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
     * @return array{total: int, sources: array<string, array{key: string, nav: string, label: string, tab: string, href: string, count: int, tone: string, oldest_days: int}>, items: list<array<string, mixed>>}
     */
    public function snapshot(int $tenantId, int $userId, array $navIds): array
    {
        if ($tenantId < 1) {
            return ['total' => 0, 'sources' => [], 'items' => []];
        }
        $defs = array_values(array_filter(
            $this->defs($userId),
            fn (array $d): bool => in_array($d['nav'], $navIds, true) && $this->canRead($d['path'], $tenantId)
        ));
        if ($defs === []) {
            return ['total' => 0, 'sources' => [], 'items' => []];
        }
        $keys = array_map(static fn (array $d): string => $d['key'], $defs);
        sort($keys);
        $cacheKey = self::NS . ':' . $tenantId . ':' . $userId . ':' . substr(sha1(implode(',', $keys)), 0, 12) . ':' . ShortCache::gen(self::NS);

        return ShortCache::remember($cacheKey, self::TTL, fn (): array => $this->build($tenantId, $defs));
    }

    /**
     * @param list<array{key: string, nav: string, label: string, tab: string, path: string, load: \Closure}> $defs
     * @return array{total: int, sources: array<string, array<string, mixed>>, items: list<array<string, mixed>>}
     */
    private function build(int $tenantId, array $defs): array
    {
        $now = $this->now();
        $sources = [];
        $distinct = [];
        foreach ($defs as $d) {
            try {
                $items = ($d['load'])($tenantId);
            } catch (\Throwable) {
                $items = [];
            }
            $items = array_slice(array_values(array_filter($items, 'is_array')), 0, self::LIMIT);
            $oldest = 0;
            foreach ($items as $it) {
                $oldest = max($oldest, self::ageDays((string) ($it['at'] ?? ''), $now));
                $it['source'] = $d['key'];
                $it['source_label'] = $d['tab'];
                $k = (string) ($it['key'] ?? '');
                if ($k === '') {
                    $k = $d['key'] . ':' . count($distinct);
                }
                // Le premier détenteur garde l'élément ; la priorité la plus haute l'emporte.
                if (!isset($distinct[$k]) || (int) ($it['priority'] ?? 0) > (int) ($distinct[$k]['priority'] ?? 0)) {
                    $distinct[$k] = $it + ['key' => $k];
                }
            }
            $sources[$d['key']] = [
                'key' => $d['key'],
                'nav' => $d['nav'],
                'label' => $d['label'],
                'tab' => $d['tab'],
                'href' => function_exists('url') ? (string) url($d['path']) : $d['path'],
                'count' => count($items),
                'tone' => self::tone($items, $now),
                'oldest_days' => $oldest,
            ];
        }
        $list = array_values($distinct);
        usort($list, static function (array $a, array $b): int {
            return ((int) ($b['priority'] ?? 0) <=> (int) ($a['priority'] ?? 0))
                ?: strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? ''));
        });

        return ['total' => count($list), 'sources' => $sources, 'items' => $list];
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

    /**
     * Les files réelles du bureau, chacune lue par le dépôt de sa page.
     *
     * @return list<array{key: string, nav: string, label: string, tab: string, path: string, load: \Closure}>
     */
    public static function defaultDefinitions(int $userId): array
    {
        $item = static fn (string $key, string $title, string $meta, ?string $at, string $path, int $priority = 0): array => [
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
                        $out[] = $item('detection:' . (int) $d['id'], (string) ($d['identity_name'] !== '' ? $d['identity_name'] : 'Identité #' . (int) $d['identity_id']), (string) $d['notice_ref'], (string) $d['created_at'], 'atak/sse/avis/detections', 1);
                    }

                    return $out;
                }],
            ['key' => 'suggestions', 'nav' => 'rapprochements', 'label' => 'Suggestions du moteur', 'tab' => 'Suggestions', 'path' => 'atak/sse/rapprochements',
                'load' => static function (int $t) use ($item): array {
                    $out = [];
                    foreach ((new SseSuggestionQueueRepository())->listSuggestions($t, ['limit' => self::LIMIT]) as $s) {
                        $out[] = $item('suggestion:' . (int) $s['id'], (string) ($s['title'] ?? 'Suggestion'), (string) (SseSuggestionQueueRepository::KINDS[(string) ($s['kind'] ?? '')] ?? ''), (string) ($s['created_at'] ?? $s['updated_at'] ?? ''), 'atak/sse/rapprochements', (string) ($s['confidence'] ?? '') === 'confirme_candidat' ? 1 : 0);
                    }

                    return $out;
                }],
            ['key' => 'anomalies', 'nav' => 'anomalies', 'label' => 'Anomalies', 'tab' => 'Anomalies', 'path' => 'atak/sse/anomalies',
                'load' => static function (int $t) use ($item): array {
                    $out = [];
                    foreach ((new SseSuggestionQueueRepository())->listSignals($t, ['limit' => self::LIMIT]) as $s) {
                        $sev = (string) ($s['severity'] ?? '');
                        $out[] = $item('signal:' . (int) $s['id'], (string) ($s['title'] ?? 'Signal'), (string) ($s['signal_type_label'] ?? ''), (string) ($s['created_at'] ?? ''), 'atak/sse/anomalies', $sev === 'critical' ? 2 : ($sev === 'high' ? 1 : 0));
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
                        $out[] = $item('notice:' . (int) $n['id'], (string) ($n['ref'] ?? 'Avis'), 'Expire ' . gmdate('d/m H:i', $ts) . 'Z', (string) ($n['created_at'] ?? gmdate('Y-m-d H:i:s', $now)), 'atak/sse/avis', $ts - $now < 86400 ? 2 : 1);
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
