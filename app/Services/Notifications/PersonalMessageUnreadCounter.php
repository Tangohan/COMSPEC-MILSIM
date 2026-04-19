<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Core\Gate;
use App\Repositories\Courrier\CourrierDocumentNotificationRepository;
use App\Repositories\ForumNotificationRepository;
use App\Repositories\TenantMessageRepository;

/**
 * Compteurs non lus agrégés : forum, courrier (si habilitation), messagerie interne.
 */
final class PersonalMessageUnreadCounter
{
    public function __construct(
        private ForumNotificationRepository $forumNotifications,
        private CourrierDocumentNotificationRepository $courrierNotifications,
        private TenantMessageRepository $tenantMessages,
    ) {}

    /**
     * @return array{
     *   forum_unread: int,
     *   courrier_unread: int,
     *   tenant_messages_unread: int,
     *   total: int
     * }
     */
    public function countsForUser(int $tenantId, int $userId, Gate $gate): array
    {
        $forum = 0;
        $courrier = 0;
        $tenantMsgs = 0;

        if ($tenantId < 1 || $userId < 1) {
            return [
                'forum_unread' => 0,
                'courrier_unread' => 0,
                'tenant_messages_unread' => 0,
                'total' => 0,
            ];
        }

        try {
            if ($this->forumNotifications->tableExists()) {
                $forum = $this->forumNotifications->unreadCount($tenantId, $userId);
            }
        } catch (\Throwable) {
            $forum = 0;
        }

        try {
            if ($gate->allows('courrier.view') && $this->courrierNotifications->tableExists()) {
                $courrier = $this->courrierNotifications->countUnread($tenantId, $userId);
            }
        } catch (\Throwable) {
            $courrier = 0;
        }

        try {
            $tenantMsgs = $this->tenantMessages->unreadCountForUser($tenantId, $userId);
        } catch (\Throwable) {
            $tenantMsgs = 0;
        }

        $total = max(0, $forum) + max(0, $courrier) + max(0, $tenantMsgs);

        return [
            'forum_unread' => max(0, $forum),
            'courrier_unread' => max(0, $courrier),
            'tenant_messages_unread' => max(0, $tenantMsgs),
            'total' => $total,
        ];
    }
}
