<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AuditLogRepository;
use App\Repositories\ForumReportRepository;
use App\Repositories\MaintenanceRepository;
use App\Repositories\ModerationArtifactRepository;
use App\Repositories\PlatformAlertRepository;

final class SystemOpsCenterController
{
    public function __construct(
        private ?ForumReportRepository $forumReports = null,
        private ?ModerationArtifactRepository $moderationArtifacts = null,
        private ?PlatformAlertRepository $platformAlerts = null,
        private ?MaintenanceRepository $maintenance = null,
        private ?AuditLogRepository $auditLogs = null,
    ) {
        $this->forumReports ??= new ForumReportRepository();
        $this->moderationArtifacts ??= new ModerationArtifactRepository();
        $this->platformAlerts ??= new PlatformAlertRepository();
        $this->maintenance ??= new MaintenanceRepository();
        $this->auditLogs ??= new AuditLogRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $forumPendingTotal = 0;
        try {
            $forumPendingTotal = $this->forumReports->countPendingAllTenants();
        } catch (\Throwable) {
            $forumPendingTotal = 0;
        }

        $contentPendingTotal = 0;
        try {
            if ($this->moderationArtifacts->tableExists()) {
                $contentPendingTotal = $this->moderationArtifacts->countPendingQueueAllTenants();
            }
        } catch (\Throwable) {
            $contentPendingTotal = 0;
        }

        $activeAlerts = [];
        try {
            $activeAlerts = $this->platformAlerts->listActiveForDisplay();
        } catch (\Throwable) {
            $activeAlerts = [];
        }

        $maintenanceRows = [];
        try {
            if ($this->maintenance->tableExists()) {
                $maintenanceRows = $this->maintenance->listAll();
            }
        } catch (\Throwable) {
            $maintenanceRows = [];
        }

        $maintenanceEnabled = array_values(array_filter($maintenanceRows, static fn (array $row): bool => (int) ($row['is_enabled'] ?? 0) === 1));
        $maintenancePlanned = array_values(array_filter($maintenanceRows, static fn (array $row): bool => (string) ($row['starts_at'] ?? '') !== '' || (string) ($row['ends_at'] ?? '') !== ''));

        $recentAudit = [];
        try {
            $recentAudit = $this->auditLogs->recentSystem(8);
        } catch (\Throwable) {
            $recentAudit = [];
        }

        $actions = [
            [
                'role' => 'moderator',
                'status' => $forumPendingTotal > 0 ? 'open' : 'monitor',
                'priority' => $forumPendingTotal > 20 ? 'high' : 'medium',
                'title' => 'Traiter les signalements forum en attente',
                'description' => sprintf('%d signalement(s) forum à traiter au niveau plateforme.', $forumPendingTotal),
                'link' => url('back-office/forum-moderation'),
                'link_label' => 'Ouvrir la console modération',
            ],
            [
                'role' => 'moderator',
                'status' => $contentPendingTotal > 0 ? 'open' : 'done',
                'priority' => $contentPendingTotal > 10 ? 'high' : 'low',
                'title' => 'Valider la file de modération de contenu',
                'description' => sprintf('%d élément(s) en quarantaine fichier/contenu.', $contentPendingTotal),
                'link' => url('admin/content-moderation'),
                'link_label' => 'Ouvrir la quarantaine',
            ],
            [
                'role' => 'moderator',
                'status' => 'monitor',
                'priority' => 'medium',
                'title' => 'Sanctions membres au niveau site',
                'description' => 'Mesures sur le compte, le forum, la messagerie ou plusieurs domaines du portail — après choix de la communauté et du membre.',
                'link' => url('admin/system/member-sanctions'),
                'link_label' => 'Ouvrir l’écran sanctions site',
            ],
            [
                'role' => 'support',
                'status' => count($activeAlerts) > 0 ? 'open' : 'monitor',
                'priority' => count($activeAlerts) > 0 ? 'medium' : 'low',
                'title' => 'Vérifier la communication incident en cours',
                'description' => sprintf('%d alerte(s) plateforme active(s) à relayer côté support.', count($activeAlerts)),
                'link' => url('admin/system/alerts'),
                'link_label' => 'Gérer les alertes',
            ],
            [
                'role' => 'support',
                'status' => count($maintenanceEnabled) > 0 ? 'open' : 'monitor',
                'priority' => count($maintenanceEnabled) > 0 ? 'high' : 'low',
                'title' => 'Synchroniser les réponses support avec la maintenance',
                'description' => sprintf('%d maintenance(s) active(s), %d planifiée(s).', count($maintenanceEnabled), count($maintenancePlanned)),
                'link' => url('admin/maintenance'),
                'link_label' => 'Voir les maintenances',
            ],
            [
                'role' => 'admin',
                'status' => count($maintenanceEnabled) > 0 ? 'open' : 'monitor',
                'priority' => count($maintenanceEnabled) > 0 ? 'high' : 'medium',
                'title' => 'Piloter les maintenances et remédiations',
                'description' => 'Contrôler les fenêtres actives, dates et messages de bypass admin.',
                'link' => url('admin/maintenance'),
                'link_label' => 'Piloter la maintenance',
            ],
            [
                'role' => 'admin',
                'status' => 'open',
                'priority' => 'medium',
                'title' => 'Auditer les escalades et décisions',
                'description' => sprintf('%d entrée(s) récentes d’audit disponibles pour revue transverse.', count($recentAudit)),
                'link' => url('admin/audit'),
                'link_label' => 'Consulter l’audit',
            ],
        ];

        $templates = [
            [
                'code' => 'incident_initial',
                'label' => 'Incident initial',
                'message' => 'Nous investiguons actuellement un incident impactant une partie de la plateforme. Prochaine mise à jour dans 30 minutes.',
            ],
            [
                'code' => 'incident_update',
                'label' => 'Mise à jour incident',
                'message' => 'Le diagnostic est en cours. Une action de remédiation est engagée. Merci pour votre patience.',
            ],
            [
                'code' => 'incident_resolved',
                'label' => 'Clôture incident',
                'message' => 'L’incident est résolu. Une revue post-incident est ouverte et les mesures correctives seront suivies.',
            ],
            [
                'code' => 'moderation_escalation',
                'label' => 'Escalade modération',
                'message' => 'Votre signalement est transmis à la modération renforcée. Vous serez notifié dès décision.',
            ],
        ];

        $statusDictionary = [
            ['code' => 'open', 'label' => 'Ouvert', 'usage' => 'Action à traiter immédiatement.'],
            ['code' => 'monitor', 'label' => 'Surveillance', 'usage' => 'Aucune action bloquante, suivi actif requis.'],
            ['code' => 'done', 'label' => 'Terminé', 'usage' => 'Action clôturée, conserver la traçabilité.'],
        ];

        return Response::view('layout.main', [
            'content' => 'admin.system.ops_center',
            'title' => 'Ops Center — Modération / Support / Admin',
            'opsCenterActions' => $actions,
            'opsCenterTemplates' => $templates,
            'opsCenterStatusDictionary' => $statusDictionary,
            'opsCenterRoleCounts' => [
                'moderator' => count(array_filter($actions, static fn (array $item): bool => $item['role'] === 'moderator')),
                'support' => count(array_filter($actions, static fn (array $item): bool => $item['role'] === 'support')),
                'admin' => count(array_filter($actions, static fn (array $item): bool => $item['role'] === 'admin')),
            ],
            'opsCenterCrossLinks' => [
                'user_lookup' => url('api/admin/user-search'),
                'audit' => url('admin/audit'),
                'moderation' => url('back-office/forum-moderation'),
            ],
        ]);
    }
}
