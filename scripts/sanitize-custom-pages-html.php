#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Purge rétroactive des Documentations HTML (training_formation_custom_pages) enregistrées
 * avant la mise en place du filtrage HTML (App\Support\HtmlContentSanitizer).
 *
 * Re-sanitise html_body, intro_html et le HTML de chaque chapitre (sections_json) pour
 * toutes les pages existantes, toutes communautés confondues. N'écrit en base que les
 * lignes réellement modifiées par la sanitisation.
 *
 * Usage :
 *   php scripts/sanitize-custom-pages-html.php --dry-run   (rapport seul, aucune écriture)
 *   php scripts/sanitize-custom-pages-html.php             (applique les corrections)
 */

use App\Core\Database;
use App\Support\HtmlContentSanitizer;

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Support/HtmlContentSanitizer.php';

$options = getopt('', ['dry-run']);
$dryRun = array_key_exists('dry-run', $options);

$pdo = Database::getPdo();

$stmt = $pdo->query('SELECT id, tenant_id, title, html_body, intro_html, sections_json FROM training_formation_custom_pages ORDER BY id ASC');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($rows);
$changed = 0;

$updateStmt = $pdo->prepare(
    'UPDATE training_formation_custom_pages SET html_body = ?, intro_html = ?, sections_json = ? WHERE id = ?'
);
$activityStmt = $pdo->prepare(
    'INSERT INTO training_formation_custom_page_activity (page_id, tenant_id, actor_user_id, action, details_json, created_at) VALUES (?, ?, NULL, ?, ?, NOW())'
);

foreach ($rows as $row) {
    $id = (int) $row['id'];
    $tenantId = (int) $row['tenant_id'];
    $origBody = (string) ($row['html_body'] ?? '');
    $origIntro = (string) ($row['intro_html'] ?? '');
    $origSections = $row['sections_json'] ?? null;

    $cleanBody = HtmlContentSanitizer::sanitize($origBody);
    $cleanIntro = HtmlContentSanitizer::sanitize($origIntro);

    $cleanSections = $origSections;
    if (is_string($origSections) && $origSections !== '') {
        $decoded = json_decode($origSections, true);
        if (is_array($decoded)) {
            foreach ($decoded as $i => $section) {
                if (is_array($section) && isset($section['html'])) {
                    $decoded[$i]['html'] = HtmlContentSanitizer::sanitize((string) $section['html']);
                }
            }
            $cleanSections = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    if ($cleanBody === $origBody && $cleanIntro === $origIntro && $cleanSections === $origSections) {
        continue;
    }

    $changed++;
    $title = (string) ($row['title'] ?? ('#' . $id));
    echo "Page #$id [tenant $tenantId] « $title » : contenu modifié par la sanitisation.\n";

    if ($dryRun) {
        continue;
    }

    $updateStmt->execute([$cleanBody, $cleanIntro, $cleanSections, $id]);
    $activityStmt->execute([$id, $tenantId, 'security_sanitized', json_encode(['reason' => 'retroactive_html_sanitization'], JSON_UNESCAPED_UNICODE)]);
}

echo "\n" . ($dryRun ? '[dry-run] ' : '') . "$changed / $total page(s) affectée(s) par la sanitisation.\n";
exit(0);
