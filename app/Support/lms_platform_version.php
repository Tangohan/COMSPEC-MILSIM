<?php

declare(strict_types=1);

/**
 * Version « produit » du LMS (Studio + rendu des parcours), distincte du code déployé.
 */
function lms_platform_config(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $path = dirname(__DIR__, 2) . '/config/lms_platform.php';
    if (!is_file($path)) {
        return $cached = [
            'version' => '0.0.0',
            'label' => 'LMS',
            'changelog' => [],
        ];
    }
    /** @var array<string, mixed> $data */
    $data = require $path;

    return $cached = [
        'version' => isset($data['version']) && is_string($data['version']) ? $data['version'] : '0.0.0',
        'label' => isset($data['label']) && is_string($data['label']) ? $data['label'] : 'LMS',
        'changelog' => isset($data['changelog']) && is_array($data['changelog']) ? $data['changelog'] : [],
    ];
}

function lms_platform_version(): string
{
    return lms_platform_config()['version'];
}

/**
 * @return list<array{version: string, date: string, title: string, items: list<string>}>
 */
function lms_platform_changelog(): array
{
    $raw = lms_platform_config()['changelog'];
    $out = [];
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $v = isset($row['version']) && is_string($row['version']) ? $row['version'] : '';
        if ($v === '') {
            continue;
        }
        $items = [];
        if (isset($row['items']) && is_array($row['items'])) {
            foreach ($row['items'] as $it) {
                if (is_string($it) && trim($it) !== '') {
                    $items[] = trim($it);
                }
            }
        }
        $out[] = [
            'version' => $v,
            'date' => isset($row['date']) && is_string($row['date']) ? $row['date'] : '',
            'title' => isset($row['title']) && is_string($row['title']) ? $row['title'] : '',
            'items' => $items,
        ];
    }

    return $out;
}

function lms_platform_version_display_label(): string
{
    $c = lms_platform_config();

    return trim((string) ($c['label'] ?? 'LMS')) . ' — v' . lms_platform_version();
}

/**
 * @param array<string, mixed> $course Ligne training_courses
 */
function lms_course_studio_created_version_label(array $course): string
{
    $v = trim((string) ($course['lms_created_with_version'] ?? ''));
    if ($v === '') {
        return 'antérieure au suivi de version';
    }

    return 'v' . $v;
}

/**
 * @param array<string, mixed> $course
 */
function lms_course_studio_last_saved_version_label(array $course): ?string
{
    $v = trim((string) ($course['lms_last_saved_with_version'] ?? ''));
    if ($v === '') {
        return null;
    }

    return 'v' . $v;
}

/**
 * Formation créée (ou sans trace) sous une version strictement inférieure à la version actuelle du Studio.
 *
 * @param array<string, mixed> $course
 */
function lms_course_studio_created_before_current(array $course): bool
{
    $current = lms_platform_version();
    $created = trim((string) ($course['lms_created_with_version'] ?? ''));
    if ($created === '') {
        return true;
    }

    return version_compare($created, $current, '<');
}

/**
 * Dernière sauvegarde Studio enregistrée sous une version &lt; actuelle (ex. pas réenregistrée après montée de version).
 *
 * @param array<string, mixed> $course
 */
function lms_course_studio_last_save_behind_current(array $course): bool
{
    $current = lms_platform_version();
    $last = trim((string) ($course['lms_last_saved_with_version'] ?? ''));
    if ($last === '') {
        return false;
    }

    return version_compare($last, $current, '<');
}
