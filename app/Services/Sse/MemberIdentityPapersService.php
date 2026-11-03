<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseIdCheckRepository;
use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseIdKitRepository;
use App\Support\MemberName;
use App\Support\SseIdentityCatalog;

/**
 * COMSPEC IDENTITY — papiers d'un joueur dans sa fiche membre (back-office) et dans « Ma situation ».
 *
 * Identité principale (civile, tirée de la fiche du personnage) et identités d'emprunt (alias, couverture)
 * rattachées au compte (sse_identities.user_id) ; papiers de ces identités ; kit porté en mission
 * (sse_id_kits, envoyé au serveur de jeu avec le Steam ID) ; contrôles subis.
 *
 * Vérité de jeu (couverture, faux, qualité du faux) : seulement avec l'habilitation Confidentiel. Sans elle, les
 * identités de couverture ou falsifiées et leurs papiers n'apparaissent pas du tout. Le joueur voit ses propres
 * papiers, couverture comprise (il la porte), mais jamais la nature de l'identité ni l'origine du papier.
 */
final class MemberIdentityPapersService
{
    /** Natures et origines qui relèvent de la vérité de jeu. */
    public const SECRET = ['cover', 'forged'];

    /** Actions de la fiche (bouton → effet). */
    public const ACTIONS = ['issue', 'validate', 'draft', 'revoke', 'stolen', 'lost', 'clear_report', 'duplicate', 'delete'];

    /** @var callable(array<string, mixed>): void */
    private $audit;

    public function __construct(
        private ?SseIdentityRepository $identities = null,
        private ?SseIdDocumentRepository $documents = null,
        private ?SseIdKitRepository $kits = null,
        private ?SseIdCheckRepository $checks = null,
        private ?SseIdCountryRepository $countries = null,
        ?callable $audit = null,
    ) {
        $this->identities ??= new SseIdentityRepository();
        $this->documents ??= new SseIdDocumentRepository();
        $this->kits ??= new SseIdKitRepository();
        $this->checks ??= new SseIdCheckRepository();
        $this->countries ??= new SseIdCountryRepository();
        $this->audit = $audit ?? static function (array $entry): void {
            try {
                (new SseAuditLogRepository())->write($entry);
            } catch (\Throwable $e) {
                error_log('Papiers membre : journal d’audit indisponible : ' . $e->getMessage());
            }
        };
    }

    /**
     * Droits sur l'onglet « Identité & papiers » d'une fiche membre.
     *
     * - voir : gestion SSE, modification des dossiers RH, ou habilitation Encadrement ;
     * - gérer (créer, émettre, révoquer, kit) : gestion SSE, ou modification des dossiers + habilitation Encadrement ;
     * - vérité de jeu et couvertures : habilitation Confidentiel.
     *
     * @return array{view: bool, manage: bool, truth: bool, cover: bool}
     */
    public static function rights(bool $sseManage, bool $editProfiles, bool $command, bool $confidential): array
    {
        $manage = $sseManage || ($editProfiles && $command);

        return [
            'view' => $manage || $editProfiles || $command,
            'manage' => $manage,
            'truth' => $confidential,
            'cover' => $manage && $confidential,
        ];
    }

    /** @param array<string, mixed> $doc papier hydraté (SseIdDocumentRepository) */
    public static function docIsSecret(array $doc): bool
    {
        return in_array($doc['identity']['kind'] ?? '', self::SECRET, true) || in_array($doc['st_origin'] ?? '', self::SECRET, true);
    }

    /** « Prénom NOM » comme partout sur le site (MemberName). */
    public static function displayName(array $identity): string
    {
        return MemberName::full(['first_name' => (string) ($identity['first_name'] ?? ''), 'last_name' => (string) ($identity['last_name'] ?? '')]);
    }

