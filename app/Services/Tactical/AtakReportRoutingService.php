<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\AtakReportRoutingRepository;

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
    ) {
        $this->routing ??= new AtakReportRoutingRepository();
        $this->activityLog ??= new AtakActivityLogService();
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

        return $recipients;
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
