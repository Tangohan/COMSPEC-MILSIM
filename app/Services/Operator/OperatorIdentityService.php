<?php

declare(strict_types=1);

namespace App\Services\Operator;

use App\Repositories\OperatorHubRepository;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseCoverExportRepository;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseIdKitRepository;
use App\Services\Sse\MemberIdentityPapersService;
use App\Services\Sse\MemberIdentitySyncService;
use App\Services\Sse\SseCoverExportService;
use App\Support\SseFileSubjectCatalog;
use App\Support\SseIdentityCatalog;

/**
 * « Mon identité » des outils opérateur (/operateur/identite) : ce qu'un membre gère lui-même sur ses identités, ses
 * papiers et ses couvertures. Tout est une donnée fictive de jeu.
 *
 * Rien n'est stocké à part : identités dans sse_identities (MemberIdentityPapersService), signalement dans
 * sse_file_profiles (celui des fichiers SSE), papiers dans sse_id_documents, kit envoyé en jeu dans sse_id_kits,
 * couvertures dans sse_cover_exports (SseCoverExportService). Seul l'état posé par le membre (identité archivée,
 * couverture active, révoquée, modifiée) va dans operator_identity_states.
 *
 * Règles :
 *  - le membre n'agit que sur ce qui est rattaché à son compte, dans sa communauté (chaque lecture repasse par là) ;
 *  - il ne voit jamais la nature d'une identité ni l'origine d'un papier (vérité de jeu) : seulement « principale » ou
 *    « d'emprunt » ;
 *  - un papier se demande (brouillon marqué REQUEST_NOTE), un responsable le valide et l'émet ; le membre déclare la
 *    perte ou le vol, demande un renouvellement, annule sa demande ;
 *  - l'état civil d'une identité qui porte déjà un papier émis ne se modifie plus par le membre ;
 *  - chaque écriture est journalisée (journal d'audit SSE, acteur « Outils opérateur »).
 */
final class OperatorIdentityService
{
    public const ACTOR = 'Outils opérateur';
    public const REQUEST_NOTE = 'Demande du membre (Outils opérateur)';
    public const MAX_ALIASES = 5;
    public const MAX_PENDING_REQUESTS = 3;
    public const MAX_HISTORY_LINES = 8;

    /** État d'un papier pour le membre → libellé et ton de la pastille. */
    public const DOC_STATUSES = [
        'requested' => ['Demande en attente', 'wait'],
        'pending' => ['Validé, pas encore émis', 'wait'],
        'valid' => ['Valide', 'ok'],
        'expired' => ['Expiré', 'warn'],
        'lost' => ['Déclaré perdu', 'bad'],
        'stolen' => ['Déclaré volé', 'bad'],
        'suspended' => ['Suspendu', 'bad'],
        'revoked' => ['Annulé', 'bad'],
    ];

    /** Ce que le membre peut faire d'un papier selon son état : action → état obtenu (null : le papier disparaît). */
    public const DOC_TRANSITIONS = [
        'requested' => ['cancel' => null],
        'pending' => [],
        'valid' => ['lost' => 'lost', 'stolen' => 'stolen', 'renew' => 'valid'],
        'expired' => ['lost' => 'lost', 'stolen' => 'stolen', 'renew' => 'expired'],
        'lost' => ['renew' => 'lost'],
        'stolen' => ['renew' => 'stolen'],
        'suspended' => [],
        'revoked' => ['renew' => 'revoked'],
    ];

    /** Champs de la légende qu'un membre peut retoucher (le nom, la date de naissance et les papiers ne bougent pas). */
    public const COVER_EDITABLE = ['profession' => 120, 'employer' => 190, 'city' => 120, 'address' => 255, 'travel' => 255, 'family' => 190];

    /** @var callable(array<string, mixed>): void */
    private $audit;
    private MemberIdentityPapersService $papers;

    public function __construct(
        private ?SseIdentityRepository $identities = null,
        private ?SseIdDocumentRepository $documents = null,
        private ?SseIdCountryRepository $countries = null,
        private ?SseIdKitRepository $kits = null,
        private ?SseFileRecordRepository $files = null,
        private ?OperatorHubRepository $states = null,
        private ?SseCoverExportRepository $exports = null,
        private ?SseCoverExportService $covers = null,
        ?callable $audit = null,
    ) {
        $this->identities ??= new SseIdentityRepository();
        $this->documents ??= new SseIdDocumentRepository();
        $this->countries ??= new SseIdCountryRepository();
        $this->kits ??= new SseIdKitRepository();
        $this->files ??= new SseFileRecordRepository();
        $this->states ??= new OperatorHubRepository();
        $this->exports ??= new SseCoverExportRepository();
        $this->audit = $audit ?? static function (array $entry): void {
            try {
                (new SseAuditLogRepository())->write($entry);
            } catch (\Throwable $e) {
                error_log('Outils opérateur : journal d’audit indisponible : ' . $e->getMessage());
            }
        };
        $this->covers ??= new SseCoverExportService($this->exports, null, null, $this->audit);
        $this->papers = new MemberIdentityPapersService($this->identities, $this->documents, $this->kits, null, $this->countries, $this->audit);
    }

    // ------------------------------------------------------------------ Identités

