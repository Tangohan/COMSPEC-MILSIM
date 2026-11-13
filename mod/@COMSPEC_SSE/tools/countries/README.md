# Registre des pays des papiers

`countries.json` est la source du registre `CfgComspecCountries` du mod SSE (et la référence à reprendre côté site) : un objet
par pays, clé = **code ISO 3166 alpha-3** (`FRA`, `DEU`, `USA`, `GBR`, `BEL`, `AUT`…). Ces codes sont **stables** : ils
sont portés par chaque papier délivré (`country`) et par le champ `country` des profils d'IA. Les pays fictifs d'Arma gardent
leurs codes hors ISO (`ALT`, `TNA`, `LVN`, `MLD`).

```
python -I tools/countries/gen_countries.py          # écrit addons/identity/CfgComspecCountries.hpp, examples.json,
                                                     # docs/guides/pays_des_papiers.md (.en.md)
python -I tools/countries/gen_countries.py --check  # échoue si un fichier versionné diffère ou si le registre est invalide
python -I tools/countries/test_countries.py         # essais : spécimens OACI 9303, clés des numéros, registre à jour
python -I tools/countries/gen_country_assets.py     # gabarits PAA des pays (ImageToPAA), --png DIR pour les aperçus
```

## Champs d'un pays

| Champ | Contenu |
|---|---|
| `code`, `iso2`, `mrz` | alpha-3, alpha-2, code OACI de la MRZ (Allemagne « D ») |
| `tier` | `full` (pays complet : dessin propre, numéros à clé), `generic` (gabarit neutre, formats simples), `fictional` |
| `order` | ordre des listes (pays réels d'abord) |
| `name` | `{fr, en, native}` ; `short` (pays fictifs) |
| `zone`, `colors`, `flag` | style ; drapeau en simples bandes : `h`, `v`, `cross`, `nordic`, `canton`, `swiss` |
| `languages`, `language`, `speech` | langues ISO 639-1, nom de la langue `{fr, en}`, mots dits par l'IA (« Langue : locale ») |
| `dateFormat`, `height`, `currency` | `dd.mm.yyyy`, `mm/dd/yyyy`… ; `cm` ou `ftin` ; devise ISO 4217 |
| `phone`, `postal`, `address`, `streets`, `cities`, `regions`, `plate` | téléphone (plage de fiction quand elle existe, `note`), code postal, adresse (`{no} {street} {postal} {city} {R}`), rues, villes `[nom, code postal, région]`, régions (`name` ; États-Unis : `dl`, `id`, `plate`, `authority`, `area`), plaque |
| `numbers` | motif par type (`#` chiffre, `@` lettre, `*` alphanumérique, `&` jeu allemand, `{C}` clé OACI, `{R}` région, `{A}` indicatif, `{Y}` année de naissance) ou `scheme:<nom>` (fr_nir, be_rrn, be_idcard, us_ssn, nl_bsn, ch_ahv, at_svnr, it_cf, it_cie, es_dni, es_nie, pl_pesel, pl_idcard, gb_dln) ou `state` ; `personal` : numéro personnel du pays |
| `mrzCodes` | code du document dans la MRZ par type (`ID`, `IR`, `AR`…) |
| `docTypes`, `primary` | papiers délivrés (`id_card`, `passport`, `driving_license`, `residence_permit`, `vehicle_registration`, `military_id`) ; pièce courante d'un civil (Royaume-Uni, États-Unis : le permis) |
| `docNames`, `authorities`, `police`, `idCheck`, `labels` | noms des papiers `[langue, FR, EN]`, autorités par type (`{city}`, `{region}`, `state`), forces de l'ordre, phrase du contrôle, étiquettes des champs dans la langue du pays |
| `validity` | années par type (0 : sans expiration) |
| `first`, `firstF`, `last`, `lastF`, `lastFSuffix` | prénoms, noms, noms au féminin alignés (polonais, tchèque…) ou terminaison |
| `maps` | cartes (`worldName` en minuscules) qui choisissent ce pays en « Auto » |

Tout est fictif : personnes inventées, NIR au rang 000, SSN en 9xx, plages de téléphone réservées à la fiction ou blocs
improbables (indiqué dans `phone.note`). Gabarits : dessins originaux, aucun modèle officiel ni emblème, bandeau
SPÉCIMEN · SIMULATION.

## Ajouter un pays depuis une extension

Un addon qui dépend de `comspec_sse_identity` déclare sa classe dans `class CfgComspecCountries` de son `config.cpp`, avec
les mêmes champs que les classes de `addons/identity/CfgComspecCountries.hpp` (copier par exemple `class FRA` et changer les
valeurs). Le pays apparaît dans toutes les listes (réglages, Eden, atelier IA, sélecteur de document) ; sans gabarit propre,
ses papiers prennent les gabarits neutres `bare` avec titre et étiquettes posés en jeu.
