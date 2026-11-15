<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdentityEventRepository;
use App\Repositories\SseIdentityHistoryRepository;
use App\Repositories\SseIdentityRepository;
use App\Support\Migrations\SchemaInspector;
use App\Support\MemberIdentity;
use App\Support\SseIdentityCatalog;

/**
 * L'identité OFFICIELLE (civile, principale) d'un membre suit son état civil.
 *
 * Règle : pour un membre (sse_identities.user_id), l'identité de nature « civil » la plus ancienne est sa principale. Son nom et
 * son prénom sont ceux du profil (user_profiles, l'état civil du personnage, voir App\Support\MemberIdentity) ; sa date de
 * naissance est celle du PERSONNAGE (user_profiles.character_birth_date), jamais la date de naissance réelle du joueur ; son lieu
 * de naissance est celui du dossier du personnage (personnel_profiles.birth_place). Quand le profil n'en donne pas : la date de
 * naissance déjà portée par l'identité est reprise si le nom était déjà bon, sinon une date COHÉRENTE et STABLE est tirée
 * (graine = communauté + membre, 24 à 45 ans au moment de l'ouverture du compte) puis enregistrée sur le profil.
 *
 * Les identités de couverture, d'emprunt, falsifiées ou générées ne sont jamais touchées, ni les sujets non membres. Une
 * identité marquée « nom volontairement différent » (name_override) est respectée en entier. Rien n'est supprimé : chaque
 * valeur remplacée va dans sse_identity_history (décision « civil », restaurable) et dans la chronologie de l'identité ; les
 * papiers OFFICIELS de l'identité portent la nouvelle valeur (le titulaire est lu sur l'identité) avec la mention « mis à jour le ».
 * Le numéro, l'autorité et les dates de ces papiers ne changent pas ; le paquet du jeu est recalculé à partir de l'identité,
 * donc rien ne demande de reconstruire le mod.
 */
final class SseMemberCivilIdentityService
{
    public const ALIGNED = 'aligned';
    public const MISALIGNED = 'misaligned';
    public const MISSING = 'missing';
    public const OVERRIDE = 'override';
    public const NO_CIVIL_STATE = 'no_civil_state';
    public const ANONYMIZED = 'anonymized';
    public const UNKNOWN = 'unknown';

    /** Création automatique d'une identité à l'enregistrement du profil : volontairement désactivée (bouton et outil seulement). */
    public const AUTO_CREATE = false;

    /** Champs repris de l'état civil : clé => libellé. */
    public const FIELDS = [
        'last_name' => 'Nom',
        'first_name' => 'Prénom',
        'birth_date' => 'Date de naissance',
        'birth_place' => 'Lieu de naissance',
    ];

    /** @var \WeakMap<\PDO, array{override: bool, synced: bool, birth: bool, history: bool}>|null */
    private static ?\WeakMap $capabilities = null;

    private Database $db;
    private ?SseIdentityRepository $identities;
    private ?SseIdentityHistoryRepository $history = null;

    /** @var callable(array<string, mixed>): void */
    private $audit;

    public function __construct(?Database $db = null, ?SseIdentityRepository $identities = null, ?callable $audit = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->identities = $identities;
        $this->audit = $audit ?? static function (array $entry): void {
            try {
                (new SseAuditLogRepository())->write($entry);
            } catch (\Throwable $e) {
                error_log('Identité officielle : journal d’audit indisponible : ' . $e->getMessage());
            }
        };
    }

    /** Colonnes de l'étape 4690 et table d'historique présentes ? (mode dégradé sinon) @return array{override: bool, synced: bool, birth: bool, history: bool} */
    public static function capabilities(): array
    {
        $pdo = Database::getPdo();
        self::$capabilities ??= new \WeakMap();
        if (!isset(self::$capabilities[$pdo])) {
            $s = new SchemaInspector($pdo);
            try {
                self::$capabilities[$pdo] = [
                    'override' => $s->columnExists('sse_identities', 'name_override'),
                    'synced' => $s->columnExists('sse_identities', 'civil_synced_at'),
                    'birth' => $s->columnExists('user_profiles', 'character_birth_date'),
                    'history' => $s->tableExists('sse_identity_history'),
                ];
            } catch (\Throwable) {
                self::$capabilities[$pdo] = ['override' => false, 'synced' => false, 'birth' => false, 'history' => false];
            }
        }

        return self::$capabilities[$pdo];
    }

