<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Support\Migrations\SchemaInspector;
use PDO;

/**
 * Volume de données reçu du jeu, jour par jour, pour l’administration du site.
 *
 * Ce que mesure chaque chiffre (aucun chiffre n’est estimé ni inventé) :
 *  - par communauté : `atak_ingest_traffic`, taille des messages que le mod envoie au site (octets reçus),
 *    dont la part des photos ; compté par tranches de 10 secondes puis additionné par jour ;
 *  - par personne : `atak_ingest_player_daily`, même mesure, par joueur et par jour (Steam ID relié au compte) ;
 *  - photos : nombre de clichés de reconnaissance enregistrés (`recon_images`), jamais leur poids : le site
 *    ne garde pas la taille d’un fichier.
 * Les jours vont de 00 h à 24 h UTC. Rien n’est mesuré pour les documents, les messages ou la base de données.
 */
final class PlatformDataVolumeService
{
    /** Périodes proposées (jours). La plus longue borne le travail demandé à la base. */
    public const PERIODS = [7, 14, 31];

    public const DEFAULT_PERIOD = 7;

    /** Nombre maximal de lignes (communautés ou personnes) affichées : les plus gourmandes d’abord. */
    public const MAX_ROWS = 100;

    /** @var callable(): int */
    private $clock;

    public function __construct(private ?PDO $pdo = null, ?callable $clock = null)
    {
        $this->clock = $clock ?? static fn (): int => time();
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public static function normalizePeriod(int|string|null $days): int
    {
        $days = (int) $days;

        return in_array($days, self::PERIODS, true) ? $days : self::DEFAULT_PERIOD;
    }

    /** « 1,4 Mo » : unités décimales lisibles, une décimale au-delà du kilo-octet. */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1) {
            return '0 o';
        }
        if ($bytes < 1000) {
            return $bytes . ' o';
        }
        $units = ['Ko', 'Mo', 'Go', 'To'];
        $value = (float) $bytes;
        $unit = 'o';
        foreach ($units as $candidate) {
            $value /= 1000;
            $unit = $candidate;
            if ($value < 1000) {
                break;
            }
        }

