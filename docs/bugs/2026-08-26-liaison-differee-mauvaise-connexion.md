# Liaison différée — mauvaise connexion

## Contexte

Quand plusieurs envois Overwatch n’atteignaient pas le poste (coupure, refus, saturation), le jeu et la carte web continuaient au rythme normal ou marquaient une coupure. Les opérateurs croyaient que tout le monde était parti.

## Symptôme

- Rafale d’envois alors que le poste ne reçoit plus.
- Sur la carte : « En liaison » ou « Coupée », jamais un état « on ralentit, mauvaise connexion ».
- Après un refus, reprise trop brutale dès le premier succès.

## Cause

Trois pauses séparées (refus, saturation, coupure), trop courtes, et un retour immédiat au rythme normal. La carte web traitait une pause comme une coupure.

## Correctif

Échelle unique pour tout ce qui part vers le poste : 45 s, puis 1 min 15, 2 min 30, 5 min, 10 min. Trois échecs d’affilée pour y entrer ; deux succès pour descendre d’un cran. Le jeu signale le mode différé avec la position. La carte affiche « Différé · mauvaise connexion ».

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updatePosition.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateVehicleTracking.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reportCrewedAirAssets.sqf`
- `public/assets/js/atak-socket.js`
- `public/assets/js/atak-units.js`
- `public/assets/css/atak.css`
- `views/atak.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE 234)

## Vérification

Tests unitaires (échelle, libellé HUD, catalogue). Recette : carte du poste — une pause affiche Différé · mauvaise connexion, les pastilles restent. Jeu : pack 1.4.84, relance complète.

## Statut

corrigé (pack Overwatch 1.4.95)
