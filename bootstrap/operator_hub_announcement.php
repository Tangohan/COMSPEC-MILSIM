<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * Annonce « Nouveau : vos outils opérateur » (platform_alerts), publiée une seule fois pour 14 jours.
 * Le texte ne vaut que pour une annonce pas encore publiée : une ligne déjà en base n'est jamais réécrite.
 *
 * @return callable(PDO, ?\DateTimeImmutable=): bool vrai si l'annonce vient d'être créée
 */
return static function (PDO $pdo, ?\DateTimeImmutable $now = null): bool {
    $schema = new SchemaInspector($pdo);
    if (!$schema->tableExists('platform_alerts')) {
        return false;
    }
    $link = '/operateur?annonce=outils-operateur';
    $find = $pdo->prepare('SELECT id FROM platform_alerts WHERE cta_url = ? LIMIT 1');
    $find->execute([$link]);
    if ($find->fetchColumn() !== false) {
        return false;
    }
    $now ??= new \DateTimeImmutable('now');
    $row = [
        'kind' => 'novelty',
        'title' => 'Nouveau : vos outils opérateur',
        'body' => 'Vos identités, papiers, fichiers et matériel sont réunis sur une seule page. Ouvrez le menu Espaces, puis la tuile bleue « Outils opérateur ».',
        'cta_label' => 'Ouvrir mes outils',
        'cta_url' => $link,
        'starts_at' => $now->format('Y-m-d H:i:s'),
        'ends_at' => $now->modify('+14 days')->format('Y-m-d H:i:s'),
        'sort_order' => 0,
        'is_active' => 1,
        'audience_json' => json_encode(['guest' => false, 'authenticated' => true, 'free' => true, 'paid' => true]),
        'created_at' => $now->format('Y-m-d H:i:s'),
    ];
    if ($schema->columnExists('platform_alerts', 'dismissible')) {
        $row['dismissible'] = 1;
    }
    if ($schema->columnExists('platform_alerts', 'display_style')) {
        $row['display_style'] = 'classic';
    }
    $cols = array_keys($row);
    $pdo->prepare('INSERT INTO platform_alerts (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
        ->execute(array_values($row));

    return true;
};
