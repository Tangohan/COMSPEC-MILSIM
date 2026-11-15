<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseFieldNoteRepository;
use App\Repositories\SseIdCheckRepository;
use App\Repositories\SseIntelEventRepository;
use App\Repositories\SseInterestCaseRepository;
use App\Repositories\SseTriageStateRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Support\ShortCache;
use App\Support\SseAccessCatalog;
use App\Support\SseWantedCatalog;

/**
 * Accueil du bureau SSE : l'état du bureau en une phrase, puis une bande par sujet (à trier, dossiers, avis de recherche,
 * remontées du terrain, Cerbère, à valider). LECTURE SEULE : chaque bande interroge le dépôt ou le service de sa page, rien
 * n'est recalculé ici.
 *
 * - Droits : une bande n'existe que si sa page figure dans la navigation de la session ET si sa section est lisible
 *   (SseAccessPolicy). Une bande illisible n'est pas rendue, pas même vide.
 * - Habilitation : un dossier tenu au-dessus du plafond de lecture n'est ni compté ni nommé (SseClearanceService) ; les
 *   dossiers d'intérêt viennent du dépôt déjà filtré pour la session.
 * - Identités de membres : un avis de recherche n'est nommé que par SseTriageSubjects (SseMemberImageGate) ; aucun auteur,
 *   aucun contrôleur, aucun nom présenté à un contrôle n'est affiché.
 * - Requêtes : la boîte « À trier » et la liste des dossiers sont celles que la page charge déjà pour la barre latérale ;
 *   le reste coûte un nombre fixe de lectures (aucune lecture par élément).
 * - Une source qui échoue laisse sa bande vide et note l'erreur (« errors ») : l'accueil ne casse jamais.
 */
final class SseHomeBoard
{
    public const ITEMS = 3;
    public const WEEK = 7;
    public const CAP = 99;

    private const VALIDATION_SOURCES = ['validation', 'frm', 'avis_valider'];
    private const THREAT_RANK = ['critical' => 3, 'high' => 2, 'medium' => 1, 'low' => 0];
    private const THREAT_LABELS = ['critical' => 'Menace critique', 'high' => 'Menace élevée', 'medium' => 'Menace moyenne', 'low' => 'Menace faible'];

    /** @var list<string> */
    private array $errors = [];

    private ?int $maxRank = null;

    /** @var array{guest: bool, tenant_id: int} */
    private array $context = ['guest' => false, 'tenant_id' => 0];

    /**
     * @param (\Closure(string): bool)|null $allows la session peut-elle lire cette section ?
     * @param (\Closure(array<string, mixed>): bool)|null $caseAbove le dossier dépasse-t-il l'habilitation de la session ?
     * @param (\Closure(int): list<array<string, mixed>>)|null $interest dossiers d'intérêt lisibles par la session
     * @param (\Closure(): int)|null $clock
     */
    public function __construct(
        private ?Database $db = null,
        private ?\Closure $allows = null,
        private ?\Closure $caseAbove = null,
        private ?\Closure $interest = null,
        private ?SseTriageSubjects $subjects = null,
        private ?\Closure $clock = null,
    ) {
    }

    /**
     * Les bandes de la session, gardées 30 s comme la boîte « À trier » et périmées par la même génération (toute écriture
     * réussie du portail). Jamais gardées pour une session invitée : son périmètre tient à son code, pas à un compte.
     *
     * @param array<string, mixed> $snapshot
     * @param list<array<string, mixed>> $cases
     * @param list<string> $navIds
     * @return array{hero: array<string, mixed>, bands: list<array<string, mixed>>, errors: list<string>}
     */
    public static function forSession(int $tenantId, int $userId, array $snapshot, array $cases, array $navIds, bool $guest): array
    {
        $build = static fn (): array => (new self())->build($tenantId, $snapshot, $cases, $navIds, $guest);
        if ($guest || $userId < 1 || $tenantId < 1) {
            return $build();
        }
        $ids = $navIds;
        sort($ids);
        $key = 'sse_home:' . $tenantId . ':' . $userId . ':' . substr(sha1(implode(',', $ids)), 0, 12) . ':' . ShortCache::gen(SseTriageInbox::NS);

        return ShortCache::remember($key, SseTriageInbox::TTL, $build);
    }

