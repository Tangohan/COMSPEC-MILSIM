# SSE — identité visible en jeu mais absente d’Identités (Athena)

## Contexte

Terminal SSE terrain : fiche `Mustafa Najjar` / `SSE-35-000001`, statut `ONLINE`.
Page Athena Identités : `000` fiches.

## Symptôme

L’opérateur croit que l’identité « remonte » parce qu’elle est affichée dans le terminal
et que la liaison est ONLINE. Le registre web reste vide.

## Cause

1. **Affichage local ≠ transmission** — le terminal lit les données générées sur l’entité
   (`ensureGenerated` / sections identity). Aucune écriture Athena tant que
   **TRANSMETTRE** n’a pas réussi un `POST /api/sse/persons`.
2. **Faux positif d’envoi** — `sendViaOverwatch` traitait un échec extension
   (`["ERROR",…]`) puis tombait sur `sendIntel` (texte HUMINT), renvoyait `true`
   → hint « fiche transmise » **sans** créer de ligne `sse_persons`.
3. (Secondaire) la page Identités liste `context_id = 1` ; une fiche créée avec un autre
   `mapId` n’apparaîtrait pas même si le POST réussit.

## Correctif

- `fn_sendViaOverwatch.sqf` : parse `["OK",…]` ; pour `PERSON` / `SubmitSsePerson`
  **pas** de fallback `sendIntel` qui ment sur le succès.
- Rebuild PBO `network` + redéploiement Workshop / `@COMSPEC_SSE`.

## Vérification

1. Cibler l’entité → Terminal → **TRANSMETTRE**.
2. Hint « transmise » seulement si l’extension répond `OK` (sinon QUEUED / échec).
3. Recharger `/atak/sse/personnes` : fiche visible (mapId 1 / contexte attendu).
4. En cas d’échec : vérifier token Overwatch, tenant, API `/api/sse/persons` (401/503).

## Statut

corrigé côté mod (à rebuild / déployer) — diagnostic confirmé
