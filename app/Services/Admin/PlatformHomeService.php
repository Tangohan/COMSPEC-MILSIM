<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Repositories\AccountPurgeRequestRepository;
use App\Repositories\AtakModReportRepository;
use App\Repositories\ContentReportRepository;
use App\Repositories\PlanPaymentRequestRepository;
use App\Repositories\UserRepository;
use App\Services\Watch\LoadLevel;
use App\Services\Watch\WatchState;
use App\Support\PlatformProductionStatus;
use App\Support\ShortCache;
use PDO;

/**
 * Page d'accueil de l'administration du site : ce qui demande une action, la santé, les chiffres clés.
 *
 * Ne renvoie que des valeurs mesurées. Chaque carte est un tableau
 * {key, state, n, extra, href} où state vaut ok, warn, bad, info ou na (source indisponible : la page affiche
 * alors « Indisponible » sans jamais tomber). Les textes sont composés par la vue, pas ici.
 */
final class PlatformHomeService
{
    public const ACTIONS_TTL = 20;
    public const HEALTH_TTL = 30;
    public const FIGURES_TTL = 300;

    /** Paliers de charge repris de la page Charge et surcharge. */
    private const LOAD_STATES = [0 => 'ok', 1 => 'warn', 2 => 'bad', 3 => 'bad'];

    public function __construct(private ?PDO $pdo = null, private ?PlatformHealthDigest $digest = null) {}

    /**
     * @return list<array{key: string, state: string, n: ?int, extra: array<string, int|string>, href: string}>
     */
    public function actions(): array
    {
        return ShortCache::remember('admin_home:actions', self::ACTIONS_TTL, fn (): array => [
            $this->card('mod_reports', 'admin/atak-mod-reports?status=new', function (): array {
                $c = (new AtakModReportRepository($this->pdo()))->countByStatus();
                $new = (int) ($c[AtakModReportRepository::STATUS_NEW] ?? 0);

                return [$new > 0 ? 'warn' : 'ok', $new, ['wip' => (int) ($c[AtakModReportRepository::STATUS_IN_PROGRESS] ?? 0)]];
            }),
            $this->card('purge', 'admin/users#demandes-purge', function (): array {
                $repo = new AccountPurgeRequestRepository($this->pdo());
                if (!$repo->tableExists()) {
                    throw new \RuntimeException('table absente');
                }
                $n = $repo->countPending();

                return [$n > 0 ? 'warn' : 'ok', $n, []];
            }),
            $this->card('payments', 'admin/paiements', function (): array {
                $repo = new PlanPaymentRequestRepository($this->pdo());
                if (!$repo->schemaReady()) {
                    throw new \RuntimeException('table absente');
                }
                $s = $repo->declaredSummary();
                $ts = $s['oldest'] !== null ? strtotime($s['oldest']) : false;
                $days = $ts !== false ? max(0, intdiv(time() - $ts, 86400)) : 0;

                return [$s['count'] > 0 ? 'warn' : 'ok', $s['count'], ['days' => $days]];
            }),
            $this->card('migrations', 'admin?onglet=migrations', function (): array {
                $m = ($this->digest ??= new PlatformHealthDigest($this->pdo))->migrationCounts();

                return [$m['failed'] > 0 ? 'bad' : ($m['pending'] > 0 ? 'warn' : 'ok'), $m['pending'], ['failed' => $m['failed']]];
            }),
            $this->abuseCard(),
            $this->card('reports', 'back-office/signalements', function (): array {
                $n = (new ContentReportRepository())->countPendingAllTenants();

                return [$n > 0 ? 'warn' : 'ok', $n, []];
            }),
        ]);
    }

    /**
     * @return list<array{key: string, state: string, n: ?int, extra: array<string, int|string>, href: string}>
     */
    public function health(): array
    {
        return ShortCache::remember('admin_home:health', self::HEALTH_TTL, function (): array {
            $cards = [];
            $rows = [];
            try {
                $rows = ($this->digest ??= new PlatformHealthDigest($this->pdo))->rows(['send_queue', 'mod_version', 'site_errors']);
            } catch (\Throwable) {
            }
            $byKey = [];
            foreach ($rows as $r) {
                $byKey[$r['key']] = $r;
            }
            foreach (['send_queue' => 'admin/parc-atak', 'mod_version' => 'admin/parc-atak'] as $key => $href) {
                $cards[] = self::fromDigestRow($key, $href, $byKey[$key] ?? null);
            }
            $cards[] = $this->card('load', 'admin/system/surcharge', function (): array {
                $now = time();
                $state = WatchState::raw();
                if ($state === null) {
                    throw new \RuntimeException('aucune mesure');
                }
                // Mesure trop ancienne ou capteur peu fiable : on ne l'affiche pas comme « Normal ».
                if ((int) WatchState::ageSeconds($state, $now) > LoadLevel::MAX_AGE_SECONDS || empty($state['sensor']['reliable'])) {
                    throw new \RuntimeException('mesure périmée');
                }
                $level = WatchState::measuredLevel($state, $now);

                return [self::LOAD_STATES[$level] ?? 'ok', $level, []];
            });
            $cards[] = self::fromDigestRow('site_errors', 'admin?onglet=etat', $byKey['site_errors'] ?? null);
            $deploy = $this->deployCard();
            if ($deploy !== null) {
                $cards[] = $deploy;
            }

            return $cards;
        });
    }

