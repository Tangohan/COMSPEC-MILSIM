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
4. Déploiement optionnel vers les dossiers Arma locaux (`Arma 3\@COMSPECOverwatch`, pack SOAR FN), jamais vers le dossier Workshop de l'ATAK natif

Sans DLL fraîche après `dotnet publish`, ou si sa copie échoue (DLL verrouillée par Arma), le build s'arrête en échec : l'ancienne DLL du dossier `@COMSPECOverwatch` n'est plus déployée.

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

Le pack hérité `@COMSPECOverwatch` n'a plus d'objet Workshop : ne le publiez pas.

- L'objet `3684656708` (« COMSPEC ATAK Native ») est l'ATAK natif. Il se publie seulement depuis `mod/COMSPEC_ATAK_Native` (dossier `@COMSPEC_ATAK_Native`), après son propre `build_mod.bat`, qui recompile ici la DLL COMSPEC Link et les PBO `main` et `connect` d'Overwatch.
- `workshop-pack.ps1` produit encore un dossier `publisher/@COMSPECOverwatch/` propre, mais il s'arrête en erreur (code 1) si la DLL retenue annonce une version sous 2.0.96 (plancher SEC-001, `docs/PLANCHER-VERSION-DLL.md` à la racine du dépôt) ou si le `meta.cpp` du pack vise l'objet `3684656708` de l'ATAK natif.
- **Ne jamais publier** : sources `.sqf` nues, dossiers `net8.0`, fichiers `.pdb`.

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
| Build en échec : « DLL Native AOT fraiche introuvable » ou « Copie de la DLL fraiche impossible » | Vérifier `bin/publish/COMSPECExtension_x64.dll` ; fermer Arma si la DLL est verrouillée |
| workshop-pack.ps1 : « DLL COMSPEC Link … refusée » | DLL plus ancienne que 2.0.96 : relancer `build_mod.bat` |
| workshop-pack.ps1 : « … vise l'objet Workshop 3684656708, qui est l'ATAK natif » | Retirer `publishedid` du `meta.cpp` ; ne pas publier ce pack |
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
