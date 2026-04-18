<?php

declare(strict_types=1);

namespace App\Services\Notifications;

/**
 * Libellés et liens pour le centre d’activité (forum, courrier, messagerie interne).
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
            'forum_mention' => [
                'title' => 'Vous avez été cité sur le forum',
                'detail' => trim((string) ($payload['title'] ?? 'Sujet du forum')),
                'href' => (isset($payload['topic_id'], $payload['post_id']))
                    ? url('forum/topic/' . (int) $payload['topic_id'] . '?newpost=' . (int) $payload['post_id'] . '#post-' . (int) $payload['post_id'])
                    : (isset($payload['topic_id']) ? url('forum/topic/' . (int) $payload['topic_id']) : url('forum')),
                'unread' => $unread,
                'at' => $at,
            ],
            'interteam_coop_reply' => [
                'title' => 'Message sur un fil partagé (coopération)',
                'detail' => trim((string) ($payload['title'] ?? 'Sujet partagé')),
                'href' => (isset($payload['mission_slug'], $payload['topic_id']) && trim((string) $payload['mission_slug']) !== '')
                    ? url('forum/coop/' . rawurlencode(trim((string) $payload['mission_slug'])) . '/sujet/' . (int) $payload['topic_id'])
                    : url('forum'),
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
            'report_opened' => [
                'title' => 'Signalement à examiner',
                'detail' => trim((string) ($payload['summary'] ?? 'Un membre a signalé un contenu ou envoyé une demande.')),
                'href' => url('back-office/forum-moderation'),
                'unread' => $unread,
                'at' => $at,
            ],
            'report_closed' => [
                'title' => 'Votre signalement a été traité',
                'detail' => 'L’équipe de modération a clos votre demande.',
                'href' => url('activite'),
                'unread' => $unread,
                'at' => $at,
            ],
            'report_reopened' => [
                'title' => 'Signalement rouvert à traiter',
                'detail' => trim((string) ($payload['summary'] ?? 'Un dossier clos a été remis dans la file d’attente.')),
                'href' => url('back-office/forum-moderation'),
                'unread' => $unread,
                'at' => $at,
            ],
            'report_reopened_reporter' => [
                'title' => 'Votre signalement est à nouveau examiné',
                'detail' => 'L’équipe poursuit l’examen de votre demande.',
                'href' => url('activite'),
                'unread' => $unread,
                'at' => $at,
            ],
            'event_rsvp_change' => [
                'title' => 'Participation mise à jour',
                'detail' => trim((string) ($payload['participant'] ?? 'Un membre')) . ' — '
                    . trim((string) ($payload['status_label'] ?? ''))
                    . (isset($payload['title']) && trim((string) $payload['title']) !== ''
                        ? ' · ' . trim((string) $payload['title'])
                        : ''),
                'href' => url('evenements'),
                'unread' => $unread,
                'at' => $at,
            ],
            'cooperation_announcement' => [
                'title' => trim((string) ($payload['title'] ?? 'Coopération inter-unités')) !== ''
                    ? trim((string) $payload['title'])
                    : 'Coopération inter-unités',
                'detail' => trim((string) ($payload['detail'] ?? 'Nouvelle information sur un dossier de coopération.')),
                'href' => (isset($payload['href']) && trim((string) $payload['href']) !== '')
                    ? trim((string) $payload['href'])
                    : url('back-office/cooperation/missions'),
                'unread' => $unread,
                'at' => $at,
            ],
            'roleplay_followup' => [
                'title' => (($payload['recipient_role'] ?? '') === 'tutor')
                    ? 'Mise à jour du dossier tutoré (roleplay)'
                    : 'Mise à jour de votre suivi roleplay',
                'detail' => trim((string) ($payload['summary'] ?? '')) !== ''
                    ? trim((string) $payload['summary'])
                    : ('Dossier : ' . trim((string) ($payload['subject_label'] ?? 'Membre'))),
                'href' => (isset($payload['href']) && trim((string) $payload['href']) !== '')
                    ? trim((string) $payload['href'])
                    : url('personnel/me/edit'),
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

    /**
     * @param array<string, mixed> $row
     * @return array{title: string, detail: string, href: string, unread: bool, at: string}
     */
    public function formatTenantMessageThreadRow(array $row, int $currentUserId): array
    {
        $threadId = (int) ($row['id'] ?? 0);
        $subject = trim((string) ($row['subject'] ?? ''));
        $preview = trim((string) ($row['last_preview'] ?? ''));
        $lastSenderId = (int) ($row['last_sender_id'] ?? 0);
        $unread = !empty($row['thread_unread']);
        $at = (string) ($row['updated_at'] ?? '');

        $head = $subject !== '' ? $subject : 'Échange avec l’encadrement';
        if ($preview !== '') {
            if (function_exists('mb_strlen') && mb_strlen($preview) > 120) {
                $preview = mb_substr($preview, 0, 117) . '…';
            } elseif (strlen($preview) > 120) {
                $preview = substr($preview, 0, 117) . '…';
            }
        }
        $who = 'Quelqu’un';
        if ($lastSenderId > 0 && $lastSenderId === $currentUserId) {
            $who = 'Vous';
        }
        $detail = $preview !== '' ? ($who . ' : ' . $preview) : ($who . ' a mis à jour la conversation.');

        return [
            'title' => 'Messagerie interne',
            'detail' => $head . ' — ' . $detail,
            'href' => $threadId > 0 ? url('messages/' . $threadId) : url('messages'),
            'unread' => $unread,
            'at' => $at,
        ];
    }
}
