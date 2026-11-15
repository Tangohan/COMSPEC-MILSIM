<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use App\Repositories\EnlistmentRecruitmentEngagementRepository;
use App\Repositories\EnlistmentTimelineRepository;
use App\Repositories\MemberNotificationRepository;
use App\Repositories\TenantRepository;
use App\Support\EnlistmentRetroAutoClose;
use Throwable;

/**
 * Clôture automatique (« néant ») des bilans 30 jours en retard de 45 jours ou plus.
 *
 * Écrit par un service (tâche planifiée « recruitment_retro_auto_close » ou ligne de commande), jamais par une route
 * web : les droits des humains ne changent pas. Idempotent (un seul néant par dossier, jamais écrasé par un second
 * passage), borné, et un dossier en erreur est consigné puis sauté sans arrêter les autres.
 *
 * Très anciennes communautés : fenêtre de sécurité (EnlistmentRetroAutoClose::AUTO_CLOSE_MAX_LATE_DAYS) au-delà de
 * laquelle un dossier n'est ni clos ni notifié, seulement compté (« stale ») pour l'avis « Données anciennes à
 * vérifier » ; une communauté peut aussi désactiver la clôture automatique (tenants.settings, retro_auto_close) ;
 * une seule notification par instructeur et par passage, plafonnée.
 */
final class EnlistmentRetroAutoCloseService
{
    public function __construct(
        private EnlistmentRecruitmentEngagementRepository $engagement,
        private EnlistmentTimelineRepository $timeline,
        private TenantRepository $tenants,
        private MemberNotificationRepository $notifications,
    ) {}

    /**
     * @param bool $dryRun simulation : liste ce qui SERAIT clos, sans rien écrire
     * @param int|null $onlyTenantId limite à une communauté (ligne de commande)
     * @return array{ok: bool, available: bool, dry_run: bool, closed: int, would_close: list<array{tenant_id: int, enlistment_id: int, name: string, late_days: int}>, errors: int, reason: string, stale: array<int, int>, disabled: list<int>, notified: int}
     */
    public function run(bool $dryRun = false, ?int $onlyTenantId = null, ?int $now = null, ?int $maxTotal = null): array
    {
        $out = ['ok' => true, 'available' => true, 'dry_run' => $dryRun, 'closed' => 0, 'would_close' => [], 'errors' => 0, 'reason' => '', 'stale' => [], 'disabled' => [], 'notified' => 0];
        if (!$this->engagement->retroTableExists()) {
            return ['available' => false, 'reason' => 'Bilans non activés (table absente).'] + $out;
        }
        if (!$this->engagement->autoCloseColumnExists()) {
            return ['available' => false, 'reason' => 'Migration à passer (colonne auto_closed_at absente) : rien n’est écrit.'] + $out;
        }

        $budget = max(1, $maxTotal ?? EnlistmentRetroAutoClose::BATCH_TOTAL);
        /** @var array<int, array<int, list<array{name: string, enlistment_id: int}>>> $toNotify communauté => instructeur => dossiers clos */
        $toNotify = [];
        foreach ($this->tenants->listBasicAll() as $tenant) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            if ($tenantId < 1 || ($onlyTenantId !== null && $tenantId !== $onlyTenantId)) {
                continue;
            }
            if ($budget <= 0) {
                break;
            }
            try {
                // Les dossiers anciens (au-delà de la fenêtre) ne sont jamais écrits : seulement comptés pour l'avis aux responsables.
                $stale = $this->engagement->countStaleWithoutStaffRetro($tenantId);
                if ($stale > 0) {
                    $out['stale'][$tenantId] = $stale;
                }
                if (!EnlistmentRetroAutoClose::enabledFor($this->tenants->getSettings($tenantId))) {
                    $out['disabled'][] = $tenantId;
                    continue;
                }
            } catch (Throwable $e) {
                error_log('[cron recruitment_retro_auto_close] réglage de la communauté ' . $tenantId . ' : ' . $e->getMessage());
            }
            try {
                $rows = $this->engagement->listStaffRetroAutoCloseCandidates($tenantId, min($budget, EnlistmentRetroAutoClose::BATCH_PER_TENANT));
            } catch (Throwable $e) {
                $out['errors']++;
                error_log('[cron recruitment_retro_auto_close] communauté ' . $tenantId . ' : ' . $e->getMessage());
                continue;
            }
            foreach ($rows as $row) {
                $eid = (int) ($row['id'] ?? 0);
                // Le préfiltre SQL ne contient aucun bilan ; la règle exacte (jours calendaires) reste dans EnlistmentRetroAutoClose.
                if ($eid < 1 || !EnlistmentRetroAutoClose::isDue($row, false, $now)) {
                    continue;
                }
                $late = (int) EnlistmentRetroAutoClose::lateDays($row, $now);
                $name = trim(trim((string) ($row['first_name'] ?? '')) . ' ' . trim((string) ($row['last_name'] ?? '')));
                if ($name === '') {
                    $name = 'Candidat #' . $eid;
                }
                if ($dryRun) {
                    $out['would_close'][] = ['tenant_id' => $tenantId, 'enlistment_id' => $eid, 'name' => $name, 'late_days' => $late];
                    $budget--;
                    continue;
                }
                try {
                    if ($this->closeOne($tenantId, $row, $name, $late)) {
                        $out['closed']++;
                        $budget--;
                        $handlerId = (int) ($row['reviewed_by'] ?? 0);
                        if ($handlerId > 0) {
                            $toNotify[$tenantId][$handlerId][] = ['name' => $name, 'enlistment_id' => $eid];
                        }
                    }
                } catch (Throwable $e) {
                    $out['errors']++;
                    error_log('[cron recruitment_retro_auto_close] dossier ' . $eid . ' : ' . $e->getMessage());
                }
            }
        }
        $out['notified'] = $this->notifyHandlers($toNotify);
        $out['ok'] = $out['errors'] === 0;

