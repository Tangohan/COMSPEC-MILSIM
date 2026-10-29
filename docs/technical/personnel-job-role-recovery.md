# Préservation et récupération des emplois

Les anciennes migrations de purge ne suppriment plus les emplois, catégories, droits ou titulaires. Un emploi inutilisé, issu d’une unité ou portant un nom de catalogue reste une donnée de la communauté. La conversion des profils d’accès ajoute les modèles manquants sans supprimer les rôles personnalisés, leurs droits ni leurs attributions. Les autorisations réservées à la plateforme restent retirées par FBAC v2 ; la récupération des emplois ne restaure jamais un accès privilégié.

La consolidation des anciens champs `primary_role`, `secondary_role`, `personnel_job_role_id` et `role_sub_label` ajoute les attributions manquantes même si le membre possède déjà d’autres emplois. Elle conserve les champs sources et les détails des attributions présentes. Un reçu par contenu historique (`personnel_job_role_legacy_imports`) empêche un rejeu ultérieur de réattribuer un emploi que les RH ont volontairement retiré après l’import. Une erreur annule uniquement l’import du membre concerné et laisse les sources intactes. Les noms personnalisés des emplois issus d’une unité ne sont plus écrasés par la synchronisation ORBAT. Dès que la table pivot est disponible, elle fait autorité pour les notifications et l’historique, même lorsqu’elle est vide : retirer le dernier emploi ne fait pas réapparaître un libellé historique.

## S.O.A.R — communauté 14

Le correctif fournit un aperçu dans **Effectifs → Emplois et fonctions → Récupération**, limité à la communauté active et aux responsables habilités. La recherche initiale porte sur CNOS et RFL. L’aperçu ne crée aucune table et ne modifie aucune donnée. La commande équivalente est :

```sh
php scripts/recover-personnel-job-roles.php --tenant=14 --labels=CNOS,RFL
```

Sans filtre `--labels`, la commande examine tous les libellés prouvés. Les sources acceptées sont les instantanés de rôles et de titulaires `access_control_v2_*`, les libellés encore présents sur des affectations ORBAT actives de la communauté, et le dernier état connu de chaque demande de correction comportant des emplois identifiables. Un identifiant seul dont le libellé a disparu ne suffit pas. Les traces d’une autre communauté ne fournissent pas d’emploi ; un titulaire ayant quitté la communauté n’est jamais réaffecté.

Après vérification des preuves affichées, l’action de récupération ajoute les définitions manquantes dans « Emplois récupérés » et les titulaires prouvés dont le dossier est encore vide. Elle préserve les emplois déjà présents, ne touche pas à l’ORBAT et ne recrée aucun rôle d’accès ou permission. Les dossiers en conflit sont signalés ; leurs emplois actuels restent en place. Les références et attributions sont relues sous verrou au moment de l’application. Plusieurs emplois prouvés peuvent être rendus à un même membre initialement sans emploi.

L’application explicite en CLI se fait avec :

```sh
php scripts/recover-personnel-job-roles.php --tenant=14 --labels=CNOS,RFL --apply --actor=ID_OPERATEUR
```

Chaque preuve appliquée est inscrite dans `personnel_job_role_recovery_log` avec le lot, l’opérateur, le titulaire éventuel et le résultat. Un nouvel appel est idempotent. L’aperçu signale les noms sans preuve dans `missing_labels` ; il ne crée jamais une définition ni un titulaire déduit uniquement de la recherche.

Le développement et les tests ont été effectués sur des données jetables. Aucune récupération n’a été exécutée sur la base de production. Si les migrations antérieures ont supprimé CNOS/RFL avant la création des instantanés, ces instantanés ne permettront pas leur restitution : il faudra une sauvegarde antérieure ou une autre trace fiable pour retrouver leurs titulaires. Les sauvegardes de production ne sont pas accessibles dans cet environnement.

## Validation

`PersonnelJobRoleRecoveryServiceTest` et `PersonnelJobRolePreservationTest` utilisent SQLite pour vérifier la lecture seule, les limites de communauté, l’absence d’élévation de droits, les conflits, les récupérations multiples et l’idempotence. Les contrats `PersonnelJobRoleConsolidationTest` et `PersonnelAccessPreservationTest` rejouent les migrations sur MariaDB, y compris après un retrait volontaire d’attribution. Ils n’acceptent que la base jetable `rh_preservation_test`, définie via `RH_PRESERVATION_TEST_DSN`, et sont ignorés sans cette variable.
