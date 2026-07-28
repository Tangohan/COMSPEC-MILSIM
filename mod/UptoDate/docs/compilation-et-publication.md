# Compilation & publication

Produire un pack **@COMSPECOverwatch** à jour (PBO + DLL).

---

## Prérequis machine

- **Windows** (build script batch)
- **.NET 8 SDK** — compilation extension
- **Arma 3 Tools** — `AddonBuilder.exe` (Steam)
- Chemins par défaut dans `build_mod.bat` :
  - Arma : `F:\SteamLibrary\steamapps\common\Arma 3`
  - Tools : `F:\SteamLibrary\steamapps\common\Arma 3 Tools\AddonBuilder\AddonBuilder.exe`

Adapter les variables en tête de `build_mod.bat` si vos chemins diffèrent.

---

## Build standard

```batch
cd mod\UptoDate
build_mod.bat
```

Étapes automatiques :

1. `dotnet publish` → **COMSPECExtension_x64.dll** (Native AOT, ~7–8 Mo)
2. AddonBuilder → **main.pbo**, **connect.pbo**, **atak_athena.pbo**, **mavik_compat.pbo**
3. Copie DLL + mod.cpp vers `@COMSPECOverwatch/`
4. Déploiement optionnel vers dossiers Arma locaux / Workshop dev

Log complet : `mod/UptoDate/build_log.txt`

Build sans pause (CI / script) :

```batch
set COMSPEC_BUILD_NOPAUSE=1
build_mod.bat
```

---

## Sortie attendue

```text
mod/UptoDate/@COMSPECOverwatch/
├── addons/
│   ├── main.pbo
│   ├── connect.pbo
│   ├── atak_athena.pbo
│   └── mavik_compat.pbo
├── COMSPECExtension_x64.dll
├── mod.cpp
├── logo.paa (si présents)
└── CHANGELOG.md
```

---

## Publication Steam Workshop

1. Lancer **build_mod.bat** (build frais)
2. Lancer **workshop-pack.ps1** → dossier `publisher/@COMSPECOverwatch/` propre
3. Copier texte depuis **STEAM_CHANGELOG.txt** dans la description Workshop
4. **Ne jamais publier** : sources `.sqf` nues, dossiers `net8.0`, fichiers `.pdb`

Workshop ID référence projet : `3684656708`

---

## Quand rebuild quoi

| Changement | Rebuild |
|---|---|
| SQF / config.cpp / hpp | PBO concerné (souvent `connect`) |
| Extension.cs | DLL obligatoire |
| Version affichée hub | `config.cpp` (main + connect + mavik) + rebuild main/connect |
| Assets PNG → PAA | Repack `connect` après TexView 2 |

Après changement **DLL seule** : remplacer `COMSPECExtension_x64.dll` suffit si PBO inchangés.

---

## Test local

1. Launcher Arma → mod `@COMSPECOverwatch` **dernier** dans l’ordre (après CBA)
2. Vérifier présence **DLL** à la racine du mod (pas dans un sous-dossier publish)
3. Supprimer anciens dossiers `addons/connect/` **sources** si copiés par erreur à côté des PBO (conflit préfixe)
4. En jeu : hub → version **1.3.0** (ou version courante)

---

## Dépannage build

| Erreur | Piste |
|---|---|
| dotnet publish failed | SDK 8 installé ? erreur C# dans log |
| AddonBuilder failed | Chemin Tools, config.cpp syntaxe |
| DLL non copiée | Vérifier `bin/publish/COMSPECExtension_x64.dll` |
| En jeu extension absente | DLL manquante ou bloquée antivirus |
| Mod charge mais pas liaison | Clé communauté, URL, pas double mod Workshop + local |

---

## Versionning

Incrémenter **versionStr** dans :

- `Sources/.../main/config.cpp`
- `Sources/.../connect/config.cpp`
- `Sources/.../mavik_compat/config.cpp`

Documenter dans :

- `@COMSPECOverwatch/CHANGELOG.md`
- `STEAM_CHANGELOG.txt`
- `CHANGELOG-ATAK.md` (dépôt racine, équipe portail)

---

## Voir aussi

- [Architecture](architecture-et-addons.md)
- [README doc](README.md)
