# Espace RH : page fourre-tout et libellés dédoublés

## Contexte

La page `/personnel/mon-espace-rh` s’appelait « Espace RH et formations ». Le même contenu était aussi nommé « Espace RH », « Mon dossier RH » ou « Espace RH complet » selon le menu, le hub, le tableau de bord ou la fiche.

## Symptôme

Un membre ne savait pas s’il devait ouvrir sa fiche, le catalogue de formations, le tableau de bord ou cette page. La page elle-même mélangeait absences, formations, charte, ancienneté, raccourcis du portail et programmes vides. L’élévation existait sur le tableau de bord, pas sur la page dite « complète ».

## Cause

Plusieurs surfaces membres pointaient vers la même URL avec des noms différents. La page avait été construite comme un hub personnel, pas comme un lieu de démarches.

## Correctif

Un seul nom : **Mes démarches**. La page sert les absences, l’élévation, le souhait d’évolution, les documents partagés et la charte si elle reste à confirmer. La fiche reste l’identité et le grade. Les formations restent dans le catalogue. Le tableau de bord propose une **démarche rapide**. Les raccourcis du portail et les programmes vides sont retirés. Le menu Formation ne liste plus cette page.

## Fichiers touchés

- `views/personnel/rh_workspace.php`
- `app/Controllers/Web/RhWorkspaceController.php`
- `config/navigation.php`
- `app/Controllers/Web/HubController.php`
- `app/Controllers/Web/HomeController.php`
- `views/partials/dashboard_aside.php`
- `views/partials/dashboard_rh_parcours.php`
- `views/partials/dashboard_member_rh.php`
- `views/personnel/file.php`
- `views/training/index.php`
- `views/training/my-training.php`

## Vérification

- Contrôle syntaxe PHP
- Test `RhWorkspaceClarityAssetTest`
- Test `DevDispatchCatalogTest` (UPDATE #466)

## Statut

Corrigé