    /**
     * Identité principale proposée depuis la fiche du personnage (user_profiles, puis dossier personnel).
     *
     * @param array<string, mixed> $userProfile first_name, last_name, birth_date, nationality
     * @param array<string, mixed> $personnel sex, birth_place, nationality
     * @param list<array<string, mixed>> $countries
     * @return array{last_name: string, first_name: string, birth_date: string, birth_place: string, sex: string, nationality: string, nationality_text: string}
     */
    public static function primaryProposal(array $userProfile, array $personnel, array $countries): array
    {
        $natText = trim((string) ($userProfile['nationality'] ?? '')) ?: trim((string) ($personnel['nationality'] ?? ''));
        $sexRaw = mb_strtolower(trim((string) ($personnel['sex'] ?? '')));
        $sex = match (true) {
            $sexRaw === '' => 'X',
            str_starts_with($sexRaw, 'h'), str_starts_with($sexRaw, 'm') => 'M',
            str_starts_with($sexRaw, 'f') => 'F',
            default => 'X',
        };
        $dob = trim((string) ($userProfile['birth_date'] ?? ''));

        return [
            'last_name' => trim((string) ($userProfile['last_name'] ?? '')),
            'first_name' => trim((string) ($userProfile['first_name'] ?? '')),
            'birth_date' => preg_match('/^\d{4}-\d{2}-\d{2}/', $dob) === 1 ? substr($dob, 0, 10) : '',
            'birth_place' => trim((string) ($personnel['birth_place'] ?? '')),
            'sex' => $sex,
            'nationality' => self::matchCountry($natText, $countries),
            'nationality_text' => $natText,
        ];
    }

    /** @param list<array<string, mixed>> $countries */
    private static function matchCountry(string $text, array $countries): string
    {
        $t = mb_strtolower(trim($text));
        if ($t === '') {
            return '';
        }
        $aliases = ['us' => 'USA', 'usa' => 'USA', 'américaine' => 'USA', 'américain' => 'USA', 'uk' => 'GBR', 'gb' => 'GBR', 'britannique' => 'GBR', 'fr' => 'FRA', 'française' => 'FRA', 'français' => 'FRA', 'belgique' => 'BEL', 'belge' => 'BEL', 'ger' => 'DEU', 'de' => 'DEU', 'allemande' => 'DEU', 'allemand' => 'DEU', 'espagne' => 'ESP', 'espagnole' => 'ESP', 'colombie' => 'COL', 'colombienne' => 'COL', 'autriche' => 'AUT', 'autrichienne' => 'AUT', 'grèce' => 'GRC', 'grecque' => 'GRC', 'grec' => 'GRC', 'gr' => 'GRC', 'pologne' => 'POL', 'polonaise' => 'POL', 'polonais' => 'POL', 'ukraine' => 'UKR', 'ukrainienne' => 'UKR', 'ukrainien' => 'UKR', 'russie' => 'RUS', 'russe' => 'RUS', 'irak' => 'IRQ', 'irakienne' => 'IRQ', 'irakien' => 'IRQ', 'syrie' => 'SYR', 'syrienne' => 'SYR', 'syrien' => 'SYR', 'afghane' => 'AFG', 'afghan' => 'AFG'];
        $t = mb_strtolower($aliases[$t] ?? trim($text));
        foreach ($countries as $c) {
            foreach ([(string) $c['code'], (string) $c['name'], (string) ($c['name_en'] ?? '')] as $candidate) {
                $cand = mb_strtolower(trim($candidate));
                if ($cand !== '' && $cand === $t) {
                    return (string) $c['code'];
                }
            }
        }

        return '';
    }

