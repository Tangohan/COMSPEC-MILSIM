# Réseau : radio ACRE2 ordinaire prise pour une MPU5

## Contexte
App Réseau du téléphone (S23), réglage « Liaison réaliste téléphone-radio » coché, joueur portant une radio ACRE2 ordinaire (exemple : AN/PRC-152) et aucune MPU5.

## Symptôme
1. L'option de relier la radio à la MPU5 (pont voix) est proposée ; au clic : « Aucune MPU5 sur vous ».
2. La section « Radio de données » affiche « MPU5 sur vous : allumée » (reliée à un relais) alors qu'aucune MPU5 n'est portée.

## Cause
En liaison réaliste, `fn_mpu5 "has"` accepte toute radio de données reconnue (ACRE2 et TFAR d'abord), voulu pour relier le téléphone. Mais le pont voix exige une vraie MPU5 (`"hasMpu"`, vérifié au clic dans `fn_mpuVoice "bridge"`), et l'affichage écrivait « MPU5 » en dur pour toute radio.

## Correctif
- Le pont voix n'est proposé que si une vraie MPU5 est portée : `fn_mpuVoice "view"` (champ `carried`), page Wave Relay, assistant Wave Relay (étape « Une MPU5 sur vous ») et dépannage MPU (`_canBridge`) utilisent `"hasMpu"`. Sans MPU5, le texte « Portez une MPU5 pour y relier votre radio. » remplace les boutons RELIER.
- La section « Radio de données » affiche le nom réel de la radio (« AN/PRC-152 · radio sur vous : allumée ») quand ce n'est pas une MPU5. Deux textes anglais ajoutés.

## Fichiers touchés
`fn_mpuVoice.sqf`, `fn_mpuDiag.sqf`, `fn_pageNetwork.sqf`, `fn_pageWaveRelay.sqf`, `fn_waveRelaySheet.sqf`, `i18n/en.txt`, `localisation/template/xx.txt`.

## Vérification
Relecture du code ; pas de vérification en jeu. La pastille d'état du téléphone (fn_mpuLink "badge") et les messages « Téléphone relié à la MPU5 » disent encore « MPU-5 » pour une radio ACRE2 : non traité ici.

## Statut
corrigé à vérifier en jeu
