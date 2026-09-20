# Couvert végétal en pavés et chargement du relevé

## Contexte

Poste Overwatch Beta, vue 2D immersif sur photo aérienne. Relevé bâtiments / forêts déjà reçu. Bosquets isolés dans les champs.

## Symptôme

Chaque arbre d’un bosquet apparaît comme un carré vert, axe aligné, avec un contour. Un bosquet dans un champ forme une grille de pavés, collée sur la photo. Les constructions, elles, se lisent correctement en emprise claire.

## Cause

Le relevé envoie un volume par arbre (emprise). Le rendu 2D dessinait cette emprise (rectangle ou cercle avec contour) telle quelle. Un bosquet de dix arbres devenait dix pavés.

## Correctif

Les objets de couvert sont regroupés par voisinage. Chaque groupe est dessiné en tache de houppier (dégradé radial, sans contour), assez large pour fondre les arbres d’un même bosquet. Les constructions restent des polygones d’emprise. En Relief 3D, le couvert est aussi regroupé plus largement.

## Fichiers touchés

- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/js/overwatch-gl/OverwatchGlLayers.js`

## Vérification

Recharger Overwatch Beta (Ctrl+F5). Passez en 2D immersif sur la photo aérienne, au-dessus d’un bosquet dans un champ : une ou deux taches, pas une grille de carrés. Un bâtiment isolé reste un toit clair.

## Statut

Corrigé
