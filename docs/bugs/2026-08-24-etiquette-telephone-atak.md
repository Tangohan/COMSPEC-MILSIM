# Fiche téléphone ATAK trop verbeuse

**Date :** 2026-08-24  
**Statut :** corrigé

## Contexte

La géolocalisation téléphone (Zeus / Eden) remontait bien sur la carte. En cliquant le contact, la fiche latérale réutilisait le dossier d’unité complet (onglets Situation, Analyse Athena, cap, vitesse, état…).

## Symptôme

Un simple signal téléphone affichait une télémétrie inventée ou inférée (vitesse vide, état « Indéterminé », confiance Athena, nom « Tél. … »). Zeus ne pouvait pas choisir ce qui est publié : tout était visible dès que le suivi était actif.

## Cause

- Le menu Zeus basculait seulement on/off (`COMSPEC_PhoneTrack`).
- `reportPhonePosition` envoyait cap, altitude, camp, véhicule et un état « stable ».
- La fiche web (`atak-unit-dossier.js`) ne distinguait pas un téléphone d’une unité ATAK.

## Correctif

- Dialogue Zeus : activer le suivi et cocher les données à publier (nom, grille, altitude, cap, dernier signal, camp, véhicule). **Tout est masqué par défaut.**
- Le point reste sur la carte ; seuls les champs cochés apparaissent dans la fiche, les infobulles et la liste des effectifs.
- Fiche compacte « Signal téléphone », sans onglets ni analyse Athena.
- Contacts déjà en liaison sans réglage : traités comme masqués.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_phoneTrackConfigure.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_phoneRevealHas.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_setPhoneTrack.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reportPhonePosition.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZenTrackActions.sqf`
- `public/assets/js/atak-unit-dossier.js`, `atak-unit-popup.js`, `atak-units.js`, `atak-map.js`, `atak-cop.js`, `atak-unit-menu.js`
- `app/Services/Tactical/AtakOperationalStatusService.php`
- `tests/Unit/AtakPhoneRevealTest.php`

## Vérification

- Test unitaire `AtakPhoneRevealTest`.
- Recette in-game : Zeus → Géolocalisation téléphone → laisser les cases décochées → fiche = « aucun détail » ; cocher le nom / la grille → ces champs apparaissent.

## Usage Zeus

Clic droit sur une personne → **Géolocalisation téléphone**. Le suivi s’active ; les cases restent vides tant qu’on ne choisit pas une donnée. Relancer Arma après le rebuild Overwatch **1.4.57**. Rafraîchir l’ATAK web.
