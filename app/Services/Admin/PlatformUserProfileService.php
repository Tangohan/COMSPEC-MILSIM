<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\GradeCategoryRepository;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelExtrasRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserLegalIdentityRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Documents\DocumentAccessService;
use App\Services\Steam\SteamWebApiService;
use App\Support\Profile\PublicFlagCountryCatalog;

/**
 * Édition complète d’une fiche depuis l’administration du site (toutes communautés).
 * Ne crée aucune donnée : les valeurs existantes sont reprises telles quelles.
 */
final class PlatformUserProfileService
{
    public function __construct(
        private UserRepository $users,
        private UserProfileRepository $profiles,
        private UserLegalIdentityRepository $legalIdentities,
        private PersonnelProfileRepository $personnelProfiles,
        private PersonnelExtrasRepository $personnelExtras,
        private PersonnelAssignmentRepository $assignments,
        private GradeRepository $grades,
        private GradeCategoryRepository $gradeCategories,
        private RoleRepository $roles,
        private UnitRepository $units,
        private TenantRepository $tenants,
        private SteamWebApiService $steamWebApi,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function load(int $userId): ?array
    {
        $user = $this->users->findById($userId, null);
        if ($user === null) {
            return null;
        }
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $profile = $this->profiles->getByUserId($userId) ?? [];
        $legal = $this->legalIdentities->getByUserId($userId) ?? [];
        $personnel = $this->personnelProfiles->getByUserId($userId) ?? [];
        $extras = $this->personnelExtras->getByUserId($userId) ?? [];

        $extraSlots = function_exists('personnel_extra_callsign_slots') ? personnel_extra_callsign_slots() : 5;
        $extraCallsigns = [];
        $rawExtras = $personnel['extra_callsigns_json'] ?? null;
        if (is_string($rawExtras) && $rawExtras !== '') {
            $decoded = json_decode($rawExtras, true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    $extraCallsigns[] = trim((string) $item);
                }
            }
        }
        while (count($extraCallsigns) < $extraSlots) {
            $extraCallsigns[] = '';
        }
        $extraCallsigns = array_slice($extraCallsigns, 0, $extraSlots);

        $nicknames = [];
        $rawNick = $personnel['nicknames_json'] ?? null;
        if (is_string($rawNick) && $rawNick !== '') {
            $decodedNick = json_decode($rawNick, true);
            if (is_array($decodedNick)) {
                foreach ($decodedNick as $item) {
                    $t = trim((string) $item);
                    if ($t !== '') {
                        $nicknames[] = $t;
                    }
                }
            }
        }

        $selectedRoleIds = $this->users->listOrganizationRoleIdsForUser($userId);
        if ($selectedRoleIds === [] && !empty($user['role_id'])) {
            $selectedRoleIds = [(int) $user['role_id']];
        }

        $tenant = $tenantId > 0 ? ($this->tenants->findById($tenantId) ?: []) : [];

        return [
            'user' => $user,
            'profile' => is_array($profile) ? $profile : [],
            'legal' => is_array($legal) ? $legal : [],
            'personnel' => is_array($personnel) ? $personnel : [],
            'extras' => is_array($extras) ? $extras : [],
            'tenant' => is_array($tenant) ? $tenant : [],
            'grades' => $tenantId > 0 ? $this->grades->listForTenant($tenantId) : [],
            'grade_categories' => $this->gradeCategories->listActive(),
            'units' => $tenantId > 0 ? $this->units->allForTenant($tenantId) : [],
            'roles' => $tenantId > 0 ? $this->roles->forTenantOrganization($tenantId) : [],
            'selected_role_ids' => $selectedRoleIds,
            'extra_callsigns' => $extraCallsigns,
            'nicknames_text' => implode("\n", $nicknames),
            'steam_configured' => $this->steamWebApi->isConfigured(),
            'clearance_options' => DocumentAccessService::getClassificationLevelLabels(),
            'flag_options' => PublicFlagCountryCatalog::optionsForSelect(),
            'status_options' => self::accountStatusOptions(),
            'blood_options' => self::bloodTypeOptions(),
            'sex_options' => self::sexOptions(),
            'family_options' => self::familySituationOptions(),
            'language_options' => self::interfaceLanguageOptions(),
            'timezone_options' => self::timezoneOptions(),
            'doctrine_options' => self::doctrineOptions(),
            'grade_format_options' => self::gradeFormatOptions(),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, error?: string, notes?: list<string>}
     */
    public function save(int $userId, int $actorId, array $input): array
    {
        $user = $this->users->findById($userId, null);
        if ($user === null) {
            return ['ok' => false, 'error' => 'Compte introuvable.'];
        }
        if (!empty($user['deleted_at'])) {
            return ['ok' => false, 'error' => 'Ce compte a déjà été anonymisé : il ne peut plus être modifié.'];
        }
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            return ['ok' => false, 'error' => 'Communauté introuvable pour cette fiche.'];
        }

        $clip = static function (string $value, int $max): string {
            $value = trim($value);
            if ($value === '') {
                return '';
            }
            if (function_exists('mb_substr')) {
                return mb_substr($value, 0, $max);
            }

            return substr($value, 0, $max);
        };

        $firstName = $clip((string) ($input['first_name'] ?? ''), 100);
        $lastName = $clip((string) ($input['last_name'] ?? ''), 100);
        if ($firstName === '' || $lastName === '') {
            return ['ok' => false, 'error' => 'Le prénom et le nom du personnage sont requis.'];
        }
        $displayName = $clip(trim($firstName . ' ' . $lastName), 160);

        $callsign = $clip((string) ($input['callsign'] ?? ''), 80);
        if ($callsign !== '' && $this->users->callsignExistsInTenant($tenantId, $callsign, $userId)) {
            return ['ok' => false, 'error' => 'Cet indicatif est déjà utilisé par un autre membre de cette communauté.'];
        }

        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Une adresse e-mail valide est requise.'];
        }
        $oldEmail = strtolower(trim((string) ($user['email'] ?? '')));
        if ($email !== $oldEmail) {
            $siblings = $oldEmail !== '' ? $this->users->listIdsByEmailNormalized($oldEmail) : [$userId];
            if ($siblings === []) {
                $siblings = [$userId];
            }
            $holders = $this->users->listIdsByEmailNormalized($email);
            $foreign = array_values(array_diff($holders, $siblings));
            if ($foreign !== []) {
                return ['ok' => false, 'error' => 'Cette adresse e-mail est déjà utilisée par un autre compte.'];
            }
        }

