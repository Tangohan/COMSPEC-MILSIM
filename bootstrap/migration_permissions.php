<?php

declare(strict_types=1);

/**
 * Création idempotente d'une permission globale (tenant_id NULL) depuis une migration.
 *
 * Le schéma de référence identifie une permission par `slug` ; certaines bases importées
 * ont en plus une colonne héritée `code` (et parfois `label` / `description`). On s'adapte
 * aux colonnes réellement présentes au lieu de supposer `code`, qui fait planter une base neuve.
 */
function migration_ensure_global_permission(PDO $pdo, string $slug, string $name, string $description, string $module): void
{
    $st = $pdo->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $st->execute(['permissions']);
    $cols = array_map('strtolower', array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN)));
    if ($cols === []) {
        return;
    }
    $has = static fn (string $c): bool => in_array($c, $cols, true);
    $key = $has('slug') ? 'slug' : ($has('code') ? 'code' : '');
    if ($key === '') {
        return;
    }

    $find = $pdo->prepare("SELECT id FROM permissions WHERE {$key} = ? LIMIT 1");
    $find->execute([$slug]);
    if ($find->fetchColumn() !== false) {
        return;
    }

    $values = ['name' => $name, $key => $slug];
    if ($has('slug')) {
        $values['slug'] = $slug;
    }
    if ($has('code')) {
        $values['code'] = $slug;
    }
    if ($has('module')) {
        $values['module'] = $module;
    }
    if ($has('description')) {
        $values['description'] = $description;
    }
    if ($has('label')) {
        $values['label'] = $name;
    }
    if ($has('scope')) {
        $values['scope'] = 'community';
    }
    $names = array_keys($values);
    $sql = 'INSERT INTO permissions (' . implode(', ', $names) . ($has('created_at') ? ', created_at' : '')
        . ') VALUES (' . implode(', ', array_fill(0, count($names), '?')) . ($has('created_at') ? ', NOW()' : '') . ')';
    $pdo->prepare($sql)->execute(array_values($values));
}
