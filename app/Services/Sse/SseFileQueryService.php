<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseFileRepository;
use App\Repositories\SseIdCheckRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SsePersonRepository;
use App\Support\SseFileCatalog;
use App\Support\SseIdentityCatalog;

/**
 * « Passage aux fichiers » : consultation des fichiers de la communauté (QW personne, QV véhicule, QA document,
 * QB biométrie), depuis le site ou le jeu, et envois du jeu (consultation différée, fiche SEEK II).
 *
 * Répond comme le fichier de l'État : jamais la vérité de jeu (nature de l'identité, couverture, faux). Un faux n'existe
 * pas dans les fichiers ; une couverture y est en règle et n'est jamais reliée à la personne réelle. Pour un papier, la
 * réponse de base est celle du contrôle N3 (SseIdDocumentLookupService, inchangée) ; s'y ajoutent les mentions, les
 * autres papiers, les contrôles subis, la fiche SEEK II et le numéro de requête. Chaque consultation est journalisée.
 */
final class SseFileQueryService
{
    /** Envois du jeu traités ici (POST /api/sse/identity/checks). */
    public const KIND_QUERY = 'file_query';

    public const KIND_SEEK = 'seek_record';

    /** Relevé d'un papier en jeu (bouton « RELEVER ») : contrôle N0 et ligne de journal QA. */
    public const KIND_RECORD = 'id_record';

    public const KINDS = [self::KIND_QUERY, self::KIND_SEEK, self::KIND_RECORD];

    /** Résultat de l'examen visuel d'un relevé → verdict du contrôle. */
    private const VISUAL_TO_VERDICT = ['ras' => 'ras', 'expire' => 'expire', 'doute' => 'signale', 'faux' => 'signale', 'signale' => 'signale'];

    /** Ordre de gravité des verdicts (le premier l'emporte). */
    private const PRECEDENCE = ['hvt', 'recherche', 'vole', 'revoque', 'expire', 'signale', 'ras', 'inconnu'];

    public function __construct(
        private ?SseFileRepository $files = null,
        private ?SseIdDocumentLookupService $docLookup = null,
        private ?SseIdDocumentRepository $documents = null,
        private ?SseIdentityRepository $identities = null,
        private ?SseIdCheckRepository $checks = null,
        private ?SseCrossMatchService $cross = null,
        private ?SsePersonRepository $persons = null,
        private ?SseIdCheckService $checkService = null,
    ) {
        $this->files ??= new SseFileRepository();
        $this->documents ??= new SseIdDocumentRepository();
        $this->identities ??= new SseIdentityRepository();
        $this->checks ??= new SseIdCheckRepository();
        $this->persons ??= new SsePersonRepository();
        $this->cross ??= new SseCrossMatchService(null, $this->persons);
        $this->docLookup ??= new SseIdDocumentLookupService($this->documents, $this->cross, $this->persons);
    }

    /**
     * Paramètres du jeu (6 paramètres fixes de la DLL) → type de requête et critères.
     *
     * @param array{country?: string, type?: string, number?: string, last?: string, first?: string, dob?: string} $q
     * @return array{0: string, 1: array<string, string>}
     */
    public static function fromGameParams(array $q): array
    {
        $raw = trim((string) ($q['type'] ?? ''));
        $type = strtoupper($raw);
        $criteria = [
            'last' => trim((string) ($q['last'] ?? '')),
            'first' => trim((string) ($q['first'] ?? '')),
            'dob' => trim((string) ($q['dob'] ?? '')),
            'number' => trim((string) ($q['number'] ?? '')),
            'country' => strtoupper(trim((string) ($q['country'] ?? ''))),
            'doc_type' => '',
        ];
        if (in_array($type, ['QW', 'QV', 'QB'], true)) {
            return [$type, $criteria];
        }
        // Tout autre type (vide, « QA », id_card, passport…) : papier, comme le contrôle N3.
        $criteria['doc_type'] = $type === 'QA' ? '' : $raw;

        return ['QA', $criteria];
    }

    /**
     * Consultation du jeu (GET /api/sse/identity/documents/lookup) : réponse au format de la DLL.
     *
     * @param array{country?: string, type?: string, number?: string, last?: string, first?: string, dob?: string} $q
     * @return array<string, mixed>
     */
    public function gameLookup(int $tenantId, array $q, ?int $userId, string $source = 'game'): array
    {
        [$type, $criteria] = self::fromGameParams($q);

        return $this->run($tenantId, $type, $criteria, [
            'source' => $source,
            'operator_user_id' => $userId,
            'operator_label' => $userId === null ? 'Jeu' : null,
        ])['answer'];
    }

