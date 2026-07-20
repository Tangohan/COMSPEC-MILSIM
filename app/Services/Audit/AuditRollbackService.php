<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Repositories\PlatformSettingsRepository;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Email\SecurityAlertService;
use App\Support\Audit\AuditFieldSnapshot;
use App\Support\Audit\AuditSnapshotPresenter;

/**
 * Restauration d’état à partir d’une entrée du journal (snapshots avant / après).
 * Uniquement pour les types où l’état « avant » est fiable et applicable en base.
 */
final class AuditRollbackService
{
    /** Clés meta / non restaurables sur un compte. */
    private const USER_SKIP_KEYS = [
        'connexion_mot_de_passe',
        'platform_directory',
        'password_hash',
        'password',
    ];

    /** @var list<string> */
    private const USER_ALLOWED_KEYS = [
        'email',
        'display_name',
        'callsign',
        'status',
        'grade_id',
        'nationality_code',
        'preferred_grade_format',
        'professional_category_code',
        'profile_slug',
    ];

    /** @var list<string> */
    private const PLATFORM_SETTINGS_KEYS = [
        'brief_member_access',
        'brief_member_closed_message',
    ];

    /** @var list<string> */
    private const SUBSCRIPTION_PLAN_KEYS = [
        'name',
        'sort_order',
        'features_json',
        'limits_json',
        'stripe_price_id_monthly',
        'stripe_price_id_yearly',
    ];

    public function __construct(
        private UserRepository $users,
        private PlatformSettingsRepository $platformSettings,
        private SubscriptionPlanRepository $subscriptionPlans,
        private TenantRepository $tenants,
        private AuditService $auditService,
        private SecurityAlertService $securityAlerts,
    ) {}

    /**
     * @param array<string, mixed> $row
     * @return array{
     *   can_rollback: bool,
     *   reason: string,
     *   summary: string,
     *   restore_fields: list<array{key: string, label: string, value: string}>
     * }
     */
    public function assess(array $row): array
    {
        $action = trim((string) ($row['action'] ?? ''));
        $empty = [
            'can_rollback' => false,
            'reason' => '',
            'summary' => '',
            'restore_fields' => [],
        ];

        if ($action === '' || $action === AuditAction::AUDIT_ROLLBACK || $action === AuditAction::AUDIT_ROLLBACK_ALERT) {
            return array_merge($empty, [
                'reason' => 'Cet événement n’est pas une modification d’état restaurable.',
            ]);
        }

        if (str_starts_with($action, 'auth.')
            || in_array($action, [
                'invitation.sent',
                'invitation.accepted',
                'invitation.revoked',
                'document_downloaded',
                'document_uploaded',
                'document_archived',
                'user_created',
                'user_deactivated',
                'user_left_community',
                'group_member_added',
                'group_member_removed',
                'role_assigned',
                'site_role.assigned',
                'site_role.revoked',
            ], true)
            || str_starts_with($action, 'deployment.')
            || str_starts_with($action, 'app_update.')
            || str_starts_with($action, 'moderation.')
            || $action === 'forum.moderation_action'
            || $action === 'security.event'
        ) {
            return array_merge($empty, [
                'reason' => $this->unsupportedReason($action),
            ]);
        }

        $before = $this->decodeMap(isset($row['old_value']) ? (string) $row['old_value'] : null);
        if ($before === []) {
            return array_merge($empty, [
                'reason' => 'Aucune entrée ne contient pas d’état « avant » exploitable. La restauration n’est pas possible.',
            ]);
        }

        return match ($action) {
            'user_updated', AuditAction::USER_STATUS_UPDATED => $this->assessUserRestore($row, $before),
            AuditAction::PLATFORM_SETTINGS_UPDATED => $this->assessPlatformSettingsRestore($before),
            AuditAction::SUBSCRIPTION_PLAN_UPDATED => $this->assessSubscriptionPlanRestore($row, $before),
            AuditAction::TENANT_PLAN_ASSIGNED => $this->assessTenantPlanRestore($row, $before),
            default => array_merge($empty, [
                'reason' => 'Ce type d’événement n’est pas encore pris en charge pour une restauration automatique. Utilisez les écrans métier concernés ou contactez un administrateur technique.',
            ]),
        };
    }

