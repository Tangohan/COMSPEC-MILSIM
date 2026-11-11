<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\AtakMapRepository;
use App\Repositories\SseIdCheckRepository;
use App\Repositories\SseIdentityRepository;
use App\Support\SseFieldNoteCatalog;
use App\Support\SseIdentityCatalog;

/**
 * COMSPEC IDENTITY (Lot 2) — lecture des contrôles d'identité remontés du terrain :
 *   - page « Contrôle ID » du vrai téléphone (/m/controle) : contrôles du joueur dans la partie, détail par niveau, photos ;
 *   - dossier RENS de la personne : contrôles regroupés par opération et par carte, photos et verdicts ;
 *   - tableau de bord identité de l'opération (bureau SSE) : contrôles, détections, faux, recherchés interpellés, fiches
 *     FRM à valider.
 * Les photos d'un contrôle sont les pièces jointes de la fiche FRM qui lui est rattachée (sse_id_checks.field_note_id) :
 * brouillon créé depuis le téléphone (« Joindre à une FRM »), ou fiche FRS envoyée par l'ATAK avec id_check_ref.
 * Tout est cloisonné par communauté.
 */
final class SseIdCheckInsightService
{
    /** Verdicts qui comptent comme une détection (tout sauf RAS et sans verdict). */
    public const DETECTION_VERDICTS = ['signale', 'recherche', 'hvt', 'inconnu', 'revoque', 'vole', 'expire'];

    /** Personnes recherchées : contrôlées = interpellées par le contrôle. */
    public const WANTED_VERDICTS = ['recherche', 'hvt'];

    /** État d'un niveau, renvoyé par le mod (fn_idEvaluate). */
    public const LEVEL_STATES = [
        'ok' => 'Conforme',
        'doubt' => 'Doute',
        'fail' => 'Anomalie',
        'alert' => 'Alerte',
        'na' => 'Non fait',
    ];

    /** Verdicts d'un niveau : ceux du contrat, plus « faux » et « doute » propres au détail du mod. */
    public const LEVEL_VERDICTS = SseIdentityCatalog::VERDICTS + [
        'faux' => 'Faux document',
        'doute' => 'Doute',
    ];

    /** Constats bruts du mod (codes des niveaux), en clair. */
    public const CODES = [
        'chip_absent' => 'Puce absente',
        'chip_defect' => 'Puce illisible',
        'chip_mismatch' => 'La puce ne correspond pas au papier',
        'chip_photo' => 'La photo de la puce ne correspond pas',
        'dark' => 'Lumière insuffisante',
        'expired' => 'Papier expiré',
        'holder_mismatch' => 'Le nom présenté ne correspond pas au titulaire',
        'lost' => 'Déclaré perdu',
        'stolen' => 'Déclaré volé',
        'revoked' => 'Papier annulé',
        'mrz_viz_dob' => 'Date de naissance de la bande MRZ différente du recto',
        'mrz_viz_name' => 'Nom de la bande MRZ différent du recto',
        'unknown_number' => 'Numéro jamais délivré',
        // Contrôle et présentation des papiers entre joueurs (mod SSE, fn_idP2pClient) : niveau N0 « non fait ».
        'p2p' => 'Contrôle joueur à joueur',
        'p2p_accepted' => 'Papiers présentés à la demande',
        'p2p_refused' => 'Refus de présenter ses papiers',
        'p2p_no_answer' => 'Pas de réponse à la demande',
        'p2p_presented' => 'Papiers présentés de sa propre initiative',
        'p2p_show' => 'Papiers montrés',
        'p2p_give' => 'Papiers remis en main',
        'p2p_reason_no_papers' => 'Motif : n’a pas ses papiers sur lui',
        'p2p_reason_not_now' => 'Motif : pas maintenant',
        'p2p_reason_no_authority' => 'Motif : conteste l’autorité du contrôleur',
        'p2p_reason_superior' => 'Motif : renvoie vers son supérieur',
    ];

    private Database $db;

    private SseIdCheckRepository $checks;

    /** @var \Closure(int, array<string, mixed>): array{note: array<string, mixed>, created: bool} */
    private \Closure $createNote;

