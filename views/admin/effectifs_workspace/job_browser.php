<?php
declare(strict_types=1);
$tree=$categoryTreeRows??\App\Support\PersonnelJobCategoryTree::rows($categories);
$filterId=(int)($jobCatalogueCategory['id']??0);
$editingCategory=!empty($editingJobCategory)||!empty($creatingJobCategory)||!empty($selectedJobCategory);
$hasEditor=$editingCategory||$creating||$selected;
$jobUrl=static fn(array $query):string=>$base.($query!==[]?'?'.http_build_query($query):'');
$inCategory=$filterId>0?['categorie_emplois'=>$filterId]:[];
$jobTree=[];
foreach(($allJobRoles??$roles) as $r) $jobTree[]=['id'=>(int)$r['id'],'parent_id'=>$r['structure']['parent_job_role_id']??null];
$excludedJobParents=$id>0?array_fill_keys(\App\Support\PersonnelJobCategoryTree::descendantIds($jobTree,$id),true):[];
$newParent=(int)($newJobParent??0);
if(!isset($roleById[$newParent]))$newParent=0;
?>
<div class="eff-jobs-browser<?= $hasEditor?' has-editor':'' ?>">
    <aside class="eff-jobs-folders" aria-label="Catégories d’emplois">
        <div class="eff-jobs-pane-head"><h2>Catégories</h2><?php if($canManage): ?><a class="eff-jobs-text-action" data-jobs-nav href="<?= $h($jobUrl(['nouvelle_categorie'=>1]+$inCategory)) ?>">+ Créer</a><?php endif ?></div>
        <p class="eff-jobs-pane-hint">Des dossiers pour ranger vos emplois, avec autant de sous-catégories que nécessaire.</p>
        <?php if($tree!==[]): ?><input class="eff-jobs-search" type="search" data-category-search placeholder="Chercher une catégorie…" aria-label="Rechercher une catégorie"><?php endif ?>
        <a class="eff-jobs-all<?= $filterId===0?' is-current':'' ?>" href="<?= $h($base) ?>" data-jobs-nav <?= $filterId===0?'aria-current="true"':'' ?>>Tous les emplois <span><?= count($allJobRoles??$roles) ?></span></a>
        <ul class="eff-category-tree" data-jobs-scroll="categories">
        <?php foreach($tree as $row):$cid=(int)$row['id']; ?>
            <li class="eff-category-node<?= $filterId===$cid?' is-current':'' ?>" style="--category-depth:<?= min(6,(int)$row['depth']) ?>" data-category-row data-category-id="<?= $cid ?>" data-category-parent="<?= (int)$row['display_parent_id'] ?>" data-search="<?= $h($row['path']) ?>">
                <div class="eff-category-node__line">
                    <?php if((int)$row['sub_category_count']>0): ?><button type="button" class="eff-category-toggle" data-category-toggle="<?= $cid ?>" aria-expanded="true" aria-label="<?= $h('Replier '.$row['name']) ?>" data-category-name="<?= $h($row['name']) ?>">⌄</button><?php else: ?><span class="eff-category-leaf" aria-hidden="true">·</span><?php endif ?>
                    <a class="eff-category-node__name" data-jobs-nav href="<?= $h($jobUrl(['categorie_emplois'=>$cid])) ?>" <?= $filterId===$cid?'aria-current="true"':'' ?>><strong><?= $h($row['name']) ?></strong><span><?= (int)$row['branch_job_count'] ?> emploi(s)<?php if($row['sub_category_count']>0): ?> · <?= (int)$row['sub_category_count'] ?> sous-catégorie(s)<?php endif ?></span></a>
                    <?php if($canManage): ?><details class="eff-category-menu"><summary aria-label="<?= $h('Actions pour '.$row['name']) ?>">⋯</summary><div>
                        <a data-jobs-nav href="<?= $h($jobUrl(['nouvelle_categorie'=>1,'parent'=>$cid,'categorie_emplois'=>$cid])) ?>">Ajouter une sous-catégorie</a>
                        <a data-jobs-nav href="<?= $h($jobUrl(['nouveau'=>1,'categorie_emplois'=>$cid])) ?>">Créer un emploi ici</a>
                        <a data-jobs-nav href="<?= $h($jobUrl(['modifier_categorie'=>1,'categorie_emplois'=>$cid])) ?>">Renommer</a>
                        <a data-jobs-nav href="<?= $h($jobUrl(['modifier_categorie'=>1,'categorie_emplois'=>$cid]).'#category-placement') ?>">Déplacer</a>
                    </div></details><?php endif ?>
                </div>
            </li>
        <?php endforeach ?></ul>
        <?php if($tree===[]): ?><p class="eff-category-empty">Commencez par « Opérations », puis ajoutez « Transmissions » à l’intérieur.</p><?php endif ?>
        <p class="eff-category-empty" data-category-empty hidden>Aucune catégorie ne correspond.</p>
    </aside>
    <section class="eff-jobs-catalogue" aria-label="Emplois de la catégorie">
        <div class="eff-jobs-pane-head"><div><p class="eff-category-path"><?= $filterId>0?$h($categoryLabel($jobCatalogueCategory)):'Votre organisation' ?></p><h2><?= $filterId>0?$h($jobCatalogueCategory['name']):'Tous les emplois' ?> <span><?= count($roles) ?></span></h2></div>
        <?php if($canManage): ?><a class="eff-btn eff-btn--primary" data-jobs-nav href="<?= $h($jobUrl(['nouveau'=>1]+$inCategory)) ?>">+ Créer un emploi</a><?php endif ?></div>
        <?php if($filterId>0): ?><div class="eff-jobs-branch-actions"><span>Sous-catégories comprises</span><?php if($canManage): ?><a data-jobs-nav href="<?= $h($jobUrl(['nouvelle_categorie'=>1,'parent'=>$filterId]+$inCategory)) ?>">+ Sous-catégorie</a><a data-jobs-nav href="<?= $h($jobUrl(['modifier_categorie'=>1]+$inCategory)) ?>">Renommer / déplacer</a><?php endif ?></div><?php endif ?>
        <input class="eff-jobs-search" type="search" data-jobs-search placeholder="Nom, abrégé, étiquette, catégorie…" aria-label="Rechercher un emploi">
        <?php $tagFilterId=(int)($jobTagFilter??0); $listQuery=$inCategory+(!empty($jobShowHidden)?['masquees'=>1]:[]); ?>
        <div class="eff-jobs-filters">
            <?php if(($jobTags??[])!==[]): ?><form method="get" action="<?= $h($base) ?>" class="eff-jobs-filters__tag" data-jobs-query><?php foreach($inCategory+(!empty($jobShowHidden)?['masquees'=>1]:[]) as $k=>$v): ?><input type="hidden" name="<?= $h($k) ?>" value="<?= $h($v) ?>"><?php endforeach ?><label>Étiquette<select name="etiquette" data-jobs-autosubmit><option value="0">Toutes</option><?php foreach($jobTags as $tag): ?><option value="<?= (int)$tag['id'] ?>" translate="no" <?= $tagFilterId===(int)$tag['id']?'selected':'' ?>><?= $h($tag['name']) ?> (<?= (int)$tag['usage'] ?>)</option><?php endforeach ?></select></label><noscript><button class="eff-btn">Filtrer</button></noscript></form><?php endif ?>
            <?php if((int)($jobHiddenCount??0)>0): ?><a class="eff-jobs-text-action" data-jobs-nav href="<?= $h($jobUrl(empty($jobShowHidden)?$inCategory+['masquees'=>1]+($tagFilterId>0?['etiquette'=>$tagFilterId]:[]):$inCategory+($tagFilterId>0?['etiquette'=>$tagFilterId]:[]))) ?>"><?= empty($jobShowHidden)?'Afficher les masquées':'Cacher les masquées' ?> (<?= (int)$jobHiddenCount ?>)</a><?php endif ?>
        </div>
        <ul class="eff-jobs-list" data-jobs-scroll="jobs">
        <?php foreach($roles as $role):$rid=(int)$role['id'];$trail=$roleTrail($role);$roleMeta=$role['structure']??[];$decor=$role['decoration']??[];$roleOrigin=(string)($decor['origin']??''); ?>
            <li data-jobs-row data-search="<?= $h($role['name'].' '.($roleMeta['code']??'').' '.($decor['abbreviation']??'').' '.implode(' ',array_column($decor['tags']??[],'name')).' '.($role['category_name']??'').' '.$trail) ?>" class="<?= $roleOrigin==='community'?'is-community':'' ?><?= !empty($decor['hidden'])?' is-hidden-job':'' ?>">
                <a class="eff-jobs-list__item<?= !$creating&&$id===$rid?' is-current':'' ?>" data-jobs-nav href="<?= $h($jobUrl(['emploi'=>$rid]+$listQuery)) ?>" <?= !$creating&&$id===$rid?'aria-current="true"':'' ?> <?= ($decor['description']??'')!==''?'title="'.$h($decor['description']).'"':'' ?>>
                    <?php if($trail!==''): ?><small class="eff-jobs-parent">↳ <?= $h($trail) ?></small><?php endif ?><strong><?= \App\Support\JobRoleBadge::html($decor,false) ?><?= $h($role['name']) ?><?php if(!empty($roleMeta['code'])): ?><code><?= $h($roleMeta['code']) ?></code><?php endif ?> <?= \App\Support\JobRoleBadge::origin($roleOrigin) ?><?php if(!empty($decor['hidden'])): ?> <span class="job-hidden-flag">Masquée</span><?php endif ?></strong>
                    <span><?= $h($role['category_name']??'Organisation') ?> · <?= ($roleMeta['kind']??'employment')==='function'?'Fonction':'Emploi' ?></span>
                    <?= \App\Support\JobRoleBadge::tags($decor['tags']??[]) ?>
                </a>
            </li>
        <?php endforeach ?></ul>
        <p class="eff-category-empty" data-jobs-empty <?= $roles!==[]?'hidden':'' ?>>Aucun emploi<?= $filterId>0?' dans cette catégorie':'' ?>.<?php if($canManage): ?> Créez-en un avec le bouton ci-dessus.<?php endif ?></p>
    </section>
    <?php if($hasEditor): ?><section class="eff-jobs-detail<?= $editingCategory?' eff-category-editor':'' ?>" aria-label="<?= $editingCategory?'Édition de la catégorie':'Fiche de l’emploi' ?>" data-jobs-editor>
        <a class="eff-jobs-close" data-jobs-nav href="<?= $h($jobUrl($inCategory)) ?>">Fermer la fiche ×</a>
        <?php if($editingCategory): require __DIR__.'/job_categories.php'; else: ?>
            <?php if($selected&&$canManage&&!empty($options['ready'])): ?><div class="eff-jobs-branch-actions"><a data-jobs-nav href="<?= $h($jobUrl(['nouveau'=>1,'sous_emploi'=>$id,'categorie_emplois'=>(int)($selected['category_id']??0)])) ?>">+ Ajouter un sous-emploi</a></div><?php endif ?>
            <?php if($creating&&$newParent>0): ?><p class="eff-jobs-relation">Sous-emploi de <strong><?= $h($roleById[$newParent]['name']) ?></strong></p><?php endif ?>
            <?php require __DIR__.'/job_editor.php'; ?>
            <?php if($selected):$children=array_filter($allJobRoles??$roles,static fn(array $r):bool=>(int)($r['structure']['parent_job_role_id']??0)===$id);if($children!==[]): ?><div class="eff-jobs-children"><h3>Sous-emplois et sous-fonctions</h3><?php foreach($children as $child): ?><a data-jobs-nav href="<?= $h($jobUrl(['emploi'=>(int)$child['id']])) ?>"><?= $h($child['name']) ?> →</a><?php endforeach ?></div><?php endif;endif ?>
        <?php endif ?>
    </section><?php else: ?><p class="eff-jobs-selection-hint">Sélectionnez un emploi pour ouvrir sa fiche. Pour organiser les catégories, utilisez leur menu « ⋯ ».</p><?php endif ?>
</div>