    /**
     * Identités du membre, vues par lui : principale d'abord, archivées à la fin. Jamais la nature (alias, couverture,
     * falsifiée) : seulement « principale » ou non.
     *
     * @return list<array<string, mixed>>
     */
    public function identities(int $tenantId, int $userId): array
    {
        $rows = $this->identities->listForUser($tenantId, $userId);
        $ids = array_map(static fn (array $i): int => (int) $i['id'], $rows);
        $profiles = $ids !== [] ? $this->files->profiles($tenantId, $ids) : [];
        $states = $this->states->states($tenantId, $userId, OperatorHubRepository::KIND_IDENTITY);
        $docs = $this->documents->list($tenantId, ['user_id' => $userId, 'limit' => 200]);
        $issuedBy = [];
        foreach ($docs as $d) {
            if (in_array($d['workflow'], ['issued', 'revoked'], true)) {
                $issuedBy[(int) $d['identity_id']] = true;
            }
        }
        $activeId = self::activeIdentityId($rows, $docs, $this->kits->listForUser($tenantId, $userId));
        $out = [];
        foreach ($rows as $i) {
            $id = (int) $i['id'];
            $p = $profiles[$id] ?? [];
            $profile = [
                'height_cm' => isset($p['height_cm']) ? (int) $p['height_cm'] : null,
                'weight_kg' => isset($p['weight_kg']) ? (int) $p['weight_kg'] : null,
                'hair' => ($p['hair'] ?? null) ?: null,
                'eyes' => ($p['eyes'] ?? null) ?: null,
                'marks' => ($p['marks'] ?? null) ?: null,
            ];
            $archived = ($states[$id]['archived_at'] ?? null) !== null;
            $out[] = [
                'id' => $id,
                'ref' => (string) $i['ref'],
                'is_primary' => !empty($i['is_primary']),
                'last_name' => (string) $i['last_name'],
                'first_name' => (string) $i['first_name'],
                'display_name' => MemberIdentityPapersService::displayName($i),
                'alias' => $i['alias'] ?? null,
                'sex' => (string) $i['sex'],
                'birth_date' => $i['birth_date'],
                'birth_place' => $i['birth_place'],
                'nationality' => $i['nationality'],
                'profession' => $i['profession'],
                'photo_path' => $i['photo_path'] ?? null,
                'profile' => $profile,
                'documents_count' => (int) ($i['documents_count'] ?? 0),
                'archived' => $archived,
                'active' => !$archived && $activeId === $id,
                'civil_locked' => isset($issuedBy[$id]),
                // Peut devenir l'identité principale : une identité créée par le membre ou civile (jamais une identité
                // montée par un responsable, dont la nature reste une vérité de jeu).
                'promotable' => !$archived && empty($i['is_primary']) && in_array($i['kind'], ['civil', 'alias'], true),
                'completeness' => self::completeness($i, $profile),
            ];
        }
        usort($out, static fn (array $a, array $b): int => [$a['archived'], !$a['is_primary'], $a['id']] <=> [$b['archived'], !$b['is_primary'], $b['id']]);

        return $out;
    }

    /**
     * Identité envoyée en jeu : celle des papiers du kit par défaut ; sans kit, l'identité principale (le serveur de jeu
     * remet alors tout seul ses papiers émis et valables).
     *
     * @param list<array<string, mixed>> $identities SseIdentityRepository::listForUser()
     * @param list<array<string, mixed>> $documents
     * @param list<array<string, mixed>> $kits SseIdKitRepository::listForUser()
     */
    public static function activeIdentityId(array $identities, array $documents, array $kits): ?int
    {
        $owner = [];
        foreach ($documents as $d) {
            $owner[(int) $d['id']] = (int) $d['identity_id'];
        }
        foreach ($kits as $kit) {
            if (($kit['context_id'] ?? null) === null && ($kit['op_key'] ?? null) === null) {
                foreach ($kit['document_ids'] as $docId) {
                    if (isset($owner[(int) $docId])) {
                        return $owner[(int) $docId];
                    }
                }
            }
        }
        foreach ($identities as $i) {
            if (!empty($i['is_primary'])) {
                return (int) $i['id'];
            }
        }

        return null;
    }

    /**
     * Ce qui manque à une identité pour que ses papiers et sa fiche aux fichiers soient complets.
     *
     * @param array<string, mixed> $identity
     * @param array<string, mixed> $profile height_cm, weight_kg, hair, eyes, marks
     * @return array{done: int, total: int, percent: int, missing: list<array{key: string, label: string}>}
     */
    public static function completeness(array $identity, array $profile): array
    {
        $filled = static fn (mixed $v): bool => $v !== null && trim((string) $v) !== '';
        $checks = [
            'last_name' => ['Nom', $filled($identity['last_name'] ?? null)],
            'first_name' => ['Prénom', $filled($identity['first_name'] ?? null)],
            'sex' => ['Sexe', in_array($identity['sex'] ?? '', ['M', 'F'], true)],
            'birth_date' => ['Date de naissance', $filled($identity['birth_date'] ?? null)],
            'birth_place' => ['Lieu de naissance', $filled($identity['birth_place'] ?? null)],
            'nationality' => ['Nationalité', $filled($identity['nationality'] ?? null)],
            'photo' => ['Photo d’identité', $filled($identity['photo_path'] ?? null)],
            'height_cm' => ['Taille', (int) ($profile['height_cm'] ?? 0) > 0],
            'eyes' => ['Yeux', $filled($profile['eyes'] ?? null)],
            'hair' => ['Cheveux', $filled($profile['hair'] ?? null)],
        ];
        $missing = [];
        foreach ($checks as $key => [$label, $ok]) {
            if (!$ok) {
                $missing[] = ['key' => $key, 'label' => $label];
            }
        }
        $total = count($checks);
        $done = $total - count($missing);

        return ['done' => $done, 'total' => $total, 'percent' => (int) round(100 * $done / $total), 'missing' => $missing];
    }

