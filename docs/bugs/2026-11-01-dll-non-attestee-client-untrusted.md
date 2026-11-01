# Connexion impossible en jeu : « Ce module n'est pas reconnu » (CLIENT_UNTRUSTED)

## Contexte
ATAK 2.9.22 puis 2.9.23 publiés le 01/11/2026, DLL COMSPEC Link 2.0.112.

## Symptôme
Toute connexion en jeu échoue (code, e-mail, Steam, session reprise) : « Athena n'a pas reconnu le compte qui a autorisé le
code. [HTTP_401] ». Journal : `Connexion Steam — ERR|CLIENT_UNTRUSTED`. Le serveur répond `client_untrusted`.

## Cause
La DLL a été compilée dans un worktree git séparé où `mod/UptoDate/COMSPECExtension/ClientAttestMaterial.Local.cs` (secret
d'attestation, ignoré par Git) n'existait pas : la DLL publiée ne signait pas ses requêtes, Athena les refusait toutes.

## Correctif
DLL recompilée avec le fichier, 2.9.23 republiée sur Steam. `build_mod.bat` s'arrête désormais si le fichier manque.

## Fichiers touchés
`mod/COMSPEC_ATAK_Native/build_mod.bat`, DLL `COMSPECExtension_x64.dll`

## Vérification
En jeu, après mise à jour Steam : connexion par Steam / code / e-mail OK ; plus de `CLIENT_UNTRUSTED` dans le journal.

## Statut
corrigé (republication 2.9.23 du 01/11/2026)
