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
            'photo_library_mention', 'photo_library_comment' => [
                'title' => $type === 'photo_library_mention'
                    ? trim((string) ($payload['by'] ?? 'Un membre')) . ' vous a cité sur une photo'
                    : trim((string) ($payload['by'] ?? 'Un membre')) . ' a commenté votre photo',
                'detail' => mb_substr(trim((string) ($payload['excerpt'] ?? '')), 0, 240),
                'href' => is_string($payload['path'] ?? null) && preg_match('#^jnet/photos/[0-9]{1,10}$#', (string) $payload['path']) === 1
                    ? url((string) $payload['path']) : url('jnet/photos'),
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
            'sse_interest_case' => [
                'title' => 'Dossier d’intérêt ' . mb_substr(trim((string) ($payload['ref'] ?? '')), 0, 40),
                'detail' => mb_substr(trim((string) ($payload['detail'] ?? 'Un dossier d’intérêt vous concerne.')), 0, 240),
                'href' => is_string($payload['path'] ?? null) && preg_match('#^atak/sse/interet/[0-9]{1,10}$#', (string) $payload['path']) === 1
                    ? url((string) $payload['path']) : url('atak/sse/interet'),
                'unread' => $unread,
                'at' => $at,
            ],
            'sse_member_image_request' => [
                'title' => 'Demande d’accès aux images d’un membre',
                'detail' => 'Un membre demande à voir les images de service d’un membre de votre chaîne de commandement.',
                'href' => url('atak/sse/fichiers/acces-images'),
                'unread' => $unread,
                'at' => $at,
            ],
            'sse_wanted_detection' => [
                'title' => 'Avis de recherche : ' . mb_substr(trim((string) ($payload['label'] ?? 'identité détectée')), 0, 80),
                'detail' => 'Une identité sous avis de recherche a été repérée (avis ' . mb_substr(trim((string) ($payload['ref'] ?? '')), 0, 40) . ').',
                'href' => is_string($payload['path'] ?? null) && preg_match('#^atak/sse/avis[A-Za-z0-9/_\-]*$#', (string) $payload['path']) === 1
                    ? url((string) $payload['path']) : url('atak/sse/avis/detections'),
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
            \App\Support\EnlistmentRetroAutoClose::NOTIFICATION_TYPE => [
                // Une seule information par passage et par instructeur : « count » dossiers regroupés (anciennes notifications : un dossier).
                'title' => (int) ($payload['count'] ?? 1) > 1
                    ? (int) $payload['count'] . ' bilans 30 jours clos automatiquement (néant)'
                    : 'Bilan 30 jours clos automatiquement (néant)',
                'detail' => (int) ($payload['count'] ?? 1) > 1
                    ? 'Pour ' . implode(', ', array_map('strval', array_slice(array_values((array) ($payload['candidates'] ?? [])), 0, 3)))
                        . ((int) $payload['count'] > 3 ? ' et ' . ((int) $payload['count'] - 3) . ' autre(s)' : '')
                        . ' : bilans non renseignés dans les délais. Vous pouvez rédiger un bilan qui remplace chaque néant.'
                    : 'Pour ' . (trim((string) ($payload['candidate'] ?? '')) !== '' ? trim((string) $payload['candidate']) : 'un candidat accepté')
                        . ' : bilan non renseigné dans les délais. Vous pouvez rédiger un bilan qui le remplace.',
                'href' => ((int) ($payload['count'] ?? 1) <= 1 && (int) ($payload['enlistment_id'] ?? 0) > 0)
                    ? url('back-office/recruitments/' . (int) $payload['enlistment_id'] . '?dossier=1#bilan-recrutement')
                    : url('back-office/recruitments'),
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
            // Demande d'accès à un outil opérateur : au responsable (à décider), puis au membre (décision).
            \App\Services\Operator\OperatorAccessRequestService::NOTIFY_REQUEST,
            \App\Services\Operator\OperatorAccessRequestService::NOTIFY_DECISION => (static function () use ($type, $payload, $unread, $at): array {
                $text = \App\Services\Operator\OperatorAccessRequestService::notificationText($type, $payload, self::fr());

                return ['title' => $text['title'], 'detail' => mb_substr($text['detail'], 0, 400), 'href' => url($text['path']), 'unread' => $unread, 'at' => $at];
            })(),
            // Campagne de fiabilisation des données : ouverture, rappel, réponse de l'encadrement à un signalement.
            \App\Services\DataCampaign\DataCampaignService::NOTIFY_OPEN,
            \App\Services\DataCampaign\DataCampaignService::NOTIFY_REMINDER,
            \App\Services\DataCampaign\DataCampaignService::NOTIFY_FLAG_HANDLED,
            \App\Services\DataCampaign\DataCampaignService::NOTIFY_AUTO_OPENED,
            \App\Services\DataCampaign\DataCampaignService::NOTIFY_AUTO_CLOSED => (static function () use ($type, $payload, $unread, $at): array {
                $due = trim((string) ($payload['due_on'] ?? ''));
                $dueText = $due !== '' && strtotime($due) !== false ? date('d/m/Y', (int) strtotime($due)) : '';
                if ($type === \App\Services\DataCampaign\DataCampaignService::NOTIFY_AUTO_OPENED || $type === \App\Services\DataCampaign\DataCampaignService::NOTIFY_AUTO_CLOSED) {
                    $cid = (int) ($payload['campaign_id'] ?? 0);
                    if ($type === \App\Services\DataCampaign\DataCampaignService::NOTIFY_AUTO_OPENED) {
                        $n = (int) ($payload['members'] ?? 0);
                        $title = self::fr() ? 'Campagne de fiabilisation ouverte automatiquement' : 'Data reliability campaign opened automatically';
                        $detail = self::fr() ? 'Campagne de fiabilisation ouverte automatiquement, ' . $n . ' membre(s) concerné(s).' : 'Data reliability campaign opened automatically, ' . $n . ' member(s) concerned.';
                        if (!empty($payload['incomplete'])) {
                            $detail .= self::fr() ? ' Beaucoup de fiches sont incomplètes : complétez l’état civil avant ou pendant la campagne.' : ' Many records are incomplete: complete the civil identity before or during the campaign.';
                        }
                    } else {
                        $title = self::fr() ? 'Campagne de fiabilisation close : rapport d’avancement' : 'Data reliability campaign closed: progress report';
                        $detail = self::fr()
                            ? (int) ($payload['confirmed'] ?? 0) . ' confirmée(s), ' . (int) ($payload['flagged'] ?? 0) . ' avec signalements, ' . (int) ($payload['none'] ?? 0) . ' sans réponse.'
                            : (int) ($payload['confirmed'] ?? 0) . ' confirmed, ' . (int) ($payload['flagged'] ?? 0) . ' with reports, ' . (int) ($payload['none'] ?? 0) . ' unanswered.';
                    }

                    return ['title' => $title, 'detail' => $detail, 'href' => url('back-office/organisation/fiabilisation' . ($cid > 0 ? '/' . $cid : '')), 'unread' => $unread, 'at' => $at];
                }
                if ($type === \App\Services\DataCampaign\DataCampaignService::NOTIFY_FLAG_HANDLED) {
                    $title = self::fr() ? 'Votre signalement sur votre fiche a reçu une réponse' : 'Your report on your record has been answered';
                    $detail = self::fr() ? 'L’encadrement a traité ce que vous avez signalé : la réponse est sur la page de vérification de votre fiche.' : 'The staff handled what you reported: the answer is on your record check page.';
                } elseif ($type === \App\Services\DataCampaign\DataCampaignService::NOTIFY_OPEN) {
                    $title = self::fr() ? 'Vérifiez votre fiche' : 'Check your record';
                    $detail = self::fr()
                        ? 'Une campagne de fiabilisation des données est ouverte' . ($dueText !== '' ? ' jusqu’au ' . $dueText : '') . ' : relisez votre fiche et confirmez-la.'
                        : 'A data reliability campaign is open' . ($dueText !== '' ? ' until ' . $dueText : '') . ': review your record and confirm it.';
                } else {
                    $title = self::fr() ? 'Rappel : votre fiche n’est pas encore confirmée' : 'Reminder: your record is not yet confirmed';
                    $detail = self::fr()
                        ? 'La campagne de fiabilisation des données se termine' . ($dueText !== '' ? ' le ' . $dueText : ' bientôt') . '. Quelques minutes suffisent.'
                        : 'The data reliability campaign ends' . ($dueText !== '' ? ' on ' . $dueText : ' soon') . '. A few minutes are enough.';
                }

                return ['title' => $title, 'detail' => $detail, 'href' => jnet_moi_url('fiabilisation'), 'unread' => $unread, 'at' => $at];
            })(),
            // Moteur de circuits : une demande (élévation, mobilité) attend l'avis du chef d'unité, dans Mon service.
            \App\Services\Workflow\WorkflowEngine::NOTIFY_OPINION_REQUEST => [
                'title' => self::fr() ? 'Avis de chef d’unité demandé' : 'Unit commander opinion requested',
                'detail' => mb_substr(trim(
                    \App\Services\Workflow\WorkflowEngine::subjectLabel((string) ($payload['subject_type'] ?? ''), self::fr())
                    . (trim((string) ($payload['member_label'] ?? '')) !== '' ? ' · ' . trim((string) $payload['member_label']) : '')
                    . (trim((string) ($payload['summary'] ?? '')) !== '' ? ' · ' . trim((string) $payload['summary']) : '')
                ), 0, 400),
                'href' => url('mon-service'),
                'unread' => $unread,
                'at' => $at,
            ],
            \App\Services\Workflow\WorkflowEngine::NOTIFY_DECISION => (static function () use ($payload, $unread, $at): array {
                $notice = \App\Services\Workflow\WorkflowEngine::decisionNotice($payload, self::fr());

                return [
                    'title' => $notice['title'],
                    'detail' => mb_substr($notice['detail'], 0, 400),
                    'href' => url($notice['path']),
                    'unread' => $unread,
                    'at' => $at,
                ];
            })(),
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
