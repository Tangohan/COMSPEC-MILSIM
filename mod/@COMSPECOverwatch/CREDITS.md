# Crédits & sources — COMSPEC Overwatch

Ce document liste les projets dont COMSPEC Overwatch s’inspire ou dont il réutilise des idées / patterns (tablette, liaison téléphone, extension native, UX).

Les sources de référence utilisées en développement sont également présentes dans le dépôt sous `mod/Sources/` (**ne jamais** les publier sur le Workshop — voir [PACKAGING.md](PACKAGING.md) et [SECURITY.md](SECURITY.md)).

## Licence — lire attentivement

| Partie | Licence / statut |
|--------|------------------|
| Code / idées **dérivés de cTab** (et éditions liées : ctav-b2, concepts SIT / cTab IRL) | **GNU GPL v2** — obligations de copyleft sur ces parties (source, notices, redistribution compatible GPL) |
| Extension native `COMSPECExtension`, intégration Athena, assets UI / marque **COMSPEC originaux** (non dérivés de cTab) | Propriété COMSPEC — redistribution du wrapper non autorisée hors accord |
| CBA A3 (dépendance) | GPL (voir dépôt CBA) |

La GPL sur les parties dérivées cTab **n’autorise pas** à republier le branding / la glue Athena COMSPEC comme « vôtre » sans respect des notices ; inversement, revendiquer une licence propriétaire sur le seul wrapper **ne retire pas** les obligations GPL sur le code dérivé de cTab. Voir SECURITY.md.

## Athena / COMSPEC

| Élément | Lien |
|--------|------|
| Portail Athena | https://athena.ttrd.fr/public |
| Auteur du mod | COMSPEC |

## CBA A3 (dépendance requise)

| Élément | Détail |
|--------|--------|
| Projet | Community Base Addons (CBA_A3) |
| Licence | GPL (voir dépôt CBA) |
| Steam Workshop | https://steamcommunity.com/sharedfiles/filedetails/?id=450814997 |
| GitHub | https://github.com/CBATeam/CBA_A3 |

## cTab / cTab+ (inspiration UI & concepts tablette)

Dossier local : `mod/Sources/cTab-master`

| Élément | Détail |
|--------|--------|
| Créateur d’origine | Riouken |
| Maintenance / cTab+ | jetelain (GrueArbre) et contributeurs |
| Licence | **GNU GPL v2** (`LICENSE` dans le dépôt) |
| GitHub | https://github.com/jetelain/cTab |
| Steam (cTab 1erGTD / cTab+) | https://steamcommunity.com/workshop/filedetails/?id=2262006564 |
| Forum BI (historique) | http://forums.bistudio.com/showthread.php?166488 |
| Remerciements détaillés | voir `AUTHORS.md` du dépôt cTab |

## cTab NSWDG Edition / ctav-b2 (référence visuelle tablette & téléphone)

Dossier local : `mod/Sources/ctav-b2`  
(PBO signé `Fredipedia` — édition visuelle NSWDG de cTab.)

| Élément | Détail |
|--------|--------|
| Édition visuelle | Fredipedia (cTab NSWDG Edition) |
| Base | cTab (Riouken / Gundy), éditions intermédiaires (ex. IceBadger) |
| Licence d’origine cTab | GNU GPL v2 (pas de LICENSE séparée dans ce dossier local) |
| Steam Workshop | https://steamcommunity.com/sharedfiles/filedetails/?id=2511318948 |

### Assets repris dans `@COMSPECOverwatch` (GPL v2 — crédits obligatoires)

Textures / modèles copiés depuis le Workshop NSWDG (`Addons/cTab/img` + `data`), renommés avec préfixe `comspec_*` pour éviter les collisions avec le mod cTab :

| Emplacement Overwatch | Contenu |
|----------------------|---------|
| `addons/connect/img/device/` | Cadres téléphone / tablette / microDAGR / TAD, icônes (batterie, signal, mail, BFT…) |
| `addons/connect/data/device/` | Modèles 3D items (`itemandroid`, `itemdk10`, `itemmicrodagr`) + textures / rvmat |

Ces assets **ne sont pas originaux COMSPEC** : ils dérivent de cTab / cTab NSWDG Edition. Toute redistribution du mod doit conserver cette mention et respecter la GPL v2.

## SIT 1erGTD / cTab IRL (inspiration liaison mobile & extension)

Dossiers locaux : `mod/Sources/@SIT 1erGTD`, `mod/Sources/_sit_extract`

| Élément | Détail |
|--------|--------|
| Auteur | GrueArbre (jetelain) / 1er GTD |
| Nom Workshop historique | SIT 1erGTD (cTab IRL) |
| Successeur annoncé | cTAB Connect [BETA] |
| Licence d’origine (projet cTab) | GNU GPL v2 |
| Steam (SIT / cTab IRL) | https://steamcommunity.com/sharedfiles/filedetails/?id=2262009445 |
| Steam (cTAB Connect BETA) | https://steamcommunity.com/sharedfiles/filedetails/?id=3438247879 |
| Site connexion | https://ctab.plan-ops.fr/ |
| GitHub (projet cTab) | https://github.com/jetelain/cTab |
| Site auteur | https://pmad.net/ |
| Unité 1er GTD | https://www.1ergtd.fr/ |

`meta.cpp` du dossier SIT local indique `publishedid = 2262009445` et `mod.cpp` pointe vers https://github.com/jetelain/cTab.

## Autres

- **Bohemia Interactive** — Arma 3 et outils de modding.
- **ACE3** (optionnel selon missions) — https://steamcommunity.com/sharedfiles/filedetails/?id=463939057

## TODO (liens à confirmer si besoin)

- [ ] Remplacer l’URL générique GitHub du `mod.cpp` par le dépôt public du projet COMSPEC / Athena, dès qu’il est publié.
- [ ] Si une licence distincte est fournie pour l’édition NSWDG (Fredipedia) hors GPL cTab, l’ajouter ici.