        $status = trim((string) ($input['status'] ?? ''));
        if ($status === 'pending') {
            $status = 'pending_verification';
        }
        if (!array_key_exists($status, self::accountStatusOptions())) {
            return ['ok' => false, 'error' => 'Le statut du compte n’est pas reconnu.'];
        }
        if ($status === 'inactive' && $userId === $actorId) {
            return ['ok' => false, 'error' => 'Vous ne pouvez pas désactiver votre propre compte.'];
        }

        $userPatch = [
            'display_name' => $displayName !== '' ? $displayName : null,
            'callsign' => $callsign !== '' ? $callsign : null,
            'email' => $email,
            'status' => $status,
        ];

        $password = (string) ($input['password'] ?? '');
        if ($password !== '') {
            if (strlen($password) < 6) {
                return ['ok' => false, 'error' => 'Le nouveau mot de passe doit contenir au moins 6 caractères.'];
            }
            $userPatch['password_hash'] = password_hash($password, PASSWORD_ARGON2ID);
        }

        $doctrine = trim((string) ($input['nationality_code'] ?? ''));
        if ($doctrine !== '' && !array_key_exists($doctrine, self::doctrineOptions())) {
            return ['ok' => false, 'error' => 'La doctrine choisie n’est pas reconnue.'];
        }
        $userPatch['nationality_code'] = $doctrine !== '' ? $doctrine : null;