    /**
     * @param array<string, mixed> $snapshot boîte « À trier » de la session (SseTriageInbox::snapshot)
     * @param list<array<string, mixed>> $cases dossiers du périmètre de la session (SseCaseRepository::listForTenant)
     * @param list<string> $navIds entrées de navigation visibles
     * @return array{hero: array<string, mixed>, bands: list<array<string, mixed>>, errors: list<string>}
     */
    public function build(int $tenantId, array $snapshot, array $cases, array $navIds, bool $guest = false): array
    {
        $this->errors = [];
        $this->context = ['guest' => $guest, 'tenant_id' => $tenantId];
        $now = $this->clock !== null ? (int) ($this->clock)() : time();
        $nav = array_fill_keys($navIds, true);
        $bands = [];
        foreach ([
            fn (): ?array => $this->triageBand($snapshot, $now),
            fn (): ?array => $this->casesBand($tenantId, $cases, $nav, $now),
            fn (): ?array => $this->wantedBand($tenantId, $nav, $now),
            fn (): ?array => $this->fieldBand($tenantId, $nav, $now, $guest),
            fn (): ?array => $this->cerbereBand($tenantId, $nav, $now),
            fn (): ?array => $this->validationBand($snapshot, $nav, $now),
        ] as $make) {
            try {
                $band = $tenantId > 0 ? $make() : null;
            } catch (\Throwable $e) {
                $this->errors[] = $e->getMessage();
                $band = null;
            }
            if ($band !== null) {
                $band['index'] = sprintf('%02d', count($bands) + 1);
                $band['mirror'] = count($bands) % 2 === 1;
                $bands[] = $band;
            }
        }

        return ['hero' => $this->hero($bands), 'bands' => $bands, 'errors' => $this->errors];
    }

    // ------------------------------------------------------------------ Phrase d'accroche

    /**
     * @param list<array<string, mixed>> $bands
     * @return array{parts: list<array{value: string, label: string, accent: string, href: string}>, link: ?array{label: string, href: string}, calm: bool}
     */
    private function hero(array $bands): array
    {
        $parts = [];
        $link = null;
        foreach ($bands as $b) {
            foreach ($b['hero'] ?? [] as $p) {
                if ((int) $p['n'] > 0) {
                    $parts[] = ['value' => self::cap((int) $p['n']), 'label' => (int) $p['n'] > 1 ? $p['many'] : $p['one'], 'accent' => (string) $b['accent'], 'href' => (string) $p['href']];
                }
            }
            if ($b['key'] === 'tri' && empty($b['empty'])) {
                $link = ['label' => 'Ouvrir « À trier »', 'href' => self::url('atak/sse/a-trier')];
            }
        }

        // Dans la phrase : ce qui attend une décision, puis les avis, puis les retards.
        $order = ['amber' => 0, 'red' => 1, 'green' => 2];
        usort($parts, static fn (array $a, array $b): int => ($order[$a['accent']] ?? 9) <=> ($order[$b['accent']] ?? 9));

        return ['parts' => array_slice($parts, 0, 3), 'link' => $link ?? ['label' => 'Ouvrir la vue opérationnelle', 'href' => self::url('atak/sse/operations')], 'calm' => $parts === []];
    }

    // ------------------------------------------------------------------ 1. À trier

    /** @param array<string, mixed> $snapshot */
    private function triageBand(array $snapshot, int $now): ?array
    {
        if (($snapshot['sources'] ?? []) === []) {
            return null;
        }
        $open = self::openItems($snapshot, null);
        $total = (int) ($snapshot['total'] ?? count($open));
        $critical = count(array_filter($open, static fn (array $i): bool => (string) ($i['severity'] ?? '') === 'critical' || (int) ($i['priority'] ?? 0) >= 2));
        $oldest = 0;
        $files = 0;
        foreach ($snapshot['sources'] as $src) {
            $oldest = max($oldest, (int) ($src['oldest_days'] ?? 0));
            $files += (int) ($src['count'] ?? 0) > 0 ? 1 : 0;
        }
        $mine = (int) ($snapshot['counts']['mine'] ?? 0);
        $href = self::url('atak/sse/a-trier');

        return [
            'key' => 'tri', 'accent' => 'amber',
            'title' => 'À trier',
            'lead' => 'Tout ce qui attend une décision, toutes files confondues, sans doublon.',
            'tag' => $total < 1 ? ['text' => 'Boîte vide', 'tone' => 'calm']
                : ($critical > 0 ? ['n' => $critical, 'text' => $critical > 1 ? 'priorités critiques' : 'priorité critique', 'tone' => 'alert']
                    : ['text' => 'Aucune priorité critique', 'tone' => 'calm']),
            'meta' => $total < 1 ? [] : array_values(array_filter([
                ['n' => $files, 'text' => $files > 1 ? 'files' : 'file'],
                $oldest > 0 ? ['n' => $oldest, 'text' => 'j au plus ancien'] : null,
            ])),
            'figure' => ['value' => self::cap($total), 'label' => $total > 1 ? 'éléments à trier' : 'élément à trier'],
            'items' => array_map(fn (array $i): array => $this->triageItem($i, $now), array_slice($open, 0, self::ITEMS)),
            'primary' => ['label' => $total < 1 ? 'Ouvrir la boîte' : 'Trier maintenant', 'href' => $href],
            'secondary' => $mine > 0 ? ['label' => 'Mes éléments', 'href' => $href . '?vue=mes'] : null,
            'empty' => $total < 1,
            'empty_text' => 'Rien à trier : aucune décision ne vous attend.',
            'hero' => [['n' => $total, 'one' => 'élément à trier', 'many' => 'éléments à trier', 'href' => $href]],
        ];
    }

