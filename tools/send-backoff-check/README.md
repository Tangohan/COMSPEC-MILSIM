# Contrôle de la pause d'envoi de COMSPEC Link (NET-006, TEST-010)

Contrôle local **sans réseau** de la pause d'envoi commune de la DLL COMSPEC Link (et de la DLL ATAK natif, compilée
depuis le même `Extension.cs`). Il charge la DLL **managée** par réflexion et rejoue les suites d'échecs et de
succès que la liaison rencontre. Aucune requête HTTP n'est émise : seules les méthodes internes de comptage sont
appelées (`NoteSendFailure`, `NoteRateLimited`, `NoteSendSuccess`, `NotePollResult`…, et `HandlePostResponse` pour les
refus 403 d'une adresse, rejoués sans requête), et les rappels vers le SQF sont captés au lieu d'être envoyés au jeu.

- **Hors** de `mod/UptoDate/COMSPECExtension/` à dessein : ce csproj compile tous les `.cs` de son dossier.
- Panne = réseau coupé, délai dépassé, maintenance 503, 5xx d'Athena entier. Frein = 429 (limite d'Athena), 403 du site entier.

## Ce qu'il vérifie

| Contrôle | Attendu |
| --- | --- |
| `U` | avant l'échelle, les pauses courtes d'avant (1 s, 2 s) sont inchangées |
| `A1` `A2` `A3` | panne : entrée au 3e échec sur un palier de 10 s (+ 0 à 5 s de gigue), sortie au premier succès, `RateLimitClear` envoyé au SQF |
| `B1` `B2` `B3` | maintenance 503 : `Retry-After` respecté, gigue bornée à 15 s, sortie au premier succès après la fin |
| `E1` `E2` | panne longue : paliers 10, 20, 30 puis 45 s (60 s au plus avec la gigue), sortie au premier succès même au dernier palier |
| `G` | la pause de panne reçoit une gigue réelle (200 tirages, écart d'au moins 3 s) |
| `C` `D` `F` `H` `T` | frein 429 / 403 : **exactement comme avant** (échelle 45 à 600 s, `Retry-After`, deux succès par palier, aucune gigue) |
| `H2` `H3` `H4` | panne sur l'échelle courte (`H2` : paliers 1 à 4 ; `H3` `H4` : palier 4) puis 429 ou 403 : le frein repart du premier palier de l'échelle longue, 45 s exactement (`H2` 429, `H3` 403) ou le `Retry-After` s'il est plus long (`H4` : 60 s) ; un second 429 aussitôt ne monte pas d'un palier ; deux succès pour sortir |
| `I` | après un frein terminé, une panne repart sur l'échelle courte |
| `P1` `P2` `P3` `P4` | canaux sondés (ordres, tchat…) : l'attente d'une panne reçoit 0 à 20 % de gigue et est levée à la sortie de pause ; l'attente d'un refus (401, 403, 429) reste exacte et gardée |
| `R1` `R2` `R3` `R4` | un canal sondé qui avait vu la panne et répond de nouveau lève la pause d'envoi (même pendant un `Retry-After` de maintenance de 900 s) ; jamais un frein, jamais un canal qui n'avait pas vu la panne |
| `S1` à `S12` | erreurs 5xx hors maintenance (NET-023, NET-015) : une seule route en erreur, ou une réponse acceptée (envoi ou sondage) dans les 10 s, garde la pause de son adresse (30 s au 1er palier, `S1` `S2` `S3` `S9` `S11`) avec 0 à 20 % de gigue, jamais moins que le `Retry-After` (`S7`) ; les adresses de position (lot de télémétrie, envoi unitaire) restent au 1er palier, sauf `Retry-After` plus long (`S12`) ; Athena entier en panne (5xx sur au moins deux adresses, sondages compris, `S10`, et rien d'accepté depuis 10 s) : pause commune, pauses par adresse de la panne levées (`S4`), échelle courte puis sortie au premier succès (`S5`), `Retry-After` d'un 503 respecté (`S6`) ; la maintenance reste à part (`S8`) |
| `L1` `L2` `L3` `L4` | refus 403 d'une adresse (NET-020, NET-022) : `feature_disabled` (fonction coupée par la communauté) verrouille les envois de cette adresse 30 min ou jusqu'à la session suivante (`L2`), la lecture reste libre ; `feature_disabled` et `feature_unavailable` (fonction hors offre, `L3` : file d'envoi et météo) abandonnent l'envoi sans pause commune ni rappel `AccessDenied` ; un autre 403 (`L4`) garde la pause de 20 s, le rappel et le nouvel essai d'avant |

Sur la DLL de main (2.0.98), `A1 A2 A3 B2 B3 E1 E2 G H2 H3 H4 I P1 P3 R1 R2` échouent (défaut de NET-006 corrigé par
le lot), `L1 L2 L3` (NET-020, NET-022) et `S4 S5 S6 S7 S10 S12` (NET-023, NET-015) : 25 en tout ; sur la première
version du lot (`8faa74ab`), `H2 H3 H4` échouent (le palier de la panne était relu sur l'échelle longue : 150, 300 ou
600 s) ; sur le lot avant son point NET-022 (`c3d8a85c`), `L1 L2 L3` et `S4 S5 S6 S7 S10 S12` ; avant son point
NET-023 (`c81a2db6`), `S4 S5 S6 S7 S10 S12`. `U B1 C D F H T P2 P4 R3 R4 L4 S1 S2 S3 S8 S9 S11` passent partout : le
frein seul, les autres refus et l'erreur d'une seule route (hors adresses de position) restent comme avant.

## Lancer (sur une copie HORS du worktree : dotnet écrit bin/obj à côté du csproj)

```bash
# DLL managée à contrôler (sortie d'un dotnet build, pas la .so AOT)
dotnet build -c Release mod/UptoDate/COMSPECExtension/COMSPECExtension.csproj -o /tmp/link-managed
cp -r tools/send-backoff-check /tmp/send-backoff-check && cd /tmp/send-backoff-check
dotnet build SendBackoffCheck.csproj -c Release
BACKOFF_DLL=/tmp/link-managed/COMSPECExtension_x64.dll dotnet bin/Release/net8.0/SendBackoffCheck.dll
```

Sortie : une ligne `PASS` ou `FAIL` par contrôle ; code de sortie 0 si tout passe, 1 sinon.
