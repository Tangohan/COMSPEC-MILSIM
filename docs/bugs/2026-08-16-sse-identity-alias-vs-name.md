# SSE — identité remontée : nom en alias, FALCON perdu

## Contexte

Journal jeu (`SSE-35-000001`) : indication terrain « FALCON », transmission digitale OK.
Fiche Athena `IDN-00001` : titre « Khalil Jawadi », **Alias = Khalil Jawadi**,
**Nom / prénom = —**, photo absente, confiance ~51 %.

## Symptôme

L’opérateur voit un alias terrain en jeu, mais Athena affiche le nom généré comme
alias et laisse la section identité déclarée quasi vide.

## Cause

Deux stocks d’identité non reliés :

1. `@COMSPEC_SSE` génère `identity.name` + `identity.alias` (ex. Khalil Jawadi / FALCON).
2. Le terminal SEEK (Overwatch) ne lit que `COMSPEC_SSE_LastName|FirstName|Alias`,
   sinon tombe sur `name _unit` **entièrement collé dans le champ alias** du POST
   `/api/sse/persons`.

Résultat : `first_name` / `last_name` vides, `alias` = nom affiché, FALCON jamais
envoyé. La photo absente est un autre bug (captures / `file_not_found`).

## Correctif

- `fn_syncIdentityBridgeVars.sqf` : miroir section → variables Eden après génération /
  `setIdentity`.
- `fn_generateData` / `fn_setIdentity` : appellent le pont.
- `fn_ssePersonDialogOnLoad` / `Submit` : lisent la section SSE si besoin ; découpent
  le nom en prénom/nom au lieu de tout mettre en alias.

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/core/functions/fn_syncIdentityBridgeVars.sqf`
- `mod/@COMSPEC_SSE/addons/core/functions/fn_setIdentity.sqf`
- `mod/@COMSPEC_SSE/addons/core/config.cpp`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_generateData.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ssePersonDialogOnLoad.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ssePersonDialogSubmit.sqf`

## Vérification

1. Rebuild PBO `core` + `generator` (`@COMSPEC_SSE`) et `connect` (Overwatch).
2. Générer / inspecter un sujet → journal « connu sous « ALIAS » ».
3. Terminal SEEK → page Sujet : prénom/nom remplis, alias = indicatif terrain.
4. Transmettre → Athena : Nom/prénom + Alias corrects (pas le nom en alias seul).

## Statut

corrigé côté mod (à rebuild / déployer) — fiches déjà créées à corriger à la main ou
re-transmettre après rebuild
