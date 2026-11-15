<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Session;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseMemberImageGrantRepository;
use App\Services\Effectifs\PersonnelCommandChainService;

/**
 * Images des membres : classification « CONFIDENTIEL ÉLEVÉ » et contrôle d'accès, pour TOUS les chemins de lecture.
 *
 * ## Règle de classification (calculée à partir des liens existants, aucune donnée à migrer)
 *
 * Une image est CONFIDENTIEL ÉLEVÉ quand son propriétaire est un membre de la communauté :
 *  - toute image d'une identité du registre rattachée à un compte (sse_identities.user_id > 0) : portrait ou photo d'identité
 *    officielle, identité de couverture, d'emprunt ou fausse préparée pour ce membre, papiers, signature ;
 *  - tout cliché de la photothèque (sse_file_photos, quelle que soit sa source : upload, atak, seek, person, extraction)
 *    rattaché à une telle identité, ou, pour un cliché SEEK II pas encore rattaché, dont la fiche SEEK II l'est ;
 *  - CAS AMBIGU : une image rattachée à une identité introuvable, d'une autre communauté ou illisible est classée
 *    CONFIDENTIEL ÉLEVÉ sans propriétaire connu (fail-closed).
 * Les images de sujets non membres (civils, cibles du lore, clichés non rattachés sans lien membre) ne changent pas.
 *
 * ## Qui peut lire une image classée (et aucune autre voie)
 *
 *  a. le titulaire (le compte du membre) ;
 *  b. un supérieur hiérarchique direct ou indirect du membre dans l'organigramme (chaîne de commandement) ;
 *  c. une habilitation SSE « Très restreint » ET le droit de lecture explicite de la section « Identités de service des
 *     membres » (SseAccessPolicy, fermée à tous par défaut : ni gestionnaire SSE ni administrateur n'y suffisent) ;
 *  d. une dérogation datée, avec motif, accordée par un supérieur du membre (sse_member_image_grants).
 * Session invitée, compte inconnu, erreur de calcul : refus. Une image sans propriétaire connu (cas ambigu) ne s'ouvre que par (c).
 *
 * Une image refusée n'est ni listée, ni servie, ni comptée dans les détails ; l'octet de l'image ne part jamais vers le client.
 */
final class SseMemberImageGate
{
    public const LABEL = 'CONFIDENTIEL ÉLEVÉ';

    public const WHY_HOLDER = 'holder';
    public const WHY_HIERARCHY = 'hierarchy';
    public const WHY_CLEARANCE = 'clearance';
    public const WHY_DEROGATION = 'derogation';

    /** Classes d'une image. */
    public const CLASS_PUBLIC = 'public';
    public const CLASS_MEMBER = 'member';
    public const CLASS_UNREADABLE = 'unreadable';

    private static ?self $session = null;

    /** @var array<string, list<int>> */
    private array $chains = [];

    /** @var array<string, array{allowed: bool, reason: string}> */
    private array $decisions = [];

    /**
     * @param (\Closure(int, int): list<int>)|null $chain supérieurs d'un membre : (communauté, membre) => comptes, du N+1 au sommet
     * @param (\Closure(int, int): bool)|null $clearance habilitation « Très restreint » et droit « Identités de service » : (communauté, lecteur)
     * @param (\Closure(array<string, mixed>): void)|null $audit écrit une ligne du journal d'audit SSE (lève une exception si impossible)
     * @param (\Closure(int, int, array<string, mixed>): void)|null $notify prévient un membre : (communauté, compte, contenu)
     * @param (\Closure(): string)|null $clock « Y-m-d H:i:s »
     * @param (\Closure(int, string): ?int)|null $seekIdentity identité d'une fiche SEEK II : (communauté, REC)
     * @param (\Closure(): ?int)|null $viewer compte qui regarde (défaut : la session, jamais un invité)
     */
    public function __construct(
        private ?SseIdentityRepository $identities = null,
        private ?SseMemberImageGrantRepository $grants = null,
        private ?\Closure $chain = null,
        private ?\Closure $clearance = null,
        private ?\Closure $audit = null,
        private ?\Closure $notify = null,
        private ?\Closure $clock = null,
        private ?\Closure $seekIdentity = null,
        private ?\Closure $viewer = null,
    ) {
    }

