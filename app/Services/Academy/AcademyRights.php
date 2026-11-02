<?php

declare(strict_types=1);

namespace App\Services\Academy;

use App\Authorization\PermissionImplication;
use App\Core\Container;
use App\Core\Gate;
use App\Repositories\AcademyAccessRepository;
use App\Repositories\AcademyCourseShareRepository;

/**
 * Qui peut faire quoi dans l'Academy. Mêmes fondations que le reste du site : les permissions Formation (Gate) donnent
 * « l'encadrement de la formation » ; la communauté peut ensuite, droit par droit, ouvrir ou restreindre l'accès à des
 * rôles d'accès, fonctions, unités (sous-unités comprises) ou membres précis.
 *
 * Valeurs par défaut = comportement d'origine : un droit jamais réglé revient à l'encadrement de la formation, un cours
 * sans réglage est visible par toute la communauté, un brouillon n'est pas privé. Les administrateurs de la communauté
 * ont toujours tous les droits. Le partage d'un cours (lecteur, éditeur, instructeur) s'ajoute à ces droits.
 */
final class AcademyRights
{
    /** Droits réglables de la communauté, dans l'ordre de l'écran « Droits d'accès ». */
    public const CAPABILITIES = ['create', 'edit', 'publish', 'assign', 'validate', 'certify', 'stats'];
    /** Bénéficiaires possibles d'un droit ou du public d'un cours. */
    public const SUBJECTS = ['staff', 'role', 'job', 'unit', 'user'];
    /** Ligne posée à chaque enregistrement : le droit est réglé (sans elle, réglage par défaut). */
    public const MARKER = 'admins';
    /** Permissions qui font partie de l'encadrement de la formation (alignées sur TrainingLmsStaffAccess). */
    public const STAFF_PERMISSIONS = ['admin.organization', 'admin.access', 'training.manage', 'training.assign', 'training.create', 'training.update',
        'training.delete', 'training.publish', 'training.publications.manage'];

    /** @var array<string, list<array{type:string,id:int}>>|null */
    private ?array $rules = null;
    /** @var array{roles:list<int>, jobs:list<int>, units:list<int>}|null */
    private ?array $memberships;
    /** @var array<string, array{owner_id:?int, audience:string, private_draft:bool}>|null */
    private ?array $settings = null;
    /** @var array<string, list<array{type:string,id:int}>>|null */
    private ?array $audiences = null;
    /** @var array<string,string>|null */
    private ?array $shares = null;
    /** @var array<string, ?string> */
    private array $linkRoles = [];

    /**
     * @param \Closure(string):bool $allows permission du membre (Gate, ou permissions de ses rôles pour l'aperçu)
     * @param array<string,string> $linkGrants liens de communauté ouverts dans la session : "tenant:cours" => empreinte du jeton
     * @param array{roles:list<int>, jobs:list<int>, units:list<int>}|null $memberships
     */
    public function __construct(
        private readonly int $tenantId,
        private readonly int $userId,
        private readonly \Closure $allows,
        private readonly ?AcademyAccessRepository $access = null,
        private readonly ?AcademyCourseShareRepository $shareRepository = null,
        private readonly array $linkGrants = [],
        ?array $memberships = null,
    ) {
        $this->memberships = $memberships;
    }

    /** Membre connecté : permissions de la session (Gate), partages et liens ouverts dans la session. */
    public static function current(int $tenantId, int $userId, array $linkGrants = []): self
    {
        $gate = Gate::getInstance();
        $shares = Container::get(AcademyCourseShareRepository::class);
        return new self($tenantId, $userId, static fn (string $permission): bool => $gate->allows($permission),
            Container::get(AcademyAccessRepository::class), $shares->ready() ? $shares : null, $linkGrants);
    }

