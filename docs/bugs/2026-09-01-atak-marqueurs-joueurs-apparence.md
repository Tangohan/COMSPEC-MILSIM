# Marqueurs joueurs — apparence et clignotement

## Contexte

Sur la carte du poste (`/public/atak`), les réglages **Apparence de la carte** (taille des icônes, libellés, cadre d’équipe, flèche) ne changeaient pas les symboles des opérateurs. Ceux-ci clignotaient, et les trois lignes sous le symbole (groupe, indicatif, état de liaison) étaient illisibles.

## Symptôme

- Déplacer Icônes ou Libellés ne changeait pas les opérateurs.
- Les symboles apparaissaient et disparaissaient au rythme des positions.
- Quand ils restaient visibles, le texte sous le symbole était minuscule, sans fond, sur la carte en clair.

## Cause

La carte live passe par la couche C2. Les curseurs d’apparence n’alimentaient que l’ancien rendu. Le nouveau gestionnaire ignorait la taille choisie, recréait le symbole à chaque déplacement de carte et à chaque degré de cap, regroupait les opérateurs proches (d’où le clignotement), et collait le rôle plus l’état de liaison en tout petit sous le symbole.

## Correctif

- La taille des icônes et des libellés des réglages s’applique aux opérateurs.
- L’indicatif seul reste sous le symbole, lisible (fond sombre). Rôle, liaison et grille au survol.
- Le symbole n’est plus recréé si rien n’a changé visuellement. Pas de regroupement en vue tactique.
- Cap arrondi, plus de double envoi à chaque position.

## Fichiers touchés

- `public/assets/js/map/MarkerManager.js`
- `public/assets/js/map/MarkerLOD.js`
- `public/assets/js/map/TacticalSymbol.js`
- `public/assets/js/map/MarkerClusterManager.js`
- `public/assets/js/map/atak-c2-bridge.js`
- `public/assets/js/atak-map.js`
- `public/assets/js/atak-marker-sizes.js`
- `public/assets/css/atak-map-c2-v2.css`
- `public/assets/css/atak-map-popups.css`
- `public/assets/css/atak.css`
- `views/atak.php`
- `tests/Unit/AtakC2PlayerMarkerAppearanceAssetTest.php`

## Vérification

- Contrôle des fichiers d’assets (chaînes attendues dans le test unitaire). PHPUnit n’est pas installé dans ce dépôt local.
- Contrôle visuel sur une mission réelle : non exécuté ici. Après déploiement : ouvrir Réglages → Apparence de la carte, bouger Icônes et Libellés des unités, confirmer que les opérateurs changent sans clignoter.

## Statut

Corrigé (à valider en mission réelle sur la carte du poste).
