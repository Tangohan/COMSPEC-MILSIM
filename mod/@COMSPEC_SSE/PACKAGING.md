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

## Signature PBO (optionnel)

Aucune clé n’est versionnée dans ce dépôt.

Si vous signez : DSCreateKey + DSSignFile (Arma 3 Tools), puis relancer `workshop-pack.ps1` (copie `*.bisign` et `keys/*.bikey`).

## Liens

- [docs/PUBLICATION.md](docs/PUBLICATION.md) — où cliquer dans le Publisher (chef de mission)
- [STEAM_DESCRIPTION.md](STEAM_DESCRIPTION.md) — texte à coller sur la fiche Workshop (ne pas l’inclure dans le pack)
- [docs/CHANGELOG.md](docs/CHANGELOG.md) — historique des versions
