<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Repositories\AtakModReportRepository;
use App\Repositories\AtakSendQueueRepository;
use App\Repositories\CronJobRunRepository;
use App\Repositories\UserRepository;
use App\Services\Backup\CompleteDataSnapshotService;
use App\Services\Cron\CronSchedule;
use App\Services\Ops\OpsHealthService;
use App\Services\Platform\PlatformStorageService;
use App\Support\AtakReleaseInfo;
use App\Support\Migrations\MigrationRegistry;
use App\Support\Migrations\MigrationRunLog;
use PDO;

/**
 * Bilan de santé du tableau de bord de l'administration du site : une ligne par signal,
 * chacune avec un ton (ok / warn / bad / info) et le lien vers l'outil qui la traite.
 * Une source indisponible donne une ligne « bad » : la page ne tombe jamais.
 */
final class PlatformHealthDigest
{
    public function __construct(private ?PDO $pdo = null) {}

    /**
     * @return list<array{key: string, label: string, value: string, tone: string, hint: string, href: string}>
     */
    public function rows(): array
    {
        $rows = [];
        foreach ([
            'communities' => ['Communautés', 'admin/tenants', fn () => $this->communities()],
            'accounts' => ['Comptes', 'admin/users', fn () => $this->accounts()],
            'send_queue' => ['Files d’envoi du mod', 'admin/parc-atak', fn () => $this->sendQueue()],
            'mod_version' => ['Liaison du mod en jeu (Overwatch connect)', 'admin/parc-atak', fn () => $this->modVersion()],
            'mod_reports' => ['Rapports d’erreurs du mod', 'admin/atak-mod-reports?status=new', fn () => $this->modReports()],
            'site_errors' => ['Pages en erreur (24 h)', 'admin?onglet=etat', fn () => $this->siteErrors()],
            'cron' => ['Tâches automatiques', 'admin/system/cron', fn () => $this->cron()],
            'migrations' => ['Migrations en attente', 'admin?onglet=migrations', fn () => $this->migrations()],
            'disk' => ['Espace disque', 'admin/system/storage', fn () => $this->disk()],
            'backup' => ['Dernière sauvegarde', 'admin?onglet=sauvegardes', fn () => $this->backup()],
        ] as $key => [$label, $href, $fn]) {
            try {
                [$value, $tone, $hint] = $fn() + [2 => ''];
            } catch (\Throwable) {
                [$value, $tone, $hint] = ['Indisponible', 'bad', 'Cette information ne peut pas être lue pour le moment.'];
            }
            $rows[] = ['key' => $key, 'label' => $label, 'value' => $value, 'tone' => $tone, 'hint' => $hint, 'href' => $href];
        }

        return $rows;
    }

    /**
     * @param list<array{tone: string}> $rows
     * @return array{ok: int, warn: int, bad: int, tone: string}
     */
    public static function summarize(array $rows): array
    {
        $count = ['ok' => 0, 'warn' => 0, 'bad' => 0];
        foreach ($rows as $r) {
            if (isset($count[$r['tone']])) {
                $count[$r['tone']]++;
            }
        }
        $count['tone'] = $count['bad'] > 0 ? 'bad' : ($count['warn'] > 0 ? 'warn' : 'ok');

        return $count;
    }

    /**
     * Version publiée face aux versions remontées par les joueurs en jeu.
     *
     * @param array<string, int> $online version => nombre de joueurs
     * @return array{0: string, 1: string, 2: string}
     */
    public static function modVersionSignal(string $published, array $online): array
    {
        $published = trim($published);
        unset($online['']);
        if ($online === []) {
            return [($published !== '' ? 'Publiée ' . $published . ' · ' : '') . 'aucun joueur en jeu ces dernières 24 h', 'info', ''];
        }
        uksort($online, static fn (string $a, string $b): int => version_compare($b, $a));
        $parts = [];
        $behind = 0;
        foreach ($online as $version => $n) {
            $parts[] = $version . ' (' . $n . ')';
            if ($published !== '' && version_compare((string) $version, $published, '<')) {
                $behind += $n;
            }
        }
        $value = ($published !== '' ? 'Publiée ' . $published . ' · ' : '') . 'en jeu : ' . implode(', ', array_slice($parts, 0, 4));
        if ($published === '') {
            return [$value, 'warn', 'La version publiée n’a pas pu être lue.'];
        }

        return $behind > 0
            ? [$value, 'warn', $behind . ' joueur(s) sur une version plus ancienne que la version publiée.']
            : [$value, 'ok', ''];
    }

