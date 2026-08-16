# SSE — extension `["",0,0]` + faux OK sendIntel

## Contexte

Journal technique SSE : `SendSSE` / `SubmitSseBiometricsSim` → `["",0,0]` puis
`sendViaOverwatch OK via sendIntel`.

## Symptôme

Transmission SEEK affichée OK côté journal, mais **pas de fiche Identités Athena**
(et biométrie / numérique non enregistrés correctement).

## Cause

1. Arma 2.18+ : `callExtension` renvoie `["texte", code, err]`. `fn_extensionCall`
   ne déballait pas le tableau → `_extOk` échouait toujours.
2. `SendSSE` **absent** de la DLL → retour vide `""` → log `["",0,0]`.
3. `SubmitSseBiometricsSim` côté DLL exigeait 2 args ; COMSPEC_SSE n’envoyait que le JSON.
4. Fallback `sendIntel` = texte HUMINT seulement (commentaire déjà présent dans le code).

## Correctif

- Unwrap `callExtension` dans `fn_extensionCall.sqf`
- DLL : `SendSSE` + biométrie 1 ou 2 args + extraction `person_id`
- Mémorisation `comspec_sse_athenaPersonId` après fiche personne
- Pas de fallback `sendIntel` pour PERSON / BIOMETRICS / DIGITAL / SendSSE

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/network/functions/fn_extensionCall.sqf`
- `mod/@COMSPEC_SSE/addons/network/functions/fn_sendViaOverwatch.sqf`
- `mod/@COMSPEC_SSE/addons/network/functions/fn_submitPersonRecord.sqf`
- `mod/@COMSPEC_SSE/addons/network/functions/fn_submitBiometricsSim.sqf`
- `mod/UptoDate/COMSPECExtension/Extension.cs`

## Vérification

1. `dotnet publish` AOT → DLL &gt; 1 Mo dans `@COMSPECOverwatch`
2. Rebuild PBO `network` / `@COMSPEC_SSE`
3. Quitter Arma, relancer, transmettre SEEK
4. Journal : `sendViaOverwatch OK ext SubmitSsePerson` (pas `via sendIntel`)
5. Module Personnes Athena : fiche visible

## Statut

Corrigé — rebuild + relance requis
