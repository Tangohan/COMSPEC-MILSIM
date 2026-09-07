# Fiche d’intégration — présentation illisible

## Contexte

La fiche d’un parcours d’arrivée (`Intégration` → un membre) doit se lire comme le reste du poste : titre de la coque, libellés métier, actions claires.

## Symptôme

La page empilait un second titre, des états techniques (`pending`, dates brutes), deux boutons de validation par étape même déjà faite, et des panneaux sombres ou étouffés.

## Cause

L’écran était un prototype : pas de charte Athena, pas de traduction des états d’étape, et le titre générique « Intégration des nouveaux membres » s’appliquait aussi à la fiche d’un membre.

## Correctif

- Titre au nom du membre, sous-titre métier, raccourcis Liste / Modèles / Fiche personnelle.
- Étapes en cartes, état en français, Valider seulement si l’étape n’est pas terminée, forcer dans un encadré.
- Journal, dossier, référents, groupes et rendez-vous en cartes du poste.

## Fichiers touchés

- `views/admin/member_integration/show.php`
- `public/assets/css/member-integration.css`
- `app/Controllers/Admin/MemberIntegrationAdminController.php`
- `app/Support/MemberIntegrationCatalog.php`
- `app/Support/BackOfficePageContext.php`
- `config/back_office_pages.php`

## Vérification

- Tests des écrans d’intégration : pas de second titre, pas d’état technique affiché, entrée « Parcours d’arrivée ».
- Contrôle visuel : ouvrir un parcours, lire le nom, l’état, la barre, valider une étape encore ouverte.

## Statut

corrigé
