<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\Platform\PlanEndWarningService;

/**
 * Prévient par e-mail les gestionnaires d'une communauté dont l'essai fondateur, la formule offerte (si activé
 * dans Administration > Formules offertes) ou la période payée par PayPal (toujours, N jours avant et la veille)
 * se termine bientôt. Relance aussi l’administration pour les paiements PayPal déclarés depuis plus de 24 h
 * et toujours pas validés.
 */
final class PlanEndWarningCronJob implements CronJobInterface
{
    public function __construct(private ?PlanEndWarningService $service = null)
    {
    }

    public function key(): string
    {
        return 'plan_end_warning';
    }

    public function label(): string
    {
        return 'Fins de formule, relances de paiement et donateurs';
    }

    public function description(): string
    {
        return 'E-mail aux gestionnaires avant la fin d’une formule payée par PayPal (N jours avant et la veille), et d’un essai fondateur ou d’une formule offerte (si activé). Relance l’administration pour les paiements déclarés non validés depuis 24 h.';
    }

    public function run(): array
    {
        $r = ($this->service ??= new PlanEndWarningService())->sendWarnings();
        $p = ['requests' => 0, 'emails' => 0];
        $u = ['reminded' => 0, 'expired' => 0];
        $donors = ['donors' => 0];
        try {
            $svc = \App\Core\Container::get(\App\Services\Billing\PlanPaymentService::class);
            $p = $svc->remindAdminsOfPendingDeclarations();
            // Relances des paiements commencés mais jamais finalisés, annulation après 30 jours.
            $u = $svc->processUnpaid();
            // Donateurs et communautés soutien (badges, liserés) : recalculés chaque jour.
            $donors = (new \App\Services\Billing\DonorService())->rebuild($svc->paidTenantIds());
        } catch (\Throwable) {
            // Registre des paiements absent (base pas à jour) : rien à relancer.
        }

        return [
            'ok' => true,
            'summary' => sprintf(
                '%d communauté(s) prévenue(s), %d e-mail(s) envoyé(s) ; %d paiement(s) à valider relancé(s) ; %d payeur(s) relancé(s), %d demande(s) abandonnée(s) annulée(s) ; %d donateur(s)',
                $r['tenants'],
                $r['emails'],
                $p['requests'],
                $u['reminded'],
                $u['expired'],
                $donors['donors'] ?? 0
            ),
            'details' => $r + ['payment_reminders' => $p, 'unpaid' => $u, 'donors' => $donors],
        ];
    }
}