        $gradeFormat = trim((string) ($input['preferred_grade_format'] ?? 'classic'));
        $userPatch['preferred_grade_format'] = array_key_exists($gradeFormat, self::gradeFormatOptions())
            ? $gradeFormat
            : 'classic';

        $category = trim((string) ($input['professional_category_code'] ?? ''));
        if ($category !== '' && $this->gradeCategories->findByCode($category) === null) {
            return ['ok' => false, 'error' => 'La catégorie de personnel choisie n’est pas reconnue.'];
        }
        $userPatch['professional_category_code'] = $category !== '' ? $category : null;

        $rawGrade = $input['grade_id'] ?? '';
        $gradeId = $rawGrade !== '' && $rawGrade !== null ? (int) $rawGrade : null;
        if ($gradeId !== null && $gradeId > 0) {
            $allowedGradeIds = array_map(
                static fn (array $g): int => (int) ($g['id'] ?? 0),
                $this->grades->listForTenant($tenantId)
            );
            if (!in_array($gradeId, $allowedGradeIds, true)) {
                return ['ok' => false, 'error' => 'Le grade sélectionné n’est pas disponible pour cette communauté.'];
            }
            $userPatch['grade_id'] = $gradeId;
        } else {
            $userPatch['grade_id'] = null;
        }

        $steamRaw = array_key_exists('steam_id', $input) ? trim((string) $input['steam_id']) : null;
        $steamNotes = [];
        if ($steamRaw !== null) {
            $steamResult = $this->resolveSteamId($steamRaw, $tenantId, $userId);
            if ($steamResult['ok'] === false) {
                return ['ok' => false, 'error' => $steamResult['error']];
            }
            $userPatch['steam_id'] = $steamResult['steam_id'];
        }

