<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $templates */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$templates = is_array($templates ?? null) ? $templates : [];
?>
<link href="<?= $h(asset_url('assets/css/member-integration.css')) ?>" rel="stylesheet">
<p><a href="<?= $h(url('back-office/integration-membres')) ?>">← Parcours d’intégration</a></p>
<h1>Modèles de parcours</h1>
<p class="mi-muted">Un modèle publié s’applique aux nouvelles arrivées. Les suivis déjà commencés conservent leur version.</p>
<p class="mi-actions"><a class="mi-btn" href="<?= $h(url('back-office/integration-membres/modeles/nouveau')) ?>">Nouveau modèle</a></p>
<table class="mi-table">
    <thead><tr><th>Nom</th><th>Version</th><th>Durée</th><th>Actif</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($templates as $t): ?>
        <tr>
            <td><?= $h($t['name'] ?? '') ?></td>
            <td><?= (int) ($t['version'] ?? 1) ?></td>
            <td><?= (int) ($t['duration_days'] ?? 0) ?> jours</td>
            <td><?= !empty($t['is_active']) ? 'Oui' : 'Non' ?></td>
            <td><a href="<?= $h(url('back-office/integration-membres/modeles/' . (int) ($t['id'] ?? 0))) ?>">Modifier</a></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($templates === []): ?>
        <tr><td colspan="5" class="mi-muted">Aucun modèle. Un parcours « Intégration recrue » peut être créé automatiquement.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
