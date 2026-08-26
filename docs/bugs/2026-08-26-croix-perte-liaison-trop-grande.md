# Croix de perte de liaison trop grande

## Contexte

Carte ATAK web, traces d’unités. Un contact passe en silence (liaison perdue ou position figée). Une croix rouge signale le dernier point connu.

## Symptôme

Un grand X orange recouvrait tout le symbole OTAN (ou la photo). L’unité devenait illisible. En vue inclinée, un double fantôme violacé apparaissait sous le symbole (marque 3D + marque à plat).

## Cause

La croix était dessinée comme un marqueur de la taille d’une pastille (18 px, glyphe 15 px), puis enveloppée dans le même habillage 3D que les unités. Elle se superposait au symbole, au même endroit, et était agrandie avec la vue.

## Correctif

La croix devient une petite marque d’angle (8 px, glyphe 7 px), décalée en coin, sans habillage 3D. Le symbole de l’unité reste lisible ; le silence reste indiqué.

## Fichiers touchés

- `public/assets/js/atak-sse-layers.js`
- `public/assets/css/atak-motion.css`
- `public/assets/css/atak.css`
- `tests/Unit/AtakTrailLossBadgeAssetTest.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE 241)

## Vérification

Tests unitaires sur la taille du badge et l’absence d’habillage 3D. Recette : un contact silencieux montre une petite croix en coin, le symbole OTAN reste lisible, une seule croix.

## Statut

corrigé
