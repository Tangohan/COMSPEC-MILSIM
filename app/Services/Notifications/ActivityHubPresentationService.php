<?php

declare(strict_types=1);

namespace App\Services\Notifications;

/**
 * Libellés et liens pour le centre d’activité (agrégation forum + courrier).
 */
final class ActivityHubPresentationService
{
    /**
     * @param array<string, mixed> $row
     * @return array{title: string, detail: string, href: string, unread: bool, at: string}
     */
    public function formatForumRow(array $row): array
    {
        $type = (string) ($row['type'] ?? '');
        $payload = [];
        if (!empty($row['payload_json'])) {
            $decoded = json_decode((string) $row['payload_json'], true);
            $payload = is_array($decoded) ? $decoded : [];
        }
        $unread = empty($row['read_at']);
        $at = (string) ($row['created_at'] ?? '');

        return match ($type) {
            'topic_reply' => [
                'title' => 'Réponse sur un sujet suivi',
                'detail' => trim((string) ($payload['title'] ?? 'Sujet du forum')),
                'href' => isset($payload['topic_id']) ? url('forum/topic/' . (int) $payload['topic_id']) : url('forum'),
                'unread' => $unread,
                'at' => $at,
            ],
            'moderation_alert' => [
                'title' => 'Contrôle automatique sur un message',
                'detail' => trim((string) ($payload['summary'] ?? 'Vérification requise.')),
                'href' => url('forum'),
                'unread' => $unread,
                'at' => $at,
            ],
            default => [
                'title' => 'Forum',
                'detail' => 'Notification',
                'href' => url('forum'),
                'unread' => $unread,
                'at' => $at,
            ],
        };
    }

    /**
     * @param array<string, mixed> $row
     * @return array{title: string, detail: string, href: string, unread: bool, at: string}
     */
    public function formatCourrierRow(array $row): array
    {
        $unread = empty($row['read_at']);
        $at = (string) ($row['created_at'] ?? '');
        $ref = trim((string) ($row['reference_number'] ?? ''));
        $subj = trim((string) ($row['subject'] ?? ''));
        $title = trim((string) ($row['title'] ?? ''));
        $detail = $subj !== '' ? $subj : ($title !== '' ? $title : 'Document du courrier interne');
        if ($ref !== '') {
            $detail = 'Réf. ' . $ref . ($detail !== '' ? ' — ' . $detail : '');
        }
        $docId = (int) ($row['document_id'] ?? 0);

        return [
            'title' => 'Courrier interne',
            'detail' => $detail,
            'href' => $docId > 0 ? url('courrier/read/' . $docId) : url('courrier'),
            'unread' => $unread,
            'at' => $at,
        ];
    }
}