    /** @var array<int, string>|null */
    private ?array $mapLabels = null;

    /**
     * @param (\Closure(int, array<string, mixed>): array{note: array<string, mixed>, created: bool})|null $createNote
     */
    public function __construct(?Database $db = null, ?SseIdCheckRepository $checks = null, ?\Closure $createNote = null)
    {
        $this->db = $db ?? Database::getInstance();
        SseIdentityRepository::ensureSchema();
        $this->checks = $checks ?? new SseIdCheckRepository($this->db);
        $this->createNote = $createNote ?? static fn (int $tenantId, array $input): array => (new SseFieldNoteService())->create($tenantId, $input);
    }

    public function repository(): SseIdCheckRepository
    {
        return $this->checks;
    }

    // ---------------------------------------------------------------- Lecture d'un contrôle

    /**
     * Détail des niveaux passés, dans l'ordre N0 → N3.
     *
     * @param array<string, mixed> $check contrôle hydraté (SseIdCheckRepository::hydrate)
     * @return list<array{code: string, label: string, state: string, state_label: string, verdict: string, verdict_label: string, codes: list<string>, tone: string}>
     */
    public static function levelRows(array $check): array
    {
        $results = is_array($check['results'] ?? null) ? $check['results'] : [];
        $codes = array_values(array_unique(array_merge((array) ($check['levels'] ?? []), array_keys($results))));
        sort($codes);
        $out = [];
        foreach ($codes as $code) {
            if (!isset(SseIdentityCatalog::LEVELS[$code])) {
                continue;
            }
            $r = $results[$code] ?? null;
            $state = is_array($r) ? strtolower((string) ($r['state'] ?? '')) : (is_string($r) ? strtolower($r) : '');
            $verdict = is_array($r) ? strtolower((string) ($r['verdict'] ?? '')) : '';
            if (is_array($r) && isset($r['valid']) && $state === '') {
                $state = $r['valid'] ? 'ok' : 'fail';
            }
            $raw = is_array($r) && is_array($r['codes'] ?? null) ? $r['codes'] : [];
            $readable = [];
            foreach (array_slice($raw, 0, 12) as $c) {
                if (is_scalar($c) && (string) $c !== '') {
                    // Nombre de papiers présentés entre joueurs : « p2p_docs:3 ».
                    if (preg_match('/^p2p_docs:(\d{1,2})$/', (string) $c, $m) === 1) {
                        $readable[] = (int) $m[1] > 1 ? (int) $m[1] . ' papiers présentés' : '1 papier présenté';
                        continue;
                    }
                    $readable[] = self::CODES[(string) $c] ?? (string) $c;
                }
            }
            $tone = match (true) {
                $state === 'na' => 'off',
                in_array($state, ['fail', 'alert'], true) || in_array($verdict, ['faux', 'recherche', 'hvt', 'vole', 'revoque', 'inconnu'], true) => 'bad',
                $state === 'doubt' || in_array($verdict, ['doute', 'signale', 'expire'], true) => 'warn',
                $state === 'ok' => 'ok',
                default => 'off',
            };
            $out[] = [
                'code' => $code,
                'label' => SseIdentityCatalog::LEVELS[$code],
                'state' => $state,
                'state_label' => self::LEVEL_STATES[$state] ?? ($state !== '' ? $state : 'Sans détail'),
                'verdict' => $verdict,
                'verdict_label' => $verdict !== '' ? (self::LEVEL_VERDICTS[$verdict] ?? $verdict) : '',
                'codes' => $readable,
                'tone' => $tone,
            ];
        }

        return $out;
    }

    /** Un niveau a conclu à un faux document (le verdict du contrat le range en « inconnu des fichiers »). */
    public static function isForgery(array $check): bool
    {
        foreach (is_array($check['results'] ?? null) ? $check['results'] : [] as $r) {
            if (is_array($r) && strtolower((string) ($r['verdict'] ?? '')) === 'faux') {
                return true;
            }
        }

        return false;
    }

    public static function isDetection(array $check): bool
    {
        return in_array((string) ($check['verdict'] ?? ''), self::DETECTION_VERDICTS, true) || self::isForgery($check);
    }