    /**
     * Consultation complète : réponse du jeu (answer) et compte rendu détaillé pour le site (report).
     *
     * @param array<string, mixed> $criteria last, first, dob, number, country, doc_type
     * @param array{source?: string, operator_user_id?: ?int, operator_label?: ?string, operator_steam_id?: ?string} $ctx
     * @return array{answer: array<string, mixed>, report: array<string, mixed>, qrn: string, query_id: int}
     */
    public function run(int $tenantId, string $type, array $criteria, array $ctx = []): array
    {
        $type = isset(SseFileCatalog::QUERY_TYPES[$type]) ? $type : 'QA';
        $criteria = self::cleanCriteria($type, $criteria);
        $result = match ($type) {
            'QW' => $this->queryPerson($tenantId, $criteria),
            'QV' => $this->queryVehicle(),
            'QB' => $this->queryBiometric($tenantId, $criteria),
            default => $this->queryDocument($tenantId, $criteria),
        };

        $flags = SseFileCatalog::orderFlags($result['flags']);
        $source = isset(SseFileCatalog::SOURCES[(string) ($ctx['source'] ?? '')]) ? (string) $ctx['source'] : 'web';
        $logged = ['id' => 0, 'qrn' => ''];
        try {
            $logged = $this->files->logQuery($tenantId, [
                'query_type' => $type,
                'criteria' => array_filter($criteria, static fn (string $v): bool => $v !== ''),
                'source' => $source,
                'operator_user_id' => $ctx['operator_user_id'] ?? null,
                'operator_label' => $ctx['operator_label'] ?? null,
                'operator_steam_id' => $ctx['operator_steam_id'] ?? null,
                'hit' => $result['found'],
                'verdict' => $result['verdict'],
                'codes' => $flags,
            ]);
        } catch (\Throwable $e) {
            // Journal indisponible (étape 4330 pas encore passée) : la réponse part quand même.
            error_log('Passage aux fichiers : journal indisponible : ' . $e->getMessage());
        }
        $qrn = (string) $logged['qrn'];

        $answer = [
            'found' => $result['found'],
            'verdict' => $result['verdict'],
            'ref' => $result['ref'],
            'level' => $result['level'],
            'flags' => SseFileCatalog::orderFlags($flags, $qrn !== '' ? $qrn : null),
            'updated_at' => $result['updated_at'],
        ];
        if (isset($result['holder'])) {
            $answer['holder'] = $result['holder'];
        }

        return [
            'answer' => $answer,
            'report' => $result['report'] + [
                'qrn' => $qrn,
                'type' => $type,
                'type_label' => SseFileCatalog::QUERY_TYPES[$type],
                'criteria' => $criteria,
                'source' => $source,
                'source_label' => SseFileCatalog::SOURCES[$source],
                'at' => gmdate('Y-m-d H:i:s'),
                'hit' => $result['found'],
                'verdict' => $result['verdict'],
                'verdict_label' => SseIdentityCatalog::label(SseIdentityCatalog::VERDICTS, $result['verdict'], 'Sans verdict'),
                'level' => $result['level'],
                'flags' => $answer['flags'],
                'holder' => $result['holder'] ?? null,
            ],
            'qrn' => $qrn,
            'query_id' => (int) $logged['id'],
        ];
    }

    // ------------------------------------------------------------- Requêtes

    /**
     * QA : papier. Réponse de base du contrôle N3 ; sans pays ou sans type, le numéro est cherché partout.
     *
     * @param array<string, string> $c
     * @return array<string, mixed>
     */
    private function queryDocument(int $tenantId, array $c): array
    {
        $number = SseIdDocumentRepository::normalizeNumber($c['number']);
        $doc = null;
        if ($number !== '') {
            if ($c['country'] !== '') {
                $doc = $this->documents->findOfficialByNumber($tenantId, $c['country'], $c['doc_type'], $number);
            } else {
                foreach ($this->documents->list($tenantId, ['q' => $number, 'doc_type' => $c['doc_type'], 'limit' => 50]) as $d) {
                    if ($d['doc_number'] === $number && self::isOfficial($d)) {
                        $doc = $d;
                        break;
                    }
                }
            }
        }
        $base = $this->docLookup->lookup($tenantId, [
            'country' => $doc !== null ? (string) $doc['country_code'] : $c['country'],
            'type' => $doc !== null ? (string) $doc['doc_type'] : $c['doc_type'],
            'number' => $number,
            'last' => $c['last'],
            'first' => $c['first'],
            'dob' => $c['dob'],
        ]);
        $result = [
            'found' => (bool) $base['found'],
            'verdict' => (string) $base['verdict'],
            'ref' => (string) $base['ref'],
            'level' => (string) $base['level'],
            'flags' => array_values(array_map('strval', $base['flags'])),
            'updated_at' => $base['updated_at'] ?? null,
            'report' => self::emptyReport(),
        ];
        if (isset($base['holder'])) {
            $result['holder'] = $base['holder'];
        }
        if ($doc === null || !$base['found']) {
            $result['report']['watch'] = $base['found'] ? [] : array_filter([$this->topWatch($tenantId, $c['last'], $c['first'])]);

            return $result;
        }

        $identityId = (int) $doc['identity_id'];
        $identity = $this->identities->find($identityId, $tenantId);
        $docs = [$doc];
        foreach ($this->documents->list($tenantId, ['identity_id' => $identityId, 'limit' => 20]) as $d) {
            if ((int) $d['id'] !== (int) $doc['id'] && self::isOfficial($d)) {
                $docs[] = $d;
            }
        }
        $mentions = $this->safe(fn (): array => $this->files->activeMentionsFor($tenantId, [$identityId], [(int) $doc['id']]));
        $checks = $this->checks->list($tenantId, ['document_id' => (int) $doc['id'], 'limit' => 50]);
        $seek = $this->safe(fn (): array => $this->files->seekFor($tenantId, ['doc_number' => $number, 'limit' => 10]));
        $this->applyMentions($result, $mentions);
        $result['flags'] = array_merge($result['flags'], self::docFlags($docs), self::checkFlags($checks), self::seekFlags($seek));
        if (isset($result['holder']) && ($identity['birth_date'] ?? null) !== null) {
            $result['flags'][] = 'dob:' . $identity['birth_date'];
        }
        $result['report'] = [
            'identities' => $identity !== null ? [self::identitySummary($identity)] : [],
            'documents' => array_map([self::class, 'documentSummary'], $docs),
            'mentions' => $mentions,
            'watch' => [],
            'seek' => $seek,
            'checks' => $checks,
        ];

        return $result;
    }