    public static function queueTone(string $level): string
    {
        return match ($level) {
            'normal' => 'ok',
            'slow' => 'warn',
            'overloaded', 'offline' => 'bad',
            default => 'info',
        };
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    private function int(string $sql): int
    {
        return (int) $this->pdo()->query($sql)->fetchColumn();
    }

    /** @return array{0: string, 1: string} */
    private function communities(): array
    {
        $all = $this->int('SELECT COUNT(*) FROM tenants WHERE id > 1');
        $new = $this->int('SELECT COUNT(*) FROM tenants WHERE id > 1 AND created_at >= (NOW() - INTERVAL 30 DAY)');

        return [$all . ' · ' . $new . ' nouvelle(s) sur 30 jours', 'info'];
    }

    /** @return array{0: string, 1: string} */
    private function accounts(): array
    {
        $human = UserRepository::sqlLiteralExcludeTechnicalInternalEmails('u');
        $all = $this->int("SELECT COUNT(*) FROM users u WHERE {$human}");
        $new = $this->int("SELECT COUNT(*) FROM users u WHERE {$human} AND u.created_at >= (NOW() - INTERVAL 7 DAY)");

        return [$all . ' · ' . $new . ' nouveau(x) sur 7 jours', 'info'];
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function sendQueue(): array
    {
        $repo = new AtakSendQueueRepository($this->pdo());
        if (!$repo->schemaReady()) {
            return ['aucune donnée', 'info', ''];
        }
        $rows = $this->pdo()->query(
            'SELECT overload, queued, retry, failed, oldest_queued_s, p95_latency_ms, avg_latency_ms, http_429, http_5xx, timeouts,
                    UNIX_TIMESTAMP(received_at) AS received_ts
             FROM atak_send_queue_snapshots
             WHERE received_at >= (NOW() - INTERVAL ' . AtakSendQueueRepository::FRESH_SECONDS . ' SECOND) LIMIT 2000'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $s = AtakSendQueueRepository::computeStatus($rows);
        if ($s['clients'] === 0) {
            return ['personne en jeu', 'info', ''];
        }

        return [
            $s['label'] . ' · ' . $s['clients'] . ' client(s) · ' . $s['queued'] . ' en attente · ' . $s['failed'] . ' en échec',
            self::queueTone($s['level']),
            $s['level'] === 'normal' ? '' : $s['explanation'],
        ];
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function modVersion(): array
    {
        // Le mod remonte la version d'Overwatch connect (1.6.x), pas celle du téléphone ATAK (3.x) : on compare des versions de même nature.
        $published = (string) (AtakReleaseInfo::connectVersion() ?? '');
        $online = [];
        if ((new AtakSendQueueRepository($this->pdo()))->schemaReady()) {
            $st = $this->pdo()->query(
                "SELECT mod_version, COUNT(DISTINCT steam_uid) AS n FROM atak_send_queue_snapshots
                 WHERE client_kind = 'client' AND received_at >= (NOW() - INTERVAL 24 HOUR)
                 GROUP BY mod_version"
            );
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $online[trim((string) $r['mod_version'])] = (int) $r['n'];
            }
        }

        [$value, $tone, $hint] = self::modVersionSignal($published, $online);
        $atak = (string) (AtakReleaseInfo::latest()['atak_native'] ?? '');
        if ($atak !== '') {
            $hint = trim($hint . ' Téléphone ATAK publié : ' . $atak . ' (sa version n’est pas remontée ici).');
        }

        return [$value, $tone, $hint];
    }

    /** @return array{0: string, 1: string} */
    private function modReports(): array
    {
        $c = (new AtakModReportRepository($this->pdo()))->countByStatus();
        $new = (int) ($c[AtakModReportRepository::STATUS_NEW] ?? 0);
        $wip = (int) ($c[AtakModReportRepository::STATUS_IN_PROGRESS] ?? 0);

        return [$new . ' nouveau(x) · ' . $wip . ' en cours', $new > 0 ? 'warn' : 'ok'];
    }

    /** @return array{0: string, 1: string} */
    private function siteErrors(): array
    {
        $e5 = $this->int('SELECT COUNT(*) FROM request_telemetry WHERE status_code >= 500 AND created_at >= (NOW() - INTERVAL 24 HOUR)');
        $all = $this->int('SELECT COUNT(*) FROM request_telemetry WHERE created_at >= (NOW() - INTERVAL 24 HOUR)');
        $rate = $all > 0 ? round(100 * $e5 / $all, 2) : 0.0;

        return [$e5 . ' sur ' . $all . ' pages vues', $rate >= 2 ? 'bad' : ($e5 > 0 ? 'warn' : 'ok')];
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function cron(): array
    {
        $runs = new CronJobRunRepository();
        if (!$runs->runsTableExists()) {
            return ['jamais lancées', 'bad', 'Installez la crontab depuis la page Tâches automatiques.'];
        }
        $active = CronSchedule::schedulerLooksActive($runs->listRecent(60));
        $failed = $this->int("SELECT COUNT(*) FROM cron_job_runs WHERE status = 'error' AND started_at >= (NOW() - INTERVAL 24 HOUR)");
        $value = ($active ? 'actives' : 'à l’arrêt') . ' · ' . $failed . ' échec(s) sur 24 h';
        if (!$active) {
            return [$value, 'bad', 'Aucun passage depuis 10 minutes : vérifiez la crontab du serveur.'];
        }

        return [$value, $failed > 0 ? 'warn' : 'ok', ''];
    }

    /** @return array{0: string, 1: string} */
    private function migrations(): array
    {
        $steps = MigrationRegistry::load(base_path());
        $results = MigrationRunLog::latestByStep($this->pdo());
        $pending = 0;
        $failed = 0;
        foreach ($steps as $s) {
            $status = (string) ($results[(string) $s['id']]['status'] ?? '');
            $pending += $status !== 'ok' ? 1 : 0;
            $failed += $status === 'error' ? 1 : 0;
        }
        if ($pending === 0) {
            return ['aucune', 'ok'];
        }

        return [$pending . ' en attente' . ($failed > 0 ? ' dont ' . $failed . ' en échec' : ''), $failed > 0 ? 'bad' : 'warn'];
    }

    /** @return array{0: string, 1: string} */
    private function disk(): array
    {
        $d = (new PlatformStorageService())->diskSnapshot();
        $pct = $d['percent_used'] !== null ? (float) $d['percent_used'] : null;
        if ($pct === null) {
            return ['inconnu', 'warn'];
        }

        return [str_replace('.', ',', (string) $pct) . ' % occupé · ' . PlatformStorageService::formatBytes($d['free']) . ' libres', OpsHealthService::diskTone($pct)];
    }

    /** @return array{0: string, 1: string} */
    private function backup(): array
    {
        $last = CompleteDataSnapshotService::fromApp()->list()[0] ?? null;
        $ts = $last !== null ? strtotime((string) ($last['created_at'] ?? '')) : false;
        if ($ts === false) {
            return ['aucune', 'bad'];
        }
        $age = time() - $ts;

        return [OpsHealthService::formatAge($age), OpsHealthService::backupTone($age / 3600)];
    }
}