    /**
     * Crée ou modifie une identité du membre. $identityId null : création ($data['primary'] vrai = identité principale
     * si elle n'existe pas encore, sinon alias).
     *
     * @param array<string, mixed> $data last_name, first_name, alias, sex, birth_date, birth_place, nationality,
     *                                   profession, height_cm, weight_kg, hair, eyes, marks
     * @return array{ok: bool, id?: int, error?: string}
     */
    public function saveIdentity(int $tenantId, int $userId, ?int $identityId, array $data, ?\DateTimeImmutable $today = null): array
    {
        $existing = null;
        $all = $this->identities->listForUser($tenantId, $userId);
        if ($identityId !== null) {
            foreach ($all as $i) {
                if ((int) $i['id'] === $identityId) {
                    $existing = $i;
                }
            }
            if ($existing === null) {
                return ['ok' => false, 'error' => 'Identité introuvable.'];
            }
            if ($this->isArchived($tenantId, $userId, $identityId)) {
                return ['ok' => false, 'error' => 'Cette identité est archivée : restaurez-la avant de la modifier.'];
            }
        }
        $locked = $existing !== null && $this->civilLocked($tenantId, $userId, (int) $existing['id']);
        $civil = ['last_name', 'first_name', 'sex', 'birth_date', 'birth_place', 'nationality'];
        $row = [];
        foreach (array_merge($civil, ['profession', 'alias']) as $k) {
            $row[$k] = $locked && in_array($k, $civil, true) ? (string) ($existing[$k] ?? '') : trim((string) ($data[$k] ?? ''));
        }
        $row['sex'] = strtoupper($row['sex']);
        $row['nationality'] = strtoupper($row['nationality']);
        $error = $this->validateIdentity($tenantId, $row, $data, $today ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }

        $hasPrimary = false;
        foreach ($all as $i) {
            $hasPrimary = $hasPrimary || !empty($i['is_primary']);
        }
        if ($existing === null) {
            if (!$hasPrimary) {
                $res = $this->papers->savePrimary($tenantId, $userId, $row, $userId);
            } else {
                $aliases = count(array_filter($all, static fn (array $i): bool => empty($i['is_primary'])));
                if ($aliases >= self::MAX_ALIASES) {
                    return ['ok' => false, 'error' => 'Vous avez déjà ' . self::MAX_ALIASES . ' identités d’emprunt : archivez-en une ou voyez un responsable.'];
                }
                $res = $this->papers->createAlias($tenantId, $userId, ['kind' => 'alias'] + $row, true, $userId);
            }
            if (!$res['ok']) {
                return ['ok' => false, 'error' => (string) ($res['error'] ?? 'Identité refusée.')];
            }
            $id = (int) $res['id'];
        } else {
            $id = (int) $existing['id'];
            // Tout ce que le membre ne saisit pas ici est conservé (nature, portée, photo, rattachements).
            $this->identities->update($id, $tenantId, array_merge($existing, $row));
        }
        $this->files->saveProfile($tenantId, $id, [
            'height_cm' => $data['height_cm'] ?? null,
            'weight_kg' => $data['weight_kg'] ?? null,
            'hair' => $data['hair'] ?? null,
            'eyes' => $data['eyes'] ?? null,
            'marks' => $data['marks'] ?? null,
        ], $userId);
        $this->log($tenantId, $userId, $existing === null ? 'op_identity.identity_create' : 'op_identity.identity_update', 'sse_identity', $id);

        return ['ok' => true, 'id' => $id];
    }

    /**
     * @param array<string, string> $row
     * @param array<string, mixed> $data
     */
    private function validateIdentity(int $tenantId, array $row, array $data, \DateTimeImmutable $today): ?string
    {
        if ($row['last_name'] === '' || $row['first_name'] === '') {
            return 'Le nom et le prénom sont obligatoires.';
        }
        foreach (['last_name' => 'Le nom', 'first_name' => 'Le prénom'] as $k => $label) {
            if (!MemberIdentitySyncService::validName($row[$k])) {
                return $label . ' n’accepte que des lettres, espaces, tirets et apostrophes (40 caractères au plus).';
            }
        }
        if ($row['sex'] !== '' && !isset(SseIdentityCatalog::SEXES[$row['sex']])) {
            return 'Sexe inconnu.';
        }
        if ($row['birth_date'] !== '') {
            $dob = \DateTimeImmutable::createFromFormat('!Y-m-d', $row['birth_date']);
            if ($dob === false || $dob->format('Y-m-d') !== $row['birth_date']) {
                return 'Date de naissance illisible (attendu : AAAA-MM-JJ).';
            }
            $age = $dob->diff($today)->y;
            if ($dob > $today || $age < 16 || $age > 90) {
                return 'La date de naissance doit donner un âge entre 16 et 90 ans.';
            }
        }
        if ($row['nationality'] !== '') {
            $country = $this->countries->findByCode($tenantId, $row['nationality']);
            if ($country === null || empty($country['active'])) {
                return 'Choisissez une nationalité parmi les pays de la communauté.';
            }
        }
        foreach (['birth_place' => 120, 'profession' => 120, 'alias' => 120] as $k => $max) {
            if (mb_strlen($row[$k]) > $max || preg_match('/[\x00-\x1F\x7F]/u', $row[$k]) === 1) {
                return 'Un champ est trop long ou contient des caractères interdits.';
            }
        }
        $h = trim((string) ($data['height_cm'] ?? ''));
        if ($h !== '' && (!ctype_digit($h) || (int) $h < 130 || (int) $h > 230)) {
            return 'La taille se donne en centimètres, entre 130 et 230.';
        }
        $w = trim((string) ($data['weight_kg'] ?? ''));
        if ($w !== '' && (!ctype_digit($w) || (int) $w < 35 || (int) $w > 200)) {
            return 'Le poids se donne en kilogrammes, entre 35 et 200.';
        }
        foreach (['hair' => SseFileSubjectCatalog::HAIR, 'eyes' => SseFileSubjectCatalog::EYES] as $k => $map) {
            $v = trim((string) ($data[$k] ?? ''));
            if ($v !== '' && !isset($map[$v])) {
                return 'Signalement : valeur inconnue.';
            }
        }
        $marks = (string) ($data['marks'] ?? '');
        if (mb_strlen($marks) > 300 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $marks) === 1) {
            return 'Signes particuliers : 300 caractères au plus.';
        }