    /** Instance partagée de la requête. */
    public static function forSession(): self
    {
        return self::$session ??= new self();
    }

    /** Remplace (tests) ou efface l'instance partagée. */
    public static function useForSession(?self $gate): void
    {
        self::$session = $gate;
    }

    private function identities(): SseIdentityRepository
    {
        return $this->identities ??= new SseIdentityRepository();
    }

    private function grants(): SseMemberImageGrantRepository
    {
        return $this->grants ??= new SseMemberImageGrantRepository();
    }

    private function now(): string
    {
        return $this->clock !== null ? (string) ($this->clock)() : gmdate('Y-m-d H:i:s');
    }

    /** Compte qui regarde ; null pour un invité (session par code) ou sans compte. */
    public function viewerId(): ?int
    {
        if ($this->viewer !== null) {
            $id = ($this->viewer)();

            return $id !== null && $id > 0 ? $id : null;
        }
        try {
            if ((new SseAccessCodeService())->isGuest()) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }
        $id = (int) Session::get('user_id');

        return $id > 0 ? $id : null;
    }

    // ------------------------------------------------------------------ Classification

    /**
     * Classe d'une identité du registre.
     *
     * @param array<string, mixed>|int|null $identity ligne (hydratée) ou identifiant ; null : aucune identité
     * @return array{class: string, owner: ?int, identity_id: ?int}
     */
    public function identityState(int $tenantId, array|int|null $identity): array
    {
        if ($identity === null) {
            return ['class' => self::CLASS_PUBLIC, 'owner' => null, 'identity_id' => null];
        }
        try {
            $id = is_array($identity) ? (int) ($identity['id'] ?? 0) : $identity;
            if ($tenantId < 1 || $id < 1) {
                return ['class' => self::CLASS_UNREADABLE, 'owner' => null, 'identity_id' => $id > 0 ? $id : null];
            }
            // Une ligne déjà lue sert telle quelle si elle est de cette communauté ; sinon on relit (jamais d'identité étrangère).
            $row = is_array($identity) && array_key_exists('user_id', $identity) && (int) ($identity['tenant_id'] ?? $tenantId) === $tenantId
                ? $identity
                : $this->identities()->find($id, $tenantId);
            if ($row === null) {
                return ['class' => self::CLASS_UNREADABLE, 'owner' => null, 'identity_id' => $id];
            }
            $owner = (int) ($row['user_id'] ?? 0);

            return $owner > 0
                ? ['class' => self::CLASS_MEMBER, 'owner' => $owner, 'identity_id' => $id]
                : ['class' => self::CLASS_PUBLIC, 'owner' => null, 'identity_id' => $id];
        } catch (\Throwable $e) {
            error_log('Images des membres : identité illisible, image masquée : ' . $e->getMessage());

            return ['class' => self::CLASS_UNREADABLE, 'owner' => null, 'identity_id' => null];
        }
    }

    /**
     * Classe d'un cliché de la photothèque (ligne sse_file_photos hydratée : identity_id, source, source_ref).
     *
     * @param array<string, mixed> $photo
     * @return array{class: string, owner: ?int, identity_id: ?int}
     */
    public function photoState(int $tenantId, array $photo): array
    {
        $identityId = ($photo['identity_id'] ?? null) !== null ? (int) $photo['identity_id'] : 0;
        if ($identityId > 0) {
            return $this->identityState($tenantId, $identityId);
        }
        // Cliché SEEK II arrivé avant sa fiche rattachée : le lien se lit sur la fiche SEEK II (REC).
        if (($photo['source'] ?? '') === 'seek' && trim((string) ($photo['source_ref'] ?? '')) !== '') {
            try {
                $viaSeek = $this->seekIdentityOf($tenantId, (string) $photo['source_ref']);
            } catch (\Throwable $e) {
                error_log('Images des membres : fiche SEEK II illisible, image masquée : ' . $e->getMessage());

                return ['class' => self::CLASS_UNREADABLE, 'owner' => null, 'identity_id' => null];
            }
            if ($viaSeek !== null) {
                return $this->identityState($tenantId, $viaSeek);
            }
        }

        return ['class' => self::CLASS_PUBLIC, 'owner' => null, 'identity_id' => null];
    }

