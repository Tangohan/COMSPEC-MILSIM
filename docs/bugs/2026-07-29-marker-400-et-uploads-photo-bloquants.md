# Bug — marqueur web en erreur 400 et uploads photo bloquants

## Contexte

Création d’un `Repère web` depuis l’ATAK in-game et remontée de clichés vers Athena.

## Symptôme

- le portail recevait des `HTTP POST 400` sur `/public/api/atak/marker`
- le marqueur n’apparaissait pas sur la carte web
- l’envoi de photos provoquait des gels perceptibles côté jeu

## Cause

- un payload marqueur invalide pouvait être réessayé en boucle par la file native
- le JSON marqueur n’était pas assez blindé face aux caractères spéciaux / formats relâchés
- les uploads photo lisaient le fichier entier en mémoire sur le thread appelant avant l’envoi asynchrone

## Correctif

- arrêt des retries automatiques sur erreurs `4xx` non récupérables
- assainissement du JSON de marqueur côté SQF et côté extension
- envoi photo en flux (`StreamContent`) au lieu d’un chargement complet en mémoire avant file asynchrone
- complément v1.4.11 : voir `docs/bugs/2026-07-29-freeze-prise-photo-atak.md` (polling `ResolveLocalImagePath` 7 s + scans récursifs)

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sendLocalTacticalMarker.sqf`

## Vérification

- contrôle des conditions de retry HTTP
- contrôle du payload marqueur
- contrôle du chemin d’upload image sans `ReadAllBytes`

## Statut

`corrigé à vérifier en jeu`
