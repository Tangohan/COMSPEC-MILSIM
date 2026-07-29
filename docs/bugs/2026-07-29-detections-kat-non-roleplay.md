# Détections médicales KAT retirées (non roleplay ATAK)

## Contexte

La 1.4.8 avait introduit des alertes auto basées sur KAT (SpO2, voies aériennes, pneumothorax).

## Symptôme

L’ATAK web affichait des détections « Voies obstruées », « Pneumothorax sous tension », « Hypoxie SpO2 » alors qu’un terminal tactique ne peut pas connaître ces données en roleplay.

## Cause

Lecture directe des variables KAT (`kat_bloodGas_spo2`, `kat_airway_occluded`, etc.) et déclenchement d’alertes automatiques côté mod + parsing côté portail.

## Correctif

- Mod : détection auto limitée à ACE — **inconscient** et **arrêt cardiaque** uniquement.
- Suppression des handlers KAT, réglages CBA hypoxie, enrichissement SpO2/voies/thorax dans les messages.
- Portail : retrait des kinds `airway_obstruction`, `tension_pneumothorax`, `hypoxia` du parser et du panneau médical.

## Fichiers touchés

- `mod/.../fn_checkMedicalAlerts.sqf`, `fn_getMedicalState.sqf`, `fn_reportMedicalAlert.sqf`
- `mod/.../XEH_preInit.sqf` (réglages KAT retirés)
- `app/Support/MedicalAlertParser.php`
- `public/assets/js/atak-medical-alerts.js`
- Changelogs 1.4.8

## Vérification

- Joueur KAT avec SpO2 bas / voies obstruées debout : **aucune** alerte auto.
- Joueur ACE inconscient ou en arrêt cardiaque : alerte « Au sol » / « Arrêt cardiaque » comme avant.

## Statut

Corrigé