    private function seekIdentityOf(int $tenantId, string $rec): ?int
    {
        if ($this->seekIdentity !== null) {
            return ($this->seekIdentity)($tenantId, $rec);
        }
        $row = \App\Core\Database::getInstance()->fetchOne(
            'SELECT identity_id FROM sse_seek_records WHERE tenant_id = :t AND rec = :r AND identity_id IS NOT NULL LIMIT 1',
            ['t' => $tenantId, 'r' => $rec]
        );
        $id = $row !== null ? (int) $row['identity_id'] : 0;

        return $id > 0 ? $id : null;
    }

    /** @param array{class: string, owner: ?int, identity_id: ?int} $state */
    public function isConfidential(array $state): bool
    {
        return $state['class'] !== self::CLASS_PUBLIC;
    }

    /**
     * Chemin disque d'une ancienne image rangée sous public/uploads (envoi de membre, ATAK), pour la servir par la route protégée quand
     * elle est CONFIDENTIEL ÉLEVÉ (jamais depuis SseSeekPhotoService, qui ne touche pas au dossier public). Même exigence de nom qu'à l'envoi ; null hors de ce dossier ou si le fichier manque.
     *
     * @param array<string, mixed> $photo
     */
    public static function publicAbsolutePath(array $photo, ?string $publicDir = null): ?string
    {
        $path = (string) ($photo['path'] ?? '');
        if (preg_match('#^uploads/[a-z0-9_-]{1,40}/[0-9]{1,10}_[a-f0-9]{16}\.(jpg|png|webp)$#', $path) !== 1) {
            return null;
        }
        $root = realpath(($publicDir ?? base_path('public')) . DIRECTORY_SEPARATOR . 'uploads');
        $abs = $root === false ? false : realpath($root . DIRECTORY_SEPARATOR . substr($path, strlen('uploads/')));
        if ($root === false || $abs === false || !is_file($abs) || !str_starts_with($abs, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $abs;
    }

    // ------------------------------------------------------------------ Décision

    /**
     * Le lecteur peut-il voir l'image ? (null : lecteur de la session.) Jamais d'exception : erreur de calcul = refus.
     *
     * @param array{class: string, owner: ?int, identity_id: ?int} $state
     * @return array{allowed: bool, reason: string, confidential: bool}
     */
    public function decide(int $tenantId, array $state, ?int $viewerId = null): array
    {
        if ($state['class'] === self::CLASS_PUBLIC) {
            return ['allowed' => true, 'reason' => '', 'confidential' => false];
        }
        try {
            $viewer = $viewerId ?? $this->viewerId();
            $key = $tenantId . ':' . (int) $viewer . ':' . (int) $state['owner'] . ':' . $state['class'];
            if (!isset($this->decisions[$key])) {
                $this->decisions[$key] = $this->compute($tenantId, (int) $viewer, $state['owner']);
            }

            return $this->decisions[$key] + ['confidential' => true];
        } catch (\Throwable $e) {
            error_log('Images des membres : décision impossible, image masquée : ' . $e->getMessage());

            return ['allowed' => false, 'reason' => '', 'confidential' => true];
        }
    }

    /** @return array{allowed: bool, reason: string} */
    private function compute(int $tenantId, int $viewer, ?int $owner): array
    {
        $deny = ['allowed' => false, 'reason' => ''];
        if ($tenantId < 1 || $viewer < 1) {
            return $deny;
        }
        if ($owner !== null && $owner > 0) {
            if ($viewer === $owner) {
                return ['allowed' => true, 'reason' => self::WHY_HOLDER];
            }
            $chain = $this->chainOf($tenantId, $owner);
            if (in_array($viewer, $chain, true)) {
                return ['allowed' => true, 'reason' => self::WHY_HIERARCHY];
            }
            $grant = $this->grants()->activeGrant($tenantId, $owner, $viewer, $this->now());
            if ($grant !== null && in_array((int) ($grant['decided_by_user_id'] ?? 0), $chain, true)) {
                return ['allowed' => true, 'reason' => self::WHY_DEROGATION];
            }
        }
        if ($this->clearanceAllows($tenantId, $viewer)) {
            return ['allowed' => true, 'reason' => self::WHY_CLEARANCE];
        }

        return $deny;
    }

    /** @return list<int> */
    public function chainOf(int $tenantId, int $owner): array
    {
        $key = $tenantId . ':' . $owner;
        if (!isset($this->chains[$key])) {
            $this->chains[$key] = $this->chain !== null
                ? array_values(array_map('intval', ($this->chain)($tenantId, $owner)))
                : (new PersonnelCommandChainService())->chainIdsForUser($tenantId, $owner);
        }

        return $this->chains[$key];
    }

    private function clearanceAllows(int $tenantId, int $viewer): bool
    {
        if ($this->clearance !== null) {
            return (bool) ($this->clearance)($tenantId, $viewer);
        }
        // Le plafond d'habilitation et la règle d'accès sont ceux de la session : un autre compte ne se juge pas ici.
        if ($viewer !== (int) Session::get('user_id') || $tenantId !== (int) Session::get('tenant_id')) {
            return false;
        }

        return (new SseClearanceService())->allows(SseCaseRepository::CLASS_RESTRICTED)
            && SseAccessPolicy::forSession()->decide($viewer, 'identites_service', 'read', ['tenant_id' => $tenantId, 'guest' => false])->allowed;
    }

    /**
     * Raccourci des écrans : l'état de l'identité et la décision pour le lecteur de la session.
     *
     * @param array<string, mixed>|int|null $identity
     * @return array{allowed: bool, reason: string, confidential: bool, owner: ?int, state: array{class: string, owner: ?int, identity_id: ?int}}
     */
    public function forIdentity(int $tenantId, array|int|null $identity): array
    {
        $state = $this->identityState($tenantId, $identity);

        return $this->decide($tenantId, $state) + ['owner' => $state['owner'], 'state' => $state];
    }

    /**
     * Images d'un membre désigné par son compte (couvertures exportées, papiers générés pour lui) : toujours CONFIDENTIEL ÉLEVÉ.
     *
     * @return array{allowed: bool, reason: string, confidential: bool, owner: ?int, state: array{class: string, owner: ?int, identity_id: ?int}}
     */
    public function forMember(int $tenantId, int $ownerUserId): array
    {
        $state = $ownerUserId > 0
            ? ['class' => self::CLASS_MEMBER, 'owner' => $ownerUserId, 'identity_id' => null]
            : ['class' => self::CLASS_UNREADABLE, 'owner' => null, 'identity_id' => null];

        return $this->decide($tenantId, $state) + ['owner' => $state['owner'], 'state' => $state];
    }

    /**
     * @param array<string, mixed> $photo
     * @return array{allowed: bool, reason: string, confidential: bool, owner: ?int, state: array{class: string, owner: ?int, identity_id: ?int}}
     */
    public function forPhoto(int $tenantId, array $photo): array
    {
        $state = $this->photoState($tenantId, $photo);

        return $this->decide($tenantId, $state) + ['owner' => $state['owner'], 'state' => $state];
    }

    // ------------------------------------------------------------------ Masquage des données d'écran

    /** Chemin du portail (sans la base du site) de l'image d'une identité : jamais un chemin sous public/. Les écrans le passent à url(). */
    public static function identityImagePath(int $identityId, string $kind = 'photo'): string
    {
        return 'atak/sse/fichiers/identites/' . $identityId . '/' . ($kind === 'signature' ? 'signature' : 'photo');
    }

    /** Adresse complète protégée de l'image d'une identité. */
    public static function identityImageUrl(int $identityId, string $kind = 'photo'): string
    {
        $path = self::identityImagePath($identityId, $kind);

        return function_exists('url') ? (string) url($path) : $path;
    }

    /**
     * Identité destinée à un écran : pour une identité de membre, l'image n'est jamais l'adresse publique. Refusée : photo_path et
     * signature_path sont vidés et « image_lock » décrit le cadre à afficher. Autorisée : l'adresse protégée (consultation journalisée).
     * Une identité non membre est rendue telle quelle.
     *
     * @param array<string, mixed> $identity
     * @return array<string, mixed>
     */
    public function maskIdentity(int $tenantId, array $identity): array
    {
        $d = $this->forIdentity($tenantId, $identity);
        if (!$d['confidential']) {
            return $identity;
        }
        $hadPhoto = ($identity['photo_path'] ?? null) !== null && $identity['photo_path'] !== '';
        $hadSign = ($identity['signature_path'] ?? null) !== null && $identity['signature_path'] !== '';
        if ($d['allowed'] && $d['reason'] === self::WHY_HOLDER) {
            // Le titulaire voit ses propres images telles quelles (sa fiche n'est pas derrière le portail SSE).
            return $identity;
        }
        if ($d['allowed']) {
            if ($hadPhoto) {
                $identity['photo_path'] = self::identityImagePath((int) $identity['id'], 'photo');
            }
            if ($hadSign) {
                $identity['signature_path'] = self::identityImagePath((int) $identity['id'], 'signature');
            }
            $identity['image_banner'] = $d['reason'];

            return $identity;
        }
        $identity['photo_path'] = null;
        $identity['signature_path'] = null;
        if (array_key_exists('photo_url', $identity)) {
            $identity['photo_url'] = null;
        }
        if (array_key_exists('signature_url', $identity)) {
            $identity['signature_url'] = null;
        }
        $identity['image_lock'] = $this->lockInfo($tenantId, $d['owner'], $hadPhoto || $hadSign);

        return $identity;
    }

    /**
     * Clichés d'un écran : ceux que le lecteur ne peut pas voir sont retirés (ni adresse, ni chemin) et comptés ; ceux qu'il peut voir
     * passent par la route protégée quand ils sont CONFIDENTIEL ÉLEVÉ.
     *
     * @param list<array<string, mixed>> $photos lignes hydratées de sse_file_photos
     * @return array{photos: list<array<string, mixed>>, hidden: int, lock: ?array<string, mixed>, banner: ?string}
     */
    public function filterPhotos(int $tenantId, array $photos): array
    {
        $kept = [];
        $hidden = 0;
        $owner = null;
        $banner = null;
        foreach ($photos as $ph) {
            $d = $this->forPhoto($tenantId, $ph);
            if ($d['allowed']) {
                if ($d['confidential']) {
                    $banner = $d['reason'];
                }
                $kept[] = $ph + ['confidential' => $d['confidential']];
            } else {
                $hidden++;
                $owner ??= $d['owner'];
            }
        }

        return ['photos' => $kept, 'hidden' => $hidden, 'lock' => $hidden > 0 ? $this->lockInfo($tenantId, $owner, true) : null, 'banner' => $banner];
    }

    /**
     * Ce que le cadre « CONFIDENTIEL ÉLEVÉ » a besoin de savoir.
     *
     * @return array{label: string, owner: ?int, can_request: bool, pending: bool}
     */
    public function lockInfo(int $tenantId, ?int $owner, bool $hasImage = true): array
    {
        $viewer = $this->viewerId();
        $pending = false;
        if ($viewer !== null && $owner !== null && $owner > 0) {
            try {
                $pending = $this->grants()->pending($tenantId, $owner, $viewer) !== null;
            } catch (\Throwable) {
                $pending = false;
            }
        }

        return [
            'label' => self::LABEL,
            'owner' => $owner,
            'can_request' => $viewer !== null && $owner !== null && $owner > 0 && $owner !== $viewer,
            'pending' => $pending,
            'has_image' => $hasImage,
        ];
    }

    // ------------------------------------------------------------------ Journal d'audit

    /**
     * Journalise la consultation d'une image CONFIDENTIEL ÉLEVÉ servie (qui, quand, quelle image, pourquoi l'accès a été ouvert).
     * Lève une exception si le journal est impossible : l'appelant refuse alors de servir l'image (pas d'accès sans trace).
     */
    public function recordConsultation(int $tenantId, int $viewer, string $imageType, int $imageId, ?int $owner, string $reason): void
    {
        $this->write($tenantId, $viewer, 'member_image.view', $imageType, $imageId, $reason, ['owner_user_id' => $owner, 'access' => $reason]);
    }

    /** Tentative refusée sur une image classée (ne bloque jamais la réponse). */
    public function recordDenied(int $tenantId, ?int $viewer, string $imageType, int $imageId, ?int $owner): void
    {
        try {
            $this->write($tenantId, (int) $viewer, 'member_image.denied', $imageType, $imageId, 'denied', ['owner_user_id' => $owner]);
        } catch (\Throwable $e) {
            error_log('Images des membres : refus non journalisé : ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $after */
    private function write(int $tenantId, int $viewer, string $action, string $objectType, int $objectId, string $reason, array $after): void
    {
        $label = trim((string) (Session::get('callsign') ?? Session::get('display_name') ?? ''));
        $row = [
            'tenant_id' => $tenantId,
            'actor_user_id' => $viewer > 0 ? $viewer : null,
            'actor_label' => $label !== '' ? $label : ($viewer > 0 ? 'Compte ' . $viewer : 'Anonyme'),
            'action' => $action,
            'object_type' => $objectType,
            'object_id' => $objectId,
            'reason' => $reason,
            'after' => $after + ['at' => $this->now()],
        ];
        if ($this->audit !== null) {
            ($this->audit)($row);

            return;
        }
        (new SseAuditLogRepository())->write($row);
    }

    // ------------------------------------------------------------------ Demande d'accès et dérogation

    /**
     * « Demander l'accès » : demande tracée (table des demandes, journal d'audit) adressée à la chaîne hiérarchique du membre
     * (centre de notifications). Sans table des demandes (étape 4660 pas lancée), la demande est seulement journalisée et notifiée.
     *
     * @return array{ok: bool, message: string}
     */
    public function requestAccess(int $tenantId, int $viewer, int $owner, string $reason): array
    {
        $reason = trim($reason);
        if ($viewer < 1 || $owner < 1 || $tenantId < 1 || $viewer === $owner) {
            return ['ok' => false, 'message' => 'Demande impossible.'];
        }
        if (mb_strlen($reason) < 5) {
            return ['ok' => false, 'message' => 'Indiquez le motif de votre demande (5 caractères au moins).'];
        }
        $chain = $this->chainOf($tenantId, $owner);
        if ($chain === []) {
            return ['ok' => false, 'message' => 'Ce membre n’a pas de supérieur désigné dans l’organigramme : demandez à l’administration de la communauté.'];
        }
        if ($this->grants()->pending($tenantId, $owner, $viewer) !== null) {
            return ['ok' => true, 'message' => 'Votre demande est déjà en attente auprès de la hiérarchie.'];
        }
        $id = $this->grants()->addRequest($tenantId, $owner, $viewer, $reason, $this->now());
        $this->write($tenantId, $viewer, 'member_image.request', 'member', $owner, mb_substr($reason, 0, 500), ['owner_user_id' => $owner, 'request_id' => $id, 'recorded' => $id !== null]);
        foreach (array_slice($chain, 0, 5) as $to) {
            $this->tell($tenantId, $to, [
                'requester_id' => $viewer,
                'owner_id' => $owner,
                'path' => 'atak/sse/fichiers/acces-images',
                'reason' => mb_substr($reason, 0, 240),
            ]);
        }

        return ['ok' => true, 'message' => 'Demande envoyée à la hiérarchie du membre.'];
    }

    /**
     * Décision d'un supérieur sur une demande : dérogation datée avec motif (fin obligatoire, 30 jours au plus) ou refus.
     *
     * @return array{ok: bool, message: string}
     */
    public function decideRequest(int $tenantId, int $decider, int $requestId, bool $approve, string $reason, ?string $endsAt): array
    {
        $req = $this->grants()->find($tenantId, $requestId);
        if ($req === null || $req['status'] !== SseMemberImageGrantRepository::PENDING) {
            return ['ok' => false, 'message' => 'Demande introuvable ou déjà traitée.'];
        }
        $owner = (int) $req['owner_user_id'];
        if (!in_array($decider, $this->chainOf($tenantId, $owner), true)) {
            return ['ok' => false, 'message' => 'Seul un supérieur hiérarchique du membre peut décider.'];
        }
        $reason = trim($reason);
        $now = $this->now();
        if ($approve) {
            if (mb_strlen($reason) < 5) {
                return ['ok' => false, 'message' => 'Indiquez le motif de la dérogation (5 caractères au moins).'];
            }
            $end = $endsAt !== null ? strtotime($endsAt . ' UTC') : false;
            $max = strtotime($now . ' UTC') + 30 * 86400;
            if ($end === false || $end <= strtotime($now . ' UTC') || $end > $max) {
                return ['ok' => false, 'message' => 'Choisissez une date de fin dans les 30 prochains jours.'];
            }
            $ok = $this->grants()->decide($tenantId, $requestId, SseMemberImageGrantRepository::GRANTED, $decider, $reason, $now, gmdate('Y-m-d H:i:s', $end), $now);
        } else {
            $ok = $this->grants()->decide($tenantId, $requestId, SseMemberImageGrantRepository::DENIED, $decider, $reason !== '' ? $reason : 'Refusé', null, null, $now);
        }
        if (!$ok) {
            return ['ok' => false, 'message' => 'Décision non enregistrée.'];
        }
        $this->write($tenantId, $decider, $approve ? 'member_image.grant' : 'member_image.deny', 'member', $owner, $reason, ['request_id' => $requestId, 'grantee_user_id' => (int) $req['grantee_user_id'], 'ends_at' => $endsAt]);
        $this->decisions = [];

        return ['ok' => true, 'message' => $approve ? 'Dérogation accordée.' : 'Demande refusée.'];
    }

    /**
     * Demandes en attente que le lecteur peut décider (celles des membres dont il est un supérieur).
     *
     * @return list<array<string, mixed>>
     */
    public function decidableRequests(int $tenantId, int $viewer): array
    {
        $out = [];
        foreach ($this->grants()->pendingForTenant($tenantId) as $r) {
            try {
                if (in_array($viewer, $this->chainOf($tenantId, (int) $r['owner_user_id']), true)) {
                    $out[] = $r;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $payload */
    private function tell(int $tenantId, int $userId, array $payload): void
    {
        try {
            if ($this->notify !== null) {
                ($this->notify)($tenantId, $userId, $payload);

                return;
            }
            (new \App\Repositories\MemberNotificationRepository())->create($tenantId, $userId, 'sse_member_image_request', $payload);
        } catch (\Throwable $e) {
            error_log('Images des membres : notification non envoyée : ' . $e->getMessage());
        }
    }
}
