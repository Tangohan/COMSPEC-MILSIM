<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseIdCheckRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Support\SseIdentityCatalog;

/**
 * « Consigner un papier » depuis les remontées des contrôles ATAK : ce que le terminal a transmis d'un papier (type, pays, numéro,
 * nom présenté, date d'expiration et appréciation visuelle d'un relevé, contrôleur, lieu, heure) est proposé à la sélection, puis
 * consigné sur un sujet contrôlé sans ressaisie. Le contrôle d'origine est rattaché au papier (sse_id_checks.document_id) : c'est ce
 * rattachement qui dit « consigné », qui interdit la double consignation et qui alimente le widget Liaisons.
 *
 * Périmètre d'un sujet : les contrôles déjà rattachés à son identité ou à sa fiche personne, et ceux qui ne sont rattachés à personne.
 * Un contrôle rattaché à une autre identité n'est jamais proposé ni consigné ici.
 */
final class SseCheckPaperSelectionService
{
    public const STATE_NEW = 'new';
    public const STATE_CONSIGNED = 'consigned';
    public const STATE_EXISTS = 'exists';
    public const STATE_INCOMPLETE = 'incomplete';

    public const SCOPE_MINE = 'mine';
    public const SCOPE_FREE = 'free';

    private const LIMIT = 200;

    /** Appréciation visuelle d'un relevé ATAK → authenticité du papier constaté. */
    private const VISUAL_TO_AUTHENTICITY = ['faux' => 'fake', 'doute' => 'doubtful'];

    private Database $db;
    private SseIdCheckRepository $checks;
    private SseIdDocumentRepository $documents;

    public function __construct(?Database $db = null, ?SseIdCheckRepository $checks = null, ?SseIdDocumentRepository $documents = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->checks = $checks ?? new SseIdCheckRepository($this->db);
        $this->documents = $documents ?? new SseIdDocumentRepository();
    }

    /**
     * Remontées proposées pour un sujet, la plus récente d'abord, avec leur état et les champs préremplis.
     *
     * @param array<string, mixed> $identity ligne de sse_identities (id, person_id)
     * @return list<array<string, mixed>>
     */
    public function candidates(int $tenantId, array $identity): array
    {
        $iid = (int) ($identity['id'] ?? 0);
        $pid = (int) ($identity['person_id'] ?? 0);
        if ($iid < 1) {
            return [];
        }
        $params = ['t' => $tenantId, 'i' => $iid, 'p' => $pid];
        $rows = $this->db->fetchAll(
            "SELECT * FROM sse_id_checks WHERE tenant_id = :t AND COALESCE(doc_number, '') <> ''
             AND (identity_id = :i OR (:p > 0 AND person_id = :p2) OR (identity_id IS NULL AND person_id IS NULL))
             ORDER BY id DESC LIMIT " . self::LIMIT,
            $params + ['p2' => $pid]
        );
        $checks = array_map([SseIdCheckRepository::class, 'hydrate'], $rows);
        $docs = $this->documentsById($tenantId, array_values(array_filter(array_map(static fn (array $c): int => (int) ($c['document_id'] ?? 0), $checks))));

        $out = [];
        foreach ($checks as $c) {
            $out[] = $this->describe($tenantId, $c, $iid, $pid, $docs);
        }

        return $out;
    }

