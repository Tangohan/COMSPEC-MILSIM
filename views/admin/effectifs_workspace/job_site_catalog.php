<?php
declare(strict_types=1);
$siteGroups=is_array($siteJobGroups??null)?$siteJobGroups:[];
$showHiddenSite=!empty($jobShowHidden);
$hiddenTotal=(int)($jobHiddenCount??0);
$siteReady=!empty($jobEnrichmentReady);
?>
<section class="eff-jobs-site" aria-labelledby="eff-jobs-site-title">
    <div class="eff-jobs-pane-head"><div><p class="eff-category-path">Référentiel fourni par Athena</p><h2 id="eff-jobs-site-title">Fonctions du site</h2></div>
    <?php if($hiddenTotal>0): ?><a class="eff-jobs-text-action" data-jobs-nav href="<?= $h($base.'?'.http_build_query(['vue'=>'site']+($showHiddenSite?[]:['masquees'=>1]))) ?>"><?= $showHiddenSite?'Cacher les masquées':'Afficher les masquées' ?> (<?= $hiddenTotal ?>)</a><?php endif ?></div>
    <p class="eff-jobs-pane-hint">Masquez les fonctions que votre communauté n’utilise pas : elles disparaissent des choix de fonction. Rien n’est supprimé, et les membres qui en occupent une la gardent sur leur fiche et dans les effectifs. Les fonctions que vous créez apparaissent toujours en tête, avec le badge <?= \App\Support\JobRoleBadge::origin('community') ?>.</p>
    <?php if(!$siteReady): ?><p class="eff-jobs-warning">Le masquage des fonctions arrive avec la prochaine mise à jour du site.</p><?php endif ?>
    <?php if($siteGroups===[]): ?><p class="eff-jobs-empty">Toutes les fonctions du site sont masquées. Utilisez « Afficher les masquées » pour en réafficher.</p><?php else: ?>
    <form method="post" action="<?= $h($base.'/masquage') ?>" data-jobs-save class="eff-jobs-site-form">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><?php if($showHiddenSite): ?><input type="hidden" name="masquees" value="1"><?php endif ?>
        <input class="eff-jobs-search" type="search" data-jobs-search placeholder="Chercher une fonction du site…" aria-label="Rechercher une fonction du site">
        <?php if($canManage&&$siteReady): ?><div class="eff-jobs-site-actions"><span data-site-selected-count aria-live="polite"></span><button class="eff-btn" name="action" value="hide">Masquer la sélection</button><?php if($hiddenTotal>0): ?><button class="eff-btn" name="action" value="show">Réafficher la sélection</button><?php endif ?></div><?php endif ?>
        <?php foreach($siteGroups as $category=>$items): ?>
        <fieldset class="eff-jobs-site-group"><legend><?php if($canManage&&$siteReady): ?><label class="eff-jobs-checkbox"><input type="checkbox" data-site-select-all><?= $h($category) ?></label><?php else: ?><?= $h($category) ?><?php endif ?> <small><?= count($items) ?></small></legend>
        <ul class="eff-jobs-list">
        <?php foreach($items as $item): ?>
            <li data-jobs-row data-search="<?= $h($item['name'].' '.$item['subcategory'].' '.$category) ?>" class="<?= $item['hidden']?'is-hidden-job':'' ?>">
                <div class="eff-jobs-list__item eff-jobs-site-item" title="<?= $h($item['description']) ?>">
                    <?php if($canManage&&$siteReady): ?><input type="checkbox" name="slugs[]" value="<?= $h($item['slug']) ?>" data-site-job aria-label="<?= $h('Sélectionner '.$item['name']) ?>"><?php endif ?>
                    <strong><?= \App\Support\JobRoleBadge::html(['icon'=>$item['icon']],false) ?><?= $h($item['name']) ?> <?= \App\Support\JobRoleBadge::origin('site') ?><?php if($item['hidden']): ?> <span class="job-hidden-flag">Masquée</span><?php endif ?></strong>
                    <span><?= $h($item['subcategory']) ?><?php if($item['holders']>0): ?> · <?= (int)$item['holders'] ?> membre(s) l’occupent<?php endif ?><?php if($item['role_id']>0): ?> · <a data-jobs-nav href="<?= $h($base.'?'.http_build_query(['emploi'=>$item['role_id']]+($item['hidden']?['masquees'=>1]:[]))) ?>">Ouvrir la fiche</a><?php endif ?></span>
                </div>
            </li>
        <?php endforeach ?></ul></fieldset>
        <?php endforeach ?>
        <p class="eff-category-empty" data-jobs-empty hidden>Aucune fonction du site ne correspond.</p>
    </form>
    <?php endif ?>
</section>
