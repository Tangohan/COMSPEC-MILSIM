<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Repositories\TenantCommunityFeedRepository;
use App\Repositories\TenantRepository;
use App\Repositories\TrainingStaffPingRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * Alertes RH effectifs — demande d’élévation (grade / rôle / droits) aux personnes habilitées.
 */
class EffectifsStaffAlertService
{
    private const ELEVATION_COOLDOWN_SEC = 86400;

    private const ELEVATION_PING_KIND = 'effectifs_elevation';

    /**
     * Personnes pouvant traiter une élévation RH dans **cette communauté** (hors demandeur).
     * Permissions communauté uniquement — pas admin.system (couche site / cross-tenant).
     *
     * @var list<string>
     */
    private const ELEVATION_PERMISSION_SLUGS = [
        'admin.access',
        'admin.organization',
        'admin.roles.manage',
        'personnel.grades.manage',
        'personnel.assignments.manage',
        'personnel.status.manage',
    ];

    /** @var array<string, string> */
    public const ELEVATION_KIND_LABELS = [
        'grade' => 'Grade',
        'role' => 'Rôle',
        'droits' => 'Droits d’accès',
        'general' => 'Situation RH',
    ];

    public function __construct(
        private TenantCommunityFeedRepository $feedRepository,
        private TrainingStaffPingRepository $pingRepository,
        private EmailService $emailService,
        private UserRepository $userRepository,
        private TenantRepository $tenantRepository,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository,
    ) {}

    /** Null = une demande peut être envoyée ; sinon secondes restantes. */
    public function secondsBeforeNextElevationRequest(int $targetUserId, int $requesterUserId): ?int
    {
        if ($targetUserId < 1 || $requesterUserId < 1) {
            return null;
        }
        $since = $this->pingRepository->secondsSinceLastPing(
            $targetUserId,
            $requesterUserId,
            self::ELEVATION_PING_KIND
        );
        if ($since === null) {
            return null;
        }
        if ($since >= self::ELEVATION_COOLDOWN_SEC) {
            return null;
        }

        return self::ELEVATION_COOLDOWN_SEC - $since;
    }

