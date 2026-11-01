# Saccades semi-régulières téléphone fermé, mode profilage

## Contexte

Retours joueurs (Discord, « Semi consistent stutters », ATAK natif 2.9.26) : à-coups semi-réguliers à pied ou en
véhicule, téléphone complètement fermé mais porté, reproduits avec le mod seul + CBA/ACE, aussi en mission locale Eden.
Les corrections de 2.9.21 (`2026-11-01-carte-saccades-drone-lanceur.md`) visaient la carte ouverte.

## Symptôme

- Image figée quelques dizaines de ms à intervalles plus ou moins réguliers, sans rien ouvrir.
- RPT du 01/11 (19:23, 2.9.26) inexploitable : rien après le chargement de la carte (19:26:10) jusqu'à la fermeture
  (21:22), aucun postInit ; seulement 2 « Error in expression » de `fn_zeusShowPlayerAtak` et les avertissements de
  gestes au chargement de la config.

## Cause

- `fn_offline` : la sauvegarde hors ligne (toutes les 30 s) marquait le profil modifié à chaque passage même sans
  nouveau message (seule l'heure changeait) : `saveProfileNamespace` toutes les 2 min (`fn_profileSave`). Il réécrit
  tout le profil (3,2 Mo chez TTRD) et fige l'image. Idem, dans une moindre mesure, carnet de distances et temps d'écran
  (5 min) même sans changement.
- `fn_dockServer` (serveur : le joueur lui-même en solo, Eden ou partie hébergée) : `allMissionObjects` toutes les minutes.
- connect `fn_initGpsBeacons` : toutes les 12 s sur chaque poste, trois appels de fonction par unité et un par véhicule.
- connect `fn_zeusShowPlayerAtak` : `replaceString` n'existe pas dans Arma 3 (fonction refusée au chargement).
- « No entry '.gesturecomspec…' » : lus une fois au chargement de la config (comme pour les gestes des autres mods), pas
  en partie.

## Correctif

Sauvegardes périodiques écrites seulement si leur contenu a changé ; une écriture du profil sert les deux échéances
(ATAK et Overwatch). Socles : un seul balayage puis `EntityCreated`. Balises : drapeaux lus directement, unités sans
drapeau écartées. `splitString` / `joinString` dans `fn_zeusShowPlayerAtak`. Nouveau mode profilage (`fn_profiler`,
réglage « Profilage (saccades) » dans ATAK · Debug) : temps de chaque boucle CBA (COMSPEC et autres mods), des appels et
rappels de la DLL et des écritures du profil, pics > 5 ms et saccades avec ce qui tournait, toutes les 10 s dans le RPT.

## Fichiers touchés

`mod/COMSPEC_ATAK_Native/Sources/addons/main/` : XEH_preInit, XEH_postInitClient, config.cpp, functions/core/
(fn_profiler nouveau, fn_perfAcc, fn_saveProfile, fn_profileSave), network/(fn_offline, fn_extensionCall, fn_extAwait),
screen/(fn_distanceLog, fn_screenTime), dock/fn_dockServer, teams/fn_callsignAction.
`mod/UptoDate/Sources/comspec-overwatch-addons/connect/` : XEH_postInit, config.cpp, functions/(fn_extAwait,
fn_extDirect, fn_log, fn_saveProfile, fn_initGpsBeacons, fn_zeusShowPlayerAtak).

## Vérification

En jeu (Eden, mission locale, téléphone porté et fermé) : cocher Profilage (saccades), jouer 10 min, lire les lignes
`[PROF]` du RPT (bilan, COMSPEC, Autres mods, Pics, Saccade). Sans message reçu, plus d'« Écriture du profil » toutes
les 2 min. Demander le RPT aux joueurs du fil Discord avec le profilage coché.

## Statut

corrigé à vérifier en jeu (2.9.28) ; cause principale à confirmer par les RPT profilés des joueurs.