    /**
     * Consigne les remontées choisies. Chaque entrée de $selection est indexée par l'identifiant du contrôle et peut porter les champs
     * modifiés à la main ; ce qui manque est repris du contrôle. Rien n'est consigné deux fois : un papier déjà au registre ou
     * déjà consigné est signalé, deux contrôles du même papier ne donnent qu'un papier.
     *
     * @param array<string, mixed> $identity
     * @param array<int|string, mixed> $selection
     * @return array{created: list<array{doc_id: int, ref: string, check_ids: list<int>}>, skipped: list<array{check_id: int, reason: string}>}
     */
    public function consign(int $tenantId, array $identity, array $selection, ?int $userId): array
    {
        $iid = (int) ($identity['id'] ?? 0);
        $pid = (int) ($identity['person_id'] ?? 0);
        $created = [];
        $skipped = [];
        $byKey = [];
        foreach ($selection as $rawId => $edits) {
            $checkId = (int) $rawId;
            $edits = is_array($edits) ? $edits : [];
            $check = $checkId > 0 ? $this->checks->find($checkId, $tenantId) : null;
            if ($check === null) {
                $skipped[] = ['check_id' => $checkId, 'reason' => 'introuvable'];
                continue;
            }
            $cIdentity = (int) ($check['identity_id'] ?? 0);
            $cPerson = (int) ($check['person_id'] ?? 0);
            if (($cIdentity > 0 && $cIdentity !== $iid) || ($cPerson > 0 && ($pid < 1 || $cPerson !== $pid))) {
                // Rattaché à une autre identité : comme un contrôle d'une autre communauté, on ne le désigne pas.
                $skipped[] = ['check_id' => $checkId, 'reason' => 'introuvable'];
                continue;
            }
            if ((int) ($check['document_id'] ?? 0) > 0) {
                $skipped[] = ['check_id' => $checkId, 'reason' => 'consigne'];
                continue;
            }
            $f = $this->fields($check, $edits);
            if ($f['country_code'] === '' || $f['doc_number'] === '') {
                $skipped[] = ['check_id' => $checkId, 'reason' => 'incomplet'];
                continue;
            }
            $key = $f['country_code'] . '|' . $f['doc_type'] . '|' . $f['doc_number'];
            if (isset($byKey[$key])) {
                // Même papier vu à deux contrôles : un seul papier, les deux contrôles lui sont rattachés.
                [$docId, $at] = $byKey[$key];
                if ($this->checks->claimDocument($checkId, $tenantId, $docId, $iid, $pid)) {
                    $created[$at]['check_ids'][] = $checkId;
                    $this->traceOrigin($tenantId, $docId, $checkId, $userId);
                } else {
                    $skipped[] = ['check_id' => $checkId, 'reason' => 'consigne'];
                }
                continue;
            }
            if ($this->documents->numberExists($tenantId, $f['country_code'], $f['doc_type'], $f['doc_number'])) {
                $skipped[] = ['check_id' => $checkId, 'reason' => 'registre'];
                continue;
            }
            try {
                $docId = $this->documents->create($tenantId, $this->documentData($iid, $identity, $f, $check), $userId);
            } catch (\PDOException) {
                // Course avec une autre consignation du même numéro : l'index unique a refusé la seconde.
                $skipped[] = ['check_id' => $checkId, 'reason' => 'registre'];
                continue;
            }
            $this->checks->claimDocument($checkId, $tenantId, $docId, $iid, $pid);
            $this->traceOrigin($tenantId, $docId, $checkId, $userId);
            $byKey[$key] = [$docId, count($created)];
            $created[] = ['doc_id' => $docId, 'ref' => SseIdentityCatalog::documentRef($docId), 'check_ids' => [$checkId]];
            $this->audit($tenantId, $userId, $docId, ['identity_id' => $iid, 'check_id' => $checkId, 'check_ref' => $check['ref'], 'doc_type' => $f['doc_type'], 'country' => $f['country_code']]);
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Libellés de la colonne « Registre » de la liste des contrôles : pour chaque contrôle, consigné (référence du papier) ou à consigner.
     *
     * @param list<array<string, mixed>> $checks
     * @return array<int, array{state: string, doc_id: int, ref: string, observed: bool}> par identifiant de contrôle
     */
    public function registryStates(int $tenantId, array $checks): array
    {
        $docs = $this->documentsById($tenantId, array_values(array_filter(array_map(static fn (array $c): int => (int) ($c['document_id'] ?? 0), $checks))));
        $out = [];
        foreach ($checks as $c) {
            $docId = (int) ($c['document_id'] ?? 0);
            $number = trim((string) ($c['doc_number'] ?? ''));
            if ($docId > 0 && isset($docs[$docId])) {
                $out[(int) $c['id']] = ['state' => self::STATE_CONSIGNED, 'doc_id' => $docId, 'ref' => SseIdentityCatalog::documentRef($docId), 'observed' => $docs[$docId]['workflow'] === 'observed'];
            } elseif ($number !== '') {
                $out[(int) $c['id']] = ['state' => self::STATE_NEW, 'doc_id' => 0, 'ref' => '', 'observed' => false];
            } else {
                $out[(int) $c['id']] = ['state' => self::STATE_INCOMPLETE, 'doc_id' => 0, 'ref' => '', 'observed' => false];
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ Interne

    /**
     * @param array<string, mixed> $check
     * @param array<int, array<string, mixed>> $docs
     * @return array<string, mixed>
     */
    private function describe(int $tenantId, array $check, int $iid, int $pid, array $docs): array
    {
        $f = $this->fields($check, []);
        $docId = (int) ($check['document_id'] ?? 0);
        $mine = (int) ($check['identity_id'] ?? 0) === $iid || ($pid > 0 && (int) ($check['person_id'] ?? 0) === $pid);
        $state = self::STATE_NEW;
        $docRef = '';
        $docOpen = 0;
        if ($docId > 0) {
            $state = self::STATE_CONSIGNED;
            $docRef = SseIdentityCatalog::documentRef($docId);
            $docOpen = $docId;
        } elseif ($f['doc_number'] === '' || $f['country_code'] === '') {
            $state = self::STATE_INCOMPLETE;
        } else {
            $existing = $this->existing($tenantId, $f);
            if ($existing > 0) {
                $state = self::STATE_EXISTS;
                $docRef = SseIdentityCatalog::documentRef($existing);
                $docOpen = $existing;
            }
        }

        return [
            'id' => (int) $check['id'],
            'ref' => (string) $check['ref'],
            'created_at' => (string) ($check['created_at'] ?? ''),
            'game_time' => (string) ($check['game_time'] ?? ''),
            'controller' => (string) ($check['controller_callsign'] ?? ''),
            'grid' => (string) ($check['grid'] ?? ''),
            'op_key' => (string) ($check['op_key'] ?? ''),
            'verdict' => (string) ($check['verdict'] ?? ''),
            'verdict_label' => (string) ($check['verdict_label'] ?? ''),
            'presented_name' => trim(mb_strtoupper((string) ($check['presented_last'] ?? '')) . ' ' . (string) ($check['presented_first'] ?? '')),
            'presented_dob' => (string) ($check['presented_dob'] ?? ''),
            'scope' => $mine ? self::SCOPE_MINE : self::SCOPE_FREE,
            'state' => $state,
            'selectable' => $state === self::STATE_NEW || $state === self::STATE_INCOMPLETE,
            'doc_id' => $docOpen,
            'doc_ref' => $docRef,
            'fields' => $f,
        ];
    }

    /**
     * Champs du papier : ce qu'a transmis le contrôle, remplacé champ par champ par ce que l'opérateur a modifié.
     *
     * @param array<string, mixed> $check
     * @param array<string, mixed> $edits
     * @return array{doc_type: string, country_code: string, doc_number: string, issued_on: string, expires_on: string, authority: string, authenticity: string, seen_on: string, place: string, remark: string}
     */
    private function fields(array $check, array $edits): array
    {
        $n0 = is_array($check['results']['N0'] ?? null) ? $check['results']['N0'] : [];
        // Délivrance et autorité lues sur le papier par le terminal (results.paper), sinon rien : l'opérateur les saisit.
        $paper = is_array($check['results']['paper'] ?? null) ? $check['results']['paper'] : [];
        $visual = strtolower((string) ($n0['visual'] ?? ''));
        $date = static fn (mixed $v): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $v)) === 1 ? trim((string) $v) : '';
        $str = static fn (mixed $v, int $max): string => mb_substr(trim(str_replace(["\r", "\n", "\t"], ' ', is_scalar($v) ? (string) $v : '')), 0, $max);
        $pick = static fn (string $key, string $from): mixed => array_key_exists($key, $edits) ? $edits[$key] : $from;

        $createdDate = substr((string) ($check['created_at'] ?? ''), 0, 10);
        $place = trim(((string) ($check['grid'] ?? '') !== '' ? 'Grille ' . $check['grid'] : '') . ((string) ($check['op_key'] ?? '') !== '' ? ' · ' . $check['op_key'] : ''), ' ·');
        $type = SseIdentityCatalog::pick(SseIdentityCatalog::DOC_TYPES, (string) $pick('doc_type', (string) ($check['doc_type'] ?? '')), 'id_card');
        $auth = (string) $pick('authenticity', self::VISUAL_TO_AUTHENTICITY[$visual] ?? 'authentic');

        return [
            'doc_type' => $type,
            'country_code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $pick('country_code', (string) ($check['country_code'] ?? ''))) ?? '', 0, 3)),
            'doc_number' => SseIdDocumentRepository::normalizeNumber((string) $pick('doc_number', (string) ($check['doc_number'] ?? ''))),
            'issued_on' => $date($pick('issued_on', $date($paper['issued'] ?? ''))),
            'expires_on' => $date($pick('expires_on', $date($n0['expires'] ?? ''))),
            'authority' => $str($pick('authority', (string) ($paper['authority'] ?? '')), 160),
            'authenticity' => isset(SseIdentityCatalog::AUTHENTICITY[$auth]) ? $auth : 'authentic',
            'seen_on' => $date($pick('seen_on', $createdDate)) ?: gmdate('Y-m-d'),
            'place' => $str($pick('place', $place), 120),
            'remark' => mb_substr(trim(is_scalar($edits['remark'] ?? null) ? (string) $edits['remark'] : ''), 0, 1500),
        ];
    }

    /**
     * @param array<string, mixed> $identity
     * @param array<string, string> $f
     * @param array<string, mixed> $check
     * @return array<string, mixed>
     */
    private function documentData(int $iid, array $identity, array $f, array $check): array
    {
        $notes = 'Constaté le ' . $f['seen_on'] . ($f['place'] !== '' ? ' · ' . $f['place'] : '') . ' · source : ' . SseIdentityCatalog::OBSERVED_SOURCES['check']
            . ' · contrôle ' . $check['ref']
            . ($f['remark'] !== '' ? "\n" . $f['remark'] : '');

        return [
            'identity_id' => $iid,
            'holder_person_id' => $identity['person_id'] ?? null,
            'workflow' => 'observed',
            'doc_type' => $f['doc_type'],
            'country_code' => $f['country_code'],
            'doc_number' => $f['doc_number'],
            'issued_on' => $f['issued_on'],
            'expires_on' => $f['expires_on'],
            'authority' => $f['authority'],
            'st_origin' => $f['authenticity'] === 'fake' ? 'forged' : 'official',
            'st_integrity' => match ($f['authenticity']) {
                'fake' => 'counterfeit',
                'doubtful' => 'altered',
                default => 'intact',
            },
            'context_id' => $check['context_id'] ?? null,
            'op_key' => $check['op_key'] ?? null,
            'notes' => $notes,
        ];
    }

    /** Papier déjà au registre pour ce pays, ce type et ce numéro (0 : aucun). */
    private function existing(int $tenantId, array $f): int
    {
        $row = $this->db->fetchOne(
            'SELECT id FROM sse_id_documents WHERE tenant_id = :t AND country_code = :c AND doc_type = :ty AND doc_number = :n',
            ['t' => $tenantId, 'c' => $f['country_code'], 'ty' => $f['doc_type'], 'n' => $f['doc_number']]
        );

        return $row === null ? 0 : (int) $row['id'];
    }

    /**
     * @param list<int> $ids
     * @return array<int, array{id: int, workflow: string}>
     */
    private function documentsById(int $tenantId, array $ids): array
    {
        $ids = array_slice(array_values(array_unique($ids)), 0, 500);
        if ($ids === []) {
            return [];
        }
        $marks = [];
        $params = ['t' => $tenantId];
        foreach ($ids as $i => $id) {
            $marks[] = ':d' . $i;
            $params['d' . $i] = $id;
        }
        $out = [];
        foreach ($this->db->fetchAll('SELECT id, workflow FROM sse_id_documents WHERE tenant_id = :t AND id IN (' . implode(', ', $marks) . ')', $params) as $r) {
            $out[(int) $r['id']] = ['id' => (int) $r['id'], 'workflow' => (string) $r['workflow']];
        }

        return $out;
    }

    /** Papier créé depuis le contrôle : lien posé par l'opérateur, visible dans le widget Liaisons (jamais bloquant). */
    private function traceOrigin(int $tenantId, int $docId, int $checkId, ?int $userId): void
    {
        if ($userId === null || $userId < 1) {
            return;
        }
        SseProvenance::manual($tenantId, ['paper', $docId], ['check', $checkId], 'created_from', 'Papier consigné depuis ce contrôle : relevé de l’ATAK repris sans ressaisie.', $userId);
    }

    /** @param array<string, mixed> $after */
    private function audit(int $tenantId, ?int $userId, int $docId, array $after): void
    {
        try {
            (new SseAuditLogRepository())->write([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'actor_label' => 'Registre des papiers',
                'action' => 'id_document.observe_from_check',
                'object_type' => 'sse_id_document',
                'object_id' => $docId,
                'after' => $after,
            ]);
        } catch (\Throwable $e) {
            error_log('Registre des papiers : audit indisponible : ' . $e->getMessage());
        }
    }
}
