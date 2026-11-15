<?php

declare(strict_types=1);

namespace App\Services\DataQuality;

use App\Support\DataCampaignProgress;
use DateTimeImmutable;

/**
 * Tableaux de la page « Qualité des données » : par personne, par unité, vérifications d'anomalies et remplissage par
 * champ. « Taux » = taux de remplissage de la fiche (champs renseignés sur champs applicables) et ancienneté de sa
 * dernière mise à jour ou confirmation. Chaque tableau se trie, se filtre et se pagine côté serveur par des paramètres
 * d'adresse préfixés (pe_, un_, ch_, an_, da_), donc partageables et sans script.
 */
final class DataQualityTables
{
    public const PER_PAGE = 25;
    public const MEMBER_CAP = 5000;
    /** Une fiche sans mise à jour depuis ce nombre de jours est dite ancienne. */
    public const STALE_DAYS = 365;
    /** En dessous de ce taux, une fiche est dite incomplète. */
    public const INCOMPLETE_BELOW = 80;

    public function __construct(private DataQualitySource $source) {}

    /**
     * @param array<string, mixed> $query paramètres d'adresse (GET)
     * @return array<string, mixed>
     */
    public function build(int $tenantId, array $query, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        $rows = $this->source->memberRows($tenantId, self::MEMBER_CAP);
        $analysis = DataQualityAnalyzer::analyze($rows, $tenantId, $this->source->qualificationDateIssues($tenantId), $now);
        $units = $this->source->unitNames($tenantId);
        $confirmations = $this->source->lastConfirmations($tenantId);
        $campaign = $this->source->campaign($tenantId);

        $campaignOf = [];
        foreach ($campaign['members'] ?? [] as $m) {
            $campaignOf[(int) $m['user_id']] = [
                'bucket' => DataCampaignProgress::bucket(!empty($m['confirmed']), (int) ($m['open_flags'] ?? 0)),
                'flags' => (int) ($m['open_flags'] ?? 0),
            ];
        }
        $findingsByUser = [];
        foreach ($analysis['findings'] as $f) {
            $findingsByUser[$f['user_id']][] = $f;
        }
        $staleLimit = $now->modify('-' . self::STALE_DAYS . ' days')->format('Y-m-d H:i:s');

        $people = [];
        foreach ($analysis['members'] as $m) {
            $mine = $findingsByUser[$m['id']] ?? [];
            $worst = 3;
            foreach ($mine as $f) {
                $worst = min($worst, DataQualityCatalog::SEVERITY_RANK[$f['severity']] ?? 1);
            }
            $lastConfirmed = $confirmations[$m['id']] ?? '';
            $people[] = [
                'id' => $m['id'],
                'name' => $m['name'] !== '' ? $m['name'] : '',
                'unit_id' => $m['unit_id'],
                'unit' => $m['unit_id'] > 0 ? ($units[$m['unit_id']] ?? '') : '',
                'percent' => $m['percent'],
                'missing_blocks' => array_values(array_filter(array_keys(DataQualityCatalog::BLOCKS), static fn (string $b): bool => $m['block_percent'][$b] < 100)),
                'updated_at' => $m['updated_at'],
                'confirmed_at' => $lastConfirmed,
                'stale' => $m['updated_at'] === '' || $m['updated_at'] < $staleLimit,
                'campaign' => $campaignOf[$m['id']]['bucket'] ?? 'out',
                'open_flags' => $campaignOf[$m['id']]['flags'] ?? 0,
                'anomalies' => count($mine),
                'worst' => $worst,
                'missing' => $m['missing'],
                'block_percent' => $m['block_percent'],
            ];
        }

        $out = [
            'ready' => true,
            'truncated' => count($rows) >= self::MEMBER_CAP,
            'campaign' => $campaign === null ? null : [
                'id' => (int) $campaign['campaign']['id'],
                'title' => (string) ($campaign['campaign']['title'] ?? ''),
                'status' => (string) ($campaign['campaign']['status'] ?? ''),
                'due_on' => (string) ($campaign['campaign']['due_on'] ?? ''),
            ],
            'query' => self::keptQuery($query),
            'summary' => self::summary($people, count($analysis['findings'])),
            'blocks' => self::blocks($people),
        ];
        $out['people'] = self::table($people, $query, 'pe', self::PEOPLE_SORTS, 'percent', 'asc', self::peopleFilter(...), self::peopleSearch(...));
        $out['units'] = self::table(self::unitRows($people, $units), $query, 'un', self::UNIT_SORTS, 'percent', 'asc', null, static fn (array $r, string $q): bool => str_contains(mb_strtolower($r['label']), $q));
        $checks = self::checkRows($analysis['findings']);
        $out['checks'] = self::table($checks, $query, 'ch', self::CHECK_SORTS, 'count', 'desc', static fn (array $r, string $f): bool => $f === '' || ($f === 'hit' ? $r['count'] > 0 : ($f === 'ok' ? $r['count'] === 0 : $r['group'] === $f)), static fn (array $r, string $q): bool => str_contains(mb_strtolower($r['label'] . ' ' . $r['help']), $q));
        $out['findings'] = self::table(self::findingRows($analysis['findings']), $query, 'an', self::FINDING_SORTS, 'severity', 'asc', self::findingFilter($query), static fn (array $r, string $q): bool => str_contains(mb_strtolower($r['name'] . ' ' . $r['label']), $q));
        $out['fields'] = self::table(self::fieldRows($analysis['members']), $query, 'da', self::FIELD_SORTS, 'percent', 'asc', static fn (array $r, string $f): bool => $f === '' || $r['block'] === $f, static fn (array $r, string $q): bool => str_contains(mb_strtolower($r['label']), $q));

        return $out;
    }

