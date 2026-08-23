# SSE — acquisition numérique rejetée (`not_a_tactical_report`)

## Contexte

Même journal que la biométrie 404 : l’extraction téléphone / ordinateur du terminal SEEK n’arrivait pas au laboratoire.

## Symptôme

- `SendSSE` → `ERROR not_a_tactical_report`
- `submitDigitalAcquisition uid=SSE-35-000001 ok=false`
- File offline `kind=DIGITAL`

## Cause

`SendSSE` n’accepte qu’un JSON avec `report_type` (rapports tactiques). L’acquisition numérique n’en a pas (`category: digital`, résumé téléphone / ordinateur). Elle était donc rejetée pour éviter un rapport « autre » vide.

## Correctif

- Commande `SubmitSseDigital` vers le laboratoire numérique.
- `SendSSE` reconnaît encore les anciens paquets `category=digital` (file offline).
- Enregistrement : support + saisie + acquisition, sans inventer de fausses données de démo.

## Fichiers touchés

- `app/Services/Sse/SseDigitalLabService.php`
- `app/Controllers/Api/SseApiController.php`
- `app/Repositories/SseDigitalLabRepository.php`
- `routes/web.php`
- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/@COMSPEC_SSE/addons/network/functions/fn_submitDigitalAcquisition.sqf`
- `mod/@COMSPEC_SSE/addons/network/functions/fn_sendViaOverwatch.sqf`

## Vérification

1. Déployer le portail.
2. Relancer Arma avec DLL 2.0.4 + SSE 0.7.11.
3. Extraire un téléphone puis transmettre → plus d’erreur `not_a_tactical_report` ; le support apparaît au laboratoire numérique.

## Statut

Corrigé (à valider in-game après déploiement web + relance Arma).
