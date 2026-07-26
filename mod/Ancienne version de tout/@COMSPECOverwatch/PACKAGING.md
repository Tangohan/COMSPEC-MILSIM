# Packaging & diffusion — COMSPEC Overwatch

Checklist pour **ne jamais** publier les sources de travail sur le Steam Workshop.

## Artefacts autorisés (Workshop / zip public)

| Élément | Emplacement dans le pack |
|---------|--------------------------|
| `mod.cpp` | racine |
| `meta.cpp` | racine (si présent) |
| `CREDITS.md` | racine (crédits + licences) |
| `main.pbo`, `connect.pbo`, `atak_athena.pbo` (optionnel) | `addons/` |
| `*.bisign` | `addons/` (si signature BI) |
| `*.bikey` | `keys/` (clé **publique** seulement) |
| `COMSPECExtension_x64.dll` | racine (Native AOT, typiquement ~5 Mo) |
| Logo optionnel (`gotak.png`, etc.) | racine ou `addons/` si référencé |

## Interdits (ne jamais uploader)

- Dossier `addons/connect/`, `addons/main/` ou `addons/atak_athena/` **décompressés** (`.sqf`, `.hpp`, `config.cpp`, `$PBOPREFIX$`)
- `mod/Sources/`, `mod/COMSPECExtension/` (`Extension.cs`, `obj/`, `bin/`)
- `net8.0/`, `*.pdb`, `*.exp`, `*.lib`, `*.deps.json`
- `.env`, secrets, `*.biprivatekey` (clé **privée** de signature)
- `STEAM_DESCRIPTION.md`, `docs/`, `SECURITY.md`, `PACKAGING.md`, `README.md` de build (texte Workshop = coller depuis STEAM_DESCRIPTION, pas shipper le .md)
- Stub DLL managé (~30–80 Ko) : ce n’est **pas** l’extension Native AOT

Le dossier de **développement** `@COMSPECOverwatch` contient volontairement les sources SQF à côté des PBO : c’est normal en local, **anormal** sur Steam.

## Commandes

### 1. Build (PBO + DLL)

```bat
cd mod
build_mod.bat
```

Ou manuellement : AddonBuilder sur `addons/main` et `addons/connect`, puis :

```bat
cd mod\COMSPECExtension
dotnet publish -c Release -r win-x64
```

Copier uniquement `COMSPECExtension_x64.dll` (pas le PDB) à la racine de `@COMSPECOverwatch`.

### 2. Pack Workshop propre

```powershell
cd mod
.\workshop-pack.ps1
# option archive :
.\workshop-pack.ps1 -Zip
```

Sortie : `mod/publisher/@COMSPECOverwatch/` — **c’est ce dossier** à donner au Publisher Arma / à zipper.

### 3. Vérification rapide

```powershell
Get-ChildItem .\publisher\@COMSPECOverwatch -Recurse -File |
  Where-Object { $_.Extension -match '\.(sqf|hpp|pdb|cs)$' -or $_.FullName -match 'net8\.0|Sources' }
# → doit ne rien retourner
```

Taille DLL attendue : plusieurs Mo (sinon mauvais artefact).

## Signature PBO (clés Bohemia)

Aucune clé `.bikey` / `.biprivatekey` n’est versionnée dans ce dépôt (volontaire).

Si vous signez vos addons :

1. Générer une paire avec **DSCreateKey** (Arma 3 Tools).
2. Signer après AddonBuilder :

```bat
"...\Arma 3 Tools\DSSignFile\DSSignFile.exe" "chemin\vers\votre.biprivatekey" "addons\connect.pbo"
"...\Arma 3 Tools\DSSignFile\DSSignFile.exe" "chemin\vers\votre.biprivatekey" "addons\main.pbo"
```

3. Distribuer uniquement le `.bikey` public dans `keys/` ; **jamais** le `.biprivatekey`.
4. Relancer `workshop-pack.ps1` (copie automatiquement `*.bisign` et `keys/*.bikey` s’ils sont présents).

La signature authentifie l’auteur pour les serveurs qui vérifient les clés ; elle **ne chiffre pas** et n’empêche pas l’unpack du PBO.

`build_log.txt` montre que AddonBuilder détecte déjà `DSSignFile` sur la machine de build : branchez la clé privée en local si vous activez la signature.

## Rebuild DLL sans fuite de chemin machine

Le binaire Native AOT peut contenir un chemin du type `E:\...\COMSPECExtension\...`. Pour le réduire :

- `COMSPECExtension.csproj` : `PathMap`, `ContinuousIntegrationBuild`, pas de PDB en Release ;
- republier avec `dotnet publish -c Release -r win-x64` ;
- ne **jamais** shipper les `.pdb`.

## Obfuscation SQF

**Non** utilisée : aucun outil d’obfuscation maintenu dans ce repo. Préférer déplacer toute logique sensible dans la DLL / le serveur Athena.

## Liens

- [SECURITY.md](SECURITY.md) — limites, licences, gate Athena  
- [CREDITS.md](CREDITS.md) — cTab GPL vs parties COMSPEC  
- [STEAM_DESCRIPTION.md](STEAM_DESCRIPTION.md) — texte à coller sur Workshop (ne pas l’inclure dans le pack)  
- [CHANGELOG.md](CHANGELOG.md) — historique des versions (dépôt ; coller le résumé « Nouveautés » sur Steam si besoin)  

