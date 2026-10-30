<?php
declare(strict_types=1);
$events=$jobHistory??[];
$more=count($events)>50;
$events=array_slice($events,0,50);
$archives=$jobArchives??[];
$moreArchives=count($archives)>50;
$archives=array_slice($archives,0,50);
$verbs=['created'=>'Création','updated'=>'Modification','deleted'=>'Suppression d’une catégorie vide','archived'=>'Archivage','restored'=>'Restauration'];
$fields=['name'=>'Nom','label_en'=>'Nom anglais','mos_code'=>'Code métier','mos_specialty_title'=>'Spécialité métier','source_unit_id'=>'Unité d’origine','description'=>'Description','category_id'=>'Catégorie','parent_id'=>'Catégorie parente','parent_job_role_id'=>'Emploi parent','linked_unit_id'=>'Unité de référence','grade_id'=>'Grade de référence','specialty_id'=>'Spécialité de référence','code'=>'Code','kind'=>'Nature'];
$flatten=static function(array $row):array{$meta=$row['structure']??[];unset($row['structure']);return $row+$meta;};
$value=static function(mixed $v,string $field):string{if($v===null||$v===''||$v===0)return 'Aucun';if($field==='kind')return $v==='function'?'Fonction':'Emploi';return (str_ends_with($field,'_id')?'#':'').(string)$v;};
?>
<section class="eff-jobs-history">
    <h2>Historique et archives</h2><p>Les changements effectués ici conservent leur date, leur auteur et les valeurs avant / après. Les emplois archivés peuvent être restaurés avec leurs rattachements encore disponibles. Aucun titulaire ni droit d’accès n’est réattribué.</p>
    <h3>Emplois archivés</h3>
    <?php if($archives===[]): ?><p class="eff-card__hint">Aucun emploi archivé à restaurer.</p><?php else: ?><ul class="eff-jobs-archives">
    <?php foreach($archives as $archive):$saved=json_decode((string)$archive['old_value'],true)??[]; ?><li><div><strong><?= $h($saved['name']??'Emploi') ?></strong><small>Archivé le <?= $h($archive['created_at']) ?></small></div><form method="post" action="<?= $h($base.'/restaurer') ?>" data-jobs-save><input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>"><input type="hidden" name="archive_id" value="<?= (int)$archive['id'] ?>"><button class="eff-btn">Restaurer cet emploi</button></form></li><?php endforeach ?></ul><?php endif ?>
    <?php if($moreArchives): ?><a class="eff-btn" data-jobs-nav href="<?= $h($base.'?'.http_build_query(['vue'=>'historique','avant_archive'=>(int)end($archives)['id'],'avant'=>(int)($jobHistoryCursor??0)])) ?>">Archives précédentes</a><?php endif ?>
    <?php if(!empty($jobArchiveCursor)||!empty($jobHistoryCursor)): ?><a class="eff-act" data-jobs-nav href="<?= $h($base.'?vue=historique') ?>">Revenir aux éléments récents</a><?php endif ?>
    <h3>Derniers changements</h3>
    <?php if($events===[]): ?><p class="eff-card__hint">Les prochaines modifications apparaîtront ici. Les pertes anciennes se recherchent dans « Retrouver des emplois perdus ».</p><?php endif ?>
    <ol class="eff-jobs-timeline">
    <?php foreach($events as $event):$before=$flatten(json_decode((string)$event['old_value'],true)??[]);$after=$flatten(json_decode((string)$event['new_value'],true)??[]);$op=substr((string)$event['action'],strrpos((string)$event['action'],'.')+1); ?>
        <li><div class="eff-jobs-event-title"><strong><?= $h($verbs[$op]??'Changement') ?> · <?= $h($after['name']??$before['name']??'Élément') ?></strong><time><?= $h($event['created_at']) ?></time></div><p class="eff-card__hint">Par <?= $h($event['actor_name']??('Membre #'.(int)$event['user_id'])) ?> · <?= $event['entity_type']==='personnel_job_category'?'Catégorie':'Emploi' ?></p>
            <details><summary>Voir les changements</summary><dl class="eff-jobs-diff"><?php foreach($fields as $field=>$label):if(($before[$field]??null)===($after[$field]??null))continue; ?><div><dt><?= $h($label) ?></dt><dd><span><?= $h($value($before[$field]??null,$field)) ?></span><span aria-hidden="true">→</span><span><?= $h($value($after[$field]??null,$field)) ?></span></dd></div><?php endforeach ?></dl></details>
        </li>
    <?php endforeach ?></ol>
    <?php if($more): ?><a class="eff-btn" data-jobs-nav href="<?= $h($base.'?'.http_build_query(['vue'=>'historique','avant'=>(int)end($events)['id'],'avant_archive'=>(int)($jobArchiveCursor??0)])) ?>">Changements précédents</a><?php endif ?>
</section>
