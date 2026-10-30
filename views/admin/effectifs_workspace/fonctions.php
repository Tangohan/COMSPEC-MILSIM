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
$roleById=[]; foreach(($allJobRoles??$roles) as $role) $roleById[(int)$role['id']]=$role;
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
    <p class="eff-page-kicker">Organisation · Effectifs</p><h1 class="eff-page-title">Emplois &amp; fonctions</h1>
    <p class="eff-page-lead">Rangez vos emplois, précisez les responsabilités, puis choisissez leurs titulaires.</p>
    <p class="eff-jobs-definitions"><strong>Catégorie</strong> : un dossier de rangement. <strong>Emploi</strong> : une responsabilité. <strong>Affectation</strong> : une personne dans une unité.</p>
    <details class="eff-jobs-guide" data-jobs-details="guide"><summary>Comprendre le module : emploi, affectation, catégorie et accès</summary>
    <dl><div><dt>Emploi ou fonction</dt><dd>Ce que fait la personne : radio, médecin, CNOS, RFL… Une fonction peut dépendre d’un emploi parent, sans héritage de droits.</dd></div><div><dt>Affectation ORBAT</dt><dd>L’unité dans laquelle la personne sert et le poste qu’elle y occupe. Elle reste indépendante de ses emplois de dossier.</dd></div><div><dt>Catégorie</dt><dd>Un dossier de rangement que votre organisation nomme et organise librement, avec des sous-catégories. Ce n’est pas un niveau d’accès.</dd></div><div><dt>Niveau d’accès</dt><dd>Les permissions de l’application. Elles se gèrent dans les rôles et ne sont pas accordées en créant un emploi.</dd></div></dl>
    <p>Les trois familles « opérationnel, état-major / encadrement et administratif » du catalogue des positions sont des repères administratifs distincts. Elles ne limitent pas vos catégories d’emplois.</p></details>

</section>
<div class="eff-jobs-feedback" data-jobs-feedback role="status" aria-live="polite" hidden></div>
<nav class="eff-jobs-tabs" aria-label="Gestion des emplois">
<?php foreach(['catalogue'=>'Emplois et catégories','attributions'=>'Qui tient quel emploi','historique'=>'Historique et archives','recuperation'=>'Retrouver des emplois perdus','reglages'=>'Réglages'] as $tab=>$label): if(in_array($tab,['historique','recuperation','reglages'],true)&&!$canManage)continue; ?>
<a class="eff-jobs-tabs__item<?= $vue===$tab?' is-current':'' ?>" href="<?= $h($base.($tab==='catalogue'?'':'?vue='.$tab)) ?>" <?= $tab!=='attributions'?'data-jobs-nav':'' ?> <?= $vue===$tab?'aria-current="page"':'' ?>><?= $h($label) ?></a>
<?php endforeach ?></nav>
<?php if($vue==='attributions'): ?>
<?php require base_path('views/admin/organization/personnel_job_roles/assignments.php'); ?>
<?php elseif($vue==='historique'&&$canManage): ?>
<?php require __DIR__.'/job_history.php'; ?>
<?php elseif($vue==='reglages'&&$canManage): ?>
<details class="eff-jobs-guide" data-jobs-details="sync"><summary>Emplois et affectations : choisir la synchronisation</summary><p>Par défaut, chaque notion reste indépendante. Activer l’option ajoute les emplois correspondants lors des prochaines affectations ORBAT ; la désactiver ne retire aucun emploi déjà attribué.</p><form method="post" action="<?= $h($base.'/reglages') ?>" data-jobs-save><input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><label class="eff-jobs-checkbox"><input type="checkbox" name="sync_jobs_from_assignments" value="1" <?= !empty($jobSyncSettings['sync_jobs_from_assignments'])?'checked':'' ?>>Ajouter automatiquement les emplois depuis les affectations ORBAT</label><button class="eff-btn">Enregistrer le choix</button></form></details><?php elseif($vue==='recuperation'&&$canManage): $report=$jobRecovery??[]; ?>
<section class="eff-jobs-recovery"><h2>Rechercher les emplois perdus</h2><p>Recherche limitée à la communauté courante <strong>#<?= (int)($jobTenantId??0) ?></strong>. Les traces conservées sont proposées avant restauration. Les emplois et attributions actuels ne sont jamais remplacés, et aucun droit d’accès n’est réattribué ici.</p>
<form method="get" action="<?= $h($base) ?>" class="eff-jobs-recovery-search" data-jobs-query><input type="hidden" name="vue" value="recuperation"><label>Libellés à retrouver, séparés par des virgules<input name="labels" value="<?= $h(implode(', ',array_map('strval',$jobRecoveryLabels??['CNOS','RFL']))) ?>" maxlength="1000" required></label><button class="eff-btn">Rechercher les traces</button></form>
<?php if(!empty($report['candidates'])): ?><div class="eff-table-scroll"><table class="eff-jobs-evidence"><thead><tr><th>Emploi</th><th>Titulaire retrouvé</th><th>Preuve conservée</th><th>État</th></tr></thead><tbody><?php foreach($report['candidates'] as $candidate): ?><tr><td><?= $h($candidate['label']??'') ?></td><td><?= !empty($candidate['user_id'])?'Membre #'.(int)$candidate['user_id']:'Définition seule' ?></td><td><?php foreach($candidate['evidence']??[] as $evidence): ?><span><?= $h($evidenceLabel($evidence)) ?></span><?php endforeach ?></td><td><?= ($candidate['skip_reason']??'')==='user_not_in_current_tenant'?'Ancien titulaire hors de la communauté':(!empty($candidate['already_assigned'])?'Déjà présent':(!empty($candidate['assignment_conflict'])?'Attribution actuelle conservée':(!empty($candidate['can_assign'])?'Restaurable':'Définition récupérable'))) ?></td></tr><?php endforeach ?></tbody></table></div>
<form method="post" action="<?= $h($base.'/recuperer') ?>" data-jobs-save><input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="labels" value="<?= $h(implode(',',array_values(array_unique(array_column($report['candidates'],'label'))))) ?>"><button class="eff-btn eff-btn--primary" <?= empty($report['ready'])?'disabled':'' ?>>Restaurer les éléments retrouvés sans remplacer les attributions actuelles</button></form>
<?php else: ?><p class="eff-jobs-empty">Aucune trace exploitable dans les sources disponibles. Une sauvegarde antérieure à la perte sera nécessaire si les données ont été entièrement supprimées.</p><?php endif ?>
<?php if(!empty($report['missing_labels'])): ?><p class="eff-jobs-warning">Sans preuve retrouvée : <?= $h(implode(', ',$report['missing_labels'])) ?>. Aucun titulaire ne sera inventé.</p><?php endif ?></section>
<?php else: ?>
<?php require __DIR__.'/job_browser.php'; ?>
<?php endif ?>
</div>
<?php if(empty($jobsFragment)): ?><link rel="stylesheet" href="<?= $h(asset_url('assets/css/personnel-jobs-workspace.css')) ?>"><script src="<?= $h(asset_url('assets/js/personnel-jobs-workspace.js')) ?>" defer></script><?php endif ?>
