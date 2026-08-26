# Bug — menus Zeus COMSPEC absents si Zeus Enhanced arrive tard

## Contexte

Outils Zeus Enhanced (ZEN) et modules ACE Zeus du pack Overwatch : relevé de carte, zones roleplay, ATAK joueur, balise GPS, téléphone, IA alliée, contacts ennemis, SSE.

## Symptôme

Les catégories **COMSPEC Roleplay**, **COMSPEC Outils** et **COMSPEC SSE** pouvaient rester vides dans Zeus Enhanced, alors que les modules Eden étaient bien présents. Un second essai à 8 secondes ne changeait rien.

## Cause

L’enregistrement ZEN des actions ATAK / suivi (GPS, téléphone, IA, contacts ennemis) posait un drapeau « déjà fait » même si Zeus Enhanced n’était pas encore chargé. Le nouvel essai sortait tout de suite. Les modules roleplay / SSE / relevé, eux, attendaient ZEN — mais seulement 2 s puis 8 s, pas jusqu’à la présence réelle de l’outil.

## Correctif

- Drapeaux séparés : ACE Zeus / clic droit / double-clic d’un côté, ZEN de l’autre.
- Seconde passe : on attend que l’outil Zeus Enhanced soit vraiment là (délai 45 s).
- Relevé de carte aussi dans ACE Zeus et au clic droit ZEN.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZenTrackActions.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZenAtakPlayerActions.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZenTheaterSurvey.sqf`

## Vérification

Tests unitaires `AtakZenEdenAssetTest`, `AtakSceneIngestAssetTest`. Recette : Zeus Enhanced chargé, catégories COMSPEC visibles après entrée Zeus.

## Statut

corrigé
