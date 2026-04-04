<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Repositories\DocumentCollaboratorRepository;
use App\Repositories\DocumentPermissionRepository;
use App\Repositories\UserRepository;

/**
 * Règle d'autorisation centralisée pour les documents.
 * Ordre de décision : owner → collaborateur → classification → permission explicite → visibilité → statut.
 * (Pas de bypass "admin global" : même les admins passent par les règles métier.)
 */
class DocumentAccessService
{
    /** Niveaux de classification du plus bas au plus élevé (indice = force). */
    private const CLASSIFICATION_ORDER = ['public', 'interne', 'restreint', 'sensible', 'confidentiel', 'operationnel'];

    /** Plafond de classification par rôle (slug => niveau max autorisé). À étendre selon les rôles du tenant. */
    private const ROLE_CLASSIFICATION_MAX = [
        'tenant_admin' => 'operationnel',
        'officer' => 'confidentiel',
        'forum_moderator' => 'sensible',
        'member' => 'interne',
        'instructeur' => 'sensible',
        'commandement' => 'confidentiel',
        'opérateur' => 'interne',
    ];

    public function __construct(
        private DocumentCollaboratorRepository $collaboratorRepository,
        private DocumentPermissionRepository $permissionRepository,
        private UserRepository $userRepository
    ) {
    }

    /**
     * @param array<string, mixed> $document Ligne document (doit contenir au moins tenant_id, owner_user_id, classification_level, visibility_scope, status, unit_id)
     */
    public function canRead(array $document, int $userId, int $tenantId): bool
    {
        return $this->resolveAccess($document, $userId, $tenantId, 'read');
    }

    /** @param array<string, mixed> $document */
    public function canEdit(array $document, int $userId, int $tenantId): bool
    {
        return $this->resolveAccess($document, $userId, $tenantId, 'edit');
    }

    /** @param array<string, mixed> $document */
    public function canApprove(array $document, int $userId, int $tenantId): bool
    {
        return $this->resolveAccess($document, $userId, $tenantId, 'approve');
    }

    /** @param array<string, mixed> $document */
    public function canManage(array $document, int $userId, int $tenantId): bool
    {
        return $this->resolveAccess($document, $userId, $tenantId, 'manage');
    }

    /**
     * @param array<string, mixed> $document
     * @param 'read'|'comment'|'edit'|'approve'|'manage' $requiredLevel
     */
    private function resolveAccess(array $document, int $userId, int $tenantId, string $requiredLevel): bool
    {
        $docId = (int) ($document['id'] ?? 0);
        $ownerId = isset($document['owner_user_id']) ? (int) $document['owner_user_id'] : null;
        $status = $document['status'] ?? 'draft';
        $visibility = $document['visibility_scope'] ?? 'private';
        $docUnitId = isset($document['unit_id']) ? (int) $document['unit_id'] : null;
        $classificationLevel = $document['classification_level'] ?? 'interne';

        // 2. Owner
        if ($ownerId !== null && $ownerId === $userId) {
            if ($this->classificationAllows($userId, $classificationLevel)) {
                return true;
            }
        }

        // 3. Collaborateur avec rôle adapté
        $collaborators = $this->collaboratorRepository->getByDocument($docId);
        foreach ($collaborators as $c) {
            if ((int) $c['user_id'] === $userId) {
                $role = $c['role'] ?? 'reader';
                if ($this->roleGrantsAtLeast($role, $requiredLevel) && $this->classificationAllows($userId, $classificationLevel)) {
                    return true;
                }
            }
        }

        // 4. Niveau de classification (plafond utilisateur)
        if (!$this->classificationAllows($userId, $classificationLevel)) {
            return false;
        }

        // 5. Permission explicite (document_permissions)
        $permissions = $this->permissionRepository->getByDocument($docId);
        $userRoleSlug = $this->userRepository->getRoleSlugForUser($userId);
        $userUnitIds = $this->userRepository->getUnitIdsForUser($userId);
        foreach ($permissions as $p) {
            $match = false;
            if ($p['permission_type'] === 'user' && (int) $p['permission_value'] === $userId) {
                $match = true;
            } elseif ($p['permission_type'] === 'role' && $userRoleSlug !== null && $p['permission_value'] === $userRoleSlug) {
                $match = true;
            } elseif ($p['permission_type'] === 'unit' && in_array((int) $p['permission_value'], $userUnitIds, true)) {
                $match = true;
            }
            if ($match && $this->accessLevelGrants($p['access_level'] ?? 'read', $requiredLevel)) {
                if ($this->visibilityAndStatusAllow($visibility, $status, $document, $userId, $tenantId, $docUnitId, $userUnitIds, $userRoleSlug, true)) {
                    return true;
                }
            }
        }

        // 6. Visibilité et statut (sans permission explicite)
        return $this->visibilityAndStatusAllow($visibility, $status, $document, $userId, $tenantId, $docUnitId, $userUnitIds, $userRoleSlug, false);
    }

