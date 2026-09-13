# Pack Overwatch — PBO en retard après merge PR #511

## Contexte

13 septembre 2026. Après le merge de la PR #511 (ECOTI / liaison), le dépôt affichait des sources Athena **1.0.115** / Overwatch **1.5.68**, mais le pack jouable livrait encore d’anciens PBO.

## Symptôme

Le téléphone / Overwatch en jeu se comporte comme une version antérieure (fonctions absentes, correctifs messagerie / auth non visibles), alors que le code source du dépôt est déjà plus récent. Impression de « régression du mod ».

## Cause

Lors de la résolution de conflits du merge `e89e8ac7d` (PR #511), les **sources** et la **DLL** ont été prises côté main (1.5.68 / 1.0.115), tandis que les **PBO** `connect.pbo` et `atak_athena.pbo` ont été conservés depuis la branche ECOTI (**1.5.66** / **1.0.113**).

Exemple : `fn_deleteChatChannel` / `fn_athena_commsDeleteChannel` présents dans les sources, absents des PBO livrés.

## Correctif

Rebuild AddonBuilder depuis `mod/UptoDate/Sources/comspec-overwatch-addons` vers `mod/UptoDate/@COMSPECOverwatch/addons` :

- `connect.pbo` → **1.5.68** (contient `deleteChatChannel`)
- `atak_athena.pbo` → **1.0.115** (contient `commsDeleteChannel`)

La recompilation Native AOT de la DLL a échoué ici (linker C++ Visual Studio manquant) ; la DLL déjà présente dans le pack (post-merge) a été conservée.

## Fichiers touchés

- `mod/UptoDate/@COMSPECOverwatch/addons/connect.pbo`
- `mod/UptoDate/@COMSPECOverwatch/addons/atak_athena.pbo`
- `mod/UptoDate/@COMSPECOverwatch/addons/main.pbo`
- `mod/UptoDate/@COMSPECOverwatch/addons/mavik_compat.pbo`
- `mod/UptoDate/@COMSPECOverwatch/addons/sse_ace.pbo`
- `docs/bugs/2026-09-13-pbo-regression-merge-pr511.md`

## Vérification

1. Extraire `versionStr` des PBO : `1.5.68` et `1.0.115`.
2. Confirmer la présence de `deleteChatChannel` / `commsDeleteChannel` dans les binaires.
3. Copier le pack vers le dossier réellement chargé par Arma (souvent `!Workshop\@COMSPECOverwatch`), quitter Arma complètement, relancer.

## Statut

corrigé — PBO rebuild + déployé Workshop (`3684656708` / `@COMPSECOverwatch`) et FN (`3026491672` / `@# S.O.A.R - FN`) le 13/09/2026 ~20:38
