# Dossier public : ancienneté incohérente et historique d’affectations morcelé

## Contexte

Fiche publique ` /public/personnel/jake-gylenhall?view=public ` (2026-09-02). L’impression utilisateur : il manque des rubriques ; l’ancienneté et les mouvements ne correspondent pas aux dates du dossier.

## Symptôme

- Autres indicateurs : création de l’entité en tiret ; communauté 5 mois 7 jours ; service cumulé 4 jours.
- Dates : enrôlement 27/03/2026, membre depuis 29/08/2026, ancienneté globale 7 mois 12 jours (libellé « antérieure à la plateforme »).
- Historique : une dizaine de lignes pour la même unité, souvent le même jour, rôles qui basculent (Membre / En formation / Chef de groupe / TACP).
- Tableau administratif : blocs État civil, Affectation, Formation, etc. marqués vides alors que l’affectation et les dates sont déjà remplis plus haut.

## Cause

1. **Mouvements.** Chaque enregistrement du dossier fermait toutes les affectations actives et en recréait une à la date du jour. Les fragments d’un jour sont du bruit de sauvegarde, pas des mutations.
2. **Ancienneté de service.** L’indicateur partait de l’affectation active (donc du dernier fragment, fin août) au lieu de la date d’enrôlement déjà saisie. L’ancienneté globale prenait le plus long des deux (communauté ou avant-plateforme), ce qui affichait 7 mois alors que la communauté en affiche 5.
3. **Rubriques vides.** Les blocs administratifs personnalisés sans saisie étaient tout de même affichés (« aucune information »). Ce n’est pas le dossier RH vide après fusion : le chargeur public prend déjà la ligne la plus complète. Le tiret « création de l’entité » venait d’un indicateur jamais saisi, affiché quand même.

## Correctif

- Ne plus refermer une affectation si l’unité et la fonction n’ont pas changé.
- Regrouper à l’affichage (et par script optionnel) les tranches jointives de la même unité, surtout les allers-retours du même jour.
- Service cumulé : date d’enrôlement, sinon plus ancienne affectation. Ancienneté globale = communauté. Masquer les indicateurs sans période.
- « Membre depuis » : plus ancienne date déjà enregistrée (enrôlement ou création de compte).
- Ne plus afficher les blocs administratifs vides. Choisir le dossier le plus rempli, même si la communauté en cours n’a qu’une coquille.

## Fichiers touchés

- `app/Repositories/PersonnelAssignmentRepository.php`
- `app/Services/Personnel/PersonnelAssignmentHistoryCoalescer.php`
- `app/Services/Personnel/SeniorityDossierInferenceSyncService.php`
- `app/Services/Personnel/SenioritySummaryService.php`
- `app/Services/Identity/UserIdentityMergeRules.php`
- `app/Controllers/Web/PersonnelController.php`
- `views/personnel/file.php`
- `views/partials/personnel/file_tableau_admin_tab.php`
- `scripts/coalesce-personnel-assignment-history.php`
- `app/Support/DevDispatchCatalog.php`

## Vérification

- Tests unitaires : regroupement d’historique, choix du dossier le plus rempli, ancienneté globale = communauté.
- Navigation : ouvrir la fiche publique, onglets Ancienneté et Poste & affectations, puis le tableau administratif.
- Script : `php scripts/coalesce-personnel-assignment-history.php` (simulation), puis `--apply` si le regroupement convient.

## Statut

Corrigé (affichage et écritures). Les fragments déjà en base se relisent regroupés ; le script optionnel les fusionne définitivement.