    /**
     * Contenu de l'onglet pour un responsable.
     *
     * @return array<string, mixed>
     */
    public function section(int $tenantId, int $userId, string $steamId, bool $truth): array
    {
        $countries = $this->countries->listForTenant($tenantId);
        $byCode = [];
        foreach ($this->countries->listForTenant($tenantId, false) as $c) {
            $byCode[$c['code']] = $c;
        }
        $identities = $this->identities->listForUser($tenantId, $userId);
        if (!$truth) {
            $identities = array_values(array_filter($identities, static fn (array $i): bool => !in_array($i['kind'], self::SECRET, true)));
        }
        $documents = $this->documents->list($tenantId, ['user_id' => $userId, 'limit' => 100]);
        if (!$truth) {
            $documents = array_values(array_filter($documents, static fn (array $d): bool => !self::docIsSecret($d)));
        }
        $identityById = [];
        foreach ($identities as $i) {
            $identityById[$i['id']] = $i;
        }
        foreach ($documents as $k => $d) {
            $identity = $identityById[$d['identity_id']] ?? null;
            $country = $byCode[$d['country_code']] ?? null;
            $documents[$k]['country_name'] = (string) ($country['name'] ?? $d['country_code']);
            $documents[$k]['mrz'] = $identity !== null ? SseMrzService::forDocument($d, $identity, (string) ($country['mrz_code'] ?? '')) : [];
            $documents[$k]['holder_name'] = $identity !== null ? self::displayName($identity) : '';
            $documents[$k]['secret'] = self::docIsSecret($d);
        }
        $visibleDocIds = array_map(static fn (array $d): int => (int) $d['id'], $documents);
        $kits = [];
        foreach ($this->kits->listForUser($tenantId, $userId) as $kit) {
            $kit['document_ids'] = array_values(array_intersect($kit['document_ids'], $visibleDocIds));
            $kits[] = $kit;
        }
        $checks = $this->checks->listForSubject($tenantId, array_keys($identityById), $visibleDocIds, $truth ? $steamId : '', 50);
        if (!$truth) {
            // Un contrôle d'un papier de couverture ne doit pas trahir la couverture.
            $allDocIds = array_map(static fn (array $d): int => (int) $d['id'], $this->documents->list($tenantId, ['user_id' => $userId, 'limit' => 200]));
            $hidden = array_diff($allDocIds, $visibleDocIds);
            $checks = array_values(array_filter($checks, static fn (array $c): bool => !in_array((int) ($c['document_id'] ?? 0), $hidden, true)));
        }
        $primary = null;
        foreach ($identities as $i) {
            if (!empty($i['is_primary'])) {
                $primary = $i;
            }
        }

        return [
            'countries' => $countries,
            'primary' => $primary,
            'aliases' => array_values(array_filter($identities, static fn (array $i): bool => empty($i['is_primary']))),
            'identities' => $identities,
            'documents' => $documents,
            'kits' => $kits,
            'checks' => $checks,
            'steam_linked' => preg_replace('/\D/', '', $steamId) !== '',
        ];
    }

