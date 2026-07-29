<?php

declare(strict_types=1);

namespace App\Services\Rbac;

/**
 * Catalogue métier de la matrice « Rôles & permissions » (modules, niveaux d'accès, correspondances).
 */
final class RolePermissionMatrixCatalog
{
    public const MODULE_MEMBERS = 'members';
    public const MODULE_OPERATIONS = 'operations';
    public const MODULE_ATAK = 'atak';
    public const MODULE_FINANCES = 'finances';
    public const MODULE_SYSTEMS = 'systems';

    public const LEVEL_COMPLET = 'complet';
    public const LEVEL_SA_SECTION = 'sa_section';
    public const LEVEL_SON_GROUPE = 'son_groupe';
    public const LEVEL_LECTURE = 'lecture';
    public const LEVEL_SA_FICHE = 'sa_fiche';
    public const LEVEL_INSTRUCTION = 'instruction';
    public const LEVEL_PARTIEL = 'partiel';
    public const LEVEL_NONE = 'none';

    /** @return list<string> */
    public static function moduleKeys(): array
    {
        return [
            self::MODULE_MEMBERS,
            self::MODULE_OPERATIONS,
            self::MODULE_ATAK,
            self::MODULE_FINANCES,
            self::MODULE_SYSTEMS,
        ];
    }

    /** @return array<string, string> */
    public static function moduleLabelsFr(): array
    {
        return [
            self::MODULE_MEMBERS => 'Membres',
            self::MODULE_OPERATIONS => 'Opérations',
            self::MODULE_ATAK => 'ATAK',
            self::MODULE_FINANCES => 'Finances',
            self::MODULE_SYSTEMS => 'Systèmes',
        ];
    }

    /** @return list<string> */
    public static function accessLevelKeys(): array
    {
        return [
            self::LEVEL_COMPLET,
            self::LEVEL_SA_SECTION,
            self::LEVEL_SON_GROUPE,
            self::LEVEL_LECTURE,
            self::LEVEL_SA_FICHE,
            self::LEVEL_INSTRUCTION,
            self::LEVEL_PARTIEL,
            self::LEVEL_NONE,
        ];
    }

    /** @return array<string, string> */
    public static function accessLevelLabelsFr(): array
    {
        return [
            self::LEVEL_COMPLET => 'Complet',
            self::LEVEL_SA_SECTION => 'Sa section',
            self::LEVEL_SON_GROUPE => 'Son groupe',
            self::LEVEL_LECTURE => 'Lecture',
            self::LEVEL_SA_FICHE => 'Sa fiche',
            self::LEVEL_INSTRUCTION => 'Instruction',
            self::LEVEL_PARTIEL => 'Partiel',
            self::LEVEL_NONE => '—',
        ];
    }

    public static function normalizeAccessLevel(?string $raw): string
    {
        $key = strtolower(trim((string) $raw));
        if ($key === '' || $key === '--' || $key === 'aucun') {
            return self::LEVEL_NONE;
        }

        return in_array($key, self::accessLevelKeys(), true) ? $key : self::LEVEL_NONE;
    }

    /**
     * Préfixes de slugs de permissions rattachés à chaque module métier.
     *
     * @return array<string, list<string>>
     */
    public static function modulePermissionPrefixes(): array
    {
        return [
            self::MODULE_MEMBERS => [
                'personnel.',
                'admin.members.',
                'organization.effectifs.',
                'organization.recruitment.',
                'invitations.',
            ],
            self::MODULE_OPERATIONS => [
                'operations.',
                'operational.',
                'intel.',
                'interteam.',
                'cooperation.',
            ],
            self::MODULE_ATAK => [
                'atak.',
                'operations.missions.',
                'operations.aar.',
                'operations.readiness.',
                'operations.medical.',
                'operations.logistics.',
                'operations.comms.',
            ],
            self::MODULE_FINANCES => [
                'finances.',
            ],
            self::MODULE_SYSTEMS => [
                'admin.',
                'dashboard.',
                'comms.settings.',
            ],
        ];
    }

