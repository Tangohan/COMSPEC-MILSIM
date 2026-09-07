# Migrations — les emplois vidés revenaient tout seuls

## Contexte

Le référentiel d’emplois avait été vidé volontairement. Lancer la mise à jour (`run-migrations.php`) a recréé une centaine d’emplois. Après l’arrêt de cette recréation, les emplois déjà revenus étaient encore en base.

## Symptôme

Après une mise à jour, le bureau effectifs réaffiche les emplois qu’on venait de retirer. Même une fois la recréation stoppée, la liste restait remplie.

## Cause

À chaque passage, la migration des emplois recréait un emploi pour chaque unité de chaque communauté, même si le référentiel venait d’être vidé. Le nettoyage existant ne retirait que les copies du catalogue militaire, pas les emplois d’unité (`unit-*` / liés à une unité).

## Correctif

1. La mise à jour ne touche plus qu’au schéma et retire les copies catalogue inutilisées. Elle ne recrée plus d’emploi. Un emploi naît seulement à la création d’une unité, à une affectation, ou quand un responsable copie un modèle.
2. Une passe supplémentaire retire aussi les emplois d’unité revenus tout seuls, s’ils ne sont posés sur aucun dossier. Un emploi déjà attribué à un membre est conservé. Les emplois créés à la main par la communauté restent.

## Fichiers touchés

- `bootstrap/unit_derived_job_roles_migration.php`
- `bootstrap/unused_recreated_job_roles_purge_v1_migration.php`
- `run-migrations.php`
- `app/Services/Personnel/UnitJobRoleSyncService.php`

## Vérification

- La migration des emplois ne contient plus d’appel qui recrée les emplois de toutes les communautés.
- La passe de nettoyage est appelée et ne recrée rien.
- Après un nouveau passage, un référentiel vidé reste vide ; les attributions déjà posées restent.

## Suite — la liste du dossier restait pleine

Le premier nettoyage ne reconnaissait que les emplois dont le code interne était encore celui du catalogue. Les copies rangées par catégorie (Forces spéciales, Artillerie, Cyber…) restaient donc visibles dans Emploi. Le dossier affiche désormais uniquement les emplois d’unité, ceux déjà posés sur la fiche, et ceux créés pour la communauté. Une seconde passe retire les copies catalogue inutilisées, y compris par catégorie.

## Statut

corrigé
