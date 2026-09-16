# Plantage ATAK — arrêt brutal pendant la synchro

## Contexte

Téléphone ATAK ouvert, liaison Athena active, boucles de sync déjà démarrées.
Plantages aléatoires, sans message SQF juste avant la fermeture.

## Symptôme

Arma se ferme d’un coup avec « Arrêt anormal » / ACCESS_VIOLATION (`0xC0000005`).
Dans le journal moteur, juste avant :

`Error: can't resize AutoArray to negative size!`

Même adresse dans `Arma3_x64.exe` sur plusieurs sessions (solo et multijoueur).
Les journaux COMSPEC s’arrêtent proprement : pas de pile d’erreur de script.

## Cause

Le calage de la carte et du tiroir d’applications relisait la largeur **déjà
réduite** de la carte, puis relançait l’animation à ressort. Une largeur cible
nulle, ou un dépassement du ressort sous zéro, faisait planter le moteur
(tableau interne à taille négative). Rouvrir la carte, changer d’application
ou simplement laisser le téléphone ouvert + synchro suffisait à relancer ce
calage.

Revue du reste : l’écran d’accueil Athena masquait encore des boutons en leur
donnant une taille nulle, même famille de plantage. Les cartouches carte
pouvaient aussi interpréter un nom d’opérateur comme du texte enrichi.

Hors cause retenue pour ces plantages du 15 septembre :

- la liaison elle-même (la DLL répondait encore `ping=OK`) ;
- l’affichage situation JVN (Draw3D), éteint par défaut ;
- les overlays d’écran cassé / hors couverture, déjà bornés à l’écran du téléphone ;
- les erreurs de lecture d’un message mal formé : elles restent dans le journal,
  elles ne ferment pas le jeu.

## Correctif

- Athena 1.0.140 : plus aucun recadrage de la carte ni du menu. Athena
  masque ou affiche seulement la grille d’accueil.
- Les cartouches carte et le bandeau Messagerie refusent une taille invalide.
- L’écran d’accueil refuse aussi une taille nulle lorsqu’un bouton est masqué.
- Pendant une alerte plein écran, le calage carte / tiroir n’anime plus
  (voir `2026-09-15-atak-alerte-plein-ecran.md` et `2026-09-15-atak-tiroir-scroll-crash.md`).

## Fichiers touchés

- `atak_athena/functions/fn_ATAK_Check_Layout.sqf`
- `atak_athena/functions/fn_athena_updateMapHud.sqf`
- `atak_athena/functions/fn_athena_commsFooter.sqf`
- `atak_athena/functions/fn_athena_taskFooter.sqf`
- `atak_athena/functions/fn_athena_applyHomeLayout.sqf`
- `atak_athena/config.cpp` (Athena 1.0.140)

## Vérification

1. Quitter Arma complètement, recharger le pack (Athena 1.0.140).
2. Ouvrir le téléphone, laisser la synchro tourner plusieurs minutes.
3. Ouvrir et fermer la carte, changer d’application, ouvrir Messagerie,
   passer par l’écran d’accueil.
4. Le jeu ne doit plus se fermer tout seul.

## Statut

Corrigé côté sources (à valider in-game après relance Arma).
