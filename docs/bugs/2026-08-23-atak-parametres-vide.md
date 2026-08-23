# Bug — tuile ATAK Paramètres parfois vide

## Contexte
App Paramètres du téléphone ATAK : page bleu clair sans champs.

## Symptôme
À l’ouverture, le panneau reste vide « par moment ». Un second essai ou Actualiser peut remplir.

## Cause
`settingsOnOpened` peignait une fois alors que le `controlsGroup` PAGE_CTRL n’était pas encore créé. Les listes (rôle, équipe, groupe) restaient vides. `getFireTeams` (HTTP) bloquait aussi le premier remplissage.

## Correctif
Peindre immédiatement avec les données locales, réessayer à 0,08 / 0,25 / 0,7 / 1,4 s, et retrouver le groupe via le contrôle résumé si la variable UI est encore nulle. Textes par défaut dans le HPP.

## Fichiers touchés
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_settingsOnOpened.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateSettings.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/settings_page.hpp`

## Vérification
Ouvrir Paramètres plusieurs fois de suite après rebuild atak_athena : indicatif, rôle et boutons visibles dès l’ouverture.

## Statut
corrigé