    public static function isWanted(array $check): bool
    {
        return in_array((string) ($check['verdict'] ?? ''), self::WANTED_VERDICTS, true);
    }

    /** Couleur du badge de verdict (sse-papers.css : idp-verdict--…). */
    public static function verdictTone(string $verdict): string
    {
        return match ($verdict) {
            'ras' => 'ok',
            'signale', 'expire' => 'warn',
            '' => 'none',
            default => 'bad',
        };
    }

    // ---------------------------------------------------------------- Fiches FRM et photos

    /**
     * Pièces jointes des fiches, par fiche.
     *
     * @param list<int> $noteIds
     * @return array<int, list<array{id: int, url: ?string, caption: string, name: string, is_image: bool, kind: string}>>
     */
    public function attachmentsFor(int $tenantId, array $noteIds): array
    {
        $ids = self::ids($noteIds);
        if ($ids === [] || !$this->tableReady('sse_field_note_attachments')) {
            return [];
        }
        [$in, $params] = self::inClause('n', $ids);
        $params['t'] = $tenantId;
        $rows = $this->db->fetchAll(
            'SELECT id, note_id, file_path, original_name, mime_type, kind, caption FROM sse_field_note_attachments
             WHERE tenant_id = :t AND note_id IN (' . $in . ') ORDER BY id ASC',
            $params
        );
        $out = [];
        foreach ($rows as $r) {
            $path = (string) ($r['file_path'] ?? '');
            $out[(int) $r['note_id']][] = [
                'id' => (int) $r['id'],
                'url' => $path !== '' && function_exists('user_media_public_url') ? user_media_public_url($path) : null,
                'caption' => trim((string) ($r['caption'] ?? '')),
                'name' => trim((string) ($r['original_name'] ?? '')),
                'is_image' => str_starts_with((string) ($r['mime_type'] ?? ''), 'image/'),
                'kind' => (string) ($r['kind'] ?? 'photo'),
            ];
        }

        return $out;
    }