    /**
     * @param array<string, mixed> $row
     * @return array{ok: bool, message: string}
     */
    public function rollback(array $row, int $actorUserId): array
    {
        $assessment = $this->assess($row);
        if (!$assessment['can_rollback']) {
            return [
                'ok' => false,
                'message' => $assessment['reason'] !== ''
                    ? $assessment['reason']
                    : 'Restauration impossible pour cet événement.',
            ];
        }

        $action = trim((string) ($row['action'] ?? ''));
        $before = $this->decodeMap(isset($row['old_value']) ? (string) $row['old_value'] : null);
        $after = $this->decodeMap(isset($row['new_value']) ? (string) $row['new_value'] : null);
        $auditId = (int) ($row['id'] ?? 0);

        try {
            $applied = match ($action) {
                'user_updated', AuditAction::USER_STATUS_UPDATED => $this->applyUserRestore($row, $before),
                AuditAction::PLATFORM_SETTINGS_UPDATED => $this->applyPlatformSettingsRestore($before),
                AuditAction::SUBSCRIPTION_PLAN_UPDATED => $this->applySubscriptionPlanRestore($row, $before),
                AuditAction::TENANT_PLAN_ASSIGNED => $this->applyTenantPlanRestore($row, $before),
                default => false,
            };
        } catch (\Throwable) {
            return [
                'ok' => false,
                'message' => 'La restauration a échoué. Réessayez ou corrigez l’état depuis l’écran métier correspondant.',
            ];
        }

        if (!$applied) {
            return [
                'ok' => false,
                'message' => 'Aucune restauration n’a pas pu être appliquée (élément introuvable ou données incomplètes).',
            ];
        }

        $this->auditService->logChange(
            AuditAction::AUDIT_ROLLBACK,
            isset($row['tenant_id']) && $row['tenant_id'] !== null && $row['tenant_id'] !== ''
                ? (int) $row['tenant_id']
                : null,
            $actorUserId > 0 ? $actorUserId : null,
            'audit_log',
            $auditId > 0 ? $auditId : null,
            [
                'source_action' => $action,
                'source_audit_id' => $auditId,
                'restored' => $before,
            ],
            [
                'source_action' => $action,
                'source_audit_id' => $auditId,
                'previous_state' => $after,
                'result' => 'restored',
            ],
        );

        $this->securityAlerts->notify(
            'WARNING',
            'Restauration depuis le journal d’activité',
            $this->alertBody($row, $actorUserId, 'Une restauration d’état a été effectuée depuis le journal d’activité plateforme.'),
            isset($row['tenant_id']) ? (int) $row['tenant_id'] : null,
        );

        return [
            'ok' => true,
            'message' => 'État précédent restauré. Un événement a été ajouté au journal et les responsables sécurité ont été prévenus.',
        ];
    }

    /**
     * Alerte dédiée (sans restauration).
     *
     * @param array<string, mixed> $row
     * @return array{ok: bool, message: string}
     */
    public function alertStaff(array $row, int $actorUserId, string $note = ''): array
    {
        $auditId = (int) ($row['id'] ?? 0);
        $action = trim((string) ($row['action'] ?? ''));
        $note = trim(strip_tags($note));
        if (mb_strlen($note) > 500) {
            $note = mb_substr($note, 0, 500);
        }

        $body = $this->alertBody($row, $actorUserId, 'Une alerte a été envoyée depuis le détail d’un événement du journal d’activité.');
        if ($note !== '') {
            $body .= "\n\nMessage de l’opérateur :\n" . $note;
        }

        $this->securityAlerts->notify(
            'WARNING',
            'Alerte journal d’activité',
            $body,
            isset($row['tenant_id']) ? (int) $row['tenant_id'] : null,
        );

        $this->auditService->logChange(
            AuditAction::AUDIT_ROLLBACK_ALERT,
            isset($row['tenant_id']) && $row['tenant_id'] !== null && $row['tenant_id'] !== ''
                ? (int) $row['tenant_id']
                : null,
            $actorUserId > 0 ? $actorUserId : null,
            'audit_log',
            $auditId > 0 ? $auditId : null,
            [],
            [
                'source_action' => $action,
                'source_audit_id' => $auditId,
                'note' => $note !== '' ? $note : null,
                'result' => 'alert_sent',
            ],
        );

        return [
            'ok' => true,
            'message' => 'Alerte envoyée aux responsables configurés. Un événement a été ajouté au journal.',
        ];
    }