    /**
     * @return list<array{key: string, state: string, n: ?int, extra: array<string, int|string>, href: string}>
     */
    public function figures(): array
    {
        return ShortCache::remember('admin_home:figures', self::FIGURES_TTL, fn (): array => [
            $this->card('communities', 'admin/tenants', function (): array {
                $new = $this->int('SELECT COUNT(*) FROM tenants WHERE id > 1 AND created_at >= (NOW() - INTERVAL 30 DAY)');
                $before = $this->int('SELECT COUNT(*) FROM tenants WHERE id > 1 AND created_at >= (NOW() - INTERVAL 60 DAY) AND created_at < (NOW() - INTERVAL 30 DAY)');

                return ['info', $this->int('SELECT COUNT(*) FROM tenants WHERE id > 1'), ['new' => $new, 'before' => $before]];
            }),
            $this->card('active', 'admin/users', function (): array {
                $human = UserRepository::sqlLiteralExcludeTechnicalInternalEmails('u');
                $active = $this->int("SELECT COUNT(*) FROM users u WHERE {$human} AND u.last_login_at IS NOT NULL AND u.last_login_at >= (NOW() - INTERVAL 30 DAY)");
                $new = $this->int("SELECT COUNT(*) FROM users u WHERE {$human} AND u.created_at >= (NOW() - INTERVAL 30 DAY)");
                $before = $this->int("SELECT COUNT(*) FROM users u WHERE {$human} AND u.created_at >= (NOW() - INTERVAL 60 DAY) AND u.created_at < (NOW() - INTERVAL 30 DAY)");

                return ['info', $active, ['new' => $new, 'before' => $before]];
            }),
            $this->card('inactive', 'admin/users?inactif=90', function (): array {
                $in = (new PlatformAccountsOverviewService($this->pdo))->inactivity(0);
                if (!$in['available']) {
                    throw new \RuntimeException('inactivité non mesurée');
                }

                return ['info', (int) ($in['buckets']['90'] ?? 0), ['never' => (int) ($in['buckets']['jamais'] ?? 0)]];
            }),
            $this->card('subscriptions', 'admin/paiements', function (): array {
                return ['info', $this->int("SELECT COUNT(*) FROM tenants WHERE id > 1 AND subscription_status IN ('active', 'trialing')"), []];
            }),
        ]);
    }

    /** @return list<array{key: string, href: string}> */
    public static function quickLinks(): array
    {
        return [
            ['key' => 'users', 'href' => 'admin/users'],
            ['key' => 'tenants', 'href' => 'admin/tenants'],
            ['key' => 'plans', 'href' => 'admin/system/subscription-plans'],
            ['key' => 'payments', 'href' => 'admin/paiements'],
        ];
    }

    /** @return array{key: string, state: string, n: ?int, extra: array<string, int|string>, href: string} */
    private function abuseCard(): array
    {
        return $this->card('abuse', 'admin/system/alertes-mod', function (): array {
            $st = $this->pdo()->query("SELECT kind, COUNT(*) AS n FROM platform_abuse_alerts WHERE status = 'open' GROUP BY kind");
            $by = ['mod' => 0, 'load' => 0];
            foreach ($st ? $st->fetchAll(PDO::FETCH_ASSOC) : [] as $r) {
                $by[(string) $r['kind']] = (int) $r['n'];
            }
            $total = array_sum($by);

            return [$total > 0 ? 'warn' : 'ok', $total, ['mod' => $by['mod'], 'load' => $by['load']]];
        });
    }

    /** @return array{key: string, state: string, n: ?int, extra: array<string, int|string>, href: string}|null */
    private function deployCard(): ?array
    {
        try {
            $prod = ShortCache::remember('admin_home:deploy', 300, static fn (): array => PlatformProductionStatus::snapshot());
            $at = (string) ($prod['deployed_at'] ?? '');
            if ($at === '' || strtotime($at) === false) {
                return null;
            }

            return [
                'key' => 'deploy', 'state' => 'info', 'n' => null, 'href' => 'admin/system/updates',
                'extra' => ['at' => (string) ($prod['deployed_at_label'] ?? ''), 'version' => (string) ($prod['platform_version'] ?? '')],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array{key: string, label: string, value: string, tone: string, hint: string, href: string}|null $row
     * @return array{key: string, state: string, n: ?int, extra: array<string, int|string>, href: string}
     */
    private static function fromDigestRow(string $key, string $href, ?array $row): array
    {
        if ($row === null || $row['value'] === 'Indisponible') {
            return ['key' => $key, 'state' => 'na', 'n' => null, 'extra' => [], 'href' => $href];
        }

        return ['key' => $key, 'state' => $row['tone'], 'n' => null, 'extra' => ['value' => $row['value'], 'hint' => $row['hint']], 'href' => $href];
    }

    /**
     * Une source qui échoue donne une carte « na », jamais une exception.
     *
     * @param callable(): array{0: string, 1: ?int, 2: array<string, int|string>} $read
     * @return array{key: string, state: string, n: ?int, extra: array<string, int|string>, href: string}
     */
    private function card(string $key, string $href, callable $read): array
    {
        try {
            [$state, $n, $extra] = $read();

            return ['key' => $key, 'state' => $state, 'n' => $n, 'extra' => $extra, 'href' => $href];
        } catch (\Throwable) {
            return ['key' => $key, 'state' => 'na', 'n' => null, 'extra' => [], 'href' => $href];
        }
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    private function int(string $sql): int
    {
        return (int) $this->pdo()->query($sql)->fetchColumn();
    }
}