    private function classificationAllows(int $userId, string $documentLevel): bool
    {
        $userRoleSlug = $this->userRepository->getRoleSlugForUser($userId);
        $userMax = self::ROLE_CLASSIFICATION_MAX[$userRoleSlug ?? ''] ?? 'interne';
        $docRank = $this->classificationRank($documentLevel);
        $userRank = $this->classificationRank($userMax);
        return $userRank >= $docRank;
    }

    private function classificationRank(string $level): int
    {
        $pos = array_search(strtolower($level), self::CLASSIFICATION_ORDER, true);
        return $pos !== false ? $pos : 0;
    }

    private function roleGrantsAtLeast(string $collabRole, string $required): bool
    {
        $order = ['reader' => 0, 'reviewer' => 1, 'editor' => 2, 'approver' => 3, 'author' => 4, 'owner' => 5];
        $requiredRank = $order[$required] ?? -1;
        $collabRank = $order[$collabRole] ?? -1;
        if ($required === 'manage') {
            return $collabRole === 'owner';
        }
        return $collabRank >= $requiredRank;
    }

    private function accessLevelGrants(string $granted, string $required): bool
    {
        $order = ['read' => 0, 'comment' => 1, 'edit' => 2, 'approve' => 3, 'manage' => 4];
        return ($order[$granted] ?? -1) >= ($order[$required] ?? -1);
    }

    /**
     * @param array<string, mixed> $document
     */
    private function visibilityAndStatusAllow(
        string $visibility,
        string $status,
        array $document,
        int $userId,
        int $tenantId,
        ?int $docUnitId,
        array $userUnitIds,
        ?string $userRoleSlug,
        bool $hasExplicitPermission
    ): bool {
        // Brouillon / en relecture / à valider : seulement owner et collaborateurs (déjà traités au-dessus). Ici on est dans "visibility/status" pour les autres.
        if (in_array($status, ['draft', 'review', 'approval'], true)) {
            return false;
        }

        // Publié, suspendu, archivé, obsolète : selon visibilité
        switch ($visibility) {
            case 'private':
                return false;
            case 'collaborators':
                return false; // déjà géré par collaborateurs
            case 'unit':
                return $docUnitId !== null && in_array($docUnitId, $userUnitIds, true);
            case 'role':
                // Document peut avoir un champ "min_role" ou on considère tout le tenant avec le bon rôle
                return $userRoleSlug !== null;
            case 'organization':
                return true; // tout le tenant
            case 'controlled':
                return $hasExplicitPermission;
            default:
                return false;
        }
    }

    public static function getClassificationLevels(): array
    {
        return self::CLASSIFICATION_ORDER;
    }

    public static function getRoleClassificationMax(): array
    {
        return self::ROLE_CLASSIFICATION_MAX;
    }
}
