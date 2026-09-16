# Téléphone ATAK — menu qui s’ouvre puis se referme, souris hors cadre

## Contexte

Session 14:22 (Athena 1.0.138, liaison 2.0.41). Capture écran 14:26,
RPT `Arma3_x64_2026-09-16_14-22-35.rpt`. Fermeture à 14:27:35.

Nouveau crash 14:36:56, RPT `Arma3_x64_2026-09-16_14-31-53.rpt`,
toujours sur liaison 2.0.42 / Athena 1.0.138. Athena 1.0.139 a été
reconstruit à 14:41, donc **jamais chargé** dans cette session.

## Symptôme

- Le menu d’applications clignote : il s’ouvre une fraction de seconde
  puis se referme tout seul.
- Dès que le curseur sort du cadre du téléphone, l’écran ne répond plus.
- Le jeu se ferme encore, plus tard, après quelques ouvertures.
- Même arrêt brutal à 14:36, 31 s après l’ouverture du téléphone.

## Cause

1. Le calage d’écran lançait une tâche avec l’écran dans un tableau, puis
   testait « est-ce vide ? » sur ce tableau. Arma écrivait une erreur à
   chaque mouvement de souris (14:26:46), ce qui fige l’interface.
2. Le bandeau carte croyait le téléphone fermé dès qu’un écran IceMan
   était introuvable une fraction de seconde. Il forçait alors le menu
   fermé. IceMan l’affichait, nous le masquions : pop / depop.
3. Un second clic trop rapide sur le chevron refermait le menu tout de
   suite.
4. AutoArray : Athena **recadrait encore** la carte et le menu (40 %
   de largeur) à chaque calage. Même sans le tableau, le moteur tombe
   dès qu’une largeur devient nulle ou négative.

## Correctif

Athena 1.0.139, liaison 2.0.43 :

- Calage : l’écran est lu comme un écran, plus comme un tableau.
- L’ouverture du menu est mémorisée tant que le téléphone est vraiment
  ouvert, pas remise à zéro entre deux recréations d’écran.
- Chevron : un seul geste toutes les 0,25 s.
- Curseur hors carte : plus de conversion carte, plus de recadrage.
- Journal : mémoire et ouverture/fermeture du téléphone toutes les 5 s
  tant que l’écran est là.

Athena 1.0.140 :

- Plus aucun recadrage de la carte ni du menu. Athena masque ou affiche
  seulement la grille d’accueil. IceMan pose les cadres.

## Fichiers touchés

- `atak_athena/functions/fn_ATAK_Check_Layout.sqf`
- `atak_athena/functions/fn_athena_enforceDrawer.sqf`
- `atak_athena/functions/fn_athena_updateMapHud.sqf`
- `atak_athena/functions/fn_athena_installMapHud.sqf`
- `atak_athena/functions/fn_athena_phoneDisplay.sqf`
- `atak_athena/XEH_postInitClient.sqf`
- `atak_athena/config.cpp`
- `mod/UptoDate/COMSPECExtension/Extension.cs`

## Vérification

1. Quitter Arma complètement (Athena 1.0.140, journal Extension 2.0.43).
2. Ouvrir le téléphone : menu fermé, carte pleine largeur.
3. Chevron : le menu s’ouvre et **reste** ouvert. Refermer, rouvrir.
4. Sortir le curseur du cadre puis revenir : l’écran répond.
5. Laisser le téléphone ouvert une minute : le jeu reste ouvert.
6. Journal COMSPEC : lignes « Mem » avec la mémoire, sans explosion.
7. Barre de version : Athena 1.0.140 (pas 1.0.138).

## Statut

Corrigé en source (à valider in-game après relance Arma complète).
