# Calques SSE des cartes : API et cartes branchées

Trois calques SSE sur les cartes web de la communauté : contrôles d'identité, lieux, pistes de téléphones. Ils sont
désactivés par défaut, servis en GeoJSON par une seule API, et dessinés par un seul module partagé
(`public/assets/js/sse-map-layers.js`) sur le poste TOC web, la carte ATAK web / Overwatch et TACMAP.

## Données réellement disponibles

| Calque | Source | Position | Temps | Limites |
| --- | --- | --- | --- | --- |
| Contrôles d'identité | `sse_id_checks` (envois du jeu, `POST /api/sse/identity/checks`) | `pos_x`, `pos_y` en mètres du monde Arma, `context_id` = carte | `created_at` (UTC) et heure de jeu | Seuls les contrôles reçus avec une position ; `(0, 0)` est ignoré |
| Lieux | `sse_sites` (sites exploités) | `pos_x`, `pos_y` en mètres du monde, `context_id` = carte | `created_at` | Seuls les sites avec position ; pas de filtre de période |
| Pistes de téléphones | `sse_phone_extraction_items`, catégorie `positions` des extractions visibles | Grille Arma (6, 8 ou 10 chiffres) convertie en mètres au centre de case | Heure de jeu `HH:MM` par position ; date de réception de l'extraction | Voir ci-dessous |

Ce qui manque, et que l'API ne fabrique pas :

- **Géolocalisation des téléphones de l'exploitation numérique** (`sse_digital_locations`, `sse_digital_artifacts.geo_*`) :
  ce sont des latitudes et longitudes de type monde réel, sans correspondance avec un théâtre Arma. Il n'existe aucune
  conversion de calibrage ; elles ne sont donc pas servies.
- **Carte d'origine des anciennes extractions** : la colonne `sse_phone_extractions.context_id` existait mais n'était pas
  remplie. Depuis cette version, elle l'est à chaque nouvel envoi. Les extractions plus anciennes n'ont pas de carte : elles
  ne sont jamais posées sur un théâtre au hasard, l'API les compte (`unmapped_extractions`) et la carte affiche « Aucune
  piste : les extractions existantes n'ont pas de carte d'origine. »
- **Date réelle de chaque position** : le téléphone du jeu n'envoie que l'heure de jeu (`HH:MM`). L'ordre des sommets est
  l'ordre de réception ; la date réelle est celle de l'extraction (`recorded_at`).
- **Grilles à 4 chiffres** : illisibles (comptées dans `unreadable_grids`).

## Endpoint

`GET /api/sse/v1/calques` : lecture seule, session de navigateur. Aucune écriture, donc pas de jeton CSRF.

| Paramètre | Valeur |
| --- | --- |
| `types` | `controles`, `lieux`, `pistes` séparés par des virgules. Vide : ne renvoie que les droits du lecteur (`meta.access`). |
| `carte` | Numéro de la carte du site (obligatoire si `types` n'est pas vide). |
| `periode` | `24h`, `7j`, `30j` (défaut), `tout`. |
| `depuis`, `jusqua` | Date `AAAA-MM-JJ` ou date-heure ISO 8601 (UTC si pas de fuseau). Remplacent `periode`. Écart maximal : 366 jours. |
| `bbox` | `xmin,ymin,xmax,ymax` en mètres du monde. Facultatif. |

Réponse : `FeatureCollection` GeoJSON.

- **Repère** : `crs.properties.name = "urn:comspec:crs:arma-world-m"`. Les coordonnées sont `[x, y]` en **mètres du monde
  Arma de la carte demandée** (pas du WGS84). Sur les cartes Leaflet du site, la position est `latlng = [y, x]` ; le
  décalage propre à chaque carte (poste TOC, carte ATAK) reste appliqué par la page, comme pour tous les autres calques.
- `Point` pour un contrôle (`properties.type = "controle"`) et un lieu (`"lieu"`).
- `LineString` pour une piste (`"piste"`), `properties.times` : un horodatage de jeu par sommet, dans l'ordre de réception.
  Pistes simplifiées (tolérance 12 m, 150 sommets au plus).
- `meta` : `version`, `map`, `period`, `access` (`controles`, `lieux`, `pistes`, `noms`) et, par type, `allowed`, `count`,
  `truncated`, `empty` (`none`, `no_extraction`, `no_position`, `no_movement`, `unmapped`, `forbidden`, `audit_unavailable`).

Plafonds : 500 contrôles, 500 lieux, 60 pistes par réponse ; `meta.types.<type>.truncated` l'indique. Cache court (20 s) par
communauté, carte, paramètres et droits du lecteur. 90 requêtes par minute et par compte.

Codes : `403 sse_access_required` (porte d'entrée SSE non franchie), `422` (paramètre invalide : `invalid_types`,
`invalid_map`, `invalid_bbox`, `invalid_window`), `429`, `503`.

## Sécurité

- La communauté vient de la session, jamais de la requête.
- Porte d'entrée : compte connecté (pas une session invitée), droit d'entrer au Bureau SSE sans code, confidentialité
  acceptée dans cette communauté. Les gardes sont appelées par un seul adaptateur,
  `App\Services\Sse\SessionSseMapLayerGate` (interface `SseMapLayerGate`), à remplacer par la politique d'accès par section
  sans toucher au reste.
- Un type refusé n'est pas interrogé. Un point de contrôle ne porte un nom (fiche personne ou identité ordinaire du
  registre) que si le lecteur a le droit sur les fichiers de personnes ; sinon, point anonyme « Contrôle ». Les identités
  d'emprunt (alias, couverture, faux) ne sont jamais nommées.
- Lire des pistes de téléphones est écrit dans le journal d'audit SSE (`map_layers.phone_tracks.read`, carte, extractions).
  Si le journal est indisponible, les pistes ne sont pas servies.
- Seules les extractions au statut `visible` sont lues.

## Cartes branchées

| Carte | Où | Remarques |
| --- | --- | --- |
| Poste TOC web | Bouton « Calques SSE » de la barre de la carte | Décalage du terrain via `TocMap.toLL` |
| ATAK web / Overwatch | Panneau « Calques », bloc « Calques SSE » | Décalage via `ATAKMap.latLngFromWorld` ; vue 3D non concernée |
| TACMAP | Bouton « Calques SSE » de la barre des calques | Cartes du jeu seulement ; fonds « monde » et « image » : non pris en charge |

Non branchées pour l'instant : l'ancien écran `/overwatch` (carte propre à la page), le lecteur d'images, l'ATAK mobile.

## Téléphone ATAK du jeu (suite, non branchée)

Le calque n'est pas branché dans la carte du téléphone du mod. Raisons : l'API est protégée par la session de navigateur et
la porte du Bureau SSE ; le téléphone du jeu s'authentifie avec la clé du jeu et le compte relié, sans engagement de
confidentialité de session ; et ce qu'un joueur voit sur son téléphone doit rester ce que son personnage peut savoir. Une
suite possible : un format léger `GET /api/atak/sse-calques` réservé aux rôles autorisés, mêmes `types`, `carte`, `bbox`,
sortie réduite à `[x, y, type, heure]`, avec les mêmes gardes, le même plafond et le même journal d'audit, puis un calque
optionnel désactivé par défaut dans `fn_pageMap.sqf` avec un réglage joueur. Cela demande une reconstruction du mod et une
publication Steam ; rien n'est modifié dans `mod/`.
