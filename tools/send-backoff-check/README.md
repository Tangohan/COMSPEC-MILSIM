# Contrôle de la pause d'envoi de COMSPEC Link (NET-006, TEST-010)

Contrôle local **sans réseau** de la pause d'envoi commune de la DLL COMSPEC Link (et de la DLL ATAK natif, compilée
depuis le même `Extension.cs`). Il charge la DLL **managée** par réflexion et rejoue les suites d'échecs et de
succès que la liaison rencontre. Aucune requête HTTP n'est émise : seules les méthodes internes de comptage sont
appelées (`NoteSendFailure`, `NoteRateLimited`, `NoteSendSuccess`, `NotePollResult`…), et les rappels vers le SQF
sont captés au lieu d'être envoyés au jeu.

- **Hors** de `mod/UptoDate/COMSPECExtension/` à dessein : ce csproj compile tous les `.cs` de son dossier.
- Panne = réseau coupé, délai dépassé, maintenance 503. Frein = 429 (limite d'Athena), 403 du site entier.

## Ce qu'il vérifie

| Contrôle | Attendu |
| --- | --- |
| `U` | avant l'échelle, les pauses courtes d'avant (1 s, 2 s) sont inchangées |
| `A1` `A2` `A3` | panne : entrée au 3e échec sur un palier de 10 s (+ 0 à 5 s de gigue), sortie au premier succès, `RateLimitClear` envoyé au SQF |
| `B1` `B2` `B3` | maintenance 503 : `Retry-After` respecté, gigue bornée à 15 s, sortie au premier succès après la fin |
| `E1` `E2` | panne longue : paliers 10, 20, 30 puis 45 s (60 s au plus avec la gigue), sortie au premier succès même au dernier palier |
| `G` | la pause de panne reçoit une gigue réelle (30 tirages, écart d'au moins 3 s) |
| `C` `D` `F` `H` `T` | frein 429 / 403 : **exactement comme avant** (échelle 45 à 600 s, `Retry-After`, deux succès par palier, aucune gigue) |
| `I` | après un frein terminé, une panne repart sur l'échelle courte |
| `P1` `P2` `P3` `P4` | canaux sondés (ordres, tchat…) : l'attente d'une panne reçoit 0 à 20 % de gigue et est levée à la sortie de pause ; l'attente d'un refus (401, 403, 429) reste exacte et gardée |
| `R1` `R2` `R3` `R4` | un canal sondé qui avait vu la panne et répond de nouveau lève la pause d'envoi (même pendant un `Retry-After` de maintenance de 900 s) ; jamais un frein, jamais un canal qui n'avait pas vu la panne |

Sur une DLL d'avant le correctif, `A1 A2 A3 B2 B3 E1 E2 G I P1 P3 R1 R2` échouent (13 : c'est le défaut corrigé) ;
les autres passent sur les deux versions.

## Lancer (sur une copie HORS du worktree : dotnet écrit bin/obj à côté du csproj)

```bash
# DLL managée à contrôler (sortie d'un dotnet build, pas la .so AOT)
dotnet build -c Release mod/UptoDate/COMSPECExtension/COMSPECExtension.csproj -o /tmp/link-managed
cp -r tools/send-backoff-check /tmp/send-backoff-check && cd /tmp/send-backoff-check
dotnet build SendBackoffCheck.csproj -c Release
BACKOFF_DLL=/tmp/link-managed/COMSPECExtension_x64.dll dotnet bin/Release/net8.0/SendBackoffCheck.dll
```

Sortie : une ligne `PASS` ou `FAIL` par contrôle ; code de sortie 0 si tout passe, 1 sinon.