    /**
     * Éléments ouverts de la boîte, déjà rangés par priorité ; $sources restreint à certaines files.
     *
     * @param array<string, mixed> $snapshot
     * @param list<string>|null $sources
     * @return list<array<string, mixed>>
     */
    private static function openItems(array $snapshot, ?array $sources): array
    {
        return array_values(array_filter((array) ($snapshot['items'] ?? []), static fn (mixed $i): bool => is_array($i)
            && (string) ($i['triage']['status'] ?? SseTriageStateRepository::OPEN) === SseTriageStateRepository::OPEN
            && ($sources === null || in_array((string) ($i['source'] ?? ''), $sources, true))));
    }

    /**
     * Une ligne de la boîte : le sujet tel que SseTriageSubjects l'a masqué, sinon le titre de l'élément ; jamais sa ligne « meta »
     * (indicatifs et noms présentés).
     *
     * @param array<string, mixed> $i
     * @return array<string, mixed>
     */
    private function triageItem(array $i, int $now): array
    {
        $subject = is_array($i['subject'] ?? null) ? $i['subject'] : null;
        $label = $subject !== null ? (string) $subject['label'] : (string) ($i['title'] ?? '');
        // Un élément dont le titre est un nom (détection, avis à valider) ne l'affiche que par son sujet, qui sait le masquer.
        if ($subject === null && in_array((string) ($i['source'] ?? ''), ['detections', 'avis_valider'], true)) {
            $label = (string) ($i['type_label'] ?? '');
        }
        $system = $subject !== null && !empty($subject['masked']);

        return [
            'label' => $label !== '' ? $label : (string) ($i['type_label'] ?? 'Élément'),
            'sub' => self::distinct((string) ($i['type_label'] ?? ''), (string) ($i['source_label'] ?? '')),
            'when' => self::age((string) ($i['at'] ?? ''), $now),
            'href' => (string) ($i['href'] ?? self::url('atak/sse/a-trier')),
            'literal' => !$system && $label !== (string) ($i['type_label'] ?? ''),
            'tone' => (string) ($i['severity'] ?? '') === 'critical' || (int) ($i['priority'] ?? 0) >= 2 ? 'alert' : '',
        ];
    }

    // ------------------------------------------------------------------ 2. Dossiers actifs

