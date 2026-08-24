# Relief ATAK — heightmap Arma vs altitude opérateur

## Contexte

Journal `COMSPEC_2026-08-24_023859_863.log`, mission tempMissionMPSEEK v2.
Action ACE « Relever le relief du théâtre » vers 03:03:45.
Connect v1.4.53 en jeu, extension 2.0.11.

## Symptôme

Centaines de lignes `ÉCHEC · Relief | ["",0,0]` en quelques secondes, plus quelques
`HTTP POST 401 /public/api/atak/terrain/chunk`. Le calque ombrage de la carte ATAK
reste vide. Confusion possible avec l’altitude déjà visible sur la carte Arma et
sur chaque pastille ATAK.

## Cause

Deux altitudes distinctes :

1. **Opérateur / véhicule** — `getPosASL` (Z au-dessus de la mer), déjà envoyée
   à chaque position ATAK (`asl_z`). C’est la hauteur du soldat, pas le modelé du sol.
2. **Sol de la carte Arma** — `getTerrainHeightASL [x,y]`. C’est la heightmap du
   théâtre. La carte satellitaire ATAK ne la contient pas : il faut l’échantillonner
   en jeu pour l’ombrage / les courbes.

Le relevé envoyait **toute** la carte (centaines de blocs). L’extension n’acquittait
pas l’appel (`return` vide → `["",0,0]`), donc chaque bloc était journalisé en échec
même avant la réponse HTTP. Les POST relief étaient ensuite refusés (401), sans
arrêt de la boucle.

## Correctif

- Relevé par défaut **autour de l’équipe** (~4 km), pas toute la carte.
- Acquittement `["OK","queued"]` ; un retour vide n’est plus traité comme un échec.
- Arrêt immédiat si le portail refuse le relief (401).
- Chaque position ATAK emporte aussi `terrain_z` (sol sous l’opérateur).

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sampleTerrain.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updatePosition.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp` (1.4.63)
- `mod/UptoDate/COMSPECExtension/Extension.cs` (2.0.12)

## Vérification

- Relancer Overwatch 1.4.63 + extension 2.0.12.
- Menu ACE : « Relever le relief autour de l’équipe » — quelques blocs, pas une
  rafale d’échecs.
- Si la liaison est refusée : un message d’interruption, pas des centaines de lignes.
- Les pastilles ATAK continuent d’envoyer l’altitude opérateur (niveau de la mer).

## Statut

corrigé (rebuild connect + DLL requis en jeu)
