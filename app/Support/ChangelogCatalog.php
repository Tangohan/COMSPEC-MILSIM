<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Journal produit public Athena.
 * Source unique des releases, modules et feuille de route.
 * À terme, un dépôt SQL pourra remplacer hydrate() sans changer la vue.
 */
final class ChangelogCatalog
{
    public const STATUS_RELEASED = 'released';
    public const STATUS_TESTING = 'testing';
    public const STATUS_DEVELOPING = 'developing';
    public const STATUS_EXPLORING = 'exploring';

    public const KIND_NEW = 'new';
    public const KIND_IMPROVED = 'improved';
    public const KIND_FIXED = 'fixed';
    public const KIND_SECURITY = 'security';
    public const KIND_PERFORMANCE = 'performance';
    public const KIND_EXPERIMENTAL = 'experimental';

    /**
     * @param array<string, int> $kpis
     * @return array<string, mixed>
     */
    public static function hydrate(array $kpis = []): array
    {
        $releases = array_map([self::class, 'resolveRelease'], self::releasesRaw());
        $featured = null;
        foreach ($releases as $release) {
            if (!empty($release['featured'])) {
                $featured = $release;
                break;
            }
        }
        if ($featured === null && $releases !== []) {
            $featured = $releases[0];
        }

        return [
            'modules' => array_map([self::class, 'resolveModule'], self::modulesRaw()),
            'releases' => $releases,
            'featured' => $featured,
            'pipeline' => array_map([self::class, 'resolvePipeline'], self::pipelineRaw()),
            'roadmap' => array_map([self::class, 'resolveRoadmap'], self::roadmapRaw()),
            'stats' => self::resolveStats($kpis),
            'years' => self::years($releases),
            'filters' => self::filters(),
            'kindLabels' => self::kindLabels(),
            'categoryLabels' => self::categoryLabels(),
            'statusLabels' => self::statusLabels(),
            'typeLabels' => [
                'major' => (string) __('site.cl_type_major'),
                'minor' => (string) __('site.cl_type_minor'),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function releasesRaw(): array
    {
        return [
            [
                'id' => '2026-08-planning',
                'version' => '2026.08b',
                'version_label' => 'Athena',
                'date' => '2026-08',
                'year' => 2026,
                'month' => 8,
                'featured' => true,
                'type' => 'major',
                'kinds' => [self::KIND_NEW],
                'categories' => ['c2', 'atak'],
                'status' => self::STATUS_RELEASED,
                'visibility' => 'public',
                'title_key' => 'site.cl_2026_08_plan_t',
                'summary_key' => 'site.cl_2026_08_plan_b',
                'why_key' => 'site.cl_2026_08_plan_why',
                'audiences' => [
                    'ops' => 'site.cl_2026_08_plan_ops',
                    'cmd' => 'site.cl_2026_08_plan_cmd',
                    'admin' => 'site.cl_2026_08_plan_admin',
                ],
                'feature_keys' => [
                    'site.cl_2026_08_plan_i1',
                    'site.cl_2026_08_plan_i2',
                    'site.cl_2026_08_plan_i3',
                    'site.cl_2026_08_plan_i4',
                    'site.cl_2026_08_plan_i5',
                    'site.cl_2026_08_plan_i6',
                ],
                'image' => 'assets/images/night-team.jpg',
                'gallery' => [
                    ['src' => 'assets/images/fog-team.jpg', 'alt_key' => 'site.cl_media_ops_alt'],
                    ['src' => 'assets/images/fog-banner.jpg', 'alt_key' => 'site.cl_media_night_alt'],
                ],
                'video' => null,
                'before_after' => null,
                'links' => [
                    ['label_key' => 'site.cl_link_planning', 'href' => 'back-office/planification'],
                    ['label_key' => 'site.cl_link_about', 'href' => 'a-propos'],
                ],
                'availability_key' => 'site.cl_avail_all',
            ],
            [
                'id' => '2026-08-intel',
                'version' => '2026.08',
                'version_label' => 'Athena',
                'date' => '2026-08',
                'year' => 2026,
                'month' => 8,
                'featured' => false,
                'type' => 'major',
                'kinds' => [self::KIND_NEW, self::KIND_IMPROVED],
                'categories' => ['sse', 'atak', 'intel'],
                'status' => self::STATUS_RELEASED,
                'visibility' => 'public',
                'title_key' => 'site.cl_2026_08_t',
                'summary_key' => 'site.cl_2026_08_b',
                'why_key' => 'site.cl_2026_08_why',
                'audiences' => [
                    'ops' => 'site.cl_2026_08_ops',
                    'cmd' => 'site.cl_2026_08_cmd',
                    'admin' => 'site.cl_2026_08_admin',
                ],
                'feature_keys' => [
                    'site.cl_2026_08_i1',
                    'site.cl_2026_08_i2',
                    'site.cl_2026_08_i3',
                    'site.cl_2026_08_i4',
                    'site.cl_2026_08_i5',
                    'site.cl_2026_08_i6',
                ],
                'image' => 'assets/images/fog-team.jpg',
                'gallery' => [
                    ['src' => 'assets/images/fog-banner.jpg', 'alt_key' => 'site.cl_media_ops_alt'],
                    ['src' => 'assets/images/night-team.jpg', 'alt_key' => 'site.cl_media_night_alt'],
                ],
                'video' => null,
                'before_after' => null,
                'links' => [
                    ['label_key' => 'site.cl_link_sse', 'href' => 'sse'],
                    ['label_key' => 'site.cl_link_about', 'href' => 'a-propos'],
                ],
                'availability_key' => 'site.cl_avail_atak_sse',
            ],
            [
                'id' => '2026-07-sse',
                'version' => '1.4.0',
                'version_label' => 'Overwatch',
                'date' => '2026-07',
                'year' => 2026,
                'month' => 7,
                'featured' => false,
                'type' => 'major',
                'kinds' => [self::KIND_NEW],
                'categories' => ['sse', 'atak', 'intel'],
                'status' => self::STATUS_RELEASED,
                'visibility' => 'public',
                'title_key' => 'site.cl_2026_07_sse_t',
                'summary_key' => 'site.cl_2026_07_sse_b',
                'why_key' => 'site.cl_2026_07_sse_why',
                'audiences' => [
                    'ops' => 'site.cl_2026_07_sse_ops',
                    'cmd' => 'site.cl_2026_07_sse_cmd',
                    'admin' => 'site.cl_2026_07_sse_admin',
                ],
                'feature_keys' => [
                    'site.cl_2026_07_sse_i1',
                    'site.cl_2026_07_sse_i2',
                    'site.cl_2026_07_sse_i3',
                    'site.cl_2026_07_sse_i4',
                ],
                'image' => 'assets/images/night-team.jpg',
                'gallery' => [],
                'video' => null,
                'before_after' => null,
                'links' => [
                    ['label_key' => 'site.cl_link_sse', 'href' => 'sse'],
                ],
                'availability_key' => 'site.cl_avail_atak',
            ],
            [
                'id' => '2026-07-plans',
                'version' => '2026.07',
                'version_label' => 'Athena',
                'date' => '2026-07',
                'year' => 2026,
                'month' => 7,
                'featured' => false,
                'type' => 'minor',
                'kinds' => [self::KIND_NEW, self::KIND_IMPROVED],
                'categories' => ['platform', 'admin', 'c2'],
                'status' => self::STATUS_RELEASED,
                'visibility' => 'public',
                'title_key' => 'site.cl_2026_07_t',
                'summary_key' => 'site.cl_2026_07_b',
                'why_key' => 'site.cl_2026_07_why',
                'audiences' => [
                    'ops' => 'site.cl_2026_07_ops',
                    'cmd' => 'site.cl_2026_07_cmd',
                    'admin' => 'site.cl_2026_07_admin',
                ],
                'feature_keys' => [
                    'site.cl_2026_07_i1',
                    'site.cl_2026_07_i2',
                    'site.cl_2026_07_i3',
                    'site.cl_2026_07_i4',
                ],
                'image' => null,
                'gallery' => [],
                'video' => null,
                'before_after' => null,
                'links' => [
                    ['label_key' => 'site.cl_link_create', 'href' => 'communities/create'],
                ],
                'availability_key' => 'site.cl_avail_plans',
            ],
            [
                'id' => '2026-04-proplus',
                'version' => '2026.04',
                'version_label' => 'Athena',
                'date' => '2026-04',
                'year' => 2026,
                'month' => 4,
                'featured' => false,
                'type' => 'minor',
                'kinds' => [self::KIND_NEW],
                'categories' => ['platform', 'training', 'admin'],
                'status' => self::STATUS_RELEASED,
                'visibility' => 'public',
                'title_key' => 'site.cl_2026_04_t',
                'summary_key' => 'site.cl_2026_04_b',
                'why_key' => 'site.cl_2026_04_why',
                'audiences' => [
                    'ops' => 'site.cl_2026_04_ops',
                    'cmd' => 'site.cl_2026_04_cmd',
                    'admin' => 'site.cl_2026_04_admin',
                ],
                'feature_keys' => [
                    'site.cl_2026_04_i1',
                    'site.cl_2026_04_i2',
                    'site.cl_2026_04_i3',
                ],
                'image' => null,
                'gallery' => [],
                'video' => null,
                'before_after' => null,
                'links' => [
                    ['label_key' => 'site.cl_link_create', 'href' => 'communities/create'],
                ],
                'availability_key' => 'site.cl_avail_proplus',
            ],
            [
                'id' => '2025-12-tenants',
                'version' => '2025.12',
                'version_label' => 'Athena',
                'date' => '2025-12',
                'year' => 2025,
                'month' => 12,
                'featured' => false,
                'type' => 'major',
                'kinds' => [self::KIND_NEW],
                'categories' => ['platform', 'admin', 'personnel'],
                'status' => self::STATUS_RELEASED,
                'visibility' => 'public',
                'title_key' => 'site.cl_2025_12_t',
                'summary_key' => 'site.cl_2025_12_b',
                'why_key' => 'site.cl_2025_12_why',
                'audiences' => [
                    'ops' => 'site.cl_2025_12_ops',
                    'cmd' => 'site.cl_2025_12_cmd',
                    'admin' => 'site.cl_2025_12_admin',
                ],
                'feature_keys' => [
                    'site.cl_2025_12_i1',
                    'site.cl_2025_12_i2',
                    'site.cl_2025_12_i3',
                ],
                'image' => 'assets/images/fog-banner.jpg',
                'gallery' => [],
                'video' => null,
                'before_after' => null,
                'links' => [
                    ['label_key' => 'site.cl_link_create', 'href' => 'communities/create'],
                    ['label_key' => 'site.cl_link_about', 'href' => 'a-propos'],
                ],
                'availability_key' => 'site.cl_avail_all',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function modulesRaw(): array
    {
        return [
            [
                'id' => 'c2',
                'filter' => 'command',
                'name_key' => 'site.cl_mod_c2_n',
                'body_key' => 'site.cl_mod_c2_b',
                'status_key' => 'site.cl_mod_status_live',
                'update_key' => 'site.cl_mod_c2_u',
                'href' => 'a-propos',
            ],
            [
                'id' => 'atak',
                'filter' => 'atak',
                'name_key' => 'site.cl_mod_atak_n',
                'body_key' => 'site.cl_mod_atak_b',
                'status_key' => 'site.cl_mod_status_live',
                'update_key' => 'site.cl_mod_atak_u',
                'href' => 'a-propos',
            ],
            [
                'id' => 'intel',
                'filter' => 'intel',
                'name_key' => 'site.cl_mod_intel_n',
                'body_key' => 'site.cl_mod_intel_b',
                'status_key' => 'site.cl_mod_status_live',
                'update_key' => 'site.cl_mod_intel_u',
                'href' => 'sse',
            ],
            [
                'id' => 'personnel',
                'filter' => 'personnel',
                'name_key' => 'site.cl_mod_pers_n',
                'body_key' => 'site.cl_mod_pers_b',
                'status_key' => 'site.cl_mod_status_live',
                'update_key' => 'site.cl_mod_pers_u',
                'href' => 'communities',
            ],
            [
                'id' => 'training',
                'filter' => 'training',
                'name_key' => 'site.cl_mod_lms_n',
                'body_key' => 'site.cl_mod_lms_b',
                'status_key' => 'site.cl_mod_status_live',
                'update_key' => 'site.cl_mod_lms_u',
                'href' => 'a-propos',
            ],
            [
                'id' => 'admin',
                'filter' => 'platform',
                'name_key' => 'site.cl_mod_admin_n',
                'body_key' => 'site.cl_mod_admin_b',
                'status_key' => 'site.cl_mod_status_live',
                'update_key' => 'site.cl_mod_admin_u',
                'href' => 'communities/create',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function pipelineRaw(): array
    {
        return [
            ['title_key' => 'site.cl_pipe_motion', 'status' => self::STATUS_TESTING],
            ['title_key' => 'site.cl_pipe_relief', 'status' => self::STATUS_TESTING],
            ['title_key' => 'site.cl_pipe_eta', 'status' => self::STATUS_TESTING],
            ['title_key' => 'site.cl_pipe_terrain', 'status' => self::STATUS_DEVELOPING],
            ['title_key' => 'site.cl_pipe_bft', 'status' => self::STATUS_TESTING],
            ['title_key' => 'site.cl_pipe_radio', 'status' => self::STATUS_EXPLORING],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function roadmapRaw(): array
    {
        return [
            ['when_key' => 'site.cl_road_now_k', 'body_key' => 'site.cl_road_now_b'],
            ['when_key' => 'site.cl_road_next_k', 'body_key' => 'site.cl_road_next_b'],
            ['when_key' => 'site.cl_road_later_k', 'body_key' => 'site.cl_road_later_b'],
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function resolveRelease(array $raw): array
    {
        $features = [];
        foreach ($raw['feature_keys'] ?? [] as $key) {
            $features[] = ['kind' => self::KIND_NEW, 'text' => (string) __((string) $key)];
        }
        $gallery = [];
        foreach ($raw['gallery'] ?? [] as $item) {
            $gallery[] = [
                'src' => (string) ($item['src'] ?? ''),
                'alt' => (string) __((string) ($item['alt_key'] ?? 'site.cl_media_ops_alt')),
            ];
        }
        $links = [];
        foreach ($raw['links'] ?? [] as $link) {
            $href = (string) ($link['href'] ?? '');
            $links[] = [
                'label' => (string) __((string) ($link['label_key'] ?? 'site.cl_link_about')),
                'href' => $href !== '' ? url($href) : '#',
            ];
        }
        $audiences = [];
        foreach ($raw['audiences'] ?? [] as $role => $key) {
            $audiences[$role] = (string) __((string) $key);
        }
        $title = (string) __((string) $raw['title_key']);
        $summary = (string) __((string) $raw['summary_key']);
        $why = isset($raw['why_key']) ? (string) __((string) $raw['why_key']) : '';
        $search = strtolower($title . ' ' . $summary . ' ' . implode(' ', array_column($features, 'text')));

        return [
            'id' => (string) $raw['id'],
            'version' => (string) $raw['version'],
            'version_label' => (string) $raw['version_label'],
            'date' => (string) $raw['date'],
            'year' => (int) $raw['year'],
            'month' => (int) $raw['month'],
            'month_label' => self::monthLabel((int) $raw['month']),
            'featured' => !empty($raw['featured']),
            'type' => (string) $raw['type'],
            'kinds' => array_values($raw['kinds'] ?? []),
            'categories' => array_values($raw['categories'] ?? []),
            'filter_groups' => self::filterGroups(array_values($raw['categories'] ?? [])),
            'status' => (string) $raw['status'],
            'visibility' => (string) ($raw['visibility'] ?? 'public'),
            'title' => $title,
            'summary' => $summary,
            'why' => $why,
            'audiences' => $audiences,
            'features' => $features,
            'image' => $raw['image'] ?? null,
            'gallery' => $gallery,
            'video' => $raw['video'] ?? null,
            'before_after' => $raw['before_after'] ?? null,
            'links' => $links,
            'availability' => isset($raw['availability_key']) ? (string) __((string) $raw['availability_key']) : '',
            'search' => $search,
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function resolveModule(array $raw): array
    {
        $href = (string) ($raw['href'] ?? '');

        return [
            'id' => (string) $raw['id'],
            'filter' => (string) ($raw['filter'] ?? 'platform'),
            'name' => (string) __((string) $raw['name_key']),
            'body' => (string) __((string) $raw['body_key']),
            'status' => (string) __((string) $raw['status_key']),
            'update' => (string) __((string) $raw['update_key']),
            'href' => $href !== '' ? url($href) : '#',
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function resolvePipeline(array $raw): array
    {
        return [
            'title' => (string) __((string) $raw['title_key']),
            'status' => (string) $raw['status'],
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function resolveRoadmap(array $raw): array
    {
        return [
            'when' => (string) __((string) $raw['when_key']),
            'body' => (string) __((string) $raw['body_key']),
        ];
    }

    /**
     * @param array<string, int> $kpis
     * @return list<array{value: string, label: string}>
     */
    private static function resolveStats(array $kpis): array
    {
        $stats = [
            ['value' => '12+', 'label' => (string) __('site.cl_stat_modules')],
            ['value' => '1', 'label' => (string) __('site.cl_stat_platform')],
            ['value' => 'C2', 'label' => (string) __('site.cl_stat_c2')],
            ['value' => 'Arma 3', 'label' => (string) __('site.cl_stat_arma')],
            ['value' => 'Multi', 'label' => (string) __('site.cl_stat_multi')],
        ];
        $communities = (int) ($kpis['communities_total'] ?? 0);
        if ($communities > 0) {
            array_unshift($stats, [
                'value' => (string) $communities,
                'label' => (string) __('site.cl_stat_communities'),
            ]);
        }

        return $stats;
    }

    /**
     * @param list<array<string, mixed>> $releases
     * @return list<int>
     */
    private static function years(array $releases): array
    {
        $years = [];
        foreach ($releases as $release) {
            $years[(int) $release['year']] = true;
        }
        $out = array_keys($years);
        rsort($out);

        return $out;
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private static function filters(): array
    {
        return [
            ['id' => 'all', 'label' => (string) __('site.cl_filter_all')],
            ['id' => 'command', 'label' => (string) __('site.cl_filter_command')],
            ['id' => 'atak', 'label' => (string) __('site.cl_filter_atak')],
            ['id' => 'intel', 'label' => (string) __('site.cl_filter_intel')],
            ['id' => 'personnel', 'label' => (string) __('site.cl_filter_personnel')],
            ['id' => 'training', 'label' => (string) __('site.cl_filter_training')],
            ['id' => 'platform', 'label' => (string) __('site.cl_filter_platform')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function kindLabels(): array
    {
        return [
            self::KIND_NEW => (string) __('site.cl_kind_new'),
            self::KIND_IMPROVED => (string) __('site.cl_kind_improved'),
            self::KIND_FIXED => (string) __('site.cl_kind_fixed'),
            self::KIND_SECURITY => (string) __('site.cl_kind_security'),
            self::KIND_PERFORMANCE => (string) __('site.cl_kind_performance'),
            self::KIND_EXPERIMENTAL => (string) __('site.cl_kind_experimental'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return [
            'atak' => 'ATAK',
            'c2' => 'C2',
            'sse' => 'SSE',
            'intel' => (string) __('site.cl_cat_intel'),
            'personnel' => (string) __('site.cl_cat_personnel'),
            'training' => (string) __('site.cl_cat_training'),
            'admin' => (string) __('site.cl_cat_admin'),
            'platform' => (string) __('site.cl_cat_platform'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_RELEASED => (string) __('site.cl_st_released'),
            self::STATUS_TESTING => (string) __('site.cl_st_testing'),
            self::STATUS_DEVELOPING => (string) __('site.cl_st_developing'),
            self::STATUS_EXPLORING => (string) __('site.cl_st_exploring'),
        ];
    }

    /**
     * @param list<string> $categories
     * @return list<string>
     */
    private static function filterGroups(array $categories): array
    {
        $map = [
            'c2' => 'command',
            'atak' => 'atak',
            'sse' => 'intel',
            'intel' => 'intel',
            'personnel' => 'personnel',
            'training' => 'training',
            'admin' => 'platform',
            'platform' => 'platform',
        ];
        $out = [];
        foreach ($categories as $cat) {
            $g = $map[$cat] ?? 'platform';
            $out[$g] = $g;
        }

        return array_values($out);
    }

    private static function monthLabel(int $month): string
    {
        $keys = [
            1 => 'site.cl_month_01',
            2 => 'site.cl_month_02',
            3 => 'site.cl_month_03',
            4 => 'site.cl_month_04',
            5 => 'site.cl_month_05',
            6 => 'site.cl_month_06',
            7 => 'site.cl_month_07',
            8 => 'site.cl_month_08',
            9 => 'site.cl_month_09',
            10 => 'site.cl_month_10',
            11 => 'site.cl_month_11',
            12 => 'site.cl_month_12',
        ];

        return isset($keys[$month]) ? (string) __($keys[$month]) : '';
    }
}