    /**
     * Destinataires d’une demande d’élévation : uniquement les comptes actifs du tenant donné
     * (jamais la liste globale d’e-mails plateforme).
     *
     * @return list<array{user_id: int, name: string, email: string}>
     */
    public function listElevationRecipients(int $tenantId, ?int $excludeUserId = null): array
    {
        if ($tenantId < 1) {
            return [];
        }
        // Filtre tenant strict dans le dépôt (users + roles + permissions du même tenant_id).
        $ids = $this->userRepository->listActiveUserIdsWithAnyPermissionSlug(
            $tenantId,
            self::ELEVATION_PERMISSION_SLUGS
        );
        if ($excludeUserId !== null && $excludeUserId > 0) {
            $ids = array_values(array_filter($ids, static fn (int $id): bool => $id !== $excludeUserId));
        }
        if ($ids === []) {
            return [];
        }
        // Seconde barrière : ignore tout id hors communauté (défense en profondeur).
        $users = $this->userRepository->findByIdsForTenant($tenantId, $ids);
        $out = [];
        $seenEmails = [];
        foreach ($ids as $uid) {
            $user = $users[$uid] ?? null;
            if (!$user || (int) ($user['tenant_id'] ?? 0) !== $tenantId) {
                continue;
            }
            $email = strtolower(trim((string) ($user['email'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seenEmails[$email])) {
                continue;
            }
            $seenEmails[$email] = true;
            $out[] = [
                'user_id' => $uid,
                'name' => $this->displayNameForUser($user),
                'email' => $email,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $targetUser
     * @return array{ok: bool, message: string, recipient_names: list<string>}
     */
    public function requestElevation(
        int $tenantId,
        int $requesterUserId,
        array $targetUser,
        string $kind,
        string $note = ''
    ): array {
        $targetId = (int) ($targetUser['id'] ?? 0);
        $kind = array_key_exists($kind, self::ELEVATION_KIND_LABELS) ? $kind : 'general';
        $kindLabel = self::ELEVATION_KIND_LABELS[$kind];
        $note = trim($note);
        if (mb_strlen($note) > 500) {
            $note = mb_substr($note, 0, 500);
        }

        if ($tenantId < 1 || $requesterUserId < 1 || $targetId < 1) {
            return [
                'ok' => false,
                'message' => 'Impossible d’envoyer la demande pour le moment.',
                'recipient_names' => [],
            ];
        }

        $wait = $this->secondsBeforeNextElevationRequest($targetId, $requesterUserId);
        if ($wait !== null) {
            $hours = max(1, (int) ceil($wait / 3600));

            return [
                'ok' => false,
                'message' => 'Une demande est déjà en cours pour ce membre. Vous pourrez renvoyer un rappel dans environ '
                    . $hours . ' heure' . ($hours > 1 ? 's' : '') . '.',
                'recipient_names' => [],
            ];
        }

        $recipients = $this->listElevationRecipients($tenantId, $requesterUserId);
        if ($recipients === []) {
            return [
                'ok' => false,
                'message' => 'Aucune personne habilitée n’est joignable dans cette communauté. Contactez un administrateur autrement.',
                'recipient_names' => [],
            ];
        }

        $requester = $this->userRepository->findById($requesterUserId, $tenantId);
        $requesterName = $this->displayNameForUser($requester);
        $requesterEmail = $requester ? strtolower(trim((string) ($requester['email'] ?? ''))) : '';
        $targetName = $this->displayNameForUser($targetUser);
        $tenant = $this->tenantRepository->findById($tenantId);
        $tenantName = 'Communauté';
        if ($tenant) {
            $tenantName = function_exists('community_display_name')
                ? community_display_name($tenant)
                : (string) ($tenant['name'] ?? 'Communauté');
        }

        $memberUrl = \effectifs_workspace_url('membres/' . $targetId);
        $editUrl = \url('back-office/users/' . $targetId . '/edit');
        $names = [];
        $sent = 0;

        try {
            foreach ($recipients as $r) {
                $names[] = $r['name'];
                $sid = (int) ($r['user_id'] ?? 0);
                if ($sid < 1 || !$this->notificationPreferencesRepository->isEmailEventEnabled($sid, EmailEvents::EFFECTIFS_ELEVATION_REQUEST)) {
                    continue;
                }
                if ($this->emailService->sendEffectifsElevationRequest(
                    $r['email'],
                    $r['name'],
                    $requesterName,
                    $requesterEmail,
                    $tenantName,
                    $targetName,
                    $kindLabel,
                    $note,
                    $memberUrl,
                    $editUrl,
                    $tenantId
                )) {
                    $sent++;
                }
            }

            $nameList = implode(', ', array_slice($names, 0, 8));
            if (count($names) > 8) {
                $nameList .= '…';
            }
            $body = $requesterName . ' demande une élévation (« ' . $kindLabel . ' ») pour ' . $targetName . '.';
            if ($note !== '') {
                $body .= ' Message : ' . $note;
            }
            $body .= ' Personnes prévenues : ' . $nameList . '.';
            $this->feedRepository->insert(
                $tenantId,
                'effectifs_elevation',
                'Élévation demandée — ' . $targetName,
                $body,
                $memberUrl,
                $requesterUserId
            );
            $this->pingRepository->log($tenantId, $targetId, $requesterUserId, self::ELEVATION_PING_KIND);

            if ($sent === 0) {
                return [
                    'ok' => true,
                    'message' => 'L’alerte a été déposée sur le tableau de bord. Aucun e-mail n’a pu être envoyé (préférences ou problème d’envoi) — les personnes listées restent prévenues via le fil communauté.',
                    'recipient_names' => $names,
                ];
            }

            return [
                'ok' => true,
                'message' => 'Demande envoyée à ' . count($names) . ' personne'
                    . (count($names) > 1 ? 's' : '')
                    . ' : ' . implode(', ', $names) . '.',
                'recipient_names' => $names,
            ];
        } catch (\Throwable) {
            return [
                'ok' => false,
                'message' => 'L’envoi de la demande a échoué. Réessayez plus tard ou contactez un administrateur.',
                'recipient_names' => [],
            ];
        }
    }

    /** @param array<string, mixed>|null $user */
    private function displayNameForUser(?array $user): string
    {
        if ($user === null) {
            return 'Membre';
        }
        $display = trim((string) ($user['display_name'] ?? ''));
        if ($display !== '') {
            return $display;
        }
        $callsign = trim((string) ($user['callsign'] ?? ''));
        if ($callsign !== '') {
            return $callsign;
        }
        $email = trim((string) ($user['email'] ?? ''));

        return $email !== '' ? $email : 'Membre';
    }
}