    /** Aperçu « voir comme » : droits calculés à partir des permissions des rôles du membre. */
    public static function forMember(int $tenantId, int $userId, array $permissions, ?AcademyAccessRepository $access, ?AcademyCourseShareRepository $shares): self
    {
        return new self($tenantId, $userId, static fn (string $permission): bool => PermissionImplication::isGranted($permissions, $permission), $access, $shares);
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function isAdmin(): bool
    {
        return ($this->allows)('admin.organization') || ($this->allows)('admin.access');
    }

    /** Encadrement de la formation : toute permission Formation de pilotage. */
    public function isStaff(): bool
    {
        foreach (self::STAFF_PERMISSIONS as $permission) {
            if (($this->allows)($permission)) {
                return true;
            }
        }
        return false;
    }

    /** Régler les droits : administrateurs de la communauté et responsables de la formation. */
    public function canManageRights(): bool
    {
        return $this->isAdmin() || ($this->allows)('training.manage');
    }

    /** @return array<string, list<array{type:string,id:int}>> */
    private function communityRules(): array
    {
        return $this->rules ??= $this->access?->rules($this->tenantId) ?? [];
    }

    public function configured(string $capability): bool
    {
        return isset($this->communityRules()[$capability]);
    }

    /**
     * Bénéficiaires d'un droit (sans la marque « réglé ») ; un droit jamais réglé revient à l'encadrement.
     *
     * @return list<array{type:string,id:int}>
     */
    public function subjects(string $capability): array
    {
        $subjects = $this->communityRules()[$capability] ?? null;
        if ($subjects === null) {
            return [['type' => 'staff', 'id' => 0]];
        }
        return array_values(array_filter($subjects, static fn (array $subject): bool => $subject['type'] !== self::MARKER));
    }

    public function can(string $capability): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        $subjects = $this->communityRules()[$capability] ?? null;
        return $subjects === null ? $this->isStaff() : $this->matches($subjects);
    }

    /** @return array<string,bool> */
    public function all(): array
    {
        $out = [];
        foreach (self::CAPABILITIES as $capability) {
            $out[$capability] = $this->can($capability);
        }
        return $out;
    }

    /** @return array{roles:list<int>, jobs:list<int>, units:list<int>} */
    private function memberships(): array
    {
        return $this->memberships ??= $this->access?->memberships($this->tenantId, $this->userId) ?? ['roles' => [], 'jobs' => [], 'units' => []];
    }

    /** @param list<array{type:string,id:int}> $subjects */
    public function matches(array $subjects): bool
    {
        foreach ($subjects as $subject) {
            $id = (int) $subject['id'];
            $ok = match ($subject['type']) {
                'staff' => $this->isStaff(),
                'role' => in_array($id, $this->memberships()['roles'], true),
                'job' => in_array($id, $this->memberships()['jobs'], true),
                'unit' => in_array($id, $this->memberships()['units'], true),
                'user' => $id > 0 && $id === $this->userId,
                default => false,
            };
            if ($ok) {
                return true;
            }
        }
        return false;
    }

    /** @return array{owner_id:?int, audience:string, private_draft:bool} */
    public function settings(string $key): array
    {
        $this->settings ??= $this->access?->allSettings($this->tenantId) ?? [];
        return $this->settings[$key] ?? AcademyAccessRepository::defaults();
    }

    /** @return list<array{type:string,id:int}> */
    public function audience(string $key): array
    {
        $this->audiences ??= $this->access?->courseAudiences($this->tenantId) ?? [];
        return $this->audiences[$key] ?? [];
    }

    /** @return array<string,string> cours partagés avec le membre => rôle (lecteur, éditeur, instructeur) */
    public function sharedCourses(): array
    {
        return $this->shares ??= $this->shareRepository !== null && $this->userId > 0 ? $this->shareRepository->coursesFor($this->tenantId, $this->userId) : [];
    }

    /** Partage reçu sur ce cours : invitation, ou lien de communauté ouvert dans cette session et toujours valide. */
    public function shareRole(string $key): ?string
    {
        $role = $this->sharedCourses()[$key] ?? null;
        if (!array_key_exists($key, $this->linkRoles)) {
            $linkRole = null;
            $grant = $this->linkGrants[$this->tenantId . ':' . $key] ?? null;
            if (is_string($grant) && $this->shareRepository !== null) {
                $link = $this->shareRepository->link($this->tenantId, $key);
                if ($link !== null && hash_equals($link['token_hash'], $grant) && $link['access'] === 'community'
                    && ($link['expires_at'] === null || strtotime($link['expires_at']) > time())) {
                    $linkRole = $link['role'];
                }
            }
            $this->linkRoles[$key] = $linkRole;
        }
        $linkRole = $this->linkRoles[$key];
        // Un lien « éditeur » complète une invitation « lecteur » ; il ne retire jamais un droit reçu.
        if ($linkRole !== null && ($role === null || $linkRole === 'editor')) {
            $role = $linkRole;
        }
        return $role;
    }

    public function isOwner(string $key): bool
    {
        return $this->userId > 0 && $this->settings($key)['owner_id'] === $this->userId;
    }

