<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CronJobRunRepository;
use App\Services\Cron\CronRunner;
use App\Services\Cron\CronSchedule;

final class SystemCronController
{
    public function __construct(
        private CronRunner $runner,
        private CronJobRunRepository $runs,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $secretConfigured = trim((string) env('CRON_SECRET', '')) !== '';
        $jobs = [];
        $latest = $this->runs->latestByJobKey();
        foreach ($this->runner->jobs() as $job) {
            $key = $job->key();
            $jobs[] = [
                'key' => $key,
                'label' => $job->label(),
                'description' => $job->description(),
                'latest' => $latest[$key] ?? null,
            ];
        }
        $recentRuns = $this->runs->listRecent(40);
        $phpBin = 'php';
        $scriptPath = base_path('scripts/cron-run.php');
        $logPath = base_path('storage/logs/cron.log');

        return Response::view('layout.main', [
            'title' => 'Tâches automatiques',
            'content' => 'admin.system.cron',
            'jobs' => $jobs,
            'recentRuns' => $recentRuns,
            'tablesReady' => $this->runs->runsTableExists(),
            'secretConfigured' => $secretConfigured,
            'schedulerActive' => CronSchedule::schedulerLooksActive($recentRuns),
            'cronHttpUrl' => url('cron/run'),
            'cliCommand' => $phpBin . ' ' . $scriptPath,
            'crontabLine' => CronSchedule::crontabLine($phpBin, $scriptPath, $logPath),
            'installCommand' => 'bash scripts/install-system-cron.sh',
        ]);
    }

    public function runNow(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('admin/system/cron'));
        }

        $jobKey = trim((string) $request->input('job_key', ''));
        $result = $this->runner->runAll('admin', $jobKey !== '' ? $jobKey : null);
        $okCount = 0;
        $failCount = 0;
        foreach ($result['results'] as $r) {
            if (!empty($r['ok'])) {
                $okCount++;
            } else {
                $failCount++;
            }
        }

        if ($failCount > 0) {
            Session::flash('error', "Exécution terminée avec des erreurs ({$okCount} réussie(s), {$failCount} en échec). Consultez l’historique ci-dessous.");
        } else {
            Session::flash('success', $okCount === 1
                ? 'Tâche exécutée avec succès.'
                : "{$okCount} tâches exécutées avec succès.");
        }

        return Response::redirect(url('admin/system/cron'));
    }
}
