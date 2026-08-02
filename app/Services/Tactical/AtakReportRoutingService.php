<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\AtakNotificationRepository;
use App\Repositories\AtakReportRoutingRepository;
use App\Repositories\AtakTacticalReportRepository;

/**
 * Diffusion dirigée des rapports tactiques — phase A du plan « niveaux
 * d'information » (`docs/PLAN-NIVEAUX-DIFFUSION.md`).
 *
 * ## Ce que ça fait
 *
 * Le moteur de règles est appliqué par le contrôleur à la soumission. Ce service
 * n'en refait rien : il **émet la notification** correspondant à la diffusion déjà
 * écrite. Sans lui, la diffusion désignait des destinataires que personne n'était
 * prévenu de lire.
 *
 * ## Pourquoi relire l'historique
 *
 * Les destinataires sont relus dans `atak_report_routing_history` plutôt que déduits
 * du retour du routage. C'est ce qui a réellement été enregistré qui doit être
 * notifié, pas ce que l'appelant croyait avoir demandé — et la forme de ce retour
 * peut changer sans que la notification s'en aperçoive.
 *
 * ## Une notification qui échoue n'annule pas la diffusion
 *
 * La diffusion est déjà en base. Un échec d'émission est tracé avec la mention
 * « destinataires à prévenir de vive voix », pour que le silence ne passe pas
 * inaperçu.
 */
final class AtakReportRoutingService
{
    public function __construct(
        private ?AtakReportRoutingRepository $routing = null,
        private ?AtakActivityLogService $activityLog = null,
        private ?AtakNotificationRepository $notifications = null,
        private ?AtakTacticalReportRepository $reports = null,
    ) {
        $this->routing ??= new AtakReportRoutingRepository();
        $this->activityLog ??= new AtakActivityLogService();
        $this->notifications ??= new AtakNotificationRepository();
        $this->reports ??= new AtakTacticalReportRepository();
    }

    /**
     * Émet la notification correspondant à la diffusion déjà appliquée.
     *
     * @return int nombre de destinataires notifiés
     */
    public function notifyForReport(int $reportId, int $tenantId, int $contextId): int
    {
        if ($reportId < 1 || $tenantId < 1) {
            return 0;
        }

        try {
            $history = $this->routing->listForReport($reportId);
        } catch (\Throwable) {
            return 0;
        }
        if ($history === []) {
            return 0;
        }

        // Idempotence : une diffusion déjà notifiée ne l'est pas une seconde fois.
        // Sans cette garde, un rejeu de la soumission — ou un simple double appel —
        // reposterait la même alerte, et une alerte en double se lit comme deux
        // événements distincts.
        $pending = array_filter($history, static fn (array $r): bool => empty($r['notification_sent']));
        if ($pending === []) {
            return 0;
        }

        $recipients = [];
        foreach ($pending as $row) {
            $recipients[] = [
                'type' => (string) ($row['routed_to_type'] ?? ''),
                'identifier' => (string) ($row['routed_to_identifier'] ?? ''),
            ];
        }

        $this->activityLog->record(
            $tenantId,
            $contextId,
            'REPORT_ROUTING',
            sprintf(
                'Rapport diffusé à %d destinataire(s) : %s.',
                count($recipients),
                self::describe($recipients)
            ),
            'Portail'
        );

        $this->notify($reportId, $tenantId, $contextId, $recipients);

        return count($recipients);
    }

    /**
     * Émet la notification correspondant à une diffusion.
     *
     * Une seule notification par rapport, portant tous les destinataires, et non
     * une par destinataire : trois lignes identiques dans le bandeau pour un même
     * compte rendu font passer l'alerte pour du bruit, et c'est le bruit qu'on
     * finit par ignorer.
     *
     * L'urgence de la notification reprend celle du rapport — un contact
     * immédiat n'a pas à s'afficher comme une routine.
     *
     * @param list<array<string, mixed>> $recipients
     */
    private function notify(int $reportId, int $tenantId, int $contextId, array $recipients): void
    {
        $report = $this->reports->findById($reportId, $tenantId);
        if (!is_array($report)) {
            return;
        }

        $roles = [];
        $units = [];
        $users = [];
        foreach ($recipients as $r) {
            $id = (string) ($r['identifier'] ?? '');
            if ($id === '') {
                continue;
            }
            match ((string) ($r['type'] ?? '')) {
                'ROLE' => $roles[] = $id,
                'UNIT' => $units[] = $id,
                'USER' => $users[] = $id,
                default => null,
            };
        }

        $priority = match ((string) ($report['priority'] ?? 'ROUTINE')) {
            'FLASH' => 'CRITICAL',
            'IMMEDIATE' => 'HIGH',
            'PRIORITY' => 'MEDIUM',
            default => 'LOW',
        };

        $label = trim((string) ($report['report_number'] ?? '')) ?: ('#' . $reportId);
        $summary = trim((string) ($report['summary'] ?? ''));

        try {
            $this->notifications->create($tenantId, $contextId, [
                'notification_type' => 'REPORT_URGENT',
                'priority' => $priority,
                'title' => sprintf('%s %s', (string) ($report['report_type'] ?? 'Rapport'), $label),
                'message' => $summary !== ''
                    ? $summary
                    : 'Rapport à traiter — ouvrez la fiche pour le détail.',
                'source_entity_type' => 'REPORT',
                'source_entity_id' => $reportId,
                'target_roles' => $roles !== [] ? json_encode(array_values(array_unique($roles)), JSON_UNESCAPED_UNICODE) : null,
                'target_units' => $units !== [] ? json_encode(array_values(array_unique($units)), JSON_UNESCAPED_UNICODE) : null,
                'target_users' => $users !== [] ? json_encode(array_values(array_unique($users)), JSON_UNESCAPED_UNICODE) : null,
                'show_on_map' => !empty($report['pos_x']) && !empty($report['pos_y']),
                'map_pos_x' => $report['pos_x'] ?? null,
                'map_pos_y' => $report['pos_y'] ?? null,
                // Une alerte de diffusion qui reste affichée une semaine devient un
                // décor : elle expire avec la pertinence du compte rendu.
                'expires_at' => date('Y-m-d H:i:s', time() + 7200),
            ]);

            $this->routing->markNotified($reportId);
        } catch (\Throwable $e) {
            // La diffusion est enregistrée : l'absence de notification ne doit pas
            // l'annuler. On trace pour que le silence ne passe pas inaperçu.
            $this->activityLog->record(
                $tenantId,
                $contextId,
                'REPORT_ROUTING',
                'Diffusion enregistrée mais notification non émise — destinataires à prévenir de vive voix.',
                'Portail',
                ['error' => substr($e->getMessage(), 0, 200)]
            );
        }
    }

    /**
     * Destinataires en clair, pour le journal et l'écran.
     *
     * @param list<array<string, mixed>> $recipients
     */
    public static function describe(array $recipients): string
    {
        $labels = [];
        foreach ($recipients as $r) {
            $type = match ((string) ($r['type'] ?? '')) {
                'ROLE' => 'fonction',
                'USER' => 'opérateur',
                'UNIT' => 'unité',
                default => 'destinataire',
            };
            $labels[] = $type . ' ' . (string) ($r['identifier'] ?? '?');
        }

        // Un journal illisible n'est pas consulté : on borne.
        if (count($labels) > 6) {
            $rest = count($labels) - 6;
            $labels = array_slice($labels, 0, 6);
            $labels[] = sprintf('et %d autre(s)', $rest);
        }

        return implode(', ', $labels);
    }
}
