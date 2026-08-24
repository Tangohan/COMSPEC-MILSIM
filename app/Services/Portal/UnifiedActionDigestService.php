<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Core\Gate;
use App\Repositories\EnlistmentRepository;
use App\Services\Notifications\PersonalMessageUnreadCounter;

/**
 * Agrégation « lecture seule » pour le centre d’actions et futurs résumés (e-mail / in-app).
 */
final class UnifiedActionDigestService
{
    public function __construct(
        private EnlistmentRepository $enlistmentRepository,
        private PersonalMessageUnreadCounter $personalMessageUnreadCounter,
    ) {}

    /**
     * @return array{
     *   forum_unread: int,
     *   courrier_unread: int,
     *   tenant_messages_unread: int,
     *   my_enlistments_pending: int,
     *   staff_enlistments_pending: int,
     *   total_attention: int,
     *   sections: list<array{title: string, items: list<array{label: string, href: string, hint: string, count?: int, priority?: string, action?: string}>}>
     * }
     */
    public function buildActionCenter(
        int $tenantId,
        int $userId,
        string $userEmail,
        Gate $gate,
        bool $showStaffRecruitment,
    ): array {
        $msg = $this->personalMessageUnreadCounter->countsForUser($tenantId, $userId, $gate);
        $forumUnread = $msg['forum_unread'];
        $courrierUnread = $msg['courrier_unread'];
        $tenantMessagesUnread = $msg['tenant_messages_unread'];

        $myPending = $this->enlistmentRepository->listPendingSubmittedForSubmitter($tenantId, $userId, $userEmail);
        $myPendingN = count($myPending);
        $staffPendingN = 0;
        if ($showStaffRecruitment) {
            $staffPending = $this->enlistmentRepository->listPendingSubmittedForTenant($tenantId, 200);
            $staffPendingN = count($staffPending);
        }

        $sections = [];

        $personal = [];
        if ($forumUnread > 0) {
            $personal[] = [
                'label' => 'Notifications à lire',
                'href' => url('activite'),
                'hint' => 'Forum, suivi roleplay et autres alertes non lues dans Mon activité.',
                'count' => $forumUnread,
                'priority' => 'normal',
                'action' => 'Consulter',
            ];
        }
        if ($courrierUnread > 0 && $gate->allows('courrier.view')) {
            $personal[] = [
                'label' => 'Courrier à consulter',
                'href' => url('courrier/notifications'),
                'hint' => 'Documents ou messages officiels non lus.',
                'count' => $courrierUnread,
                'priority' => 'high',
                'action' => 'Ouvrir',
            ];
        }
        if ($tenantMessagesUnread > 0) {
            $personal[] = [
                'label' => 'Messagerie interne',
                'href' => url('messages'),
                'hint' => 'Conversations avec l’encadrement — nouveaux messages non lus.',
                'count' => $tenantMessagesUnread,
                'priority' => 'normal',
                'action' => 'Répondre',
            ];
        }
        if ($myPendingN > 0) {
            $personal[] = [
                'label' => 'Dossier de recrutement personnel',
                'href' => url('account'),
                'hint' => 'Une ou plusieurs étapes attendent votre complément.',
                'count' => $myPendingN,
                'priority' => 'high',
                'action' => 'Compléter',
            ];
        }
        if ($personal !== []) {
            $sections[] = ['title' => 'À traiter pour vous', 'items' => $personal];
        }

        if ($showStaffRecruitment && $staffPendingN > 0) {
            $sections[] = [
                'title' => 'Encadrement',
                'items' => [[
                    'label' => 'Dossiers de recrutement à examiner',
                    'href' => url('back-office/recruitments'),
                    'hint' => 'Candidatures soumises en attente de traitement.',
                    'count' => $staffPendingN,
                    'priority' => 'high',
                    'action' => 'Examiner',
                ]],
            ];
        }

        $sections[] = [
            'title' => 'Raccourcis',
            'items' => [
                ['label' => 'Centre opérationnel', 'href' => url('hub'), 'hint' => 'Vue d’ensemble des modules.'],
                ['label' => 'Recherche portail', 'href' => url('search'), 'hint' => 'Recherche unifiée sur les contenus autorisés.'],
                ['label' => 'Mon activité', 'href' => url('activite'), 'hint' => 'Historique récent des échanges.'],
            ],
        ];

        return [
            'forum_unread' => $forumUnread,
            'courrier_unread' => $courrierUnread,
            'tenant_messages_unread' => $tenantMessagesUnread,
            'my_enlistments_pending' => $myPendingN,
            'staff_enlistments_pending' => $staffPendingN,
            'total_attention' => $forumUnread + $courrierUnread + $tenantMessagesUnread + $myPendingN + $staffPendingN,
            'sections' => $sections,
        ];
    }

    /**
     * Textes prêts pour un futur envoi (résumé hebdomadaire) — réutilise les mêmes compteurs.
     *
     * @return list<string>
     */
    public function buildWeeklyDigestLines(
        int $tenantId,
        int $userId,
        string $userEmail,
        Gate $gate,
        bool $showStaffRecruitment,
    ): array {
        $d = $this->buildActionCenter($tenantId, $userId, $userEmail, $gate, $showStaffRecruitment);
        $lines = [];
        if ($d['forum_unread'] > 0) {
            $lines[] = 'Vous avez des notifications non lues : ouvrez « Mon activité » pour les traiter.';
        }
        if ($d['courrier_unread'] > 0) {
            $lines[] = 'Des éléments du courrier interne attendent votre lecture.';
        }
        if ($d['tenant_messages_unread'] > 0) {
            $lines[] = 'La messagerie interne contient des messages non lus.';
        }
        if ($d['my_enlistments_pending'] > 0) {
            $lines[] = 'Votre dossier de recrutement contient des actions à finaliser.';
        }
        if ($d['staff_enlistments_pending'] > 0) {
            $lines[] = 'Des candidatures sont en attente de traitement côté encadrement.';
        }
        if ($lines === []) {
            $lines[] = 'Aucune action urgente détectée pour cette période. Pensez à consulter le centre opérationnel pour les actualités.';
        }

        return $lines;
    }
}