    /**
     * Fiches FRM rattachées, par identifiant.
     *
     * @param list<int> $noteIds
     * @return array<int, array{id: int, ref: string, status: string, status_label: string, title: string, created_at: string}>
     */
    public function notesFor(int $tenantId, array $noteIds): array
    {
        $ids = self::ids($noteIds);
        if ($ids === [] || !$this->tableReady('sse_field_notes')) {
            return [];
        }
        [$in, $params] = self::inClause('n', $ids);
        $params['t'] = $tenantId;
        $rows = $this->db->fetchAll(
            'SELECT id, reference_code, status, title, created_at FROM sse_field_notes WHERE tenant_id = :t AND id IN (' . $in . ')',
            $params
        );
        $out = [];
        foreach ($rows as $r) {
            $status = (string) ($r['status'] ?? '');
            $out[(int) $r['id']] = [
                'id' => (int) $r['id'],
                'ref' => (string) ($r['reference_code'] ?? ''),
                'status' => $status,
                'status_label' => SseFieldNoteCatalog::STATUSES[$status] ?? $status,
                'title' => (string) ($r['title'] ?? ''),
                'created_at' => (string) ($r['created_at'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Ajoute à chaque contrôle sa fiche FRM (note), ses photos et le détail des niveaux (level_rows).
     *
     * @param list<array<string, mixed>> $checks
     * @return list<array<string, mixed>>
     */
    public function enrich(int $tenantId, array $checks): array
    {
        $noteIds = array_values(array_filter(array_map(static fn (array $c): int => (int) ($c['field_note_id'] ?? 0), $checks)));
        $notes = $this->notesFor($tenantId, $noteIds);
        $photos = $this->attachmentsFor($tenantId, $noteIds);
        foreach ($checks as &$c) {
            $nid = (int) ($c['field_note_id'] ?? 0);
            $c['note'] = $notes[$nid] ?? null;
            $c['photos'] = array_values(array_filter($photos[$nid] ?? [], static fn (array $p): bool => $p['is_image'] && $p['url'] !== null));
            $c['level_rows'] = self::levelRows($c);
            $c['forgery'] = self::isForgery($c);
            $c['verdict_tone'] = self::verdictTone((string) ($c['verdict'] ?? ''));
            $c['map_label'] = $this->mapLabel((int) ($c['context_id'] ?? 1));
        }
        unset($c);

        return $checks;
    }

    /**
     * Rattache une fiche à un contrôle désigné par « CTRL-42 » ou par sa clé d'idempotence (fiche FRS envoyée par
     * l'ATAK, champ id_check_ref). Un brouillon déjà rattaché (créé depuis le téléphone) est classé sans suite : la
     * fiche du contrôleur, avec ses photos, le remplace.
     */
    public function linkNote(int $tenantId, string $checkRef, int $noteId): bool
    {
        if ($noteId < 1) {
            return false;
        }
        $check = $this->checks->findByRef($tenantId, $checkRef);
        if ($check === null) {
            return false;
        }
        $old = (int) ($check['field_note_id'] ?? 0);
        if ($old === $noteId) {
            return true;
        }
        if ($old > 0 && $this->tableReady('sse_field_notes')) {
            $this->db->execute(
                "UPDATE sse_field_notes SET status = 'sans_suite', triage_note = :why WHERE id = :id AND tenant_id = :t AND status = 'brouillon'",
                ['why' => 'Remplacée par la fiche envoyée par le contrôleur depuis l’ATAK.', 'id' => $old, 't' => $tenantId]
            );
        }

        return $this->checks->setFieldNote((int) $check['id'], $tenantId, $noteId);
    }

    /**
     * Brouillon de fiche FRM né d'un contrôle (« Joindre à une FRM » du téléphone), à valider dans le bureau SSE.
     * Idempotent : une fiche déjà rattachée au contrôle est renvoyée telle quelle.
     *
     * @param array<string, mixed> $check
     * @param array{label?: string, user_id?: int|null, steam_id?: string|null} $author
     * @return array{ok: true, note_id: int, ref: string, created: bool}|array{ok: false, error: string}
     */
    public function draftFromCheck(int $tenantId, array $check, array $author, string $remark = ''): array
    {
        $checkId = (int) ($check['id'] ?? 0);
        if ($checkId < 1) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $existing = (int) ($check['field_note_id'] ?? 0);
        if ($existing > 0) {
            $note = $this->notesFor($tenantId, [$existing])[$existing] ?? null;
            if ($note !== null) {
                return ['ok' => true, 'note_id' => $existing, 'ref' => $note['ref'], 'created' => false];
            }
        }
        $pos = is_array($check['pos'] ?? null) ? $check['pos'] : [null, null, null];
        $who = trim(mb_strtoupper((string) ($check['presented_last'] ?? '')) . ' ' . (string) ($check['presented_first'] ?? ''));
        $themes = ['PERSON'];
        if (self::isWanted($check)) {
            $themes[] = 'TERROR';
            $themes[] = 'INSURG';
        }
        try {
            $res = ($this->createNote)($tenantId, [
                'context_id' => (int) ($check['context_id'] ?? 1),
                'note_kind' => 'FRM',
                'themes' => $themes,
                'title' => mb_substr('Contrôle d’identité ' . (string) $check['ref'] . ($who !== '' ? ' — ' . $who : ''), 0, SseFieldNoteCatalog::TITLE_MAX_LENGTH),
                'body' => self::draftBody($check, $remark),
                'observed_at' => (string) ($check['created_at'] ?? '') ?: null,
                'grid_reference' => $check['grid'] ?? null,
                'pos_x' => $pos[0] ?? null,
                'pos_y' => $pos[1] ?? null,
                'pos_z' => $pos[2] ?? null,
                'urgency' => self::isWanted($check) ? 'urgent' : 'routine',
                'intel_source' => 'HUMINT',
                'classification' => 'interne',
                'source_reliability' => 'C',
                'info_credibility' => 3,
                'status' => 'brouillon',
                'origin' => 'atak',
                'author_label' => trim((string) ($author['label'] ?? '')) ?: (string) ($check['controller_callsign'] ?? ''),
                'author_user_id' => $author['user_id'] ?? null,
                'author_steam_id' => $author['steam_id'] ?? null,
                'case_code' => $check['case_ref'] ?? null,
                'idempotency_key' => 'idcheck-frm-' . $tenantId . '-' . $checkId,
            ]);
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'unavailable'];
        }
        $noteId = (int) ($res['note']['id'] ?? 0);
        if ($noteId < 1) {
            return ['ok' => false, 'error' => 'unavailable'];
        }
        $this->checks->setFieldNote($checkId, $tenantId, $noteId);

        return ['ok' => true, 'note_id' => $noteId, 'ref' => (string) ($res['note']['reference_code'] ?? ''), 'created' => (bool) ($res['created'] ?? true)];
    }

    /** Texte du brouillon FRM : le contrôle, ses niveaux, puis la remarque du contrôleur (1 000 caractères au plus). */
    public static function draftBody(array $check, string $remark = ''): string
    {
        $type = SseIdentityCatalog::DOC_TYPES[(string) ($check['doc_type'] ?? '')] ?? 'Papier';
        $who = trim(mb_strtoupper((string) ($check['presented_last'] ?? '')) . ' ' . (string) ($check['presented_first'] ?? ''));
        $lines = [
            sprintf(
                'Contrôle d’identité %s%s%s : %s%s.',
                (string) ($check['ref'] ?? ''),
                trim((string) ($check['game_time'] ?? '')) !== '' ? ' du ' . trim((string) $check['game_time']) : '',
                trim((string) ($check['grid'] ?? '')) !== '' ? ' au ' . trim((string) $check['grid']) : '',
                $who !== '' ? $who : 'identité non lue',
                trim((string) ($check['presented_dob'] ?? '')) !== '' ? ', né(e) le ' . trim((string) $check['presented_dob']) : ''
            ),
            sprintf('Papier présenté : %s %s n° %s.', is_string($type) ? $type : 'Papier', (string) ($check['country_code'] ?? ''), (string) ($check['doc_number'] ?? '—')),
            sprintf('Verdict : %s.', SseIdentityCatalog::label(SseIdentityCatalog::VERDICTS, (string) ($check['verdict'] ?? ''), 'sans verdict')),
        ];
        foreach (self::levelRows($check) as $l) {
            $lines[] = sprintf(
                '%s : %s%s%s.',
                $l['code'],
                $l['state_label'],
                $l['verdict_label'] !== '' ? ', ' . mb_strtolower($l['verdict_label']) : '',
                $l['codes'] !== [] ? ' (' . implode(', ', $l['codes']) . ')' : ''
            );
        }
        if (trim((string) ($check['controller_callsign'] ?? '')) !== '') {
            $lines[] = 'Contrôleur : ' . trim((string) $check['controller_callsign']) . '.';
        }
        $remark = trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $remark));
        if ($remark !== '') {
            $lines[] = '';
            $lines[] = 'Remarque : ' . $remark;
        }
        $body = implode("\n", $lines);

        return mb_strlen($body) > SseFieldNoteCatalog::BODY_MAX_LENGTH ? mb_substr($body, 0, SseFieldNoteCatalog::BODY_MAX_LENGTH - 1) . '…' : $body;
    }

    // ---------------------------------------------------------------- Téléphone, dossier, tableau de bord

    /**
     * Contrôles faits par le joueur de la session mobile, sur la carte de la partie, depuis le début de la partie
     * (8 heures avant l'ouverture du lien au plus).
     *
     * @param array<string, mixed> $link session « Sur mon téléphone » (AtakMobileLinkService::current)
     * @return list<array<string, mixed>>
     */
    public function forMobile(array $link, int $limit = 60): array
    {
        $tenantId = (int) ($link['tenant_id'] ?? 0);
        $steam = (string) ($link['steam_uid'] ?? '');
        if ($tenantId < 1 || $steam === '') {
            return [];
        }
        $start = strtotime((string) ($link['created_at'] ?? '') . ' UTC') ?: time();
        $since = gmdate('Y-m-d H:i:s', $start - 28800);

        return $this->enrich($tenantId, $this->checks->listForController($tenantId, (int) ($link['map_id'] ?? 1), $steam, $since, $limit));
    }

    /** Contrôle de la session mobile (le joueur en est le contrôleur), sinon null. */
    public function mobileCheck(array $link, int $id): ?array
    {
        $tenantId = (int) ($link['tenant_id'] ?? 0);
        $steam = (string) preg_replace('/\D/', '', (string) ($link['steam_uid'] ?? ''));
        $check = $id > 0 && $tenantId > 0 ? $this->checks->find($id, $tenantId) : null;
        if ($check === null || $steam === '' || (string) ($check['controller_steam_id'] ?? '') !== $steam) {
            return null;
        }

        return $this->enrich($tenantId, [$check])[0];
    }

    /**
     * Dossier RENS : contrôles de la personne (fiche ou papiers qu'elle porte), regroupés par opération et par carte.
     *
     * @param list<int> $documentIds papiers dont la personne est titulaire
     * @return array{total: int, photos: int, groups: list<array<string, mixed>>}
     */
    public function personDossier(int $tenantId, int $personId, array $documentIds = [], int $limit = 200, ?callable $keep = null): array
    {
        $empty = ['total' => 0, 'photos' => 0, 'groups' => []];
        if ($tenantId < 1 || $personId < 1) {
            return $empty;
        }
        $params = ['t' => $tenantId, 'p' => $personId];
        $or = ['person_id = :p'];
        $docs = self::ids($documentIds);
        if ($docs !== []) {
            [$in, $dp] = self::inClause('d', $docs);
            $or[] = 'document_id IN (' . $in . ')';
            $params += $dp;
        }
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_id_checks WHERE tenant_id = :t AND (' . implode(' OR ', $or) . ') ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)),
            $params
        );
        $hydrated = array_map([SseIdCheckRepository::class, 'hydrate'], $rows);
        $checks = $this->enrich($tenantId, $keep !== null ? $keep($hydrated) : $hydrated);
        $groups = [];
        $photos = 0;
        foreach ($checks as $c) {
            $op = trim((string) ($c['op_key'] ?? ''));
            $key = (int) $c['context_id'] . '|' . $op;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'context_id' => (int) $c['context_id'],
                    'map_label' => $c['map_label'],
                    'op_key' => $op,
                    'total' => 0,
                    'verdicts' => [],
                    'detections' => 0,
                    'forgeries' => 0,
                    'first_at' => (string) $c['created_at'],
                    'last_at' => (string) $c['created_at'],
                    'checks' => [],
                    'photos' => [],
                ];
            }
            $g = &$groups[$key];
            $g['total']++;
            $v = (string) $c['verdict'];
            $g['verdicts'][$v] = ($g['verdicts'][$v] ?? 0) + 1;
            $g['detections'] += self::isDetection($c) ? 1 : 0;
            $g['forgeries'] += $c['forgery'] ? 1 : 0;
            $g['first_at'] = min($g['first_at'], (string) $c['created_at']);
            $g['last_at'] = max($g['last_at'], (string) $c['created_at']);
            $g['checks'][] = $c;
            foreach ($c['photos'] as $p) {
                $g['photos'][] = $p + ['check_ref' => $c['ref']];
                $photos++;
            }
            unset($g);
        }
        foreach ($groups as &$g) {
            // Pire verdict du groupe en tête (même ordre de gravité que le site : hvt > recherche > vole > …).
            uksort($g['verdicts'], static fn (string $a, string $b): int => self::severity($b) <=> self::severity($a));
        }
        unset($g);

        return ['total' => count($checks), 'photos' => $photos, 'groups' => array_values($groups)];
    }

    /**
     * Tableau de bord identité d'une opération : null = toutes les cartes / toutes les opérations.
     *
     * @return array<string, mixed>
     */
    public function dashboard(int $tenantId, ?int $mapId, ?string $opKey, bool $truth): array
    {
        $checks = $this->checks->listForScope($tenantId, $mapId, $opKey);
        $byVerdict = [];
        $detections = 0;
        $forgeries = 0;
        $wanted = [];
        $controllers = [];
        $alerts = [];
        $docIds = [];
        $points = [];
        foreach ($checks as $c) {
            $v = (string) $c['verdict'];
            // Carte des contrôles (lot 6) : seulement ceux qui ont une position.
            if (is_array($c['pos'] ?? null) && count($points) < 1000) {
                $points[] = [
                    'id' => (int) $c['id'], 'ref' => (string) $c['ref'], 'x' => round((float) $c['pos'][0], 1), 'y' => round((float) $c['pos'][1], 1),
                    'verdict' => $v, 'label' => (string) $c['verdict_label'], 'tone' => self::verdictTone($v), 'grid' => (string) ($c['grid'] ?? ''),
                    'at' => (string) $c['created_at'], 'forgery' => self::isForgery($c),
                ];
            }
            $byVerdict[$v] = ($byVerdict[$v] ?? 0) + 1;
            $isDetection = self::isDetection($c);
            $detections += $isDetection ? 1 : 0;
            $forgeries += self::isForgery($c) ? 1 : 0;
            if (self::isWanted($c)) {
                $k = (int) ($c['person_id'] ?? 0) > 0
                    ? 'p' . (int) $c['person_id']
                    : 'n' . mb_strtolower(trim((string) ($c['presented_last'] ?? '')) . '|' . trim((string) ($c['presented_first'] ?? '')) . '|' . trim((string) ($c['presented_dob'] ?? '')));
                $wanted[$k] ??= $c;
            }
            $who = trim((string) ($c['controller_callsign'] ?? '')) ?: 'Sans indicatif';
            $controllers[$who] = ($controllers[$who] ?? 0) + 1;
            if ($isDetection && count($alerts) < 15) {
                $alerts[] = $c;
            }
            if ((int) ($c['document_id'] ?? 0) > 0) {
                $docIds[] = (int) $c['document_id'];
            }
        }
        arsort($controllers);
        uksort($byVerdict, static fn (string $a, string $b): int => self::severity($b) <=> self::severity($a));

        // Vérité de jeu (habilitation Confidentiel) : faux réellement présentés, d'après le registre.
        $realForgeries = null;
        if ($truth) {
            $realForgeries = 0;
            $bad = $this->forgedDocumentIds($tenantId, $docIds);
            foreach ($checks as $c) {
                $realForgeries += in_array((int) ($c['document_id'] ?? 0), $bad, true) ? 1 : 0;
            }
        }

        return [
            'total' => count($checks),
            'detections' => $detections,
            'forgeries' => $forgeries,
            'real_forgeries' => $realForgeries,
            'wanted' => count($wanted),
            'wanted_list' => $this->enrich($tenantId, array_values(array_slice($wanted, 0, 20))),
            'by_verdict' => $byVerdict,
            'controllers' => array_slice($controllers, 0, 10, true),
            'alerts' => $this->enrich($tenantId, $alerts),
            'drafts' => $this->pendingDrafts($tenantId, $mapId, $opKey),
            'truncated' => count($checks) >= 2000,
            'points' => $points,
        ];
    }

    /**
     * Brouillons FRM nés des contrôles, à valider dans le bureau SSE.
     *
     * @return list<array<string, mixed>>
     */
    public function pendingDrafts(int $tenantId, ?int $mapId = null, ?string $opKey = null, int $limit = 50): array
    {
        if (!$this->tableReady('sse_field_notes')) {
            return [];
        }
        $where = ['c.tenant_id = :t', "n.status = 'brouillon'"];
        $params = ['t' => $tenantId];
        if ($mapId !== null && $mapId > 0) {
            $where[] = 'c.context_id = :m';
            $params['m'] = $mapId;
        }
        if ($opKey !== null) {
            if ($opKey === '') {
                $where[] = "(c.op_key IS NULL OR c.op_key = '')";
            } else {
                $where[] = 'c.op_key = :op';
                $params['op'] = $opKey;
            }
        }
        $rows = $this->db->fetchAll(
            'SELECT c.id AS check_id, c.verdict, c.presented_last, c.presented_first, c.controller_callsign, c.grid, c.created_at AS checked_at,
                    n.id AS note_id, n.reference_code, n.title, n.author_label, n.created_at
             FROM sse_id_checks c JOIN sse_field_notes n ON n.id = c.field_note_id AND n.tenant_id = c.tenant_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY n.id DESC LIMIT ' . max(1, min(200, $limit)),
            $params
        );
        $photos = $this->attachmentsFor($tenantId, array_map(static fn (array $r): int => (int) $r['note_id'], $rows));

        return array_map(static fn (array $r): array => [
            'check_id' => (int) $r['check_id'],
            'check_ref' => SseIdentityCatalog::checkRef((int) $r['check_id']),
            'verdict' => (string) ($r['verdict'] ?? ''),
            'verdict_label' => SseIdentityCatalog::label(SseIdentityCatalog::VERDICTS, (string) ($r['verdict'] ?? ''), 'Sans verdict'),
            'verdict_tone' => self::verdictTone((string) ($r['verdict'] ?? '')),
            'who' => trim(mb_strtoupper((string) ($r['presented_last'] ?? '')) . ' ' . (string) ($r['presented_first'] ?? '')),
            'controller' => (string) ($r['controller_callsign'] ?? ''),
            'grid' => (string) ($r['grid'] ?? ''),
            'note_id' => (int) $r['note_id'],
            'note_ref' => (string) ($r['reference_code'] ?? ''),
            'title' => (string) ($r['title'] ?? ''),
            'author' => (string) ($r['author_label'] ?? ''),
            'created_at' => (string) ($r['created_at'] ?? ''),
            'photos' => count($photos[(int) $r['note_id']] ?? []),
        ], $rows);
    }

    /** Opérations proposées dans le filtre du tableau de bord. */
    public function operations(int $tenantId): array
    {
        return array_map(fn (array $o): array => $o + ['map_label' => $this->mapLabel($o['context_id'])], $this->checks->operations($tenantId));
    }

    public function mapLabel(int $mapId): string
    {
        if ($this->mapLabels === null) {
            $this->mapLabels = [];
            try {
                foreach ((new AtakMapRepository())->getAll() as $m) {
                    $this->mapLabels[(int) $m['id']] = (string) ($m['name'] ?? $m['display_name'] ?? $m['label'] ?? $m['slug'] ?? '');
                }
            } catch (\Throwable) {
                // Cartes indisponibles : numéro seul.
            }
        }
        $label = trim($this->mapLabels[$mapId] ?? '');

        return $label !== '' ? $label : 'Carte ' . $mapId;
    }

    // ---------------------------------------------------------------- Outils

    /** Gravité d'un verdict (ordre du contrat). */
    private static function severity(string $verdict): int
    {
        return ['hvt' => 8, 'recherche' => 7, 'vole' => 6, 'revoque' => 5, 'inconnu' => 4, 'expire' => 3, 'signale' => 2, 'ras' => 1][$verdict] ?? 0;
    }

    /**
     * @param list<int> $docIds
     * @return list<int>
     */
    private function forgedDocumentIds(int $tenantId, array $docIds): array
    {
        $ids = self::ids($docIds);
        if ($ids === [] || !$this->tableReady('sse_id_documents')) {
            return [];
        }
        $out = [];
        foreach (array_chunk($ids, 400) as $chunk) {
            [$in, $params] = self::inClause('d', $chunk);
            $params['t'] = $tenantId;
            foreach ($this->db->fetchAll(
                "SELECT id FROM sse_id_documents WHERE tenant_id = :t AND id IN ({$in})
                 AND (st_origin = 'forged' OR st_integrity IN ('altered', 'counterfeit'))",
                $params
            ) as $r) {
                $out[] = (int) $r['id'];
            }
        }

        return $out;
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private static function ids(array $ids): array
    {
        return array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0))), 0, 1000);
    }

    /**
     * @param list<int> $ids
     * @return array{0: string, 1: array<string, int>}
     */
    private static function inClause(string $prefix, array $ids): array
    {
        $marks = [];
        $params = [];
        foreach (array_values($ids) as $i => $id) {
            $marks[] = ':' . $prefix . $i;
            $params[$prefix . $i] = $id;
        }

        return [implode(', ', $marks), $params];
    }

    private function tableReady(string $table): bool
    {
        try {
            $this->db->fetchOne('SELECT 1 AS ok FROM ' . $table . ' LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
