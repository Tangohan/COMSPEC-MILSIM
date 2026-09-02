# Dossier personnel — alertes au-dessus du bandeau

## Contexte

Page dossier personnel (écran de choix de vue, vue publique, vue RH). Bandeau sombre « Dossier personnel » sous le menu.

## Symptôme

L’absence en cours, les restrictions et les actions du dossier s’affichaient au-dessus du bandeau d’identité. Seule l’annonce d’ancienneté de la plateforme était à sa place, sous le menu.

## Cause

Les alertes propres à la page étaient rendues avant le bandeau (et avant l’écran de choix). L’ancienneté, elle, vient du bandeau général du site et doit rester en haut.

## Correctif

Les alertes et actions du dossier passent sous le bandeau d’identité. L’ancienneté de plateforme reste sous le menu.

## Fichiers touchés

- `views/personnel/file.php`
- `views/partials/personnel/file_page_notices.php`
- `views/partials/personnel/file_view_gate.php`
- `views/partials/personnel/file_rh_view.php`

## Vérification

Ouvrir un dossier avec une absence en cours et un accès RH : ancienneté sous le menu, puis bandeau Dossier personnel, puis l’absence, puis les cartes de vue.

## Statut

corrigé
