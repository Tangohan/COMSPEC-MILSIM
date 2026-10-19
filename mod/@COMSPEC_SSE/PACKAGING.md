# Packaging & diffusion — COMSPEC SSE (atelier)

Checklist pour **ne jamais** publier les sources de travail sur le Steam Workshop.

Même esprit que Overwatch (`mod/UptoDate/workshop-pack.ps1`) : un dossier `publisher/@COMSPEC_SSE` propre, sans SQF nu.

## Artefacts autorisés (Workshop)

| Élément | Emplacement dans le pack |
|---------|--------------------------|
| `mod.cpp` | racine |
| `meta.cpp` | racine (créé par le Publisher à la **première** mise en ligne — ne pas inventer d’identifiant) |
| `CREDITS.md` | racine |
| `CHANGELOG.md` | racine (copie depuis `docs/CHANGELOG.md`) |
| `comspec_sse_*.pbo` | `addons/` |
| `*.bisign` | `addons/` (si signature BI) |
| `*.bikey` | `keys/` (clé **publique** seulement) |
| `logo.paa`, `logoSmall.paa` | racine |

## Interdits (ne jamais uploader)

- Dossiers `addons/main/`, `addons/core/`, etc. **décompressés** (`.sqf`, `.hpp`, `config.cpp`, `$PBOPREFIX$`)
- `docs/` (guides d’atelier) — coller le texte Workshop depuis `STEAM_DESCRIPTION.md`, ne pas shipper le dossier
- `missions/`, `tools/`, `build_log.txt`, `build_mod.bat`, `build_pbo.bat`, `workshop-pack.ps1`
- `STEAM_DESCRIPTION.md`, `PACKAGING.md`
- `.env`, secrets, `*.biprivatekey`

Le dossier de **développement** `@COMSPEC_SSE` contient volontairement les sources à côté des PBO : c’est normal en local, **anormal** sur Steam.

## Commandes

### 1. Build (PBO)

Prérequis : **Arma 3 Tools** (Steam), chemin par défaut :

`F:\SteamLibrary\steamapps\common\Arma 3 Tools\AddonBuilder\AddonBuilder.exe`

Adapter `BUILDER_PATH` en tête de `build_pbo.bat` si vos chemins diffèrent.

```bat
cd mod\@COMSPEC_SSE
set COMSPEC_BUILD_NOPAUSE=1
build_mod.bat
```

(`build_pbo.bat` est le même script ; `build_mod.bat` l’appelle.)

Option debug (PBO journal technique) :

```bat
build_pbo.bat debug
```

Log : `mod/@COMSPEC_SSE/build_log.txt`

Si AddonBuilder est absent, les PBO déjà présents dans `addons/` peuvent encore être assemblés par `workshop-pack.ps1`.

### 2. Pack Workshop propre

```powershell
cd mod\@COMSPEC_SSE
.\workshop-pack.ps1
# option archive :
.\workshop-pack.ps1 -Zip
```

Sortie : `mod/publisher/@COMSPEC_SSE/` — **c’est ce dossier** à donner au Publisher Arma 3.

Le script **ne copie pas** `docs/` (piège connu du pack Overwatch).

### 3. Vérification rapide

```powershell
Get-ChildItem .\..\publisher\@COMSPEC_SSE -Recurse -File |
  Where-Object { $_.Extension -match '\.(sqf|hpp|pdb|cs)$' -or $_.FullName -match 'docs|Sources|missions' }
# → doit ne rien retourner
```

## Dépendances Workshop

Tous les addons SSE déclarent `comspec_atak_native_main` dans `requiredAddons` (et `requiredVersion = 2.14`, via la macro `REQUIRED_VERSION` de `addons/main/script_mod.hpp`, recopiée par `tools/sync_headers.py`) : sans COMSPEC ATAK Native, Arma refuse de charger le SSE.

Sur la fiche Workshop du SSE (3790566777), **Required items** doit lister à la main : CBA A3, ACE3 et **COMSPEC ATAK Native (3684656708)**. Le Publisher ne le fait pas tout seul. COMSPEC Overwatch reste optionnel : ne pas l’ajouter.

## Signature PBO (optionnel)

Aucune clé n’est versionnée dans ce dépôt.

Si vous signez : DSCreateKey + DSSignFile (Arma 3 Tools), puis relancer `workshop-pack.ps1` (copie `*.bisign` et `keys/*.bikey`).

## Liens

- [docs/PUBLICATION.md](docs/PUBLICATION.md) — où cliquer dans le Publisher (chef de mission)
- [STEAM_DESCRIPTION.md](STEAM_DESCRIPTION.md) — texte à coller sur la fiche Workshop (ne pas l’inclure dans le pack)
- [docs/CHANGELOG.md](docs/CHANGELOG.md) — historique des versions

## Galerie Workshop

Images de la galerie Steam (1920 x 1080, JPEG < 1 Mo), dans la charte des pages Workshop COMSPEC (celle d'ATAK
Native) : `tools/make_workshop_gallery.py` (Python 3 + Pillow, police Bahnschrift de Windows).

```powershell
cd mod\@COMSPEC_SSE
python tools\make_workshop_gallery.py              # FR et EN
python tools\make_workshop_gallery.py --list       # diapositives, sources trouvées ou absentes
python tools\make_workshop_gallery.py --only seek --lang en
```

- **Entrées** : captures en jeu dans `docs/workshop/source/` (noms attendus et cadrage :
  [docs/workshop/CAPTURES_A_FAIRE.md](docs/workshop/CAPTURES_A_FAIRE.md)), visuels de la charte dans
  `public/assets/workshop/sse/{fr,en}/`, rendus des objets dans `tools/icons/png/`. Une diapositive sans source est
  sautée ; les autres sont produites.
- **Sortie** : `docs/workshop/gallery/{fr,en}/sse-NN-*.jpg` (`--out` pour changer). Jamais dans `mod/publisher/`
  (vidé par `workshop-pack.ps1` ; le script refuse ce dossier), jamais dans le pack (`docs/` n'est pas copié).
- **Textes** : liste `SLIDES` du script, FR et EN. N'y écrire que ce que le mod fait.
- **Envoi** : à la main, sur la page Steam › « Ajouter / modifier des images et vidéos », dans l'ordre des numéros
  (version FR ; les images EN servent à la traduction anglaise ou aux annonces). La description BBCode est dans
  [STEAM_DESCRIPTION.md](STEAM_DESCRIPTION.md). Ne rien publier sans demande.
- **Dépendance** : COMSPEC ATAK Native (3684656708) s'ajoute à la main comme « Required item » sur la page Steam.
