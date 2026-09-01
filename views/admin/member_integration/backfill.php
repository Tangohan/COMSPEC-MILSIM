<?php
declare(strict_types=1);

/** @var array{candidates?: list<array<string,mixed>>, would_create?: int, ignored?: int} $preview */
/** @var string $since */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$preview = is_array($preview ?? null) ? $preview : [];
$cands = is_array($preview['candidates'] ?? null) ? $preview['candidates'] : [];
?>
<link href="<?= $h(asset_url('assets/css/member-integration.css')) ?>" rel="stylesheet">
<p><a href="<?= $h(url('back-office/integration-membres')) ?>">← Parcours d’intégration</a></p>
<h1>Reprise des arrivées récentes</h1>
<p class="mi-muted">Aucun parcours n’est créé en masse sans votre accord. Cochez les membres concernés, puis lancez la reprise.</p>
<form method="get" class="mi-toolbar" action="<?= $h(url('back-office/integration-membres/reprise')) ?>">
    <label>Arrivés depuis <input type="date" name="since" value="<?= $h($since ?? '') ?>"></label>
    <button type="submit">Prévisualiser</button>
</form>
<p>À créer : <?= (int) ($preview['would_create'] ?? 0) ?> · Déjà suivis : <?= (int) ($preview['ignored'] ?? 0) ?></p>
<form method="post" action="<?= $h(url('back-office/integration-membres/reprise')) ?>" class="mi-form">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="since" value="<?= $h($since ?? '') ?>">
    <table class="mi-table">
        <thead><tr><th></th><th>Membre</th><th>Arrivée</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($cands as $c): ?>
            <tr>
                <td>
                    <?php if (empty($c['already_tracked'])): ?>
                        <input type="checkbox" name="user_ids[]" value="<?= (int) ($c['id'] ?? 0) ?>" checked>
                    <?php endif; ?>
                </td>
                <td><?= $h($c['display_name'] ?? $c['email'] ?? '') ?></td>
                <td><?= $h($c['created_at'] ?? '') ?></td>
                <td><?= !empty($c['already_tracked']) ? 'Déjà en cours' : 'À ouvrir' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($cands === []): ?>
            <tr><td colspan="4" class="mi-muted">Personne à reprendre pour cette période.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php if ($cands !== []): ?>
        <div class="mi-actions"><button class="mi-btn" type="submit">Créer les parcours cochés</button></div>
    <?php endif; ?>
</form>
