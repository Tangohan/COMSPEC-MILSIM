# Flèche d’orientation, prédiction et superposition en haut à gauche

## Contexte

Overwatch Beta, carte du poste. Contact suivi (ex. TA1), flèche d’orientation et anticipation cochées, météo affichée.

## Symptôme

- La flèche d’orientation n’était qu’un trait blanc trop court, sans pointe, posé sur tous les contacts. L’anticipation doublait ce trait et changeait de taille selon le zoom.
- Aucune option claire pour voir le chemin déjà parcouru par la personne ouverte.
- En haut à gauche, le bandeau « Suivi » recouvrait la pastille météo (vent, km/h) au même emplacement.

## Cause

- La flèche était un segment de 28 m en coordonnées théâtre, redessiné pour chaque contact.
- Le tracé n’était enregistré que si la couche Trajectoires était cochée.
- Les pastilles suivi et météo étaient toutes les deux en `left: 52px; top: 10px`.

## Correctif

- Flèche et anticipation limitées au contact ouvert, avec pointe et longueur en pixels d’écran.
- Option « Tracé de progression » : le chemin du contact ouvert est mémorisé dès que les positions arrivent.
- Suivi et météo empilés dans un même coin, l’un sous l’autre.

## Fichiers touchés

- `public/assets/js/atak-overwatch-ops.js`
- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/css/atak-overwatch-beta.css`
- `views/atak-overwatch-beta.php`

## Vérification

Ouvrir un contact, cocher flèche, anticipation et tracé : une pointe lisible, des pointillés devant s’il avance, un trait derrière. Activer le suivi avec la météo : deux pastilles distinctes, sans texte qui se croise.

## Statut

corrigé
