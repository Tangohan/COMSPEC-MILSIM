<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelCorrectionRequestRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * Demandes de correction RH (bouton anomalie) → validation organisateur → mise à jour fiche.
 */
final class PersonnelCorrectionRequestService
{
    /**
     * Champs de la fiche que le membre peut proposer (hors habilitation, e-mail, notes commandement).
     *
     * @var array<string, string>
     */
    public const CORRECTABLE_FIELDS = [
        'first_name' => 'Prénom',
        'last_name' => 'Nom',
        'callsign' => 'Indicatif radio',
        'nickname_primary' => 'Surnom principal',
        'extra_callsigns' => 'Indicatifs secondaires',
        'nicknames' => 'Autres surnoms',
        'motto' => 'Devise',
        'bio' => 'Présentation du personnage',
        'languages' => 'Langues parlées',
        'nationality' => 'Nationalité (personnage)',
        'blood_type' => 'Groupe sanguin',
        'sex' => 'Sexe',
        'birth_place' => 'Lieu de naissance',
        'family_situation' => 'Situation familiale',
        'weight_kg' => 'Poids (kg)',
        'operator_status' => 'Statut opérateur',
        'operator_tags' => 'Spécialités',
        'enlistment_date' => 'Date d’engagement',
        'rp_medical_due_date' => 'Échéance visite médicale',
        'rp_operational_function' => 'Fonction sur le dossier',
        'rank_display' => 'Grade ou titre affiché',
        'rank_display_override' => 'Libellé court du grade',
        'grade_id' => 'Grade attribué',
        'unit_assignments' => 'Affectations d’unité',
        'job_roles' => 'Emplois',
        'equipment_class' => 'Classe d’équipement',
        'kit_assigned' => 'Kit attribué',
        'radio_assigned' => 'Radio attribuée',
        'vehicle_authorized' => 'Véhicule autorisé',
        'weapon_specialty' => 'Spécialité armement',
    ];

    /** @var array<string, string> */
    public const FIELD_GROUPS = [
        'identity' => 'Identité du personnage',
        'details' => 'Détails du personnage',
        'assignment' => 'Affectation',
        'engagement' => 'Engagement et statut',
        'equipment' => 'Équipement',
    ];

    /** @var list<string> */
    public const ORBAT_KEYS = [
        'grade_id',
        'unit_assignments',
        'job_roles',
        'rank_display',
        'rank_display_override',
        'enlistment_date',
    ];

    /** @var list<string> */
    private const USER_PROFILE_KEYS = ['first_name', 'last_name', 'bio'];

    /** @var array<string, string> extra_callsigns|nicknames → colonne JSON personnel_profiles */
    private const LINE_LIST_COLUMNS = [
        'extra_callsigns' => 'extra_callsigns_json',
        'nicknames' => 'nicknames_json',
    ];

    /** @var list<string> */
    private const DATE_KEYS = ['enlistment_date', 'rp_medical_due_date'];

    /** @var list<string> */
    private const STAFF_PERMISSION_SLUGS = [
        'admin.access',
        'admin.organization',
        'admin.roles.manage',
        'personnel.profile.update',
        'personnel.grades.manage',
        'personnel.assignments.manage',
        'personnel.status.manage',
    ];

    public function __construct(
        private PersonnelCorrectionRequestRepository $requests,
        private PersonnelProfileRepository $profiles,
        private UserRepository $userRepository,
        private TenantRepository $tenantRepository,
        private EmailService $emailService,
        private UserNotificationPreferencesRepository $notificationPreferences,
        private UserProfileRepository $userProfiles,
        private PersonnelAssignmentRepository $assignments,
        private PersonnelJobRoleRepository $jobRoles,
        private UnitRepository $units,
        private GradeRepository $grades,
    ) {
    }

    /** @return array<string, string> */
    public static function fieldLabels(): array
    {
        return self::CORRECTABLE_FIELDS;
    }

