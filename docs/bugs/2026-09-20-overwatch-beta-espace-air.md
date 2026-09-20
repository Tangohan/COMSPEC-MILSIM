# Overwatch Beta : pas d’espace aéronefs / JTAC

## Contexte

Poste Overwatch Beta. Le poste classique a un panneau Appui aérien (manifestes) et un onglet JTAC. Un manifeste de vol envoyé depuis le jeu (indicatif, emport, arrivée estimée) doit arriver au commandement.

## Symptôme

Sur Overwatch Beta, la barre du haut n’avait pas d’espace Air. Les aéronefs, les manifestes, les demandes JTAC et les arrivées estimées n’étaient pas consultables. Seuls un résumé 9-line et un formulaire étaient noyés dans Mission.

## Cause

Overwatch Beta n’ouvrait pas de page dédiée. La fiche aéronef du poste omettait aussi l’emport, l’arrivée estimée et l’autonomie, même quand le manifeste les avait bien enregistrés.

## Correctif

1. Espace Air dans la barre du haut : fiches aéronefs (manifeste, ETA, emport) et demandes JTAC, avec formulaire de nouvelle demande.
2. La fiche aéronef du poste inclut désormais l’emport, l’arrivée estimée et l’autonomie.

## Fichiers touchés

- `views/atak-overwatch-beta.php`
- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/css/atak-overwatch-beta.css`
- `app/Controllers/Api/AtakApiController.php`
- `app/Services/Tactical/AtakAirAssetMergeService.php`
- `public/assets/js/atak-air-assets.js`

## Vérification

Recharger Overwatch Beta (Ctrl+F5). Ouvrir Air. Un manifeste envoyé depuis le jeu doit afficher l’emport et l’arrivée estimée. Une demande d’appui se prépare dans le même tiroir ; clic droit sur la carte reprend la grille.

## Statut

corrigé