    /**
     * Habilitations accordées par module et niveau d'accès (slugs canoniques).
     *
     * @return list<string>
     */
    public static function permissionSlugsForModuleLevel(string $moduleKey, string $accessLevel): array
    {
        $moduleKey = strtolower(trim($moduleKey));
        $accessLevel = self::normalizeAccessLevel($accessLevel);
        if ($accessLevel === self::LEVEL_NONE) {
            return [];
        }

        $map = [
            self::MODULE_MEMBERS => [
                self::LEVEL_COMPLET => [
                    'personnel.profile.view', 'personnel.profile.update', 'personnel.sensitive.view',
                    'personnel.grades.manage', 'personnel.assignments.manage', 'personnel.status.manage',
                    'personnel.badges.manage', 'personnel.directory.export',
                    'admin.members.view', 'admin.members.manage', 'admin.members.invite', 'admin.members.moderate',
                    'organization.effectifs.hub.view', 'organization.recruitment.manage',
                    'organization.recruitment.openings.manage', 'invitations.send',
                ],
                self::LEVEL_SA_SECTION => [
                    'personnel.profile.view', 'personnel.profile.update', 'personnel.assignments.manage',
                    'organization.effectifs.hub.view', 'admin.members.view',
                ],
                self::LEVEL_SON_GROUPE => [
                    'personnel.profile.view', 'organization.effectifs.hub.view',
                ],
                self::LEVEL_LECTURE => [
                    'personnel.profile.view', 'organization.effectifs.hub.view', 'admin.members.view',
                ],
                self::LEVEL_SA_FICHE => [
                    'personnel.profile.view',
                ],
                self::LEVEL_INSTRUCTION => [
                    'personnel.profile.view', 'training.view', 'training.submissions.grade',
                ],
                self::LEVEL_PARTIEL => [
                    'personnel.profile.view', 'admin.members.view',
                ],
            ],
            self::MODULE_OPERATIONS => [
                self::LEVEL_COMPLET => [
                    'operations.missions.view', 'operations.missions.manage',
                    'operations.sitrep.view', 'operations.sitrep.create',
                    'operations.aar.view', 'operations.aar.export',
                    'operations.readiness.view', 'operations.readiness.manage',
                    'operational.board.view', 'operational.board.edit',
                    'intel.transmission.view', 'intel.transmission.manage', 'intel.transmission.contribute', 'intel.poe.manage',
                    'interteam.missions.manage', 'interteam.missions.respond',
                    'cooperation.missions.view', 'cooperation.missions.create', 'cooperation.missions.manage',
                    'cooperation.missions.respond', 'cooperation.missions.activate', 'cooperation.missions.close',
                ],
                self::LEVEL_SA_SECTION => [
                    'operations.missions.view', 'operations.sitrep.view', 'operations.aar.view',
                    'operational.board.view', 'intel.transmission.view', 'intel.transmission.contribute',
                ],
                self::LEVEL_SON_GROUPE => [
                    'operations.missions.view', 'operations.sitrep.view', 'operational.board.view',
                ],
                self::LEVEL_LECTURE => [
                    'operations.missions.view', 'operations.sitrep.view', 'operations.aar.view', 'operational.board.view',
                ],
                self::LEVEL_SA_FICHE => [],
                self::LEVEL_INSTRUCTION => [
                    'operations.missions.view', 'operational.board.view',
                ],
                self::LEVEL_PARTIEL => [
                    'operations.missions.view', 'operations.sitrep.view',
                ],
            ],
            self::MODULE_ATAK => [
                self::LEVEL_COMPLET => [
                    'atak.terminals.view', 'atak.terminals.manage',
                    'atak.certificates.view', 'atak.certificates.manage',
                    'atak.config.manage', 'atak.mission_cycle.manage', 'atak.aar.manage',
                    'atak.sse.access', 'atak.sse.grant', 'atak.sse.case.manage', 'atak.sse.export',
                    'operations.missions.view', 'operations.missions.manage',
                    'operations.aar.view', 'operations.aar.export',
                ],
                self::LEVEL_SA_SECTION => [
                    'atak.terminals.view', 'atak.certificates.view',
                    'atak.sse.access',
                    'operations.missions.view', 'operations.aar.view',
                ],
                self::LEVEL_SON_GROUPE => [
                    'atak.terminals.view', 'operations.missions.view',
                ],
                self::LEVEL_LECTURE => [
                    'atak.terminals.view', 'atak.certificates.view',
                    'atak.sse.access', 'atak.sse.case.manage', 'atak.sse.export',
                    'operations.missions.view', 'operations.aar.view',
                ],
                self::LEVEL_SA_FICHE => [],
                self::LEVEL_INSTRUCTION => [
                    'atak.terminals.view', 'operations.missions.view',
                ],
                self::LEVEL_PARTIEL => [
                    'atak.terminals.view', 'operations.missions.view',
                ],
            ],
            self::MODULE_FINANCES => [
                self::LEVEL_COMPLET => [
                    'finances.view', 'finances.manage', 'finances.export',
                ],
                self::LEVEL_SA_SECTION => [
                    'finances.view',
                ],
                self::LEVEL_SON_GROUPE => [
                    'finances.view',
                ],
                self::LEVEL_LECTURE => [
                    'finances.view',
                ],
                self::LEVEL_SA_FICHE => [],
                self::LEVEL_INSTRUCTION => [],
                self::LEVEL_PARTIEL => [
                    'finances.view',
                ],
            ],
            self::MODULE_SYSTEMS => [
                self::LEVEL_COMPLET => [
                    'admin.access', 'admin.organization', 'admin.backoffice.view',
                    'admin.roles.manage', 'admin.permissions.manage', 'admin.audit.view',
                    'admin.settings.manage', 'admin.branding.manage', 'admin.integrations.manage',
                    'admin.compliance.export', 'dashboard.pins.manage', 'comms.settings.advanced',
                ],
                self::LEVEL_SA_SECTION => [
                    'admin.backoffice.view', 'admin.audit.view', 'admin.settings.manage',
                ],
                self::LEVEL_SON_GROUPE => [
                    'admin.backoffice.view',
                ],
                self::LEVEL_LECTURE => [
                    'admin.backoffice.view', 'admin.audit.view',
                ],
                self::LEVEL_SA_FICHE => [],
                self::LEVEL_INSTRUCTION => [
                    'admin.backoffice.view',
                ],
                self::LEVEL_PARTIEL => [
                    'admin.backoffice.view', 'admin.settings.manage',
                ],
            ],
        ];

        return $map[$moduleKey][$accessLevel] ?? [];
    }

