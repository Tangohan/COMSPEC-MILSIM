<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Repositories\AthenaHeritageRepository;
use App\Support\AthenaHeritageMajorVersions;
use App\Support\ChangelogCatalog;

/**
 * Patrimoine statistique public ATHENA : snapshots, jalons uniques, frise des versions majeures.
 */
final class AthenaHeritageService
{
    /** @var list<int> */
    public const PLAYER_THRESHOLDS = [10, 50, 100, 250, 500, 1000];

    /** @var list<int> */
    public const KM_THRESHOLDS = [100, 500, 1000];

    public const PLAYTIME_HOURS_THRESHOLD = 10000;

    public function __construct(
        private AthenaHeritageRepository $repo,
    ) {}

    /**
     * Capture un snapshot du jour + détection des jalons + sync versions majeures.
     *
     * @return array{ok: bool, summary: string, details: array<string, mixed>}
     */
    public function refresh(): array
    {
        if (!$this->repo->ensureSchema()) {
            return [
                'ok' => true,
                'summary' => 'Tables patrimoine absentes — migration requise.',
                'details' => ['skipped' => true],
            ];
        }

        $metrics = $this->collectLiveMetrics();
        $backfilled = 0;
        if ($this->repo->countSnapshots() < 2) {
            try {
                $backfilled = $this->repo->backfillSnapshotsFromGrowth();
            } catch (\Throwable) {
                $backfilled = 0;
            }
        }

        $this->repo->upsertSnapshot([
            'snapshot_date' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'operators_total' => $metrics['operators_total'],
            'organizations_total' => $metrics['organizations_total'],
            'sessions_total' => $metrics['sessions_total'],
            'distance_km' => $metrics['distance_km'],
            'playtime_hours' => $metrics['playtime_hours'],
            'version_label' => $metrics['version_label'],
            'version_adoption_pct' => $metrics['version_adoption_pct'],
            'metrics_json' => $metrics['extra'],
            'source' => 'live',
        ]);

        $created = $this->detectAndPersistMilestones($metrics);
        $versions = $this->syncMajorVersionPoints($metrics);

        return [
            'ok' => true,
            'summary' => sprintf(
                'Snapshot enregistré · %d jalon(s) · %d version(s) majeure(s) · backfill %d.',
                $created,
                $versions,
                $backfilled
            ),
            'details' => [
                'operators' => $metrics['operators_total'],
                'organizations' => $metrics['organizations_total'],
                'milestones_created' => $created,
                'versions_synced' => $versions,
                'snapshots_backfilled' => $backfilled,
            ],
        ];
    }