    /**
     * QW : personne (nom, prénom, date de naissance) dans le registre des identités et sur les listes.
     *
     * @param array<string, string> $c
     * @return array<string, mixed>
     */
    private function queryPerson(int $tenantId, array $c): array
    {
        $found = [];
        if ($c['last'] !== '' || $c['first'] !== '') {
            $dob = self::normDate($c['dob']);
            foreach ($this->identities->list($tenantId, ['q' => $c['last'] !== '' ? $c['last'] : $c['first'], 'limit' => 200]) as $i) {
                if ($i['kind'] === 'forged'
                    || ($c['last'] !== '' && self::norm($c['last']) !== self::norm((string) $i['last_name']))
                    || ($c['first'] !== '' && self::norm($c['first']) !== self::norm((string) $i['first_name']))
                    || ($dob !== null && $i['birth_date'] !== null && $i['birth_date'] !== $dob)) {
                    continue;
                }
                $found[] = $i;
            }
        }
        $watch = $this->topWatch($tenantId, $c['last'], $c['first']);

        return $this->personResult($tenantId, $found, $watch, []);
    }

    /**
     * QB : clé biométrique ou numéro d'enregistrement SEEK II (REC-…).
     *
     * @param array<string, string> $c
     * @return array<string, mixed>
     */
    private function queryBiometric(int $tenantId, array $c): array
    {
        $key = trim($c['number']);
        $seek = [];
        $bioKey = '';
        if ($key !== '') {
            if (preg_match('/^REC-/i', $key) === 1) {
                $rec = $this->safe(fn (): ?array => $this->files->findSeekByRec($tenantId, strtoupper($key)));
                $bioKey = (string) ($rec['bio_key'] ?? '');
                $seek = $bioKey !== '' ? $this->safe(fn (): array => $this->files->seekFor($tenantId, ['bio_key' => $bioKey])) : array_filter([$rec]);
            } else {
                $bioKey = $key;
                $seek = $this->safe(fn (): array => $this->files->seekFor($tenantId, ['bio_key' => $bioKey]));
            }
        }
        $byId = [];
        // Identité rattachée à la dernière fiche d'abord (la plus sûre), puis celles qui portent la clé.
        foreach ($seek as $s) {
            if (($s['identity_id'] ?? null) !== null && !isset($byId[$s['identity_id']])) {
                $i = $this->identities->find((int) $s['identity_id'], $tenantId);
                if ($i !== null) {
                    $byId[(int) $i['id']] = $i;
                }
            }
        }
        foreach ($bioKey !== '' ? $this->safe(fn (): array => $this->files->identitiesByBioKey($tenantId, $bioKey)) : [] as $i) {
            $byId[(int) $i['id']] ??= $i;
        }
        $found = array_values(array_filter($byId, static fn (array $i): bool => $i['kind'] !== 'forged'));
        $result = $this->personResult($tenantId, $found, null, $seek);
        if (!$result['found'] && $seek !== []) {
            $top = $seek[0];
            $result['found'] = true;
            $result['ref'] = (string) $top['rec'];
            $result['verdict'] = $top['verdict'] === 'signale' ? 'signale' : 'ras';
            if (!$top['subject_unknown'] && trim((string) $top['last_name'] . (string) $top['first_name']) !== '') {
                $result['holder'] = ['last_name' => (string) $top['last_name'], 'first_name' => (string) $top['first_name'], 'level' => $result['level']];
                if (self::normDate((string) $top['birth_date']) !== null) {
                    $result['flags'][] = 'dob:' . self::normDate((string) $top['birth_date']);
                }
            }
            $result['updated_at'] = self::iso($top['updated_at'] ?? $top['created_at']);
        }
        if ($seek !== [] && $seek[0]['verdict'] === 'signale' && !in_array('flagged', $result['flags'], true)) {
            $result['flags'][] = 'flagged';
            $result['verdict'] = self::worst($result['verdict'], 'signale');
        }

        return $result;
    }

    /** QV : véhicule. Le registre de la communauté n'a pas de fichier des véhicules : NO HIT propre. */
    private function queryVehicle(): array
    {
        return [
            'found' => false,
            'verdict' => 'inconnu',
            'ref' => '',
            'level' => '',
            'flags' => ['no_vehicle_file'],
            'updated_at' => null,
            'report' => self::emptyReport(),
        ];
    }

