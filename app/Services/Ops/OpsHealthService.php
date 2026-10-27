<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Core\Database;
use App\Repositories\CronJobRunRepository;
use App\Services\Backup\CompleteDataSnapshotService;
use App\Services\Backup\MysqlSnapshotIo;
use App\Services\Platform\PlatformStorageService;
use App\Services\Cron\CronSchedule;
use App\Support\PlatformProductionStatus;
use PDO;

/**
 * État du site pour la console d'exploitation : chaque contrôle donne une valeur lisible,
 * un ton (ok / warn / bad / info) et, s'il y a lieu, quoi faire.
 *
 * Aucun contrôle ne fait tomber la page : une source indisponible devient un contrôle « bad ».
 */
final class OpsHealthService
{
    /** Dossiers où PHP doit pouvoir écrire. */
    public const WRITABLE_DIRS = [
        'storage' => 'Stockage',
        'storage/logs' => 'Journaux',
        'storage/cache' => 'Cache',
        'storage/uploads' => 'Fichiers envoyés',
        'public/uploads' => 'Images publiques',
    ];

    public function __construct(
        private ?PDO $pdo = null,
        private ?PlatformStorageService $storage = null,
        private ?CronJobRunRepository $cronRuns = null,
        private ?CompleteDataSnapshotService $snapshots = null,
        private ?MysqlSnapshotIo $mysql = null,
    ) {}

    /**
     * @return list<array{key: string, title: string, items: list<array{label: string, value: string, tone: string, hint?: string}>}>
     */
    public function sections(): array
    {
        return [
            ['key' => 'app', 'title' => 'Application', 'items' => $this->guard(fn () => $this->application())],
            ['key' => 'db', 'title' => 'Base de données', 'items' => $this->guard(fn () => $this->database())],
            ['key' => 'disk', 'title' => 'Disque et dossiers', 'items' => $this->guard(fn () => $this->disk())],
            ['key' => 'jobs', 'title' => 'Tâches automatiques et file d’attente', 'items' => $this->guard(fn () => $this->jobs())],
            ['key' => 'errors', 'title' => 'Erreurs', 'items' => $this->guard(fn () => $this->errors())],
            ['key' => 'backup', 'title' => 'Sauvegardes', 'items' => $this->guard(fn () => $this->backups())],
        ];
    }

    /**
     * @param list<array{key: string, title: string, items: list<array<string, string>>}> $sections
     * @return array{ok: int, warn: int, bad: int, tone: string}
     */
    public static function summarize(array $sections): array
    {
        $count = ['ok' => 0, 'warn' => 0, 'bad' => 0];
        foreach ($sections as $s) {
            foreach ($s['items'] as $i) {
                $t = (string) ($i['tone'] ?? 'info');
                if (isset($count[$t])) {
                    $count[$t]++;
                }
            }
        }
        $count['tone'] = $count['bad'] > 0 ? 'bad' : ($count['warn'] > 0 ? 'warn' : 'ok');

        return $count;
    }

    /** Ton d'un taux d'occupation du disque (en %). */
    public static function diskTone(?float $percentUsed): string
    {
        if ($percentUsed === null) {
            return 'warn';
        }

        return $percentUsed >= 92 ? 'bad' : ($percentUsed >= 80 ? 'warn' : 'ok');
    }

    /** Ton de l'âge de la dernière sauvegarde (en heures, null = aucune). */
    public static function backupTone(?float $ageHours): string
    {
        if ($ageHours === null) {
            return 'bad';
        }

        return $ageHours > 24 * 7 ? 'bad' : ($ageHours > 48 ? 'warn' : 'ok');
    }

    public static function formatAge(int $seconds): string
    {
        if ($seconds < 90) {
            return 'il y a ' . max(0, $seconds) . ' s';
        }
        if ($seconds < 5400) {
            return 'il y a ' . (int) round($seconds / 60) . ' min';
        }
        if ($seconds < 172800) {
            return 'il y a ' . (int) round($seconds / 3600) . ' h';
        }

        return 'il y a ' . (int) round($seconds / 86400) . ' j';
    }

