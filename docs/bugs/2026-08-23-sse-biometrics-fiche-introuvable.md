# SSE — biométrie « Fiche introuvable » après une fiche OK

## Contexte

Journal terrain du 23/08 ~04:28–04:41 : la fiche personne part (`SubmitSsePerson` → OK, id 4) puis la biométrie échoue en boucle. La file offline reste à 3 éléments.

## Symptôme

- `SubmitSseBiometricsSim` → HTTP 404 `{error: not_found, message: Fiche introuvable.}`
- Log `names=//` (payload biométrie sans nom)
- `flushQueue sent=0 remaining=3`
- Immédiatement après `SubmitSsePerson → ["OK","4"]` pour PrenomUlu / NomZul

## Cause

1. Le terminal envoie la fiche avec `biometrics_simulated: true`. La colonne est donc déjà à 1.
2. L’appel suivant `POST /persons/{id}/biometrics-sim` fait `UPDATE … SET biometrics_simulated = 1`.
3. MySQL/PDO `rowCount()` vaut **0** si la valeur ne change pas. Le serveur traitait ça comme « fiche introuvable ».
4. Les rejeux sans id Athena numérique (UID `SSE-35-…` ou JSON passé comme id) aboutissaient au même 404.

## Correctif

- Marquer la simulation seulement si la fiche existe ; un UPDATE sans changement n’est plus une erreur.
- N’accepter qu’un identifiant Athena **numérique**.
- Propager l’id 4 vers les biométries encore en file.

## Fichiers touchés

- `app/Repositories/SsePersonRepository.php`
- `app/Controllers/Api/SseApiController.php`
- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/@COMSPEC_SSE/addons/network/functions/fn_sendViaOverwatch.sqf`
- `mod/@COMSPEC_SSE/addons/network/functions/fn_submitPersonRecord.sqf`

## Vérification

1. Déployer le portail (correctif PHP).
2. Rebuild SSE 0.7.11 + DLL Overwatch 2.0.4, **quitter Arma**.
3. Transmettre une identité avec biométrie → plus de 404 ; la fiche au registre affiche le relevé.

## Statut

Corrigé (à valider in-game après déploiement web + relance Arma).