        return null;
    }

    /** Identité du membre (non archivée par défaut), sinon null : autre membre, autre communauté, inconnue. */
    public function ownedIdentity(int $tenantId, int $userId, int $identityId, bool $allowArchived = false): ?array
    {
        $identity = $this->papers->ownedIdentity($tenantId, $userId, $identityId, true);
        if ($identity === null || (!$allowArchived && $this->isArchived($tenantId, $userId, $identityId))) {
            return null;
        }

        return $identity;
    }

    /**
     * Archive ou restaure une identité d'emprunt. L'identité principale et l'identité envoyée en jeu ne s'archivent pas.
     *
     * @return array{ok: bool, error?: string}
     */
    public function archiveIdentity(int $tenantId, int $userId, int $identityId, bool $archive): array
    {
        $found = null;
        foreach ($this->identities($tenantId, $userId) as $i) {
            if ($i['id'] === $identityId) {
                $found = $i;
            }
        }
        if ($found === null) {
            return ['ok' => false, 'error' => 'Identité introuvable.'];
        }
        if ($archive && $found['is_primary']) {
            return ['ok' => false, 'error' => 'L’identité principale ne s’archive pas.'];
        }
        if ($archive && $found['active']) {
            return ['ok' => false, 'error' => 'Cette identité est celle envoyée en jeu : choisissez-en une autre d’abord.'];
        }
        $this->states->setState($tenantId, $userId, OperatorHubRepository::KIND_IDENTITY, $identityId, ['archived_at' => $archive ? gmdate('Y-m-d H:i:s') : null], $userId);
        $this->log($tenantId, $userId, $archive ? 'op_identity.identity_archive' : 'op_identity.identity_restore', 'sse_identity', $identityId);

        return ['ok' => true];
    }

    /**
     * Change l'identité principale du membre : celle de son personnage. L'identité choisie devient l'identité civile ;
     * les autres identités civiles du compte deviennent des identités d'emprunt (l'ordre du registre donne la principale
     * à la plus ancienne identité civile : il n'en reste donc qu'une). Rien n'est supprimé, les papiers suivent leur
     * identité.
     *
     * @return array{ok: bool, error?: string}
     */
    public function setPrimary(int $tenantId, int $userId, int $identityId): array
    {
        $all = $this->identities->listForUser($tenantId, $userId);
        $chosen = null;
        foreach ($all as $i) {
            if ((int) $i['id'] === $identityId) {
                $chosen = $i;
            }
        }
        if ($chosen === null || $this->isArchived($tenantId, $userId, $identityId)) {
            return ['ok' => false, 'error' => 'Identité introuvable.'];
        }
        if (!empty($chosen['is_primary'])) {
            return ['ok' => true];
        }
        if (!in_array($chosen['kind'], ['civil', 'alias'], true)) {
            return ['ok' => false, 'error' => 'Cette identité vous a été remise par un responsable : elle ne peut pas devenir votre identité principale.'];
        }
        foreach ($all as $i) {
            if ((int) $i['id'] !== $identityId && $i['kind'] === 'civil') {
                $this->identities->update((int) $i['id'], $tenantId, array_merge($i, ['kind' => 'alias']));
            }
        }
        $this->identities->update($identityId, $tenantId, array_merge($chosen, ['kind' => 'civil']));
        $this->log($tenantId, $userId, 'op_identity.identity_primary', 'sse_identity', $identityId);

        return ['ok' => true];
    }

    /**
     * Fait de cette identité celle envoyée en jeu (kit par défaut recomposé avec ses papiers émis et valables).
     *
     * @return array{ok: bool, message: string}
     */
    public function activateIdentity(int $tenantId, int $userId, int $identityId): array
    {
        if ($this->ownedIdentity($tenantId, $userId, $identityId) === null) {
            return ['ok' => false, 'message' => 'Identité introuvable.'];
        }
        $sync = new MemberIdentitySyncService($this->identities, null, null, $this->kits, $this->documents, $this->audit);

        return $sync->sendToGame($tenantId, $userId, $identityId, true, $userId);
    }

    /** Enregistre le chemin de la photo d'identité (déjà vérifiée et réencodée par SseIdImageService), null = retirée. */
    public function setPhoto(int $tenantId, int $userId, int $identityId, ?string $path): bool
    {
        if ($this->ownedIdentity($tenantId, $userId, $identityId) === null) {
            return false;
        }
        $this->identities->setImages($identityId, $tenantId, ['photo_path' => $path]);
        $this->log($tenantId, $userId, 'op_identity.photo', 'sse_identity', $identityId, ['removed' => $path === null]);

        return true;
    }

    private function isArchived(int $tenantId, int $userId, int $identityId): bool
    {
        return ($this->states->states($tenantId, $userId, OperatorHubRepository::KIND_IDENTITY)[$identityId]['archived_at'] ?? null) !== null;
    }

    private function civilLocked(int $tenantId, int $userId, int $identityId): bool
    {
        foreach ($this->documents->list($tenantId, ['user_id' => $userId, 'limit' => 200]) as $d) {
            if ((int) $d['identity_id'] === $identityId && in_array($d['workflow'], ['issued', 'revoked'], true)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------ Papiers

    /**
     * État d'un papier pour le membre, null s'il ne doit pas le voir (brouillon préparé par un responsable).
     *
     * @param array<string, mixed> $doc papier hydraté
     */
    public static function docStatus(array $doc, ?string $today = null): ?string
    {
        $today ??= gmdate('Y-m-d');
        $wf = (string) ($doc['workflow'] ?? 'draft');
        if ($wf === 'draft') {
            return self::isRequest($doc) ? 'requested' : null;
        }
        if ($wf === 'validated') {
            return 'pending';
        }
        if ($wf === 'revoked' || ($doc['st_validity'] ?? 'valid') === 'revoked') {
            return 'revoked';
        }
        if (($doc['st_validity'] ?? 'valid') === 'suspended') {
            return 'suspended';
        }
        if (in_array($doc['st_report'] ?? 'none', ['lost', 'stolen'], true)) {
            return (string) $doc['st_report'];
        }
        $expires = (string) ($doc['expires_on'] ?? '');
        if (($doc['st_validity'] ?? 'valid') === 'expired' || ($expires !== '' && $expires < $today)) {
            return 'expired';
        }

        return 'valid';
    }

    /** @param array<string, mixed> $doc */
    public static function isRequest(array $doc): bool
    {
        return str_starts_with((string) ($doc['notes'] ?? ''), self::REQUEST_NOTE);
    }

    /** État obtenu si le membre fait $action sur un papier dans l'état $status ; false : action refusée. */
    public static function docTransition(string $status, string $action): string|false|null
    {
        $allowed = self::DOC_TRANSITIONS[$status] ?? [];

        return array_key_exists($action, $allowed) ? $allowed[$action] : false;
    }

    /**
     * Papiers du membre, vus par lui (jamais l'origine ni la falsification), groupés par identité côté vue.
     *
     * @return list<array<string, mixed>>
     */
    public function documents(int $tenantId, int $userId, ?string $today = null): array
    {
        $today ??= gmdate('Y-m-d');
        $byCode = [];
        foreach ($this->countries->listForTenant($tenantId, false) as $c) {
            $byCode[(string) $c['code']] = $c;
        }
        $identities = [];
        foreach ($this->identities->listForUser($tenantId, $userId) as $i) {
            $identities[(int) $i['id']] = $i;
        }
        $profiles = $identities !== [] ? $this->files->profiles($tenantId, array_keys($identities)) : [];
        $out = [];
        foreach ($this->documents->list($tenantId, ['user_id' => $userId, 'limit' => 100]) as $d) {
            $status = self::docStatus($d, $today);
            $identity = $identities[(int) $d['identity_id']] ?? null;
            if ($status === null || $identity === null) {
                continue;
            }
            $country = $byCode[(string) $d['country_code']] ?? null;
            $expires = (string) ($d['expires_on'] ?? '');
            $soon = $status === 'valid' && $expires !== '' && $expires <= gmdate('Y-m-d', strtotime($today . ' +30 days'));
            $p = $profiles[(int) $d['identity_id']] ?? [];
            $out[] = [
                'id' => (int) $d['id'],
                'ref' => (string) $d['ref'],
                'identity_id' => (int) $d['identity_id'],
                'holder_name' => MemberIdentityPapersService::displayName($identity),
                'last_name' => (string) $identity['last_name'],
                'first_name' => (string) $identity['first_name'],
                'sex' => (string) $identity['sex'],
                'birth_date' => $identity['birth_date'],
                'birth_place' => $identity['birth_place'],
                'nationality' => $identity['nationality'],
                'photo_path' => $identity['photo_path'] ?? null,
                'height_cm' => isset($p['height_cm']) ? (int) $p['height_cm'] : null,
                'eyes' => ($p['eyes'] ?? null) ?: null,
                'hair' => ($p['hair'] ?? null) ?: null,
                'doc_type' => (string) $d['doc_type'],
                'doc_type_label' => (string) $d['doc_type_label'],
                'country_code' => (string) $d['country_code'],
                'country_name' => (string) ($country['name'] ?? $d['country_code']),
                'color_primary' => $country['color_primary'] ?? null,
                'color_secondary' => $country['color_secondary'] ?? null,
                'doc_number' => (string) $d['doc_number'],
                'issued_on' => $d['issued_on'],
                'expires_on' => $d['expires_on'],
                'authority' => $d['authority'],
                'status' => $status,
                'status_label' => self::DOC_STATUSES[$status][0],
                'status_tone' => self::DOC_STATUSES[$status][1],
                'expires_soon' => $soon,
                'actions' => array_keys(self::DOC_TRANSITIONS[$status] ?? []),
                'issued_at' => $d['issued_at'] ?? null,
                'mrz' => $status === 'requested' ? [] : \App\Services\Sse\SseMrzService::forDocument($d, $identity, (string) ($country['mrz_code'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * Demande d'un nouveau papier : brouillon marqué, qu'un responsable valide et émet.
     *
     * @return array{ok: bool, id?: int, error?: string}
     */
    public function requestDocument(int $tenantId, int $userId, int $identityId, string $type, string $countryCode): array
    {
        $identity = $this->ownedIdentity($tenantId, $userId, $identityId);
        if ($identity === null) {
            return ['ok' => false, 'error' => 'Identité introuvable.'];
        }
        if (!isset(SseIdentityCatalog::DOC_TYPES[$type])) {
            return ['ok' => false, 'error' => 'Type de papier inconnu.'];
        }
        $pending = 0;
        foreach ($this->documents->list($tenantId, ['user_id' => $userId, 'limit' => 200]) as $d) {
            $status = self::docStatus($d);
            if ($status === 'requested') {
                $pending++;
            }
            if ((int) $d['identity_id'] === $identityId && $d['doc_type'] === $type && strtoupper((string) $d['country_code']) === strtoupper($countryCode)
                && in_array($status, ['requested', 'pending', 'valid'], true)) {
                return ['ok' => false, 'error' => 'Cette identité a déjà ce papier (valide ou en cours de demande).'];
            }
        }
        if ($pending >= self::MAX_PENDING_REQUESTS) {
            return ['ok' => false, 'error' => 'Vous avez déjà ' . self::MAX_PENDING_REQUESTS . ' demandes en attente : laissez un responsable les traiter.'];
        }
        $country = $this->countries->findByCode($tenantId, strtoupper($countryCode));
        if ($country === null || empty($country['active']) || !in_array($type, $country['doc_types'], true)) {
            return ['ok' => false, 'error' => 'Ce pays ne délivre pas ce type de papier.'];
        }
        // Numéro et dates proposés au format du pays : le responsable n'a plus qu'à valider et émettre.
        $proposal = $this->papers->proposal($tenantId, $identity, $country, $type);
        $res = $this->papers->saveDocument($tenantId, $userId, $identity, null, [
            'doc_type' => $type, 'country_code' => (string) $country['code'], 'notes' => self::REQUEST_NOTE,
            'doc_number' => $proposal['doc_number'], 'issued_on' => $proposal['issued_on'], 'expires_on' => $proposal['expires_on'], 'authority' => $proposal['authority'],
        ], false, $userId);
        if ($res['ok']) {
            $this->log($tenantId, $userId, 'op_identity.document_request', 'sse_id_document', (int) $res['id'], ['type' => $type, 'country' => strtoupper($countryCode)]);
        }

        return $res;
    }

    /**
     * Action du membre sur un de ses papiers : cancel (sa demande), lost, stolen, renew.
     *
     * @return array{ok: bool, message: string}
     */
    public function documentAction(int $tenantId, int $userId, int $docId, string $action): array
    {
        $doc = $this->papers->ownedDocument($tenantId, $userId, $docId, true);
        $status = $doc !== null ? self::docStatus($doc) : null;
        if ($doc === null || $status === null) {
            return ['ok' => false, 'message' => 'Papier introuvable.'];
        }
        if (self::docTransition($status, $action) === false) {
            return ['ok' => false, 'message' => 'Cette action n’est pas possible dans l’état actuel du papier.'];
        }
        if ($action === 'cancel') {
            $res = $this->papers->action($tenantId, $userId, $doc, 'delete', '', $userId);

            return ['ok' => $res['ok'], 'message' => $res['ok'] ? 'Demande annulée.' : $res['message']];
        }
        if ($action === 'lost' || $action === 'stolen') {
            $res = $this->papers->action($tenantId, $userId, $doc, $action, '', $userId);

            return ['ok' => $res['ok'], 'message' => $res['ok']
                ? ($action === 'lost' ? 'Papier déclaré perdu' : 'Papier déclaré volé') . ' : les contrôles et le passage aux fichiers le signalent désormais. Seul un responsable peut lever ce signalement.'
                : $res['message']];
        }
        // Renouvellement : une seule demande ouverte par papier d'origine.
        foreach ($this->documents->list($tenantId, ['user_id' => $userId, 'limit' => 200]) as $d) {
            if ((int) $d['identity_id'] === (int) $doc['identity_id'] && $d['doc_type'] === $doc['doc_type'] && $d['country_code'] === $doc['country_code']
                && in_array(self::docStatus($d), ['requested', 'pending'], true)) {
                return ['ok' => false, 'message' => 'Un renouvellement de ce papier est déjà demandé.'];
            }
        }
        $res = $this->papers->action($tenantId, $userId, $doc, 'duplicate', '', $userId);
        if (!$res['ok'] || !isset($res['id'])) {
            return ['ok' => false, 'message' => 'Le renouvellement n’a pas pu être demandé.'];
        }
        $copy = $this->documents->find((int) $res['id'], $tenantId);
        if ($copy !== null) {
            $this->documents->updateDetails((int) $copy['id'], $tenantId, [
                'doc_number' => $copy['doc_number'], 'issued_on' => (string) ($copy['issued_on'] ?? ''), 'expires_on' => (string) ($copy['expires_on'] ?? ''),
                'authority' => (string) ($copy['authority'] ?? ''), 'context_id' => (int) ($copy['context_id'] ?? 0), 'op_key' => (string) ($copy['op_key'] ?? ''),
                'notes' => self::REQUEST_NOTE . ' : renouvellement de ' . $doc['ref'],
            ]);
        }
        $this->log($tenantId, $userId, 'op_identity.document_renew', 'sse_id_document', (int) $res['id'], ['from' => (int) $doc['id']]);

        return ['ok' => true, 'message' => 'Renouvellement demandé : un responsable valide et émet le nouveau papier.'];
    }

    /** Papier du membre qu'il peut voir (aperçu, spécimen), sinon null. */
    public function ownedDocumentView(int $tenantId, int $userId, int $docId): ?array
    {
        foreach ($this->documents($tenantId, $userId) as $d) {
            if ($d['id'] === $docId) {
                return $d;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------ Couvertures

    /**
     * Couvertures du membre (les siennes et celles qu'un responsable lui a préparées), l'active d'abord.
     *
     * @return list<array<string, mixed>>
     */
    public function covers(int $tenantId, int $userId, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $nowIso = $now->format('Y-m-d\TH:i:s\Z');
        $states = $this->states->states($tenantId, $userId, OperatorHubRepository::KIND_COVER);
        $out = [];
        foreach ($this->exports->listFilesForUser($tenantId, $userId, 20) as $row) {
            $id = (int) $row['id'];
            $parsed = SseCoverExportService::parse((string) ($row['payload_json'] ?? ''), new \DateTimeImmutable('@0'));
            $cover = $parsed['ok'] ? $parsed['cover'] : [];
            $file = $parsed['ok'] ? $parsed['file'] : [];
            $state = $states[$id] ?? ['is_active' => false, 'archived_at' => null, 'revoked_at' => null, 'edited_at' => null];
            $expires = (string) ($file['expires_at'] ?? '');
            $status = $state['revoked_at'] !== null ? 'revoked' : (!$parsed['ok'] ? 'invalid' : ($expires < $nowIso ? 'expired' : 'valid'));
            $downloaded = ($row['downloaded_at'] ?? null) !== null ? (string) $row['downloaded_at'] : null;
            $sync = $downloaded === null ? 'never' : ($state['edited_at'] !== null && $state['edited_at'] > $downloaded ? 'update' : 'synced');
            $byManager = $row['created_by_user_id'] !== null && (int) $row['created_by_user_id'] !== $userId;
            $out[] = [
                'id' => $id,
                'ref' => (string) $row['ref'],
                'name' => (string) ($cover['name'] ?? $row['cover_name']),
                'sex' => (string) ($cover['sex'] ?? ''),
                'dob' => (string) ($cover['dob'] ?? ''),
                'pob' => (string) ($cover['pob'] ?? ''),
                'country' => (string) ($cover['country'] ?? $row['country_code']),
                'country_code' => (string) ($cover['country_code'] ?? $row['country_code']),
                'profession' => (string) ($cover['profession'] ?? ''),
                'employer' => (string) ($cover['employer'] ?? ''),
                'city' => (string) ($cover['city'] ?? ''),
                'address' => (string) ($cover['address'] ?? ''),
                'phone' => (string) ($cover['phone'] ?? ''),
                'travel' => (string) ($cover['travel'] ?? ''),
                'family' => (string) ($cover['family'] ?? ''),
                'history' => array_values(array_map('strval', $cover['history'] ?? [])),
                'contacts' => $cover['contacts'] ?? [],
                'docs' => $cover['docs'] ?? [],
                'photo_b64' => is_array($cover['photo'] ?? null) ? (string) $cover['photo']['b64'] : null,
                'created_at' => (string) $row['created_at'],
                'expires_at' => $expires,
                'by_manager' => $byManager,
                'status' => $status,
                'active' => $status === 'valid' && $state['is_active'],
                'sync' => $sync,
                'downloaded_at' => $downloaded,
                'edited_at' => $state['edited_at'],
                'editable' => $status === 'valid' && !$byManager,
            ];
        }
        usort($out, static fn (array $a, array $b): int => [!$a['active'], $a['status'] !== 'valid', -$a['id']] <=> [!$b['active'], $b['status'] !== 'valid', -$b['id']]);

        return $out;
    }

    /**
     * Génère une couverture (personne fictive tirée du registre du pays, ou identité Athena). La première devient active.
     *
     * @return array{ok: true, id: int, ref: string, name: string}|array{ok: false, error: string}
     */
    public function generateCover(int $tenantId, int $userId, string $source, string $countryCode, ?string $sex, string $lang, ?string $photoBytes, ?\DateTimeImmutable $now = null): array
    {
        $res = $source === 'athena'
            ? $this->covers->generateFromIdentity($tenantId, $userId, $userId, $lang, false, $now, $this->identities, $this->documents)
            : $this->covers->generate($tenantId, $userId, $userId, $countryCode, $sex, $lang, $photoBytes, false, $now);
        if ($res['ok']) {
            $hasActive = false;
            foreach ($this->covers($tenantId, $userId, $now) as $c) {
                $hasActive = $hasActive || $c['active'];
            }
            if (!$hasActive) {
                $this->states->setOnlyActive($tenantId, $userId, OperatorHubRepository::KIND_COVER, (int) $res['id'], $userId);
            }
        }

        return $res;
    }

    /** Couverture du membre dans sa communauté, sinon null. */
    private function ownedCover(int $tenantId, int $userId, int $coverId): ?array
    {
        $row = $coverId > 0 ? $this->exports->find($tenantId, $coverId) : null;

        return $row !== null && (int) $row['user_id'] === $userId ? $row : null;
    }

    /** @return array<string, mixed>|null */
    private function coverView(int $tenantId, int $userId, int $coverId, ?\DateTimeImmutable $now = null): ?array
    {
        foreach ($this->covers($tenantId, $userId, $now) as $c) {
            if ($c['id'] === $coverId) {
                return $c;
            }
        }

        return null;
    }

    /**
     * Retouche la légende (métier, employeur, ville, adresse, motif du déplacement, famille, histoire). Le fichier est
     * réécrit et resigné sous la même référence et la même date d'expiration : le téléphone voit une mise à jour.
     *
     * @param array<string, mixed> $input champs de COVER_EDITABLE et « history » (texte, une ligne par élément)
     * @return array{ok: bool, error?: string}
     */
    public function editCover(int $tenantId, int $userId, int $coverId, array $input, ?\DateTimeImmutable $now = null): array
    {
        $row = $this->ownedCover($tenantId, $userId, $coverId);
        $view = $row !== null ? $this->coverView($tenantId, $userId, $coverId, $now) : null;
        if ($row === null || $view === null) {
            return ['ok' => false, 'error' => 'Couverture introuvable.'];
        }
        if (!$view['editable']) {
            return ['ok' => false, 'error' => $view['by_manager']
                ? 'Cette couverture a été préparée par un responsable : elle ne se modifie pas ici.'
                : 'Une couverture expirée ou révoquée ne se modifie plus : dupliquez-la.'];
        }
        $parsed = SseCoverExportService::parse((string) $row['payload_json'], new \DateTimeImmutable('@0'));
        if (!$parsed['ok']) {
            return ['ok' => false, 'error' => 'Fichier de couverture illisible.'];
        }
        $cover = $parsed['cover'];
        foreach (self::COVER_EDITABLE as $k => $max) {
            if (!array_key_exists($k, $input)) {
                continue;
            }
            $v = trim((string) $input[$k]);
            if (mb_strlen($v) > $max || preg_match('/[\x00-\x1F\x7F]/u', $v) === 1) {
                return ['ok' => false, 'error' => 'Un champ est trop long ou contient des caractères interdits.'];
            }
            $cover[$k] = $v;
        }
        if (array_key_exists('history', $input)) {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $input['history']) ?: []), static fn (string $l): bool => $l !== ''));
            if (count($lines) > self::MAX_HISTORY_LINES) {
                return ['ok' => false, 'error' => 'L’histoire tient en ' . self::MAX_HISTORY_LINES . ' lignes au plus.'];
            }
            foreach ($lines as $l) {
                if (mb_strlen($l) > 255 || preg_match('/[\x00-\x1F\x7F]/u', $l) === 1) {
                    return ['ok' => false, 'error' => 'Une ligne de l’histoire dépasse 255 caractères.'];
                }
            }
            $cover['history'] = $lines;
        }
        try {
            $generated = new \DateTimeImmutable((string) $parsed['file']['generated_at'], new \DateTimeZone('UTC'));
            $file = $this->covers->buildFile($cover, $tenantId, $userId, (string) $row['ref'], $generated);
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'La couverture n’a pas pu être réécrite.'];
        }
        // Même contrôle que le mod avant d'accepter le fichier.
        if (strlen($file['json']) > SseCoverExportService::MAX_BYTES || !SseCoverExportService::parse($file['json'], new \DateTimeImmutable('@0'))['ok']) {
            return ['ok' => false, 'error' => 'La légende modifiée n’est pas valable (texte trop long ?).'];
        }
        $this->exports->updatePayload($tenantId, $userId, $coverId, [
            'cover_name' => (string) $cover['name'], 'doc_number' => (string) ($cover['docs'][0]['number'] ?? ''),
            'content_sha' => $file['sha'], 'payload_json' => $file['json'],
        ]);
        $at = ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $this->states->setState($tenantId, $userId, OperatorHubRepository::KIND_COVER, $coverId, ['edited_at' => $at], $userId);
        $this->log($tenantId, $userId, 'op_identity.cover_edit', 'sse_cover_export', $coverId, ['ref' => (string) $row['ref']]);

        return ['ok' => true];
    }

    /**
     * Copie une couverture sous une nouvelle référence (compte dans la limite de générations sur 24 heures).
     *
     * @return array{ok: bool, id?: int, error?: string}
     */
    public function duplicateCover(int $tenantId, int $userId, int $coverId, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $row = $this->ownedCover($tenantId, $userId, $coverId);
        $parsed = $row !== null ? SseCoverExportService::parse((string) $row['payload_json'], new \DateTimeImmutable('@0')) : ['ok' => false];
        if ($row === null || !$parsed['ok']) {
            return ['ok' => false, 'error' => 'Couverture introuvable.'];
        }
        if ($this->exports->countSince($tenantId, $userId, $now->modify('-24 hours')->format('Y-m-d H:i:s')) >= SseCoverExportService::LIMIT_SELF) {
            return ['ok' => false, 'error' => 'Limite de générations atteinte pour aujourd’hui. Réessayez dans 24 heures.'];
        }
        $cover = $parsed['cover'];
        $ref = 'CVR-' . strtoupper(bin2hex(random_bytes(4)));
        $file = $this->covers->buildFile($cover, $tenantId, $userId, $ref, $now);
        $id = $this->exports->insert($tenantId, $userId, $userId, [
            'ref' => $ref, 'seed' => (int) $cover['seed'], 'country_code' => (string) $cover['country_code'], 'cover_name' => (string) $cover['name'],
            'doc_number' => (string) ($cover['docs'][0]['number'] ?? ''), 'content_sha' => $file['sha'], 'payload_json' => $file['json'],
        ], $now->format('Y-m-d H:i:s'));
        $this->log($tenantId, $userId, 'op_identity.cover_duplicate', 'sse_cover_export', $id, ['ref' => $ref, 'from' => $coverId]);

        return ['ok' => true, 'id' => $id];
    }

    /**
     * Révoque une couverture : plus téléchargeable, plus proposée au téléphone. Définitif.
     *
     * @return array{ok: bool, error?: string}
     */
    public function revokeCover(int $tenantId, int $userId, int $coverId, ?\DateTimeImmutable $now = null): array
    {
        $row = $this->ownedCover($tenantId, $userId, $coverId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Couverture introuvable.'];
        }
        $at = ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $this->states->setState($tenantId, $userId, OperatorHubRepository::KIND_COVER, $coverId, ['revoked_at' => $at, 'is_active' => 0], $userId);
        $this->log($tenantId, $userId, 'op_identity.cover_revoke', 'sse_cover_export', $coverId, ['ref' => (string) $row['ref']]);

        return ['ok' => true];
    }

    /**
     * Choisit la couverture active (une seule par membre) : celle que le membre joue.
     *
     * @return array{ok: bool, error?: string}
     */
    public function activateCover(int $tenantId, int $userId, int $coverId, ?\DateTimeImmutable $now = null): array
    {
        $view = $this->ownedCover($tenantId, $userId, $coverId) !== null ? $this->coverView($tenantId, $userId, $coverId, $now) : null;
        if ($view === null) {
            return ['ok' => false, 'error' => 'Couverture introuvable.'];
        }
        if ($view['status'] !== 'valid') {
            return ['ok' => false, 'error' => 'Une couverture expirée ou révoquée ne peut pas devenir active.'];
        }
        $this->states->setOnlyActive($tenantId, $userId, OperatorHubRepository::KIND_COVER, $coverId, $userId);
        $this->log($tenantId, $userId, 'op_identity.cover_activate', 'sse_cover_export', $coverId, ['ref' => $view['ref']]);

        return ['ok' => true];
    }

    /**
     * Fichier à télécharger (même fichier que le téléphone), null : pas à ce membre, révoquée, inconnue.
     *
     * @return array{row: array<string, mixed>, json: string}|null
     */
    public function downloadCover(int $tenantId, int $userId, int $coverId, ?\DateTimeImmutable $now = null): ?array
    {
        return $coverId > 0 ? $this->covers->download($tenantId, $coverId, $userId, false, $now, $userId) : null;
    }

    /** Générations de couverture restantes sur 24 heures. */
    public function coverQuota(int $tenantId, int $userId, ?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return max(0, SseCoverExportService::LIMIT_SELF - $this->exports->countSince($tenantId, $userId, $now->modify('-24 hours')->format('Y-m-d H:i:s')));
    }

    // ------------------------------------------------------------------ Synthèse (page d'accueil des outils)

    /**
     * Chiffres de la bande d'en-tête et pastilles des cartes.
     *
     * @return array{identity_missing: int, has_primary: bool, papers_valid: int, papers_attention: int, papers_pending: int, cover_active: ?string, covers: int}
     */
    public function summary(int $tenantId, int $userId): array
    {
        $out = ['identity_missing' => 0, 'has_primary' => false, 'papers_valid' => 0, 'papers_attention' => 0, 'papers_pending' => 0, 'cover_active' => null, 'covers' => 0];
        foreach ($this->identities($tenantId, $userId) as $i) {
            if ($i['is_primary']) {
                $out['has_primary'] = true;
                $out['identity_missing'] = count($i['completeness']['missing']);
            }
        }
        foreach ($this->documents($tenantId, $userId) as $d) {
            match (true) {
                $d['status'] === 'valid' && !$d['expires_soon'] => $out['papers_valid']++,
                in_array($d['status'], ['requested', 'pending'], true) => $out['papers_pending']++,
                $d['status'] === 'revoked' => null,
                default => $out['papers_attention']++,
            };
        }
        foreach ($this->covers($tenantId, $userId) as $c) {
            if ($c['status'] === 'valid') {
                $out['covers']++;
                if ($c['active']) {
                    $out['cover_active'] = $c['name'];
                }
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function countries(int $tenantId): array
    {
        // Même ordre que le mod : pays complets d'abord (France, Allemagne, États-Unis…), puis le reste du registre.
        return \App\Support\SseDocumentTypes::sortCountries($this->countries->listForTenant($tenantId, true));
    }

    /** @return list<array<string, mixed>> */
    public function history(int $tenantId, int $userId, int $limit = 20): array
    {
        return $this->states->auditTrail($tenantId, $userId, $limit);
    }

    /** @param array<string, mixed> $extra */
    private function log(int $tenantId, int $userId, string $action, string $type, int $objectId, array $extra = []): void
    {
        ($this->audit)([
            'tenant_id' => $tenantId,
            'actor_user_id' => $userId,
            'actor_label' => self::ACTOR,
            'action' => $action,
            'object_type' => $type,
            'object_id' => $objectId,
            'after' => ['member_user_id' => $userId] + $extra,
        ]);
    }
}
