# Bug — relevé de carte : curseur Zeus bloqué

## Contexte

Fenêtre d’avancement « Relevé de la carte » ouverte depuis Zeus (COMSPEC Outils).

## Symptôme

Après ouverture de la fenêtre, le curseur Zeus ne plaçait plus d’unités. Parfois il ne revenait pas non plus après fermeture. Le parcours de la carte continuait, mais Zeus était inutilisable tant que la fenêtre restait ouverte.

## Cause

La fenêtre était accrochée à l’écran Zeus (affichage 312). Un enfant de cet écran prend la main sur le curseur et, à la fermeture, ne la rend pas toujours.

Les listes Eden « Au début de la mission » / « Afficher les IA ennemies » pouvaient aussi livrer un numéro au lieu du libellé : le module les traitait comme « non, laisser en manuel / masqué ».

## Correctif

- En mission, la fenêtre s’accroche à l’écran de jeu, pas à Zeus. L’éditeur (Eden) reste sur son propre écran.
- Simulation laissée active pendant l’affichage.
- Les listes Eden acceptent aussi un numéro (0 / 1) en plus du texte.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_theaterSurveyShow.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_theaterSurveyOnLoad.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_theater_survey.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_moduleTheaterSurvey.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_moduleAtakShowEnemyAi.sqf`

## Vérification

Tests `AtakZenEdenAssetTest`, `AtakSceneIngestAssetTest`. Recette : Zeus, COMSPEC Outils → Relever la carte ; fermer la fenêtre ; le curseur Zeus replace des unités.

## Statut

corrigé
