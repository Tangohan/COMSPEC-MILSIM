# Carte hors écran, menu vide, même arrêt AutoArray

## Contexte

Athena 1.0.128 en jeu le 17/09 vers 22:26. Capture : la carte
déborde à droite du téléphone. Cinquième arrêt avec
`can't resize AutoArray to negative size!` puis ACCESS_VIOLATION
à `7C2D2B58`. Les correctifs journal 8 000 / frise / ACE étaient
déjà en place.

## Symptôme

La carte sort du cadre. Le menu d’applications n’est plus celui
du téléphone. Environ 27 s après la fermeture de la carte, le jeu
s’arrête, sans activité COMSPEC visible juste avant.

## Cause

Athena vidait le calage du menu : le chevron n’avait plus rien à
fermer. Avant cela, Athena élargissait la carte hors cadre.

Prendre le mini-écran 3D pour un téléphone ouvert relançait le
même calage hors cadre.

## Correctif

Athena 1.0.129 : plus aucun recale de la carte ni du tiroir.
Les cartouches restent ceux du téléphone. Un seul rythme pour
la carte ouverte. Le mini-écran 3D n’est plus traité comme ouvert.

## Fichiers touchés

- Calage carte (ne touche plus à la taille)
- Cartouches carte, menu, écran réellement ouvert
- Accroches carte regroupées
- Accueil Athena : plus de taille nulle pour masquer

## Vérification

Quitter Arma complètement, recharger Athena 1.0.130. Ouvrir le
téléphone : carte dans l’écran. Chevron : le menu s’ouvre avec les
applications. Chevron ou Retour : il se referme.

## Statut

Corrigé (Athena 1.0.130).
