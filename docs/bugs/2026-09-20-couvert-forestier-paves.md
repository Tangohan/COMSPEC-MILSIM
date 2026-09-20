# Couvert végétal en pavés et chargement du relevé

## Contexte

Poste Overwatch Beta, vue 2D immersif ou Relief 3D, dézoom sur tout le théâtre. Relevé bâtiments / forêts déjà reçu.

## Symptôme

Toute l’île se tapisse de prismes ou de carrés verts. En zoomant, les arbres restent des pavés illisibles. Le relevé apparaît d’un coup, sans indication de chargement.

## Cause

Chaque objet forêt était dessiné comme un rectangle (emprise) ou regroupé dans une grille de cubes. Au large, des milliers de pavés recouvraient le fond. Aucun message n’attendait la fin du chargement.

## Correctif

Le couvert n’est plus dessiné à l’échelle du théâtre. En se rapprochant, il apparaît en taches de houppier (cercles), pas en carrés. Un bandeau « Chargement du relevé » s’affiche si l’attente dépasse un court délai.

## Fichiers touchés

- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/js/overwatch-gl/OverwatchGlLayers.js`
- `views/atak-overwatch-beta.php`
- `public/assets/css/atak-overwatch-beta.css`

## Vérification

Recharger Overwatch Beta (Ctrl+F5). Dézoomer : plus de tapis de pavés verts. Se rapprocher d’un bois : taches de houppier. Si le relevé met un moment, le message de chargement apparaît puis disparaît.

## Statut

Corrigé