        return number_format($value, $value < 10 ? 1 : 0, ',', "\u{202F}") . ' ' . $unit;
    }

    /**
     * Jours de la période, du plus ancien au plus récent, au format Y-m-d (UTC), aujourd’hui compris.
     *
     * @return list<string>
     */
    public function days(int $period): array
    {
        $period = self::normalizePeriod($period);
        $today = (int) floor(($this->clock)() / 86400) * 86400;
        $out = [];
        for ($i = $period - 1; $i >= 0; $i--) {
            $out[] = gmdate('Y-m-d', $today - $i * 86400);
        }

        return $out;
    }

    /** Ce qui existe réellement dans cette base : une source absente n’est jamais remplacée par un zéro. */
    public function sources(): array
    {
        $schema = new SchemaInspector($this->pdo());

        return [
            'communities' => $schema->tableExists('atak_ingest_traffic'),
            'players' => $schema->tableExists('atak_ingest_player_daily'),
            'photos' => $schema->tableExists('recon_images'),
        ];
    }

    /**
     * Total par jour, toutes communautés confondues (ou une seule).
     *
     * @return array<string, array{bytes: int, photo_bytes: int, requests: int, communities: int, photos: int|null}>
     */
    public function dailyTotals(int $period, ?int $tenantId = null): array
    {
        $days = $this->days($period);
        $out = [];
        foreach ($days as $day) {
            $out[$day] = ['bytes' => 0, 'photo_bytes' => 0, 'requests' => 0, 'communities' => 0, 'photos' => null];
        }
        $sources = $this->sources();
        if ($sources['communities']) {
            [$from, $to] = $this->window($days);
            $tenant = $tenantId !== null && $tenantId > 0 ? ' AND tenant_id = ?' : '';
            $st = $this->pdo()->prepare(
                "SELECT DATE(bucket_ts) AS d, SUM(bytes) AS b, SUM(photo_bytes) AS pb, SUM(requests) AS r, COUNT(DISTINCT tenant_id) AS c
                 FROM atak_ingest_traffic
                 WHERE bucket_ts >= ? AND bucket_ts < ?{$tenant}
                 GROUP BY DATE(bucket_ts)"
            );
            $st->execute($tenant !== '' ? [$from, $to, $tenantId] : [$from, $to]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $d = (string) $row['d'];
                if (isset($out[$d])) {
                    $out[$d]['bytes'] = (int) $row['b'];
                    $out[$d]['photo_bytes'] = (int) $row['pb'];
                    $out[$d]['requests'] = (int) $row['r'];
                    $out[$d]['communities'] = (int) $row['c'];
                }
            }
        }
        if ($sources['photos']) {
            foreach ($days as $day) {
                $out[$day]['photos'] = 0;
            }
            [$from, $to] = $this->window($days);
            $schema = new SchemaInspector($this->pdo());
            $alive = $schema->columnExists('recon_images', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
            $tenant = $tenantId !== null && $tenantId > 0 ? ' AND tenant_id = ?' : '';
            $st = $this->pdo()->prepare(
                "SELECT DATE(created_at) AS d, COUNT(*) AS n FROM recon_images
                 WHERE created_at >= ? AND created_at < ?{$alive}{$tenant}
                 GROUP BY DATE(created_at)"
            );
            $st->execute($tenant !== '' ? [$from, $to, $tenantId] : [$from, $to]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $d = (string) $row['d'];
                if (isset($out[$d])) {
                    $out[$d]['photos'] = (int) $row['n'];
                }
            }
        }

        return $out;
    }

    /**
     * Une ligne par communauté (les plus gourmandes d’abord), une colonne par jour.
     *
     * @return array{days: list<string>, rows: list<array<string, mixed>>, truncated: bool}
     */
    public function byCommunity(int $period, ?int $tenantId = null): array
    {
        $days = $this->days($period);
        if (!$this->sources()['communities']) {
            return ['days' => $days, 'rows' => [], 'truncated' => false];
        }
        [$from, $to] = $this->window($days);
        $tenant = $tenantId !== null && $tenantId > 0 ? ' AND x.tenant_id = ?' : '';
        $st = $this->pdo()->prepare(
            "SELECT x.tenant_id, DATE(x.bucket_ts) AS d, SUM(x.bytes) AS b, SUM(x.photo_bytes) AS pb, SUM(x.requests) AS r
             FROM atak_ingest_traffic x
             WHERE x.bucket_ts >= ? AND x.bucket_ts < ?{$tenant}
             GROUP BY x.tenant_id, DATE(x.bucket_ts)"
        );
        $st->execute($tenant !== '' ? [$from, $to, $tenantId] : [$from, $to]);
        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $id = (int) $row['tenant_id'];
            $rows[$id] ??= ['tenant_id' => $id, 'name' => '', 'slug' => '', 'total' => 0, 'photo_bytes' => 0, 'requests' => 0, 'cells' => array_fill_keys($days, 0)];
            $d = (string) $row['d'];
            if (isset($rows[$id]['cells'][$d])) {
                $rows[$id]['cells'][$d] = (int) $row['b'];
            }
            $rows[$id]['total'] += (int) $row['b'];
            $rows[$id]['photo_bytes'] += (int) $row['pb'];
            $rows[$id]['requests'] += (int) $row['r'];
        }
        uasort($rows, static fn (array $a, array $b): int => [$b['total'], $a['tenant_id']] <=> [$a['total'], $b['tenant_id']]);
        $truncated = count($rows) > self::MAX_ROWS;
        $rows = array_slice(array_values($rows), 0, self::MAX_ROWS);
        $this->nameCommunities($rows);

        return ['days' => $days, 'rows' => $rows, 'truncated' => $truncated];
    }

    /**
     * Une ligne par personne (les plus gourmandes d’abord), une colonne par jour. Les joueurs dont le Steam ID
     * n’est relié à aucun compte restent visibles sous leur indicatif, marqués « non relié ».
     *
     * @return array{days: list<string>, rows: list<array<string, mixed>>, truncated: bool}
     */
    public function byAccount(int $period, ?int $tenantId = null): array
    {
        $days = $this->days($period);
        if (!$this->sources()['players']) {
            return ['days' => $days, 'rows' => [], 'truncated' => false];
        }
        $tenant = $tenantId !== null && $tenantId > 0 ? ' AND tenant_id = ?' : '';
        $params = [$days[0], $days[count($days) - 1]];
        if ($tenant !== '') {
            $params[] = $tenantId;
        }
        // Classement d’abord (une requête bornée), détail par jour seulement pour les premiers.
        $st = $this->pdo()->prepare(
            'SELECT steam_uid, SUM(bytes) AS b FROM atak_ingest_player_daily
             WHERE day >= ? AND day <= ?' . $tenant . '
             GROUP BY steam_uid ORDER BY b DESC, steam_uid ASC LIMIT ' . (self::MAX_ROWS + 1)
        );
        $st->execute($params);
        $top = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $truncated = count($top) > self::MAX_ROWS;
        $uids = array_map(static fn (array $r): string => (string) $r['steam_uid'], array_slice($top, 0, self::MAX_ROWS));
        if ($uids === []) {
            return ['days' => $days, 'rows' => [], 'truncated' => false];
        }
        $in = implode(',', array_fill(0, count($uids), '?'));
        $st = $this->pdo()->prepare(
            "SELECT steam_uid, day, SUM(bytes) AS b, SUM(photo_bytes) AS pb, SUM(requests) AS r, SUM(events_accepted) AS ea, MAX(callsign) AS cs
             FROM atak_ingest_player_daily
             WHERE day >= ? AND day <= ?{$tenant} AND steam_uid IN ({$in})
             GROUP BY steam_uid, day"
        );
        $st->execute(array_merge($params, $uids));
        $byUid = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $uid = (string) $row['steam_uid'];
            $byUid[$uid] ??= ['uid' => $uid, 'callsign' => '', 'total' => 0, 'photo_bytes' => 0, 'requests' => 0, 'events' => 0, 'cells' => array_fill_keys($days, 0)];
            $d = (string) $row['day'];
            if (isset($byUid[$uid]['cells'][$d])) {
                $byUid[$uid]['cells'][$d] = (int) $row['b'];
            }
            $byUid[$uid]['total'] += (int) $row['b'];
            $byUid[$uid]['photo_bytes'] += (int) $row['pb'];
            $byUid[$uid]['requests'] += (int) $row['r'];
            $byUid[$uid]['events'] += (int) $row['ea'];
            if ($byUid[$uid]['callsign'] === '' && trim((string) $row['cs']) !== '') {
                $byUid[$uid]['callsign'] = trim((string) $row['cs']);
            }
        }
        $accounts = $this->accountsBySteam(array_keys($byUid));
        $rows = [];
        foreach ($byUid as $uid => $line) {
            $account = $accounts[$uid] ?? null;
            // Un compte peut avoir plusieurs Steam ID : leurs lignes sont additionnées sous la même personne.
            $key = $account !== null ? 'acc:' . $account['email_key'] : 'uid:' . $uid;
            if (!isset($rows[$key])) {
                $label = $account !== null
                    ? ($account['callsign'] !== '' ? $account['callsign'] : ($account['display_name'] !== '' ? $account['display_name'] : 'Compte sans nom'))
                    : ($line['callsign'] !== '' && !str_starts_with($line['callsign'], 'cs:') ? $line['callsign'] : 'Joueur inconnu');
                $rows[$key] = [
                    'label' => $label,
                    'sub' => $account !== null ? $account['display_name'] : '',
                    'email_key' => $account['email_key'] ?? '',
                    'linked' => $account !== null,
                    'total' => 0, 'photo_bytes' => 0, 'requests' => 0, 'events' => 0,
                    'cells' => array_fill_keys($days, 0),
                ];
            }
            foreach ($days as $d) {
                $rows[$key]['cells'][$d] += $line['cells'][$d];
            }
            foreach (['total', 'photo_bytes', 'requests', 'events'] as $k) {
                $rows[$key][$k] += $line[$k];
            }
        }
        uasort($rows, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return ['days' => $days, 'rows' => array_values($rows), 'truncated' => $truncated];
    }

    /**
     * Volume reçu du jeu pour des Steam ID, sur les N derniers jours (colonne « Données » de l’annuaire).
     *
     * @param list<string> $steamIds
     * @return array<string, int> steam_id => octets
     */
    public function bytesBySteam(array $steamIds, int $days = 30): array
    {
        $steamIds = array_values(array_unique(array_filter(array_map(static fn ($s): string => trim((string) $s), $steamIds), static fn (string $s): bool => $s !== '')));
        if ($steamIds === [] || !$this->sources()['players']) {
            return [];
        }
        $days = max(1, min(31, $days));
        $since = gmdate('Y-m-d', ($this->clock)() - ($days - 1) * 86400);
        $out = [];
        foreach (array_chunk($steamIds, 200) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $st = $this->pdo()->prepare(
                "SELECT steam_uid, SUM(bytes) AS b FROM atak_ingest_player_daily WHERE day >= ? AND steam_uid IN ({$in}) GROUP BY steam_uid"
            );
            $st->execute(array_merge([$since], $chunk));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $out[(string) $row['steam_uid']] = (int) $row['b'];
            }
        }

        return $out;
    }

    /**
     * @param list<string> $days
     * @return array{0: string, 1: string} début inclus, fin exclue (UTC)
     */
    private function window(array $days): array
    {
        $last = (int) strtotime($days[count($days) - 1] . ' 00:00:00 UTC');

        return [$days[0] . ' 00:00:00', gmdate('Y-m-d H:i:s', $last + 86400)];
    }

    /**
     * @param list<string> $steamIds
     * @return array<string, array{email_key: string, display_name: string, callsign: string}>
     */
    private function accountsBySteam(array $steamIds): array
    {
        $steamIds = array_values(array_filter($steamIds, static fn (string $s): bool => $s !== '' && !str_starts_with($s, 'cs:')));
        if ($steamIds === []) {
            return [];
        }
        $schema = new SchemaInspector($this->pdo());
        $alive = $schema->columnExists('users', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        $in = implode(',', array_fill(0, count($steamIds), '?'));
        $st = $this->pdo()->prepare(
            "SELECT steam_id, email, display_name, callsign FROM users
             WHERE steam_id IN ({$in}){$alive} AND email NOT LIKE '%@deleted.invalid' AND email NOT LIKE '%@merged.invalid'
             ORDER BY id ASC"
        );
        $st->execute($steamIds);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $uid = (string) $row['steam_id'];
            if (isset($out[$uid])) {
                continue;
            }
            $out[$uid] = [
                'email_key' => strtolower(trim((string) $row['email'])),
                'display_name' => trim((string) ($row['display_name'] ?? '')),
                'callsign' => trim((string) ($row['callsign'] ?? '')),
            ];
        }

        return $out;
    }

    /** @param list<array<string, mixed>> $rows */
    private function nameCommunities(array &$rows): void
    {
        $ids = array_values(array_unique(array_map(static fn (array $r): int => (int) $r['tenant_id'], $rows)));
        if ($ids === []) {
            return;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo()->prepare("SELECT id, name, slug FROM tenants WHERE id IN ({$in})");
        $st->execute($ids);
        $names = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $t) {
            $names[(int) $t['id']] = $t;
        }
        foreach ($rows as &$row) {
            $t = $names[(int) $row['tenant_id']] ?? null;
            $row['name'] = $t !== null ? (string) $t['name'] : 'Communauté supprimée';
            $row['slug'] = $t !== null ? (string) $t['slug'] : '';
        }
        unset($row);
    }
}
