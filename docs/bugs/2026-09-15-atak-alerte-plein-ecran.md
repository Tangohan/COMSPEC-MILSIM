# Alerte plein écran — voile trop petit, puis arrêt brutal

## Contexte

Alerte envoyée depuis Overwatch Beta vers le téléphone ATAK ouvert, menu
d’applications déjà déployé. Pack Athena 1.0.127 / Overwatch 1.5.78.
Journal session 21:29, arrêt anormal vers 21:32.

## Symptôme

Le voile d’alerte n’assombrissait que la carte. Le menu d’applications restait
visible à droite. Le titre « ALERTE POSTE » n’apparaissait pas, seulement la
barre Fermer en bas de la carte. Quelques secondes plus tard : Arrêt anormal
`0xC0000005` / ACCESS_VIOLATION.

Dans le journal moteur, juste avant : `can't resize AutoArray to negative size!`

## Cause

Le voile prenait le rectangle de la carte visible, pas le cadre complet du
téléphone. Avec le menu ouvert, la carte est déjà réduite : le voile laissait
le tiroir à découvert, et le titre n’avait plus assez de hauteur.

Pendant l’alerte, le calage carte / tiroir continuait d’animer. Une largeur
cible nulle ou négative fermait le jeu (même famille que le plantage du
15 septembre).

## Correctif

- Le voile utilise le cadre téléphone figé, élargi au menu s’il est ouvert.
- Le calage n’anime plus tant que l’alerte est affichée.
- Les cartouches carte sont masqués pendant l’alerte.
- Athena 1.0.129.

## Fichiers touchés

- `atak_athena/functions/fn_athena_paintFullscreenAlert.sqf`
- `atak_athena/functions/fn_ATAK_Check_Layout.sqf`
- `atak_athena/functions/fn_athena_updateMapHud.sqf`
- `atak_athena/config.cpp`

## Vérification

1. Quitter Arma complètement, recharger le pack (Athena 1.0.129).
2. Ouvrir le téléphone, ouvrir le menu d’applications.
3. Envoyer une alerte plein écran depuis Overwatch Beta.
4. Le voile recouvre tout l’écran, le titre et le texte se lisent, Fermer
   fonctionne. Le jeu ne se ferme pas tout seul.

## Statut

Corrigé côté sources (à valider in-game après relance Arma).
