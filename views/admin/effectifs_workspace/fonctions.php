<?php
declare(strict_types=1);
$h=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$roles=is_array($jobRoles??null)?$jobRoles:[];
$categories=is_array($jobCategories??null)?$jobCategories:[];
$options=$jobStructureOptions??['ready'=>false,'roles'=>[],'units'=>[],'grades'=>[],'specialties'=>[],'kinds'=>['employment'=>'Emploi','function'=>'Fonction']];
$vue=$jobsVue??'catalogue';
$canManage=!empty($canManageAssignments);
$creating=!empty($creatingJobRole);
$selected=$selectedJobRole??null;
$id=(int)($selected['id']??0);
$meta=$selected['structure']??[];
$unavailableReferences=[];
foreach(['parent_job_role_id'=>'roles','linked_unit_id'=>'units','grade_id'=>'grades','specialty_id'=>'specialties'] as $field=>$choices){
    $reference=(int)($meta[$field]??0);
    if(!$creating && $reference>0 && !in_array($reference,array_map('intval',array_column($options[$choices]??[],'id')),true)) $unavailableReferences[$field]=$reference;
}
$unavailableOption=static function(string $field)use($unavailableReferences):string{
    $reference=$unavailableReferences[$field]??0;
    return $reference>0?'<option value="'.$reference.'" selected>Référence indisponible (#'.$reference.')</option>':'';
};
$csrf=$csrfToken??\App\Core\Csrf::token();
$base=effectifs_workspace_url('fonctions');
$categoryById=[]; foreach($categories as $category) $categoryById[(int)$category['id']]=$category;
$categoryLabel=static function(array $category)use($categoryById):string{
    $parts=[(string)$category['name']]; $seen=[(int)$category['id']=>true]; $parent=(int)($category['parent_id']??0);
    while($parent>0 && isset($categoryById[$parent]) && !isset($seen[$parent])){
        $seen[$parent]=true; array_unshift($parts,(string)$categoryById[$parent]['name']); $parent=(int)($categoryById[$parent]['parent_id']??0);
    }
    return implode(' / ',$parts);
};
$evidenceLabel=static function(mixed $evidence):string{
    if(!is_array($evidence))return (string)$evidence;
    $sources=['access_control_v2_role_snapshot'=>'Archive de migration','personnel_assignments'=>'Affectation ORBAT conservée','personnel_correction_requests'=>'Demande de correction'];
    $label=$sources[$evidence['source']??'']??'Trace conservée';
    if(!empty($evidence['id']))$label.=' #'.(int)$evidence['id'];
    if(!empty($evidence['at']))$label.=' · '.(string)$evidence['at'];
    return $label;
};
$roleById=[]; foreach($roles as $role) $roleById[(int)$role['id']]=$role;
$roleTrail=static function(array $role)use($roleById):string{
    $parts=[]; $seen=[]; $parent=(int)($role['structure']['parent_job_role_id']??0);
    while($parent>0 && isset($roleById[$parent]) && !isset($seen[$parent])){
        $seen[$parent]=true; array_unshift($parts,(string)$roleById[$parent]['name']); $parent=(int)($roleById[$parent]['structure']['parent_job_role_id']??0);
    }
    return implode(' / ',$parts);
};
?>
<div class="eff-roles-page eff-jobs-workspace" data-jobs-workspace data-jobs-base="<?= $h($base) ?>">
<section class="eff-page-head">
    <p class="eff-page-kicker">Référentiel de votre communauté</p><h1 class="eff-page-title">Emplois &amp; fonctions</h1>
    <p class="eff-page-lead">Définissez vos métiers, responsabilités et sous-fonctions. Les ranger ou les relier à un grade, une spécialité ou une unité ne modifie ni les titulaires ni leurs droits.</p>
    <details class="eff-jobs-guide" data-jobs-details="guide"><summary>Comprendre le module : emploi, affectation, catégorie et accès</summary>
    <dl><div><dt>Emploi ou fonction</dt><dd>Ce que fait la personne : radio, médecin, CNOS, RFL… Une fonction peut dépendre d’un emploi parent, sans héritage de droits.</dd></div><div><dt>Affectation ORBAT</dt><dd>L’unité dans laquelle la personne sert et le poste qu’elle y occupe. Elle reste indépendante de ses emplois de dossier.</dd></div><div><dt>Catégorie</dt><dd>Un dossier de rangement que votre organisation nomme et organise librement, avec des sous-catégories. Ce n’est pas un niveau d’accès.</dd></div><div><dt>Niveau d’accès</dt><dd>Les permissions de l’application. Elles se gèrent dans les rôles et ne sont pas accordées en créant un emploi.</dd></div></dl>
    <p>Les trois familles « opérationnel, état-major / encadrement et administratif » du catalogue des positions sont des repères administratifs distincts. Elles ne limitent pas vos catégories d’emplois.</p></details>
