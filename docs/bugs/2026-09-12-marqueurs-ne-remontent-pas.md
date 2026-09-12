# Marqueurs INF / ATAK ne remontent pas au poste

## Contexte

12 septembre 2026. Canal Athena « OK sync » (CIV 1.5.46 / liaison 2.0.28), photos TA1 reçues au poste, Effectifs vides (« Pas encore vu »). Sur le téléphone, un losange rouge INF est visible ; il n’apparaît pas clairement sur la carte du poste.

## Symptôme

- Repères posés ou visibles sur la carte du téléphone (INF Marker Widget / cTab) absents de la carte Athena.
- La liaison et les photos fonctionnent : le problème n’est pas « aucun canal ».
- Distinct des contacts Effectifs (position BFT), mais souvent constaté en même temps.

## Cause

1. **File jetée sous liaison dégradée** : `syncMapMarker` abandonnait l’envoi dès que `canTransmit` échouait (perte simulée / fiab. &lt; 100 %), sans mettre le marqueur en file. Un INF posé pendant un micro-creux ne partait jamais.
2. **JSON vidé côté liaison** : `SanitizeLooseJsonObject` ne corrigeait pas les guillemets doublés SQF ; en échec de parse il renvoyait `{}`. Le poste acceptait un marqueur sans position, invisible sur la carte.
3. **Resync périodique trop strict** : le passage de rattrapage rappelait la sync sans forçage, donc retombait dans le même frein.

Les contacts Effectifs restent un autre flux (position). Les marqueurs n’en dépendent pas côté serveur.

## Correctif

- Soft-block liaison → file d’attente marqueurs, flush dès que le canal est ouvert.
- DLL : normalisation JSON avant sanitize ; refus d’envoyer un upsert vide.
- API : refus d’un repère sans coordonnées valides.
- Resync périodique en forcé pour rattraper les INF / Widget.
- Pont cTab utilisateur inchangé (déjà indépendant de la position).

Pack : Overwatch **1.5.54** · Athena **1.0.99** · liaison **2.0.32**. Relancer Arma complètement.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncMapMarker.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_resyncAllMapMarkers.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeCtabMarkers.sqf`
- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `app/Controllers/Api/AtakApiController.php`
- `docs/bugs/2026-09-12-marqueurs-ne-remontent-pas.md`
- `tests/Unit/AtakMarkerUplinkAssetTest.php`

## Vérification

1. En mission, canal poste OK : poser un INF / Marker Widget sur le téléphone.
2. Sous ~5–10 s (au plus ~45 s au resync), le repère apparaît sur la carte du poste à la bonne grille — même si Effectifs est encore vide.
3. Hub → Renvoyer les marqueurs carte : les repères déjà posés repartent.

## Statut

corrigé (Overwatch 1.5.54 · Athena 1.0.99 · liaison 2.0.32)