    /**
     * Réponse « personne » commune à QW et QB.
     *
     * @param list<array<string, mixed>> $identities
     * @param array<string, mixed>|null $watch
     * @param list<array<string, mixed>> $seek
     * @return array<string, mixed>
     */
    private function personResult(int $tenantId, array $identities, ?array $watch, array $seek): array
    {
        $result = [
            'found' => $identities !== [] || $watch !== null,
            'verdict' => $identities !== [] || $watch !== null ? 'ras' : 'inconnu',
            'ref' => '',
            'level' => '',
            'flags' => [],
            'updated_at' => null,
            'report' => self::emptyReport(),
        ];
        $ids = array_map(static fn (array $i): int => (int) $i['id'], $identities);
        $docs = [];
        foreach (array_slice($identities, 0, 10) as $i) {
            foreach ($this->documents->list($tenantId, ['identity_id' => (int) $i['id'], 'limit' => 20]) as $d) {
                if (self::isOfficial($d)) {
                    $docs[] = $d;
                }
            }
        }
        $docIds = array_map(static fn (array $d): int => (int) $d['id'], $docs);
        $seekIds = array_map(static fn (array $s): int => (int) $s['id'], $seek);
        $mentions = $this->safe(fn (): array => $this->files->activeMentionsFor($tenantId, $ids, $docIds, $seekIds));
        $checks = $ids !== [] || $docIds !== [] ? $this->checks->listForSubject($tenantId, $ids, $docIds, '', 200) : [];
        if ($seek === [] && $ids !== []) {
            $seek = $this->safe(fn (): array => $this->files->seekFor($tenantId, ['identity_ids' => $ids, 'limit' => 10]));
        }

        $primary = $identities[0] ?? null;
        if ($watch !== null) {
            $result['flags'][] = 'watchlist';
            $result['level'] = $watch['level'];
            $result['verdict'] = self::worst($result['verdict'], $watch['level'] === 'prioritaire' ? 'recherche' : 'signale');
            $result['ref'] = $watch['ref'];
        }
        // Fiche personne prioritaire : jamais à travers une couverture (elle n'est pas reliée à la personne réelle).
        foreach ($identities as $i) {
            if (!in_array($i['kind'], ['cover', 'forged'], true) && (int) ($i['person_id'] ?? 0) > 0) {
                $p = $this->persons->findById((int) $i['person_id'], $tenantId);
                if (is_array($p) && SsePersonRepository::normalizeStatus((string) ($p['status'] ?? '')) === SsePersonRepository::STATUS_PRIORITAIRE) {
                    $result['flags'][] = 'person_priority';
                    $result['level'] = 'hvt';
                    $result['verdict'] = 'hvt';
                    break;
                }
            }
        }
        $this->applyMentions($result, $mentions);
        if ($primary !== null) {
            $result['ref'] = (string) $primary['ref'];
            $result['holder'] = ['last_name' => (string) $primary['last_name'], 'first_name' => (string) $primary['first_name'], 'level' => $result['level']];
            if ($primary['birth_date'] !== null) {
                $result['flags'][] = 'dob:' . $primary['birth_date'];
            }
            $result['updated_at'] = self::iso($primary['updated_at'] ?? $primary['created_at']);
        } elseif ($watch !== null) {
            $result['holder'] = ['last_name' => (string) $watch['last_name'], 'first_name' => (string) $watch['first_name'], 'level' => $watch['level']];
        }
        $result['flags'] = array_merge($result['flags'], self::docFlags($docs), self::checkFlags($checks), self::seekFlags($seek));
        $result['report'] = [
            'identities' => array_map([self::class, 'identitySummary'], $identities),
            'documents' => array_map([self::class, 'documentSummary'], $docs),
            'mentions' => $mentions,
            'watch' => $watch !== null ? [$watch] : [],
            'seek' => $seek,
            'checks' => $checks,
        ];

        return $result;
    }

    // ------------------------------------------------------- Envois du jeu