</section>
<div class="eff-jobs-feedback" data-jobs-feedback role="status" aria-live="polite" hidden></div>
<nav class="eff-jobs-tabs" aria-label="Gestion des emplois">
<?php foreach(['catalogue'=>'Catalogue','categories'=>'Catégories','attributions'=>'Qui tient quel emploi','recuperation'=>'Restaurer des emplois'] as $tab=>$label): if($tab==='recuperation'&&!$canManage)continue; ?>
<a class="eff-jobs-tabs__item<?= $vue===$tab?' is-current':'' ?>" href="<?= $h($base.($tab==='catalogue'?'':'?vue='.$tab)) ?>" <?= $tab!=='attributions'?'data-jobs-nav':'' ?> <?= $vue===$tab?'aria-current="page"':'' ?>><?= $h($label) ?></a>
<?php endforeach ?></nav>
<?php if($vue==='attributions'): ?>
<?php require base_path('views/admin/organization/personnel_job_roles/assignments.php'); ?>
<?php elseif($vue==='categories'): ?>
<section class="eff-jobs-categories"><h2>Vos catégories et sous-catégories</h2><p class="eff-card__hint">Renommer ou déplacer une catégorie conserve les emplois qu’elle contient. Seules les catégories vides peuvent être supprimées.</p>
<?php foreach($categories as $category): $cid=(int)$category['id']; ?>
<form method="post" action="<?= $h($base.'/categorie') ?>" class="eff-jobs-category-row" data-jobs-save>
<input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="category_edit_id" value="<?= $cid ?>">
<label>Nom<input name="category_name" value="<?= $h($category['name']) ?>" maxlength="120" required <?= !$canManage?'disabled':'' ?>></label>
<label>Catégorie parente<select name="parent_id" <?= !$canManage?'disabled':'' ?>><option value="0">À la racine</option><?php foreach($categories as $parent): if((int)$parent['id']===$cid)continue; ?><option value="<?= (int)$parent['id'] ?>" <?= (int)($category['parent_id']??0)===(int)$parent['id']?'selected':'' ?>><?= $h($categoryLabel($parent)) ?></option><?php endforeach ?></select></label>
<?php if($canManage): ?><button class="eff-btn" type="submit">Enregistrer</button><button class="eff-btn eff-btn--danger" name="operation" value="delete" formnovalidate data-jobs-confirm="Supprimer cette catégorie vide ?">Supprimer</button><?php endif ?></form><?php endforeach ?>
<?php if($canManage): ?><form method="post" action="<?= $h($base.'/categorie') ?>" class="eff-jobs-category-row" data-jobs-save>
<input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><label>Nouvelle catégorie<input name="category_name" maxlength="120" required placeholder="Ex. Transmissions"></label><label>Catégorie parente<select name="parent_id"><option value="0">À la racine</option><?php foreach($categories as $parent): ?><option value="<?= (int)$parent['id'] ?>"><?= $h($categoryLabel($parent)) ?></option><?php endforeach ?></select></label><button class="eff-btn eff-btn--primary">Créer la catégorie</button></form><?php endif ?></section>
<?php elseif($vue==='recuperation'&&$canManage): $report=$jobRecovery??[]; ?>
<section class="eff-jobs-recovery"><h2>Rechercher les emplois perdus</h2><p>Recherche limitée à la communauté courante <strong>#<?= (int)($jobTenantId??0) ?></strong>. Les traces conservées sont proposées avant restauration. Les emplois et attributions actuels ne sont jamais remplacés, et aucun droit d’accès n’est réattribué ici.</p>
<form method="get" action="<?= $h($base) ?>" class="eff-jobs-recovery-search" data-jobs-query><input type="hidden" name="vue" value="recuperation"><label>Libellés à retrouver, séparés par des virgules<input name="labels" value="<?= $h(implode(', ',array_map('strval',$jobRecoveryLabels??['CNOS','RFL']))) ?>" maxlength="1000" required></label><button class="eff-btn">Rechercher les traces</button></form>
<?php if(!empty($report['candidates'])): ?><div class="eff-table-scroll"><table class="eff-jobs-evidence"><thead><tr><th>Emploi</th><th>Titulaire retrouvé</th><th>Preuve conservée</th><th>État</th></tr></thead><tbody><?php foreach($report['candidates'] as $candidate): ?><tr><td><?= $h($candidate['label']??'') ?></td><td><?= !empty($candidate['user_id'])?'Membre #'.(int)$candidate['user_id']:'Définition seule' ?></td><td><?php foreach($candidate['evidence']??[] as $evidence): ?><span><?= $h($evidenceLabel($evidence)) ?></span><?php endforeach ?></td><td><?= ($candidate['skip_reason']??'')==='user_not_in_current_tenant'?'Ancien titulaire hors de la communauté':(!empty($candidate['already_assigned'])?'Déjà présent':(!empty($candidate['assignment_conflict'])?'Attribution actuelle conservée':(!empty($candidate['can_assign'])?'Restaurable':'Définition récupérable'))) ?></td></tr><?php endforeach ?></tbody></table></div>
<form method="post" action="<?= $h($base.'/recuperer') ?>" data-jobs-save><input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="labels" value="<?= $h(implode(',',array_values(array_unique(array_column($report['candidates'],'label'))))) ?>"><button class="eff-btn eff-btn--primary" <?= empty($report['ready'])?'disabled':'' ?>>Restaurer les éléments retrouvés sans remplacer les attributions actuelles</button></form>
<?php else: ?><p class="eff-jobs-empty">Aucune trace exploitable dans les sources disponibles. Une sauvegarde antérieure à la perte sera nécessaire si les données ont été entièrement supprimées.</p><?php endif ?>
<?php if(!empty($report['missing_labels'])): ?><p class="eff-jobs-warning">Sans preuve retrouvée : <?= $h(implode(', ',$report['missing_labels'])) ?>. Aucun titulaire ne sera inventé.</p><?php endif ?></section>
<?php else: ?>
<div class="eff-jobs-actions"><span><?= count($roles) ?> emploi<?= count($roles)>1?'s':'' ?> / fonction<?= count($roles)>1?'s':'' ?></span><?php if($canManage): ?><a class="eff-btn eff-btn--primary" data-jobs-nav href="<?= $h($base.'?nouveau=1') ?>">Créer un emploi ou une fonction</a><a class="eff-btn" data-jobs-nav href="<?= $h($base.'?vue=categories') ?>">Organiser les catégories</a><?php endif ?></div>
<div class="eff-access-layout">
<aside class="eff-access-rail" aria-label="Catalogue des emplois"><input class="eff-jobs-search" type="search" data-jobs-search placeholder="Nom, code, catégorie…" aria-label="Rechercher un emploi"><ul class="eff-access-rail__list">
<?php foreach($roles as $role): $rid=(int)$role['id']; $trail=$roleTrail($role); $roleMeta=$role['structure']??[]; ?>
<li data-jobs-row data-search="<?= $h(mb_strtolower($role['name'].' '.($roleMeta['code']??'').' '.($role['category_name']??'').' '.$trail,'UTF-8')) ?>"><a class="eff-access-rail__item<?= !$creating&&$id===$rid?' is-current':'' ?>" href="<?= $h($base.'?emploi='.$rid) ?>" data-jobs-nav>
<?php if($trail!==''): ?><small class="eff-jobs-parent">↳ <?= $h($trail) ?></small><?php endif ?><strong><?= $h($role['name']) ?><?php if(!empty($roleMeta['code'])): ?><code><?= $h($roleMeta['code']) ?></code><?php endif ?></strong>
<span><?= $h($role['category_name']??'Sans catégorie') ?> · <?= count(($jobHoldersByRole??[])[$rid]??[]) ?> titulaire(s)</span></a></li><?php endforeach ?></ul><p class="eff-card__hint" data-jobs-empty <?= $roles!==[]?'hidden':'' ?>>Aucun emploi trouvé.</p></aside>
<div class="eff-access-main">
<?php if($canManage&&($creating||$selected)): ?>
<form method="post" action="<?= $h($creating?$base.'/nouveau':$base) ?>" class="eff-access-editor" data-jobs-save>
<input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><?php if(!$creating): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif ?>
<div class="eff-jobs-editor-title"><h2><?= $creating?'Créer un emploi ou une fonction':$h($selected['name']??'') ?></h2><?php if(!$creating): ?><span>#<?= $id ?></span><?php endif ?></div>
<div class="eff-jobs-fields"><label>Nom<input name="name" required maxlength="120" value="<?= $h($creating?'':($selected['name']??'')) ?>" placeholder="Ex. Responsable des transmissions"></label><label>Catégorie<select name="category_id"><option value="0">Organisation</option><?php foreach($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= !$creating&&(int)($selected['category_id']??0)===(int)$category['id']?'selected':'' ?>><?= $h($categoryLabel($category)) ?></option><?php endforeach ?></select></label><label class="eff-jobs-wide">Description<textarea name="description" rows="3" maxlength="500"><?= $h($creating?'':($selected['description']??'')) ?></textarea></label></div>
<?php if(!empty($options['ready'])): ?><input type="hidden" name="structure_enabled" value="1"><fieldset class="eff-jobs-links"><legend>Organisation et rattachements</legend><?php if($unavailableReferences!==[]): ?><p class="eff-jobs-warning">Un rattachement n’est plus disponible. Choisissez une référence valide ou « Aucun » pour le détacher explicitement avant d’enregistrer.</p><?php endif ?><p>Ces informations décrivent l’emploi. Elles ne déplacent personne dans l’ORBAT et ne donnent aucun droit.</p><div class="eff-jobs-fields">
<label>Nature<select name="kind"><?php foreach($options['kinds'] as $value=>$label): ?><option value="<?= $h($value) ?>" <?= ($creating?'employment':($meta['kind']??'employment'))===$value?'selected':'' ?>><?= $h($label) ?></option><?php endforeach ?></select></label><label>Code ou abréviation<input name="code" maxlength="40" value="<?= $h($creating?'':($meta['code']??'')) ?>" placeholder="Ex. CNOS"></label>
<label>Emploi / fonction parent<select name="parent_job_role_id"><option value="0">Aucun : à la racine</option><?= $unavailableOption('parent_job_role_id') ?><?php foreach($options['roles'] as $parent): if((int)$parent['id']===$id&&!$creating)continue; ?><option value="<?= (int)$parent['id'] ?>" <?= !$creating&&(int)($meta['parent_job_role_id']??0)===(int)$parent['id']?'selected':'' ?>><?= $h($parent['name']) ?></option><?php endforeach ?></select></label>
<label>Unité ORBAT de référence<select name="linked_unit_id"><option value="0">Aucune</option><?= $unavailableOption('linked_unit_id') ?><?php foreach($options['units'] as $unit): ?><option value="<?= (int)$unit['id'] ?>" <?= !$creating&&(int)($meta['linked_unit_id']??0)===(int)$unit['id']?'selected':'' ?>><?= $h($unit['name']) ?></option><?php endforeach ?></select></label>
<label>Grade de référence<select name="grade_id"><option value="0">Aucun</option><?= $unavailableOption('grade_id') ?><?php foreach($options['grades'] as $grade): ?><option value="<?= (int)$grade['id'] ?>" <?= !$creating&&(int)($meta['grade_id']??0)===(int)$grade['id']?'selected':'' ?>><?= $h(($grade['code']??'').' · '.($grade['label_long']??$grade['label_short']??$grade['label_fr']??$grade['name']??$grade['label']??'')) ?></option><?php endforeach ?></select></label>
<label>Spécialité de référence<select name="specialty_id"><option value="0">Aucune</option><?= $unavailableOption('specialty_id') ?><?php foreach($options['specialties'] as $specialty): ?><option value="<?= (int)$specialty['id'] ?>" <?= !$creating&&(int)($meta['specialty_id']??0)===(int)$specialty['id']?'selected':'' ?>><?= $h(($specialty['code']??'').' · '.($specialty['label_fr']??$specialty['name']??'')) ?></option><?php endforeach ?></select></label>
</div></fieldset><?php else: ?><p class="eff-jobs-warning">Les rattachements et sous-fonctions seront disponibles après la migration du module. Vos emplois existants sont conservés.</p><?php endif ?>
<div class="eff-access-editor__save"><button class="eff-btn eff-btn--primary"><?= $creating?'Créer l’emploi':'Enregistrer' ?></button><?php if(!$creating): ?><a class="eff-act" href="<?= $h($base.'?vue=attributions&job_role_id='.$id) ?>">Gérer les titulaires</a><?php endif ?></div></form>
<?php if(!$creating): $holders=($jobHoldersByRole??[])[$id]??[]; ?><section class="eff-jobs-holders"><h3>Titulaires</h3><?php if($holders===[]): ?><p>Aucun titulaire. L’emploi reste dans votre catalogue.</p><?php else: ?><ul><?php foreach($holders as $holder): ?><li><a href="<?= $h(effectifs_workspace_url('membres/'.(int)$holder['user_id'])) ?>"><?= $h($holder['display_name']??('Membre #'.(int)$holder['user_id'])) ?></a></li><?php endforeach ?></ul><?php endif ?></section>
<?php if(empty($selected['is_system'])): ?><form method="post" action="<?= $h($base.'/supprimer') ?>" data-jobs-save><input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="id" value="<?= $id ?>"><button class="eff-btn eff-btn--danger" data-jobs-confirm="Retirer cet emploi non attribué du catalogue ?">Retirer du catalogue</button></form><?php endif; endif ?>
<?php elseif($selected): ?><article class="eff-role-card"><h2><?= $h($selected['name']) ?></h2><p><?= $h($selected['description']??'') ?></p><p>Vous pouvez consulter ce référentiel. Sa modification est réservée aux responsables habilités.</p></article>
<?php else: ?><p class="eff-jobs-empty"><?= $canManage?'Créez votre premier emploi ou retrouvez les éléments supprimés dans « Restaurer des emplois ».':'Aucun emploi disponible dans ce référentiel.' ?></p><?php endif ?></div></div>
<?php if($canManage): ?><details class="eff-jobs-guide" data-jobs-details="sync"><summary>Emplois et affectations : choisir la synchronisation</summary><p>Par défaut, chaque notion reste indépendante. Activer l’option ajoute les emplois correspondants lors des prochaines affectations ORBAT ; la désactiver ne retire aucun emploi déjà attribué.</p><form method="post" action="<?= $h($base.'/reglages') ?>" data-jobs-save><input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><label class="eff-jobs-checkbox"><input type="checkbox" name="sync_jobs_from_assignments" value="1" <?= !empty($jobSyncSettings['sync_jobs_from_assignments'])?'checked':'' ?>>Ajouter automatiquement les emplois depuis les affectations ORBAT</label><button class="eff-btn">Enregistrer le choix</button></form></details><?php endif ?>
<?php endif ?>
</div>
<?php if(empty($jobsFragment)): ?><link rel="stylesheet" href="<?= $h(asset_url('assets/css/personnel-jobs-workspace.css')) ?>"><script src="<?= $h(asset_url('assets/js/personnel-jobs-workspace.js')) ?>" defer></script><?php endif ?>
