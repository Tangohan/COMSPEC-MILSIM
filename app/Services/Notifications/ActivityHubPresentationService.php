<?php

declare(strict_types=1);

namespace App\Services\Notifications;

/**
 * Libellés et liens pour le centre d’activité (notifications membre, courrier, messagerie interne).
 */
final class ActivityHubPresentationService
{
    private static function fr(): bool
    {
        return !function_exists('locale') || locale() !== 'en';
    }

    /**
     * @param array<string, mixed> $row
     * @return array{title: string, detail: string, href: string, unread: bool, at: string}
     */
    public function formatNotificationRow(array $row): array
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
            'report_opened' => [
                'title' => 'Signalement à examiner',
                'detail' => trim((string) ($payload['summary'] ?? 'Un membre a signalé un contenu ou envoyé une demande.')),
                'href' => url('back-office/signalements'),
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
                'href' => url('back-office/signalements'),
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
            'sse_file_watch' => [
                'title' => 'Surveillance des fichiers : ' . mb_substr(trim((string) ($payload['label'] ?? 'nouvelle mention')), 0, 80),
                'detail' => mb_substr(trim((string) ($payload['detail'] ?? 'Un nouveau document cite ce que vous surveillez.')), 0, 240),
                'href' => is_string($payload['path'] ?? null) && preg_match('#^atak/sse/fichiers[A-Za-z0-9/_\-?=&]*$#', (string) $payload['path']) === 1
                    ? url((string) $payload['path']) : url('atak/sse/fichiers'),
                'unread' => $unread,
                'at' => $at,
            ],
            'steam_removed_by_owner' => [
                'title' => 'Compte Steam retiré de votre compte',
                'detail' => 'Ce compte Steam était aussi relié à un autre compte Athena, dont le titulaire a prouvé qu’il lui appartient en se connectant à Steam. S’il est bien à vous, reliez-le de nouveau avec « Se connecter avec Steam ».',
                'href' => url('account/preferences') . '#section-liaisons',
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
            'academy_auto_validated' => [
                'title' => self::fr() ? 'Chapitre validé automatiquement par Athena' : 'Chapter automatically validated by Athena',
                'detail' => sprintf(
                    !empty($payload['final'])
                        ? (self::fr() ? '« %s », chapitre %d (dernier) : attestation et qualifications attribuées automatiquement par Athena après %d h sans réponse du formateur. Un formateur peut annuler cette attribution.'
                            : '"%s", chapter %d (last): certificate and qualifications automatically granted by Athena after %d h without a trainer response. A trainer can cancel this grant.')
                        : (self::fr() ? '« %s », chapitre %d : validé automatiquement par Athena après %d h sans réponse du formateur. Un formateur peut annuler cette validation.'
                            : '"%s", chapter %d: automatically validated by Athena after %d h without a trainer response. A trainer can cancel this validation.'),
                    trim((string) ($payload['course'] ?? '')),
                    (int) ($payload['chapter'] ?? 0),
                    (int) ($payload['hours'] ?? 72)
                ),
                'href' => url(ltrim((string) ($payload['href'] ?? 'academy'), '/')),
                'unread' => $unread,
                'at' => $at,
            ],
            'academy_auto_pending' => [
                'title' => self::fr() ? 'Chapitre à valider : vérification automatique non concluante' : 'Chapter to validate: automatic check inconclusive',
                'detail' => sprintf(
                    self::fr() ? '« %s », chapitre %d : en attente depuis plus de %d h, Athena ne peut pas le valider seule. Merci de le traiter.'
                        : '"%s", chapter %d: waiting for more than %d h, Athena cannot validate it on its own. Please review it.',
                    trim((string) ($payload['course'] ?? '')),
                    (int) ($payload['chapter'] ?? 0),
                    (int) ($payload['hours'] ?? 72)
                ),
                'href' => url('academy/instructor') . '#file',
                'unread' => $unread,
                'at' => $at,
            ],
            'academy_auto_revoked' => [
                'title' => self::fr() ? 'Validation automatique annulée par un formateur' : 'Automatic validation cancelled by a trainer',
                'detail' => sprintf(self::fr() ? 'Le chapitre %d est de nouveau en attente de validation par un formateur.' : 'Chapter %d is again waiting for a trainer to validate it.', (int) ($payload['chapter'] ?? 0)),
                'href' => url(ltrim((string) ($payload['href'] ?? 'academy'), '/')),
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
            'atak_mod_report_reply' => [
                'title' => !empty($payload['auto'])
                    ? 'Réponse sur une erreur détectée par votre mod'
                    : 'Réponse à votre signalement du mod',
                'detail' => trim((string) ($payload['summary'] ?? '')) !== ''
                    ? trim((string) $payload['summary'])
                    : trim((string) ($payload['report'] ?? 'L’équipe COMSPEC a traité votre signalement.')),
                'href' => url('activite'),
                'unread' => $unread,
                'at' => $at,
            ],
            // Communauté restée à 1 ou 2 membres : texte déjà rédigé dans la langue du destinataire (responsables)
            // ou pour l'administration de la plateforme.
            'org_low_members', 'org_low_members_admin' => [
                'title' => trim((string) ($payload['title'] ?? '')) !== ''
                    ? trim((string) $payload['title'])
                    : ($type === 'org_low_members_admin' ? 'Organisations à supprimer' : 'Votre communauté compte encore peu de membres'),
                'detail' => trim((string) ($payload['detail'] ?? '')),
                'href' => (isset($payload['href']) && trim((string) $payload['href']) !== '')
                    ? trim((string) $payload['href'])
                    : ($type === 'org_low_members_admin' ? url('admin/organisations-a-supprimer') : url('back-office/invitations')),
                'unread' => $unread,
                'at' => $at,
            ],
            default => [
                'title' => 'Notification',
                'detail' => '',
                'href' => url('activite'),
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