    /**
     * @param list<array<string, mixed>> $cases
     * @param array<string, bool> $nav
     */
    private function casesBand(int $tenantId, array $cases, array $nav, int $now): ?array
    {
        $showCases = isset($nav['dossiers']);
        $showInterest = isset($nav['interet']);
        if ((!$showCases && !$showInterest) || !$this->can('dossiers')) {
            return null;
        }
        $rows = [];
        if ($showCases) {
            foreach ($cases as $c) {
                if (!empty($c['is_folder']) || !in_array((string) ($c['status'] ?? ''), ['ouvert', 'en_cours'], true) || $this->above($c)) {
                    continue;
                }
                $rows[] = [
                    'label' => trim((string) ($c['reference_code'] ?? '') . ' · ' . (string) ($c['title'] ?? ''), ' ·'),
                    'sub' => array_values(array_filter(['Dossier validé', (string) ($c['status_label'] ?? '')])),
                    'at' => (string) ($c['updated_at'] ?? $c['created_at'] ?? ''), 'utc' => false,
                    'href' => self::url('atak/sse/dossiers/' . (int) ($c['id'] ?? 0)), 'late' => 0,
                ];
            }
        }
        if ($showInterest) {
            foreach ($this->interestCases($tenantId) as $c) {
                if (!empty($c['is_deleted']) || in_array((string) ($c['tab'] ?? ''), ['clos', 'archives'], true) || ($c['tab'] ?? null) === null) {
                    continue;
                }
                $late = !empty($c['is_overdue']) ? max(1, (int) floor(($now - (int) strtotime((string) $c['due_at'])) / 86400)) : 0;
                $rows[] = [
                    'label' => trim((string) ($c['reference_code'] ?? '') . ' · ' . (string) ($c['temporary_designation'] ?? ''), ' ·'),
                    'sub' => array_values(array_filter(['Dossier d’intérêt', (string) ($c['status_label'] ?? '')])),
                    'at' => (string) ($c['updated_at'] ?? $c['created_at'] ?? ''), 'utc' => false,
                    'href' => self::url('atak/sse/interet/' . (int) ($c['id'] ?? 0)), 'late' => $late,
                ];
            }
        }
        $active = count($rows);
        $late = count(array_filter($rows, static fn (array $r): bool => $r['late'] > 0));
        // Les dossiers en retard d'abord (le plus en retard en tête), puis les derniers modifiés.
        usort($rows, static fn (array $a, array $b): int => ($b['late'] <=> $a['late']) ?: strcmp($b['at'], $a['at']));
        $items = [];
        foreach (array_slice($rows, 0, self::ITEMS) as $r) {
            $items[] = [
                'label' => $r['label'], 'sub' => $r['sub'], 'href' => $r['href'], 'literal' => true,
                'when' => $r['late'] > 0 ? ['text' => 'En retard de %d j', 'n' => $r['late']] : self::age($r['at'], $now, false),
                'tone' => $r['late'] > 0 ? 'alert' : '',
            ];
        }
        $main = $showInterest && ($late > 0 || !$showCases)
            ? ['label' => $late > 0 ? 'Voir les dossiers en retard' : 'Ouvrir les dossiers d’intérêt', 'href' => self::url('atak/sse/interet') . ($late > 0 ? '?en_retard=1' : '')]
            : ['label' => 'Ouvrir les dossiers', 'href' => self::url('atak/sse/dossiers')];

        return [
            'key' => 'dossiers', 'accent' => 'green',
            'title' => 'Dossiers actifs',
            'lead' => 'Dossiers validés et dossiers d’intérêt en cours d’instruction, les retards en tête.',
            'tag' => $active < 1 ? ['text' => 'Aucun dossier ouvert', 'tone' => 'calm']
                : ($late > 0 ? ['n' => $late, 'text' => $late > 1 ? 'dossiers en retard' : 'dossier en retard', 'tone' => 'alert'] : ['text' => 'Aucun retard', 'tone' => 'calm']),
            'meta' => $rows === [] ? [] : [['text' => 'Dernière modification'], self::age($rows === [] ? '' : max(array_column($rows, 'at')), $now, false)],
            'figure' => ['value' => self::cap($active), 'label' => $active > 1 ? 'dossiers actifs' : 'dossier actif'],
            'items' => $items,
            'primary' => $main,
            'secondary' => $showCases && $showInterest ? ($main['href'] === self::url('atak/sse/dossiers')
                ? ['label' => 'Dossiers d’intérêt', 'href' => self::url('atak/sse/interet')]
                : ['label' => 'Dossiers validés', 'href' => self::url('atak/sse/dossiers')]) : null,
            'empty' => $active < 1,
            'empty_text' => 'Aucun dossier en cours : le premier s’ouvre depuis une fiche ou un signalement.',
            'hero' => [['n' => $late, 'one' => 'dossier en retard', 'many' => 'dossiers en retard', 'href' => self::url('atak/sse/interet') . '?en_retard=1']],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function interestCases(int $tenantId): array
    {
        return $this->safe(fn (): array => $this->interest !== null
            ? ($this->interest)($tenantId)
            : (new SseInterestCaseRepository($this->db))->listForSession($tenantId)) ?? [];
    }

    // ------------------------------------------------------------------ 3. Avis de recherche

    /** @param array<string, bool> $nav */
    private function wantedBand(int $tenantId, array $nav, int $now): ?array
    {
        if (!isset($nav['avis-recherche']) || !$this->can('fichiers') || !SseWantedNoticeRepository::ready()) {
            return null;
        }
        $notices = $this->safe(fn (): array => (new SseWantedNoticeRepository($this->db))->list($tenantId, ['status' => 'active', 'limit' => 300])) ?? [];
        $armed = count(array_filter($notices, static fn (array $n): bool => (string) $n['armed'] === 'yes'));
        usort($notices, static fn (array $a, array $b): int => ((self::THREAT_RANK[$b['threat_level']] ?? 0) <=> (self::THREAT_RANK[$a['threat_level']] ?? 0))
            ?: (((string) $b['armed'] === 'yes') <=> ((string) $a['armed'] === 'yes'))
            ?: ((int) $b['id'] <=> (int) $a['id']));
        $top = array_slice($notices, 0, self::ITEMS);
        // Le nom vient du service des sujets : une identité de membre reste masquée pour qui n'a pas le droit de la voir.
        $named = $top === [] ? [] : ($this->safe(fn (): array => ($this->subjects ?? new SseTriageSubjects($this->db))->enrich($tenantId, array_map(
            static fn (array $n): array => ['subject_type' => 'identity', 'subject_id' => (int) $n['identity_id']],
            $top
        ))) ?? []);
        $items = [];
        foreach ($top as $k => $n) {
            $subject = is_array($named[$k]['subject'] ?? null) ? $named[$k]['subject'] : null;
            $isArmed = (string) $n['armed'] === 'yes';
            $items[] = [
                'label' => $subject !== null ? (string) $subject['label'] : (string) $n['ref'],
                'literal' => $subject !== null && empty($subject['masked']),
                'ref' => (string) $n['ref'],
                'sub' => array_values(array_filter([
                    self::THREAT_LABELS[$n['threat_level']] ?? self::THREAT_LABELS['medium'],
                    $isArmed ? SseWantedCatalog::ARMED['yes'][0] : '',
                ])),
                'when' => self::age((string) ($n['issued_at'] ?: ($n['created_at'] ?? '')), $now),
                'href' => self::url('atak/sse/avis'),
                'tone' => $isArmed || in_array((string) $n['threat_level'], ['critical', 'high'], true) ? 'alert' : '',
            ];
        }
        $count = count($notices);

        return [
            'key' => 'avis', 'accent' => 'red',
            'title' => 'Avis de recherche',
            'lead' => 'Avis actifs de la communauté, la menace la plus haute d’abord.',
            'tag' => $count < 1 ? ['text' => 'Aucun avis actif', 'tone' => 'calm']
                : ($armed > 0 ? ['n' => $armed, 'text' => $armed > 1 ? 'armés et dangereux' : 'armé et dangereux', 'tone' => 'alert'] : ['text' => 'Aucun sujet armé', 'tone' => 'calm']),
            'meta' => $notices === [] ? [] : [['text' => 'Dernier avis posé'], self::age((string) max(array_map(static fn (array $n): string => (string) ($n['issued_at'] ?: ''), $notices)), $now)],
            'figure' => ['value' => self::cap($count), 'label' => $count > 1 ? 'avis actifs' : 'avis actif'],
            'items' => $items,
            'primary' => ['label' => 'Tous les avis de recherche', 'href' => self::url('atak/sse/avis')],
            'secondary' => isset($nav['avis-detections']) ? ['label' => 'Détections', 'href' => self::url('atak/sse/avis/detections')] : null,
            'empty' => $count < 1,
            'empty_text' => 'Aucun avis de recherche actif.',
            'hero' => [['n' => $count, 'one' => 'avis actif', 'many' => 'avis actifs', 'href' => self::url('atak/sse/avis')]],
        ];
    }

    // ------------------------------------------------------------------ 4. Remontées du terrain

    /** @param array<string, bool> $nav */
    private function fieldBand(int $tenantId, array $nav, int $now, bool $guest): ?array
    {
        $since = gmdate('Y-m-d H:i:s', $now - self::WEEK * 86400);
        $rows = [];
        $kinds = [];
        $links = [];
        if (isset($nav['transmissions']) && $this->can('dossiers')) {
            $links[] = ['label' => 'Transmissions', 'href' => self::url('atak/sse/transmissions')];
            $list = $this->safe(fn (): array => (new SseIntelEventRepository($this->db))->listForTenant($tenantId, [
                'source_systems' => SseIntelEventRepository::armaTerrainSourceSystems(), 'since' => $since, 'limit' => 100,
            ])) ?? [];
            $kinds[] = ['n' => count($list), 'text' => count($list) > 1 ? 'transmissions' : 'transmission'];
            foreach ($list as $e) {
                $summary = trim((string) ($e['summary'] ?? ''));
                $rows[] = [
                    'label' => $summary !== '' ? mb_strimwidth($summary, 0, 90, '…') : (string) ($e['event_type_label'] ?? 'Transmission'),
                    'literal' => $summary !== '',
                    'sub' => array_values(array_filter(['Transmission', $summary !== '' ? (string) ($e['event_type_label'] ?? '') : ''])),
                    'at' => (string) ($e['event_time'] ?? ''), 'href' => self::url('atak/sse/transmissions/' . (int) $e['id']),
                ];
            }
        }
        if (isset($nav['papiers-controles']) && $this->can('papiers')) {
            $links[] = ['label' => 'Contrôles reçus', 'href' => self::url('atak/sse/papiers/controles')];
            $list = array_values(array_filter(
                $this->safe(fn (): array => (new SseIdCheckRepository($this->db))->list($tenantId, ['limit' => 100])) ?? [],
                static fn (array $c): bool => (string) ($c['created_at'] ?? '') >= $since
            ));
            $kinds[] = ['n' => count($list), 'text' => count($list) > 1 ? 'contrôles' : 'contrôle'];
            foreach ($list as $c) {
                // Ni le nom présenté ni l'indicatif du contrôleur : la référence et le verdict suffisent à ouvrir le contrôle.
                $rows[] = [
                    'label' => (string) ($c['ref'] ?? 'Contrôle'), 'literal' => true,
                    'sub' => array_values(array_filter(['Contrôle d’identité', (string) ($c['verdict_label'] ?? '')])),
                    'at' => (string) ($c['created_at'] ?? ''), 'href' => self::url('atak/sse/papiers/controles/' . (int) $c['id']),
                    'tone' => in_array((string) ($c['verdict'] ?? ''), ['recherche', 'hvt'], true) ? 'alert' : '',
                ];
            }
        }
        if (isset($nav['fiches']) && $this->can('terrain')) {
            $links[] = ['label' => 'Fiches de renseignement', 'href' => self::url('atak/sse/fiches')];
            $list = $this->safe(fn (): array => (new SseFieldNoteRepository($this->db))->listForTenant($tenantId, ['since' => $since, 'limit' => 100])) ?? [];
            $list = array_values(array_filter($list, static fn (array $n): bool => (string) ($n['status'] ?? '') !== 'brouillon'));
            $kinds[] = ['n' => count($list), 'text' => count($list) > 1 ? 'fiches' : 'fiche'];
            foreach ($list as $n) {
                $title = trim((string) ($n['title'] ?? ''));
                $rows[] = [
                    'label' => $title !== '' ? $title : (string) ($n['reference_code'] ?? 'Fiche'), 'literal' => true,
                    'sub' => array_values(array_filter(['Fiche de renseignement', (string) ($n['urgency_label'] ?? '')])),
                    'at' => (string) ($n['observed_at'] ?? $n['created_at'] ?? ''), 'href' => self::url('atak/sse/fiches/' . (int) $n['id']),
                    'tone' => (string) ($n['urgency'] ?? '') === 'critique' ? 'alert' : '',
                ];
            }
        }
        if ($links === []) {
            return null;
        }
        usort($rows, static fn (array $a, array $b): int => strcmp($b['at'], $a['at']));
        $count = count($rows);
        $items = [];
        foreach (array_slice($rows, 0, self::ITEMS) as $r) {
            $items[] = ['label' => $r['label'], 'literal' => $r['literal'], 'sub' => $r['sub'], 'when' => self::age($r['at'], $now), 'href' => $r['href'], 'tone' => $r['tone'] ?? ''];
        }
        $write = !$guest && isset($nav['fiches']) ? ['label' => 'Rédiger une fiche', 'href' => self::url('atak/sse/fiches/nouvelle')] : null;

        return [
            'key' => 'terrain', 'accent' => 'blue',
            'title' => 'Remontées du terrain',
            'lead' => 'Transmissions, contrôles d’identité et fiches de renseignement des sept derniers jours.',
            'tag' => $count < 1 ? ['text' => 'Terrain silencieux', 'tone' => 'calm'] : ['text' => 'Dernière remontée', 'when' => self::age($rows[0]['at'], $now), 'tone' => 'calm'],
            'meta' => $count < 1 ? [] : array_values(array_filter($kinds, static fn (array $k): bool => $k['n'] > 0)),
            'figure' => ['value' => self::cap($count), 'label' => $count > 1 ? 'remontées en 7 jours' : 'remontée en 7 jours'],
            'items' => $items,
            'primary' => $links[0],
            'secondary' => $write ?? ($links[1] ?? null),
            'empty' => $count < 1,
            'empty_text' => 'Aucune remontée depuis sept jours.',
            'hero' => [],
        ];
    }

    // ------------------------------------------------------------------ 5. Cerbère

    /** @param array<string, bool> $nav */
    private function cerbereBand(int $tenantId, array $nav, int $now): ?array
    {
        if (!isset($nav['cerberes']) || !$this->can('analyse')) {
            return null;
        }
        $report = $this->safe(fn (): array => (new SseCerbereReport($this->db))->build($tenantId));
        if ($report === null) {
            return null;
        }
        $day = gmdate('Y-m-d H:i:s', $now - 86400);
        $rows = [];
        foreach ($report['recent'] as $r) {
            // Le motif d'un lien peut citer un nom : on n'affiche que la règle et le traitement.
            $rows[] = ['label' => self::ucfirst((string) ($r['rule_label'] !== '' ? $r['rule_label'] : $r['treatment'])), 'sub' => [(string) $r['treatment'], 'Lien posé'], 'at' => (string) $r['at'], 'href' => self::url('atak/sse/cerberes')];
        }
        foreach ($report['detections'] as $d) {
            $rows[] = ['label' => 'Avis de recherche détecté', 'sub' => ['Cerbère-Détection', (int) $d['repeat'] > 1 ? 'Détection répétée' : 'Détection'], 'at' => (string) $d['at'], 'href' => self::url(isset($nav['avis-detections']) ? 'atak/sse/avis/detections' : 'atak/sse/cerberes')];
        }
        usort($rows, static fn (array $a, array $b): int => strcmp($b['at'], $a['at']));
        $today = count(array_filter($rows, static fn (array $r): bool => $r['at'] >= $day));
        $capped = $today > 0 && $today === count($rows) && (count($report['recent']) >= 12 || count($report['detections']) >= 6);
        $total = array_sum(array_map(static fn (array $t): int => (int) $t['total'], $report['treatments']));
        $pending = (int) ($report['queue']['pending'] ?? 0);
        $items = [];
        foreach (array_slice($rows, 0, self::ITEMS) as $r) {
            $items[] = ['label' => $r['label'], 'literal' => false, 'sub' => $r['sub'], 'when' => self::age($r['at'], $now), 'href' => $r['href'], 'tone' => ''];
        }

        return [
            'key' => 'cerbere', 'accent' => 'violet',
            'title' => 'Ce que Cerbère a fait',
            'lead' => 'Liens posés, propositions préparées et avis détectés par Cerbère, sans intervention.',
            'tag' => $pending > 0 ? ['n' => $pending, 'text' => $pending > 1 ? 'propositions à valider' : 'proposition à valider', 'tone' => 'warn']
                : ['text' => $rows === [] ? 'Cerbère veille' : 'Aucune proposition en attente', 'tone' => 'calm'],
            'meta' => $total > 0 ? [['n' => $total, 'text' => $total > 1 ? 'liens posés depuis le début' : 'lien posé depuis le début']] : [],
            'figure' => ['value' => self::cap($today) . ($capped && $today <= self::CAP ? '+' : ''), 'label' => $today > 1 ? 'actions en 24 h' : 'action en 24 h'],
            'items' => $items,
            'primary' => ['label' => 'Voir les Cerbères', 'href' => self::url('atak/sse/cerberes')],
            'secondary' => isset($nav['rapprochements']) ? ['label' => 'Propositions', 'href' => self::url('atak/sse/rapprochements')] : null,
            'empty' => $rows === [],
            'empty_text' => 'Cerbère n’a encore rien eu à rattacher.',
            'hero' => [],
        ];
    }

    // ------------------------------------------------------------------ 6. À valider

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, bool> $nav
     */
    private function validationBand(array $snapshot, array $nav, int $now): ?array
    {
        $files = array_values(array_intersect(self::VALIDATION_SOURCES, array_keys((array) ($snapshot['sources'] ?? []))));
        if ($files === []) {
            return null;
        }
        $open = self::openItems($snapshot, $files);
        $count = count($open);
        $per = [];
        foreach ($files as $key) {
            $n = count(array_filter($open, static fn (array $i): bool => (string) $i['source'] === $key));
            if ($n > 0) {
                $per[] = ['n' => $n, 'text' => (string) $snapshot['sources'][$key]['label']];
            }
        }
        $main = isset($nav['validation']) ? ['label' => 'Files de validation', 'href' => self::url('atak/sse/validation')] : ['label' => 'Ouvrir « À trier »', 'href' => self::url('atak/sse/a-trier')];

        return [
            'key' => 'valider', 'accent' => 'slate',
            'title' => 'À valider',
            'lead' => 'Documents en relecture, comptes rendus de contrôle et avis proposés qui attendent une signature.',
            'tag' => $count < 1 ? ['text' => 'Rien en attente', 'tone' => 'calm'] : ['text' => 'Le plus ancien', 'when' => self::age((string) min(array_map(static fn (array $i): string => (string) ($i['at'] ?? ''), $open)), $now), 'tone' => 'warn'],
            'meta' => $per,
            'figure' => ['value' => self::cap($count), 'label' => $count > 1 ? 'validations en attente' : 'validation en attente'],
            'items' => array_map(fn (array $i): array => $this->triageItem($i, $now), array_slice($open, 0, self::ITEMS)),
            'primary' => $main,
            'secondary' => isset($nav['documents']) ? ['label' => 'Rédaction', 'href' => self::url('atak/sse/documents')] : null,
            'empty' => $count < 1,
            'empty_text' => 'Aucun document n’attend de validation.',
            'hero' => [],
        ];
    }

    // ------------------------------------------------------------------ Outils

    private function can(string $section): bool
    {
        try {
            return $this->allows !== null
                ? (bool) ($this->allows)($section)
                : SseAccessPolicy::sessionAllows($section, SseAccessCatalog::LEVEL_READ, $this->context);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Le dossier dépasse-t-il le plafond de lecture de la session ? Le plafond est lu une seule fois (aucune lecture par dossier) ;
     * la règle est celle de SseClearanceService::caseAboveClearance. Toute erreur ferme.
     *
     * @param array<string, mixed> $case
     */
    private function above(array $case): bool
    {
        try {
            if ($this->caseAbove !== null) {
                return (bool) ($this->caseAbove)($case);
            }
            $class = (string) ($case['classification'] ?? '');
            if (!isset(SseRedactionService::LEVELS[$class])) {
                return false;
            }
            $this->maxRank ??= SseRedactionService::levelRank((new SseClearanceService())->maxLevel());

            return SseRedactionService::levelRank($class) > $this->maxRank;
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @template T
     * @param \Closure(): T $read
     * @return T|null
     */
    private function safe(\Closure $read): mixed
    {
        try {
            return $read();
        } catch (\Throwable $e) {
            $this->errors[] = $e->getMessage();

            return null;
        }
    }

    private static function url(string $path): string
    {
        return function_exists('url') ? (string) url($path) : '/' . $path;
    }

    /**
     * Le type d'un élément et sa file, sans redite (« Files de validation » ne se répète pas en « Validation »).
     *
     * @return list<string>
     */
    private static function distinct(string $type, string $source): array
    {
        if ($type === '' || $source === '') {
            return array_values(array_filter([$type, $source]));
        }
        $a = mb_strtolower($type);
        $b = mb_strtolower($source);

        return str_contains($a, $b) || str_contains($b, $a) ? [$type] : [$type, $source];
    }

    private static function ucfirst(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }

    private static function cap(int $n): string
    {
        return $n > self::CAP ? self::CAP . '+' : (string) max(0, $n);
    }

    /**
     * Ancienneté lisible : texte à trou (%d) et sa valeur, pour rester traduisible.
     *
     * @return array{text: string, n?: int}
     */
    public static function age(string $at, int $now, bool $utc = true): array
    {
        $ts = $at !== '' ? strtotime($at . ($utc ? ' UTC' : '')) : false;
        if ($ts === false) {
            return ['text' => ''];
        }
        $d = max(0, $now - $ts);

        return match (true) {
            $d < 3600 => $d < 120 ? ['text' => 'À l’instant'] : ['text' => 'Il y a %d min', 'n' => intdiv($d, 60)],
            $d < 86400 => ['text' => 'Il y a %d h', 'n' => intdiv($d, 3600)],
            default => ['text' => 'Il y a %d j', 'n' => intdiv($d, 86400)],
        };
    }
}
