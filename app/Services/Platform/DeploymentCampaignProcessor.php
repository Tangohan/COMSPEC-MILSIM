<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Repositories\DeploymentCampaignRepository;

/**
 * Exécute les étapes « en attente » d’une campagne de publication, dans l’ordre, jusqu’à $maxSteps ou première erreur.
 */
final class DeploymentCampaignProcessor
{
    public function __construct(
        private DeploymentCampaignRepository $campaignRepository,
        private DeploymentChannelReleaseService $channelReleaseService,
    ) {}

    /**
     * Choisit la prochaine ligne job exécutable (première en attente dont toutes les étapes précédentes sont réussies).
     *
     * @param list<array<string, mixed>> $jobsOrdered tri par step_order croissant
     *
     * @return array<string, mixed>|null
     */
    public static function pickNextRunnableJob(array $jobsOrdered): ?array
    {
        $n = count($jobsOrdered);
        for ($i = 0; $i < $n; ++$i) {
            $row = $jobsOrdered[$i];
            if (($row['status'] ?? '') !== 'queued') {
                continue;
            }
            $ok = true;
            for ($j = 0; $j < $i; ++$j) {
                if (($jobsOrdered[$j]['status'] ?? '') !== 'success') {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array{
     *   processed: int,
     *   stopped_reason: 'none'|'campaign_done'|'campaign_failed'|'no_queued_job'|'invalid_campaign'|'schema_missing'|'batch_limit',
     *   campaign_status: string|null,
     *   last_error: string|null,
     *   should_audit_failure: bool
     * }
     */
    public function processCampaignSteps(int $campaignId, ?int $actorUserId, int $maxSteps = 5): array
    {
        $out = [
            'processed' => 0,
            'stopped_reason' => 'none',
            'campaign_status' => null,
            'last_error' => null,
            'should_audit_failure' => false,
        ];
        if (!$this->campaignRepository->schemaReady() || $campaignId < 1) {
            $out['stopped_reason'] = 'schema_missing';

            return $out;
        }
        $campaign = $this->campaignRepository->findCampaign($campaignId);
        if ($campaign === null) {
            $out['stopped_reason'] = 'invalid_campaign';

            return $out;
        }
        $moduleId = (int) ($campaign['module_id'] ?? 0);
        $status = (string) ($campaign['status'] ?? '');
        $out['campaign_status'] = $status;
        if (in_array($status, ['completed', 'failed', 'cancelled'], true)) {
            $out['stopped_reason'] = 'campaign_done';

            return $out;
        }
        if ($status === 'queued') {
            $this->campaignRepository->updateCampaignStatus($campaignId, 'in_progress');
        }
        $maxSteps = max(1, min(25, $maxSteps));
        $processed = 0;
        while ($processed < $maxSteps) {
            $jobs = $this->campaignRepository->listJobsForCampaign($campaignId);
            $next = self::pickNextRunnableJob($jobs);
            if ($next === null) {
                $counts = $this->campaignRepository->countJobStatusesForCampaign($campaignId);
                $failed = (int) ($counts['failed'] ?? 0);
                $queued = (int) ($counts['queued'] ?? 0);
                $running = (int) ($counts['running'] ?? 0);
                if ($failed > 0) {
                    $this->campaignRepository->markCampaignFinished($campaignId, 'failed');
                    $out['stopped_reason'] = 'campaign_failed';
                    $out['campaign_status'] = 'failed';
                } elseif ($queued === 0 && $running === 0) {
                    $this->campaignRepository->markCampaignFinished($campaignId, 'completed');
                    $out['stopped_reason'] = 'campaign_done';
                    $out['campaign_status'] = 'completed';
                } else {
                    $out['stopped_reason'] = 'no_queued_job';
                }
                break;
            }
            $jobId = (int) ($next['id'] ?? 0);
            $channelId = (int) ($next['target_channel_id'] ?? 0);
            $versionId = (int) ($next['module_version_id'] ?? 0);
            if ($jobId < 1 || $channelId < 1 || $versionId < 1 || $moduleId < 1) {
                if ($jobId > 0) {
                    $this->campaignRepository->markJobFailed($jobId, 'Données d’étape incohérentes.');
                }
                $this->campaignRepository->markCampaignFinished($campaignId, 'failed');
                $out['stopped_reason'] = 'campaign_failed';
                $out['campaign_status'] = 'failed';
                $out['last_error'] = 'Données d’étape incohérentes.';
                $out['should_audit_failure'] = true;
                break;
            }
            if (!$this->campaignRepository->markJobRunning($jobId)) {
                $out['stopped_reason'] = 'no_queued_job';
                $out['last_error'] = 'Une autre exécution est peut-être en cours. Rechargez la page.';
                break;
            }
            try {
                $this->channelReleaseService->publishVersionOnChannel($moduleId, $channelId, $versionId, $actorUserId);
                $this->campaignRepository->markJobSuccess($jobId);
                ++$processed;
            } catch (\Throwable) {
                $msg = 'La publication sur l’environnement visé n’a pas pu être appliquée. Vérifiez la version et les droits, puis réessayez ou contactez l’équipe plateforme.';
                $this->campaignRepository->markJobFailed($jobId, $msg);
                $this->campaignRepository->markCampaignFinished($campaignId, 'failed');
                $out['stopped_reason'] = 'campaign_failed';
                $out['campaign_status'] = 'failed';
                $out['last_error'] = $msg;
                $out['should_audit_failure'] = true;
                break;
            }
        }
        $out['processed'] = $processed;
        if ($processed > 0 && $out['stopped_reason'] === 'none') {
            $counts = $this->campaignRepository->countJobStatusesForCampaign($campaignId);
            $out['stopped_reason'] = ((int) ($counts['queued'] ?? 0)) > 0 ? 'batch_limit' : 'no_queued_job';
        }
        $this->finalizeCampaignWhenAllJobsDone($campaignId);
        $fresh = $this->campaignRepository->findCampaign($campaignId);
        if ($fresh !== null) {
            $out['campaign_status'] = (string) ($fresh['status'] ?? $out['campaign_status']);
        }

        return $out;
    }

    private function finalizeCampaignWhenAllJobsDone(int $campaignId): void
    {
        $camp = $this->campaignRepository->findCampaign($campaignId);
        if ($camp === null) {
            return;
        }
        $st = (string) ($camp['status'] ?? '');
        if (in_array($st, ['completed', 'failed', 'cancelled'], true)) {
            return;
        }
        $counts = $this->campaignRepository->countJobStatusesForCampaign($campaignId);
        if ((int) ($counts['failed'] ?? 0) > 0) {
            $this->campaignRepository->markCampaignFinished($campaignId, 'failed');

            return;
        }
        if ((int) ($counts['queued'] ?? 0) === 0 && (int) ($counts['running'] ?? 0) === 0) {
            $sum = array_sum($counts);
            if ($sum > 0) {
                $this->campaignRepository->markCampaignFinished($campaignId, 'completed');
            }
        }
    }
}
