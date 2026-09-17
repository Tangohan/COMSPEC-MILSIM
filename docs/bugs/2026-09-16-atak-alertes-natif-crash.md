# Alertes plein écran et cartouches IceMan — plantage vs pack du 14/09

## Contexte

Le pack Workshop `mod/Workshop/14-09-2026` (Overwatch 1.5.69 / Athena 1.0.116)
ne fermait pas le jeu. Les packs 1.0.127–1.0.143 ferment encore, parfois sans
ouvrir le téléphone.

## Symptôme

Arrêt anormal `ACCESS_VIOLATION` / `can't resize AutoArray to negative size!`,
y compris téléphone fermé ou en miniature.

## Cause

Par rapport au pack du 14 septembre, Athena a ajouté :

1. Un voile d’alerte créé à la volée sur **les deux** écrans (téléphone ouvert
   et miniature 3D), avec un cadre IceMan (`Iceman_ReportsDetailText`) qui
   n’était pas dans la maquette d’origine. Le nom de classe ne contient pas
   « structured » : le voile était **détruit et recréé à chaque tick**.
2. Les cartouches de carte (identité, curseur) créés de la même façon par-dessus
   l’écran IceMan, au lieu d’utiliser les cartouches déjà présents.

Le pack 1.0.116 n’avait ni voile, ni `setPlainText`, ni cadre IceMan créé à la
volée : seulement les notifications natives du téléphone.

## Correctif

Athena 1.0.144 :

- le voile d’alerte n’existe que si le téléphone est vraiment ouvert ;
- le téléphone en miniature n’en reçoit plus ;
- plus aucun cadre IceMan créé à la volée (texte simple du téléphone seulement) ;
- les restes sur le miniature sont retirés, pas recréés.

## Fichiers touchés

- `atak_athena/functions/fn_athena_paintFullscreenAlert.sqf`
- `atak_athena/functions/fn_athena_updateMapHud.sqf`
- `atak_athena/config.cpp`

## Vérification

1. Quitter Arma complètement, recharger Athena 1.0.144.
2. Laisser le téléphone fermé quelques minutes : le jeu reste ouvert.
3. Ouvrir le téléphone : cartouches IceMan d’origine, pas de calque ajouté.
4. Alerte depuis le poste : voile sur l’écran ouvert, pas sur le miniature.

## Statut

Corrigé côté sources (Athena 1.0.144, à valider in-game après relance).
