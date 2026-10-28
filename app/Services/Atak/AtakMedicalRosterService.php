<?php

declare(strict_types=1);

namespace App\Services\Atak;

use App\Core\Gate;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Rbac\RbacService;
use App\Support\EffectifsLmsAccess;

/**
 * Fiches médicales de la communauté dans l'app Médical de l'ATAK natif (COMSPEC Link « MedicalRoster ») : groupe
 * sanguin, poids, taille, intolérances médicamenteuses et allergies de chaque membre actif, les mêmes données que
 * Effectifs > Tableur > Médical. Le téléphone garde la liste sur le PC (Sync\medical_roster.json) et ne la relit
 * que sur ACTUALISER.
 * Réservé au joueur connecté par sa propre session de jeu qui est :
 *   - gestionnaire de la communauté ou administrateur de la plateforme ;
 *   - au pilotage des effectifs (il voit déjà la vue Médical du tableur) ;
 *   - ou titulaire d'une fonction médicale (médecin, infirmier, medic, corpsman, combat lifesaver…).
 * Seuls les membres de la communauté de la session sont renvoyés ; rien n'est écrit.
 */
final class AtakMedicalRosterService
{
    /** Membres renvoyés au plus (la DLL découpe la liste en pages). */
    public const MAX_ROWS = 500;

    /** Mots qui désignent une fonction médicale (nom ou identifiant de l'emploi, sans accents ni casse). */
    public const MEDIC_KEYWORDS = ['medic', 'medec', 'medical', 'infirm', 'corpsman', 'sante', 'lifesaver', 'doctor', 'docteur', 'nurse', 'pararescue', 'secouris', 'sanitaire', 'cls'];

    /** Comptes sans accès (users.status). */
    private const CLOSED = ['banned', 'deleted', 'disabled', 'suspended', 'inactive'];

    public function __construct(
        private ?UserRepository $users = null,
        private ?PersonnelProfileRepository $profiles = null,
        private ?PersonnelJobRoleRepository $jobs = null,
    ) {}

    /**
     * Droit du joueur sur les fiches médicales : "platform", "manager", "effectifs", "medic" ou null.
     *
     * @param array<string, mixed>|null $raw ligne users déjà lue
     */
    public function role(int $tenantId, ?int $userId, ?array $raw = null): ?string
    {
        if ($tenantId < 1 || $userId === null || $userId < 1) {
            return null;
        }
        $users = $this->users ??= new UserRepository();
        $raw ??= $users->findById($userId);
        if ($raw === null || in_array((string) ($raw['status'] ?? 'active'), self::CLOSED, true)) {
            return null;
        }
        $admin = (new AtakGameAdminService(users: $users))->role($tenantId, $userId, $raw);
        if ($admin !== null) {
            return $admin;
        }
        $member = $users->findById($userId, $tenantId);
        if ($member === null) {
            return null;
        }
        $gate = Gate::getInstance();
        $before = [$gate->permissionSlugs(), $gate->getUnitPermissionMap(), $gate->isPlatformAdmin()];
        try {
            (new RbacService())->setPermissionsForGateFromUserRow($member + ['tenant_id' => $tenantId], $users);
            if (EffectifsLmsAccess::allows($gate)) {
                return 'effectifs';
            }
        } catch (\Throwable) {
            // Droits illisibles : on regarde encore la fonction du membre.
        } finally {
            // Requête d'API : on rend la porte telle qu'on l'a trouvée.
            $gate->setFullRbacState($before[0], $before[1]);
            $gate->setPlatformAdmin($before[2]);
        }
        try {
            $assigned = ($this->jobs ??= new PersonnelJobRoleRepository())->listPivotAssignmentsForUsers($tenantId, [$userId]);
        } catch (\Throwable) {
            $assigned = [];
        }
        foreach ($assigned[$userId] ?? [] as $job) {
            if (self::isMedicalJob((string) ($job['role_name'] ?? '') . ' ' . (string) ($job['role_slug'] ?? '') . ' ' . (string) ($job['role_label_en'] ?? '') . ' ' . (string) ($job['role_detail'] ?? ''))) {
                return 'medic';
            }
        }

        return null;
    }

    /** Fonction médicale d'après son libellé (« Médecin », « Combat Medic », « infirmier-chef », « CLS »…). */
    public static function isMedicalJob(string $label): bool
    {
        $t = strtolower(strtr($label, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'É' => 'e', 'È' => 'e', 'à' => 'a', 'ç' => 'c', 'ï' => 'i', 'î' => 'i']));
        if (trim($t) === '') {
            return false;
        }
        foreach (self::MEDIC_KEYWORDS as $k) {
            // « cls » seul : mot entier, pour ne pas prendre « clsoft » ou « cycles ».
            if ($k === 'cls' ? preg_match('/(^|[^a-z])cls([^a-z]|$)/', $t) === 1 : str_contains($t, $k)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fiches des membres actifs de la communauté, par indicatif.
     *
     * @return list<array{steam: string, callsign: string, job: string, blood_type: string, weight_kg: string, height_cm: string, drug_intolerances: string, allergies: string}>
     */
    public function rows(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $users = $this->users ??= new UserRepository();
        $members = $users->listForTenant($tenantId, null, 'active');
        $ids = array_values(array_filter(array_map(static fn (array $m): int => (int) ($m['id'] ?? 0), $members), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return [];
        }
        try {
            $medical = ($this->profiles ??= new PersonnelProfileRepository())->medicalForUsers($tenantId, $ids);
        } catch (\Throwable) {
            $medical = [];
        }
        try {
            $jobs = ($this->jobs ??= new PersonnelJobRoleRepository())->listPivotAssignmentsForUsers($tenantId, $ids);
        } catch (\Throwable) {
            $jobs = [];
        }
        $rows = [];
        foreach ($members as $m) {
            $uid = (int) ($m['id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $med = $medical[$uid] ?? [];
            $job = $jobs[$uid][0] ?? [];
            $label = trim((string) ($m['callsign'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($m['public_name'] ?? ''));
            }
            if ($label === '') {
                $label = trim((string) ($m['display_name'] ?? '')) ?: 'Membre';
            }
            $rows[] = [
                'steam' => trim((string) ($m['steam_id'] ?? '')),
                'callsign' => mb_substr($label, 0, 60),
                'job' => mb_substr(trim((string) ($job['role_name'] ?? '')), 0, 60),
                'blood_type' => mb_substr((string) ($med['blood_type'] ?? ''), 0, 8),
                'weight_kg' => self::number((string) ($med['weight_kg'] ?? '')),
                'height_cm' => self::number((string) ($med['height_cm'] ?? '')),
                'drug_intolerances' => mb_substr((string) ($med['drug_intolerances'] ?? ''), 0, 200),
                'allergies' => mb_substr((string) ($med['allergies'] ?? ''), 0, 200),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => strcasecmp($a['callsign'], $b['callsign']));

        return array_slice($rows, 0, self::MAX_ROWS);
    }

    /** « 82.0 » → « 82 », valeur vide ou nulle → "". */
    private static function number(string $v): string
    {
        $n = (float) str_replace(',', '.', trim($v));

        return $n > 0 ? (string) round($n) : '';
    }
}
