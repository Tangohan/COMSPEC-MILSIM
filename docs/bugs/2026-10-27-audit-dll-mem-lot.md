# Audit LOT-DLL / LOT-MEM — freezes liaison et files

## Contexte

Reprise audit perf ATAK : freezes possibles hors combat via `callExtension` synchrone et accumulations mémoire.

## Symptôme

- Commandes réseau (avis admin, whoami, IP serveur, self-test) pouvaient attendre jusqu’à ~8 s sur le fil de jeu.
- `MapPack.Count` reparcourait tout l’arbre PNG à chaque appel.
- HashMap Iceman médical et file de logs DLL sans plafond nettoyage.

## Cause

- `TryGetSyncResponse` exécuté sur le thread `callExtension` même pour HTTP.
- Enum FS synchrone ; files non bornées (A6).

## Correctif

- `InAsyncWorker` : hors Async → `ERR|use_async` pour POST lourds ; GetClientIp / Server.PublicIp sync seulement dans Async, sinon `ServePollGet`.
- Cache TTL 10 s pour `MapPack.Count`.
- Purge clés Iceman > 90 s ; plafond 2000 `PendingLogLines`.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/.../fn_pushIcemanMedicalAlert.sqf`
- `reports/engineering/*`, `issues.json`
- `app/Support/DevDispatchCatalog.php` (UPDATE 1183)

## Vérification

- `dotnet build` Extension : OK.
- Arma / banc réseau : non exécutés.

## Statut

Corrigé en sources (non mesuré en jeu).
