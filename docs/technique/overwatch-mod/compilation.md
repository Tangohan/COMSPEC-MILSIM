## Compilation & publication — COMSPEC Overwatch

Produire un pack **@COMSPECOverwatch** à jour (PBO + DLL).

---

## Prérequis machine

- **Windows** (script batch)
- **.NET 8 SDK** — compilation extension
- **Arma 3 Tools** — `AddonBuilder.exe` (Steam)
- Chemins par défaut dans `build_mod.bat` (à adapter si besoin) :
  - Arma 3
  - Arma 3 Tools → AddonBuilder

---

## Build standard

```batch
cd mod\UptoDate
build_mod.bat
```

Étapes automatiques :

1. `dotnet publish` → **COMSPECExtension_x64.dll** (Native AOT)
2. AddonBuilder → **main.pbo**, **connect.pbo**, **atak_athena.pbo**, **mavik_compat.pbo**
3. Copie DLL + `mod.cpp` vers `@COMSPECOverwatch/`
4. Déploiement optionnel vers dossiers Arma locaux

Log : `mod/UptoDate/build_log.txt`

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
3. Coller la **fiche** depuis **STEAM_DESCRIPTION.md**.
4. Coller les **notes de version** depuis **STEAM_CHANGELOG.txt**.
4. **Ne jamais publier** : sources `.sqf` nues, dossiers `net8.0`, fichiers `.pdb`

---

## Quand rebuild quoi

| Changement | Rebuild |
|---|---|
| SQF / config.cpp / hpp | PBO concerné (souvent `connect`) |
| Extension.cs | DLL obligatoire |
| Version affichée hub | `config.cpp` (main + connect + mavik) + rebuild |
| Assets PNG → PAA | Repack `connect` après TexView 2 |

Après changement **DLL seule** : remplacer `COMSPECExtension_x64.dll` suffit si les PBO sont inchangés.

---

## Test local

1. Launcher Arma → mod `@COMSPECOverwatch` **après** CBA (et mods optionnels)
2. Vérifier la présence de la **DLL** à la racine du mod
3. Ne pas laisser de dossiers sources à côté des PBO (conflit de préfixe)
4. En jeu : hub → version affichée = version courante du pack

---

## Dépannage build

| Erreur | Piste |
|---|---|
| `dotnet publish` échoue | SDK 8 installé ? erreur C# dans le log |
| AddonBuilder échoue | Chemin Tools, syntaxe `config.cpp` |
| DLL non copiée | Vérifier `bin/publish/COMSPECExtension_x64.dll` |
| En jeu : extension absente | DLL manquante ou bloquée (antivirus) |
| Mod charge mais pas de liaison | Configuration de liaison dans le hub ; éviter double chargement Workshop + local |

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

- Architecture du mod
- Bibliothèques & mods utilisés
