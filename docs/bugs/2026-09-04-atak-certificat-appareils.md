# Certificat ATAK et page appareils

- Date : 2026-09-04
- Statut : corrigé

## Contexte

En production, l’enregistrement d’un certificat depuis le jeu et l’ouverture de la page des appareils liés renvoyaient une erreur.

## Symptôme

- POST `/api/atak/certificates` : contrainte d’intégrité, le certificat pointe vers un appareil qui n’existe pas.
- GET `/account/security/devices` : la page des appareils liés ne s’ouvre pas (contrôleur sans liaisons).

## Cause

1. Le jeu envoie parfois un numéro d’appareil obsolète. La base refuse d’enregistrer le certificat.
2. La page des appareils n’était pas branchée dans le conteneur d’application, donc elle était créée sans ses dépendances.

## Correctif

- N’enregistrer un appareil sur le certificat que s’il existe bien dans la communauté ; sinon le certificat est créé sans lien appareil.
- Contrôleur des appareils liés enregistré, constructeur utilisable même sans injection, page Compte → Appareils liés.

## Fichiers touchés

- `app/Repositories/AtakRealismRepository.php`
- `app/Controllers/Api/AtakRealismApiController.php`
- `app/Controllers/Web/AtakDeviceSecurityController.php`
- `app/Core/ContainerIntegrations.php`
- `routes/web.php`
- `views/account/devices.php`

## Vérification

- Associer un certificat depuis le jeu : plus d’erreur serveur si l’appareil n’est pas encore en base.
- Compte connecté, ouvrir Appareils liés : la liste s’affiche.

## Statut

Corrigé dans les sources — à déployer sur le poste.
