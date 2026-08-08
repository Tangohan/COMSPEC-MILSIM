<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $recentModels */
/** @var list<array{key:string,label:string,description:string}> $templates */
/** @var int $publishedCount */
/** @var int $modelsCount */
$recentModels = is_array($recentModels ?? null) ? $recentModels : [];
$templates = is_array($templates ?? null) ? $templates : [];
$publishedCount = (int) ($publishedCount ?? 0);
$modelsCount = (int) ($modelsCount ?? count($recentModels));
$canManage = (bool) ($canManage ?? false);
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <strong>Atelier de préparation</strong></div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Préparation mission</div>
        <h1>Atelier de préparation</h1>
        <p>
            Concevez ici les modèles destinés aux missions Arma : profils, thèmes narratifs,
            listes de contacts et messages. Une fois prêts, téléchargez-les pour les appliquer
            en jeu via le module COMSPEC SSE.
        </p>
    </div>
    <?php if ($canManage): ?>
        <div class="page-reference">
            <a class="btn" href="<?= $h(url('atak/sse/dev/modeles/nouveau')) ?>">Créer un modèle</a>
        </div>
    <?php endif; ?>
</div>

<div class="metrics-grid lab-metrics">
    <a class="metric lab-metric" href="<?= $h(url('atak/sse/dev/modeles')) ?>">
        <div class="metric-label">Modèles</div>
        <div class="metric-value"><?= $modelsCount ?></div>
        <div class="metric-detail">Bibliothèque →</div>
    </a>
    <a class="metric lab-metric" href="<?= $h(url('atak/sse/dev/modeles') . '?status=published') ?>">
        <div class="metric-label">Publiés</div>
        <div class="metric-value"><?= $publishedCount ?></div>
        <div class="metric-detail">Prêts pour la mission →</div>
    </a>
</div>

<section class="panel lab-flow">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01</span> Démarrer rapidement</div>
        <div class="panel-meta">Modèles types par ère</div>
    </div>
    <div class="panel-body">
        <p class="lab-form-lead">Choisissez un point de départ (Irak 2010–2020 ou Russie / Est 2020–2024), puis adaptez les listes à votre scénario.</p>
        <?php
        $grouped = [];
        foreach ($templates as $tpl) {
            $g = trim((string) ($tpl['group'] ?? 'Générique'));
            if ($g === '') {
                $g = 'Générique';
            }
            $grouped[$g][] = $tpl;
        }
        foreach ($grouped as $groupLabel => $items):
        ?>
            <h3 style="margin:18px 0 8px;font-size:1rem"><?= $h($groupLabel) ?></h3>
            <div class="lab-form-grid">
                <?php foreach ($items as $tpl): ?>
                    <div class="lab-form-field lab-form-field--span2" style="border:1px solid var(--border, #333);padding:12px;border-radius:4px">
                        <strong><?= $h($tpl['label']) ?></strong>
                        <p style="margin:6px 0 10px;opacity:.85"><?= $h($tpl['description']) ?></p>
                        <?php if ($canManage): ?>
                            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/dev/modeles/nouveau') . '?modele=' . rawurlencode((string) $tpl['key'])) ?>">
                                Partir de ce modèle
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel" style="margin-top:10px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">02</span> Modèles récents</div>
        <a class="link" href="<?= $h(url('atak/sse/dev/modeles')) ?>">Voir tous</a>
    </div>
    <?php if ($recentModels === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <strong>Aucun modèle pour l’instant</strong>
                <p>Créez un modèle dédié pour peupler vos sites sensibles en mission.</p>
                <?php if ($canManage): ?>
                    <a class="btn" href="<?= $h(url('atak/sse/dev/modeles/nouveau')) ?>">Créer le premier modèle</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Nom</th><th>Profil</th><th>Thème</th><th>État</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recentModels as $m): ?>
                    <tr>
                        <td><?= $h($m['name'] ?? '') ?></td>
                        <td><?= $h($m['profile_label'] ?? '') ?></td>
                        <td><?= $h($m['theme_label'] ?? '') ?></td>
                        <td><?= $h($m['status_label'] ?? '') ?></td>
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
