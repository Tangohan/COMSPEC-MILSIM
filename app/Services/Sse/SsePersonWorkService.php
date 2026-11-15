<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseAuditLogRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SsePersonWorkRepository;
use App\Repositories\SsePortalSettingsRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Support\SsePersonWork as W;
use App\Support\SseUsClassification;

/**
 * Page « Personnes » : liste de travail (filtres, onglets, tri, compteurs) et actions (classer, verrouiller, clore, rouvrir).
 *
 * ## Règles
 *
 *  - Lire : droit « lire » de la section « fichiers » ; une fiche verrouillée (manuellement ou par la règle « au-delà du niveau X »
 *    de la communauté) n'est lue que par une habilitation au moins égale à sa classification, sinon la ligne s'appelle
 *    « Élément restreint » (ni nom, ni lien, ni photo, ni correspondance à la recherche). Une fiche liée à l'identité d'un MEMBRE
 *    suit en plus SseMemberImageGate (titulaire, supérieur, habilitation, dérogation) : sans accord, elle est restreinte.
 *  - Écrire (classer, verrouiller, clore, rouvrir) : compte identifié + droit « écrire » + fiche lisible + habilitation au moins égale
 *    à la classification de la fiche. Une classification posée ne dépasse jamais l'habilitation de celui qui la pose.
 *  - Chaque changement est journalisé (journal d'audit SSE) avec son motif ; rien n'est supprimé.
 *  - Clore une personne sous avis de recherche actif est refusé, sauf « lever l'avis et clore » par qui peut lever les avis.
 */
final class SsePersonWorkService
{
    private ?SseMemberImageGate $gate;

    /** @var \Closure(): int */
    private \Closure $clock;

