<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\PersonnelCorrectionRequestRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * Demandes de correction RH (bouton anomalie) → validation organisateur → mise à jour fiche.
 */
final class PersonnelCorrectionRequestService
{
    /**
     * Champs corrigibles par le membre (hors clearance / grade / rôles — circuit élévation).
     *
     * @var array<string, string>
     */
    public const CORRECTABLE_FIELDS = [
        'callsign' => 'Indicatif radio',
        'character_name' => 'Nom de personnage',
        'nickname_primary' => 'Surnom principal',
        'motto' => 'Devise',
        'languages' => 'Langues',
        'nationality' => 'Nationalité (RP)',
        'blood_type' => 'Groupe sanguin',
        'birth_place' => 'Lieu de naissance',
        'sex' => 'Sexe',
        'family_situation' => 'Situation familiale',
        'weight_kg' => 'Poids (kg)',
        'equipment_class' => 'Classe d’équipement',
        'kit_assigned' => 'Kit attribué',
        'radio_assigned' => 'Radio attribuée',
        'vehicle_authorized' => 'Véhicule autorisé',
        'weapon_specialty' => 'Spécialité arme',
        'operator_status' => 'Statut opérateur',
        'operator_tags' => 'Tags opérateur',
        'rank_display' => 'Rang affiché',
        'enlistment_date' => 'Date d’engagement',
    ];

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
    ) {
    }

    /** @return array<string, string> */
    public static function fieldLabels(): array
    {
        return self::CORRECTABLE_FIELDS;
    }

    /**
     * @return array<string, mixed>
     */
    public function currentSnapshot(int $userId): array
    {
        $profile = $this->profiles->getByUserId($userId) ?? [];
        $out = [];
        foreach (array_keys(self::CORRECTABLE_FIELDS) as $key) {
            $val = $profile[$key] ?? null;
            if ($val === null) {
                $out[$key] = '';
            } else {
                $out[$key] = is_scalar($val) ? (string) $val : '';
            }
        }

        return $out;
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
            $payload = $this->normalizeProposed($proposed, $this->currentSnapshot((int) $row['target_user_id']));
            if ($payload === []) {
                return ['ok' => false, 'message' => 'Aucune champ valide à appliquer.'];
            }
            if (isset($payload['weight_kg']) && $payload['weight_kg'] !== '' && $payload['weight_kg'] !== null) {
                $payload['weight_kg'] = max(20, min(300, (int) $payload['weight_kg']));
            } elseif (array_key_exists('weight_kg', $payload) && ($payload['weight_kg'] === '' || $payload['weight_kg'] === null)) {
                $payload['weight_kg'] = null;
            }
            foreach ($payload as $k => $v) {
                if ($k === 'weight_kg') {
                    continue;
                }
                if ($v === '') {
                    $payload[$k] = null;
                }
            }
            $this->profiles->update((int) $row['target_user_id'], $payload);
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
            $new = is_scalar($raw[$key]) ? trim((string) $raw[$key]) : '';
            if ($key === 'weight_kg' && $new !== '') {
                $new = (string) max(20, min(300, (int) $new));
            }
            if ($key === 'enlistment_date' && $new !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $new)) {
                continue;
            }
            $old = isset($before[$key]) ? trim((string) $before[$key]) : '';
            if ($new === $old) {
                continue;
            }
            $max = match ($key) {
                'motto', 'languages', 'operator_tags', 'weapon_specialty', 'kit_assigned' => 255,
                'operator_status' => 160,
                'character_name', 'callsign', 'nickname_primary', 'rank_display' => 120,
                default => 150,
            };
            $out[$key] = mb_substr($new, 0, $max);
        }

        return $out;
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
        $diffLines = $this->formatDiffLines($proposed, $before);
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
        $diffLines = $this->formatDiffLines($proposed, is_array($row['before'] ?? null) ? $row['before'] : []);
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
    private function formatDiffLines(array $proposed, array $before): array
    {
        $lines = [];
        foreach ($proposed as $key => $newVal) {
            $label = self::CORRECTABLE_FIELDS[$key] ?? $key;
            $old = array_key_exists($key, $before) ? trim((string) $before[$key]) : '';
            $new = trim((string) $newVal);
            $lines[] = $label . ' : « ' . ($old !== '' ? $old : '—') . ' » → « ' . ($new !== '' ? $new : '—') . ' »';
        }

        return $lines;
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
