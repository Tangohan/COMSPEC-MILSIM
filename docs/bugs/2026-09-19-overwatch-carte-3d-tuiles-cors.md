# Carte 3D Overwatch sans fond (tuiles Atlas)

## Contexte

Poste Overwatch Beta, vue **Tactique 3D** / **Relief 3D** sur Altis. Le 2D à plat montrait encore le théâtre ; le relief restait sombre.

## Symptôme

Console : les extraits de carte Atlas (`atlas.plan-ops.fr/...webp`) répondent 200 mais le navigateur les refuse. Le relief s’ouvre sans le plan ni la photo aérienne. Des cases hors carte (indice négatif) répondaient 404.

## Cause

La vue relief peint le fond sur un canevas. Le navigateur exige alors que le serveur distant autorise Athena. Atlas ne le fait pas. Les extraits arrivent, mais ne peuvent pas être dessinés. Les cases hors théâtre étaient demandées inutilement.

## Correctif

Les extraits passent par Athena (même site que le poste), avec un cache. Les cases hors carte ne sont plus demandées.

## Fichiers touchés

- `app/Support/AtakRemoteTileGuard.php`
- `app/Controllers/Api/AtakMapDataController.php`
- `routes/web.php`
- `public/assets/js/overwatch-gl/TheaterProjection.js`
- `public/assets/js/overwatch-gl/OverwatchGlMap.js`
- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/js/atak-aerial.js`

## Vérification

Syntaxe PHP des fichiers ajoutés. Garde-fou : URL Atlas Altis acceptée, URL étrangère refusée. Rechargement Overwatch Beta (Ctrl+F5) puis Tactique 3D : le fond doit coller au 2D.

## Statut

corrigé