    /** Oublie les colonnes repérées (tests qui créent ou retirent une colonne après coup). */
    public static function forgetCapabilities(): void
    {
        self::$capabilities = null;
    }

    private function identities(): SseIdentityRepository
    {
        return $this->identities ??= new SseIdentityRepository($this->db);
    }

    private function history(): SseIdentityHistoryRepository
    {
        return $this->history ??= new SseIdentityHistoryRepository($this->db);
    }

    // ------------------------------------------------------------------ Dérivation

    /**
     * Date de naissance du personnage tirée pour ce membre : déterministe (même communauté, même membre, même date), 24 à 45 ans
     * à l'ouverture du compte (année de $memberSince, 2025 à défaut). Elle ne dépend ni de la date du jour ni de l'ordre des appels.
     */
    public static function generateBirthDate(int $tenantId, int $userId, ?string $memberSince = null): string
    {
        $ref = (int) substr(trim((string) $memberSince), 0, 4);
        if ($ref < 2000 || $ref > 2100) {
            $ref = 2025;
        }
        $seed = (int) (abs(crc32('athena-civil|' . $tenantId . '|' . $userId)) % (SseIdentityGeneratorService::MAX_SEED + 1));
        $age = 24 + SseIdentityGeneratorService::hash($seed, 'age') % 22;
        $month = 1 + SseIdentityGeneratorService::hash($seed, 'mon') % 12;
        $day = 1 + SseIdentityGeneratorService::hash($seed, 'day') % 28;

        return sprintf('%04d-%02d-%02d', $ref - $age, $month, $day);
    }

    /** Date au format AAAA-MM-JJ réellement existante, sinon chaîne vide. */
    public static function validDate(mixed $raw): string
    {
        $raw = substr(trim(is_scalar($raw) ? (string) $raw : ''), 0, 10);
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

        return $d !== false && $d->format('Y-m-d') === $raw ? $raw : '';
    }

    /**
     * @return array<string, mixed>|null le membre (users + prénom, nom et naissance du personnage), null s'il n'est pas de cette communauté
     */
    private function member(int $tenantId, int $userId): ?array
    {
        if ($tenantId < 1 || $userId < 1) {
            return null;
        }
        $birth = self::capabilities()['birth'];
        try {
            return $this->db->fetchOne(
                'SELECT u.*, p.first_name AS civil_first, p.last_name AS civil_last' . ($birth ? ', p.character_birth_date AS civil_birth' : '')
                . ' FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id WHERE u.id = :u AND u.tenant_id = :t',
                ['u' => $userId, 't' => $tenantId]
            );
        } catch (\Throwable $e) {
            error_log('Identité officielle : état civil du membre indisponible : ' . $e->getMessage());

            return null;
        }
    }

