# Écrans TOC 4.4.0 invisibles, pose toujours sur le même écran

## Contexte
ATAK 4.4.0 (build 2026.11.11 d808f1004), retour de TTRD en jeu : nouveaux écrans de salle TOC et module « Écran TOC ».

## Symptôme
- Eden / Zeus, COMSPEC › Écrans TOC et projection : seuls les quatre anciens écrans ; murs d'images, 98", 65", bandeau et plaque absents (classes pourtant présentes dans main.pbo).
- « Poser un écran TOC » : toujours le grand écran sur trépied, carte et flux, nommé « TOC » (journal : `Écran TOC COMSPEC_ATAK_Screen_Tripod (TOC) : affichage SPLIT` à chaque pose).
- Écrans d'autres mods (écran blanc OPSIS de salle de briefing…) impossibles à utiliser.

## Cause
- `toc_screens3d.hpp` : `COMSPEC_ATAK_Screen_Base3D: Static` sans `side` ; `Static` hérite de `All` (`side=4`, camp inconnu). Eden et Zeus ne listent que les camps 0 à 3.
- `fn_moduleTocScreen` : module lâché au Zeus traité comme un module Eden quand le contexte Zeus n'était pas reconnu : attributs jamais posés, donc défauts `COMSPEC_ATAK_Screen_Tripod` / `SPLIT` / « TOC ». `fn_tocScreen` « spawn » remplaçait en silence toute classe hors de la liste du serveur par la première.
- Aucune surface connue pour les écrans tiers (sélection 0 = châssis, ou nom « largeScreen » / « camo » non reconnu).

## Correctif
- `side=3` sur la base des écrans du mod.
- Module : fenêtre de choix dès que l'attribut `Class` n'est pas posé et que le joueur est Zeus ; « spawn » garde la classe choisie (liste, écran COMSPEC ou écran déclaré), repli journalisé, journal « Écran TOC posé : classe ».
- `CfgComspecTocScreens` (`toc_screens_tiers.hpp`) : surface, dalles recopiées, format, matière par classe ; fenêtre Zeus « Écran TOC : utiliser cet objet » (surface, format, matière, Tester l'image) ; action ACE « Utiliser comme écran TOC ».

## Fichiers touchés
`toc_screens3d.hpp`, `toc_screens_tiers.hpp`, `config.cpp`, `modules.hpp`, `stringtable.xml`, `fn_tocScreen.sqf`, `fn_moduleTocScreen.sqf`, `fn_tocPersist.sqf`, `fn_zeusForm.sqf`, `i18n/en.txt`, `localisation/template/xx.txt`.

## Vérification
`tests/Unit/AtakTocScreensPlacementAssetTest.php`, `hemtt check`. En jeu : écrans listés dans Eden et Zeus, pose de chaque type, écran OPSIS désigné.

## Statut
corrigé à vérifier en jeu