    /**
     * Métadonnées de saisie pour le formulaire membre (type, groupe, listes).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function fieldCatalog(): array
    {
        $out = [];
        foreach (self::CORRECTABLE_FIELDS as $key => $label) {
            $out[$key] = array_merge(self::fieldMeta($key), ['label' => $label]);
        }

        return $out;
    }

    /**
     * @return array<string, list<array{value: string, label: string}>>
     */
    public static function choiceCatalog(): array
    {
        return [
            'blood_type' => self::choicePairs(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Inconnu']),
            'sex' => [
                ['value' => 'Homme', 'label' => 'Homme'],
                ['value' => 'Femme', 'label' => 'Femme'],
                ['value' => 'Autre', 'label' => 'Autre'],
            ],
            'family_situation' => [
                ['value' => 'Célibataire', 'label' => 'Célibataire'],
                ['value' => 'En couple', 'label' => 'En couple'],
                ['value' => 'Marié(e)', 'label' => 'Marié(e)'],
                ['value' => 'Pacsé(e)', 'label' => 'Pacsé(e)'],
                ['value' => 'Divorcé(e)', 'label' => 'Divorcé(e)'],
                ['value' => 'Veuf / Veuve', 'label' => 'Veuf / Veuve'],
            ],
            'operator_status' => [
                ['value' => 'Actif', 'label' => 'Actif'],
                ['value' => 'Disponible', 'label' => 'Disponible'],
                ['value' => 'En formation', 'label' => 'En formation'],
                ['value' => 'Réserve', 'label' => 'Réserve'],
                ['value' => 'Indisponible', 'label' => 'Indisponible'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function currentSnapshot(int $userId): array
    {
        $profile = $this->profiles->getByUserId($userId) ?? [];
        $userProfile = $this->userProfiles->getByUserId($userId) ?? [];
        $userRow = $this->userRepository->findById($userId) ?? [];
        $tenantId = (int) ($userRow['tenant_id'] ?? 0);
        $out = [];
        foreach (array_keys(self::CORRECTABLE_FIELDS) as $key) {
            if (in_array($key, self::USER_PROFILE_KEYS, true)) {
                $val = $userProfile[$key] ?? null;
                $out[$key] = is_scalar($val) ? trim((string) $val) : '';
                continue;
            }
            if ($key === 'extra_callsigns') {
                $list = function_exists('personnel_decode_extra_callsigns')
                    ? personnel_decode_extra_callsigns($profile['extra_callsigns_json'] ?? null)
                    : [];
                $out[$key] = implode("\n", $list);
                continue;
            }
            if ($key === 'nicknames') {
                $out[$key] = $this->jsonListToLines($profile['nicknames_json'] ?? null);
                continue;
            }
            if ($key === 'grade_id') {
                $gid = (int) ($userRow['grade_id'] ?? 0);
                $out[$key] = $gid > 0 ? (string) $gid : '';
                continue;
            }
            if ($key === 'unit_assignments') {
                $out[$key] = self::canonicalizeUnitAssignments($this->currentUnitAssignmentRows($userId));
                continue;
            }
            if ($key === 'job_roles') {
                $out[$key] = self::canonicalizeJobRoles($this->currentJobRoleRows($tenantId, $userId));
                continue;
            }
            $val = $profile[$key] ?? null;
            if ($val === null) {
                $out[$key] = '';
            } elseif (in_array($key, self::DATE_KEYS, true)) {
                $out[$key] = substr(trim((string) $val), 0, 10);
            } else {
                $out[$key] = is_scalar($val) ? (string) $val : '';
            }
        }

        return $out;
    }

    /**
     * Diff proposé vs fiche actuelle (champs présents dans $rawInput uniquement).
     *
     * @param array<string, mixed> $rawInput
     * @return array<string, mixed>
     */
    public function proposedDiff(int $targetUserId, array $rawInput): array
    {
        return $this->normalizeProposed($rawInput, $this->currentSnapshot($targetUserId));
    }

    /**
     * @param array<string, mixed> $rawInput
     * @return array{ok: bool, message: string, request_id?: int, recipient_names?: list<string>}
     */
    public function submit(
        int $tenantId,
        int $requesterUserId,
        int $targetUserId,
        array $rawInput,
        string $note = ''
    ): array {
        if ($tenantId < 1 || $requesterUserId < 1 || $targetUserId < 1) {
            return ['ok' => false, 'message' => 'Contexte invalide.'];
        }
        $target = $this->userRepository->findById($targetUserId, $tenantId);
        if (!$target) {
            return ['ok' => false, 'message' => 'Fiche introuvable.'];
        }
        if ($this->requests->hasPendingForTarget($tenantId, $targetUserId)) {
            return [
                'ok' => false,
                'message' => 'Une demande de correction est déjà en attente pour cette fiche. Attendez la réponse de l’organisation.',
            ];
        }

        $before = $this->currentSnapshot($targetUserId);
        $proposed = $this->normalizeProposed($rawInput, $before);
        if ($proposed === []) {
            return ['ok' => false, 'message' => 'Aucune modification détectée. Corrigez au moins un champ.'];
        }

        $note = trim($note);
        if (mb_strlen($note) > 1000) {
            $note = mb_substr($note, 0, 1000);
        }

        $recipients = $this->listStaffRecipients($tenantId, $requesterUserId);
        if ($recipients === []) {
            return [
                'ok' => false,
                'message' => 'Aucune organisateur joignable pour valider. Contactez un administrateur autrement.',
            ];
        }

        $requestId = $this->requests->create(
            $tenantId,
            $targetUserId,
            $requesterUserId,
            $proposed,
            array_intersect_key($before, $proposed),
            $note
        );
        if ($requestId < 1) {
            return ['ok' => false, 'message' => 'Enregistrement de la demande impossible.'];
        }

        $this->notifySubmitted(
            $tenantId,
            $requestId,
            $target,
            $requesterUserId,
            $proposed,
            array_intersect_key($before, $proposed),
            $note,
            $recipients
        );

        return [
            'ok' => true,
            'message' => 'Demande envoyée. Un organisateur doit la confirmer avant mise à jour de la fiche.',
            'request_id' => $requestId,
            'recipient_names' => array_column($recipients, 'name'),
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function decide(
        int $tenantId,
        int $requestId,
        int $resolverUserId,
        string $decision,
        string $resolutionNote = ''
    ): array {
        $decision = strtolower(trim($decision));
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return ['ok' => false, 'message' => 'Décision invalide.'];
        }
        $row = $this->requests->findById($requestId, $tenantId);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Demande introuvable.'];
        }
        if (($row['status'] ?? '') !== 'pending') {
            return ['ok' => false, 'message' => 'Cette demande a déjà été traitée.'];
        }

        $resolutionNote = trim($resolutionNote);
        if (mb_strlen($resolutionNote) > 1000) {
            $resolutionNote = mb_substr($resolutionNote, 0, 1000);
        }

        if ($decision === 'approved') {
            $proposed = is_array($row['proposed'] ?? null) ? $row['proposed'] : [];
            $targetUserId = (int) $row['target_user_id'];
            $payload = $this->normalizeProposed($proposed, $this->currentSnapshot($targetUserId));
            if ($payload === []) {
                return ['ok' => false, 'message' => 'Aucune champ valide à appliquer.'];
            }
            $this->applyApprovedPayload($tenantId, $targetUserId, $payload);
        }

        if (!$this->requests->resolve($requestId, $tenantId, $decision, $resolverUserId, $resolutionNote)) {
            return ['ok' => false, 'message' => 'Mise à jour du statut impossible.'];
        }

        $this->notifyDecision($tenantId, $row, $decision, $resolverUserId, $resolutionNote);

        return [
            'ok' => true,
            'message' => $decision === 'approved'
                ? 'Correction confirmée — la fiche a été mise à jour.'
                : 'Demande refusée — la fiche n’a pas été modifiée.',
        ];
    }

    /**
     * Correction appliquée tout de suite par un responsable, sans file d’attente.
     *
     * @param array<string, mixed> $rawInput
     * @return array{ok: bool, message: string}
     */
    public function applyDirect(
        int $tenantId,
        int $actorUserId,
        int $targetUserId,
        array $rawInput,
        string $note = ''
    ): array {
        if ($tenantId < 1 || $actorUserId < 1 || $targetUserId < 1) {
            return ['ok' => false, 'message' => 'Contexte invalide.'];
        }
        $target = $this->userRepository->findById($targetUserId, $tenantId);
        if (!$target) {
            return ['ok' => false, 'message' => 'Fiche introuvable.'];
        }

        $before = $this->currentSnapshot($targetUserId);
        $proposed = $this->normalizeProposed($rawInput, $before);
        if ($proposed === []) {
            return ['ok' => false, 'message' => 'Aucune modification détectée. Corrigez au moins un champ.'];
        }

        $note = trim($note);
        if (mb_strlen($note) > 1000) {
            $note = mb_substr($note, 0, 1000);
        }

        $this->applyApprovedPayload($tenantId, $targetUserId, $proposed);
        $this->requests->cancelPendingForTarget(
            $tenantId,
            $targetUserId,
            $actorUserId,
            'Le dossier a été corrigé directement.'
        );

        try {
            $requestId = $this->requests->create(
                $tenantId,
                $targetUserId,
                $actorUserId,
                $proposed,
                array_intersect_key($before, $proposed),
                $note !== '' ? $note : 'Correction enregistrée sans demande.'
            );
            if ($requestId > 0) {
                $this->requests->resolve(
                    $requestId,
                    $tenantId,
                    'approved',
                    $actorUserId,
                    $note !== '' ? $note : 'Enregistré tout de suite.'
                );
            }
        } catch (\Throwable) {
            // L’écriture du dossier prime sur le journal.
        }

        return [
            'ok' => true,
            'message' => 'Le dossier a été mis à jour tout de suite.',
        ];
    }

    /**
     * @return list<array{user_id: int, name: string, email: string}>
     */
    public function listStaffRecipients(int $tenantId, ?int $excludeUserId = null): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $ids = $this->userRepository->listActiveUserIdsWithAnyPermissionSlug(
            $tenantId,
            self::STAFF_PERMISSION_SLUGS
        );
        if ($excludeUserId !== null && $excludeUserId > 0) {
            $ids = array_values(array_filter($ids, static fn (int $id): bool => $id !== $excludeUserId));
        }
        if ($ids === []) {
            return [];
        }
        $users = $this->userRepository->findByIdsForTenant($tenantId, $ids);
        $out = [];
        $seen = [];
        foreach ($ids as $uid) {
            $user = $users[$uid] ?? null;
            if (!$user || (int) ($user['tenant_id'] ?? 0) !== $tenantId) {
                continue;
            }
            $email = strtolower(trim((string) ($user['email'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $out[] = [
                'user_id' => $uid,
                'name' => $this->displayName($user),
                'email' => $email,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $before
     * @return array<string, mixed>
     */
    private function normalizeProposed(array $raw, array $before): array
    {
        $out = [];
        foreach (array_keys(self::CORRECTABLE_FIELDS) as $key) {
            if (!array_key_exists($key, $raw)) {
                continue;
            }
            $new = $this->normalizeIncomingValue($key, $raw[$key]);
            if (in_array($key, self::DATE_KEYS, true) && $new !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $new)) {
                continue;
            }
            $old = isset($before[$key]) ? trim((string) $before[$key]) : '';
            if ($key === 'extra_callsigns' || $key === 'nicknames') {
                $old = $this->normalizeIncomingValue($key, $old);
            }
            if ($new === $old) {
                continue;
            }
            if (in_array($key, ['unit_assignments', 'job_roles'], true)) {
                $out[$key] = $new;
                continue;
            }
            $max = match ($key) {
                'bio' => 2000,
                'extra_callsigns', 'nicknames' => 800,
                'motto', 'languages', 'operator_tags', 'weapon_specialty', 'kit_assigned', 'vehicle_authorized' => 255,
                'operator_status', 'rp_operational_function' => 160,
                'callsign', 'nickname_primary', 'rank_display', 'rank_display_override', 'first_name', 'last_name' => 120,
                'grade_id' => 12,
                default => 150,
            };
            $out[$key] = mb_substr($new, 0, $max);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyApprovedPayload(int $tenantId, int $targetUserId, array $payload): void
    {
        if (isset($payload['weight_kg']) && $payload['weight_kg'] !== '' && $payload['weight_kg'] !== null) {
            $payload['weight_kg'] = max(20, min(300, (int) $payload['weight_kg']));
        } elseif (array_key_exists('weight_kg', $payload) && ($payload['weight_kg'] === '' || $payload['weight_kg'] === null)) {
            $payload['weight_kg'] = null;
        }

        $userProfilePatch = [];
        $personnelPatch = [];
        foreach ($payload as $key => $value) {
            if ($key !== 'weight_kg' && $value === '' && !in_array($key, ['unit_assignments', 'job_roles', 'grade_id'], true)) {
                $value = null;
            }
            if ($key === 'grade_id') {
                $gid = (int) $value;
                $this->userRepository->update($targetUserId, $tenantId, [
                    'grade_id' => $gid > 0 ? $gid : null,
                ]);
                continue;
            }
            if ($key === 'unit_assignments') {
                $this->applyUnitAssignments($targetUserId, $value);
                continue;
            }
            if ($key === 'job_roles') {
                $this->applyJobRoles($tenantId, $targetUserId, $value);
                continue;
            }
            if (in_array($key, self::USER_PROFILE_KEYS, true)) {
                $userProfilePatch[$key] = $value;
                continue;
            }
            if (isset(self::LINE_LIST_COLUMNS[$key])) {
                $lines = ($value === null || $value === '')
                    ? []
                    : (preg_split('/\r\n|\r|\n/', (string) $value) ?: []);
                if ($key === 'extra_callsigns' && function_exists('personnel_normalize_extra_callsigns')) {
                    $lines = personnel_normalize_extra_callsigns($lines, null, 5, 100);
                } else {
                    $clean = [];
                    foreach ($lines as $line) {
                        $line = mb_substr(trim((string) $line), 0, 120);
                        if ($line !== '' && !in_array($line, $clean, true)) {
                            $clean[] = $line;
                        }
                    }
                    $lines = array_slice($clean, 0, 12);
                }
                $personnelPatch[self::LINE_LIST_COLUMNS[$key]] = $lines === []
                    ? null
                    : json_encode($lines, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                continue;
            }
            $personnelPatch[$key] = $value;
        }

        if ($userProfilePatch !== []) {
            $this->userProfiles->ensureRow($targetUserId);
            $this->userProfiles->upsert($targetUserId, $userProfilePatch);
            if (isset($userProfilePatch['first_name']) || isset($userProfilePatch['last_name'])) {
                $current = $this->userProfiles->getByUserId($targetUserId) ?? [];
                $derived = trim(
                    (string) ($current['first_name'] ?? '') . ' ' . (string) ($current['last_name'] ?? '')
                );
                if ($derived !== '') {
                    $this->profiles->update($targetUserId, ['character_name' => $derived]);
                    $this->userRepository->update($targetUserId, $tenantId, ['display_name' => $derived]);
                }
            }
        }

        if (array_key_exists('callsign', $personnelPatch)) {
            $cs = trim((string) ($personnelPatch['callsign'] ?? ''));
            $this->userRepository->update($targetUserId, $tenantId, [
                'callsign' => $cs !== '' ? $cs : null,
            ]);
        }

        if ($personnelPatch !== []) {
            $this->profiles->update($targetUserId, $personnelPatch);
        }
    }

    /** @return array<string, mixed> */
    private static function fieldMeta(string $key): array
    {
        return match ($key) {
            'bio' => ['type' => 'textarea', 'group' => 'identity', 'span' => 2, 'rows' => 3],
            'extra_callsigns' => [
                'type' => 'textarea',
                'group' => 'identity',
                'span' => 2,
                'rows' => 2,
                'help' => 'Un indicatif par ligne.',
            ],
            'nicknames' => [
                'type' => 'textarea',
                'group' => 'identity',
                'span' => 2,
                'rows' => 2,
                'help' => 'Un surnom par ligne.',
            ],
            'motto' => ['type' => 'text', 'group' => 'identity', 'span' => 2],
            'first_name', 'last_name', 'callsign', 'nickname_primary' => ['type' => 'text', 'group' => 'identity'],
            'blood_type' => ['type' => 'select', 'group' => 'details', 'choices' => 'blood_type'],
            'sex' => ['type' => 'select', 'group' => 'details', 'choices' => 'sex'],
            'family_situation' => ['type' => 'select', 'group' => 'details', 'choices' => 'family_situation'],
            'operator_status' => ['type' => 'select', 'group' => 'details', 'choices' => 'operator_status'],
            'weight_kg' => ['type' => 'number', 'group' => 'details', 'min' => 20, 'max_num' => 300],
            'languages', 'nationality', 'birth_place' => ['type' => 'text', 'group' => 'details'],
            'operator_tags' => ['type' => 'text', 'group' => 'details', 'span' => 2],
            'enlistment_date' => ['type' => 'date', 'group' => 'assignment'],
            'rp_medical_due_date' => ['type' => 'date', 'group' => 'engagement'],
            'rp_operational_function' => ['type' => 'text', 'group' => 'engagement'],
            'rank_display', 'rank_display_override' => ['type' => 'text', 'group' => 'assignment'],
            'grade_id' => ['type' => 'select', 'group' => 'assignment', 'choices' => 'grade_id'],
            'unit_assignments' => [
                'type' => 'unit_assignments',
                'group' => 'assignment',
                'span' => 2,
                'help' => 'Unité principale et rôle dans l’unité. Les affectations complémentaires se proposent depuis le dossier.',
            ],
            'job_roles' => [
                'type' => 'job_roles',
                'group' => 'assignment',
                'span' => 2,
                'help' => 'Emploi principal du référentiel. Les emplois complémentaires se proposent depuis le dossier.',
            ],
            'weapon_specialty' => ['type' => 'text', 'group' => 'equipment', 'span' => 2],
            default => ['type' => 'text', 'group' => 'equipment'],
        };
    }

    /**
     * @param list<string> $values
     * @return list<array{value: string, label: string}>
     */
    private static function choicePairs(array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            $out[] = ['value' => $value, 'label' => $value];
        }

        return $out;
    }

    private function normalizeIncomingValue(string $key, mixed $raw): string
    {
        if ($key === 'unit_assignments') {
            return self::canonicalizeUnitAssignments(self::decodeAssignmentRows($raw));
        }
        if ($key === 'job_roles') {
            return self::canonicalizeJobRoles(self::decodeJobRoleRows($raw));
        }
        if ($key === 'grade_id') {
            $gid = (int) $raw;

            return $gid > 0 ? (string) $gid : '';
        }
        if (is_array($raw)) {
            $lines = [];
            foreach ($raw as $item) {
                $item = trim((string) $item);
                if ($item !== '') {
                    $lines[] = $item;
                }
            }
            $raw = implode("\n", $lines);
        }
        $new = trim((string) $raw);
        if ($key === 'weight_kg' && $new !== '') {
            return (string) max(20, min(300, (int) $new));
        }
        if ($key === 'extra_callsigns' || $key === 'nicknames') {
            $parts = preg_split('/\r\n|\r|\n/', $new) ?: [];
            $clean = [];
            foreach ($parts as $line) {
                $line = trim((string) $line);
                if ($line !== '' && !in_array($line, $clean, true)) {
                    $clean[] = $line;
                }
            }

            return implode("\n", $clean);
        }

        return $new;
    }

    private function jsonListToLines(mixed $json): string
    {
        if (is_array($json)) {
            $raw = $json;
        } elseif (is_string($json) && $json !== '') {
            $decoded = json_decode($json, true);
            $raw = is_array($decoded) ? $decoded : [];
        } else {
            $raw = [];
        }
        $items = [];
        foreach ($raw as $item) {
            $item = trim((string) $item);
            if ($item !== '' && !in_array($item, $items, true)) {
                $items[] = $item;
            }
        }

        return implode("\n", $items);
    }

    /**
     * @param array<string, mixed> $targetUser
     * @param array<string, mixed> $proposed
     * @param array<string, mixed> $before
     * @param list<array{user_id: int, name: string, email: string}> $recipients
     */
    private function notifySubmitted(
        int $tenantId,
        int $requestId,
        array $targetUser,
        int $requesterUserId,
        array $proposed,
        array $before,
        string $note,
        array $recipients
    ): void {
        $tenant = $this->tenantRepository->findById($tenantId);
        $tenantName = $tenant ? (string) ($tenant['name'] ?? 'Communauté') : 'Communauté';
        $requester = $this->userRepository->findById($requesterUserId, $tenantId) ?? [];
        $targetName = $this->displayName($targetUser);
        $requesterName = $this->displayName($requester);
        $requesterEmail = trim((string) ($requester['email'] ?? ''));
        $diffLines = $this->formatDiffLines($proposed, $before, $tenantId);
        $queueUrl = url('back-office/personnel/corrections');
        $ficheUrl = url('personnel/' . (int) ($targetUser['id'] ?? 0));

        foreach ($recipients as $recipient) {
            if (!$this->wantsEmail((int) $recipient['user_id'], EmailEvents::PERSONNEL_CORRECTION_REQUEST_STAFF)) {
                continue;
            }
            $this->emailService->sendPersonnelCorrectionRequestStaff(
                $recipient['email'],
                $recipient['name'],
                $requesterName,
                $requesterEmail,
                $tenantName,
                $targetName,
                $diffLines,
                $note,
                $queueUrl,
                $ficheUrl,
                $tenantId
            );
        }

        $memberEmail = strtolower(trim((string) ($targetUser['email'] ?? '')));
        if ($memberEmail !== '' && filter_var($memberEmail, FILTER_VALIDATE_EMAIL)
            && $this->wantsEmail((int) ($targetUser['id'] ?? 0), EmailEvents::PERSONNEL_CORRECTION_REQUEST_MEMBER)
        ) {
            $this->emailService->sendPersonnelCorrectionRequestMember(
                $memberEmail,
                $targetName,
                $tenantName,
                $diffLines,
                $note,
                $ficheUrl,
                $tenantId
            );
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function notifyDecision(
        int $tenantId,
        array $row,
        string $decision,
        int $resolverUserId,
        string $resolutionNote
    ): void {
        $tenant = $this->tenantRepository->findById($tenantId);
        $tenantName = $tenant ? (string) ($tenant['name'] ?? 'Communauté') : 'Communauté';
        $target = $this->userRepository->findById((int) $row['target_user_id'], $tenantId);
        $requester = $this->userRepository->findById((int) $row['requested_by'], $tenantId);
        $resolver = $this->userRepository->findById($resolverUserId, $tenantId) ?? [];
        if (!$target) {
            return;
        }
        $targetName = $this->displayName($target);
        $resolverName = $this->displayName($resolver);
        $proposed = is_array($row['proposed'] ?? null) ? $row['proposed'] : [];
        $diffLines = $this->formatDiffLines($proposed, is_array($row['before'] ?? null) ? $row['before'] : [], $tenantId);
        $ficheUrl = url('personnel/' . (int) $target['id']);
        $decisionLabel = $decision === 'approved' ? 'confirmée' : 'refusée';

        $emails = [];
        $targetEmail = strtolower(trim((string) ($target['email'] ?? '')));
        if ($targetEmail !== '') {
            $emails[$targetEmail] = [
                'user_id' => (int) $target['id'],
                'name' => $targetName,
            ];
        }
        if ($requester && (int) ($requester['id'] ?? 0) !== (int) ($target['id'] ?? 0)) {
            $reqEmail = strtolower(trim((string) ($requester['email'] ?? '')));
            if ($reqEmail !== '') {
                $emails[$reqEmail] = [
                    'user_id' => (int) $requester['id'],
                    'name' => $this->displayName($requester),
                ];
            }
        }
        // Récap aussi au validateur
        $resolverEmail = strtolower(trim((string) ($resolver['email'] ?? '')));
        if ($resolverEmail !== '') {
            $emails[$resolverEmail] = [
                'user_id' => $resolverUserId,
                'name' => $resolverName,
            ];
        }

        foreach ($emails as $email => $meta) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if (!$this->wantsEmail((int) $meta['user_id'], EmailEvents::PERSONNEL_CORRECTION_DECISION)) {
                continue;
            }
            $this->emailService->sendPersonnelCorrectionDecision(
                $email,
                (string) $meta['name'],
                $tenantName,
                $targetName,
                $decisionLabel,
                $resolverName,
                $diffLines,
                $resolutionNote,
                $ficheUrl,
                $tenantId
            );
        }
    }

    /**
     * @param array<string, mixed> $proposed
     * @param array<string, mixed> $before
     * @return list<string>
     */
    private function formatDiffLines(array $proposed, array $before, ?int $tenantId = null): array
    {
        $lines = [];
        foreach ($proposed as $key => $newVal) {
            $label = self::CORRECTABLE_FIELDS[$key] ?? $key;
            $old = array_key_exists($key, $before) ? $this->displayFieldValue((string) $key, $before[$key], $tenantId) : '';
            $new = $this->displayFieldValue((string) $key, $newVal, $tenantId);
            $lines[] = $label . ' : « ' . ($old !== '' ? $old : '—') . ' » → « ' . ($new !== '' ? $new : '—') . ' »';
        }

        return $lines;
    }

    public function displayFieldValue(string $key, mixed $value, ?int $tenantId = null): string
    {
        if ($key === 'grade_id') {
            $gid = (int) $value;
            if ($gid < 1) {
                return '';
            }
            $grade = $this->grades->findById($gid);
            if (!$grade) {
                return 'Grade #' . $gid;
            }

            return trim((string) ($grade['label_long'] ?? $grade['label_short'] ?? $grade['name'] ?? ('Grade #' . $gid)));
        }
        if ($key === 'unit_assignments') {
            return $this->formatUnitAssignmentsLabel(self::decodeAssignmentRows($value));
        }
        if ($key === 'job_roles') {
            return $this->formatJobRolesLabel(self::decodeJobRoleRows($value), $tenantId);
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        return trim((string) preg_replace('/\s+/u', ' ', str_replace(["\r\n", "\r", "\n"], ' · ', $text)));
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public static function canonicalizeUnitAssignments(array $rows): string
    {
        $norm = [];
        $seen = [];
        foreach ($rows as $row) {
            $unitId = (int) ($row['unit_id'] ?? 0);
            if ($unitId < 1) {
                continue;
            }
            if (isset($seen[$unitId])) {
                $idx = $seen[$unitId];
                if (!empty($row['is_primary'])) {
                    $norm[$idx]['is_primary'] = 1;
                }
                continue;
            }
            $seen[$unitId] = count($norm);
            $norm[] = [
                'unit_id' => $unitId,
                'role_name' => mb_substr(trim((string) ($row['role_name'] ?? '')), 0, 120),
                'is_primary' => !empty($row['is_primary']) ? 1 : 0,
            ];
        }
        usort($norm, static function (array $a, array $b): int {
            if ((int) $a['is_primary'] !== (int) $b['is_primary']) {
                return (int) $b['is_primary'] <=> (int) $a['is_primary'];
            }

            return (int) $a['unit_id'] <=> (int) $b['unit_id'];
        });
        if ($norm !== []) {
            $hasPrimary = false;
            foreach ($norm as $row) {
                if (!empty($row['is_primary'])) {
                    $hasPrimary = true;
                    break;
                }
            }
            if (!$hasPrimary) {
                $norm[0]['is_primary'] = 1;
            }
        }

        return json_encode($norm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public static function canonicalizeJobRoles(array $rows): string
    {
        $norm = [];
        $seen = [];
        foreach ($rows as $row) {
            $roleId = (int) ($row['role_id'] ?? $row['personnel_job_role_id'] ?? 0);
            if ($roleId < 1) {
                continue;
            }
            if (isset($seen[$roleId])) {
                $idx = $seen[$roleId];
                if (!empty($row['is_primary'])) {
                    $norm[$idx]['is_primary'] = 1;
                }
                continue;
            }
            $seen[$roleId] = count($norm);
            $norm[] = [
                'role_id' => $roleId,
                'detail' => mb_substr(trim((string) ($row['detail'] ?? $row['role_detail'] ?? '')), 0, 150),
                'is_primary' => !empty($row['is_primary']) ? 1 : 0,
            ];
        }
        usort($norm, static function (array $a, array $b): int {
            if ((int) $a['is_primary'] !== (int) $b['is_primary']) {
                return (int) $b['is_primary'] <=> (int) $a['is_primary'];
            }

            return (int) $a['role_id'] <=> (int) $b['role_id'];
        });
        if ($norm !== []) {
            $hasPrimary = false;
            foreach ($norm as $row) {
                if (!empty($row['is_primary'])) {
                    $hasPrimary = true;
                    break;
                }
            }
            if (!$hasPrimary) {
                $norm[0]['is_primary'] = 1;
            }
        }

        return json_encode($norm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    /** @return list<array<string, mixed>> */
    public static function decodeAssignmentRows(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public static function decodeJobRoleRows(mixed $raw): array
    {
        return self::decodeAssignmentRows($raw);
    }

    /** @return list<array<string, mixed>> */
    private function currentUnitAssignmentRows(int $userId): array
    {
        $rows = [];
        foreach ($this->assignments->listActiveForUserResolved($userId) as $row) {
            $rows[] = [
                'unit_id' => (int) ($row['unit_id'] ?? 0),
                'role_name' => (string) ($row['role_name'] ?? ''),
                'is_primary' => !empty($row['is_primary']),
            ];
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function currentJobRoleRows(int $tenantId, int $userId): array
    {
        if ($tenantId < 1 || $userId < 1 || !$this->jobRoles->tablesExist() || !$this->jobRoles->pivotTableExists()) {
            return [];
        }
        $rows = [];
        foreach ($this->jobRoles->listPivotAssignmentsForUsers($tenantId, [$userId])[$userId] ?? [] as $row) {
            $rows[] = [
                'role_id' => (int) ($row['personnel_job_role_id'] ?? 0),
                'detail' => (string) ($row['role_detail'] ?? ''),
                'is_primary' => !empty($row['is_primary']),
            ];
        }

        return $rows;
    }

    private function applyUnitAssignments(int $userId, mixed $raw): void
    {
        $parsed = [];
        foreach (self::decodeAssignmentRows($raw) as $row) {
            $unitId = (int) ($row['unit_id'] ?? 0);
            if ($unitId < 1) {
                continue;
            }
            $parsed[] = [
                'unit_id' => $unitId,
                'role_name' => mb_substr(trim((string) ($row['role_name'] ?? '')), 0, 120),
                'is_primary' => !empty($row['is_primary']),
            ];
        }
        if ($parsed === []) {
            $this->assignments->replaceActiveAssignmentsFromDossier($userId, []);
            $this->profiles->update($userId, ['primary_unit_id' => null]);

            return;
        }
        $primaryId = null;
        foreach ($parsed as $i => $row) {
            if (!empty($row['is_primary']) && $primaryId === null) {
                $primaryId = (int) $row['unit_id'];
            } else {
                $parsed[$i]['is_primary'] = $primaryId !== null ? false : !empty($row['is_primary']);
            }
        }
        if ($primaryId === null) {
            $parsed[0]['is_primary'] = true;
            $primaryId = (int) $parsed[0]['unit_id'];
        }
        foreach ($parsed as &$row) {
            if (trim((string) $row['role_name']) === '') {
                $row['role_name'] = 'Membre';
            }
        }
        unset($row);
        $this->assignments->replaceActiveAssignmentsFromDossier($userId, $parsed);
        $this->profiles->update($userId, ['primary_unit_id' => $primaryId]);
    }

    private function applyJobRoles(int $tenantId, int $userId, mixed $raw): void
    {
        if (!$this->jobRoles->tablesExist() || !$this->jobRoles->pivotTableExists()) {
            return;
        }
        $rows = [];
        foreach (self::decodeJobRoleRows($raw) as $row) {
            $roleId = (int) ($row['role_id'] ?? $row['personnel_job_role_id'] ?? 0);
            if ($roleId < 1) {
                continue;
            }
            $rows[] = [
                'personnel_job_role_id' => $roleId,
                'role_detail' => trim((string) ($row['detail'] ?? $row['role_detail'] ?? '')),
                'is_primary' => !empty($row['is_primary']),
            ];
        }
        $this->jobRoles->replaceUserPivotJobRoles($tenantId, $userId, $rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function formatUnitAssignmentsLabel(array $rows): string
    {
        $parts = [];
        foreach ($rows as $row) {
            $unitId = (int) ($row['unit_id'] ?? 0);
            if ($unitId < 1) {
                continue;
            }
            $unit = $this->units->findById($unitId);
            $name = trim((string) ($unit['name'] ?? ''));
            if ($name === '') {
                $name = 'Unité #' . $unitId;
            }
            $role = trim((string) ($row['role_name'] ?? ''));
            $bit = $role !== '' ? $name . ' — ' . $role : $name;
            if (!empty($row['is_primary'])) {
                $bit .= ' (principale)';
            }
            $parts[] = $bit;
        }

        return implode(' · ', $parts);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function formatJobRolesLabel(array $rows, ?int $tenantId = null): string
    {
        $parts = [];
        foreach ($rows as $row) {
            $roleId = (int) ($row['role_id'] ?? $row['personnel_job_role_id'] ?? 0);
            if ($roleId < 1) {
                continue;
            }
            $jr = ($tenantId !== null && $tenantId > 0)
                ? $this->jobRoles->findRoleById($roleId, $tenantId)
                : null;
            $name = trim((string) ($jr['name'] ?? ''));
            if ($name === '') {
                $name = 'Emploi #' . $roleId;
            }
            $detail = trim((string) ($row['detail'] ?? $row['role_detail'] ?? ''));
            $bit = $detail !== '' ? $name . ' — ' . $detail : $name;
            if (!empty($row['is_primary'])) {
                $bit .= ' (principal)';
            }
            $parts[] = $bit;
        }

        return implode(' · ', $parts);
    }

    private function wantsEmail(int $userId, string $event): bool
    {
        if ($userId < 1) {
            return true;
        }
        try {
            return $this->notificationPreferences->isEmailEventEnabled($userId, $event);
        } catch (\Throwable) {
            return true;
        }
    }

    /** @param array<string, mixed> $user */
    private function displayName(array $user): string
    {
        $name = trim((string) ($user['display_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $email = trim((string) ($user['email'] ?? ''));

        return $email !== '' ? $email : 'Membre';
    }
}