    /** @return list<string> cours dont le membre est l'auteur */
    public function ownedKeys(): array
    {
        $this->settings('');
        return array_keys(array_filter($this->settings ?? [], fn (array $s): bool => $this->userId > 0 && $s['owner_id'] === $this->userId));
    }

    /** Brouillon privé d'un autre auteur : invisible, même pour l'encadrement (hors administrateurs et invités). */
    private function privateToOthers(array $course): bool
    {
        $key = (string) ($course['key'] ?? '');
        $settings = $this->settings($key);
        return empty($course['published']) && empty($course['built_in']) && $settings['private_draft']
            && !$this->isOwner($key) && !$this->isAdmin() && $this->shareRole($key) === null;
    }

    public function canEdit(array $course): bool
    {
        $key = (string) ($course['key'] ?? '');
        if (!empty($course['built_in']) || $this->privateToOthers($course)) {
            return false;
        }
        return $this->can('edit') || $this->isOwner($key) || $this->shareRole($key) === 'editor';
    }

    /** Publier, archiver, restaurer, mettre à la corbeille et partager par lien. */
    public function canPublish(array $course): bool
    {
        return empty($course['built_in']) && !$this->privateToOthers($course) && $this->can('publish');
    }

    /** Régler le public du cours, son brouillon privé et les personnes invitées (auteur ou droit « publier »). */
    public function canConfigure(array $course): bool
    {
        if (!empty($course['built_in'])) {
            return $this->can('publish');
        }
        return $this->canPublish($course) || ($this->isOwner((string) ($course['key'] ?? '')) && !$this->privateToOthers($course));
    }

    /** Suivre et valider les stagiaires d'un cours : droit « valider », instructeur invité ou auteur du cours. */
    public function canTeach(string $key): bool
    {
        return $this->can('validate') || $this->shareRole($key) === 'instructor' || $this->isOwner($key);
    }

    /** Aperçu stagiaire (brouillon compris), sans inscription. */
    public function canPreview(array $course): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        if ($this->privateToOthers($course)) {
            return false;
        }
        $key = (string) ($course['key'] ?? '');
        return $this->canEdit($course) || $this->canPublish($course) || $this->canTeach($key) || $this->shareRole($key) !== null
            || $this->can('stats');
    }

    /**
     * Rôle dans le Studio : « owner » (tout gérer), « editor » (modifier le brouillon), « viewer » (aperçu), ou aucun.
     */
    public function studioRole(array $course): ?string
    {
        if (!empty($course['built_in'])) {
            return $this->can('create') || $this->can('edit') || $this->can('publish') ? 'owner' : ($this->canPreview($course) ? 'viewer' : null);
        }
        return $this->canPublish($course) ? 'owner' : ($this->canEdit($course) ? 'editor' : ($this->canPreview($course) ? 'viewer' : null));
    }

    /** Un cours publié dans le catalogue : toute la communauté, ou le public choisi (unités, rôles, fonctions, membres). */
    public function canSee(array $course): bool
    {
        $key = (string) ($course['key'] ?? '');
        if (isset($course['published']) && !$course['published']) {
            return $this->canPreview($course);
        }
        if ($this->settings($key)['audience'] !== 'restricted') {
            return true;
        }
        return $this->isAdmin() || $this->canPublish($course) || $this->canEdit($course) || $this->canTeach($key)
            || $this->shareRole($key) !== null || $this->matches($this->audience($key));
    }

    /**
     * @param list<array<string,mixed>> $courses
     * @return list<array<string,mixed>>
     */
    public function visible(array $courses): array
    {
        return array_values(array_filter($courses, fn (array $course): bool => $this->canSee($course)));
    }

    /** Ouvrir le Studio : créer, modifier, publier, ou un cours partagé / dont on est l'auteur. */
    public function entersStudio(): bool
    {
        return $this->can('create') || $this->can('edit') || $this->can('publish') || $this->sharedCourses() !== [] || $this->ownedKeys() !== [];
    }

    /** Ouvrir l'espace instructeur : au moins un droit Academy, ou un cours où l'on encadre. */
    public function entersInstructor(): bool
    {
        foreach (self::CAPABILITIES as $capability) {
            if ($this->can($capability)) {
                return true;
            }
        }
        return in_array('instructor', $this->sharedCourses(), true) || in_array('editor', $this->sharedCourses(), true) || $this->ownedKeys() !== [];
    }
}