        return $out;
    }

    /**
     * Une seule information sobre par instructeur et par passage (et non une par dossier), plafonnée par exécution.
     *
     * @param array<int, array<int, list<array{name: string, enlistment_id: int}>>> $toNotify
     */
    private function notifyHandlers(array $toNotify): int
    {
        $sent = 0;
        try {
            if ($toNotify === [] || !$this->notifications->tableExists()) {
                return 0;
            }
        } catch (Throwable) {
            return 0;
        }
        foreach ($toNotify as $tenantId => $byHandler) {
            foreach ($byHandler as $handlerId => $dossiers) {
                if ($sent >= EnlistmentRetroAutoClose::MAX_NOTIFICATIONS_PER_RUN) {
                    return $sent;
                }
                try {
                    $names = array_values(array_unique(array_map(static fn (array $d): string => $d['name'], array_slice($dossiers, 0, 3))));
                    $this->notifications->create($tenantId, $handlerId, EnlistmentRetroAutoClose::NOTIFICATION_TYPE, [
                        'candidate' => $dossiers[0]['name'],
                        'candidates' => $names,
                        'count' => count($dossiers),
                        'enlistment_id' => $dossiers[0]['enlistment_id'],
                    ]);
                    $sent++;
                } catch (Throwable $e) {
                    error_log('[cron recruitment_retro_auto_close] notification de l’instructeur ' . $handlerId . ' : ' . $e->getMessage());
                }
            }
        }

        return $sent;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function closeOne(int $tenantId, array $row, string $name, int $lateDays): bool
    {
        $eid = (int) $row['id'];
        // Insertion idempotente : faux si un bilan d'équipe existe déjà (écrit entre-temps par un membre ou par un autre passage).
        if (!$this->engagement->autoCloseStaffRetro($tenantId, $eid, EnlistmentRetroAutoClose::COMMENT)) {
            return false;
        }
        // Journal du dossier : sans acteur (le système), il garde la trace même si un vrai bilan remplace le néant.
        try {
            $this->timeline->append(
                $tenantId,
                $eid,
                'system',
                'general',
                'Bilan de recrutement (équipe, après 30 jours) — clos automatiquement par Athena (néant)',
                EnlistmentRetroAutoClose::COMMENT . "\nAttendu depuis " . $lateDays . ' jours (seuil : ' . EnlistmentRetroAutoClose::AUTO_CLOSE_LATE_DAYS . ' jours). Sans note. Un instructeur peut rédiger un bilan qui le remplace.',
                null,
                ['retro' => 'staff_one_month', 'auto' => true, 'validated_by' => 'athena', 'late_days' => $lateDays]
            );
        } catch (Throwable $e) {
            error_log('[cron recruitment_retro_auto_close] journal du dossier ' . $eid . ' : ' . $e->getMessage());
        }

        return true;
    }
}
