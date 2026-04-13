<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Core\Gate;
use App\Repositories\TenantEmailCampaignRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Services\EmailService;
use App\Support\TenantEmailKind;

/**
 * Vérifie les droits, préférences et envoie (file ou direct) les e-mails d’une campagne.
 */
final class TenantEmailDispatchService
{
    public function __construct(
        private TenantEmailRecipientResolver $recipientResolver,
        private TenantEmailRenderService $renderService,
        private TenantEmailCampaignRepository $campaignRepository,
        private UserRepository $userRepository,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository,
        private EmailService $emailService
    ) {}

    /**
     * @param array<string, mixed> $groupDefinition
     * @return array{ok: bool, message: string, campaign_id?: int, queued?: int}
     */
    public function dispatch(
        int $tenantId,
        int $senderUserId,
        string $kind,
        string $subject,
        string $htmlBody,
        ?string $textBody,
        array $groupDefinition,
        ?int $templateId = null
    ): array {
        if (!TenantEmailKind::isValid($kind)) {
            return ['ok' => false, 'message' => 'Type de message non reconnu.'];
        }
        $perm = TenantEmailKind::permissionForKind($kind);
        if (!Gate::getInstance()->allows($perm)) {
            return ['ok' => false, 'message' => 'Vous n’avez pas l’habilitation pour ce type de message.'];
        }

        $userIds = $this->recipientResolver->resolveUserIds($tenantId, $groupDefinition);
        if ($userIds === []) {
            return ['ok' => false, 'message' => 'Aucun destinataire ne correspond aux critères choisis.'];
        }

        $prefKey = TenantEmailKind::notificationPreferenceKey($kind);
        $filtered = [];
        foreach ($userIds as $uid) {
            if ($this->notificationPreferencesRepository->isEmailEventEnabled((int) $uid, $prefKey)) {
                $filtered[] = (int) $uid;
            }
        }
        if ($filtered === []) {
            return ['ok' => false, 'message' => 'Tous les destinataires ont désactivé ce type de message dans leurs préférences.'];
        }

        $eventCode = TenantEmailKind::eventCode($kind);
        $campaignId = $this->campaignRepository->create(
            $tenantId,
            $kind,
            $templateId,
            mb_substr($subject, 0, 500),
            $senderUserId,
            count($filtered),
            'queued'
        );
        if ($campaignId < 1) {
            return ['ok' => false, 'message' => 'Enregistrement de l’envoi impossible (tables manquantes ?). Exécutez les migrations.'];
        }

        $queued = 0;
        $failed = 0;
        foreach ($filtered as $uid) {
            $user = $this->userRepository->findById($uid, $tenantId);
            if (!$user) {
                ++$failed;
                continue;
            }
            $email = trim((string) ($user['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                ++$failed;
                continue;
            }
            $rendered = $this->renderService->renderForUser($tenantId, $uid, $subject, $htmlBody, $textBody);
            $ok = $this->emailService->send(
                $eventCode,
                $email,
                $rendered['subject'],
                $this->wrapHtml($rendered['html']),
                $rendered['text'],
                $tenantId,
                null,
                [
                    'target_user_id' => $uid,
                    'tenant_email_kind' => $kind,
                    'campaign_id' => $campaignId,
                ],
                $campaignId
            );
            if ($ok) {
                ++$queued;
            } else {
                ++$failed;
            }
        }

        $status = $failed > 0 && $queued === 0 ? 'failed_partial' : 'completed';
        if ($queued > 0 && $failed > 0) {
            $status = 'failed_partial';
        }
        $this->campaignRepository->updateStatus($campaignId, $tenantId, $status, count($filtered));

        return [
            'ok' => $queued > 0,
            'message' => $queued > 0
                ? ($failed > 0
                    ? 'Envoi lancé : ' . $queued . ' message(s) pris en charge, ' . $failed . ' ignoré(s) ou en échec.'
                    : 'Envoi lancé : ' . $queued . ' message(s) pris en charge.')
                : 'Aucun message n’a pu être envoyé.',
            'campaign_id' => $campaignId,
            'queued' => $queued,
        ];
    }

    private function wrapHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '<p></p>';
        }
        if (stripos($html, '<html') !== false || stripos($html, '<body') !== false || stripos($html, '<p') !== false || stripos($html, '<div') !== false) {
            return $html;
        }

        return '<div>' . $html . '</div>';
    }
}
