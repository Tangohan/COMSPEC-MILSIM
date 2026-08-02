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
 * ## Ce que ça branche
 *
 * `AtakReportRoutingRepository` existait avec un moteur de règles complet —
 * conditions, destinataires, escalade, accusé de réception — et **aucun appelant**.
 * Le chantier avait été commencé puis laissé avant branchement. Ce service est le
 * raccordement manquant, rien de plus : il n'ajoute aucune règle métier au moteur.
 *
 * ## Pourquoi une enveloppe plutôt qu'un appel direct
 *
 * Un rapport tactique est un compte rendu de terrain. Le routage, lui, est un
 * confort d'organisation : il désigne qui doit le lire en priorité. Si le routage
 * échoue — règle mal formée, table absente parce qu'une migration n'est pas passée,
 * zone introuvable — **le rapport doit rester enregistré quand même**. Perdre un
 * compte rendu de contact parce qu'une règle de diffusion est cassée serait un
 * échange calamiteux.
 *
 * Le service avale donc les erreurs et les journalise, exactement comme les
 * automatismes SSE.
 *
 * ## Il n'y a pas d'interrupteur, et c'est voulu
 *
 * Sans règle enregistrée, `applyRoutingRules()` ne route vers personne et
 * n'écrit rien. Une table de règles vide *est* l'état désactivé. Ajouter un
 * réglage par-dessus donnerait deux façons de désactiver la même chose, donc deux
 * endroits à vérifier quand quelqu'un demande pourquoi son rapport n'est pas
 * arrivé.
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
     * Applique les règles de diffusion à un rapport qui vient d'être enregistré.
     *
     * @return list<array{type: string, identifier: mixed}> destinataires désignés
     */
    public function onReportSubmitted(
        int $reportId,
        int $tenantId,
        int $contextId,
        ?string $actorLabel = null
    ): array {
        if ($reportId < 1 || $tenantId < 1) {
            return [];
        }

        try {
            $result = $this->routing->applyRoutingRules($reportId, $tenantId, $contextId);
        } catch (\Throwable $e) {
            // Le rapport est déjà en base : on trace et on rend la main.
            $this->activityLog->record(
                $tenantId,
                $contextId,
                'REPORT_ROUTING',
                'Diffusion dirigée indisponible pour ce rapport — il reste enregistré et consultable.',
                $actorLabel ?? 'Portail',
                ['error' => substr($e->getMessage(), 0, 200)]
            );

            return [];
        }

        if (isset($result['error'])) {
            return [];
        }

        $recipients = is_array($result['routed_to'] ?? null) ? $result['routed_to'] : [];
        if ($recipients === []) {
            return [];
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
            $actorLabel ?? 'Portail'
        );

        $this->notify($reportId, $tenantId, $contextId, $recipients);

        return $recipients;
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
