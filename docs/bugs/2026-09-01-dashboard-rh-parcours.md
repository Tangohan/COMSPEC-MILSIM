# Tableau de bord — dossier RH en parcours

## Contexte

Sur le tableau de bord communautaire, le bloc Personnel montrait en même temps le formulaire d’élévation et celui d’avancement, l’absence n’étant qu’un lien en bas de carte.

## Symptôme

Les deux formulaires occupaient la page côte à côte. Il n’y avait pas de choix préalable, et le bloc n’était pas clairement un parcours en bas de page.

## Cause

Le dossier RH du tableau de bord n’était pas un enchaînement : les formulaires étaient affichés ensemble, sans étape de sélection Absence / Élévation / Avancement.

## Correctif

En bas du tableau de bord, le membre choisit d’abord une démarche (Absence, Élévation ou Avancement), puis voit uniquement le formulaire correspondant. Les envois reviennent sur le tableau de bord.

## Fichiers touchés

- `views/partials/dashboard_rh_parcours.php`
- `views/partials/dashboard_command_center.php`
- `public/assets/css/dashboard-impact.css`
- `app/Support/DashboardRhParcours.php`
- `app/Controllers/Web/HomeController.php`
- `app/Controllers/Web/RhWorkspaceController.php`
- `routes/web.php`

## Vérification

Parcours lu dans les vues : trois cartes de choix, un formulaire à la fois, inclusion après les salons et épingles. Tests d’assets `DashboardRhParcoursAssetTest`.

## Statut

Corrigé