        $roleIds = [];
        if (array_key_exists('org_role_ids', $input) || array_key_exists('org_role_ids_present', $input)) {
            $rawRoles = $input['org_role_ids'] ?? [];
            if (!is_array($rawRoles)) {
                $rawRoles = $rawRoles !== '' && $rawRoles !== null ? [$rawRoles] : [];
            }
            $knownRoles = $this->roles->forTenantOrganization($tenantId);
            $knownIds = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $knownRoles);
            foreach ($rawRoles as $rid) {
                $rid = (int) $rid;
                if ($rid > 0 && in_array($rid, $knownIds, true)) {
                    $roleIds[] = $rid;
                }
            }
            $roleIds = array_values(array_unique($roleIds));
            $oldRoleIds = $this->users->listOrganizationRoleIdsForUser($userId);
            $ownerRoleId = $this->roles->getIdBySlug($tenantId, 'community_owner');
            if ($ownerRoleId !== null) {
                $hadOwner = in_array($ownerRoleId, $oldRoleIds, true);
                $hasOwnerNew = in_array($ownerRoleId, $roleIds, true);
                if ($hadOwner && !$hasOwnerNew) {
                    $count = $this->users->countUsersWithRole($ownerRoleId);
                    if ($count <= 1) {
                        return ['ok' => false, 'error' => 'Impossible de retirer le rôle de propriétaire au dernier titulaire.'];
                    }
                }
            }
            try {
                $this->users->syncOrganizationRoles($userId, $tenantId, $roleIds, $actorId);
            } catch (\InvalidArgumentException $e) {
                return ['ok' => false, 'error' => $e->getMessage()];
            }
        }

        $this->users->update($userId, $tenantId, $userPatch);
        if ($email !== $oldEmail && $oldEmail !== '' && !str_ends_with($oldEmail, '@deleted.invalid')) {
            $siblings = $this->users->listIdsByEmailNormalized($oldEmail);
            foreach ($siblings as $sid) {
                $sid = (int) $sid;
                if ($sid < 1 || $sid === $userId) {
                    continue;
                }
                $sibling = $this->users->findById($sid, null);
                if ($sibling === null || !empty($sibling['deleted_at'])) {
                    continue;
                }
                $siblingTid = (int) ($sibling['tenant_id'] ?? 0);
                if ($siblingTid > 0) {
                    $this->users->update($sid, $siblingTid, ['email' => $email]);
                }
            }
        }

        if (!empty($input['sync_steam_profile']) && !empty($userPatch['steam_id'])) {
            $steamNotes = $this->applySteamProfileSync($userId, $tenantId, (string) $userPatch['steam_id']);
        } elseif (!empty($input['sync_steam_profile'])) {
            $finalSteam = trim((string) ($userPatch['steam_id'] ?? $user['steam_id'] ?? ''));
            if ($finalSteam === '') {
                return ['ok' => false, 'error' => 'Indiquez un identifiant Steam avant de synchroniser la photo du compte.'];
            }
            $steamNotes = $this->applySteamProfileSync($userId, $tenantId, $finalSteam);
        }

        $phone = $clip((string) ($input['phone'] ?? ''), 40);
        $birthDate = $this->normalizeDate((string) ($input['birth_date'] ?? ''));
        $civilNationality = $clip((string) ($input['civil_nationality'] ?? ''), 80);
        $discord = $clip((string) ($input['discord_handle'] ?? ''), 80);
        $timezone = trim((string) ($input['timezone'] ?? ''));
        if ($timezone !== '' && !array_key_exists($timezone, self::timezoneOptions())) {
            $existingTz = trim((string) (($this->profiles->getByUserId($userId)['timezone'] ?? '')));
            if ($timezone !== $existingTz) {
                return ['ok' => false, 'error' => 'Le fuseau horaire choisi n’est pas reconnu.'];
            }
        }
        $language = trim((string) ($input['language'] ?? ''));
        if ($language !== '' && !array_key_exists($language, self::interfaceLanguageOptions())) {
            return ['ok' => false, 'error' => 'La langue de l’interface n’est pas reconnue.'];
        }
        $flag = strtoupper(trim((string) ($input['public_flag_country_code'] ?? '')));
        if ($flag !== '' && !PublicFlagCountryCatalog::isAllowed($flag)) {
            return ['ok' => false, 'error' => 'Le drapeau choisi n’est pas reconnu.'];
        }
        $bio = $clip((string) ($input['bio'] ?? ''), 2000);
        $country = $clip((string) ($input['country_of_residence'] ?? ''), 80);

        $this->profiles->upsert($userId, [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone !== '' ? $phone : null,
            'birth_date' => $birthDate,
            'nationality' => $civilNationality !== '' ? $civilNationality : null,
            'country_of_residence' => $country !== '' ? $country : null,
            'public_flag_country_code' => $flag !== '' ? $flag : null,
            'discord_handle' => $discord !== '' ? $discord : null,
            'timezone' => $timezone !== '' ? $timezone : null,
            'language' => $language !== '' ? $language : null,
            'bio' => $bio !== '' ? $bio : null,
        ]);

        $civilFirst = $clip((string) ($input['civil_first_name'] ?? ''), 100);
        $civilLast = $clip((string) ($input['civil_last_name'] ?? ''), 100);
        $this->legalIdentities->upsert($userId, $tenantId, [
            'first_name' => $civilFirst,
            'last_name' => $civilLast,
            'phone' => $phone,
            'birth_date' => (string) ($birthDate ?? ''),
            'nationality' => $civilNationality,
        ]);

        $blood = trim((string) ($input['blood_type'] ?? ''));
        $bloodAllowed = array_values(array_filter(array_keys(self::bloodTypeOptions()), static fn (string $k): bool => $k !== ''));
        if ($blood !== '' && !in_array($blood, $bloodAllowed, true)) {
            $existingBlood = trim((string) (($this->personnelProfiles->getByUserId($userId)['blood_type'] ?? '')));
            if ($blood !== $existingBlood) {
                return ['ok' => false, 'error' => 'Le groupe sanguin choisi n’est pas reconnu.'];
            }
        }
        $sex = trim((string) ($input['sex'] ?? ''));
        if ($sex !== '' && !array_key_exists($sex, self::sexOptions())) {
            $existingSex = trim((string) (($this->personnelProfiles->getByUserId($userId)['sex'] ?? '')));
            if ($sex !== $existingSex) {
                return ['ok' => false, 'error' => 'Le sexe indiqué n’est pas reconnu.'];
            }
        }
        $family = trim((string) ($input['family_situation'] ?? ''));
        if ($family !== '' && !array_key_exists($family, self::familySituationOptions())) {
            $existingFamily = trim((string) (($this->personnelProfiles->getByUserId($userId)['family_situation'] ?? '')));
            if ($family !== $existingFamily) {
                return ['ok' => false, 'error' => 'La situation familiale choisie n’est pas reconnue.'];
            }
        }

        $weightRaw = $input['weight_kg'] ?? '';
        $weight = ($weightRaw === null || $weightRaw === '') ? null : max(20, min(300, (int) $weightRaw));
        $readinessRaw = $input['readiness_score'] ?? '';
        $readiness = ($readinessRaw === null || $readinessRaw === '') ? null : max(0, min(100, (int) $readinessRaw));
        $enlistment = $this->normalizeDate((string) ($input['enlistment_date'] ?? ''));
        $clearanceReview = $this->normalizeDate((string) ($input['clearance_reviewed_at'] ?? ''));
        $clearance = trim((string) ($input['clearance_level'] ?? ''));
        $clearanceLabels = DocumentAccessService::getClassificationLevelLabels();
        if ($clearance !== '' && !isset($clearanceLabels[$clearance])) {
            return ['ok' => false, 'error' => 'Le niveau d’habilitation n’est pas reconnu.'];
        }

        $primaryUnitIdRaw = $input['primary_unit_id'] ?? '';
        $primaryUnitId = $primaryUnitIdRaw !== '' && $primaryUnitIdRaw !== null ? (int) $primaryUnitIdRaw : null;
        if ($primaryUnitId !== null && $primaryUnitId > 0) {
            $unitRow = $this->units->findById($primaryUnitId, $tenantId);
            if (!$unitRow) {
                return ['ok' => false, 'error' => 'L’unité sélectionnée n’appartient pas à cette communauté.'];
            }
        } else {
            $primaryUnitId = null;
        }

        $extraCallsignsJson = json_encode(
            function_exists('personnel_normalize_extra_callsigns')
                ? personnel_normalize_extra_callsigns(
                    $input['extra_callsigns'] ?? [],
                    $callsign,
                    function_exists('personnel_extra_callsign_slots') ? personnel_extra_callsign_slots() : 5,
                    100
                )
                : [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $nicknamesJson = json_encode(
            $this->normalizeMultilineList((string) ($input['nicknames_text'] ?? ''), 12, 120),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $matricule = $clip((string) ($input['matricule_internal'] ?? ''), 64);
        $notes = trim((string) ($input['command_notes'] ?? ''));

        $this->personnelProfiles->ensureRecord($userId);
        $this->personnelProfiles->update($userId, [
            'character_name' => $displayName,
            'callsign' => $callsign !== '' ? $callsign : null,
            'extra_callsigns_json' => $extraCallsignsJson,
            'nickname_primary' => $clip((string) ($input['nickname_primary'] ?? ''), 120) ?: null,
            'nicknames_json' => $nicknamesJson,
            'rank_display' => $clip((string) ($input['rank_display'] ?? ''), 100) ?: null,
            'rank_display_override' => $clip((string) ($input['rank_display_override'] ?? ''), 100) ?: null,
            'primary_unit_id' => $primaryUnitId,
            'clearance_level' => $clearance !== '' ? $clearance : null,
            'clearance_reviewed_at' => $clearanceReview,
            'readiness_score' => $readiness,
            'blood_type' => $blood !== '' ? $blood : null,
            'nationality' => $clip((string) ($input['nationality_rp'] ?? ''), 100) ?: null,
            'languages' => $clip((string) ($input['languages'] ?? ''), 255) ?: null,
            'enlistment_date' => $enlistment,
            'motto' => $clip((string) ($input['motto'] ?? ''), 255) ?: null,
            'sex' => $sex !== '' ? $sex : null,
            'family_situation' => $family !== '' ? $family : null,
            'weight_kg' => $weight,
            'operator_status' => $clip((string) ($input['operator_status'] ?? ''), 160) ?: null,
            'operator_tags' => $clip((string) ($input['operator_tags'] ?? ''), 255) ?: null,
            'birth_place' => $clip((string) ($input['birth_place'] ?? ''), 150) ?: null,
            'matricule_internal' => $matricule !== '' ? $matricule : null,
            'command_notes' => $notes !== '' ? $notes : null,
            'equipment_class' => $clip((string) ($input['equipment_class'] ?? ''), 120) ?: null,
            'kit_assigned' => $clip((string) ($input['kit_assigned'] ?? ''), 120) ?: null,
            'radio_assigned' => $clip((string) ($input['radio_assigned'] ?? ''), 120) ?: null,
            'vehicle_authorized' => $clip((string) ($input['vehicle_authorized'] ?? ''), 120) ?: null,
            'weapon_specialty' => $clip((string) ($input['weapon_specialty'] ?? ''), 120) ?: null,
            'deployable' => !empty($input['deployable']) ? 1 : 0,
        ]);

        if ($matricule !== '') {
            $this->personnelExtras->updateServiceNumber($userId, $matricule);
        }
        $this->personnelExtras->updateAdminNotes($userId, $notes);

        try {
            $this->assignments->syncPrimaryAssignmentFromDossier(
                $userId,
                $primaryUnitId,
                $clip((string) ($input['assignment_role'] ?? ''), 120) ?: 'Membre',
                null
            );
        } catch (\Throwable) {
            // La fiche reste enregistrée même si l’organigramme n’a pas pu être aligné.
        }

        return [
            'ok' => true,
            'notes' => $steamNotes,
        ];
    }

    /** @return array<string, string> */
    public static function accountStatusOptions(): array
    {
        return [
            'active' => 'Compte actif',
            'inactive' => 'Compte inactif',
            'pending_verification' => 'En attente de vérification de l’e-mail',
        ];
    }

    /** @return array<string, string> */
    public static function bloodTypeOptions(): array
    {
        return [
            '' => 'Non renseigné',
            'A+' => 'A+',
            'A-' => 'A-',
            'B+' => 'B+',
            'B-' => 'B-',
            'AB+' => 'AB+',
            'AB-' => 'AB-',
            'O+' => 'O+',
            'O-' => 'O-',
            'Inconnu' => 'Inconnu',
        ];
    }

    /** @return array<string, string> */
    public static function sexOptions(): array
    {
        return [
            '' => 'Non renseigné',
            'Homme' => 'Homme',
            'Femme' => 'Femme',
            'Autre' => 'Autre',
        ];
    }

    /** @return array<string, string> */
    public static function familySituationOptions(): array
    {
        return [
            '' => 'Non renseignée',
            'Célibataire' => 'Célibataire',
            'En couple' => 'En couple',
            'Marié(e)' => 'Marié(e)',
            'Pacsé(e)' => 'Pacsé(e)',
            'Divorcé(e)' => 'Divorcé(e)',
            'Veuf / veuve' => 'Veuf / veuve',
        ];
    }

    /** @return array<string, string> */
    public static function interfaceLanguageOptions(): array
    {
        return [
            '' => 'Non renseignée',
            'fr' => 'Français',
            'en' => 'Anglais',
        ];
    }

    /** @return array<string, string> */
    public static function timezoneOptions(): array
    {
        return [
            '' => 'Non renseigné',
            'Europe/Paris' => 'Paris (Europe)',
            'Europe/Brussels' => 'Bruxelles',
            'Europe/London' => 'Londres',
            'Europe/Berlin' => 'Berlin',
            'Atlantic/Reykjavik' => 'Reykjavik',
            'America/New_York' => 'New York',
            'America/Chicago' => 'Chicago',
            'America/Denver' => 'Denver',
            'America/Los_Angeles' => 'Los Angeles',
            'America/Montreal' => 'Montréal',
            'America/Guadeloupe' => 'Guadeloupe',
            'America/Martinique' => 'Martinique',
            'Indian/Reunion' => 'La Réunion',
            'Pacific/Tahiti' => 'Tahiti',
            'Pacific/Noumea' => 'Nouméa',
            'UTC' => 'UTC',
        ];
    }

    /** @return array<string, string> */
    public static function doctrineOptions(): array
    {
        return [
            'FR' => 'Française',
            'US' => 'Américaine',
        ];
    }

    /** @return array<string, string> */
    public static function gradeFormatOptions(): array
    {
        return [
            'classic' => 'Classique (texte)',
            'otan' => 'OTAN',
            'hybrid' => 'Hybride (ex. Capitaine (OF-2))',
        ];
    }

    /**
     * @return array{ok: true, steam_id: ?string}|array{ok: false, error: string}
     */
    private function resolveSteamId(string $raw, int $tenantId, int $excludeUserId): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['ok' => true, 'steam_id' => null];
        }
        $resolved = $this->steamWebApi->resolveSteamIdFromUserInput($raw);
        if ($resolved === null) {
            return [
                'ok' => false,
                'error' => 'Impossible de reconnaître l’identifiant Steam. Indiquez le numéro Steam, le format classique, ou l’adresse du profil public.',
            ];
        }
        $existing = $this->users->findBySteamIdForTenant($tenantId, $resolved);
        if ($existing && (int) ($existing['id'] ?? 0) !== $excludeUserId) {
            return [
                'ok' => false,
                'error' => 'Cet identifiant Steam est déjà rattaché à un autre membre de cette communauté.',
            ];
        }

        return ['ok' => true, 'steam_id' => $resolved];
    }

    /** @return list<string> */
    private function applySteamProfileSync(int $userId, int $tenantId, string $steamId): array
    {
        $notes = [];
        if (!$this->steamWebApi->isConfigured()) {
            $notes[] = 'Identifiant Steam enregistré. La lecture du profil public n’est pas configurée sur ce serveur.';

            return $notes;
        }
        $summary = $this->steamWebApi->fetchPublicPlayer($steamId);
        if ($summary === null) {
            $notes[] = 'Identifiant Steam enregistré, mais le profil public Steam n’a pas pu être lu.';

            return $notes;
        }
        $patch = [];
        if (($summary['avatar_url'] ?? '') !== '') {
            $patch['avatar_url'] = function_exists('mb_substr')
                ? mb_substr((string) $summary['avatar_url'], 0, 500)
                : substr((string) $summary['avatar_url'], 0, 500);
        }
        if ($patch === []) {
            $notes[] = 'Identifiant Steam enregistré. Aucune donnée exploitable renvoyée par Steam.';

            return $notes;
        }
        $this->users->update($userId, $tenantId, $patch);
        $notes[] = 'Photo du compte mise à jour depuis Steam.';

        return $notes;
    }

    private function normalizeDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return null;
        }

        return $raw;
    }

    /**
     * @return list<string>
     */
    private function normalizeMultilineList(string $raw, int $maxItems, int $maxLen): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
            $t = trim((string) $line);
            if ($t === '') {
                continue;
            }
            if (function_exists('mb_substr')) {
                $t = mb_substr($t, 0, $maxLen);
            } else {
                $t = substr($t, 0, $maxLen);
            }
            $out[] = $t;
            if (count($out) >= $maxItems) {
                break;
            }
        }

        return $out;
    }
}
