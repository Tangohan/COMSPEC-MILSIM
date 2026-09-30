# Bug — Villes du relevé non transmises pendant le scan des routes

## Contexte

30 septembre 2026. Relevé de la carte : 127 villes collectées, mais transmission au poste `0 / 127` tant que le scan « Routes — bande x / 60 » n’est pas fini.

## Symptôme

Sur Overwatch Beta, calques « Villes et localités » / « Réseau routier » : aucun relevé. Dans le panneau Relevé : villes présentes en local, zéro au poste.

## Cause

`fn_sampleGeoNetwork.sqf` collectait toutes les villes, puis toutes les routes, puis n’envoyait `Geo.Ingest` qu’à la fin. Le scan routes dure plusieurs minutes → les villes restent bloquées en file locale.

## Correctif

- Envoyer les villes par lots dès la fin de leur collecte.
- Envoyer les tronçons routiers au fil de l’eau (tous les 120 segments + à chaque bande).
- Côté poste : activer automatiquement les calques quand une couverture apparaît, avec rafraîchissement périodique.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sampleGeoNetwork.sqf`
- `public/assets/js/atak-overwatch-ops.js`

## Vérification

Relancer un relevé (ou « Renvoyer les données manquantes ») : compteur villes au poste monte avant la fin des routes. Sur Overwatch Beta, cocher Villes / Routes (ou laisser l’auto-activation) → noms et réseau visibles.

## Statut

corrigé (PBO connect + asset web à déployer)
