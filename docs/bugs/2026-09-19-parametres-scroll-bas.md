# Paramètres ATAK — page ouverte trop bas

## Contexte

Capture de la page Paramètres : Enregistrer / Actualiser, puis uniquement la carte « Liaison au poste » et les champs avancés. L’opérateur a l’impression qu’il manque l’indicatif, le rôle, la carte et l’équipe.

## Symptôme

À l’ouverture (ou après « Afficher les réglages avancés »), la liste saute en bas. Les réglages de fiche sont plus haut, hors écran.

## Cause

Le défilement automatique du groupe envoyait en bas dès que le contenu s’allongeait. Enregistrer / Actualiser étaient eux-mêmes dans la zone défilante, donc visibles seulement une fois arrivé sur la liaison.

## Correctif

- Défilement automatique coupé ; la page s’ouvre en haut sur « Votre fiche ».
- Enregistrer / Actualiser restent visibles sous le titre.
- Même carte visuelle pour la fiche et pour la liaison.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/settings_page.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_settingsOnOpened.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateSettings.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp` (Athena 1.0.150)

## Vérification

Quitter Arma. Recharger Athena 1.0.150. Ouvrir Paramètres : indicatif et rôle visibles sans faire défiler vers le haut. Faire défiler : liaison au poste inchangée visuellement.

## Statut

corrigé
