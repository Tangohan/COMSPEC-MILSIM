# ICD SSE : numéro DSE (Dossier Synchronisé Eden)

Contrat site et mod @COMSPEC_SSE pour retrouver une donnée SSE, et l'IA reliée, à partir de son seul numéro DSE. Côté site : livré. Côté mod : à faire (PR native séparée).

## Le numéro DSE
`DSE-` + 5 à 8 caractères de `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` (jamais 0, O, 1, I), actuellement 6. Unique par communauté, toutes données confondues, jamais réutilisé ni modifié. La saisie est tolérante (casse, espaces, préfixe facultatif) ; la réponse donne toujours la forme canonique.

## Route
`GET /api/sse/v1/dse/{dse}`

Authentification (l'une des deux) :
- clé API du jeu (en-tête habituel du mod) : communauté de la clé ; la clé est le canal de confiance du serveur de jeu ;
- session web : communauté de la session ; l'utilisateur doit pouvoir lire la section SSE de la donnée (et, pour fiches et documents, le dossier auquel elle est rattachée).

Limite de débit : 30 requêtes par minute et par acteur (clé ou compte), puis 429 (`Retry-After`).
Journal : chaque appel est inscrit dans `sse_audit_log` (action `dse.resolve`, trouvé ou non, DSE canonique ou `invalid`).

## Réponse 200
```json
{
  "dse": "DSE-K7M4QX",
  "entity": {"type": "person", "id": 42, "label": "Fiche personne ID-2026-0042", "url": "/atak/sse/identites/42"},
  "identifiers": {"id_number": "ID-2026-0042", "codename": "FAUCON-12", "sheet_name": "Fiche personne ID-2026-0042"},
  "ai_profile": {"id": 7, "label": "Garde du pont", "side": "civ", "category": "civilian", "identity_id": 31}
}
```
`entity.type` : `person`, `identity`, `field_note`, `report`, `document`. `ai_profile` vaut `null` si aucune IA n'est reliée (ou si le profil est archivé). `identity_id` peut être `null`.

## Erreurs
| Code | Corps | Cas |
|---|---|---|
| 401 | `{"error":"unauthorized"}` | ni clé valide ni session |
| 403 | `{"error":"tenant_context_required"}` | communauté non déterminée |
| 404 | `{"error":"not_found"}` | DSE mal formé, inconnu, d'une autre communauté, donnée supprimée ou illisible pour l'appelant : **réponse identique** |
| 429 | | débit dépassé |

## Reste à faire côté mod (hors de cette PR)
- Appel de la route depuis la DLL (même canal que `POST /api/sse/identity/checks`) et saisie / affichage du DSE dans Zeus / Eden.
- Rattacher l'IA incarnée à `ai_profile.id` et appliquer le profil (camp, catégorie, comportement, kit).
- Gérer 404 (DSE inconnu), 429 (attente) et 401.
- À tester en jeu : résolution d'un DSE valide, inconnu, d'une autre communauté ; débit.
