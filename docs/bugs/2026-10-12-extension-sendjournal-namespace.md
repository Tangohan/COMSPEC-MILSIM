# Extension_SendJournal — namespace manquant

## Contexte

Rebuild NativeAOT `COMSPECATAKNativeExtension` après pull `9abdfb66e` (journal des envois Athena).

## Symptôme

Compilation Native : `JournalTrack` / `JournalHttp` introuvables dans `Extension.cs` ; dans `Extension_SendJournal.cs` : `IsPositionEndpoint` et champs privés introuvables.

## Cause

`Extension_SendJournal.cs` déclarait `partial class Extension` hors du namespace `COMSPECExtension`, alors que `Extension.cs` / Telemetry / Realism sont dans `namespace COMSPECExtension`.

## Correctif

Ajouter `namespace COMSPECExtension;` en tête de `Extension_SendJournal.cs`, et lier le fichier dans `COMSPECATAKNativeExtension.csproj`.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension_SendJournal.cs`
- `mod/COMSPEC_ATAK_Native/COMSPECATAKNativeExtension/COMSPECATAKNativeExtension.csproj`

## Vérification

`dotnet publish` Native + Overwatch OK.

## Statut

Corrigé (2026-10-12)