    private function unsupportedReason(string $action): string
    {
        if (str_starts_with($action, 'auth.')) {
            return 'Les événements de connexion ne modifient pas une donnée à restaurer.';
        }
        if (str_starts_with($action, 'moderation.') || $action === 'forum.moderation_action') {
            return 'Les mesures de modération se gèrent depuis l’écran de modération (levée de sanction), pas par restauration automatique.';
        }
        if (str_starts_with($action, 'deployment.') || str_starts_with($action, 'app_update.')) {
            return 'Les actions de déploiement se gèrent depuis le centre de déploiement, pas depuis ce journal.';
        }
        if (str_starts_with($action, 'invitation.')) {
            return 'Les invitations ne peuvent pas être « annulées » automatiquement depuis cet écran.';
        }
        if (in_array($action, ['role_assigned', 'site_role.assigned', 'site_role.revoked', 'group_member_added', 'group_member_removed'], true)) {
            return 'Les attributions de rôles ou de groupes se corrigent depuis la fiche membre ou les rôles, pas par restauration automatique.';
        }

        return 'Ce type d’événement ne permet pas une restauration sûre automatique.';
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $before
     * @return array{can_rollback: bool, reason: string, summary: string, restore_fields: list<array{key: string, label: string, value: string}>}
     */
    private function assessUserRestore(array $row, array $before): array
    {
        $entityId = (int) ($row['entity_id'] ?? 0);
        if ($entityId < 1) {
            return [
                'can_rollback' => false,
                'reason' => 'Le compte concerné n’est pas identifiable pour une restauration.',
                'summary' => '',
                'restore_fields' => [],
            ];
        }

        $payload = $this->filterUserRestorePayload($before);
        if ($payload === []) {
            $hasPasswordOnly = array_key_exists('connexion_mot_de_passe', $before);
            return [
                'can_rollback' => false,
                'reason' => $hasPasswordOnly
                    ? 'Le mot de passe ne peut pas être restauré depuis le journal (mesure de sécurité).'
                    : 'Aucune donnée de compte restaurable n’est présente dans l’état « avant ».',
                'summary' => '',
                'restore_fields' => [],
            ];
        }

        $fields = $this->fieldSummaries($payload);
        return [
            'can_rollback' => true,
            'reason' => '',
            'summary' => 'Restaurer les valeurs précédentes sur le compte concerné.',
            'restore_fields' => $fields,
        ];
    }

    /**
     * @param array<string, mixed> $before
     * @return array{can_rollback: bool, reason: string, summary: string, restore_fields: list<array{key: string, label: string, value: string}>}
     */
    private function assessPlatformSettingsRestore(array $before): array
    {
        $payload = [];
        foreach (self::PLATFORM_SETTINGS_KEYS as $k) {
            if (array_key_exists($k, $before)) {
                $payload[$k] = $before[$k];
            }
        }
        if ($payload === []) {
            return [
                'can_rollback' => false,
                'reason' => 'Aucun réglage plateforme restaurable n’est présent dans l’état « avant ».',
                'summary' => '',
                'restore_fields' => [],
            ];
        }
        if (!$this->platformSettings->tableExists()) {
            return [
                'can_rollback' => false,
                'reason' => 'Les réglages plateforme ne sont pas disponibles sur cette installation.',
                'summary' => '',
                'restore_fields' => [],
            ];
        }

        return [
            'can_rollback' => true,
            'reason' => '',
            'summary' => 'Restaurer les réglages brief précédents.',
            'restore_fields' => $this->fieldSummaries($payload),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $before
     * @return array{can_rollback: bool, reason: string, summary: string, restore_fields: list<array{key: string, label: string, value: string}>}
     */
    private function assessSubscriptionPlanRestore(array $row, array $before): array
    {
        $planId = (int) ($row['entity_id'] ?? 0);
        if ($planId < 1) {
            return [
                'can_rollback' => false,
                'reason' => 'La formule d’accès concernée n’est pas identifiable.',
                'summary' => '',
                'restore_fields' => [],
            ];
        }
        $payload = [];
        foreach (self::SUBSCRIPTION_PLAN_KEYS as $k) {
            if (array_key_exists($k, $before)) {
                $payload[$k] = $before[$k];
            }
        }
        if ($payload === []) {
            return [
                'can_rollback' => false,
                'reason' => 'Aucune entrée ne contient pas assez de données pour restaurer la formule.',
                'summary' => '',
                'restore_fields' => [],
            ];
        }

        return [
            'can_rollback' => true,
            'reason' => '',
            'summary' => 'Restaurer les valeurs précédentes de la formule d’accès.',
            'restore_fields' => $this->fieldSummaries($payload),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $before
     * @return array{can_rollback: bool, reason: string, summary: string, restore_fields: list<array{key: string, label: string, value: string}>}
     */
    private function assessTenantPlanRestore(array $row, array $before): array
    {
        $tenantId = (int) ($row['entity_id'] ?? ($row['tenant_id'] ?? 0));
        if ($tenantId < 2) {
            return [
                'can_rollback' => false,
                'reason' => 'La communauté concernée n’est pas identifiable pour une restauration.',
                'summary' => '',
                'restore_fields' => [],
            ];
        }
        $payload = [];
        foreach (['plan_slug', 'subscription_status'] as $k) {
            if (array_key_exists($k, $before) && (string) $before[$k] !== '') {
                $payload[$k] = $before[$k];
            }
        }
        if ($payload === [] || !isset($payload['plan_slug'], $payload['subscription_status'])) {
            return [
                'can_rollback' => false,
                'reason' => 'L’affectation de formule précédente est incomplète : restauration refusée.',
                'summary' => '',
                'restore_fields' => [],
            ];
        }

        return [
            'can_rollback' => true,
            'reason' => '',
            'summary' => 'Rétablir la formule d’accès précédente pour cette communauté.',
            'restore_fields' => $this->fieldSummaries($payload),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $before
     */
    private function applyUserRestore(array $row, array $before): bool
    {
        $entityId = (int) ($row['entity_id'] ?? 0);
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $payload = $this->filterUserRestorePayload($before);
        if ($entityId < 1 || $payload === []) {
            return false;
        }
        $user = $tenantId > 0 ? $this->users->findById($entityId, $tenantId) : null;
        if ($user === null) {
            $user = $this->users->findById($entityId, null);
        }
        if ($user === null) {
            return false;
        }
        $effectiveTenantId = (int) ($user['tenant_id'] ?? $tenantId);
        if ($effectiveTenantId < 1) {
            return false;
        }
        if (isset($payload['grade_id']) && $payload['grade_id'] !== null && $payload['grade_id'] !== '') {
            $payload['grade_id'] = (int) $payload['grade_id'];
        }

        return $this->users->update($entityId, $effectiveTenantId, $payload);
    }

    /**
     * @param array<string, mixed> $before
     */
    private function applyPlatformSettingsRestore(array $before): bool
    {
        $pairs = [];
        foreach (self::PLATFORM_SETTINGS_KEYS as $k) {
            if (!array_key_exists($k, $before)) {
                continue;
            }
            $v = $before[$k];
            if ($k === 'brief_member_access') {
                $pairs[$k] = ($v === true || $v === 1 || $v === '1' || $v === 'true') ? '1' : '0';
            } else {
                $pairs[$k] = (string) $v;
            }
        }
        if ($pairs === []) {
            return false;
        }
        $this->platformSettings->setMany($pairs);

        return true;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $before
     */
    private function applySubscriptionPlanRestore(array $row, array $before): bool
    {
        $planId = (int) ($row['entity_id'] ?? 0);
        if ($planId < 1) {
            return false;
        }
        $current = $this->subscriptionPlans->findById($planId);
        if ($current === null) {
            return false;
        }
        $data = [
            'name' => (string) ($before['name'] ?? $current['name'] ?? ''),
            'sort_order' => (int) ($before['sort_order'] ?? $current['sort_order'] ?? 0),
            'features_json' => array_key_exists('features_json', $before)
                ? ($before['features_json'] !== '' && $before['features_json'] !== null ? (string) $before['features_json'] : null)
                : ($current['features_json'] ?? null),
            'limits_json' => array_key_exists('limits_json', $before)
                ? ($before['limits_json'] !== '' && $before['limits_json'] !== null ? (string) $before['limits_json'] : null)
                : ($current['limits_json'] ?? null),
            'stripe_price_id_monthly' => array_key_exists('stripe_price_id_monthly', $before)
                ? ($before['stripe_price_id_monthly'] !== '' && $before['stripe_price_id_monthly'] !== null ? (string) $before['stripe_price_id_monthly'] : null)
                : ($current['stripe_price_id_monthly'] ?? null),
            'stripe_price_id_yearly' => array_key_exists('stripe_price_id_yearly', $before)
                ? ($before['stripe_price_id_yearly'] !== '' && $before['stripe_price_id_yearly'] !== null ? (string) $before['stripe_price_id_yearly'] : null)
                : ($current['stripe_price_id_yearly'] ?? null),
        ];
        if (trim($data['name']) === '') {
            return false;
        }

        return $this->subscriptionPlans->update($planId, $data);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $before
     */
    private function applyTenantPlanRestore(array $row, array $before): bool
    {
        $tenantId = (int) ($row['entity_id'] ?? ($row['tenant_id'] ?? 0));
        $planSlug = trim((string) ($before['plan_slug'] ?? ''));
        $status = trim((string) ($before['subscription_status'] ?? ''));
        if ($tenantId < 2 || $planSlug === '' || $status === '') {
            return false;
        }

        return $this->tenants->updatePlanAssignment($tenantId, $planSlug, $status);
    }

    /**
     * @param array<string, mixed> $before
     * @return array<string, mixed>
     */
    private function filterUserRestorePayload(array $before): array
    {
        $out = [];
        foreach (self::USER_ALLOWED_KEYS as $k) {
            if (!array_key_exists($k, $before)) {
                continue;
            }
            if (in_array($k, self::USER_SKIP_KEYS, true)) {
                continue;
            }
            $out[$k] = $before[$k];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array{key: string, label: string, value: string}>
     */
    private function fieldSummaries(array $payload): array
    {
        $rows = [];
        foreach ($payload as $k => $v) {
            $rows[] = [
                'key' => (string) $k,
                'label' => AuditSnapshotPresenter::fieldLabel((string) $k),
                'value' => AuditSnapshotPresenter::displayScalar($v, (string) $k),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMap(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }
        try {
            $v = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }
        if (!is_array($v)) {
            return [];
        }

        return AuditFieldSnapshot::stripSensitive(AuditSnapshotPresenter::flattenAssociative($v));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function alertBody(array $row, int $actorUserId, string $intro): string
    {
        $id = (int) ($row['id'] ?? 0);
        $action = trim((string) ($row['action'] ?? ''));
        $label = AuditActionLabel::toFrench($action);
        $when = (string) ($row['created_at'] ?? '');
        $tenant = trim((string) ($row['tenant_name'] ?? ''));
        $lines = [
            $intro,
            '',
            'Référence journal : ' . ($id > 0 ? (string) $id : '—'),
            'Événement d’origine : ' . $label,
            'Date de l’événement : ' . ($when !== '' ? $when : '—'),
            'Communauté : ' . ($tenant !== '' ? $tenant : 'Plateforme'),
            'Opérateur (n° compte) : ' . ($actorUserId > 0 ? (string) $actorUserId : '—'),
        ];

        return implode("\n", $lines);
    }
}
