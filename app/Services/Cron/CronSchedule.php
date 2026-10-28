<?php

declare(strict_types=1);

namespace App\Services\Cron;

use App\Support\UtcDateTime;

/**
 * Cadence des tâches automatiques : un passage serveur toutes les 5 minutes,
 * chaque job ne se relance que s’il est dû.
 */
final class CronSchedule
{
    public const TICK_MINUTES = 5;

    public const DEFAULT_INTERVAL_MINUTES = 60;

    public const STUCK_RUNNING_MINUTES = 30;

    /** @var array<string, int> */
    private const INTERVALS = [
        'atak_report_routing_escalations' => 5,
        'sse_sync_maintenance' => 15,
        'attendance_reminders' => 30,
        'cooperation_reminders' => 60,
        'training_expire' => 60,
        'qualification_expiry_scan' => 60,
        // Cursus pilote : les heures de vol arrivent toutes les 10 min, un passage par heure suffit.
        'pilot_qualifications' => 60,
        'personnel_progression_evaluate' => 60,
        'personnel_capability_refresh' => 60,
        'seniority_sync_all' => 1440,
        'moderation_quarantine_expire' => 60,
        'account_deletion_anonymize' => 60,
        'recruitment_retro_reminders' => 360,
        'hr_weekly_digest' => 1440,
        'training_forgotten_docs_digest' => 1440,
        'roleplay_bilan_due' => 1440,
        'request_telemetry_purge' => 1440,
        'sse_analytical_nightly' => 1440,
        'sse_analyst_digest' => 1440,
        'member_integration_daily' => 1440,
        'personnel_auto_advancement' => 1440,
        'advancement_seniority' => 1440,
        // Passage borné à 90 s : le stock de photos se résorbe d'heure en heure.
        'photo_compress' => 60,
        // Fins d'essai fondateur et de formule offerte : e-mail une fois par fin.
        'plan_end_warning' => 360,
        // Annonce de la rétrospective annuelle : une part du quota d'e-mails par jour, pendant 14 jours.
        'year_review_mail' => 1440,
        // Rôles liés Discord : chaque compte relié est renvoyé au plus une fois par 24 h, 50 par passage.
        'discord_linked_roles_sync' => 60,
        // E-mails reportés par le quota quotidien ou mis en file (MAIL_QUEUE).
        'mail_queue' => 5,
        // Le récapitulatif vérifie sa propre cadence (Administration > Envois d'e-mails).
        'mail_ops_digest' => 5,
        'athena_heritage_snapshot' => 1440,
    ];

    public static function intervalMinutes(string $jobKey): int
    {
        return self::INTERVALS[$jobKey] ?? self::DEFAULT_INTERVAL_MINUTES;
    }

    /**
     * @param array<string, mixed>|null $latest
     */
    public static function isDue(string $jobKey, ?array $latest, bool $force = false, ?int $now = null): bool
    {
        if ($force) {
            return true;
        }
        $now = $now ?? time();
        if ($latest === null) {
            return true;
        }
        $status = (string) ($latest['status'] ?? '');
        $anchor = (string) ($latest['finished_at'] ?? $latest['started_at'] ?? '');
        // started_at / finished_at sont écrits par NOW() en session UTC : relus en UTC,
        // sinon un passage paraît 1 à 2 h plus ancien et les tâches horaires tournent à chaque tick.
        $at = UtcDateTime::timestamp($anchor);
        if ($at === null) {
            return true;
        }
        if ($status === 'running') {
            return ($now - $at) >= (self::STUCK_RUNNING_MINUTES * 60);
        }
        if ($status === 'error') {
            return true;
        }
        $interval = max(1, self::intervalMinutes($jobKey));

        return ($now - $at) >= ($interval * 60);
    }

    /**
     * @param list<array<string, mixed>> $recentRuns
     */
    public static function schedulerLooksActive(array $recentRuns, int $maxAgeSeconds = 600, ?int $now = null): bool
    {
        $now = $now ?? time();
        $cutoff = $now - max(60, $maxAgeSeconds);
        foreach ($recentRuns as $run) {
            $src = (string) ($run['trigger_source'] ?? '');
            if (!in_array($src, ['cli', 'http', 'watchdog'], true)) {
                continue;
            }
            $at = UtcDateTime::timestamp($run['finished_at'] ?? $run['started_at'] ?? '');
            if ($at !== null && $at >= $cutoff) {
                return true;
            }
        }

        return false;
    }

    public static function crontabLine(string $phpBin, string $scriptPath, string $logPath, string $lockPath = '/tmp/athena-cron.lock'): string
    {
        $php = trim($phpBin) !== '' ? trim($phpBin) : 'php';

        return sprintf(
            '*/%d * * * * flock -n %s %s %s >> %s 2>&1',
            self::TICK_MINUTES,
            $lockPath,
            $php,
            $scriptPath,
            $logPath
        );
    }
}