    /**
     * @return array{
     *   operators_total: int,
     *   organizations_total: int,
     *   sessions_total: int,
     *   distance_km: float,
     *   playtime_hours: float,
     *   version_label: string,
     *   version_adoption_pct: ?float,
     *   extra: array<string, mixed>
     * }
     */
    public function collectLiveMetrics(): array
    {
        $version = function_exists('platform_app_version') ? platform_app_version() : '1.0.0';
        $major = AthenaHeritageMajorVersions::normalizeMajor($version) ?? '1.0.0';
        $prefix = explode('.', $major)[0] . '.';

        return [
            'operators_total' => $this->repo->countActiveOperators(),
            'organizations_total' => $this->repo->countOrganizations(),
            'sessions_total' => $this->repo->countSessions(),
            'distance_km' => $this->repo->sumDistanceKm(),
            'playtime_hours' => $this->repo->sumPlaytimeHours(),
            'version_label' => $version,
            'version_adoption_pct' => $this->repo->versionAdoptionPct($prefix),
            'extra' => [
                'major_version' => $major,
                'captured_at' => (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $metrics
     */
    public function detectAndPersistMilestones(array $metrics): int
    {
        $created = 0;
        $operators = (int) ($metrics['operators_total'] ?? 0);
        $distance = (float) ($metrics['distance_km'] ?? 0);
        $playtime = (float) ($metrics['playtime_hours'] ?? 0);

        foreach (self::PLAYER_THRESHOLDS as $threshold) {
            if ($operators < $threshold) {
                continue;
            }
            $key = 'players_' . $threshold;
            $occurred = $this->repo->nthOperatorCreatedAt($threshold);
            $precision = $occurred !== null ? 'exact' : 'unknown';
            if ($this->persistMilestone([
                'milestone_key' => $key,
                'category' => 'threshold',
                'occurred_at' => $occurred,
                'date_precision' => $precision,
                'title' => (string) __('site.hist_ms_players_title', ['value' => (string) $threshold]),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_players', [
                    'value' => $this->formatInt($threshold),
                ]),
                'value_numeric' => $threshold,
                'value_label' => $this->formatInt($threshold) . ' opérateurs',
                'context_json' => ['metric' => 'operators', 'threshold' => $threshold],
            ])) {
                $created++;
            }
        }

        foreach (self::KM_THRESHOLDS as $threshold) {
            if ($distance < $threshold) {
                continue;
            }
            $key = 'km_' . $threshold;
            // Sans historique de distance, on ne fabrique pas de date précise.
            $precision = 'unknown';
            $occurred = null;
            $snap = $this->earliestSnapshotMeeting(static fn (array $s): bool => (float) ($s['distance_km'] ?? 0) >= $threshold);
            if ($snap !== null) {
                $occurred = ($snap['snapshot_date'] ?? null) !== null
                    ? ((string) $snap['snapshot_date']) . ' 00:00:00'
                    : null;
                $precision = $occurred !== null ? 'day' : 'unknown';
            }
            if ($this->persistMilestone([
                'milestone_key' => $key,
                'category' => 'threshold',
                'occurred_at' => $occurred,
                'date_precision' => $precision,
                'title' => (string) __('site.hist_ms_km_title', ['value' => (string) $threshold]),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_km', [
                    'value' => $this->formatInt($threshold),
                ]),
                'value_numeric' => $threshold,
                'value_label' => $this->formatInt($threshold) . ' km',
                'context_json' => ['metric' => 'distance_km', 'threshold' => $threshold],
            ])) {
                $created++;
            }
        }

        if ($playtime >= self::PLAYTIME_HOURS_THRESHOLD) {
            $occurred = null;
            $precision = 'unknown';
            $snap = $this->earliestSnapshotMeeting(
                static fn (array $s): bool => (float) ($s['playtime_hours'] ?? 0) >= self::PLAYTIME_HOURS_THRESHOLD
            );
            if ($snap !== null && !empty($snap['snapshot_date'])) {
                $occurred = ((string) $snap['snapshot_date']) . ' 00:00:00';
                $precision = 'day';
            }
            if ($this->persistMilestone([
                'milestone_key' => 'playtime_hours_' . self::PLAYTIME_HOURS_THRESHOLD,
                'category' => 'threshold',
                'occurred_at' => $occurred,
                'date_precision' => $precision,
                'title' => (string) __('site.hist_ms_playtime_title', ['value' => $this->formatInt(self::PLAYTIME_HOURS_THRESHOLD)]),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_playtime', [
                    'value' => $this->formatInt(self::PLAYTIME_HOURS_THRESHOLD),
                ]),
                'value_numeric' => self::PLAYTIME_HOURS_THRESHOLD,
                'value_label' => $this->formatInt(self::PLAYTIME_HOURS_THRESHOLD) . ' h',
                'context_json' => ['metric' => 'playtime_hours', 'threshold' => self::PLAYTIME_HOURS_THRESHOLD],
            ])) {
                $created++;
            }
        }

        $created += $this->detectFirstEvents();

        return $created;
    }

    private function detectFirstEvents(): int
    {
        $created = 0;

        $defs = [
            [
                'key' => 'first_arma_operator',
                'date' => $this->repo->firstRowDate('atak_units', 'created_at')
                    ?? $this->repo->firstRowDate('atak_last_activity', 'updated_at')
                    ?? $this->repo->firstRowDate('operator_game_profiles', 'created_at'),
                'title' => (string) __('site.hist_ms_first_arma_t'),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_first_arma'),
            ],
            [
                'key' => 'first_authenticated_terminal',
                'date' => $this->repo->firstRowDate('atak_trusted_devices', 'approved_at')
                    ?? $this->repo->firstRowDate('atak_trusted_devices', 'created_at')
                    ?? $this->repo->firstRowDate('atak_terminals', 'created_at'),
                'title' => (string) __('site.hist_ms_first_terminal_t'),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_first_terminal'),
            ],
            [
                'key' => 'first_tactical_relay',
                'date' => $this->repo->firstRowDate('atak_relays', 'created_at'),
                'title' => (string) __('site.hist_ms_first_relay_t'),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_first_relay'),
            ],
            [
                'key' => 'first_atak_message',
                'date' => $this->repo->firstRowDate('atak_chat_messages', 'created_at'),
                'title' => (string) __('site.hist_ms_first_atak_msg_t'),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_first_atak_msg'),
            ],
            [
                'key' => 'first_sse_mission',
                'date' => $this->repo->firstRowDate('sse_cases', 'created_at'),
                'title' => (string) __('site.hist_ms_first_sse_t'),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_first_sse'),
            ],
            [
                'key' => 'first_drone_flight',
                'date' => $this->repo->firstRowDate('atak_air_assets', 'updated_at')
                    ?? $this->repo->firstRowDate('atak_air_assets', 'created_at'),
                'title' => (string) __('site.hist_ms_first_drone_t'),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_first_drone'),
            ],
            [
                'key' => 'first_translation_pack',
                'date' => $this->repo->firstTranslationPackDate(),
                'title' => (string) __('site.hist_ms_first_translation_t'),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_first_translation'),
            ],
        ];

        foreach ($defs as $def) {
            $date = $def['date'];
            if ($date === null || $date === '') {
                // Pas de preuve archivée : on n’invente rien et on n’enregistre pas.
                continue;
            }
            if ($this->persistMilestone([
                'milestone_key' => $def['key'],
                'category' => 'first',
                'occurred_at' => $date,
                'date_precision' => 'exact',
                'title' => $def['title'],
                'narrative' => $def['narrative'],
                'value_numeric' => 1,
                'value_label' => null,
                'context_json' => ['kind' => 'first'],
            ])) {
                $created++;
            }
        }

        $foreign = $this->repo->firstForeignOrganization('FR');
        if ($foreign !== null && !empty($foreign['occurred_at'])) {
            $cc = trim((string) ($foreign['country_code'] ?? ''));
            $countrySuffix = $cc !== '' ? ' (' . strtoupper($cc) . ')' : '';
            if ($this->persistMilestone([
                'milestone_key' => 'first_international_org',
                'category' => 'first',
                'occurred_at' => $foreign['occurred_at'],
                'date_precision' => 'exact',
                'title' => (string) __('site.hist_ms_first_intl_t'),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_intl', [
                    'country' => $countrySuffix,
                ]),
                'value_numeric' => 1,
                'value_label' => $cc !== '' ? strtoupper($cc) : null,
                'context_json' => ['country_code' => $cc],
            ])) {
                $created++;
            }
        }

        $ops = $this->repo->firstLargeOperation(50);
        if ($ops !== null && !empty($ops['occurred_at'])) {
            $players = (int) ($ops['players'] ?? 50);
            if ($this->persistMilestone([
                'milestone_key' => 'first_ops_50_players',
                'category' => 'first',
                'occurred_at' => $ops['occurred_at'],
                'date_precision' => 'exact',
                'title' => (string) __('site.hist_ms_ops_50_t'),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_ops_50', [
                    'value' => $this->formatInt(max(50, $players)),
                ]),
                'value_numeric' => $players,
                'value_label' => $this->formatInt($players) . ' joueurs',
                'context_json' => ['event_id' => $ops['event_id'] ?? null, 'players' => $players],
            ])) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $metrics
     */
    public function syncMajorVersionPoints(array $metrics): int
    {
        $synced = 0;
        $current = (string) ($metrics['version_label'] ?? '');
        $reached = AthenaHeritageMajorVersions::reachedMajors($current);
        $catalog = [];
        foreach (AthenaHeritageMajorVersions::catalog() as $entry) {
            $catalog[$entry['version']] = $entry;
        }

        foreach ($reached as $version) {
            $meta = $catalog[$version] ?? [
                'version' => $version,
                'released_at' => null,
                'date_precision' => 'unknown',
                'title_key' => 'site.hist_ver_generic_t',
                'summary_key' => 'site.hist_ver_generic_b',
                'changelog_ref' => null,
            ];
            $title = (string) __($meta['title_key'] ?? 'site.hist_ver_generic_t');
            if ($title === ($meta['title_key'] ?? '') || $title === '') {
                $title = 'ATHENA ' . $version;
            }
            $summary = (string) __($meta['summary_key'] ?? 'site.hist_ver_generic_b');
            if ($summary === ($meta['summary_key'] ?? '')) {
                $summary = AthenaHeritageNarration::render('site.hist_narr_version', ['version' => $version]);
            }

            $stats = null;
            if ($version === (AthenaHeritageMajorVersions::normalizeMajor($current) ?? '')) {
                $stats = [
                    'operators_total' => (int) ($metrics['operators_total'] ?? 0),
                    'organizations_total' => (int) ($metrics['organizations_total'] ?? 0),
                    'sessions_total' => (int) ($metrics['sessions_total'] ?? 0),
                    'distance_km' => (float) ($metrics['distance_km'] ?? 0),
                    'playtime_hours' => (float) ($metrics['playtime_hours'] ?? 0),
                    'version_adoption_pct' => $metrics['version_adoption_pct'] ?? null,
                ];
            }

            $releasedAt = $meta['released_at'] ?? null;
            $precision = (string) ($meta['date_precision'] ?? 'unknown');
            // Enrichir depuis platform_app_releases si déployée en x.0.0
            $deployDate = $this->lookupDeployedMajorDate($version);
            if ($deployDate !== null) {
                $releasedAt = $deployDate;
                $precision = 'exact';
            }

            $this->repo->upsertVersionPoint([
                'version' => $version,
                'released_at' => $releasedAt,
                'date_precision' => $precision,
                'title' => $title,
                'summary' => $summary,
                'stats_json' => $stats,
                'changelog_ref' => $meta['changelog_ref'] ?? null,
            ]);

            if ($this->persistMilestone([
                'milestone_key' => 'version_' . str_replace('.', '_', $version),
                'category' => 'version',
                'occurred_at' => $releasedAt !== null ? $releasedAt . ' 00:00:00' : null,
                'date_precision' => $precision,
                'title' => $title,
                'narrative' => $summary,
                'value_numeric' => null,
                'value_label' => $version,
                'context_json' => ['version' => $version, 'stats' => $stats],
            ])) {
                $synced++;
            } else {
                $synced++;
            }
        }

        return $synced;
    }

    private function lookupDeployedMajorDate(string $version): ?string
    {
        if (!$this->repo->hasTable('platform_app_releases') || !class_exists(\App\Core\Database::class)) {
            return null;
        }
        $pdo = \App\Core\Database::getPdo();
        $st = $pdo->prepare(
            "SELECT COALESCE(deployed_at, created_at) AS d
             FROM platform_app_releases
             WHERE version = ? AND status IN ('deployed', 'validated', 'previewed')
             ORDER BY COALESCE(deployed_at, created_at) ASC
             LIMIT 1"
        );
        $st->execute([$version]);
        $val = $st->fetchColumn();
        if ($val === false || $val === null || $val === '') {
            return null;
        }
        $dt = substr((string) $val, 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt) ? $dt : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function persistMilestone(array $row): bool
    {
        // Titres i18n peuvent renvoyer la clé : fallback lisible.
        if (($row['title'] ?? '') === '' || str_starts_with((string) $row['title'], 'site.')) {
            $row['title'] = (string) ($row['value_label'] ?? $row['milestone_key'] ?? 'Jalon ATHENA');
        }

        return $this->repo->insertMilestoneOnce($row);
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return array<string, mixed>|null
     */
    private function earliestSnapshotMeeting(callable $predicate): ?array
    {
        foreach ($this->repo->listSnapshots() as $snap) {
            if ($predicate($snap)) {
                return $snap;
            }
        }

        return null;
    }

    /**
     * Payload public pour la page « Notre histoire ».
     *
     * @return array<string, mixed>
     */
    public function buildPublicPage(?string $from = null, ?string $to = null): array
    {
        $live = $this->collectLiveMetrics();
        try {
            $this->repo->ensureSchema();
        } catch (\Throwable) {
            // Lecture seule éventuelle : on continue en mode reconstruit.
        }

        if ($this->repo->ready()) {
            // Refresh si snapshot du jour manquant, frise vide, ou courbe encore trop fine.
            try {
                $latest = $this->repo->latestSnapshot();
                $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
                $latestDate = is_array($latest) ? (string) ($latest['snapshot_date'] ?? '') : '';
                $needsRefresh = $latestDate !== $today
                    || $this->repo->countMilestones() < 2
                    || $this->repo->countVersionPoints() < 1
                    || $this->repo->countSnapshots() < 2;
                if ($needsRefresh) {
                    $this->refresh();
                } else {
                    // Même jour : resynchroniser jalons / versions (idempotent) sans re-backfill.
                    $this->detectAndPersistMilestones($live);
                    $this->syncMajorVersionPoints($live);
                }
            } catch (\Throwable) {
                // La page reste consultable même si l’écriture échoue.
            }
        }

        $snapshots = $this->repo->ready() ? $this->repo->listSnapshots($from, $to) : [];
        if (count($snapshots) < 2) {
            try {
                $reconstructed = $this->repo->reconstructGrowthSeries($from, $to);
                if ($reconstructed !== []) {
                    // Fusionne : conserve sessions/distance du live sur le point du jour.
                    $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
                    $snapshots = $reconstructed;
                    foreach ($snapshots as $i => $row) {
                        if ((string) ($row['snapshot_date'] ?? '') === $today) {
                            $snapshots[$i]['operators_total'] = max(
                                (int) ($row['operators_total'] ?? 0),
                                (int) $live['operators_total']
                            );
                            $snapshots[$i]['organizations_total'] = max(
                                (int) ($row['organizations_total'] ?? 0),
                                (int) $live['organizations_total']
                            );
                            $snapshots[$i]['sessions_total'] = (int) $live['sessions_total'];
                            $snapshots[$i]['distance_km'] = (float) $live['distance_km'];
                            $snapshots[$i]['playtime_hours'] = (float) $live['playtime_hours'];
                            $snapshots[$i]['version_label'] = $live['version_label'];
                            $snapshots[$i]['version_adoption_pct'] = $live['version_adoption_pct'];
                            $snapshots[$i]['source'] = 'live';
                        }
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }
        if ($snapshots === []) {
            // Point courant synthétique pour le graphique tant que l’historique se constitue.
            $snapshots = [[
                'snapshot_date' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
                'operators_total' => $live['operators_total'],
                'organizations_total' => $live['organizations_total'],
                'sessions_total' => $live['sessions_total'],
                'distance_km' => $live['distance_km'],
                'playtime_hours' => $live['playtime_hours'],
                'version_label' => $live['version_label'],
                'version_adoption_pct' => $live['version_adoption_pct'],
                'source' => 'live',
            ]];
        }

        $milestones = $this->repo->ready() ? $this->repo->listMilestones() : [];
        if ($milestones === []) {
            $milestones = $this->virtualMilestonesFromLive($live);
        }
        $versions = $this->repo->ready() ? $this->repo->listVersionPoints() : [];
        if ($versions === []) {
            $versions = $this->virtualVersionPoints($live);
        }

        $changelog = [];
        try {
            $catalog = ChangelogCatalog::hydrate([]);
            foreach ($catalog['releases'] as $release) {
                if (!is_array($release)) {
                    continue;
                }
                $changelog[(string) ($release['id'] ?? '')] = $release;
            }
        } catch (\Throwable) {
            $changelog = [];
        }

        $versionCards = [];
        foreach ($versions as $vp) {
            $ref = (string) ($vp['changelog_ref'] ?? '');
            $features = [];
            if ($ref !== '' && isset($changelog[$ref]) && is_array($changelog[$ref]['features'] ?? null)) {
                $features = array_slice($changelog[$ref]['features'], 0, 6);
            }
            $stats = $vp['stats_json'] ?? null;
            if (is_string($stats)) {
                $decoded = json_decode($stats, true);
                $stats = is_array($decoded) ? $decoded : null;
            }
            if (!is_array($stats)) {
                $stats = [
                    'operators_total' => $live['operators_total'],
                    'organizations_total' => $live['organizations_total'],
                    'sessions_total' => $live['sessions_total'],
                    'distance_km' => $live['distance_km'],
                    'playtime_hours' => $live['playtime_hours'],
                    'version_adoption_pct' => $live['version_adoption_pct'],
                ];
            }
            $versionCards[] = [
                'version' => (string) ($vp['version'] ?? ''),
                'released_at' => $vp['released_at'] ?? null,
                'date_precision' => (string) ($vp['date_precision'] ?? 'unknown'),
                'title' => (string) ($vp['title'] ?? ''),
                'summary' => (string) ($vp['summary'] ?? ''),
                'stats' => $stats,
                'features' => $features,
                'changelog_ref' => $ref !== '' ? $ref : null,
            ];
        }

        $timeline = $this->buildTimeline($milestones, $versionCards);
        $chart = $this->buildChartSeries($snapshots, $versionCards);
        $nextGoal = $this->computeNextGoal($live);

        return [
            'live' => $live,
            'snapshots' => $snapshots,
            'milestones' => $milestones,
            'versions' => $versionCards,
            'timeline' => $timeline,
            'chart' => $chart,
            'next_goal' => $nextGoal,
            'honesty_note' => (string) __('site.hist_honesty_note'),
        ];
    }

    /**
     * @param array<string, mixed> $live
     * @return list<array<string, mixed>>
     */
    private function virtualVersionPoints(array $live): array
    {
        $out = [];
        foreach (AthenaHeritageMajorVersions::reachedMajors((string) ($live['version_label'] ?? '1.0.0')) as $version) {
            $meta = null;
            foreach (AthenaHeritageMajorVersions::catalog() as $entry) {
                if (($entry['version'] ?? '') === $version) {
                    $meta = $entry;
                    break;
                }
            }
            $title = $meta !== null ? (string) __((string) ($meta['title_key'] ?? 'site.hist_ver_generic_t')) : '';
            if ($title === '' || str_starts_with($title, 'site.')) {
                $title = 'ATHENA ' . $version;
            }
            $summary = $meta !== null ? (string) __((string) ($meta['summary_key'] ?? 'site.hist_ver_generic_b')) : '';
            if ($summary === '' || str_starts_with($summary, 'site.')) {
                $summary = AthenaHeritageNarration::render('site.hist_narr_version', ['version' => $version]);
            }
            $out[] = [
                'version' => $version,
                'released_at' => $meta['released_at'] ?? null,
                'date_precision' => (string) ($meta['date_precision'] ?? 'unknown'),
                'title' => $title,
                'summary' => $summary,
                'stats_json' => [
                    'operators_total' => (int) ($live['operators_total'] ?? 0),
                    'organizations_total' => (int) ($live['organizations_total'] ?? 0),
                    'sessions_total' => (int) ($live['sessions_total'] ?? 0),
                    'distance_km' => (float) ($live['distance_km'] ?? 0),
                    'playtime_hours' => (float) ($live['playtime_hours'] ?? 0),
                    'version_adoption_pct' => $live['version_adoption_pct'] ?? null,
                ],
                'changelog_ref' => $meta['changelog_ref'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Jalons calculés à la volée (sans tables / avant premier refresh) pour peupler la frise.
     *
     * @param array<string, mixed> $live
     * @return list<array<string, mixed>>
     */
    private function virtualMilestonesFromLive(array $live): array
    {
        $out = [];
        $operators = (int) ($live['operators_total'] ?? 0);
        $distance = (float) ($live['distance_km'] ?? 0);
        $playtime = (float) ($live['playtime_hours'] ?? 0);

        foreach (self::PLAYER_THRESHOLDS as $threshold) {
            if ($operators < $threshold) {
                continue;
            }
            $occurred = null;
            try {
                $occurred = $this->repo->nthOperatorCreatedAt($threshold);
            } catch (\Throwable) {
                $occurred = null;
            }
            $out[] = [
                'milestone_key' => 'players_' . $threshold,
                'category' => 'threshold',
                'occurred_at' => $occurred,
                'date_precision' => $occurred !== null ? 'exact' : 'unknown',
                'title' => (string) __('site.hist_ms_players_title', ['value' => (string) $threshold]),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_players', [
                    'value' => $this->formatInt($threshold),
                ]),
                'value_label' => $this->formatInt($threshold) . ' opérateurs',
            ];
        }

        foreach (self::KM_THRESHOLDS as $threshold) {
            if ($distance < $threshold) {
                continue;
            }
            $out[] = [
                'milestone_key' => 'km_' . $threshold,
                'category' => 'threshold',
                'occurred_at' => null,
                'date_precision' => 'unknown',
                'title' => (string) __('site.hist_ms_km_title', ['value' => (string) $threshold]),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_km', [
                    'value' => $this->formatInt($threshold),
                ]),
                'value_label' => $this->formatInt($threshold) . ' km',
            ];
        }

        if ($playtime >= self::PLAYTIME_HOURS_THRESHOLD) {
            $out[] = [
                'milestone_key' => 'playtime_hours_' . self::PLAYTIME_HOURS_THRESHOLD,
                'category' => 'threshold',
                'occurred_at' => null,
                'date_precision' => 'unknown',
                'title' => (string) __('site.hist_ms_playtime_title', ['value' => $this->formatInt(self::PLAYTIME_HOURS_THRESHOLD)]),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_playtime', [
                    'value' => $this->formatInt(self::PLAYTIME_HOURS_THRESHOLD),
                ]),
                'value_label' => $this->formatInt(self::PLAYTIME_HOURS_THRESHOLD) . ' h',
            ];
        }

        $firstDefs = [
            [
                'key' => 'first_arma_operator',
                'date' => $this->safeFirstDate('atak_units', 'created_at')
                    ?? $this->safeFirstDate('atak_last_activity', 'updated_at')
                    ?? $this->safeFirstDate('operator_game_profiles', 'created_at'),
                'title' => (string) __('site.hist_ms_first_arma_t'),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_first_arma'),
            ],
            [
                'key' => 'first_authenticated_terminal',
                'date' => $this->safeFirstDate('atak_trusted_devices', 'approved_at')
                    ?? $this->safeFirstDate('atak_trusted_devices', 'created_at')
                    ?? $this->safeFirstDate('atak_terminals', 'created_at'),
                'title' => (string) __('site.hist_ms_first_terminal_t'),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_first_terminal'),
            ],
            [
                'key' => 'first_tactical_relay',
                'date' => $this->safeFirstDate('atak_relays', 'created_at'),
                'title' => (string) __('site.hist_ms_first_relay_t'),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_first_relay'),
            ],
            [
                'key' => 'first_atak_message',
                'date' => $this->safeFirstDate('atak_chat_messages', 'created_at'),
                'title' => (string) __('site.hist_ms_first_atak_msg_t'),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_first_atak_msg'),
            ],
            [
                'key' => 'first_sse_mission',
                'date' => $this->safeFirstDate('sse_cases', 'created_at'),
                'title' => (string) __('site.hist_ms_first_sse_t'),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_first_sse'),
            ],
            [
                'key' => 'first_drone_flight',
                'date' => $this->safeFirstDate('atak_air_assets', 'updated_at')
                    ?? $this->safeFirstDate('atak_air_assets', 'created_at'),
                'title' => (string) __('site.hist_ms_first_drone_t'),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_first_drone'),
            ],
            [
                'key' => 'first_translation_pack',
                'date' => $this->safeFirstTranslationPackDate(),
                'title' => (string) __('site.hist_ms_first_translation_t'),
                'narrative' => AthenaHeritageNarration::render('site.hist_narr_first_translation'),
            ],
        ];

        foreach ($firstDefs as $def) {
            $date = $def['date'];
            if ($date === null || $date === '') {
                continue;
            }
            $out[] = [
                'milestone_key' => $def['key'],
                'category' => 'first',
                'occurred_at' => $date,
                'date_precision' => 'exact',
                'title' => $def['title'],
                'narrative' => $def['narrative'],
                'value_label' => null,
            ];
        }

        try {
            $foreign = $this->repo->firstForeignOrganization('FR');
            if (is_array($foreign) && !empty($foreign['occurred_at'])) {
                $cc = trim((string) ($foreign['country_code'] ?? ''));
                $countrySuffix = $cc !== '' ? ' (' . strtoupper($cc) . ')' : '';
                $out[] = [
                    'milestone_key' => 'first_international_org',
                    'category' => 'first',
                    'occurred_at' => $foreign['occurred_at'],
                    'date_precision' => 'exact',
                    'title' => (string) __('site.hist_ms_first_intl_t'),
                    'narrative' => AthenaHeritageNarration::render('site.hist_narr_intl', [
                        'country' => $countrySuffix,
                    ]),
                    'value_label' => $cc !== '' ? strtoupper($cc) : null,
                ];
            }
        } catch (\Throwable) {
        }

        try {
            $ops = $this->repo->firstLargeOperation(50);
            if (is_array($ops) && !empty($ops['occurred_at'])) {
                $players = (int) ($ops['players'] ?? 50);
                $out[] = [
                    'milestone_key' => 'first_ops_50_players',
                    'category' => 'first',
                    'occurred_at' => $ops['occurred_at'],
                    'date_precision' => 'exact',
                    'title' => (string) __('site.hist_ms_ops_50_t'),
                    'narrative' => AthenaHeritageNarration::render('site.hist_narr_ops_50', [
                        'value' => $this->formatInt(max(50, $players)),
                    ]),
                    'value_label' => $this->formatInt($players) . ' joueurs',
                ];
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    private function safeFirstDate(string $table, string $column): ?string
    {
        try {
            return $this->repo->firstRowDate($table, $column);
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeFirstTranslationPackDate(): ?string
    {
        try {
            return $this->repo->firstTranslationPackDate();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param list<array<string, mixed>> $milestones
     * @param list<array<string, mixed>> $versions
     * @return list<array<string, mixed>>
     */
    private function buildTimeline(array $milestones, array $versions): array
    {
        $items = [];
        foreach ($milestones as $m) {
            $ctx = $m['context_json'] ?? null;
            if (is_string($ctx)) {
                $decoded = json_decode($ctx, true);
                $ctx = is_array($decoded) ? $decoded : null;
            }
            $stats = null;
            if (is_array($ctx) && isset($ctx['stats']) && is_array($ctx['stats'])) {
                $stats = $ctx['stats'];
            }
            $items[] = [
                'kind' => (string) ($m['category'] ?? 'custom'),
                'key' => (string) ($m['milestone_key'] ?? ''),
                'title' => (string) ($m['title'] ?? ''),
                'narrative' => (string) ($m['narrative'] ?? ''),
                'occurred_at' => $m['occurred_at'] ?? null,
                'date_precision' => (string) ($m['date_precision'] ?? 'unknown'),
                'value_label' => $m['value_label'] ?? null,
                'version' => is_array($ctx) ? ($ctx['version'] ?? null) : null,
                'stats' => $stats,
            ];
        }
        foreach ($versions as $v) {
            // Évite le doublon si un jalon version_* existe déjà.
            $verKey = 'version_' . str_replace('.', '_', (string) ($v['version'] ?? ''));
            $already = false;
            foreach ($items as $it) {
                if (($it['key'] ?? '') === $verKey) {
                    $already = true;
                    break;
                }
            }
            if ($already) {
                continue;
            }
            $items[] = [
                'kind' => 'version',
                'key' => $verKey,
                'title' => (string) ($v['title'] ?? ''),
                'narrative' => (string) ($v['summary'] ?? ''),
                'occurred_at' => !empty($v['released_at']) ? ((string) $v['released_at']) . ' 00:00:00' : null,
                'date_precision' => (string) ($v['date_precision'] ?? 'unknown'),
                'value_label' => (string) ($v['version'] ?? ''),
                'version' => (string) ($v['version'] ?? ''),
                'stats' => is_array($v['stats'] ?? null) ? $v['stats'] : null,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            $ao = $a['occurred_at'] ?? null;
            $bo = $b['occurred_at'] ?? null;
            if ($ao === null && $bo === null) {
                return strcmp((string) ($a['key'] ?? ''), (string) ($b['key'] ?? ''));
            }
            if ($ao === null) {
                return 1;
            }
            if ($bo === null) {
                return -1;
            }

            return strcmp((string) $ao, (string) $bo);
        });

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $snapshots
     * @param list<array<string, mixed>> $versions
     * @return array{labels: list<string>, operators: list<int>, organizations: list<int>, markers: list<array<string, mixed>>}
     */
    private function buildChartSeries(array $snapshots, array $versions): array
    {
        $labels = [];
        $operators = [];
        $organizations = [];
        foreach ($snapshots as $s) {
            $labels[] = (string) ($s['snapshot_date'] ?? '');
            $operators[] = (int) ($s['operators_total'] ?? 0);
            $organizations[] = (int) ($s['organizations_total'] ?? 0);
        }
        $markers = [];
        foreach ($versions as $v) {
            $date = $v['released_at'] ?? null;
            if ($date === null || $date === '') {
                continue;
            }
            $markers[] = [
                'date' => (string) $date,
                'version' => (string) ($v['version'] ?? ''),
                'title' => (string) ($v['title'] ?? ''),
            ];
        }

        return [
            'labels' => $labels,
            'operators' => $operators,
            'organizations' => $organizations,
            'markers' => $markers,
        ];
    }

    /**
     * @param array<string, mixed> $live
     * @return array{metric: string, label: string, current: float|int, target: float|int, progress_pct: float, remaining: float|int}|null
     */
    public function computeNextGoal(array $live): ?array
    {
        $operators = (int) ($live['operators_total'] ?? 0);
        foreach (self::PLAYER_THRESHOLDS as $threshold) {
            if ($operators < $threshold) {
                $pct = $threshold > 0 ? round(($operators / $threshold) * 100, 1) : 0.0;

                return [
                    'metric' => 'operators',
                    'label' => (string) __('site.hist_next_players', ['value' => $this->formatInt($threshold)]),
                    'current' => $operators,
                    'target' => $threshold,
                    'progress_pct' => min(100.0, $pct),
                    'remaining' => max(0, $threshold - $operators),
                ];
            }
        }

        $distance = (float) ($live['distance_km'] ?? 0);
        foreach (self::KM_THRESHOLDS as $threshold) {
            if ($distance < $threshold) {
                $pct = $threshold > 0 ? round(($distance / $threshold) * 100, 1) : 0.0;

                return [
                    'metric' => 'distance_km',
                    'label' => (string) __('site.hist_next_km', ['value' => $this->formatInt($threshold)]),
                    'current' => $distance,
                    'target' => $threshold,
                    'progress_pct' => min(100.0, $pct),
                    'remaining' => max(0, $threshold - $distance),
                ];
            }
        }

        $playtime = (float) ($live['playtime_hours'] ?? 0);
        if ($playtime < self::PLAYTIME_HOURS_THRESHOLD) {
            $pct = round(($playtime / self::PLAYTIME_HOURS_THRESHOLD) * 100, 1);

            return [
                'metric' => 'playtime_hours',
                'label' => (string) __('site.hist_next_playtime', ['value' => $this->formatInt(self::PLAYTIME_HOURS_THRESHOLD)]),
                'current' => $playtime,
                'target' => self::PLAYTIME_HOURS_THRESHOLD,
                'progress_pct' => min(100.0, $pct),
                'remaining' => max(0, self::PLAYTIME_HOURS_THRESHOLD - $playtime),
            ];
        }

        return null;
    }

    private function formatInt(int|float $n): string
    {
        $locale = function_exists('locale') && locale() === 'en' ? 'en_GB' : 'fr_FR';
        if (class_exists(\NumberFormatter::class)) {
            $fmt = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);

            return (string) $fmt->format((int) $n);
        }

        return number_format((int) $n, 0, ',', ' ');
    }
}
