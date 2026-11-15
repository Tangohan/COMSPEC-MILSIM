# ICD-SSE-CATALOGUE-001 : catalogue du mod SSE

Les listes que le mod propose (SEEK II, modules Zeus, attributs Eden) viennent du site de la communauté au lieu d'être
figées dans le mod. Un seul paquet, en lecture seule. Si le site ne répond pas, le mod garde ses listes locales.

## Lecture

| | |
|---|---|
| Route | `GET /api/game/v1/sse/catalogue` |
| Voie du mod (COMSPEC Link 2.0.125, sans nouvelle DLL) | `SubmitIdCheck` → `POST /api/sse/identity/checks`, corps `{"kind":"sse_catalogue","map":1,"op":"OP-…","rev":"…","page":0}` |
| Authentification | clé de communauté ou jeton de jeu (les mêmes en-têtes que les autres routes du jeu), ou session du portail avec l'habilitation SSE |
| Communauté | celle de la clé ou de la session. Jamais un paramètre : `tenant_id` et `tenant_slug` sont ignorés |
| Débit | 90 lectures par minute par communauté et par compte (429, `Retry-After`), en plus de la règle générale des `GET /api/*` |
| Cache serveur | paquet gardé 30 s par communauté, carte, opération et compte |

Paramètres de la route : `map` (carte, 1 par défaut), `op` (code d'opération, pour la section `lore`), `sections`
(liste séparée par des virgules), `since_rev` (révision connue), `format=sqf` et `page` (forme paginée ci-dessous).

## Réponse JSON

```json
{
  "version": 1,
  "revision": "54afc686882338ee",
  "generated_at": "2026-11-15T12:14:56Z",
  "ttl": 600,
  "map": 1,
  "op": "OP-KAVALA",
  "lore_session": {"id": 1, "op": "OP-KAVALA", "title": "Soirée du 15"},
  "sections": { "contexts": [{"code": "cordon_fouille", "fr": "Cordon et fouille", "en": "Cordon and search", "group": "…"}] },
  "truncated": ["cases"]
}
```

- `revision` : empreinte du contenu. Elle ne change que si une liste change. En-tête `ETag: W/"ssecat-<révision>"` ;
  `If-None-Match` → 304 ; `since_rev` égal → `{"unchanged": true, …}` sans les sections.
- `ttl` : durée de validité conseillée au client, en secondes.
- `truncated` : listes coupées à leur plafond.

| Section | Colonnes (ordre de la forme paginée) | Source | Plafond |
|---|---|---|---|
| `contexts` | code, fr, en, group | contextes d'acquisition actifs de la communauté (`SseAcquisitionContexts`) | |
| `statuses` | code, fr, en | statuts d'une personne | |
| `pieces` | code, fr, en | catégories de pièces saisies | |
| `supports` | code, fr, en | types de supports numériques | |
| `site_types` | code, fr, en | types de sites | |
| `documents` | code, fr, en | papiers intégrés au mod | |
| `mentions` | code, fr, en | mentions aux fichiers | |
| `threats` | code, fr, en | niveaux de menace d'un avis | |
| `notice_types` | code, fr, en | types d'avis de la communauté | |
| `cases` | id, ref, name | dossiers ouverts, diffusion interne, hors classeurs | 80 |
| `sites` | id, ref, name, type, case | sites ouverts de la carte | 80 |
| `campaigns` | key, name | campagnes actives | 40 |
| `wanted` | id, ref, name, level, type | avis de recherche actifs | 80 |
| `lore` | uid, ref, name | personnes de la session de lore de `op` | 150 |

Les opérations en cours ne sont pas dans le catalogue : `GET /api/game/v1/operations` demande une session de jeu
personnelle, pas une clé de communauté.

## Forme paginée (parseSimpleArray)

Réponse `{"id": <entier>, "ref": "<page>", "page": 0, "next": 1, "pages": 3}`. COMSPEC Link rend `OK|id|ref` ; `ref` fait
7 400 octets au plus et se lit avec `parseSimpleArray` :

```
[version, "révision", "généré le", ttl, page, page suivante (0 = fin),
 [["section", [[colonne, …], …]], …],
 ["sections tronquées"], [id session, "op", "titre"] ou [],
 true si la révision envoyée (rev) est encore la bonne : aucune section n'est renvoyée]
```

Une section peut être répartie sur deux pages : le client ajoute les lignes. Une section vide est annoncée, pour que le
client vide sa liste. Si la révision change entre deux pages, le client abandonne et recommence plus tard. Dans un
texte, `"` est doublé et `|` est écrit `\p`. Douze pages au plus.

## Ce que la clé du jeu reçoit

Des identifiants, des références et des noms affichés, déjà donnés par d'autres routes du jeu à la même clé :

- dossiers : seulement ceux de diffusion interne, ouverts. Ni résumé, ni dossier d'un niveau supérieur. Le dossier d'un
  site n'est nommé que s'il est lui-même dans la liste ;
- avis de recherche : référence, nom, niveau, type. Ni motif, ni consigne, ni photo, ni zone ;
- lore : identifiant d'IA, référence du registre, nom. Le nom suit la règle de la redescente du lore
  (ICD-SSE-LORE-001) : donné s'il est connu des fichiers à la phase en cours, ou si le compte relié a le droit de lire le
  caché MJ ; sinon vide. Aucune date de naissance, nationalité ni adresse ;
- campagnes : clé et nom, jamais le contenu.

## Côté mod

- Cache : `comspec_overwatch_connect_fnc_sseCatalogue` (ATAK natif). Lecture au début de la mission, puis à l'ouverture
  d'un écran si la liste a plus de `ttl` secondes ; bouton ACTUALISER ; pas de nouvel essai avant 60 s après un échec.
- Repli : les référentiels de la dernière lecture sont gardés sur le poste ; à défaut, les listes du mod. Les listes
  vivantes (dossiers, sites, avis, lore, campagnes) ne sont jamais gardées d'une partie à l'autre.
- Chaque écran écrit l'origine de sa liste (site et heure de synchro, poste, ou mod). Aucune alerte.
- Pack SSE : `comspec_sse_fnc_catalogue`, ligne `CATALOG` de `comspec_sse_fnc_uiForm`, `comspec_sse_fnc_edenCatalogue`.
- Eden : pas de partie en cours, donc la liste n'est lue que si la connexion Athena du menu COMSPEC SSE est ouverte sur
  le poste. Sinon l'attribut garde la valeur enregistrée, appliquée par le serveur au lancement de la mission.

## Retour vers le site

Rien de nouveau côté routes. La fiche personne (`POST /api/sse/persons`, file hors ligne du mod, clé d'idempotence
inchangée) porte les choix faits dans les listes : `circumstances` (contexte), `case_reference` (dossier), `wanted_ref`
(avis associé, gardé au journal de transmission).
