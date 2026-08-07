<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $models */
/** @var array<string,string> $statuses */
/** @var array<string,string> $profiles */
/** @var array{status?:string,profile?:string,q?:string} $filters */
$models = is_array($models ?? null) ? $models : [];
$statuses = is_array($statuses ?? null) ? $statuses : [];
$profiles = is_array($profiles ?? null) ? $profiles : [];
$filters = is_array($filters ?? null) ? $filters : [];
$canManage = (bool) ($canManage ?? false);
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/dev')) ?>">Atelier</a> / <strong>Modèles</strong></div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Atelier // Bibliothèque</div>
        <h1>Modèles de mission</h1>
        <p>Bibliothèque des profils réutilisables pour peupler les sites sensibles en Arma.</p>
    </div>
    <div class="page-reference"><strong>Vue // Registre</strong> Réf. ATH-SSE-DEV-MDL</div>
</div>

<form class="toolbar" method="get" action="<?= $h(url('atak/sse/dev/modeles')) ?>">
    <div class="toolbar-field">
        <label for="status">État</label>
        <select id="status" name="status">
            <option value="">Tous les états</option>
            <?php foreach ($statuses as $k => $lab): ?>
                <option value="<?= $h($k) ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="profile">Profil</label>
        <select id="profile" name="profile">
            <option value="">Tous les profils</option>
            <?php foreach ($profiles as $k => $lab): ?>
                <option value="<?= $h($k) ?>" <?= ($filters['profile'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="q">Recherche</label>
        <input id="q" name="q" type="search" value="<?= $h($filters['q'] ?? '') ?>" placeholder="Nom, auteur…">
    </div>
    <div class="toolbar-actions">
        <button class="btn" type="submit">Appliquer</button>
        <?php if ($canManage): ?>
            <a class="btn" href="<?= $h(url('atak/sse/dev/modeles/nouveau')) ?>">Nouveau modèle</a>
        <?php endif; ?>
    </div>
</form>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">02.01</span> Bibliothèque</div>
        <div class="panel-meta"><?= count($models) ?> modèle(s)</div>
    </div>
    <?php if ($models === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <strong>Aucun modèle</strong>
                <p>Créez un modèle ou partez d’un modèle type depuis l’atelier.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Nom</th>
                    <th>Profil</th>
                    <th>Région</th>
                    <th>Thème</th>
                    <th>État</th>
                    <th>Mis à jour</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($models as $m): ?>
                    <tr>
                        <td>
                            <strong><?= $h($m['name'] ?? '') ?></strong>
                            <?php if (!empty($m['author_label'])): ?>
                                <div style="opacity:.7;font-size:.85em"><?= $h($m['author_label']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= $h($m['profile_label'] ?? '') ?></td>
                        <td><?= $h($m['region_label'] ?? '') ?></td>
                        <td><?= $h($m['theme_label'] ?? '') ?></td>
                        <td><?= $h($m['status_label'] ?? '') ?></td>
                        <td><?= $h($m['updated_at'] ?? '') ?></td>
                        <td><a class="btn-open" href="<?= $h(url('atak/sse/dev/modeles/' . (int) ($m['id'] ?? 0))) ?>">Ouvrir</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
