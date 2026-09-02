# 2026-09-02 — Photo de présentation collection : stockage indisponible

## Contexte
Sur le portail Équipement, modification d’une collection (`/equipment/collections/1`, préfixe `/public/` sur athena.ttrd.fr) avec une photo de présentation.

## Symptôme
Un bandeau rouge indique « Stockage des photos indisponible pour le moment. » La collection est enregistrée, la photo ne l’est pas.

## Cause
Le dépôt tentait uniquement `public/uploads/equipment/{communauté}`. Après déploiement, ce dossier n’est souvent pas inscriptible (création du sous-dossier communauté refusée). Le message s’affichait dès cet échec, même si un dépôt interne (`storage/uploads`) aurait pu recevoir le fichier. Un second cas : le fichier temporaire était refusé s’il n’était pas reconnu comme téléversement PHP, alors qu’il était bien présent.

## Correctif
Création du dossier communauté d’abord sous le dépôt public, puis repli sur le dépôt interne. La photo est ensuite servie aux membres connectés de la même communauté. L’enregistrement accepte un fichier temporaire lisible même si la reconnaissance PHP du téléversement échoue.

## Fichiers touchés
- `app/Support/EquipmentCoverStorage.php`
- `app/Services/Media/ImageCompressionService.php`
- `app/Controllers/Web/ArsenalWardrobeController.php`
- `routes/web.php`
- `tests/Unit/EquipmentHubAssetTest.php`

## Vérification
Tests d’assets et lecture du catalogue. Sur le portail : Équipement, collection, Modifier, renvoyer un JPG/PNG. La photo s’affiche. Si le fichier est trop lourd ou au format iPhone, le message reste celui déjà en place.

## Statut
Corrigé (à déployer sur le portail)
