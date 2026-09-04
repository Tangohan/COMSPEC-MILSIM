# Objet carte compact, pas transmis au poste

- Date : 2026-09-04
- Statut : corrigé (pack 1.8.19)

## Contexte

Le téléphone enregistrait déjà les dessins, mais n’envoyait au jeu qu’une ligne courte (`création | draw_12 | polygone`). Le poste ne recevait pas les points.

## Symptôme

Une zone posée hors liaison restait sur le téléphone. À la reconnexion, le poste n’avait pas la zone. Une position envoyée souvent n’aurait pas dû remplir l’historique.

## Cause

Le canal compact partait bien. L’objet complet n’était pas transmis à la file de liaison. Cette file vivait seulement en mémoire : fermer Arma la vidait. Le poste n’acceptait que les relevés de bâtiments / forêts, pas un dessin opérateur.

## Correctif

Le téléphone envoie l’objet complet (points, style, auteur). La position reste un état : seule la dernière compte. Les dessins vont dans la file de la machine, même hors liaison, puis au poste. Pack **1.8.19**.

## Fichiers touchés

- `web/map-bus.js`, `web/map-tiles.js`
- `functions/fn_webJSDialog.sqf`
- `app/Services/Tactical/AtakEventEnvelopeIngest.php`
- `app/Controllers/Api/AtakSceneApiController.php`
- `mod/UptoDate/COMSPECExtension/Extension.cs`

## Vérification

- Posez une zone, ouvrez Liaison : elle apparaît en attente.
- Relancez Arma, reconnectez : la zone part au poste.
- La position ne s’accumule pas dans l’historique.

## Statut

Corrigé dans les sources 1.8.19 — reconstruire le pack et relancer Arma complètement.