    /**
     * Synthèse par membre pour la liste « Papiers des membres » du bureau SSE : compte Steam lié, papiers émis,
     * kit défini, dernier contrôle. Sans la vérité de jeu, les couvertures et faux ne sont ni comptés ni trahis
     * (un kit fait seulement de papiers de couverture apparaît « non défini », leurs contrôles sont ignorés).
     *
     * @param list<int> $userIds membres de la communauté (déjà filtrés sur le tenant)
     * @return array<int, array{steam_linked: bool, issued: int, kit: bool, last_check: ?string}>
     */
    public function roster(int $tenantId, array $userIds, bool $truth): array
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $i): bool => $i > 0))), 0, 500);
        if ($tenantId < 1 || $ids === []) {
            return [];
        }
        $db = \App\Core\Database::getInstance();
        $in = implode(', ', $ids);
        $mask = $truth ? '' : " AND i.kind NOT IN ('cover', 'forged') AND d.st_origin NOT IN ('cover', 'forged')";
        $docJoin = 'sse_id_documents d JOIN sse_identities i ON i.id = d.identity_id AND i.tenant_id = d.tenant_id';
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['steam_linked' => false, 'issued' => 0, 'kit' => false, 'last_check' => null];
        }
        $steamByUser = [];
        foreach ($db->fetchAll('SELECT id, steam_id FROM users WHERE id IN (' . $in . ')') as $r) {
            $steam = (string) preg_replace('/\D/', '', (string) ($r['steam_id'] ?? ''));
            $out[(int) $r['id']]['steam_linked'] = $steam !== '';
            if ($steam !== '') {
                $steamByUser[$steam] = (int) $r['id'];
            }
        }
        foreach ($db->fetchAll(
            'SELECT i.user_id AS uid, COUNT(*) AS n FROM ' . $docJoin . " WHERE d.tenant_id = :t AND d.workflow = 'issued' AND i.user_id IN (" . $in . ')' . $mask . ' GROUP BY i.user_id',
            ['t' => $tenantId]
        ) as $r) {
            $out[(int) $r['uid']]['issued'] = (int) $r['n'];
        }
        foreach ($db->fetchAll(
            'SELECT DISTINCT k.user_id AS uid FROM sse_id_kits k JOIN sse_id_documents d ON d.id = k.document_id AND d.tenant_id = k.tenant_id
             JOIN sse_identities i ON i.id = d.identity_id AND i.tenant_id = d.tenant_id
             WHERE k.tenant_id = :t AND k.user_id IN (' . $in . ')' . $mask,
            ['t' => $tenantId]
        ) as $r) {
            $out[(int) $r['uid']]['kit'] = true;
        }
        $last = static function (array &$out, int $uid, ?string $at): void {
            if ($at !== null && $at !== '' && isset($out[$uid]) && ($out[$uid]['last_check'] === null || $at > $out[$uid]['last_check'])) {
                $out[$uid]['last_check'] = $at;
            }
        };
        $identityMask = $truth ? '' : " AND i.kind NOT IN ('cover', 'forged')";
        foreach ($db->fetchAll(
            'SELECT i.user_id AS uid, MAX(c.created_at) AS at FROM sse_id_checks c JOIN sse_identities i ON i.id = c.identity_id AND i.tenant_id = c.tenant_id
             WHERE c.tenant_id = :t AND i.user_id IN (' . $in . ')' . $identityMask . ' GROUP BY i.user_id',
            ['t' => $tenantId]
        ) as $r) {
            $last($out, (int) $r['uid'], $r['at'] !== null ? (string) $r['at'] : null);
        }
        foreach ($db->fetchAll(
            'SELECT i.user_id AS uid, MAX(c.created_at) AS at FROM sse_id_checks c JOIN sse_id_documents d ON d.id = c.document_id AND d.tenant_id = c.tenant_id
             JOIN sse_identities i ON i.id = d.identity_id AND i.tenant_id = d.tenant_id
             WHERE c.tenant_id = :t AND i.user_id IN (' . $in . ')' . $mask . ' GROUP BY i.user_id',
            ['t' => $tenantId]
        ) as $r) {
            $last($out, (int) $r['uid'], $r['at'] !== null ? (string) $r['at'] : null);
        }
        if ($truth && $steamByUser !== []) {
            // Contrôles du joueur lui-même (Steam ID) : comme l'onglet, seulement avec la vérité de jeu.
            $marks = [];
            $params = ['t' => $tenantId];
            foreach (array_keys($steamByUser) as $k => $steam) {
                $marks[] = ':s' . $k;
                $params['s' . $k] = (string) $steam;
            }
            foreach ($db->fetchAll(
                'SELECT subject_steam_id AS steam, MAX(created_at) AS at FROM sse_id_checks WHERE tenant_id = :t AND subject_steam_id IN (' . implode(', ', $marks) . ') GROUP BY subject_steam_id',
                $params
            ) as $r) {
                $uid = $steamByUser[(string) $r['steam']] ?? 0;
                $last($out, $uid, $r['at'] !== null ? (string) $r['at'] : null);
            }
        }

        return $out;
    }

    /**
     * « Mes papiers » : lecture seule par le joueur, sans brouillon ni vérité de jeu (ni nature d'identité, ni
     * origine, ni falsification).
     *
     * @return array{identities: list<array<string, mixed>>, documents: list<array<string, mixed>>, kits: list<array<string, mixed>>, checks: list<array<string, mixed>>, steam_linked: bool}
     */
    public function memberView(int $tenantId, int $userId, string $steamId): array
    {
        $byCode = [];
        foreach ($this->countries->listForTenant($tenantId, false) as $c) {
            $byCode[$c['code']] = $c;
        }
        $identities = [];
        foreach ($this->identities->listForUser($tenantId, $userId) as $i) {
            $identities[$i['id']] = [
                'id' => $i['id'],
                'is_primary' => !empty($i['is_primary']),
                'last_name' => $i['last_name'],
                'first_name' => $i['first_name'],
                'display_name' => self::displayName($i),
                'sex' => $i['sex'],
                'birth_date' => $i['birth_date'],
                'birth_place' => $i['birth_place'],
                'nationality' => $i['nationality'],
            ];
        }
        $documents = [];
        foreach ($this->documents->list($tenantId, ['user_id' => $userId, 'limit' => 100]) as $d) {
            if ($d['workflow'] === 'draft' || !isset($identities[$d['identity_id']])) {
                continue;
            }
            $identity = $identities[$d['identity_id']];
            $country = $byCode[$d['country_code']] ?? null;
            $documents[] = [
                'id' => $d['id'],
                'ref' => $d['ref'],
                'identity_id' => $d['identity_id'],
                'holder_name' => $identity['display_name'],
                'doc_type' => $d['doc_type'],
                'doc_type_label' => $d['doc_type_label'],
                'country_code' => $d['country_code'],
                'country_name' => (string) ($country['name'] ?? $d['country_code']),
                'doc_number' => $d['doc_number'],
                'issued_on' => $d['issued_on'],
                'expires_on' => $d['expires_on'],
                'expired' => $d['expired'],
                'authority' => $d['authority'],
                'workflow' => $d['workflow'],
                'workflow_label' => $d['workflow_label'],
                'st_validity' => $d['st_validity'],
                'st_report' => $d['st_report'],
                'identity' => ['sex' => $identity['sex'], 'birth_date' => $identity['birth_date'], 'birth_place' => $identity['birth_place'], 'nationality' => $identity['nationality']],
                'mrz' => SseMrzService::forDocument($d, $identity, (string) ($country['mrz_code'] ?? '')),
            ];
        }
        $docIds = array_map(static fn (array $d): int => (int) $d['id'], $documents);
        $kits = [];
        foreach ($this->kits->listForUser($tenantId, $userId) as $kit) {
            $kit['document_ids'] = array_values(array_intersect($kit['document_ids'], $docIds));
            if ($kit['document_ids'] !== []) {
                $kits[] = $kit;
            }
        }
        $checks = [];
        foreach ($this->checks->listForSubject($tenantId, array_keys($identities), $docIds, $steamId, 30) as $c) {
            $checks[] = [
                'ref' => $c['ref'],
                'created_at' => $c['created_at'],
                'verdict' => $c['verdict'],
                'verdict_label' => $c['verdict_label'],
                'levels' => $c['levels'],
                'doc_number' => $c['doc_number'],
                'controller_callsign' => $c['controller_callsign'],
                'grid' => $c['grid'],
            ];
        }

        return [
            'identities' => array_values($identities),
            'documents' => $documents,
            'kits' => $kits,
            'checks' => $checks,
            'steam_linked' => preg_replace('/\D/', '', $steamId) !== '',
        ];
    }

    // ------------------------------------------------------------------ Écritures

    /**
     * Crée (ou met à jour) l'identité principale du joueur.
     *
     * @param array<string, mixed> $data last_name, first_name, sex, birth_date, birth_place, nationality, profession
     * @return array{ok: bool, id?: int, error?: string}
     */
    public function savePrimary(int $tenantId, int $userId, array $data, ?int $actorId): array
    {
        if (trim((string) ($data['last_name'] ?? '')) === '' || trim((string) ($data['first_name'] ?? '')) === '') {
            return ['ok' => false, 'error' => 'Le nom et le prénom sont obligatoires.'];
        }
        $existing = null;
        foreach ($this->identities->listForUser($tenantId, $userId) as $i) {
            if (!empty($i['is_primary'])) {
                $existing = $i;
            }
        }
        $row = ['kind' => 'civil'] + $data;
        if ($existing !== null) {
            $this->identities->update((int) $existing['id'], $tenantId, $row + [
                'context_id' => $existing['context_id'], 'op_key' => $existing['op_key'], 'person_id' => $existing['person_id'],
                'arma_key' => $existing['arma_key'], 'notes' => $existing['notes'],
                // Atelier (lot 6) : photo, alias et rattachement de population conservés.
                'photo_path' => $existing['photo_path'] ?? null, 'alias' => $existing['alias'] ?? null,
                'category' => $existing['category'] ?? null, 'side' => $existing['side'] ?? null, 'faction' => $existing['faction'] ?? null,
                'language' => $existing['language'] ?? null, 'zone' => $existing['zone'] ?? null,
            ]);
            $this->log($tenantId, $actorId, 'id_member.primary_update', 'sse_identity', (int) $existing['id'], $userId);

            return ['ok' => true, 'id' => (int) $existing['id']];
        }
        $id = $this->identities->create($tenantId, $row + ['user_id' => $userId], $actorId);
        $this->log($tenantId, $actorId, 'id_member.primary_create', 'sse_identity', $id, $userId);

        return ['ok' => true, 'id' => $id];
    }

    /**
     * Identité d'emprunt (alias, ou couverture avec l'habilitation Confidentiel).
     *
     * @param array<string, mixed> $data
     * @return array{ok: bool, id?: int, error?: string}
     */
    public function createAlias(int $tenantId, int $userId, array $data, bool $truth, ?int $actorId): array
    {
        $kind = (string) ($data['kind'] ?? 'alias');
        if (!in_array($kind, ['alias', 'cover'], true)) {
            $kind = 'alias';
        }
        if ($kind === 'cover' && !$truth) {
            return ['ok' => false, 'error' => 'Une identité de couverture demande l’habilitation Confidentiel.'];
        }
        if (trim((string) ($data['last_name'] ?? '')) === '' || trim((string) ($data['first_name'] ?? '')) === '') {
            return ['ok' => false, 'error' => 'Le nom et le prénom sont obligatoires.'];
        }
        $id = $this->identities->create($tenantId, ['kind' => $kind, 'user_id' => $userId] + $data, $actorId);
        $this->log($tenantId, $actorId, 'id_member.alias_create', 'sse_identity', $id, $userId, ['kind' => $truth ? $kind : 'alias']);

        return ['ok' => true, 'id' => $id];
    }

    /**
     * Identité du joueur visible par ce responsable (sinon null : autre membre, autre communauté, couverture masquée).
     *
     * @return array<string, mixed>|null
     */
    public function ownedIdentity(int $tenantId, int $userId, int $identityId, bool $truth): ?array
    {
        foreach ($this->identities->listForUser($tenantId, $userId) as $i) {
            if ($i['id'] === $identityId && ($truth || !in_array($i['kind'], self::SECRET, true))) {
                return $i;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function ownedDocument(int $tenantId, int $userId, int $docId, bool $truth): ?array
    {
        $doc = $this->documents->find($docId, $tenantId);
        if ($doc === null || ($doc['identity']['user_id'] ?? null) !== $userId || (!$truth && self::docIsSecret($doc))) {
            return null;
        }

        return $doc;
    }

    /**
     * Propositions du formulaire (« Générer ») : numéro libre au format du pays, dates selon la durée du type,
     * autorité par défaut, bande de lecture.
     *
     * @param array<string, mixed> $identity
     * @param array<string, mixed> $country
     * @return array{doc_number: string, issued_on: string, expires_on: string, authority: string, mrz: list<string>}
     */
    public function proposal(int $tenantId, array $identity, array $country, string $type, ?int $seed = null): array
    {
        $issued = gmdate('Y-m-d');
        $expires = gmdate('Y-m-d', (int) strtotime('+' . SseIdCountryRepository::validityFor($country, $type) . ' years'));
        $number = $this->freeNumber($tenantId, $country, $type, $seed ?? SseIdentityGeneratorService::randomSeed());

        return [
            'doc_number' => $number,
            'issued_on' => $issued,
            'expires_on' => $expires,
            'authority' => (string) ($country['authority'] ?? ''),
            'mrz' => SseMrzService::forDocument(['doc_type' => $type, 'country_code' => $country['code'], 'doc_number' => $number, 'expires_on' => $expires], $identity, (string) $country['mrz_code']),
        ];
    }

    /** @param array<string, mixed> $country */
    public function freeNumber(int $tenantId, array $country, string $type, int $seed): string
    {
        $format = (string) $country['number_format'];
        for ($i = 0; $i < 20; $i++) {
            $n = SseIdentityGeneratorService::documentNumber($format, $seed, $type . $i);
            if (!$this->documents->numberExists($tenantId, (string) $country['code'], $type, $n)) {
                return $n;
            }
        }

        return SseIdentityGeneratorService::documentNumber($format, SseIdentityGeneratorService::randomSeed(), $type);
    }

    /**
     * Crée un papier (brouillon) ou modifie un papier existant.
     *
     * @param array<string, mixed> $identity identité du joueur (ownedIdentity)
     * @param array<string, mixed>|null $doc papier modifié (ownedDocument), null = création
     * @param array<string, mixed> $input champs du formulaire
     * @return array{ok: bool, id?: int, error?: string}
     */
    public function saveDocument(int $tenantId, int $userId, array $identity, ?array $doc, array $input, bool $truth, ?int $actorId): array
    {
        $type = $doc['doc_type'] ?? SseIdentityCatalog::pick(SseIdentityCatalog::DOC_TYPES, $input['doc_type'] ?? null, 'id_card');
        $country = $this->countries->findByCode($tenantId, (string) ($doc['country_code'] ?? $input['country_code'] ?? ''));
        if ($country === null || ($doc === null && !$country['active'])) {
            return ['ok' => false, 'error' => 'Choisissez un pays émetteur actif.'];
        }
        if ($doc === null && !in_array($type, $country['doc_types'], true)) {
            return ['ok' => false, 'error' => 'Ce pays ne délivre pas ce type de papier.'];
        }
        if ($doc !== null && $doc['workflow'] === 'revoked') {
            return ['ok' => false, 'error' => 'Un papier révoqué ne se modifie plus : dupliquez-le.'];
        }
        $number = SseIdDocumentRepository::normalizeNumber((string) ($input['doc_number'] ?? ''));
        if ($number === '') {
            $number = $this->freeNumber($tenantId, $country, $type, SseIdentityGeneratorService::randomSeed());
        }
        if (!SseIdentityGeneratorService::matchesFormat($number, (string) $country['number_format'])) {
            return ['ok' => false, 'error' => 'Ce numéro ne suit pas le format des papiers de ce pays (' . $country['number_format'] . ').'];
        }
        if ($this->documents->numberExists($tenantId, (string) $country['code'], $type, $number, (int) ($doc['id'] ?? 0))) {
            return ['ok' => false, 'error' => 'Ce numéro est déjà attribué à un autre papier de ce pays.'];
        }
        $details = [
            'doc_number' => $number,
            'issued_on' => (string) ($input['issued_on'] ?? ''),
            'expires_on' => (string) ($input['expires_on'] ?? ''),
            'authority' => (string) ($input['authority'] ?? ''),
            'context_id' => (int) ($input['context_id'] ?? 0),
            'op_key' => (string) ($input['op_key'] ?? ''),
            'notes' => (string) ($input['notes'] ?? ''),
        ];
        if ($doc !== null) {
            $this->documents->updateDetails((int) $doc['id'], $tenantId, $details);
            if ($truth) {
                $this->documents->updateStatuses((int) $doc['id'], $tenantId, [
                    'chip_status' => $doc['chip_status'], 'st_integrity' => $doc['st_integrity'], 'st_validity' => $doc['st_validity'],
                    'st_report' => $doc['st_report'], 'st_attribution' => $doc['st_attribution'], 'holder_person_id' => $doc['holder_person_id'],
                    'st_origin' => $input['st_origin'] ?? $doc['st_origin'],
                    'forgery_kind' => $input['forgery_kind'] ?? $doc['forgery_kind'],
                    'forgery_quality' => $input['forgery_quality'] ?? $doc['forgery_quality'],
                ]);
            }
            $this->log($tenantId, $actorId, 'id_member.document_update', 'sse_id_document', (int) $doc['id'], $userId, ['number' => $number]);

            return ['ok' => true, 'id' => (int) $doc['id']];
        }
        $data = $details + [
            'identity_id' => (int) $identity['id'],
            'doc_type' => $type,
            'country_code' => $country['code'],
            'holder_person_id' => $identity['person_id'],
            'st_origin' => match ($identity['kind']) {
                'cover' => 'cover',
                'forged' => 'forged',
                'generated' => 'generated',
                default => 'official',
            },
        ];
        if ($truth) {
            foreach (['st_origin', 'forgery_kind', 'forgery_quality'] as $k) {
                if (trim((string) ($input[$k] ?? '')) !== '') {
                    $data[$k] = $input[$k];
                }
            }
        }
        $id = $this->documents->create($tenantId, $data, $actorId);
        $this->log($tenantId, $actorId, 'id_member.document_create', 'sse_id_document', $id, $userId, ['type' => $type, 'country' => $country['code'], 'number' => $number]);

        return ['ok' => true, 'id' => $id];
    }

    /**
     * Bouton d'action sur un papier.
     *
     * @param array<string, mixed> $doc ownedDocument()
     * @return array{ok: bool, message: string, id?: int}
     */
    public function action(int $tenantId, int $userId, array $doc, string $action, string $reason, ?int $actorId): array
    {
        $id = (int) $doc['id'];
        $wf = (string) $doc['workflow'];
        $ok = false;
        $message = '';
        switch ($action) {
            case 'validate':
                $ok = $this->documents->transition($id, $tenantId, 'validated', $actorId);
                $message = 'Papier validé.';
                break;
            case 'issue':
                if ($wf === 'draft') {
                    $this->documents->transition($id, $tenantId, 'validated', $actorId);
                }
                $ok = $this->documents->transition($id, $tenantId, 'issued', $actorId);
                $message = 'Papier émis : il sera chargé par le serveur de jeu à la prochaine mise à jour.';
                break;
            case 'draft':
                $ok = $this->documents->transition($id, $tenantId, 'draft', $actorId);
                $message = 'Papier repassé en brouillon.';
                break;
            case 'revoke':
                if (trim($reason) === '') {
                    return ['ok' => false, 'message' => 'Indiquez le motif de la révocation.'];
                }
                $ok = $this->documents->transition($id, $tenantId, 'revoked', $actorId, $reason);
                $message = 'Papier révoqué : les contrôles en ligne le signalent désormais comme annulé.';
                break;
            case 'stolen':
            case 'lost':
                $ok = $wf !== 'draft' && $this->documents->setReport($id, $tenantId, $action);
                $message = $action === 'stolen' ? 'Papier déclaré volé.' : 'Papier déclaré perdu.';
                break;
            case 'clear_report':
                $ok = $this->documents->setReport($id, $tenantId, 'none');
                $message = 'Signalement levé.';
                break;
            case 'duplicate':
                $country = $this->countries->findByCode($tenantId, (string) $doc['country_code']);
                if ($country === null) {
                    break;
                }
                $newId = $this->documents->create($tenantId, [
                    'identity_id' => $doc['identity_id'], 'doc_type' => $doc['doc_type'], 'country_code' => $doc['country_code'],
                    'doc_number' => $this->freeNumber($tenantId, $country, (string) $doc['doc_type'], SseIdentityGeneratorService::randomSeed()),
                    'issued_on' => gmdate('Y-m-d'),
                    'expires_on' => gmdate('Y-m-d', (int) strtotime('+' . SseIdCountryRepository::validityFor($country, (string) $doc['doc_type']) . ' years')),
                    'authority' => $doc['authority'], 'context_id' => $doc['context_id'], 'op_key' => $doc['op_key'],
                    'holder_person_id' => $doc['holder_person_id'], 'chip_status' => $doc['chip_status'], 'st_origin' => $doc['st_origin'],
                    'forgery_kind' => $doc['forgery_kind'], 'forgery_quality' => $doc['forgery_quality'],
                ], $actorId);
                $this->log($tenantId, $actorId, 'id_member.document_duplicate', 'sse_id_document', $newId, $userId, ['from' => $id]);

                return ['ok' => true, 'message' => 'Copie créée en brouillon avec un nouveau numéro.', 'id' => $newId];
            case 'delete':
                $ok = $wf === 'draft' && $this->documents->deleteDraft($id, $tenantId);
                $message = 'Brouillon supprimé.';
                break;
        }
        if (!$ok) {
            return ['ok' => false, 'message' => 'Cette action n’est pas possible dans l’état actuel du papier.'];
        }
        $this->log($tenantId, $actorId, 'id_member.document_' . $action, 'sse_id_document', $id, $userId, $reason !== '' ? ['reason' => mb_substr($reason, 0, 255)] : []);

        return ['ok' => true, 'message' => $message];
    }

    /**
     * Kit porté en mission pour une portée (par défaut, carte, opération). Seuls les papiers du joueur visibles par
     * ce responsable comptent ; les papiers de couverture déjà dans le kit sont gardés si le responsable ne les voit pas.
     *
     * @param list<int> $documentIds
     */
    public function saveKit(int $tenantId, int $userId, ?int $contextId, ?string $opKey, array $documentIds, bool $truth, ?int $actorId): int
    {
        $visible = [];
        $hiddenKept = [];
        $contextId = $contextId !== null && $contextId > 0 ? $contextId : null;
        $opKey = $opKey !== null && trim($opKey) !== '' ? trim($opKey) : null;
        foreach ($this->documents->list($tenantId, ['user_id' => $userId, 'limit' => 200]) as $d) {
            if ($truth || !self::docIsSecret($d)) {
                $visible[] = (int) $d['id'];
            }
        }
        if (!$truth) {
            foreach ($this->kits->listForUser($tenantId, $userId) as $kit) {
                if ($kit['context_id'] === $contextId && $kit['op_key'] === $opKey) {
                    $hiddenKept = array_values(array_diff($kit['document_ids'], $visible));
                }
            }
        }
        $ids = array_merge(array_values(array_intersect(array_map('intval', $documentIds), $visible)), $hiddenKept);
        $n = $this->kits->replace($tenantId, $userId, $contextId, $opKey, $ids, $actorId);
        $this->log($tenantId, $actorId, 'id_member.kit_save', 'user', $userId, $userId, ['scope' => SseIdKitRepository::scopeName($contextId, $opKey), 'map' => $contextId, 'op' => $opKey, 'count' => $n]);

        return $n;
    }

    /** @param array<string, mixed> $extra */
    private function log(int $tenantId, ?int $actorId, string $action, string $type, int $objectId, int $memberId, array $extra = []): void
    {
        ($this->audit)([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actorId,
            'actor_label' => 'Fiche membre',
            'action' => $action,
            'object_type' => $type,
            'object_id' => $objectId,
            'after' => ['member_user_id' => $memberId] + $extra,
        ]);
    }
}
