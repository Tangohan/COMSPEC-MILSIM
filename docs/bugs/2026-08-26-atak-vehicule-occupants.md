# ATAK — véhicule et personnes à bord invisibles

## Contexte

Sur Zeus, un CH-146 Griffin (groupe Alpha 1-2) affiche clairement l’équipage et les passagers. Sur le poste ATAK, la fiche du même appareil ne montrait que la télémétrie (position, cap, vitesse), sans le nom de l’appareil ni la liste des personnes à bord.

## Symptôme

- La fiche « Alpha 1-2 — Hélicoptère » n’indique pas le modèle (Griffin) ni qui est dedans.
- L’onglet Personnel reste vide.
- Zeus liste des lignes « SE DÉPLACER » à la place de vrais noms (fuites d’ordres ACE).

## Cause

1. Le relevé d’occupation aérienne envoyait l’équipage, mais la liste du poste ne renvoyait ni `crew` ni `occupants` au navigateur.
2. Les personnes à bord n’étaient pas collectées de façon lisible (noms d’ordre ACE, JSON d’équipage mal sérialisé).
3. La fiche ouvrait l’onglet Situation, donc même un onglet Personnel rempli n’était pas vu d’emblée.
4. Les envois d’occupation et de suivi véhicule étaient trop espacés (une quinzaine de secondes), donc la liste n’apparaissait pas assez tôt.
5. Journal 18:00 : HTTP **code 0** sur les flux caméra puis le manifeste d’occupation, annoncé à tort comme « Athena saturé » (pause 2 s puis 8 s). Code 0 = timeout / coupure, pas un 429. Toute la file Tx était gelée, l’équipage du Griffin n’arrivait pas.

## Correctif

- Collecte des personnes à bord (pilote, tireur, passagers) avec des noms lisibles, sans les libellés d’ordre ACE.
- L’occupation aérienne envoie l’équipage ; le poste le renvoie au navigateur.
- La fiche affiche le modèle de l’appareil et la liste à bord ; l’onglet Personnel s’ouvre quand des personnes sont connues.
- Cadence assouplie : envoi dès 2,5 s si la situation change, rappel toutes les 7 s sinon.
- Liaison 1.17.3 : code 0 / timeout = « poste injoignable », jamais « saturé ». Les caméras ont un cooldown à part et ne jettent plus le manifeste d’occupation.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_collectVehicleOccupants.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reportCrewedAirAssets.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updatePosition.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initGpsBeacons.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initVehicleTracking.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf`
- `mod/UptoDate/COMSPECExtension/Extension.cs` (liaison 1.17.3)
- `app/Controllers/Api/AtakApiController.php`
- `app/Repositories/AtakDataRepository.php`
- `app/Services/Tactical/AtakAirAssetMergeService.php`
- `public/assets/js/atak-unit-popup.js`
- `public/assets/js/atak-unit-dossier.js`

## Vérification

- Tests unitaires : fusion manifeste + occupation conserve l’équipage ; sources SQF et API exposent `occupants` ; cadence 2,5 s / 7 s ; liaison 1.17.3.
- Recette in-game : Overwatch 1.4.78 + liaison 1.17.3, **quitter Arma** puis relancer, rafraîchir le poste, ouvrir la fiche de l’hélicoptère.

## Statut

corrigé — pack 1.4.78 + liaison 1.17.3. Quitter Arma pour copier la DLL si le Workshop est verrouillé.
