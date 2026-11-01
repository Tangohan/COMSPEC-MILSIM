# Déploiement du personnel : membres en double ou en triple

## Contexte

1er novembre 2026. Page `/deploiement` (« Déploiement du personnel »).

## Symptôme

Certains membres apparaissaient plusieurs fois (ex. Jake Gylenhall TA1 ×3, Andrew Anderson YA4 ×2, James Alexander ZPU1 ×2, Liam Kaiser YA1 ×2). À chaque fois : une ou deux lignes « À corriger » sans unité, matricule ni groupe sanguin, et une ligne « Prêt » complète. Les compteurs « À corriger » / « Prêts » étaient faussés.

## Cause

La liste joignait `personnel_profiles` sur le seul `user_id`. Or un membre peut avoir plusieurs dossiers :

- des dossiers vides créés sans communauté (`tenant_id` NULL) par l’ancien `ensureRecord()` — la clé unique `(user_id, tenant_id)` ne bloque pas les NULL, chaque appel en ajoutait un ;
- un dossier par communauté pour un compte membre de plusieurs communautés.

Chaque dossier produisait une ligne. Le bouton « Déployer » relisait en plus le dossier sans préciser la communauté.

## Correctif

- La requête ne joint plus les dossiers : elle rend une ligne par membre, puis charge ses dossiers et n’en garde qu’un, le même que la fiche (`UserIdentityMergeRules::pickPreferredDossierRow` : le plus complet, puis celui de la communauté courante). Une ligne en double éventuelle est écartée.
- Le contrôle avant déploiement lit le dossier de la communauté courante (`getByUserId($id, $tenantId)`).
- Aucune donnée n’est supprimée.

## Nettoyage conseillé en base (non fait)

Repérer les dossiers fantômes : dossiers d’un membre qui en a un autre, sans communauté ou d’une autre communauté que celle du compte, et vides.

```sql
SELECT pp.id, pp.user_id, pp.tenant_id, u.tenant_id AS user_tenant,
       pp.matricule_internal, pp.blood_type, pp.primary_unit_id, pp.character_name
FROM personnel_profiles pp
JOIN users u ON u.id = pp.user_id
WHERE (SELECT COUNT(*) FROM personnel_profiles p2 WHERE p2.user_id = pp.user_id) > 1
ORDER BY pp.user_id, pp.id;
```

Après sauvegarde et contrôle ligne par ligne, supprimer seulement les lignes `tenant_id IS NULL` (ou d’une communauté dont le membre n’est plus membre) qui n’ont ni matricule, ni groupe sanguin, ni unité, ni nom de personnage, ni emploi (`personnel_profile_job_roles`). Garder toujours le dossier de la communauté du compte.

D’autres requêtes joignent encore `personnel_profiles` sur le seul `user_id` (ex. `EventRsvpNominativeRepository`, `PersonnelRoleplayTimelineRepository`, `AtakRoleService`, `MissingUserMediaScanner`) : elles peuvent montrer le même doublon tant que le nettoyage n’est pas fait.

## Fichiers touchés

- `app/Repositories/PersonnelDeploymentRepository.php`
- `app/Controllers/Web/PersonnelDeploymentController.php`
- `tests/Unit/PersonnelDeploymentDuplicateDossierTest.php`

## Vérification

- Test unitaire : trois dossiers pour un membre → une seule ligne, avec le dossier complet (matricule, groupe sanguin, unité).
- Sur le site : `/deploiement`, chercher Jake Gylenhall, Andrew Anderson, James Alexander, Liam Kaiser : une ligne chacun, statut « Prêt » ; compteurs cohérents ; déployer l’un d’eux fonctionne.

## Statut

Corrigé (affichage). Nettoyage des dossiers fantômes à faire en base.