    public function __construct(
        private ?SsePersonRepository $persons = null,
        private ?SsePersonWorkRepository $work = null,
        private ?SsePortalSettingsRepository $settings = null,
        private ?SseAuditLogRepository $audit = null,
        ?SseMemberImageGate $gate = null,
        ?\Closure $clock = null,
    ) {
        $this->persons ??= new SsePersonRepository();
        $this->work ??= new SsePersonWorkRepository();
        $this->settings ??= new SsePortalSettingsRepository();
        $this->gate = $gate;
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * Écrans de travail caviardés si la communauté l'a décidé (même règle que l'ancienne page : rabattus sur l'habilitation).
     *
     * @param list<array<string, mixed>> $persons
     * @return list<array<string, mixed>>
     */
    private function redact(array $persons, SsePersonViewer $viewer): array
    {
        try {
            return $persons === [] ? [] : (new SseClearanceService())->redactPeopleForScreens($persons, $viewer->tenantId);
        } catch (\Throwable) {
            return $persons;
        }
    }

    private function gate(): SseMemberImageGate
    {
        return $this->gate ??= SseMemberImageGate::forSession();
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s', ($this->clock)());
    }

    private function audit(): SseAuditLogRepository
    {
        return $this->audit ??= new SseAuditLogRepository();
    }

    /** Niveau au-delà duquel une fiche classifiée est verrouillée d'office (code), ou null si la règle est éteinte. */
    public function autoLockLevel(int $tenantId): ?string
    {
        return W::cleanClassification($this->settings->get($tenantId, W::SETTING_AUTO_LOCK, ''));
    }

    public function setAutoLockLevel(SsePersonViewer $viewer, ?string $level): array
    {
        if (!$viewer->canManage || $viewer->tenantId < 1) {
            return $this->fail('Droit manquant : gérer la section « Fichiers et sujets ».');
        }
        $level = W::cleanClassification($level);
        // Une règle plus haute que son propre niveau ne se pose pas : elle verrouillerait des fiches qu'on ne pourrait plus rouvrir.
        $before = $this->autoLockLevel($viewer->tenantId);
        foreach ([$before, $level] as $touched) {
            if ($touched !== null && W::rank($touched) > $viewer->rank()) {
                return $this->fail('Cette règle touche un niveau au-dessus de votre habilitation.');
            }
        }
        if (!$this->settings->set($viewer->tenantId, W::SETTING_AUTO_LOCK, $level ?? '', $viewer->userId)) {
            return $this->fail('Le réglage n’a pas pu être enregistré.');
        }
        $this->log($viewer, 'person.autolock_rule', 0, null, ['level' => $before], ['level' => $level]);

        return ['ok' => true];
    }

    // ------------------------------------------------------------------ Liste

    /**
     * @param array<string, mixed> $rawQuery paramètres de l'adresse (nettoyés ici)
     * @return array<string, mixed>
     */
    public function listing(SsePersonViewer $viewer, array $rawQuery): array
    {
        $query = W::cleanQuery($rawQuery);
        $threshold = W::autoThreshold($this->settings->get($viewer->tenantId, W::SETTING_AUTO_LOCK, ''));
        $now = ($this->clock)();
        $ready = SsePersonWorkRepository::ready();

        $persons = $viewer->canRead ? $this->redact($this->persons->listAllForTenant($viewer->tenantId), $viewer) : [];
        $ids = array_map(static fn (array $p): int => (int) $p['id'], $persons);
        $photoIds = array_values(array_filter(array_map(static fn (array $p): int => (int) ($p['primary_photo_id'] ?? 0), $persons)));
        $photos = $this->work->primaryPhotos($viewer->tenantId, $photoIds);
        $samples = $this->work->biometricSamples($viewer->tenantId, $ids);
        $identities = $this->work->identities($viewer->tenantId, $ids);
        $links = $this->work->caseLinkCounts($viewer->tenantId, $ids);
        $views = $this->work->views($viewer->tenantId, $ids);
        $notices = $this->activeNotices($viewer, $identities);

        $viewerNames = [];
        if ($viewer->canManage) {
            $viewerIds = [];
            foreach ($views as $list) {
                foreach ($list as $v) {
                    $viewerIds[] = $v['user_id'];
                }
            }
            $viewerNames = $this->work->userLabels($viewer->tenantId, $viewerIds);
        }

        $rows = [];
        foreach ($persons as $p) {
            $rows[] = $this->row($viewer, $p, [
                'threshold' => $threshold,
                'now' => $now,
                'photo' => $photos[(int) $p['id']] ?? null,
                'samples' => $samples[(int) $p['id']] ?? [],
                'identities' => $identities[(int) $p['id']] ?? [],
                'links' => $links[(int) $p['id']] ?? 0,
                'views' => $views[(int) $p['id']] ?? [],
                'notices' => $notices,
                'names' => $viewerNames,
            ]);
        }

        $counts = $this->counts($rows, $now);
        $filtered = $this->filter($rows, $query, $now);
        $filtered = $this->sort($filtered, $query['sort']);
        $total = count($filtered);
        $pages = max(1, (int) ceil($total / W::PER_PAGE));
        $page = min($query['p'], $pages);
        $slice = array_slice($filtered, ($page - 1) * W::PER_PAGE, W::PER_PAGE);

        $nationalities = [];
        $contexts = [];
        foreach ($rows as $r) {
            if ($r['restricted']) {
                continue;
            }
            if ($r['nationality'] !== '') {
                $nationalities[$r['nationality']] = ($nationalities[$r['nationality']] ?? 0) + 1;
            }
            $contexts[$r['context_id']] = ($contexts[$r['context_id']] ?? 0) + 1;
        }
        arsort($nationalities);
        ksort($contexts);

        return [
            'query' => $query,
            'rows' => $slice,
            'counts' => $counts,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => W::PER_PAGE,
            'facets' => ['nationalities' => array_slice($nationalities, 0, 40, true), 'contexts' => $contexts],
            'ready' => $ready,
            'viewsReady' => SsePersonWorkRepository::viewsReady(),
            'truncated' => count($persons) >= W::LOAD_LIMIT,
            'autoLevel' => $threshold === null ? null : (SseUsClassification::codes()[$threshold] ?? null),
            'anyPerson' => $rows !== [],
            'filtering' => $this->isFiltering($query),
            'canClassify' => $ready && $viewer->canWrite,
        ];
    }

    /**
     * @param array<string, mixed> $query
     */
    private function isFiltering(array $query): bool
    {
        foreach (['q', 'status', 'cls', 'wanted', 'signed', 'bio', 'nat', 'period'] as $k) {
            if (($query[$k] ?? '') !== '') {
                return true;
            }
        }

        return (int) ($query['ctx'] ?? 0) > 0 || ($query['tab'] ?? 'toutes') !== 'toutes';
    }

    /**
     * @param array<string, mixed> $p fiche hydratée
     * @param array<string, mixed> $x données jointes
     * @return array<string, mixed>
     */
    private function row(SsePersonViewer $viewer, array $p, array $x): array
    {
        $id = (int) $p['id'];
        $lock = W::lockState($p, $x['threshold'], $x['now']);
        $access = $this->access($viewer, $p, $lock, $x['identities']);
        $closed = W::isClosed($p);
        $base = [
            'id' => $id,
            'restricted' => !$access['read'],
            'locked' => $lock['locked'],
            'closed' => $closed,
            'context_id' => (int) ($p['context_id'] ?? 1),
            'updated_at' => (string) ($p['updated_at'] ?? $p['created_at'] ?? ''),
            'created_at' => (string) ($p['created_at'] ?? ''),
            'classification' => null,
            'wanted' => null,
            'signed' => false,
            'nationality' => '',
            'status' => 'civil',
            'sort_name' => '',
            'my_opened_ts' => 0,
            'priority' => 0,
        ];
        if (!$access['read']) {
            return $base + ['name' => '', 'idn' => ''];
        }

        $sig = is_array($p['signature'] ?? null) ? $p['signature'] : null;
        $states = W::bioStates($x['samples']);
        $threat = null;
        $wanted = null;
        foreach ($x['identities'] as $identity) {
            $n = $x['notices'][$identity['id']] ?? null;
            if ($n !== null) {
                $wanted = [
                    'id' => (int) $n['id'],
                    'ref' => (string) $n['ref'],
                    'threat' => (string) $n['threat_level'],
                    'identity_id' => (int) $identity['id'],
                ];
                $threat = $wanted['threat'];
                break;
            }
        }
        $registry = $x['identities'][0] ?? null;

        $mine = null;
        $other = null;
        foreach ($x['views'] as $v) {
            if ($viewer->userId !== null && $v['user_id'] === $viewer->userId) {
                $mine = $v['at'];
            } elseif ($other === null || strcmp($v['at'], $other['at']) > 0) {
                $other = $v;
            }
        }
        $lastRead = $other === null ? null : [
            'at' => $other['at'],
            'who' => $viewer->canManage ? ($x['names'][$other['user_id']] ?? null) : null,
        ];

        $status = (string) $p['status'];
        $first = trim((string) $p['first_name']);
        $last = trim((string) $p['last_name']);
        $name = trim($first . ' ' . $last);
        if ($name === '') {
            $name = trim((string) ($p['alias'] ?? '')) !== '' ? (string) $p['alias'] : 'Personne sans nom';
        }

        return array_merge($base, [
            'restricted' => false,
            'idn' => 'IDN-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT),
            'name' => $name,
            'alias' => trim((string) ($p['alias'] ?? '')),
            'initials' => $this->initials($first, $last, (string) ($p['alias'] ?? '')),
            'photo' => $x['photo'],
            'status' => $status,
            'status_label' => (string) $p['status_label'],
            'status_tone' => W::STATUS_TONES[$status] ?? 'ok',
            'classification' => $p['classification'],
            'lock' => $lock,
            'lock_by' => $lock['by'] !== null ? ($x['names'][$lock['by']] ?? null) : null,
            'close_reason' => $closed ? (string) $p['close_reason'] : null,
            'closed_at' => $closed ? (string) $p['closed_at'] : null,
            'wanted' => $wanted,
            'registry_id' => $registry['id'] ?? null,
            'signed' => $sig !== null,
            'sig' => $sig === null ? null : [
                'callsign' => (string) ($sig['callsign'] ?? ''),
                'terminal' => (string) ($sig['terminal_uid'] ?? ''),
                'at' => (string) ($sig['signed_at'] ?? ''),
            ],
            'bio' => $states,
            'bio_complete' => W::bioComplete($states),
            'nationality' => trim((string) ($p['nationality'] ?? '')),
            'circumstances' => (string) ($p['circumstances_label'] ?? ''),
            'quality' => $p['acquisition_quality_avg'],
            'affiliation' => trim((string) ($p['affiliation'] ?? '')),
            'grid' => trim((string) ($p['grid_reference'] ?? '')),
            'links' => (int) $x['links'] + count($x['identities']),
            'my_opened_at' => $mine,
            'my_opened_ts' => $mine !== null ? (int) strtotime($mine . ' UTC') : 0,
            'last_read' => $lastRead,
            'can_modify' => $access['write'],
            'sort_name' => mb_strtolower($last . ' ' . $first . ' ' . (string) ($p['alias'] ?? '')),
            'priority' => W::priority($p, $threat, $sig !== null),
        ]);
    }

    private function initials(string $first, string $last, string $alias): string
    {
        $a = mb_strtoupper(mb_substr($first !== '' ? $first : $alias, 0, 1));
        $b = mb_strtoupper(mb_substr($last, 0, 1));
        $s = $a . $b;

        return $s !== '' ? $s : '?';
    }

    /**
     * Lecture et écriture d'une fiche pour ce lecteur (verrouillage, identité de membre, droit d'écrire).
     *
     * @param array<string, mixed> $p
     * @param array{locked: bool, required: int} $lock
     * @param list<array{id: int, kind: string, user_id: int|null}> $identities
     * @return array{read: bool, write: bool, clearance: bool}
     */
    private function access(SsePersonViewer $viewer, array $p, array $lock, array $identities): array
    {
        if (!$viewer->canRead) {
            return ['read' => false, 'write' => false, 'clearance' => false];
        }
        $clearance = !$lock['locked'] || $viewer->rank() >= $lock['required'];
        $memberOk = true;
        $hierarchy = false;
        foreach ($identities as $identity) {
            if ($identity['user_id'] === null) {
                continue;
            }
            try {
                $state = $this->gate()->identityState($viewer->tenantId, [
                    'id' => $identity['id'], 'user_id' => $identity['user_id'], 'tenant_id' => $viewer->tenantId,
                ]);
                $decision = $this->gate()->decide($viewer->tenantId, $state, $viewer->userId);
            } catch (\Throwable) {
                $decision = ['allowed' => false, 'reason' => ''];
            }
            if (!$decision['allowed']) {
                $memberOk = false;
            } elseif (in_array($decision['reason'], [SseMemberImageGate::WHY_HOLDER, SseMemberImageGate::WHY_HIERARCHY], true)) {
                $hierarchy = true;
            }
        }
        // Une autorisation hiérarchique (titulaire ou supérieur du membre) tient lieu d'habilitation pour une fiche verrouillée.
        $read = $memberOk && ($clearance || $hierarchy);
        $touchable = W::rank(is_string($p['classification'] ?? null) ? $p['classification'] : null) <= $viewer->rank();

        return ['read' => $read, 'write' => $read && $viewer->canWrite && $touchable && SsePersonWorkRepository::ready(), 'clearance' => $clearance];
    }

    /**
     * @param array<int, list<array{id: int, kind: string, user_id: int|null}>> $identities
     * @return array<int, array<string, mixed>> avis actifs par identité visible
     */
    private function activeNotices(SsePersonViewer $viewer, array $identities): array
    {
        if (!$viewer->canRead || !SseWantedNoticeRepository::ready()) {
            return [];
        }
        $ids = [];
        foreach ($identities as $list) {
            foreach ($list as $i) {
                if ($viewer->canSeeTruth || !in_array($i['kind'], MemberIdentityPapersService::SECRET, true)) {
                    $ids[] = $i['id'];
                }
            }
        }
        $out = [];
        try {
            $repo = new SseWantedNoticeRepository();
            foreach (array_chunk($ids, 400) as $chunk) {
                $out += $repo->activeForIdentities($viewer->tenantId, $chunk);
            }
        } catch (\Throwable) {
            return [];
        }

        return $out;
    }

    // ------------------------------------------------------------------ Compteurs, filtres, tri

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, int>
     */
    private function counts(array $rows, int $now): array
    {
        $counts = array_fill_keys(W::TABS, 0);
        foreach ($rows as $r) {
            foreach (W::TABS as $tab) {
                if ($this->inTab($r, $tab, $now)) {
                    $counts[$tab]++;
                }
            }
        }

        return $counts;
    }

    /** @param array<string, mixed> $r */
    private function inTab(array $r, string $tab, int $now): bool
    {
        if ($tab === 'closes') {
            return $r['closed'];
        }
        if ($r['closed']) {
            return false;
        }
        if ($r['restricted']) {
            // Une ligne restreinte ne se range que dans « Toutes » et « Verrouillées » : rien de ce qu'elle porte n'est lisible.
            return $tab === 'toutes' || $tab === 'verrouillees';
        }

        return match ($tab) {
            'toutes' => true,
            'recherchees' => $r['wanted'] !== null,
            'non_signees' => !$r['signed'],
            'classifiees' => $r['classification'] !== null,
            'verrouillees' => $r['locked'],
            'recentes' => $this->time($r['updated_at'] !== '' ? $r['updated_at'] : $r['created_at']) >= $now - 7 * 86400
                || $r['my_opened_ts'] >= $now - 7 * 86400,
            default => false,
        };
    }

    private function time(string $stamp): int
    {
        $t = strtotime($stamp . ' UTC');

        return $t === false ? 0 : $t;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed> $query
     * @return list<array<string, mixed>>
     */
    private function filter(array $rows, array $query, int $now): array
    {
        $needle = mb_strtolower($query['q']);
        $content = $needle !== '' || $query['status'] !== '' || $query['cls'] !== '' || $query['wanted'] !== '' || $query['signed'] !== ''
            || $query['bio'] !== '' || $query['nat'] !== '' || $query['ctx'] > 0 || $query['period'] !== '';
        $since = match ($query['period']) {
            '24h' => $now - 86400,
            '7j' => $now - 7 * 86400,
            '30j' => $now - 30 * 86400,
            default => 0,
        };
        $out = [];
        foreach ($rows as $r) {
            if (!$this->inTab($r, $query['tab'], $now)) {
                continue;
            }
            if ($r['restricted']) {
                // Jamais retrouvée par une recherche ni un filtre : ce serait lire à travers le verrou.
                if (!$content) {
                    $out[] = $r;
                }
                continue;
            }
            if ($needle !== '' && !$this->matches($r, $needle)) {
                continue;
            }
            if ($query['status'] !== '' && $r['status'] !== $query['status']) {
                continue;
            }
            if ($query['cls'] === 'none' && $r['classification'] !== null) {
                continue;
            }
            if ($query['cls'] !== '' && $query['cls'] !== 'none' && $r['classification'] !== $query['cls']) {
                continue;
            }
            if ($query['wanted'] === '1' && $r['wanted'] === null || $query['wanted'] === '0' && $r['wanted'] !== null) {
                continue;
            }
            if ($query['signed'] === '1' && !$r['signed'] || $query['signed'] === '0' && $r['signed']) {
                continue;
            }
            if ($query['bio'] === 'full' && !$r['bio_complete'] || $query['bio'] === 'partial' && $r['bio_complete']) {
                continue;
            }
            if ($query['nat'] !== '' && mb_strtolower($r['nationality']) !== mb_strtolower($query['nat'])) {
                continue;
            }
            if ($query['ctx'] > 0 && $r['context_id'] !== $query['ctx']) {
                continue;
            }
            if ($since > 0 && $this->time($r['updated_at'] !== '' ? $r['updated_at'] : $r['created_at']) < $since) {
                continue;
            }
            $out[] = $r;
        }

        return $out;
    }

    /**
     * Recherche plein texte : nom, alias, IDN, nationalité, grille, affiliation ; « 123 » retrouve IDN-00123.
     *
     * @param array<string, mixed> $r
     */
    private function matches(array $r, string $needle): bool
    {
        $hay = mb_strtolower(implode(' ', [$r['name'], $r['alias'], $r['idn'], $r['nationality'], $r['grid'], $r['affiliation']]));
        if (str_contains($hay, $needle)) {
            return true;
        }
        $num = ltrim(substr((string) $r['idn'], 4), '0');

        return $num !== '' && ctype_digit($needle) && $needle === $num;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function sort(array $rows, string $sort): array
    {
        $upd = fn (array $r): int => $this->time($r['updated_at'] !== '' ? $r['updated_at'] : $r['created_at']);
        usort($rows, function (array $a, array $b) use ($sort, $upd): int {
            // Les lignes restreintes ferment la liste.
            if ($a['restricted'] !== $b['restricted']) {
                return $a['restricted'] <=> $b['restricted'];
            }
            if ($a['restricted']) {
                return $a['id'] <=> $b['id'];
            }
            $byUpdate = $upd($b) <=> $upd($a);
            $r = match ($sort) {
                'nom' => strcmp($a['sort_name'], $b['sort_name']),
                'modification' => $byUpdate,
                'priorite' => $b['priority'] <=> $a['priority'],
                default => $b['my_opened_ts'] <=> $a['my_opened_ts'],
            };

            return $r !== 0 ? $r : ($byUpdate !== 0 ? $byUpdate : $b['id'] <=> $a['id']);
        });

        return $rows;
    }

    /**
     * Parmi ces identifiants, ceux que ce lecteur peut lire (communauté, verrouillage, identité de membre). Sert aux actions qui
     * sortent des données (export, rattachement à un dossier) : une fiche restreinte n'y entre jamais.
     *
     * @param list<int> $ids
     * @return list<array<string, mixed>> fiches hydratées lisibles
     */
    public function readable(SsePersonViewer $viewer, array $ids): array
    {
        if (!$viewer->canRead) {
            return [];
        }
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0))), 0, W::BATCH_MAX);
        $threshold = W::autoThreshold($this->settings->get($viewer->tenantId, W::SETTING_AUTO_LOCK, ''));
        $identities = $this->work->identities($viewer->tenantId, $ids);
        $out = [];
        foreach ($this->persons->listByIds($viewer->tenantId, $ids) as $p) {
            $lock = W::lockState($p, $threshold, ($this->clock)());
            if ($this->access($viewer, $p, $lock, $identities[(int) $p['id']] ?? [])['read']) {
                $out[] = $p;
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ Tiroir et fiche

    /**
     * Détail d'une fiche pour le tiroir : null si elle n'existe pas dans la communauté ou n'est pas lisible (même réponse :
     * on ne distingue pas « inexistante » de « restreinte »).
     *
     * @return array<string, mixed>|null
     */
    public function detail(SsePersonViewer $viewer, int $personId): ?array
    {
        if (!$viewer->canRead || $personId < 1) {
            return null;
        }
        $person = $this->persons->findById($personId, $viewer->tenantId);
        if ($person === null) {
            return null;
        }
        $person = $this->redact([$person], $viewer)[0] ?? $person;
        $threshold = W::autoThreshold($this->settings->get($viewer->tenantId, W::SETTING_AUTO_LOCK, ''));
        $identities = $this->work->identities($viewer->tenantId, [$personId]);
        $lock = W::lockState($person, $threshold, ($this->clock)());
        $access = $this->access($viewer, $person, $lock, $identities[$personId] ?? []);
        if (!$access['read']) {
            return null;
        }
        $samples = $this->persons->listBiometricSamples($personId, $viewer->tenantId);
        $notices = $this->activeNotices($viewer, $identities);
        $views = $this->work->views($viewer->tenantId, [$personId]);
        $names = $this->work->userLabels($viewer->tenantId, array_merge(
            array_column($views[$personId] ?? [], 'user_id'),
            array_filter([(int) ($person['locked_by'] ?? 0), (int) ($person['closed_by'] ?? 0)])
        ));
        $row = $this->row($viewer, $person, [
            'threshold' => $threshold, 'now' => ($this->clock)(), 'photo' => is_array($person['primary_photo'] ?? null) ? ($person['primary_photo']['url'] ?? null) : null,
            'samples' => $samples, 'identities' => $identities[$personId] ?? [],
            'links' => $this->work->caseLinkCounts($viewer->tenantId, [$personId])[$personId] ?? 0,
            'views' => $views[$personId] ?? [], 'notices' => $notices, 'names' => $viewer->canManage ? $names : [],
        ]);
        $custody = $this->persons->custodyEventsForPersons([$personId], $viewer->tenantId)[$personId] ?? [];
        $history = [];
        try {
            $history = $this->audit()->listForTenant($viewer->tenantId, ['object_type' => 'person', 'object_id' => $personId, 'limit' => 8]);
        } catch (\Throwable) {
            $history = [];
        }

        return [
            'row' => $row,
            'person' => $person,
            'samples' => $samples,
            'custody' => $custody,
            'history' => $history,
            'close_by' => ($person['closed_by'] ?? null) !== null && $viewer->canManage ? ($names[(int) $person['closed_by']] ?? null) : null,
            'ready' => SsePersonWorkRepository::ready(),
        ];
    }

    /**
     * Enregistre l'ouverture de la fiche par ce compte (tiroir ou fiche complète). Sans effet pour un invité.
     */
    public function recordOpen(SsePersonViewer $viewer, int $personId): bool
    {
        if ($viewer->userId === null || $viewer->guest) {
            return false;
        }

        return $this->work->touchView($viewer->tenantId, $viewer->userId, $personId, $this->now(), W::VIEW_WINDOW_SECONDS);
    }

    // ------------------------------------------------------------------ Actions

    /**
     * Classe une fiche (ou retire sa classification avec $level = null).
     *
     * @return array{ok: bool, error?: string, code?: string}
     */
    public function classify(SsePersonViewer $viewer, int $personId, ?string $level, string $reason): array
    {
        $ctx = $this->writable($viewer, $personId);
        if (isset($ctx['error'])) {
            return $ctx;
        }
        $person = $ctx['person'];
        $level = W::cleanClassification($level);
        $before = $person['classification'];
        if ($level !== null && W::rank($level) > $viewer->rank()) {
            return $this->fail('Vous ne pouvez pas classer au-dessus de votre propre habilitation.', 'ceiling');
        }
        if ($level === $before) {
            return $this->fail('Cette fiche a déjà cette classification.', 'same');
        }
        $reason = $this->reason($reason);
        // Poser une première classification ne demande pas de motif ; relever, abaisser ou retirer en demande un.
        if ($before !== null && mb_strlen($reason) < W::REASON_MIN) {
            return $this->fail('Le motif est obligatoire pour relever, abaisser ou retirer une classification.', 'reason');
        }
        $lock = $ctx['lock'];
        if ($lock['manual'] && $level === null) {
            return $this->fail('Cette fiche est verrouillée : retirez d’abord le verrou avant de retirer sa classification.', 'locked');
        }
        $this->audit()->write($this->entry($viewer, 'person.classify', $personId, $reason, ['classification' => $before], ['classification' => $level]));
        if (!$this->work->setClassification($viewer->tenantId, $personId, $level)) {
            return $this->fail('La classification n’a pas pu être enregistrée.');
        }

        return ['ok' => true];
    }

    /**
     * Verrouille une fiche : lisible et modifiable seulement avec une habilitation au moins égale à sa classification. Une fiche
     * non classifiée reçoit alors $level (obligatoire dans ce cas).
     *
     * @return array{ok: bool, error?: string, code?: string}
     */
    public function lock(SsePersonViewer $viewer, int $personId, string $reason, ?string $until, ?string $level = null): array
    {
        $ctx = $this->writable($viewer, $personId);
        if (isset($ctx['error'])) {
            return $ctx;
        }
        $person = $ctx['person'];
        if ($ctx['lock']['manual']) {
            return $this->fail('Cette fiche est déjà verrouillée.', 'same');
        }
        $reason = $this->reason($reason);
        if (mb_strlen($reason) < W::REASON_MIN) {
            return $this->fail('Le motif du verrouillage est obligatoire.', 'reason');
        }
        $untilSql = null;
        if (trim((string) $until) !== '') {
            $ts = strtotime(trim((string) $until) . ' UTC');
            if ($ts === false || $ts <= ($this->clock)()) {
                return $this->fail('La date de fin doit être dans le futur.', 'until');
            }
            $untilSql = gmdate('Y-m-d H:i:s', $ts);
        }
        $classification = $person['classification'];
        if ($classification === null) {
            $classification = W::cleanClassification($level);
            if ($classification === null) {
                return $this->fail('Choisissez la classification de la fiche : elle fixe l’habilitation exigée.', 'classification');
            }
            if (W::rank($classification) > $viewer->rank()) {
                return $this->fail('Vous ne pouvez pas classer au-dessus de votre propre habilitation.', 'ceiling');
            }
        }
        $this->audit()->write($this->entry(
            $viewer,
            'person.lock',
            $personId,
            $reason,
            ['classification' => $person['classification']],
            ['classification' => $classification, 'until' => $untilSql]
        ));
        if ($person['classification'] === null && !$this->work->setClassification($viewer->tenantId, $personId, $classification)) {
            return $this->fail('La classification n’a pas pu être enregistrée.');
        }
        if (!$this->work->lock($viewer->tenantId, $personId, (int) $viewer->userId, $reason, $untilSql, $this->now())) {
            return $this->fail('Le verrouillage n’a pas pu être enregistré.');
        }

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string, code?: string} */
    public function unlock(SsePersonViewer $viewer, int $personId, string $reason): array
    {
        $ctx = $this->writable($viewer, $personId);
        if (isset($ctx['error'])) {
            return $ctx;
        }
        if (!$ctx['lock']['manual']) {
            return $this->fail($ctx['lock']['auto']
                ? 'Cette fiche est verrouillée par la règle de la communauté : abaissez sa classification ou la règle.'
                : 'Cette fiche n’est pas verrouillée.', 'same');
        }
        $reason = $this->reason($reason);
        if (mb_strlen($reason) < W::REASON_MIN) {
            return $this->fail('Le motif du déverrouillage est obligatoire.', 'reason');
        }
        $this->audit()->write($this->entry($viewer, 'person.unlock', $personId, $reason, ['locked_at' => $ctx['person']['locked_at']], []));
        if (!$this->work->unlock($viewer->tenantId, $personId)) {
            return $this->fail('Le déverrouillage n’a pas pu être enregistré.');
        }

        return ['ok' => true];
    }

    /**
     * Clôt le dossier d'une personne (jamais de suppression).
     *
     * @param bool $liftNotice lever l'avis de recherche actif avant de clore (droit de lever les avis exigé)
     * @return array{ok: bool, error?: string, code?: string}
     */
    public function close(SsePersonViewer $viewer, int $personId, string $reasonCode, string $note, bool $liftNotice = false, ?SseWantedAccess $wanted = null): array
    {
        $ctx = $this->writable($viewer, $personId);
        if (isset($ctx['error'])) {
            return $ctx;
        }
        $reasonCode = strtolower(trim($reasonCode));
        if (!isset(W::CLOSE_REASONS[$reasonCode])) {
            return $this->fail('Choisissez un motif de clôture.', 'reason');
        }
        if (W::isClosed($ctx['person'])) {
            return $this->fail('Ce dossier est déjà clos.', 'same');
        }
        $note = $this->reason($note);
        $identities = $this->work->identities($viewer->tenantId, [$personId])[$personId] ?? [];
        $notices = $this->activeNotices($viewer, [$personId => $identities]);
        $active = [];
        foreach ($identities as $i) {
            if (isset($notices[$i['id']])) {
                $active[] = $notices[$i['id']];
            }
        }
        if ($active !== []) {
            if (!$liftNotice) {
                return $this->fail('Cette personne est sous avis de recherche actif : levez l’avis avant de clore, ou choisissez « Lever l’avis et clore ».', 'wanted');
            }
            if (!$viewer->canWanted || $wanted === null) {
                return $this->fail('Vous n’avez pas le droit de lever un avis de recherche.', 'wanted_right');
            }
            $label = W::closeReasonLabel($reasonCode);
            foreach ($active as $n) {
                $res = (new SseWantedService())->lift($viewer->tenantId, (int) $n['id'], 'lifted', 'Clôture du dossier : ' . $label . ($note !== '' ? ' — ' . $note : ''), $wanted);
                if (!($res['ok'] ?? false)) {
                    return $this->fail((string) ($res['errors'][0] ?? 'L’avis n’a pas pu être levé.'), 'wanted');
                }
            }
        }
        $this->audit()->write($this->entry(
            $viewer,
            'person.close',
            $personId,
            W::closeReasonLabel($reasonCode) . ($note !== '' ? ' — ' . $note : ''),
            ['closed_at' => null],
            ['reason' => $reasonCode, 'notice_lifted' => $active !== []]
        ));
        if (!$this->work->close($viewer->tenantId, $personId, (int) $viewer->userId, $reasonCode, $this->now())) {
            return $this->fail('La clôture n’a pas pu être enregistrée.');
        }

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string, code?: string} */
    public function reopen(SsePersonViewer $viewer, int $personId, string $reason): array
    {
        $ctx = $this->writable($viewer, $personId);
        if (isset($ctx['error'])) {
            return $ctx;
        }
        if (!W::isClosed($ctx['person'])) {
            return $this->fail('Ce dossier n’est pas clos.', 'same');
        }
        $reason = $this->reason($reason);
        if (mb_strlen($reason) < W::REASON_MIN) {
            return $this->fail('Le motif de la réouverture est obligatoire.', 'reason');
        }
        $this->audit()->write($this->entry($viewer, 'person.reopen', $personId, $reason, ['close_reason' => $ctx['person']['close_reason']], []));
        if (!$this->work->reopen($viewer->tenantId, $personId)) {
            return $this->fail('La réouverture n’a pas pu être enregistrée.');
        }

        return ['ok' => true];
    }

    /**
     * Action groupée : chaque fiche est traitée seule, avec les mêmes règles qu'une action unique.
     *
     * @param list<int> $ids
     * @param array<string, mixed> $args level, reason, until, reason_code, note, lift
     * @return array{results: list<array{id: int, ok: bool, error?: string}>, done: list<int>, failed: int}
     */
    public function batch(SsePersonViewer $viewer, string $action, array $ids, array $args, ?SseWantedAccess $wanted = null): array
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0))), 0, W::BATCH_MAX);
        $results = [];
        $done = [];
        foreach ($ids as $id) {
            $r = match ($action) {
                'classify' => $this->classify($viewer, $id, (string) ($args['level'] ?? '') === '' ? null : (string) $args['level'], (string) ($args['reason'] ?? '')),
                'lock' => $this->lock($viewer, $id, (string) ($args['reason'] ?? ''), (string) ($args['until'] ?? ''), (string) ($args['level'] ?? '')),
                'unlock' => $this->unlock($viewer, $id, (string) ($args['reason'] ?? '')),
                'close' => $this->close($viewer, $id, (string) ($args['reason_code'] ?? ''), (string) ($args['note'] ?? ''), !empty($args['lift']), $wanted),
                'reopen' => $this->reopen($viewer, $id, (string) ($args['reason'] ?? 'Annulation de la clôture')),
                default => $this->fail('Action inconnue.'),
            };
            $results[] = ['id' => $id] + $r;
            if ($r['ok']) {
                $done[] = $id;
            }
        }

        return ['results' => $results, 'done' => $done, 'failed' => count($results) - count($done)];
    }

    // ------------------------------------------------------------------ Garde commune des écritures

    /**
     * Fiche de la communauté, lisible et modifiable par ce lecteur, ou l'erreur à montrer.
     *
     * @return array{person: array<string, mixed>, lock: array<string, mixed>}|array{ok: false, error: string, code?: string}
     */
    private function writable(SsePersonViewer $viewer, int $personId): array
    {
        if ($viewer->guest || $viewer->userId === null || !$viewer->canWrite) {
            return $this->fail('Droit manquant : écrire dans « Fichiers et sujets » avec un compte identifié.', 'right');
        }
        if (!SsePersonWorkRepository::ready()) {
            return $this->fail('Cette fonction n’est pas encore activée : la mise à jour de la base (étape « SSE Personnes ») n’a pas été passée.', 'unavailable');
        }
        $person = $personId > 0 ? $this->persons->findById($personId, $viewer->tenantId) : null;
        // Même réponse pour « inexistante », « d'une autre communauté » et « restreinte » : on ne confirme rien.
        $notFound = $this->fail('Cette personne est introuvable ou restreinte.', 'notfound');
        if ($person === null) {
            return $notFound;
        }
        $threshold = W::autoThreshold($this->settings->get($viewer->tenantId, W::SETTING_AUTO_LOCK, ''));
        $identities = $this->work->identities($viewer->tenantId, [$personId])[$personId] ?? [];
        $lock = W::lockState($person, $threshold, ($this->clock)());
        $access = $this->access($viewer, $person, $lock, $identities);
        if (!$access['read']) {
            return $notFound;
        }
        if (!$access['write']) {
            return $this->fail('Cette fiche est classifiée au-dessus de votre habilitation.', 'ceiling');
        }

        return ['person' => $person, 'lock' => $lock];
    }

    /** @return array{ok: false, error: string, code?: string} */
    private function fail(string $message, string $code = ''): array
    {
        return $code === '' ? ['ok' => false, 'error' => $message] : ['ok' => false, 'error' => $message, 'code' => $code];
    }

    private function reason(string $raw): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $raw) ?? ''), 0, 400);
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array<string, mixed>
     */
    private function entry(SsePersonViewer $viewer, string $action, int $personId, string $reason, array $before, array $after): array
    {
        return [
            'tenant_id' => $viewer->tenantId,
            'actor_user_id' => $viewer->userId,
            'actor_label' => $viewer->actor(),
            'action' => $action,
            'object_type' => 'person',
            'object_id' => $personId,
            'reason' => $reason,
            'before' => $before,
            'after' => $after,
        ];
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    private function log(SsePersonViewer $viewer, string $action, int $personId, ?string $reason, array $before, array $after): void
    {
        try {
            $this->audit()->write($this->entry($viewer, $action, $personId, (string) $reason, $before, $after));
        } catch (\Throwable $e) {
            error_log('Journal SSE personnes : ' . $e->getMessage());
        }
    }
}
