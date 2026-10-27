# Module copié accepté par le poste

## Contexte

Une copie du module, modifiée et sans crédit, continuait à parler au poste.

## Symptôme

N’importe quel programme qui présentait une clé d’accès de communauté était traité comme le module officiel : liaison, positions, téléphone.

## Cause

La clé d’accès prouve la communauté, pas le programme. Elle est remise au module au moment de la liaison, donc une copie qui l’a récupérée était acceptée.

## Correctif

Chaque requête du jeu doit porter une preuve calculée avec un secret connu seulement du pack officiel publié et du serveur (`COMSPEC_CLIENT_ATTEST_SECRET`). Sans ce secret, le comportement actuel est conservé pour ne pas couper les joueurs avant la publication du pack. Avec le secret, une copie ou un autre programme est refusé. Le portail (session du navigateur) n’a pas à présenter cette preuve.

Le secret n’est pas dans le dépôt. `tools/embed-client-attest.php` l’écrit dans `.env` et dans `ClientAttestMaterial.Local.cs` (ignoré par Git).

Limite : extraire le secret du pack publié permet de le réutiliser. On le change, on recompile, on republie.

## Fichiers touchés

- `app/Support/OfficialClientAttestation.php`
- `app/Support/ComspecApiKeyAuth.php`
- `mod/UptoDate/COMSPECExtension/OfficialClientProof.cs`
- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/COMSPECExtension/GameAuth.cs`
- Textes de liaison dans le module Overwatch

## Vérification

`php vendor/phpunit/phpunit/phpunit tests/Unit/OfficialClientAttestationTest.php`

## Statut

Corrigé. Actif seulement une fois le secret posé sur le serveur et le pack officiel publié.
