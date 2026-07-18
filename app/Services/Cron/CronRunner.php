<?php

declare(strict_types=1);

namespace App\Services\Cron;

use App\Repositories\CronJobRunRepository;

final class CronRunner
{
    /** @param list<CronJobInterface> $jobs */
    public function __construct(
        private array $jobs,
        private CronJobRunRepository $runs,
    ) {}

    /**
     * @return list<CronJobInterface>
     */
    public function jobs(): array
    {
        return $this->jobs;
    }

    public function find(string $key): ?CronJobInterface
    {
        foreach ($this->jobs as $job) {
            if ($job->key() === $key) {
                return $job;
            }
        }

        return null;
    }

    /**
     * @return array{ok: bool, results: list<array<string, mixed>>}
     */
    public function runAll(string $triggerSource = 'cli', ?string $onlyKey = null): array
    {
        $results = [];
        $allOk = true;
        foreach ($this->jobs as $job) {
            if ($onlyKey !== null && $onlyKey !== '' && $job->key() !== $onlyKey) {
                continue;
            }
            $results[] = $this->runOne($job, $triggerSource);
            if (!($results[array_key_last($results)]['ok'] ?? false)) {
                $allOk = false;
            }
        }

        return ['ok' => $allOk, 'results' => $results];
    }

    /**
     * @return array{ok: bool, job: string, label: string, summary: string, details: array<string, mixed>}
     */
    public function runOne(CronJobInterface $job, string $triggerSource = 'cli'): array
    {
        $runId = $this->runs->beginRun($job->key(), $triggerSource);
        try {
            $out = $job->run();
            $ok = !empty($out['ok']);
            $summary = (string) ($out['summary'] ?? ($ok ? 'Terminé' : 'Échec'));
            $details = is_array($out['details'] ?? null) ? $out['details'] : [];
            $this->runs->finishRun($runId, $ok ? 'ok' : 'error', $summary, $details);

            return [
                'ok' => $ok,
                'job' => $job->key(),
                'label' => $job->label(),
                'summary' => $summary,
                'details' => $details,
            ];
        } catch (\Throwable $e) {
            $summary = 'Erreur : ' . $e->getMessage();
            $this->runs->finishRun($runId, 'error', $summary, [
                'exception' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'job' => $job->key(),
                'label' => $job->label(),
                'summary' => $summary,
                'details' => [],
            ];
        }
    }
}
