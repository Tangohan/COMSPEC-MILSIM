# Page Terminaux ATAK : erreur à l’ouverture

## Contexte

Production `https://athena.ttrd.fr/public/account/security/devices`.
Après déploiement de l’appariement de secours, un membre connecté ouvre
Compte → Terminaux ATAK.

## Symptôme

Erreur `ArgumentCountError` : le contrôleur des terminaux ATAK attend
quatre dépendances, le routeur l’instancie sans aucune.

## Cause

Le routeur résout les contrôleurs via le conteneur. Absent du registre,
le contrôleur tombait sur `new $class()` (zéro argument). Le même oubli
existait pour l’API d’appariement.

Les colonnes `atak_terminal_id` et `certificate_id` étaient aussi
déclarées en entier long, alors que le parc de terminaux et les
certificats utilisent un entier simple : la création de la table des
terminaux de confiance échouait.

## Correctif

- Enregistrement du dépôt, du service, du contrôleur web et du
  contrôleur d’API dans le registre des liaisons.
- Types des colonnes alignés sur le parc de terminaux et les certificats.

## Fichiers touchés

- `app/Core/ContainerIntegrations.php`
- `migrations/20260904120000_atak_secure_device_auth.sql`
- `tests/Unit/AtakDeviceAuthContractTest.php`
- `docs/bugs/2026-09-04-atak-device-security-container.md`

## Vérification

Tests d’assets. Après déploiement : ouvrir Compte → Terminaux ATAK
connecté, sans erreur.

## Statut

corrigé