    /**
     * Consultation faite en jeu hors liaison, remontée plus tard (kind file_query) : ligne de journal « différée ».
     *
     * @param array<string, mixed> $body
     * @return array{id: int, ref: string, duplicate: bool}|array{error: string}
     */
    public function storeDeferred(int $tenantId, array $body, ?int $userId): array
    {
        $key = trim((string) ($body['idempotency_key'] ?? ''));
        if (!SseIdCheckService::validKey($key)) {
            return ['error' => 'idempotency_key_required'];
        }
        $type = strtoupper(trim(is_scalar($body['type'] ?? null) ? (string) $body['type'] : ''));
        $type = isset(SseFileCatalog::QUERY_TYPES[$type]) ? $type : 'QA';
        $query = is_array($body['query'] ?? null) ? $body['query'] : [];
        $criteria = [];
        foreach (['last', 'first', 'dob', 'number', 'country', 'doc_type', 'bio_key', 'plate'] as $k) {
            $v = self::str($query[$k] ?? null, 120);
            if ($v !== null) {
                $criteria[$k] = $v;
            }
        }
        $codes = [];
        foreach (is_array($body['codes'] ?? null) ? $body['codes'] : [] as $code) {
            $code = self::str($code, 64);
            if ($code !== null && count($codes) < 32) {
                $codes[] = str_replace(',', ' ', $code);
            }
        }
        $source = is_string($body['source'] ?? null) && in_array($body['source'], SseFileCatalog::DEFERRED_SOURCES, true) ? $body['source'] : 'game';
        $controller = is_array($body['controller'] ?? null) ? $body['controller'] : [];
        $steam = ($s = preg_replace('/\D/', '', (string) ($controller['steam_id'] ?? ''))) !== '' ? substr((string) $s, 0, 32) : null;
        $qrn = self::str($body['qrn'] ?? null, 32);
        $hit = $body['hit'] ?? false;

        $logged = $this->files->logQuery($tenantId, [
            'query_type' => $type,
            'criteria' => $criteria,
            'source' => $source,
            'operator_user_id' => $userId,
            'operator_label' => self::str($controller['callsign'] ?? null, 80),
            'operator_steam_id' => $steam,
            'hit' => $hit === true || $hit === 1 || $hit === '1' || $hit === 'true',
            'verdict' => self::str($body['verdict'] ?? null, 16),
            'codes' => $codes,
            'deferred' => true,
            'idempotency_key' => $key,
            'qrn' => $qrn !== null && preg_match('/^[A-Za-z0-9_.:\-]{3,32}$/', $qrn) === 1 ? $qrn : null,
            'game_time' => self::str($body['game_time'] ?? null, 32),
            'grid' => self::str($body['grid'] ?? null, 32),
        ]);

        return ['id' => $logged['id'], 'ref' => $logged['qrn'], 'duplicate' => $logged['duplicate']];
    }

    /**
     * Fiche biométrique SEEK II (kind seek_record) : fiche créée ou mise à jour, et contrôle N4 enregistré par le service
     * des contrôles (il apparaît dans « Contrôles subis » de la personne), rattaché au registre de la même façon.
     *
     * @param array<string, mixed> $body
     * @return array{id: int, ref: string, check_ref: string, duplicate: bool}|array{error: string}
     */
    public function storeSeek(int $tenantId, int $mapId, array $body): array
    {
        $key = trim((string) ($body['idempotency_key'] ?? ''));
        if (!SseIdCheckService::validKey($key)) {
            return ['error' => 'idempotency_key_required'];
        }
        $existing = $this->files->findSeekByKey($tenantId, $key);
        if ($existing !== null) {
            $check = $existing['check_id'] !== null ? SseIdentityCatalog::checkRef((int) $existing['check_id']) : '';

            return ['id' => (int) $existing['id'], 'ref' => (string) $existing['rec'], 'check_ref' => $check, 'duplicate' => true];
        }
        $arr = static fn (mixed $v): array => is_array($v) ? $v : [];
        $bool = static fn (mixed $v): bool => $v === true || $v === 1 || $v === '1' || $v === 'true';
        $subject = $arr($body['subject'] ?? null);
        $document = $arr($body['document'] ?? null);
        $mod = $arr($body['modalities'] ?? null);
        $quality = $arr($body['quality'] ?? null);
        $controller = $arr($body['controller'] ?? null);
        $digits = static fn (mixed $v): ?string => ($s = preg_replace('/\D/', '', is_scalar($v) ? (string) $v : '')) !== '' ? substr((string) $s, 0, 32) : null;

        $rec = strtoupper((string) self::str($body['rec'] ?? null, 40));
        $rec = preg_match('/^[A-Z0-9_.:\-]{3,40}$/', $rec) === 1 ? $rec : '';
        $verdict = is_string($body['verdict'] ?? null) && isset(SseFileCatalog::SEEK_VERDICTS[$body['verdict']]) ? $body['verdict'] : 'inconnu';
        $unknown = $bool($subject['unknown'] ?? false);
        $docType = SseIdentityCatalog::pick(SseIdentityCatalog::DOC_TYPES, $document['type'] ?? null, '');
        $docCountry = strtoupper((string) self::str($document['country'] ?? null, 3));
        $docNumber = SseIdDocumentRepository::normalizeNumber((string) ($document['number'] ?? ''));
        $q = [];
        foreach (['fp', 'iris', 'face'] as $k) {
            if (is_numeric($quality[$k] ?? null)) {
                $q[$k] = max(0, min(100, (int) $quality[$k]));
            }
        }
        $dob = self::str($subject['dob'] ?? null, 16);
        $bioKey = self::str($body['bio_key'] ?? ($subject['bio_key'] ?? null), 64);
        $controllerSteam = $digits($controller['steam_id'] ?? null);
        $op = self::str($body['op'] ?? null, 64);
        $grid = self::str($body['grid'] ?? null, 32);
        $gameTime = self::str($body['game_time'] ?? null, 32);

        $stored = $this->files->upsertSeek($tenantId, [
            'rec' => $rec,
            'idempotency_key' => $key,
            'bio_key' => $bioKey,
            'last_name' => $unknown ? null : self::str($subject['last'] ?? null, 120),
            'first_name' => $unknown ? null : self::str($subject['first'] ?? null, 120),
            'birth_date' => $unknown ? null : $dob,
            'subject_unknown' => $unknown ? 1 : 0,
            'subject_steam_id' => $digits($subject['steam_id'] ?? null),
            'subject_netid' => self::str($subject['netid'] ?? null, 64),
            'doc_type' => $docType !== '' ? $docType : null,
            'doc_country' => $docCountry !== '' ? $docCountry : null,
            'doc_number' => $docNumber !== '' ? $docNumber : null,
            'verdict' => $verdict,
            'enrolled' => $bool($body['enroll'] ?? false) ? 1 : 0,
            'mod_fingers' => $bool($mod['fingers'] ?? false) ? 1 : 0,
            'mod_iris' => $bool($mod['iris'] ?? false) ? 1 : 0,
            'mod_face' => $bool($mod['face'] ?? false) ? 1 : 0,
            'quality_json' => $q !== [] ? (string) json_encode($q) : null,
            'grid' => $grid,
            'game_time' => $gameTime,
            'controller_callsign' => self::str($controller['callsign'] ?? null, 80),
            'controller_steam_id' => $controllerSteam,
            'reason' => self::str($body['reason'] ?? null, 255),
            'unit' => self::str($body['unit'] ?? null, 80),
            'context_id' => $mapId > 0 ? $mapId : null,
            'op_key' => $op,
        ]);

        // Contrôle N4 : même service, mêmes rattachements (papier, fiche, clé biométrique) que les autres contrôles.
        $checkKey = strlen($key) <= 75 ? 'seek:' . $key : 'seek:' . sha1($key);
        $check = ($this->checkService ??= new SseIdCheckService())->store($tenantId, $mapId, [
            'idempotency_key' => $checkKey,
            'document' => ['type' => $docType, 'country' => $docCountry, 'number' => $docNumber],
            'presented_name' => $unknown ? [] : ['last' => $subject['last'] ?? null, 'first' => $subject['first'] ?? null, 'dob' => $dob],
            'subject' => ['bio_key' => $bioKey, 'steam_id' => $subject['steam_id'] ?? null, 'netid' => $subject['netid'] ?? null],
            'controller' => $controller,
            'levels' => ['N4'],
            'results' => ['N4' => array_filter([
                'source' => 'seek',
                'rec' => $stored['rec'],
                'verdict' => $verdict,
                'enroll' => $bool($body['enroll'] ?? false),
                'modalities' => ['fingers' => $bool($mod['fingers'] ?? false), 'iris' => $bool($mod['iris'] ?? false), 'face' => $bool($mod['face'] ?? false)],
                'quality' => $q,
                'reason' => self::str($body['reason'] ?? null, 255),
            ], static fn (mixed $v): bool => $v !== null && $v !== [])],
            'verdict' => SseFileCatalog::SEEK_TO_CHECK_VERDICT[$verdict],
            'grid' => $grid,
            'game_time' => $gameTime,
            'op' => $op,
        ]);
        $checkRef = '';
        if (!isset($check['error'])) {
            $checkRef = (string) $check['ref'];
            $row = $this->checks->find((int) $check['id'], $tenantId);
            $this->files->linkSeek(
                $stored['id'],
                $tenantId,
                $row['identity_id'] ?? null,
                $row['person_id'] ?? null,
                $row['document_id'] ?? null,
                (int) $check['id']
            );
        }

        return ['id' => $stored['id'], 'ref' => $stored['rec'], 'check_ref' => $checkRef, 'duplicate' => false];
    }

