# Fiche de renseignement — PHOTO ne charge pas les captures in-game

## Contexte

Dans le rédacteur de fiche (ATAK), le volet pièces jointes propose un bouton
PHOTO pour joindre une image déjà prise. Les emplacements restaient gris, sans
aperçu, et la liste ne proposait pas les captures d’écran du jeu.

## Symptôme

- PHOTO / ancienne GALERIE n’attache rien, ou prend silencieusement la première
  photo ATAK.
- Les captures d’écran déjà faites en jeu n’apparaissent pas.
- Les emplacements affichent une légende mais pas l’image.

## Cause

1. La collecte ne regardait que la bibliothèque ATAK (Iceman), pas le dossier
   des captures du profil de jeu.
2. Aucun choix n’était proposé s’il y avait plusieurs fichiers.
3. Les emplacements étaient du texte seul, sans contrôle d’image.

## Correctif

- L’extension liste les captures locales (hors ligne).
- PHOTO ouvre une liste des captures du jeu et des photos ATAK.
- Chaque emplacement affiche un aperçu quand le fichier existe.

## Fichiers touchés

- `COMSPECExtension/Extension.cs` (2.0.7)
- `connect/display_intel_note.hpp`
- `connect/functions/fn_listLocalScreenshots.sqf`
- `connect/functions/fn_intelNoteCollectPhotos.sqf`
- `connect/functions/fn_intelNoteAddPiece.sqf`
- `connect/functions/fn_intelNoteRefresh.sqf`
- `connect/functions/fn_intelNotePane.sqf`
- `atak_athena/functions/fn_athena_collectLocalPhotos.sqf`

## Vérification

1. Quitter Arma, rebuild `connect.pbo` 1.4.44 + `atak_athena.pbo` 1.0.40 + DLL 2.0.7.
2. Prendre une capture d’écran en jeu, ouvrir une fiche → Pièces jointes → PHOTO.
3. La capture apparaît dans la liste, l’aperçu s’affiche une fois jointe.

## Statut

Corrigé côté sources (à valider in-game après relance Arma).