    /**
     * Paramètres d'adresse des tableaux (et seulement eux), réécrits dans les liens de tri et de page.
     *
     * @param array<string, mixed> $query
     * @return array<string, string>
     */
    private static function keptQuery(array $query): array
    {
        $out = [];
        foreach ($query as $key => $value) {
            if (is_string($key) && is_scalar($value) && (string) $value !== '' && preg_match('/^(?:(?:pe|un|ch|an|da)_[a-z]|simuler)$/', $key) === 1) {
                $out[$key] = mb_substr((string) $value, 0, 80);
            }
        }

        return $out;
    }

    private const PEOPLE_SORTS = ['name', 'unit', 'percent', 'updated_at', 'confirmed_at', 'campaign', 'anomalies'];
    private const UNIT_SORTS = ['label', 'members', 'percent', 'confirmed_percent', 'flagged', 'none', 'anomalies', 'stale', 'updated_at'];
    private const CHECK_SORTS = ['label', 'group', 'severity', 'count', 'members'];
    private const FINDING_SORTS = ['severity', 'label', 'name'];
    private const FIELD_SORTS = ['label', 'block', 'percent', 'missing'];

    /**
     * @param list<array<string, mixed>> $people
     * @return array{members: int, average: int, complete: int, incomplete: int, stale: int, with_anomalies: int, findings: int}
     */
    private static function summary(array $people, int $findings): array
    {
        $n = count($people);

        return [
            'members' => $n,
            'average' => $n > 0 ? (int) round(array_sum(array_column($people, 'percent')) / $n) : 0,
            'complete' => count(array_filter($people, static fn (array $p): bool => $p['percent'] >= 100)),
            'incomplete' => count(array_filter($people, static fn (array $p): bool => $p['percent'] < self::INCOMPLETE_BELOW)),
            'stale' => count(array_filter($people, static fn (array $p): bool => $p['stale'])),
            'with_anomalies' => count(array_filter($people, static fn (array $p): bool => $p['anomalies'] > 0)),
            'findings' => $findings,
        ];
    }

