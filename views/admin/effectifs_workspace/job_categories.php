<?php
declare(strict_types=1);

$tree = $categoryTreeRows ?? \App\Support\PersonnelJobCategoryTree::rows($categories);
$creatingCategory = !empty($creatingJobCategory);
$category = $creatingCategory ? null : ($selectedJobCategory ?? null);
$categoryId = (int) ($category['id'] ?? 0);
$parentId = $creatingCategory ? (int) ($newCategoryParent ?? 0) : (int) ($category['parent_id'] ?? 0);
if ($creatingCategory && !isset($categoryById[$parentId])) $parentId = 0;
$excludedParents = $categoryId > 0 ? array_fill_keys(\App\Support\PersonnelJobCategoryTree::descendantIds($categories, $categoryId), true) : [];
$parentChoices = array_values(array_filter($tree, static fn (array $row): bool => !isset($excludedParents[(int) $row['id']])));
$selectedTree = null;
foreach ($tree as $row) if ((int) $row['id'] === $categoryId) $selectedTree = $row;
$categoryUrl = static fn (array $query): string => $base . '?' . http_build_query(['vue'=>'categories'] + $query);
?>
<section class="eff-category-workspace" aria-label="Organisation des catégories">
    <div class="eff-category-intro">
        <div><h2>Des dossiers pour ranger vos emplois</h2>
        <p>Une catégorie regroupe des emplois et des fonctions. Une sous-catégorie permet de les ranger plus précisément à l’intérieur d’une catégorie.</p>
        <p class="eff-category-example"><span>Exemple</span> Opérations <b aria-hidden="true">›</b> Transmissions <b aria-hidden="true">›</b> Opérateur radio <small>(emploi)</small></p>
        <p class="eff-card__hint">Ce rangement ne change ni les affectations ORBAT, ni les titulaires, ni les droits d’accès.</p></div>
        <?php if ($canManage): ?><a class="eff-btn eff-btn--primary" data-jobs-nav href="<?= $h($categoryUrl(['nouvelle_categorie'=>1])) ?>">+ Créer une catégorie</a><?php endif ?>
    </div>
    <div class="eff-category-layout">
        <div class="eff-category-browser">
            <div class="eff-category-browser__head"><h2>Votre organisation <span><?= count($tree) ?></span></h2>
            <?php if ($tree !== []): ?><label class="eff-category-search-label"><span class="sr-only">Rechercher une catégorie</span><input type="search" data-category-search placeholder="Rechercher une catégorie…" aria-label="Rechercher une catégorie"></label><?php endif ?></div>
            <?php if ($tree === []): ?><div class="eff-category-empty"><h3>Commencez par une catégorie</h3><p>Par exemple « Opérations ». Vous pourrez ensuite créer « Transmissions » comme sous-catégorie et y ranger vos emplois radio.</p></div>
            <?php else: ?><ul class="eff-category-tree">
            <?php foreach ($tree as $row): $cid=(int)$row['id']; $direct=(int)($row['job_count']??0); ?>
                <li class="eff-category-node<?= $categoryId===$cid?' is-current':'' ?>" style="--category-depth:<?= min(6,(int)$row['depth']) ?>" data-category-row data-category-id="<?= $cid ?>" data-category-parent="<?= (int)$row['display_parent_id'] ?>" data-search="<?= $h($row['path']) ?>">
                    <div class="eff-category-node__line">
                        <a class="eff-category-node__name" data-jobs-nav href="<?= $h($categoryUrl(['categorie'=>$cid])) ?>" <?= $categoryId===$cid?'aria-current="true"':'' ?>>
                            <strong><?= $h($row['name']) ?></strong>
                            <span><?= $row['parent_name']!==''?'Dans « '.$h($row['parent_name']).' »':'Catégorie principale' ?> · <?= $direct ?> emploi<?= $direct!==1?'s':'' ?><?php if($row['sub_category_count']>0): ?> · <?= (int)$row['sub_category_count'] ?> sous-catégorie<?= $row['sub_category_count']!==1?'s':'' ?><?php endif ?></span>
                        </a>
                        <?php if ($canManage): ?><a class="eff-category-add" data-jobs-nav href="<?= $h($categoryUrl(['nouvelle_categorie'=>1,'parent'=>$cid])) ?>" aria-label="<?= $h('Ajouter une sous-catégorie dans '.$row['name']) ?>">+ Sous-catégorie</a><?php endif ?>
                    </div>
                </li>
            <?php endforeach ?></ul><p class="eff-category-empty" data-category-empty hidden>Aucune catégorie ne correspond à votre recherche.</p><?php endif ?>
        </div>
        <div class="eff-category-editor">
            <?php if ($category || $creatingCategory): ?>
                <p class="eff-page-kicker"><?= $creatingCategory?($parentId>0?'Nouvelle sous-catégorie':'Nouvelle catégorie'):'Modifier la catégorie' ?></p>
                <h2><?= $creatingCategory?($parentId>0?'Créer une sous-catégorie':'Créer une catégorie principale'):$h($category['name']) ?></h2>
                <?php if ($creatingCategory && $parentId>0): ?><p class="eff-category-path">Dans « <?= $h($categoryLabel($categoryById[$parentId])) ?> »</p><?php endif ?>
                <?php if ($selectedTree): ?><p class="eff-category-path"><?= $h($selectedTree['path']) ?></p><?php endif ?>
                <?php if ($canManage): ?><form method="post" action="<?= $h($base.'/categorie') ?>" data-jobs-save class="eff-category-form">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="category_edit_id" value="<?= $categoryId ?>">
                    <label>Nom de la catégorie<input name="category_name" value="<?= $h($category['name']??'') ?>" maxlength="120" required placeholder="Ex. Transmissions" autocomplete="off"></label>
                    <label>Ranger dans<select name="parent_id" data-category-placement>
                        <option value="0">Catégorie principale — aucun dossier au-dessus</option>
                        <?php if($parentId>0 && !in_array($parentId,array_map('intval',array_column($parentChoices,'id')),true)): ?><option value="<?= $parentId ?>" selected>Rattachement à corriger explicitement</option><?php endif ?>
                        <?php foreach ($parentChoices as $parent): ?><option value="<?= (int)$parent['id'] ?>" <?= $parentId===(int)$parent['id']?'selected':'' ?>><?= $h($parent['path']) ?></option><?php endforeach ?>
                    </select></label>
                    <p class="eff-card__hint" data-category-placement-help><?= $parentId>0?'Elle sera une sous-catégorie du dossier choisi.':'Elle apparaîtra au premier niveau de votre organisation.' ?></p>
                    <div class="eff-category-form__actions"><button class="eff-btn eff-btn--primary" data-category-submit <?= $creatingCategory?'data-category-create':'' ?>><?= $creatingCategory?($parentId>0?'Créer la sous-catégorie':'Créer la catégorie'):'Enregistrer les modifications' ?></button><a class="eff-act" data-jobs-nav href="<?= $h($categoryUrl([])) ?>">Annuler</a></div>
                </form><?php endif ?>
                <?php if ($category): ?><div class="eff-category-contents"><h3>Dans cette catégorie</h3><p><?= (int)($selectedTree['branch_job_count']??0) ?> emploi(s), sous-catégories comprises.</p><a class="eff-act" data-jobs-nav href="<?= $h($base.'?categorie_emplois='.$categoryId) ?>">Voir les emplois →</a></div>
                <?php if($canManage): $used=(int)($selectedTree['branch_job_count']??0)>0 || (int)($selectedTree['sub_category_count']??0)>0; ?>
                <details class="eff-category-remove"><summary>Supprimer cette catégorie</summary><p><?= $used?'Déplacez d’abord ses emplois et ses sous-catégories. Une catégorie remplie ne peut pas être supprimée.':'Cette catégorie est vide. Sa suppression ne retire aucun emploi.' ?></p>
                    <form method="post" action="<?= $h($base.'/categorie') ?>" data-jobs-save><input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="category_edit_id" value="<?= $categoryId ?>"><button class="eff-btn eff-btn--danger" name="operation" value="delete" data-jobs-confirm="Supprimer cette catégorie vide ?" <?= $used?'disabled':'' ?>>Supprimer la catégorie vide</button></form>
                </details><?php endif; endif ?>
            <?php else: ?><div class="eff-category-empty"><h2><?= $canManage?'Choisissez une catégorie pour la modifier':'Choisissez une catégorie' ?></h2><p><?= $canManage?'Cliquez sur son nom pour la renommer ou la déplacer. Pour créer un dossier à l’intérieur, utilisez « + Sous-catégorie » sur la ligne correspondante.':'Cliquez sur son nom pour consulter ses emplois et son emplacement.' ?></p></div><?php endif ?>
        </div>
    </div>
</section>
