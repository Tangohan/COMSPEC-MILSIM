<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseFileRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseFileQueryService;
use App\Support\SseFileCatalog;
use App\Support\SseIdentityCatalog;

/**
 * Portail SSE — RENS › Identités & documents › Passage aux fichiers.
 *
 * Consultation des fichiers de la communauté façon terminal de patrouille (QW personne, QV véhicule, QA document,
 * QB biométrie), journal des consultations du site et du jeu, mentions aux fichiers et fiches SEEK II.
 *
 * Consulter : accès au portail SSE (comme le registre). Poser ou lever une mention, déclarer un papier volé ou perdu :
 * gestion des dossiers SSE (atak.sse.case.manage, atak.sse.grant ou admin.access), jamais en invité — mêmes droits que
 * les statuts d'un papier. Données fictives du registre de la communauté uniquement ; jamais la vérité de jeu.
 */
final class SseFileController
{
    private const BASE = 'atak/sse/fichiers';

    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SseFileQueryService $service = null,
        private ?SseFileRepository $files = null,
        private ?SseIdentityRepository $identities = null,
        private ?SseIdDocumentRepository $documents = null,
    ) {
        $this->access ??= new SseAccessCodeService();
    }

    /** Onglets du terminal (code court, libellé). */
    public const TABS = ['qw' => 'Personne', 'qv' => 'Véhicule', 'qa' => 'Document', 'qb' => 'Biométrie', 'qd' => 'Appareil ATAK'];

    /**
     * Terminal : case unique et filtres de signalement (QW), véhicule (QV), document (QA), biométrie (QB), appareils
     * ATAK (QD). Résultats classés avec la confiance ; chaque nouvelle requête (avec son motif) est inscrite au journal.
     */
    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $ctx = new SseFilesContext($this->access);
        $settings = $ctx->settings();
        $legacy = strtolower($request->queryString('type'));
        $tab = strtolower($request->queryString('t', $legacy !== '' ? $legacy : 'qw'));
        $tab = isset(self::TABS[$tab]) ? $tab : 'qw';
        $purpose = strtoupper($request->queryString('motif'));
        $purpose = isset(\App\Support\SseFileSubjectCatalog::PURPOSES[$purpose]) ? $purpose : '';
        $p = \App\Services\Sse\SseFileSearchService::cleanParams($_GET);
        $page = max(1, (int) $request->queryString('page', '1'));
        $data = [
            'title' => 'Passage aux fichiers',
            'tab' => $tab,
            'params' => $p,
            'purpose' => $purpose,
            'purposeRequired' => !empty($settings['purpose_required']),
            'terminalCountry' => (string) $settings['default_country'],
            'search' => null,
            'vehicles' => null,
            'result' => null,
            'needPurpose' => false,
            'criteria' => self::criteriaFrom($request),
            'vehicleQuery' => ['plate' => mb_substr(trim($request->queryString('plate')), 0, 20), 'text' => mb_substr(trim($request->queryString('desc')), 0, 60)],
            'recent' => $this->safeList(fn (): array => $this->files()->listQueries($tenantId, ['limit' => 6])),
            'savedSearches' => [],
            'recentSearches' => [],
            'watches' => [],
            'devices' => [],
        ];
        $uid = $this->userId();
        $wantsSearch = match ($tab) {
            'qw' => \App\Services\Sse\SseFileSearchService::hasCriteria($p),
            'qv' => $data['vehicleQuery']['plate'] !== '' || $data['vehicleQuery']['text'] !== '',
            'qa', 'qb' => trim($data['criteria']['number']) !== '',
            default => false,
        };
        if ($wantsSearch && $purpose === '' && $data['purposeRequired']) {
            $data['needPurpose'] = true;
        } elseif ($wantsSearch) {
            try {
                if ($tab === 'qw') {
                    $search = new \App\Services\Sse\SseFileSearchService();
                    $data['search'] = $search->search($tenantId, $p, $page);
                    $this->logSearch($tenantId, $p, $purpose, (int) $data['search']['total'], $page);
                    if ($uid !== null) {
                        (new \App\Repositories\SseFileRecordRepository())->rememberSearch($tenantId, $uid, $p);
                    }
                } elseif ($tab === 'qv') {
                    $data['vehicles'] = (new \App\Services\Sse\SseFileSearchService())->vehicles($tenantId, $data['vehicleQuery']['plate'], $data['vehicleQuery']['text']);
                    $this->logSearch($tenantId, ['plate' => $data['vehicleQuery']['plate'], 'q' => $data['vehicleQuery']['text']], $purpose, count($data['vehicles']), 1, 'QV');
                } else {
                    $data['result'] = $this->service()->run($tenantId, strtoupper($tab), $data['criteria'], [
                        'source' => 'web', 'operator_user_id' => $uid, 'operator_label' => $this->operatorLabel(),
                    ]);
                    $this->setPurpose($tenantId, (int) $data['result']['query_id'], $purpose);
                }
            } catch (\Throwable $e) {
                error_log('Passage aux fichiers : consultation impossible : ' . $e->getMessage());
                Session::flash('error', 'Les fichiers ne répondent pas pour le moment. Réessayez plus tard.');
            }
        }
        if ($uid !== null) {
            try {
                $records = new \App\Repositories\SseFileRecordRepository();
                $data['savedSearches'] = $records->searches($tenantId, $uid, true, 8);
                $data['recentSearches'] = $records->searches($tenantId, $uid, false, 6);
                $data['watches'] = (new \App\Services\Sse\SseFileXrefService($records))->watches($tenantId, $uid);
                if ($tab === 'qd') {
                    $data['devices'] = (new \App\Services\Sse\SsePhoneExtractionService($records))->list($tenantId, ['page' => 1], $ctx->canAdmin())['rows'];
                }
            } catch (\Throwable $e) {
                error_log('Passage aux fichiers : listes du membre illisibles : ' . $e->getMessage());
            }
        }

        return $ctx->render('atak.sse.files.index', $data + ['replayUrl' => self::replayUrl(strtoupper($tab === 'qd' ? 'qa' : $tab), $data['criteria'])]);
    }

    /**
     * Une recherche de fiches inscrite au journal (une fois par jeu de critères, pas à chaque page ni à chaque filtre
     * retapé à l'identique).
     *
     * @param array<string, string> $p
     */
    private function logSearch(int $tenantId, array $p, string $purpose, int $total, int $page, string $type = 'QW'): void
    {
        $criteria = array_filter($p, static fn (string $v): bool => $v !== '');
        $hash = sha1($type . json_encode($criteria));
        if ($page > 1 || Session::get('sse_files_last_search') === $hash) {
            return;
        }
        Session::set('sse_files_last_search', $hash);
        try {
            $logged = $this->files()->logQuery($tenantId, [
                'query_type' => $type,
                'criteria' => $criteria,
                'source' => 'web',
                'operator_user_id' => $this->userId(),
                'operator_label' => $this->operatorLabel(),
                'hit' => $total > 0,
                'verdict' => $total > 0 ? null : 'inconnu',
                'codes' => ['matches:' . $total],
            ]);
            $this->setPurpose($tenantId, $logged['id'], $purpose);
        } catch (\Throwable $e) {
            error_log('Passage aux fichiers : recherche non journalisée : ' . $e->getMessage());
        }
    }

    private function setPurpose(int $tenantId, int $queryId, string $purpose): void
    {
        if ($queryId < 1 || $purpose === '') {
            return;
        }
        try {
            $this->files()->db()->execute('UPDATE sse_file_queries SET purpose = :p WHERE id = :id AND tenant_id = :t', ['p' => $purpose, 'id' => $queryId, 't' => $tenantId]);
        } catch (\Throwable) {
            // Colonne purpose absente (étape 4340 pas encore passée) : le motif n'est pas gardé.
        }
    }

    /**
     * Ancienne adresse de consultation (liens « Rejouer » du journal, du registre et du jeu) : renvoie au terminal avec
     * les mêmes critères. La consultation est faite (et journalisée) par le terminal, avec son motif.
     */
    public function query(Request $request, array $params = []): Response
    {
        $type = strtoupper($request->queryString('type', 'QW'));
        $type = isset(SseFileCatalog::QUERY_TYPES[$type]) ? $type : 'QW';
        $criteria = array_filter(self::criteriaFrom($request), static fn (string $v): bool => $v !== '');
        $q = ['t' => strtolower($type)];
        if ($type === 'QW') {
            $q += array_intersect_key($criteria, ['last' => 1, 'first' => 1, 'dob' => 1]);
        } elseif ($type === 'QV') {
            $q['plate'] = $criteria['number'] ?? '';
        } else {
            $q += $criteria;
        }
        $motif = strtoupper($request->queryString('motif'));
        if ($motif !== '') {
            $q['motif'] = $motif;
        }

        return Response::redirect(url(self::BASE) . '?' . http_build_query($q));
    }

    /** Journal des consultations (site et jeu), filtrable par source, type et HIT. */
    public function journal(Request $request, array $params = []): Response
    {
        $filters = [
            'source' => $request->queryString('source'),
            'type' => strtoupper($request->queryString('type')),
            'hit' => $request->queryString('hit'),
            'q' => mb_substr(trim($request->queryString('q')), 0, 80),
        ];
        $rows = $this->safeList(fn (): array => $this->files()->listQueries($this->tenantId(), $filters + ['limit' => 300]));
        $names = $this->userNames(array_map(static fn (array $r): int => (int) ($r['operator_user_id'] ?? 0), $rows));
        foreach ($rows as $k => $r) {
            $rows[$k]['operator'] = self::operatorOf($r, $names);
            $rows[$k]['replay_url'] = self::replayUrl((string) $r['query_type'], self::replayCriteria($r['criteria']));
        }

        return $this->render('atak.sse.files.journal', [
            'title' => 'Journal des consultations',
            'rows' => $rows,
            'filters' => $filters,
        ]);
    }

    /** Fiches biométriques SEEK II remontées du jeu. */
    public function seekIndex(Request $request, array $params = []): Response
    {
        $filters = [
            'verdict' => $request->queryString('verdict'),
            'q' => mb_substr(trim($request->queryString('q')), 0, 80),
        ];

        return $this->render('atak.sse.files.seek', [
            'title' => 'Fiches SEEK II',
            'records' => $this->safeList(fn (): array => $this->files()->listSeek($this->tenantId(), $filters)),
            'filters' => $filters,
        ]);
    }

    /** Mentions aux fichiers (actives d'abord), formulaire de pose par référence. */
    public function mentionsIndex(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $active = $request->queryString('etat') !== 'toutes';
        $mentions = $this->safeList(fn (): array => $this->files()->listMentions($tenantId, ['active' => $active, 'limit' => 300]));
        $names = $this->userNames(array_merge(
            array_map(static fn (array $m): int => (int) ($m['created_by_user_id'] ?? 0), $mentions),
            array_map(static fn (array $m): int => (int) ($m['lifted_by_user_id'] ?? 0), $mentions)
        ));
        foreach ($mentions as $k => $m) {
            $mentions[$k]['target'] = $this->targetOf($tenantId, $m);
            $mentions[$k]['created_by'] = $names[(int) ($m['created_by_user_id'] ?? 0)] ?? '';
            $mentions[$k]['lifted_by'] = $names[(int) ($m['lifted_by_user_id'] ?? 0)] ?? '';
        }

        return $this->render('atak.sse.files.mentions', [
            'title' => 'Mentions aux fichiers',
            'mentions' => $mentions,
            'onlyActive' => $active,
            'prefillRef' => mb_substr(trim($request->queryString('ref')), 0, 40),
        ]);
    }

    /** Poser une mention sur une identité, un papier ou une fiche SEEK II (responsables). */
    public function mentionStore(Request $request, array $params = []): Response
    {
        $back = self::safeBack($request->inputString('back'));
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied($back);
        }
        $tenantId = $this->tenantId();
        $mention = $request->inputString('mention');
        $target = $this->resolveTarget($tenantId, $request->inputString('target_type'), (int) $request->input('target_id', 0), $request->inputString('target_ref'));
        if (!isset(SseFileCatalog::MENTIONS[$mention]) || $target === null) {
            Session::flash('error', 'Mention ou fiche introuvable : rien n’a été enregistré.');

            return Response::redirect($back);
        }
        $id = $this->files()->createMention($tenantId, [
            'mention' => $mention,
            $target['column'] => $target['id'],
            'note' => $request->inputString('note'),
            'reference' => $request->inputString('reference'),
        ], $this->userId());
        $this->audit($tenantId, 'file_mention.create', 'sse_file_mention', $id, ['mention' => $mention, 'target' => $target['ref']]);
        Session::flash('success', 'Mention posée. Elle apparaît en jeu dès la prochaine consultation.');

        return Response::redirect($back);
    }

    /** Lever une mention (date et auteur gardés). */
    public function mentionLift(Request $request, array $params = []): Response
    {
        $back = self::safeBack($request->inputString('back'));
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied($back);
        }
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $mention = $this->files()->findMention($id, $tenantId);
        if ($mention === null || !$this->files()->liftMention($id, $tenantId, $this->userId(), $request->inputString('lift_note'))) {
            Session::flash('error', 'Mention introuvable ou déjà levée.');

            return Response::redirect($back);
        }
        $this->audit($tenantId, 'file_mention.lift', 'sse_file_mention', $id, ['mention' => $mention['mention']]);
        Session::flash('success', 'Mention levée.');

        return Response::redirect($back);
    }

    /**
     * Déclarer un papier volé ou perdu, ou lever le signalement : même action que la fiche du papier (signalement du
     * registre, st_report), pas une mention en double.
     */
    public function documentReport(Request $request, array $params = []): Response
    {
        $back = self::safeBack($request->inputString('back'));
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied($back);
        }
        $tenantId = $this->tenantId();
        $documents = $this->documents ??= new SseIdDocumentRepository();
        $doc = $documents->find((int) ($params['id'] ?? 0), $tenantId);
        $report = match ($request->inputString('report')) {
            'stolen' => 'stolen',
            'lost' => 'lost',
            'none' => 'none',
            default => '',
        };
        if ($doc === null || $report === '' || ($report !== 'none' && $doc['workflow'] === 'draft') || !$documents->setReport((int) $doc['id'], $tenantId, $report)) {
            Session::flash('error', 'Cette action n’est pas possible dans l’état actuel du papier.');

            return Response::redirect($back);
        }
        $this->audit($tenantId, 'file_document.report', 'sse_id_document', (int) $doc['id'], ['report' => $report, 'before' => $doc['st_report']]);
        Session::flash('success', match ($report) {
            'stolen' => 'Papier déclaré volé.',
            'lost' => 'Papier déclaré perdu.',
            default => 'Signalement levé.',
        });

        return Response::redirect($back);
    }

    // ----------------------------------------------------------------- Outils

    /** @return array<string, string> */
    public static function criteriaFrom(Request $request): array
    {
        $out = [];
        foreach (['last', 'first', 'dob', 'number', 'country', 'doc_type'] as $k) {
            $out[$k] = mb_substr(trim($request->queryString($k)), 0, 120);
        }

        return $out;
    }

    /** @param array<string, string> $c */
    public static function hasCriteria(string $type, array $c): bool
    {
        return match ($type) {
            'QW' => trim($c['last'] . $c['first']) !== '',
            default => trim($c['number']) !== '',
        };
    }

    /** @param array<string, mixed> $criteria */
    public static function replayUrl(string $type, array $criteria): string
    {
        $q = ['type' => $type];
        foreach (['last', 'first', 'dob', 'number', 'country', 'doc_type'] as $k) {
            $v = trim((string) ($criteria[$k] ?? ''));
            if ($v !== '') {
                $q[$k] = $v;
            }
        }

        return url(self::BASE . '/requete') . '?' . http_build_query($q);
    }

    /**
     * Critères d'une ligne du journal → critères de l'écran (le jeu envoie aussi bio_key et plate).
     *
     * @param array<string, mixed> $criteria
     * @return array<string, string>
     */
    public static function replayCriteria(array $criteria): array
    {
        $out = [];
        foreach (['last', 'first', 'dob', 'number', 'country', 'doc_type'] as $k) {
            $out[$k] = is_scalar($criteria[$k] ?? null) ? (string) $criteria[$k] : '';
        }
        foreach (['bio_key', 'plate'] as $k) {
            if ($out['number'] === '' && is_scalar($criteria[$k] ?? null)) {
                $out['number'] = (string) $criteria[$k];
            }
        }

        return $out;
    }

    /** Retour sur une page du passage aux fichiers seulement (jamais une adresse extérieure). */
    public static function safeBack(string $back): string
    {
        $back = trim($back);
        if ($back !== '' && preg_match('#^atak/sse/fichiers(/[A-Za-z0-9/_-]*)?(\?[^\s]*)?$#', $back) === 1) {
            return url($back);
        }

        return url(self::BASE);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $names
     */
    private static function operatorOf(array $row, array $names): string
    {
        $uid = (int) ($row['operator_user_id'] ?? 0);
        $parts = array_filter([
            $uid > 0 ? ($names[$uid] ?? ('#' . $uid)) : null,
            ($row['operator_label'] ?? '') !== '' && ($uid < 1 || ($names[$uid] ?? '') !== $row['operator_label']) ? (string) $row['operator_label'] : null,
            ($row['operator_steam_id'] ?? '') !== '' ? 'Steam ' . $row['operator_steam_id'] : null,
        ]);

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    /**
     * @return array{column: string, id: int, ref: string}|null
     */
    private function resolveTarget(int $tenantId, string $type, int $id, string $ref): ?array
    {
        $ref = strtoupper(trim($ref));
        if ($ref !== '') {
            if (preg_match('/^IDT-0*(\d{1,10})$/', $ref, $m) === 1) {
                [$type, $id] = ['identity', (int) $m[1]];
            } elseif (preg_match('/^DOC-0*(\d{1,10})$/', $ref, $m) === 1) {
                [$type, $id] = ['document', (int) $m[1]];
            } elseif (str_starts_with($ref, 'REC-')) {
                $seek = $this->files()->findSeekByRec($tenantId, $ref);
                [$type, $id] = ['seek', (int) ($seek['id'] ?? 0)];
            }
        }
        if ($id < 1) {
            return null;
        }

        return match ($type) {
            'identity' => ($i = ($this->identities ??= new SseIdentityRepository())->find($id, $tenantId)) !== null
                ? ['column' => 'identity_id', 'id' => $id, 'ref' => (string) $i['ref']] : null,
            'document' => ($d = ($this->documents ??= new SseIdDocumentRepository())->find($id, $tenantId)) !== null
                ? ['column' => 'document_id', 'id' => $id, 'ref' => (string) $d['ref']] : null,
            'seek' => ($s = $this->files()->findSeek($id, $tenantId)) !== null
                ? ['column' => 'seek_record_id', 'id' => $id, 'ref' => (string) $s['rec']] : null,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $m
     * @return array{label: string, url: ?string, query: ?string}
     */
    private function targetOf(int $tenantId, array $m): array
    {
        try {
            if ($m['identity_id'] !== null && ($i = ($this->identities ??= new SseIdentityRepository())->find((int) $m['identity_id'], $tenantId)) !== null) {
                return [
                    'label' => $i['ref'] . ' · ' . $i['display_name'],
                    'url' => url('atak/sse/papiers/identites/' . (int) $i['id']),
                    'query' => self::replayUrl('QW', ['last' => $i['last_name'], 'first' => $i['first_name'], 'dob' => $i['birth_date'] ?? '']),
                ];
            }
            if ($m['document_id'] !== null && ($d = ($this->documents ??= new SseIdDocumentRepository())->find((int) $m['document_id'], $tenantId)) !== null) {
                return [
                    'label' => $d['ref'] . ' · ' . $d['country_code'] . ' ' . $d['doc_number'],
                    'url' => url('atak/sse/papiers/documents/' . (int) $d['id']),
                    'query' => self::replayUrl('QA', ['number' => $d['doc_number'], 'country' => $d['country_code'], 'doc_type' => $d['doc_type']]),
                ];
            }
            if ($m['seek_record_id'] !== null && ($s = $this->files()->findSeek((int) $m['seek_record_id'], $tenantId)) !== null) {
                return ['label' => (string) $s['rec'], 'url' => null, 'query' => self::replayUrl('QB', ['number' => $s['rec']])];
            }
        } catch (\Throwable) {
            // Fiche supprimée ou base indisponible : la mention s'affiche sans lien.
        }

        return ['label' => '—', 'url' => null, 'query' => null];
    }

    /**
     * @param list<int> $ids
     * @return array<int, string>
     */
    private function userNames(array $ids): array
    {
        $out = [];
        $ids = array_slice(array_values(array_unique(array_filter($ids, static fn (int $i): bool => $i > 0))), 0, 100);
        if ($ids === []) {
            return [];
        }
        try {
            $users = new \App\Repositories\UserRepository();
            foreach ($ids as $id) {
                $u = $users->findById($id, $this->tenantId());
                if (is_array($u)) {
                    $out[$id] = (string) ($u['display_name'] ?? $u['callsign'] ?? ('#' . $id));
                }
            }
        } catch (\Throwable) {
            // Noms indisponibles : le numéro du compte reste affiché.
        }

        return $out;
    }

    /** @param array<string, mixed> $after */
    private function audit(int $tenantId, string $action, string $objectType, int $objectId, array $after): void
    {
        try {
            (new SseAuditLogRepository())->write([
                'tenant_id' => $tenantId,
                'actor_user_id' => $this->userId(),
                'actor_label' => 'Passage aux fichiers',
                'action' => $action,
                'object_type' => $objectType,
                'object_id' => $objectId,
                'after' => $after,
            ]);
        } catch (\Throwable $e) {
            error_log('Passage aux fichiers : audit indisponible : ' . $e->getMessage());
        }
    }

    /** @return list<array<string, mixed>> */
    private function safeList(callable $fn): array
    {
        try {
            $rows = $fn();

            return is_array($rows) ? array_values($rows) : [];
        } catch (\Throwable $e) {
            error_log('Passage aux fichiers : lecture impossible : ' . $e->getMessage());

            return [];
        }
    }

    /** @param array<string, mixed> $data */
    private function render(string $view, array $data): Response
    {
        $data['papersTab'] = 'fichiers';
        $data['filesCanManage'] = $this->canManage();
        $data['csrfToken'] = Csrf::token();
        $data['mentionLabels'] = SseFileCatalog::MENTIONS;
        $data['docTypes'] = SseIdentityCatalog::DOC_TYPES;

        return (new SseIdentityRegistryController($this->access))->page($view, $data);
    }

    private function files(): SseFileRepository
    {
        return $this->files ??= new SseFileRepository();
    }

    private function service(): SseFileQueryService
    {
        return $this->service ??= new SseFileQueryService($this->files());
    }

    private function operatorLabel(): ?string
    {
        if ($this->access->isGuest()) {
            $label = trim((string) Session::get('sse_guest_label', ''));

            return $label !== '' ? 'Invité · ' . $label : 'Invité';
        }
        $name = trim((string) (Session::get('display_name') ?? Session::get('callsign') ?? ''));

        return $name !== '' ? $name : null;
    }

    private function denied(?string $back = null): Response
    {
        Session::flash('error', 'Action non autorisée.');

        return Response::redirect($back ?? url(self::BASE));
    }

    private function tenantId(): int
    {
        $tid = $this->access->tenantId();

        return $tid > 0 ? $tid : (int) Session::get('tenant_id');
    }

    private function userId(): ?int
    {
        $id = (int) Session::get('user_id');

        return $id > 0 ? $id : null;
    }

    /** Mêmes droits que les statuts d'un papier (SseIdentityRegistryController::canManage). */
    private function canManage(): bool
    {
        if ($this->access->isGuest()) {
            return false;
        }

        return function_exists('can') && (can('atak.sse.case.manage') || can('atak.sse.grant') || can('admin.access'));
    }
}
