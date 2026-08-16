<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $recentModels */
/** @var list<array{key:string,label:string,description:string,group?:string}> $templates */
/** @var int $publishedCount */
/** @var int $modelsCount */
$recentModels = is_array($recentModels ?? null) ? $recentModels : [];
$templates = is_array($templates ?? null) ? $templates : [];
$publishedCount = (int) ($publishedCount ?? 0);
$modelsCount = (int) ($modelsCount ?? count($recentModels));
$canManage = (bool) ($canManage ?? false);
require __DIR__ . '/_subnav.php';

$grouped = [];
foreach ($templates as $tpl) {
    $g = trim((string) ($tpl['group'] ?? 'Générique'));
    if ($g === '') {
        $g = 'Générique';
    }
    $grouped[$g][] = $tpl;
}
$groupKeys = array_keys($grouped);
$firstGroup = $groupKeys[0] ?? '';
?>
<div class="breadcrumb">Athena / SSE / <strong>Modèles de mission</strong></div>

<section class="lab-hero" aria-labelledby="lab-hero-title">
    <div class="lab-hero__main">
        <p class="lab-hero__kicker">Atelier de préparation</p>
        <h1 id="lab-hero-title">Créer des modèles de mission</h1>
        <p class="lab-hero__lead">
            Préparez les profils, thèmes et listes narratives destinés aux missions Arma.
            Une fois prêts, emportez-les pour les appliquer en jeu via le module COMSPEC SSE.
        </p>
    </div>
    <div class="lab-hero__side">
        <?php if ($canManage): ?>
            <a class="btn" href="<?= $h(url('atak/sse/dev/modeles/nouveau')) ?>">Créer un modèle</a>
        <?php endif; ?>
        <div class="lab-hero__metrics">
            <a class="lab-hero__metric" href="<?= $h(url('atak/sse/dev/modeles')) ?>">
                <span>Modèles</span><strong><?= $modelsCount ?></strong>
            </a>
            <a class="lab-hero__metric" href="<?= $h(url('atak/sse/dev/modeles') . '?status=published') ?>">
                <span>Publiés</span><strong><?= $publishedCount ?></strong>
            </a>
        </div>
    </div>
</section>

<section class="panel lab-start" id="lab-start">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01</span> Démarrer rapidement</div>
        <div class="panel-meta">Modèles types par ère</div>
    </div>
    <div class="panel-body">
        <p class="lab-form-lead">Choisissez un point de départ, puis adaptez les listes à votre scénario.</p>
        <?php if ($groupKeys !== []): ?>
            <div class="lab-era-tabs" role="tablist" aria-label="Époque du modèle">
                <?php foreach ($groupKeys as $i => $groupLabel): ?>
                    <button type="button"
                            class="lab-era-tab <?= $i === 0 ? 'is-active' : '' ?>"
                            role="tab"
                            aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                            data-era="<?= $h($groupLabel) ?>">
                        <?= $h($groupLabel) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php foreach ($grouped as $groupLabel => $items): ?>
                <div class="lab-era-panel <?= $groupLabel === $firstGroup ? 'is-active' : '' ?>"
                     data-era-panel="<?= $h($groupLabel) ?>"
                     <?= $groupLabel === $firstGroup ? '' : 'hidden' ?>>
                    <div class="lab-tpl-grid">
                        <?php foreach ($items as $tpl): ?>
                            <article class="lab-tpl-card">
                                <p class="lab-tpl-card__era"><?= $h($groupLabel) ?></p>
                                <h3><?= $h($tpl['label'] ?? '') ?></h3>
                                <p><?= $h($tpl['description'] ?? '') ?></p>
                                <?php if ($canManage): ?>
                                    <a class="btn btn--ghost btn--sm" href="<?= $h(url('atak/sse/dev/modeles/nouveau') . '?modele=' . rawurlencode((string) ($tpl['key'] ?? ''))) ?>">
                                        Partir de ce modèle
                                    </a>
                                <?php else: ?>
                                    <p class="muted lab-tpl-card__locked">Consultation seule</p>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="muted">Aucun modèle type n’est disponible pour le moment.</p>
        <?php endif; ?>
    </div>
</section>

<section class="panel" style="margin-top:10px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">02</span> Kits mission (datasets)</div>
        <div class="panel-meta">Eden · Zeus · générateur</div>
    </div>
    <div class="panel-body">
        <p class="lab-form-lead">
            Packs narratifs prêts à poser en mission (graine stable, rôles, niveaux de révélation).
        </p>
        <?php
        $missionKits = is_array($missionKits ?? null) ? $missionKits : [];
        if ($missionKits === []):
        ?>
            <p class="muted">Aucun kit mission n’est publié pour le moment.</p>
        <?php else: ?>
            <div class="lab-tpl-grid">
                <?php foreach ($missionKits as $kit): ?>
                    <?php if (!is_array($kit)) {
                        continue;
                    } ?>
                    <article class="lab-tpl-card">
                        <p class="lab-tpl-card__era"><?= $h((string) ($kit['era'] ?? '')) ?></p>
                        <h3><?= $h((string) ($kit['label'] ?? '')) ?></h3>
                        <p><?= $h((string) ($kit['summary'] ?? '')) ?></p>
                        <p class="muted">Graine : <?= $h((string) ($kit['seed'] ?? '')) ?></p>
                        <?php
                        $roles = is_array($kit['roles'] ?? null) ? $kit['roles'] : [];
                        if ($roles !== []):
                        ?>
                            <ul class="muted" style="margin:.5rem 0 0;padding-left:1.1rem;font-size:.85rem">
                                <?php foreach ($roles as $role): ?>
                                    <?php if (!is_array($role)) {
                                        continue;
                                    } ?>
                                    <li><?= $h((string) ($role['label'] ?? '')) ?><?php
                                        $alias = trim((string) ($role['alias'] ?? ''));
                                        if ($alias !== '') {
                                            echo ' — ' . $h($alias);
                                        }
                                    ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="panel" style="margin-top:10px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">03</span> Scénarios de dossier complets</div>
        <div class="panel-meta">Athena + Arma</div>
    </div>
    <div class="panel-body lab-scenario-card">
        <p class="lab-form-lead">
            Générez un dossier fictif complet (identités, sites, pièces), importez-le dans Athena,
            puis emportez le pack terrain pour Arma 3.
        </p>
        <div class="lab-scenario-card__actions">
            <?php if ($canManage): ?>
                <a class="btn" href="<?= $h(url('atak/sse/dossiers/importer')) ?>">Importer un scénario</a>
            <?php endif; ?>
            <a class="link" href="<?= $h(url('atak/sse/guide')) ?>">Voir le guide des scénarios fictifs</a>
        </div>
    </div>
</section>

<section class="panel" style="margin-top:10px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">04</span> Modèles récents</div>
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
                        <td><span class="badge"><?= $h($m['status_label'] ?? '') ?></span></td>
                        <td><a class="btn-open" href="<?= $h(url('atak/sse/dev/modeles/' . (int) ($m['id'] ?? 0))) ?>">Ouvrir</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<script>
(function () {
    var root = document.getElementById('lab-start');
    if (!root) return;
    var tabs = root.querySelectorAll('[data-era]');
    var panels = root.querySelectorAll('[data-era-panel]');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var key = tab.getAttribute('data-era');
            tabs.forEach(function (t) {
                var on = t === tab;
                t.classList.toggle('is-active', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            panels.forEach(function (p) {
                var on = p.getAttribute('data-era-panel') === key;
                p.classList.toggle('is-active', on);
                p.hidden = !on;
            });
        });
    });
})();
</script>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