    /**
     * @param callable(): list<array<string, string>> $fn
     * @return list<array<string, string>>
     */
    private function guard(callable $fn): array
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return [['label' => 'Contrôle', 'value' => 'Indisponible', 'tone' => 'bad', 'hint' => $e->getMessage()]];
        }
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /** @return list<array<string, string>> */
    private function application(): array
    {
        $prod = PlatformProductionStatus::snapshot();
        $debug = filter_var((string) env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
        $items = [
            ['label' => 'Version du site', 'value' => trim($prod['platform_version'] . ' ' . $prod['codename_label']), 'tone' => 'info'],
            ['label' => 'Code déployé', 'value' => ($prod['git_sha'] !== '' ? $prod['git_sha'] . ' · ' : '') . $prod['deployed_at_label'], 'tone' => $prod['git_sha'] !== '' ? 'ok' : 'warn',
                'hint' => 'Date du dernier commit présent sur le serveur (git pull ou action « Deploy VPS »).'],
            ['label' => 'Mods publiés', 'value' => $prod['pack_label'], 'tone' => 'info'],
            ['label' => 'PHP', 'value' => PHP_VERSION . ' · mémoire ' . (string) ini_get('memory_limit') . ' · envoi ' . (string) ini_get('upload_max_filesize'),
                'tone' => version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'warn'],
            ['label' => 'Cache de code (OPcache)', 'value' => function_exists('opcache_get_status') && @opcache_get_status(false) ? 'actif' : 'inactif',
                'tone' => function_exists('opcache_get_status') && @opcache_get_status(false) ? 'ok' : 'warn'],
            ['label' => 'Mode débogage', 'value' => $debug ? 'activé' : 'désactivé', 'tone' => $debug ? 'warn' : 'ok',
                'hint' => $debug ? 'APP_DEBUG=true affiche le détail des erreurs aux visiteurs : à couper en production.' : ''],
            ['label' => 'Adresse du site', 'value' => (string) env('APP_URL', ''), 'tone' => 'info'],
        ];
        $missing = array_values(array_filter(['pdo_mysql', 'mbstring', 'json', 'curl', 'gd', 'zip', 'openssl'], static fn (string $e): bool => !extension_loaded($e)));
        $items[] = ['label' => 'Extensions PHP', 'value' => $missing === [] ? 'toutes présentes' : 'manquantes : ' . implode(', ', $missing), 'tone' => $missing === [] ? 'ok' : 'warn'];

        return $items;
    }

    /** @return list<array<string, string>> */
    private function database(): array
    {
        $pdo = $this->pdo();
        $t0 = microtime(true);
        $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $ms = (int) round((microtime(true) - $t0) * 1000);
        $row = $pdo->query(
            'SELECT COUNT(*) AS n, COALESCE(SUM(data_length + index_length), 0) AS bytes FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $users = $this->scalar('SELECT COUNT(*) FROM users');
        $tenants = $this->scalar('SELECT COUNT(*) FROM tenants');

        return [
            ['label' => 'Temps de réponse', 'value' => 'répond en ' . $ms . ' ms', 'tone' => $ms > 500 ? 'warn' : 'ok'],
            ['label' => 'Serveur', 'value' => $version, 'tone' => 'info'],
            ['label' => 'Taille', 'value' => PlatformStorageService::formatBytes((int) ($row['bytes'] ?? 0)) . ' · ' . (int) ($row['n'] ?? 0) . ' tables', 'tone' => 'info'],
            ['label' => 'Comptes · communautés', 'value' => ($users ?? '—') . ' · ' . ($tenants ?? '—'), 'tone' => 'info'],
        ];
    }

    /** @return list<array<string, string>> */
    private function disk(): array
    {
        $this->storage ??= new PlatformStorageService();
        $d = $this->storage->diskSnapshot();
        $pct = $d['percent_used'] !== null ? (float) $d['percent_used'] : null;
        $items = [[
            'label' => 'Disque du serveur',
            'value' => $pct === null ? 'inconnu' : str_replace('.', ',', (string) $pct) . ' % occupé · ' . PlatformStorageService::formatBytes($d['free']) . ' libres',
            'tone' => self::diskTone($pct),
            'hint' => $pct !== null && $pct >= 80 ? 'Videz les historiques (Espace disque) ou retirez d’anciennes sauvegardes.' : '',
        ]];
        $notWritable = [];
        foreach (self::WRITABLE_DIRS as $rel => $label) {
            $abs = base_path($rel);
            if (is_dir($abs) && !is_writable($abs)) {
                $notWritable[] = $label . ' (' . $rel . ')';
            }
        }
        $items[] = [
            'label' => 'Droits d’écriture',
            'value' => $notWritable === [] ? 'tous les dossiers sont accessibles' : 'bloqués : ' . implode(', ', $notWritable),
            'tone' => $notWritable === [] ? 'ok' : 'bad',
            'hint' => $notWritable === [] ? '' : 'Sur le VPS : chown -R www-data:www-data storage public/uploads',
        ];

        return $items;
    }

    /** @return list<array<string, string>> */
    private function jobs(): array
    {
        $this->cronRuns ??= new CronJobRunRepository();
        $items = [];
        if (!$this->cronRuns->runsTableExists()) {
            return [['label' => 'Tâches automatiques', 'value' => 'jamais lancées', 'tone' => 'bad', 'hint' => 'Installez la crontab (Tâches automatiques).']];
        }
        $recent = $this->cronRuns->listRecent(60);
        $active = CronSchedule::schedulerLooksActive($recent);
        $last = $recent[0] ?? null;
        $lastTs = $last !== null ? strtotime((string) ($last['finished_at'] ?? $last['started_at'] ?? '') . ' UTC') : false;
        $items[] = [
            'label' => 'Planificateur',
            'value' => ($active ? 'actif' : 'à l’arrêt') . ($lastTs ? ' · dernier passage ' . self::formatAge(time() - (int) $lastTs) : ''),
            'tone' => $active ? 'ok' : 'bad',
            'hint' => $active ? '' : 'Aucun passage depuis 10 minutes : vérifiez la crontab du VPS.',
        ];
        $failed = (int) ($this->scalar("SELECT COUNT(*) FROM cron_job_runs WHERE status = 'error' AND started_at >= (NOW() - INTERVAL 24 HOUR)") ?? 0);
        $items[] = ['label' => 'Tâches en échec (24 h)', 'value' => (string) $failed, 'tone' => $failed > 0 ? 'warn' : 'ok'];

        $pending = $this->scalar('SELECT COUNT(*) FROM async_jobs WHERE reserved_at IS NULL');
        $stuck = $this->scalar('SELECT COUNT(*) FROM async_jobs WHERE attempts >= 3');
        if ($pending !== null) {
            $items[] = ['label' => 'File d’attente', 'value' => $pending . ' en attente · ' . (int) $stuck . ' bloqué(s) après 3 essais',
                'tone' => (int) $stuck > 0 ? 'warn' : ((int) $pending > 200 ? 'warn' : 'ok')];
        }
        $maint = $this->scalar('SELECT COUNT(*) FROM app_maintenance WHERE is_enabled = 1');
        if ($maint !== null) {
            $items[] = ['label' => 'Maintenance', 'value' => (int) $maint > 0 ? 'en cours (' . (int) $maint . ')' : 'aucune', 'tone' => (int) $maint > 0 ? 'warn' : 'ok'];
        }

        return $items;
    }

    /** @return list<array<string, string>> */
    private function errors(): array
    {
        $items = [];
        $e5 = $this->scalar('SELECT COUNT(*) FROM request_telemetry WHERE status_code >= 500 AND created_at >= (NOW() - INTERVAL 24 HOUR)');
        $all = $this->scalar('SELECT COUNT(*) FROM request_telemetry WHERE created_at >= (NOW() - INTERVAL 24 HOUR)');
        if ($e5 !== null) {
            $rate = (int) $all > 0 ? round(100 * (int) $e5 / (int) $all, 2) : 0.0;
            $items[] = ['label' => 'Pages en erreur (24 h)', 'value' => $e5 . ' sur ' . (int) $all . ' requêtes (' . str_replace('.', ',', (string) $rate) . ' %)',
                'tone' => $rate >= 2 ? 'bad' : ((int) $e5 > 0 ? 'warn' : 'ok')];
        }
        $log = base_path('storage/logs/app.log');
        if (is_file($log)) {
            $age = time() - (int) filemtime($log);
            $items[] = ['label' => 'Journal d’erreurs', 'value' => PlatformStorageService::formatBytes((int) filesize($log)) . ' · dernière écriture ' . self::formatAge($age),
                'tone' => $age < 3600 ? 'warn' : 'ok'];
        } else {
            $items[] = ['label' => 'Journal d’erreurs', 'value' => 'vide', 'tone' => 'ok'];
        }

        return $items;
    }

    /** @return list<array<string, string>> */
    private function backups(): array
    {
        $this->snapshots ??= CompleteDataSnapshotService::fromApp();
        $this->mysql ??= new MysqlSnapshotIo();
        $list = $this->snapshots->list();
        $last = $list[0] ?? null;
        $ageH = null;
        if ($last !== null && ($ts = strtotime((string) ($last['created_at'] ?? ''))) !== false) {
            $ageH = (time() - $ts) / 3600;
        }
        $root = $this->snapshots->snapshotRoot();
        $dump = $this->mysql->which('mysqldump');

        return [
            ['label' => 'Dernière sauvegarde', 'value' => $last === null ? 'aucune' : self::formatAge((int) round($ageH * 3600)) . ' · ' . count($list) . ' conservée(s)',
                'tone' => self::backupTone($ageH), 'hint' => $ageH === null || $ageH > 48 ? 'Créez une sauvegarde depuis l’onglet Sauvegardes.' : ''],
            ['label' => 'Dossier des sauvegardes', 'value' => $root, 'tone' => (is_dir($root) ? is_writable($root) : is_writable(dirname($root))) ? 'ok' : 'bad',
                'hint' => 'Hors du dossier public. Réglable avec DATA_SNAPSHOT_DIR dans .env.'],
            ['label' => 'Outil de copie de la base', 'value' => $dump !== null ? 'mysqldump (' . $dump . ')' : 'copie en PHP (mysqldump absent)', 'tone' => $dump !== null ? 'ok' : 'warn',
                'hint' => $dump !== null ? '' : 'Plus lent sur une grosse base. Sur le VPS : apt install mariadb-client (ou mysql-client).'],
        ];
    }

    private function scalar(string $sql): ?string
    {
        try {
            $v = $this->pdo()->query($sql)->fetchColumn();

            return $v === false ? null : (string) $v;
        } catch (\Throwable) {
            return null;
        }
    }
}