    /**
     * @param list<array<string, mixed>> $people
     * @return list<array{key: string, label: string, percent: int}>
     */
    private static function blocks(array $people): array
    {
        $out = [];
        foreach (DataQualityCatalog::BLOCKS as $key => $label) {
            $values = array_map(static fn (array $p): int => (int) $p['block_percent'][$key], $people);
            $out[] = ['key' => $key, 'label' => $label, 'percent' => $values === [] ? 0 : (int) round(array_sum($values) / count($values))];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $people
     * @param array<int, string> $units
     * @return list<array<string, mixed>>
     */
    private static function unitRows(array $people, array $units): array
    {
        $groups = [];
        foreach ($people as $p) {
            $groups[$p['unit_id']][] = $p;
        }
        $out = [];
        foreach ($groups as $unitId => $members) {
            $inCampaign = array_filter($members, static fn (array $p): bool => $p['campaign'] !== 'out');
            $confirmed = count(array_filter($inCampaign, static fn (array $p): bool => $p['campaign'] === DataCampaignProgress::CONFIRMED));
            $updated = '';
            foreach ($members as $p) {
                $updated = max($updated, (string) $p['updated_at']);
            }
            $out[] = [
                'unit_id' => (int) $unitId,
                'label' => $unitId > 0 ? ($units[$unitId] ?? '') : '',
                'members' => count($members),
                'percent' => (int) round(array_sum(array_column($members, 'percent')) / count($members)),
                'in_campaign' => count($inCampaign),
                'confirmed_percent' => $inCampaign === [] ? -1 : (int) round($confirmed * 100 / count($inCampaign)),
                'flagged' => count(array_filter($inCampaign, static fn (array $p): bool => $p['campaign'] === DataCampaignProgress::FLAGGED)),
                'none' => count(array_filter($inCampaign, static fn (array $p): bool => $p['campaign'] === DataCampaignProgress::NONE)),
                'anomalies' => array_sum(array_column($members, 'anomalies')),
                'stale' => count(array_filter($members, static fn (array $p): bool => $p['stale'])),
                'updated_at' => $updated,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $findings
     * @return list<array<string, mixed>>
     */
    private static function checkRows(array $findings): array
    {
        $count = [];
        $users = [];
        foreach ($findings as $f) {
            $count[$f['code']] = ($count[$f['code']] ?? 0) + 1;
            $users[$f['code']][$f['user_id']] = true;
        }
        $out = [];
        foreach (DataQualityCatalog::CHECKS as $code => $c) {
            $out[] = [
                'code' => $code,
                'group' => $c['group'],
                'severity' => $c['severity'],
                'label' => $c['label'],
                'help' => $c['help'],
                'count' => $count[$code] ?? 0,
                'members' => count($users[$code] ?? []),
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $findings
     * @return list<array<string, mixed>>
     */
    private static function findingRows(array $findings): array
    {
        return array_map(static fn (array $f): array => $f + ['label' => DataQualityCatalog::CHECKS[$f['code']]['label'], 'help' => DataQualityCatalog::CHECKS[$f['code']]['help']], $findings);
    }

    /**
     * @param list<array<string, mixed>> $members
     * @return list<array<string, mixed>>
     */
    private static function fieldRows(array $members): array
    {
        $out = [];
        foreach (DataQualityCatalog::FIELDS as $key => $f) {
            $total = 0;
            $filled = 0;
            foreach ($members as $m) {
                if (array_key_exists($key, $m['filled'])) {
                    $total++;
                    $filled += $m['filled'][$key] ? 1 : 0;
                }
            }
            if ($total === 0 && $members !== []) {
                continue;
            }
            $out[] = [
                'key' => $key,
                'label' => $f['label'],
                'block' => $f['block'],
                'filled' => $filled,
                'total' => $total,
                'missing' => $total - $filled,
                'percent' => $total > 0 ? (int) round($filled * 100 / $total) : 0,
            ];
        }

        return $out;
    }

    /** @param array<string, mixed> $row */
    private static function peopleSearch(array $row, string $q): bool
    {
        return str_contains(mb_strtolower((string) $row['name'] . ' ' . (string) $row['unit']), $q);
    }

    /** @param array<string, mixed> $row */
    private static function peopleFilter(array $row, string $filter): bool
    {
        return match ($filter) {
            'incomplete' => $row['percent'] < self::INCOMPLETE_BELOW,
            'complete' => $row['percent'] >= 100,
            'stale' => $row['stale'],
            'anomaly' => $row['anomalies'] > 0,
            DataCampaignProgress::CONFIRMED, DataCampaignProgress::FLAGGED, DataCampaignProgress::NONE, 'out' => $row['campaign'] === $filter,
            default => true,
        };
    }

    /**
     * Filtres des constats : code de vérification (an_f), gravité (an_v) et membre (an_u).
     *
     * @param array<string, mixed> $query
     * @return callable(array<string, mixed>, string): bool
     */
    private static function findingFilter(array $query): callable
    {
        $severity = self::str($query['an_v'] ?? '');
        $user = (int) ($query['an_u'] ?? 0);

        return static function (array $row, string $code) use ($severity, $user): bool {
            return ($code === '' || $row['code'] === $code)
                && ($severity === '' || $row['severity'] === $severity)
                && ($user < 1 || $row['user_id'] === $user);
        };
    }

    /**
     * Recherche, filtre, tri et page d'un tableau à partir des paramètres {prefixe}_q, _f, _s, _d, _p.
     *
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed> $query
     * @param list<string> $sorts colonnes triables (liste blanche)
     * @param (callable(array<string, mixed>, string): bool)|null $filter
     * @param (callable(array<string, mixed>, string): bool)|null $search
     * @return array{rows: list<array<string, mixed>>, total: int, all: int, page: int, pages: int, q: string, filter: string, sort: string, dir: string, prefix: string}
     */
    public static function table(array $rows, array $query, string $prefix, array $sorts, string $defaultSort, string $defaultDir, ?callable $filter, ?callable $search, int $perPage = self::PER_PAGE): array
    {
        $q = mb_strtolower(trim(self::str($query[$prefix . '_q'] ?? '')));
        $f = self::str($query[$prefix . '_f'] ?? '');
        $sort = self::str($query[$prefix . '_s'] ?? '');
        $sort = in_array($sort, $sorts, true) ? $sort : $defaultSort;
        $dir = self::str($query[$prefix . '_d'] ?? '');
        $dir = in_array($dir, ['asc', 'desc'], true) ? $dir : $defaultDir;
        $all = count($rows);
        if ($filter !== null) {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => $filter($r, $f)));
        } else {
            $f = '';
        }
        if ($q !== '' && $search !== null) {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => $search($r, $q)));
        }
        usort($rows, static function (array $a, array $b) use ($sort, $dir): int {
            $va = self::sortValue($a, $sort);
            $vb = self::sortValue($b, $sort);
            $cmp = $va <=> $vb;
            if ($cmp === 0) {
                $cmp = self::sortValue($a, 'name') <=> self::sortValue($b, 'name');
            }

            return $dir === 'desc' ? -$cmp : $cmp;
        });
        $total = count($rows);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($pages, (int) ($query[$prefix . '_p'] ?? 1)));

        return [
            'rows' => array_slice($rows, ($page - 1) * $perPage, $perPage),
            'total' => $total,
            'all' => $all,
            'page' => $page,
            'pages' => $pages,
            'q' => $q,
            'filter' => $f,
            'sort' => $sort,
            'dir' => $dir,
            'prefix' => $prefix,
        ];
    }

    /** @param array<string, mixed> $row */
    private static function sortValue(array $row, string $key): int|string
    {
        $v = $row[$key] ?? ($key === 'name' ? ($row['label'] ?? '') : '');
        if ($key === 'severity') {
            return is_string($v) ? (DataQualityCatalog::SEVERITY_RANK[$v] ?? 1) : (int) $v;
        }
        if ($key === 'campaign') {
            return ['flagged' => 0, 'none' => 1, 'confirmed' => 2, 'out' => 3][(string) $v] ?? 4;
        }
        if (is_int($v) || is_bool($v)) {
            return (int) $v;
        }

        return mb_strtolower((string) $v);
    }

    private static function str(mixed $v): string
    {
        return is_scalar($v) ? (string) $v : '';
    }
}
