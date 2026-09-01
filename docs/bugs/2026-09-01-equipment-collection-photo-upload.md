# 2026-09-01 — Photo de présentation collection Équipement refusée

## Contexte
Création d’une collection (SOAR) depuis Équipement, avec une photo de présentation.

## Symptôme
La collection est bien créée (« Collection créée. ») mais un bandeau rouge indique « Le téléversement de la photo a échoué. Réessayez. » La fiche reste « Sans photo de présentation ».

## Cause
Le fichier arrive jusqu’à PHP, qui le refuse (souvent trop lourd pour la limite d’envoi du serveur, parfois format photo iPhone). Le code renvoyait le même message générique pour tous ces cas, alors que le formulaire annonçait 8 Mo sans tenir compte de la limite réelle.

## Correctif
Messages d’erreur précis (trop lourd, envoi interrompu, photo iPhone). La taille indiquée suit la limite réelle du serveur. La photo est allégée à l’enregistrement. Le serveur demande une limite d’envoi plus haute.

## Fichiers touchés
- `app/Support/EquipmentCoverStorage.php`
- `views/equipment/hub.php`
- `views/equipment/collection.php`
- `views/equipment/tenue.php`
- `public/.user.ini`
- `tests/Unit/EquipmentHubAssetTest.php`

## Vérification
Tests d’assets. Sur le portail : Modifier la collection, renvoyer un JPG/PNG. Si trop lourd, le message indique la taille maximale.

## Statut
Corrigé (à déployer sur le portail)
