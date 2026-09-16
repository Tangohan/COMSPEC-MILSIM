# Téléphone ATAK — menu hors de l’écran et arrêt brutal

## Contexte

Athena 1.0.131. Téléphone ouvert, menu d’applications, accueil, carte.

## Symptôme

- Le menu d’applications flotte à droite du boîtier, hors de l’écran.
- Sur l’accueil, un encart d’identité recouvre le bureau bleu.
- Un panneau sombre recouvre la moitié droite de la carte.
- Le jeu se ferme (Arrêt anormal).

## Cause

Pour éviter une largeur nulle (planteur), le calage conservait la largeur du
menu et le poussait à droite de la carte. Une fois le menu « fermé », le
panneau restait visible **à côté du téléphone**. La hauteur / largeur mal
reprise du contrôle vivant recouvrait ensuite la carte. L’encart d’identité
n’était pas masqué sur l’accueil.

## Correctif

- Menu ouvert : 60 % carte / 40 % menu, **dans** l’écran, avec les icônes.
- Menu fermé : masqué, carte pleine largeur. Plus de panneau hors cadre.
- Accueil : encart d’identité et menu masqués.
- Pose directe, sans ressort, largeur et hauteur du menu calées sur l’écran.

Athena 1.0.132.

## Fichiers touchés

- `atak_athena/functions/fn_ATAK_Check_Layout.sqf`
- `atak_athena/functions/fn_athena_updateMapHud.sqf`
- `atak_athena/config.cpp`

## Vérification

1. Quitter Arma complètement, recharger le pack (Athena 1.0.132).
2. Carte : ouvrir le menu — il reste dans l’écran, à droite.
3. Refermer : la carte reprend toute la largeur, rien à côté du boîtier.
4. Accueil : bureau bleu, sans encart d’identité.
5. Ouvrir / fermer le menu plusieurs fois : le jeu reste ouvert.

## Statut

Corrigé côté sources (à valider in-game après relance Arma).