    /**
     * Relevé d'un papier fait en jeu (kind id_record) : contrôle d'identité N0 (source « relevé », dans « Contrôles
     * subis ») et ligne de journal QA (source idcheck). Idempotent par clé.
     *
     * @param array<string, mixed> $body
     * @return array{id: int, ref: string, qrn: string, duplicate: bool}|array{error: string}
     */
    public function storeRecord(int $tenantId, int $mapId, array $body, ?int $userId): array
    {
        $key = trim((string) ($body['idempotency_key'] ?? ''));
        if (!SseIdCheckService::validKey($key)) {
            return ['error' => 'idempotency_key_required'];
        }
        $arr = static fn (mixed $v): array => is_array($v) ? $v : [];
        $document = $arr($body['document'] ?? null);
        $controller = $arr($body['controller'] ?? null);
        $visual = strtolower((string) self::str($body['visual'] ?? null, 16));
        $levels = array_values(array_filter(is_array($body['levels'] ?? null) ? $body['levels'] : [], 'is_scalar'));
        $check = ($this->checkService ??= new SseIdCheckService())->store($tenantId, $mapId, [
            'idempotency_key' => $key,
            'document' => $document,
            'presented_name' => $arr($body['presented_name'] ?? null),
            'subject' => $arr($body['subject'] ?? null),
            'controller' => $controller,
            'levels' => $levels !== [] ? $levels : ['N0'],
            'results' => ['N0' => array_filter([
                'source' => 'releve',
                'visual' => $visual !== '' ? $visual : null,
                'expires' => self::str($body['expires'] ?? null, 16),
                'local_id' => self::str($document['local_id'] ?? null, 64),
            ], static fn (mixed $v): bool => $v !== null)],
            'verdict' => self::VISUAL_TO_VERDICT[$visual] ?? '',
            'grid' => $body['grid'] ?? null,
            'pos' => $body['pos'] ?? null,
            'game_time' => $body['game_time'] ?? null,
            'op' => $body['op'] ?? null,
        ]);
        if (isset($check['error'])) {
            return ['error' => (string) $check['error']];
        }
        $qrn = '';
        try {
            $row = $this->checks->find((int) $check['id'], $tenantId) ?? [];
            $steam = ($s = preg_replace('/\D/', '', (string) ($controller['steam_id'] ?? ''))) !== '' ? substr((string) $s, 0, 32) : null;
            $qrn = $this->files->logQuery($tenantId, [
                'query_type' => 'QA',
                'criteria' => array_filter([
                    'number' => (string) ($row['doc_number'] ?? ''),
                    'country' => (string) ($row['country_code'] ?? ''),
                    'doc_type' => (string) ($row['doc_type'] ?? ''),
                    'last' => (string) ($row['presented_last'] ?? ''),
                    'first' => (string) ($row['presented_first'] ?? ''),
                    'dob' => (string) ($row['presented_dob'] ?? ''),
                ], static fn (string $v): bool => $v !== ''),
                'source' => 'idcheck',
                'operator_user_id' => $userId,
                'operator_label' => self::str($controller['callsign'] ?? null, 80),
                'operator_steam_id' => $steam,
                'hit' => ($row['document_id'] ?? null) !== null,
                'verdict' => self::VISUAL_TO_VERDICT[$visual] ?? null,
                'codes' => array_values(array_filter([$visual !== '' ? 'visual:' . $visual : null, 'check:' . $check['ref']])),
                'idempotency_key' => strlen($key) <= 75 ? 'rel:' . $key : 'rel:' . sha1($key),
                'game_time' => self::str($body['game_time'] ?? null, 32),
                'grid' => self::str($body['grid'] ?? null, 32),
            ])['qrn'];
        } catch (\PDOException $e) {
            // Journal indisponible (étape 4330) : le contrôle est enregistré quand même.
            error_log('Passage aux fichiers : relevé non journalisé : ' . $e->getMessage());
        }

        return ['id' => (int) $check['id'], 'ref' => (string) $check['ref'], 'qrn' => $qrn, 'duplicate' => (bool) $check['duplicate']];
    }