    /** @return array<string, mixed> dossier du personnage (lieu de naissance, nationalité, sexe), vide s'il n'existe pas */
    private function personnel(int $tenantId, int $userId): array
    {
        try {
            return $this->db->fetchOne('SELECT birth_place, nationality, sex FROM personnel_profiles WHERE user_id = :u AND tenant_id = :t LIMIT 1', ['u' => $userId, 't' => $tenantId]) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    private static function isDeletedAccount(array $user): bool
    {
        $email = strtolower(trim((string) ($user['email'] ?? '')));

        return str_starts_with($email, 'deleted-') && str_ends_with($email, '@deleted.invalid');
    }

    /**
     * Identités de nature « civil » du membre, la plus ancienne d'abord.
     *
     * @return list<array<string, mixed>>
     */
    private function civilIdentities(int $tenantId, int $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM sse_identities WHERE tenant_id = :t AND user_id = :u AND kind = 'civil' ORDER BY id ASC LIMIT 20",
            ['t' => $tenantId, 'u' => $userId]
        );

        return array_map([SseIdentityRepository::class, 'hydrate'], $rows);
    }

    /**
     * Où en est l'identité officielle de ce membre ? Lecture seule, avec ce que l'alignement ferait.
     *
     * @return array{
     *     state: string, user_id: int, identity: ?array<string, mixed>, duplicates: list<int>, changes: list<array{field: string, label: string, old: string, new: string}>,
     *     target: array<string, string>, sources: array<string, string>, documents: list<array<string, mixed>>, other_documents: int,
     *     can_override: bool, degraded: list<string>
     * }
     */
    public function inspect(int $tenantId, int $userId): array
    {
        $cap = self::capabilities();
        $out = [
            'state' => self::UNKNOWN, 'user_id' => $userId, 'identity' => null, 'duplicates' => [], 'changes' => [], 'target' => [],
            'sources' => [], 'documents' => [], 'other_documents' => 0, 'can_override' => $cap['override'],
            'degraded' => array_values(array_filter([
                $cap['override'] ? '' : 'name_override',
                $cap['birth'] ? '' : 'character_birth_date',
                $cap['history'] ? '' : 'sse_identity_history',
            ])),
        ];
        $member = $this->member($tenantId, $userId);
        if ($member === null) {
            return $out;
        }
        if (self::isDeletedAccount($member)) {
            // Compte supprimé : jamais nommé, jamais aligné.
            return ['state' => self::ANONYMIZED] + $out;
        }
        $civil = $this->civilIdentities($tenantId, $userId);
        $identity = $civil[0] ?? null;
        $out['identity'] = $identity;
        $out['duplicates'] = array_map(static fn (array $i): int => (int) $i['id'], array_slice($civil, 1));

        $first = (string) (MemberIdentity::cleanNamePart($member['civil_first'] ?? '') ?? '');
        $last = (string) (MemberIdentity::cleanNamePart($member['civil_last'] ?? '') ?? '');
        $nameSource = 'civil';
        if ($first === '' && $last === '') {
            $nameSource = trim((string) ($member['callsign'] ?? '')) !== '' ? 'callsign' : 'fallback';
            $last = $nameSource === 'callsign' ? mb_substr(trim((string) $member['callsign']), 0, 120) : 'Membre';
        } elseif ($last === '') {
            [$last, $first] = [$first, ''];
        }

        if ($identity === null) {
            $out['state'] = self::MISSING;
            $out['target'] = $this->target($tenantId, $userId, $member, null, $first, $last, true);
            $out['sources'] = ['name' => $nameSource] + $out['target']['_sources'];
            unset($out['target']['_sources']);
            foreach (self::FIELDS as $field => $label) {
                $out['changes'][] = ['field' => $field, 'label' => $label, 'old' => '', 'new' => (string) ($out['target'][$field] ?? '')];
            }

            return $out;
        }
        $out['documents'] = $this->officialDocuments($tenantId, (int) $identity['id']);
        $out['other_documents'] = max(0, $this->countDocuments($tenantId, (int) $identity['id']) - count($out['documents']));
        if (!empty($identity['name_override'])) {
            $out['state'] = self::OVERRIDE;

            return $out;
        }
        if ($nameSource !== 'civil') {
            // Profil sans nom : on ne remplace pas un nom par un indicatif.
            $out['state'] = self::NO_CIVIL_STATE;

            return $out;
        }
        $target = $this->target($tenantId, $userId, $member, $identity, $first, $last, false);
        $out['sources'] = ['name' => $nameSource] + $target['_sources'];
        unset($target['_sources']);
        $out['target'] = $target;
        $out['changes'] = self::diff($identity, $target);
        $out['state'] = $out['changes'] === [] ? self::ALIGNED : self::MISALIGNED;

        return $out;
    }

    /**
     * Valeurs visées pour l'identité (nom, prénom, naissance, lieu) et d'où elles viennent.
     *
     * @param array<string, mixed> $member
     * @param array<string, mixed>|null $identity identité actuelle, null pour une création
     * @return array<string, mixed>
     */
    private function target(int $tenantId, int $userId, array $member, ?array $identity, string $first, string $last, bool $create): array
    {
        $personnel = $this->personnel($tenantId, $userId);
        $nameChanges = $identity !== null
            && SseIdentityHolderService::key((string) $identity['first_name'] . ' ' . (string) $identity['last_name']) !== SseIdentityHolderService::key($first . ' ' . $last);
        $sources = [];

        $dob = self::validDate($member['civil_birth'] ?? '');
        if ($dob !== '') {
            $sources['birth_date'] = 'profile';
        } elseif ($identity !== null && !$nameChanges && self::validDate($identity['birth_date'] ?? '') !== '') {
            $dob = self::validDate($identity['birth_date']);
            $sources['birth_date'] = 'identity';
        } else {
            $dob = self::generateBirthDate($tenantId, $userId, (string) ($member['created_at'] ?? ''));
            $sources['birth_date'] = 'generated';
        }

        $place = mb_substr(trim((string) ($personnel['birth_place'] ?? '')), 0, 120);
        if ($place !== '') {
            $sources['birth_place'] = 'profile';
        } elseif ($identity !== null && !$nameChanges) {
            $place = (string) ($identity['birth_place'] ?? '');
            $sources['birth_place'] = 'identity';
        } else {
            $nationality = $identity !== null ? (string) ($identity['nationality'] ?? '') : '';
            $place = $this->generatedPlace($tenantId, $userId, $nationality);
            $sources['birth_place'] = $place !== '' ? 'generated' : 'none';
        }

        $target = ['first_name' => $first, 'last_name' => $last, 'birth_date' => $dob, 'birth_place' => $place, '_sources' => $sources];
        if ($create) {
            $proposal = $this->proposal($tenantId, $userId, $personnel);
            $target['sex'] = $proposal['sex'];
            $target['nationality'] = $proposal['nationality'];
        }

        return $target;
    }

    /** @param array<string, mixed> $personnel @return array{sex: string, nationality: string} */
    private function proposal(int $tenantId, int $userId, array $personnel): array
    {
        try {
            $p = MemberIdentityPapersService::primaryProposal(
                ['nationality' => ''],
                $personnel,
                (new SseIdCountryRepository($this->db))->listForTenant($tenantId, false)
            );

            return ['sex' => $p['sex'], 'nationality' => $p['nationality']];
        } catch (\Throwable) {
            return ['sex' => 'X', 'nationality' => ''];
        }
    }

    /** Lieu de naissance tiré (même membre, même lieu) parmi ceux de la région du pays de l'identité ; vide sans réservoir. */
    private function generatedPlace(int $tenantId, int $userId, string $nationality): string
    {
        if ($nationality === '') {
            return '';
        }
        try {
            $country = (new SseIdCountryRepository($this->db))->findByCode($tenantId, $nationality);
        } catch (\Throwable) {
            return '';
        }
        $places = $country !== null ? SseIdentityGeneratorService::placesFor((string) ($country['region'] ?? '')) : [];
        if ($places === []) {
            return '';
        }

        return SseIdentityGeneratorService::pick((int) (abs(crc32('athena-civil|' . $tenantId . '|' . $userId)) % (SseIdentityGeneratorService::MAX_SEED + 1)), 'pob', $places);
    }

    /**
     * Écarts entre l'identité et ce que dit l'état civil. Le nom ne compte comme différent que si ses mots changent (« GYLENHALL jake »
     * et « Jake Gylenhall » sont le même nom : rien n'est réécrit).
     *
     * @param array<string, mixed> $identity
     * @param array<string, mixed> $target
     * @return list<array{field: string, label: string, old: string, new: string}>
     */
    public static function diff(array $identity, array $target): array
    {
        $changes = [];
        $sameName = SseIdentityHolderService::key((string) $identity['first_name'] . ' ' . (string) $identity['last_name'])
            === SseIdentityHolderService::key((string) $target['first_name'] . ' ' . (string) $target['last_name']);
        foreach (self::FIELDS as $field => $label) {
            if ($sameName && in_array($field, ['first_name', 'last_name'], true)) {
                continue;
            }
            $old = trim((string) ($identity[$field] ?? ''));
            $new = trim((string) ($target[$field] ?? ''));
            if ($field === 'birth_date') {
                $old = substr($old, 0, 10);
            }
            if ($old !== $new) {
                $changes[] = ['field' => $field, 'label' => $label, 'old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }

    /** @return list<array<string, mixed>> papiers officiels (hors révoqués) de l'identité */
    private function officialDocuments(int $tenantId, int $identityId): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT id, doc_type, country_code, doc_number, workflow FROM sse_id_documents
                 WHERE tenant_id = :t AND identity_id = :i AND st_origin = 'official' AND workflow <> 'revoked' ORDER BY id ASC LIMIT 50",
                ['t' => $tenantId, 'i' => $identityId]
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'ref' => SseIdentityCatalog::documentRef((int) $r['id']),
            'type' => SseIdentityCatalog::label(SseIdentityCatalog::DOC_TYPES, (string) $r['doc_type']),
            'country' => (string) $r['country_code'],
            'number' => (string) $r['doc_number'],
            'workflow' => (string) $r['workflow'],
        ], $rows);
    }

    private function countDocuments(int $tenantId, int $identityId): int
    {
        try {
            return (int) ($this->db->fetchOne('SELECT COUNT(*) AS n FROM sse_id_documents WHERE tenant_id = :t AND identity_id = :i', ['t' => $tenantId, 'i' => $identityId])['n'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    // ------------------------------------------------------------------ Alignement

    /**
     * Aligne l'identité officielle du membre sur son état civil (ou la crée, si $create). Une seule transaction : en cas d'échec rien
     * n'est modifié.
     *
     * @param string $via manual (bouton), auto (changement d'état civil), cli (outil en ligne de commande)
     * @param ?int $expectIdentityId identité vue à l'écran : si elle a changé entre-temps, rien n'est fait
     * @return array{ok: bool, status: string, message: string, identity_id?: int, changes?: list<array{field: string, label: string, old: string, new: string}>, documents?: int}
     */
    public function align(int $tenantId, int $userId, ?int $actorId, string $via = 'manual', ?int $expectIdentityId = null, bool $create = true): array
    {
        $info = $this->inspect($tenantId, $userId);
        $identity = $info['identity'];
        if ($expectIdentityId !== null && $expectIdentityId > 0 && (int) ($identity['id'] ?? 0) !== $expectIdentityId) {
            return ['ok' => false, 'status' => 'stale', 'message' => 'L’identité du membre a changé entre-temps : rechargez la page et recommencez.'];
        }
        switch ($info['state']) {
            case self::ALIGNED:
                return ['ok' => true, 'status' => self::ALIGNED, 'message' => 'L’identité officielle reprend déjà l’état civil du membre.', 'identity_id' => (int) $identity['id'], 'changes' => [], 'documents' => 0];
            case self::OVERRIDE:
                return ['ok' => false, 'status' => self::OVERRIDE, 'message' => 'Le nom de cette identité est volontairement différent de l’état civil : retirez ce réglage pour l’aligner.', 'identity_id' => (int) $identity['id']];
            case self::NO_CIVIL_STATE:
                return ['ok' => false, 'status' => self::NO_CIVIL_STATE, 'message' => 'Le membre n’a ni prénom ni nom d’état civil : l’identité n’est pas modifiée. Renseignez d’abord son état civil.'];
            case self::ANONYMIZED:
                return ['ok' => false, 'status' => self::ANONYMIZED, 'message' => 'Compte supprimé : l’identité n’est pas modifiée.'];
            case self::UNKNOWN:
                return ['ok' => false, 'status' => self::UNKNOWN, 'message' => 'Membre introuvable dans cette communauté.'];
            case self::MISSING:
                if (!$create) {
                    return ['ok' => false, 'status' => self::MISSING, 'message' => 'Aucune identité officielle : rien à aligner.'];
                }
                break;
        }
        if ($info['state'] === self::MISALIGNED && !self::capabilities()['history'] && !SseIdentityEventRepository::ready()) {
            return ['ok' => false, 'status' => 'no_history', 'message' => 'L’historique des identités n’est pas encore installé (étapes 4250 et 4600) : rien n’est modifié, pour ne perdre aucune valeur.'];
        }

        // Une transaction à soi, ou un point de reprise dans celle de l'appelant : en cas d'échec rien de ce membre n'est modifié.
        $pdo = Database::getPdo();
        $own = !$pdo->inTransaction();
        $own ? $pdo->beginTransaction() : $pdo->exec('SAVEPOINT civil_align');
        try {
            $result = $info['state'] === self::MISSING
                ? $this->createDerived($tenantId, $userId, $info, $actorId, $via)
                : $this->applyChanges($tenantId, $userId, $info, $actorId, $via);
            $own ? $pdo->commit() : $pdo->exec('RELEASE SAVEPOINT civil_align');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $own ? $pdo->rollBack() : $pdo->exec('ROLLBACK TO SAVEPOINT civil_align');
            }
            error_log('Identité officielle : alignement du membre ' . $userId . ' impossible : ' . $e->getMessage());

            return ['ok' => false, 'status' => 'error', 'message' => 'L’alignement a échoué, rien n’a été modifié : ' . $e->getMessage()];
        }

        try {
            // Le paquet d'identité du jeu est recalculé à sa prochaine lecture (le titulaire des papiers a changé).
            \App\Support\SseIdentityPackCache::invalidate();
        } catch (\Throwable) {
            // Une écriture hors HTTP se voit au plus tard à la fin du délai du cache.
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $info inspect()
     * @return array{ok: bool, status: string, message: string, identity_id: int, changes: list<array{field: string, label: string, old: string, new: string}>, documents: int}
     */
    private function applyChanges(int $tenantId, int $userId, array $info, ?int $actorId, string $via): array
    {
        $identity = $info['identity'];
        $identityId = (int) $identity['id'];
        $changed = [];
        foreach ($info['changes'] as $c) {
            $changed[$c['field']] = $c['new'] !== '' ? $c['new'] : null;
        }
        $cap = self::capabilities();
        if ($cap['history']) {
            foreach ($info['changes'] as $c) {
                $this->history()->add($tenantId, [
                    'user_id' => $userId, 'identity_id' => $identityId, 'field' => $c['field'],
                    'kept_value' => $c['new'] !== '' ? $c['new'] : null, 'lost_value' => $c['old'] !== '' ? $c['old'] : null,
                    'lost_side' => 'athena', 'decision' => 'civil', 'actor_user_id' => $actorId,
                ]);
            }
        }
        $this->identities()->update($identityId, $tenantId, array_merge($identity, $changed));
        $this->touch($tenantId, $identityId);
        $this->persistBirthDate($userId, (string) $info['target']['birth_date'], $info['sources']['birth_date'] ?? '');
        $docs = $this->noteDocuments($tenantId, $info['documents'], array_column($info['changes'], 'label'));
        $labels = implode(', ', array_column($info['changes'], 'label'));
        $this->event($tenantId, $identityId, 'Aligné sur l’état civil du membre : ' . $labels . '.', ['changes' => $info['changes'], 'documents' => $docs], $actorId, $via);
        $this->log($tenantId, $actorId, 'id_member.civil_align', $identityId, $userId, ['via' => $via, 'fields' => array_column($info['changes'], 'field'), 'documents' => $docs]);

        return [
            'ok' => true, 'status' => self::MISALIGNED, 'identity_id' => $identityId, 'changes' => $info['changes'], 'documents' => $docs,
            'message' => 'Identité officielle alignée sur l’état civil (' . $labels . '). ' . $docs . ' papier(s) officiel(s) portent la nouvelle valeur ; les anciennes valeurs sont dans l’historique.',
        ];
    }

    /**
     * @param array<string, mixed> $info inspect() d'un membre sans identité officielle
     * @return array{ok: bool, status: string, message: string, identity_id: int, changes: list<array{field: string, label: string, old: string, new: string}>, documents: int}
     */
    private function createDerived(int $tenantId, int $userId, array $info, ?int $actorId, string $via): array
    {
        $t = $info['target'];
        $id = $this->identities()->create($tenantId, [
            'kind' => 'civil', 'user_id' => $userId,
            'last_name' => $t['last_name'], 'first_name' => $t['first_name'], 'sex' => $t['sex'] ?? 'X',
            'birth_date' => $t['birth_date'], 'birth_place' => $t['birth_place'], 'nationality' => $t['nationality'] ?? '',
            'notes' => 'Identité générée à partir de l’état civil du membre.',
        ], $actorId);
        $this->touch($tenantId, $id);
        $this->persistBirthDate($userId, (string) $t['birth_date'], $info['sources']['birth_date'] ?? '');
        $changes = [];
        foreach (self::FIELDS as $field => $label) {
            $changes[] = ['field' => $field, 'label' => $label, 'old' => '', 'new' => (string) ($t[$field] ?? '')];
        }
        $this->event($tenantId, $id, 'Identité générée à partir de l’état civil du membre.', ['changes' => $changes], $actorId, $via);
        $this->log($tenantId, $actorId, 'id_member.civil_create', $id, $userId, ['via' => $via]);

        return ['ok' => true, 'status' => self::MISSING, 'identity_id' => $id, 'changes' => $changes, 'documents' => 0, 'message' => 'Identité officielle créée à partir de l’état civil du membre.'];
    }

    private function touch(int $tenantId, int $identityId): void
    {
        if (self::capabilities()['synced']) {
            $this->db->execute('UPDATE sse_identities SET civil_synced_at = :now WHERE id = :id AND tenant_id = :t', ['now' => gmdate('Y-m-d H:i:s'), 'id' => $identityId, 't' => $tenantId]);
        }
    }

    /** Enregistre la date de naissance du personnage sur le profil, une fois (une valeur déjà posée n'est jamais remplacée). */
    private function persistBirthDate(int $userId, string $date, string $source): void
    {
        if (!self::capabilities()['birth'] || $source === 'profile' || self::validDate($date) === '') {
            return;
        }
        $exists = $this->db->fetchOne('SELECT user_id FROM user_profiles WHERE user_id = :u', ['u' => $userId]) !== null;
        if ($exists) {
            $this->db->execute('UPDATE user_profiles SET character_birth_date = :d WHERE user_id = :u AND character_birth_date IS NULL', ['d' => $date, 'u' => $userId]);
        } else {
            $this->db->execute('INSERT INTO user_profiles (user_id, character_birth_date) VALUES (:u, :d)', ['u' => $userId, 'd' => $date]);
        }
    }

    /**
     * Mention « mis à jour le » dans les notes des papiers officiels (numéro, autorité et dates inchangés). Les papiers de couverture,
     * falsifiés ou générés ne sont pas touchés.
     *
     * @param list<array<string, mixed>> $documents
     * @param list<string> $labels
     */
    private function noteDocuments(int $tenantId, array $documents, array $labels): int
    {
        $n = 0;
        $line = 'Identité mise à jour le ' . gmdate('Y-m-d') . ' d’après l’état civil du membre (' . mb_strtolower(implode(', ', $labels)) . ').';
        foreach ($documents as $d) {
            $row = $this->db->fetchOne('SELECT notes FROM sse_id_documents WHERE id = :id AND tenant_id = :t', ['id' => $d['id'], 't' => $tenantId]);
            if ($row === null) {
                continue;
            }
            $notes = trim((string) ($row['notes'] ?? ''));
            $notes = mb_substr($notes === '' ? $line : $notes . "\n" . $line, 0, 4000);
            $n += $this->db->execute('UPDATE sse_id_documents SET notes = :n, updated_at = :now WHERE id = :id AND tenant_id = :t', ['n' => $notes, 'now' => gmdate('Y-m-d H:i:s'), 'id' => $d['id'], 't' => $tenantId]) > 0 ? 1 : 0;
        }

        return $n;
    }

    /** @param array<string, mixed> $details */
    private function event(int $tenantId, int $identityId, string $summary, array $details, ?int $actorId, string $via): void
    {
        if (!SseIdentityEventRepository::ready()) {
            return;
        }
        try {
            [$sessionUser, $label] = SseIdentityEventRepository::currentActor();
            (new SseIdentityEventRepository($this->db))->add(
                $tenantId, $identityId, 'identity.civil_aligned', $summary, $details + ['via' => $via],
                $actorId ?? $sessionUser, $via === 'cli' ? 'Système (outil)' : ($via === 'auto' ? 'Changement d’état civil' : $label), 'base', $via === 'cli' ? 'system' : 'web'
            );
        } catch (\Throwable $e) {
            error_log('Identité officielle : trace non écrite : ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $extra */
    private function log(int $tenantId, ?int $actorId, string $action, int $identityId, int $memberId, array $extra = []): void
    {
        ($this->audit)([
            'tenant_id' => $tenantId, 'actor_user_id' => $actorId, 'actor_label' => 'Identité officielle', 'action' => $action,
            'object_type' => 'sse_identity', 'object_id' => $identityId, 'after' => ['member_user_id' => $memberId] + $extra,
        ]);
    }

    // ------------------------------------------------------------------ Nom volontairement différent

    /**
     * Pose ou retire « nom volontairement différent » sur l'identité officielle du membre.
     *
     * @return array{ok: bool, message: string}
     */
    public function setOverride(int $tenantId, int $userId, int $identityId, bool $on, ?int $actorId): array
    {
        if (!self::capabilities()['override']) {
            return ['ok' => false, 'message' => 'Ce réglage sera disponible après l’étape 4690 (identité officielle) de la mise à jour de la base.'];
        }
        $identity = null;
        foreach ($this->civilIdentities($tenantId, $userId) as $i) {
            if ((int) $i['id'] === $identityId) {
                $identity = $i;
            }
        }
        if ($identity === null || $this->member($tenantId, $userId) === null) {
            return ['ok' => false, 'message' => 'Identité introuvable pour ce membre.'];
        }
        $this->db->execute('UPDATE sse_identities SET name_override = :o, updated_at = :now WHERE id = :id AND tenant_id = :t', ['o' => $on ? 1 : 0, 'now' => gmdate('Y-m-d H:i:s'), 'id' => $identityId, 't' => $tenantId]);
        $this->event($tenantId, $identityId, $on ? 'Nom volontairement différent de l’état civil : l’identité n’est plus alignée automatiquement.' : 'Nom aligné de nouveau sur l’état civil du membre à chaque changement.', ['override' => $on], $actorId, 'manual');
        $this->log($tenantId, $actorId, $on ? 'id_member.civil_override_on' : 'id_member.civil_override_off', $identityId, $userId);

        return ['ok' => true, 'message' => $on ? 'Nom marqué comme volontairement différent : l’identité ne suit plus l’état civil.' : 'Réglage retiré : l’identité suivra de nouveau l’état civil.'];
    }

    /**
     * Identités marquées « nom volontairement différent », par identifiant.
     *
     * @param list<int> $identityIds
     * @return array<int, bool>
     */
    public function overrides(int $tenantId, array $identityIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $identityIds), static fn (int $i): bool => $i > 0)));
        if ($ids === [] || !self::capabilities()['override']) {
            return [];
        }
        $out = [];
        foreach (array_chunk($ids, 200) as $chunk) {
            $rows = $this->db->fetchAll('SELECT id FROM sse_identities WHERE tenant_id = ? AND name_override = 1 AND id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')', array_merge([$tenantId], $chunk));
            foreach ($rows as $r) {
                $out[(int) $r['id']] = true;
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ Changement d'état civil

    /**
     * Appelée après un enregistrement du prénom ou du nom d'un membre (UserProfileRepository::upsert) : met à jour son identité
     * officielle, sans jamais bloquer ni faire échouer l'enregistrement. Ne crée pas d'identité (AUTO_CREATE) et ne fait rien
     * dans une communauté sans registre SSE.
     */
    public static function onProfileChanged(int $userId): void
    {
        try {
            $pdo = Database::getPdo();
            if ($userId < 1 || !(new SchemaInspector($pdo))->tableExists('sse_identities')) {
                return;
            }
            $db = Database::getInstance();
            $tenantId = (int) ($db->fetchOne('SELECT tenant_id FROM users WHERE id = :u', ['u' => $userId])['tenant_id'] ?? 0);
            if ($tenantId < 1) {
                return;
            }
            $session = 0;
            try {
                $session = (int) \App\Core\Session::get('user_id');
            } catch (\Throwable) {
                $session = 0;
            }
            (new self($db))->align($tenantId, $userId, $session > 0 ? $session : null, 'auto', null, self::AUTO_CREATE);
        } catch (\Throwable $e) {
            error_log('Identité officielle : mise à jour automatique impossible : ' . $e->getMessage());
        }
    }
}