    /**
     * Droits transverses (suppression / export) mappés vers des slugs.
     *
     * @return list<string>
     */
    public static function transversalPermissionSlugs(bool $canDelete, bool $canExport): array
    {
        $slugs = [];
        if ($canDelete) {
            $slugs = array_merge($slugs, [
                'documents.delete',
                'training.delete',
                'admin.members.moderate',
            ]);
        }
        if ($canExport) {
            $slugs = array_merge($slugs, [
                'personnel.directory.export',
                'operations.aar.export',
                'training.results.export',
                'admin.compliance.export',
                'finances.export',
                'atak.certificates.export',
                'atak.sse.export',
            ]);
        }

        return array_values(array_unique($slugs));
    }

    /**
     * Profil matriciel par défaut selon le slug de rôle connu.
     *
     * @return array{level: int, modules: array<string, string>, can_delete: bool, can_export: bool}|null
     */
    public static function defaultProfileForRoleSlug(string $slug): ?array
    {
        return match ($slug) {
            'community_owner', 'tenant_admin' => [
                'level' => 5,
                'modules' => [
                    self::MODULE_MEMBERS => self::LEVEL_COMPLET,
                    self::MODULE_OPERATIONS => self::LEVEL_COMPLET,
                    self::MODULE_ATAK => self::LEVEL_COMPLET,
                    self::MODULE_FINANCES => self::LEVEL_COMPLET,
                    self::MODULE_SYSTEMS => self::LEVEL_COMPLET,
                ],
                'can_delete' => true,
                'can_export' => true,
            ],
            'deputy_commander', 'operations_officer' => [
                'level' => 4,
                'modules' => [
                    self::MODULE_MEMBERS => self::LEVEL_SA_SECTION,
                    self::MODULE_OPERATIONS => self::LEVEL_COMPLET,
                    self::MODULE_ATAK => self::LEVEL_COMPLET,
                    self::MODULE_FINANCES => self::LEVEL_LECTURE,
                    self::MODULE_SYSTEMS => self::LEVEL_PARTIEL,
                ],
                'can_delete' => false,
                'can_export' => true,
            ],
            'technical_admin' => [
                'level' => 4,
                'modules' => [
                    self::MODULE_MEMBERS => self::LEVEL_LECTURE,
                    self::MODULE_OPERATIONS => self::LEVEL_LECTURE,
                    self::MODULE_ATAK => self::LEVEL_COMPLET,
                    self::MODULE_FINANCES => self::LEVEL_NONE,
                    self::MODULE_SYSTEMS => self::LEVEL_PARTIEL,
                ],
                'can_delete' => false,
                'can_export' => true,
            ],
            'officer', 'training_officer', 'intelligence_officer', 'logistics_officer' => [
                'level' => 3,
                'modules' => [
                    self::MODULE_MEMBERS => self::LEVEL_SA_SECTION,
                    self::MODULE_OPERATIONS => self::LEVEL_SA_SECTION,
                    self::MODULE_ATAK => self::LEVEL_LECTURE,
                    self::MODULE_FINANCES => self::LEVEL_NONE,
                    self::MODULE_SYSTEMS => self::LEVEL_NONE,
                ],
                'can_delete' => false,
                'can_export' => true,
            ],
            'instructor', 'trainer', 'senior_instructor', 'instructor_trainer', 'trainer_of_trainers' => [
                'level' => 2,
                'modules' => [
                    self::MODULE_MEMBERS => self::LEVEL_LECTURE,
                    self::MODULE_OPERATIONS => self::LEVEL_LECTURE,
                    self::MODULE_ATAK => self::LEVEL_NONE,
                    self::MODULE_FINANCES => self::LEVEL_NONE,
                    self::MODULE_SYSTEMS => self::LEVEL_NONE,
                ],
                'can_delete' => false,
                'can_export' => false,
            ],
            'member' => [
                'level' => 1,
                'modules' => [
                    self::MODULE_MEMBERS => self::LEVEL_SA_FICHE,
                    self::MODULE_OPERATIONS => self::LEVEL_LECTURE,
                    self::MODULE_ATAK => self::LEVEL_NONE,
                    self::MODULE_FINANCES => self::LEVEL_NONE,
                    self::MODULE_SYSTEMS => self::LEVEL_NONE,
                ],
                'can_delete' => false,
                'can_export' => false,
            ],
            'invite', 'probation' => [
                'level' => 0,
                'modules' => [
                    self::MODULE_MEMBERS => self::LEVEL_SA_FICHE,
                    self::MODULE_OPERATIONS => self::LEVEL_NONE,
                    self::MODULE_ATAK => self::LEVEL_NONE,
                    self::MODULE_FINANCES => self::LEVEL_NONE,
                    self::MODULE_SYSTEMS => self::LEVEL_NONE,
                ],
                'can_delete' => false,
                'can_export' => false,
            ],
            default => null,
        };
    }

    public static function roleCodeFromSlug(string $slug, int $roleId = 0): string
    {
        $map = [
            'community_owner' => 'ROL-ADM',
            'tenant_admin' => 'ROL-ADM',
            'deputy_commander' => 'ROL-CMD',
            'operations_officer' => 'ROL-OPS',
            'technical_admin' => 'ROL-TAK',
            'training_officer' => 'ROL-FRM',
            'officer' => 'ROL-SEC',
            'member' => 'ROL-MBR',
            'invite' => 'ROL-INV',
            'instructor' => 'ROL-INS',
            'forum_moderator' => 'ROL-MOD',
            'hr' => 'ROL-RH',
            'recruiter' => 'ROL-REC',
        ];
        if (isset($map[$slug])) {
            return $map[$slug];
        }
        $suffix = strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $slug) ?? 'ROL', 0, 3));
        if ($suffix === '') {
            $suffix = 'ROL';
        }

        return 'ROL-' . $suffix . ($roleId > 0 ? sprintf('%02d', $roleId % 100) : '');
    }
}
