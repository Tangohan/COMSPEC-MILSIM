<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Repositories\TenantMessageRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * E-mails aux participants d’un fil lorsqu’un nouveau message est publié (hors expéditeur).
 */
final class TenantInternalMessageNotificationService
{
    public function __construct(
        private EmailService $emailService,
        private UserRepository $userRepository,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository,
        private TenantMessageRepository $messageRepository,
        private TenantRepository $tenantRepository,
    ) {}

    public function notifyAfterMessage(int $tenantId, int $threadId, int $senderUserId, string $body): void
    {
        if ($tenantId < 1 || $threadId < 1 || $senderUserId < 1) {
            return;
        }
        $tenant = $this->tenantRepository->findById($tenantId);
        $tenantName = $tenant ? (string) ($tenant['name'] ?? 'Communauté') : 'Communauté';
        if (function_exists('community_display_name') && $tenant) {
            $tenantName = community_display_name($tenant);
        }

        $sender = $this->userRepository->findById($senderUserId, $tenantId);
        $senderLabel = trim((string) ($sender['display_name'] ?? ''));
        if ($senderLabel === '') {
            $senderLabel = trim((string) ($sender['email'] ?? '')) ?: 'Un membre';
        }

        $preview = $this->truncateLine($body, 200);

        $participantIds = $this->messageRepository->listParticipantUserIds($threadId);
        foreach ($participantIds as $uid) {
            if ($uid < 1 || $uid === $senderUserId) {
                continue;
            }
            if (!$this->notificationPreferencesRepository->isEmailEventEnabled($uid, EmailEvents::TENANT_INTERNAL_MESSAGE_THREAD)) {
                continue;
            }
            $recipient = $this->userRepository->findById($uid, $tenantId);
            if (!$recipient) {
                continue;
            }
            $email = strtolower(trim((string) ($recipient['email'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $name = trim((string) ($recipient['display_name'] ?? ''));
            if ($name === '') {
                $name = $email;
            }
            try {
                $this->emailService->sendTenantInternalMessageThread(
                    $email,
                    $name,
                    $tenantName,
                    $senderLabel,
                    $preview,
                    $threadId,
                    $tenantId
                );
            } catch (\Throwable) {
            }
        }
    }

    private function truncateLine(string $text, int $max): string
    {
        $t = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
        if (function_exists('mb_strlen') && mb_strlen($t) > $max) {
            return mb_substr($t, 0, $max - 1) . '…';
        }
        if (strlen($t) > $max) {
            return substr($t, 0, $max - 1) . '…';
        }

        return $t;
    }
}