    // --------------------------------------------------------------- Outils

    /**
     * Mentions actives → codes, verdict et niveau.
     *
     * @param array<string, mixed> $result
     * @param list<array<string, mixed>> $mentions
     */
    private function applyMentions(array &$result, array $mentions): void
    {
        foreach ($mentions as $m) {
            $code = (string) $m['mention'];
            if (!isset(SseFileCatalog::MENTIONS[$code])) {
                continue;
            }
            $result['flags'][] = $code;
            $wanted = in_array($code, SseFileCatalog::MENTIONS_WANTED, true);
            $result['verdict'] = self::worst($result['verdict'], $wanted ? 'recherche' : 'signale');
            $result['level'] = self::maxLevel($result['level'], $wanted ? 'prioritaire' : 'surveillance');
        }
        if (isset($result['holder'])) {
            $result['holder']['level'] = self::maxLevel((string) $result['holder']['level'], $result['level']);
        }
    }

    /**
     * @param list<array<string, mixed>> $docs
     * @return list<string>
     */
    private static function docFlags(array $docs): array
    {
        $out = [];
        foreach (array_slice($docs, 0, SseFileCatalog::MAX_DOC_FLAGS) as $d) {
            $out[] = 'doc:' . $d['country_code'] . '|' . $d['doc_type'] . '|' . $d['doc_number'] . '|' . self::docState($d);
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $checks
     * @return list<string>
     */
    private static function checkFlags(array $checks): array
    {
        if ($checks === []) {
            return [];
        }
        $last = self::iso($checks[0]['created_at'] ?? null);

        return array_values(array_filter(['checks:' . count($checks), $last !== null ? 'lastcheck:' . $last : null]));
    }

    /**
     * @param list<array<string, mixed>> $seek
     * @return list<string>
     */
    private static function seekFlags(array $seek): array
    {
        return isset($seek[0]['rec']) && $seek[0]['rec'] !== '' ? ['seek:' . $seek[0]['rec']] : [];
    }

    /** État d'un papier tel que le fichier le donne : ok, stolen, lost, revoked, suspended, expired. */
    public static function docState(array $d): string
    {
        return match (true) {
            ($d['st_report'] ?? '') === 'stolen' => 'stolen',
            ($d['st_report'] ?? '') === 'lost' => 'lost',
            ($d['workflow'] ?? '') === 'revoked' || ($d['st_validity'] ?? '') === 'revoked' => 'revoked',
            ($d['st_validity'] ?? '') === 'suspended' => 'suspended',
            !empty($d['expired']) || ($d['st_validity'] ?? '') === 'expired' => 'expired',
            default => 'ok',
        };
    }

    /** Papier « des fichiers » : émis ou révoqué, jamais un faux (même règle que findOfficialByNumber). */
    private static function isOfficial(array $d): bool
    {
        return in_array($d['workflow'] ?? '', ['issued', 'revoked'], true) && ($d['st_origin'] ?? '') !== 'forged'
            && ($d['identity']['kind'] ?? '') !== 'forged';
    }

    /**
     * Identité résumée pour l'écran : jamais sa nature (vérité de jeu).
     *
     * @return array<string, mixed>
     */
    private static function identitySummary(array $i): array
    {
        return [
            'id' => (int) $i['id'],
            'ref' => (string) $i['ref'],
            'display_name' => (string) $i['display_name'],
            'last_name' => (string) $i['last_name'],
            'first_name' => (string) $i['first_name'],
            'sex' => (string) ($i['sex'] ?? 'X'),
            'birth_date' => $i['birth_date'] ?? null,
            'birth_place' => $i['birth_place'] ?? null,
            'nationality' => $i['nationality'] ?? null,
            'profession' => $i['profession'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private static function documentSummary(array $d): array
    {
        $state = self::docState($d);

        return [
            'id' => (int) $d['id'],
            'ref' => (string) $d['ref'],
            'identity_id' => (int) $d['identity_id'],
            'doc_type' => (string) $d['doc_type'],
            'doc_type_label' => (string) $d['doc_type_label'],
            'country_code' => (string) $d['country_code'],
            'doc_number' => (string) $d['doc_number'],
            'issued_on' => $d['issued_on'] ?? null,
            'expires_on' => $d['expires_on'] ?? null,
            'state' => $state,
            'state_label' => SseFileCatalog::DOC_STATES[$state],
            'holder' => trim(mb_strtoupper((string) ($d['identity']['last_name'] ?? '')) . ' ' . (string) ($d['identity']['first_name'] ?? '')),
        ];
    }

    /** @return array<string, mixed>|null */
    private function topWatch(int $tenantId, string $last, string $first): ?array
    {
        if (trim($last . $first) === '') {
            return null;
        }
        $hits = $this->cross->matchOne(['last_name' => $last, 'first_name' => $first, 'display_name' => trim($first . ' ' . $last)], $tenantId);
        $entry = is_array($hits[0]['entry'] ?? null) ? $hits[0]['entry'] : null;
        if ($entry === null) {
            return null;
        }

        return [
            'id' => (int) ($entry['id'] ?? 0),
            'ref' => 'WL-' . (int) ($entry['id'] ?? 0),
            'level' => ($entry['threat_level'] ?? '') === 'prioritaire' ? 'prioritaire' : 'surveillance',
            'last_name' => (string) ($entry['last_name'] ?? ''),
            'first_name' => (string) ($entry['first_name'] ?? ''),
            'display_name' => (string) ($entry['display_name'] ?? ''),
            'notes' => $entry['notes'] ?? null,
        ];
    }

    /** @return array<string, list<array<string, mixed>>> */
    private static function emptyReport(): array
    {
        return ['identities' => [], 'documents' => [], 'mentions' => [], 'watch' => [], 'seek' => [], 'checks' => []];
    }

    /**
     * @param array<string, mixed> $c
     * @return array<string, string>
     */
    private static function cleanCriteria(string $type, array $c): array
    {
        $out = [];
        foreach (['last', 'first', 'dob', 'number', 'country', 'doc_type'] as $k) {
            $out[$k] = (string) (self::str($c[$k] ?? null, 120) ?? '');
        }
        $out['country'] = strtoupper(mb_substr($out['country'], 0, 3));
        if ($type === 'QA') {
            $out['doc_type'] = isset(SseIdentityCatalog::DOC_TYPES[$out['doc_type']]) ? $out['doc_type'] : '';
        } else {
            $out['doc_type'] = '';
        }
        if ($type === 'QV') {
            $out['number'] = (string) preg_replace('/[^A-Z0-9\-]/', '', strtoupper($out['number']));
        }

        return $out;
    }

    /** Lecture des tables du passage aux fichiers ; [] si elles manquent encore. */
    private function safe(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (\PDOException $e) {
            // Tables du passage aux fichiers absentes (étape 4330) : réponse sans mentions ni fiches SEEK II.
            error_log('Passage aux fichiers : ' . $e->getMessage());

            return [];
        }
    }

    private static function worst(string $a, string $b): string
    {
        $ia = array_search($a, self::PRECEDENCE, true);
        $ib = array_search($b, self::PRECEDENCE, true);

        return ($ib !== false && ($ia === false || $ib < $ia)) ? $b : $a;
    }

    private static function maxLevel(string $a, string $b): string
    {
        $rank = ['' => 0, 'surveillance' => 1, 'prioritaire' => 2, 'hvt' => 3];

        return ($rank[$b] ?? 0) > ($rank[$a] ?? 0) ? $b : $a;
    }

    private static function norm(string $s): string
    {
        return str_replace('<', '', SseMrzService::sanitize($s));
    }

    private static function normDate(string $s): ?string
    {
        $s = trim($s);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) === 1) {
            return $s;
        }
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $s, $m) === 1) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        return null;
    }

    private static function iso(mixed $dt): ?string
    {
        $s = trim((string) $dt);

        return $s === '' ? null : str_replace(' ', 'T', substr($s, 0, 19)) . 'Z';
    }

    private static function str(mixed $v, int $max): ?string
    {
        $s = trim(str_replace(["\r", "\n", "\t"], ' ', is_scalar($v) ? (string) $v : ''));

        return $s === '' ? null : mb_substr($s, 0, $max);
    }
}
