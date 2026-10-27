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
3. Copie DLL + mod.cpp vers `@COMSPECOverwatch/` (git ne suit pas cette DLL : elle n'existe qu'après un build)
4. Déploiement optionnel vers les dossiers Arma locaux (`Arma 3\@COMSPECOverwatch`, pack SOAR FN), jamais vers le dossier Workshop de l'ATAK natif

Le pack SOAR FN (`Arma 3\!Workshop\@# S.O.A.R - FN`) est la copie locale avec laquelle TTRD teste le mod. Le build y remplace les PBO, la DLL, `mod.cpp` et les logos, et y supprime d'anciens fichiers, dont `comspec_overwatch_connect.pbo`. Cette cible est gardée volontairement. Le build refuse une cible qui contient `COMSPECATAKNativeExtension_x64.dll`, la DLL du seul pack natif : il n'y écrit rien, met à jour l'autre cible, puis finit en échec. Sans cette DLL, vérifiez avant un build que ce dossier porte le pack hérité et non l'ATAK natif, dont le `main.pbo` a le préfixe `z\comspec_atak_native\addons\main`.

Sans DLL fraîche après `dotnet publish`, ou si sa copie échoue (DLL verrouillée par Arma), le build s'arrête en échec : l'ancienne DLL du dossier `@COMSPECOverwatch` n'est plus déployée.

Il s'arrête aussi en échec si une copie vers un dossier Arma échoue, par exemple un PBO ou la DLL verrouillés par Arma : le détail est dans `build_log.txt`, sous la ligne `[DEPLOY] Cible` du dossier en cause. Un addon optionnel (`atak_athena`, `mavik_compat`, `sse_ace`) qui ne compile pas ne donne qu'un avertissement, à son nom : son ancien PBO, s'il existe, part tel quel au déploiement.

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
- Il refuse aussi un `-OutDir` situé dans `mod/COMSPEC_ATAK_Native` ou au-dessus, sous `steamapps\workshop` ou sous le `!Workshop` d'Arma 3, et toute sortie qui contient `!Workshop`, `steamapps`, `arma3_x64.exe` ou `COMSPECATAKNativeExtension_x64.dll` (dossier d'Arma 3, bibliothèque Steam, pack de l'ATAK natif) : le nettoyage du staging viderait ce dossier. Un `-OutDir` passé à la main qui existe doit être vide ou porter le fichier `workshop-pack-sortie.txt`, que le script écrit dans chaque sortie ; la sortie par défaut n'en a pas besoin. Une fois ce contrôle passé, un refus retire le `meta.cpp` du dossier de sortie, car une sortie de l'ancien script, d'avant ARCH-021, y gardait le `publishedid` de l'ATAK natif. Un arrêt sur erreur ou par Ctrl+C le retire aussi (message « Pack inachevé »). Après la dernière copie, le script relit le `meta.cpp` de la sortie et refuse le pack s'il vise l'objet de l'ATAK natif.
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
| Build en échec : « Au moins une copie vers un dossier Arma a echoue » | Fermer Arma puis relancer ; voir `build_log.txt` sous la ligne `[DEPLOY] Cible` |
| Build en échec : « Cible refusee: elle contient COMSPECATAKNativeExtension_x64.dll » | Ce dossier porte l'ATAK natif : le build hérité ne l'écrase pas. Vérifier ce dossier ; le message « Au moins une copie… » qui suit vient de ce refus |
| workshop-pack.ps1 : « DLL COMSPEC Link … refusée » | DLL plus ancienne que 2.0.96 : relancer `build_mod.bat` |
| workshop-pack.ps1 : « … vise l'objet Workshop 3684656708, qui est l'ATAK natif » | Retirer `publishedid` du `meta.cpp` ; ne pas publier ce pack |
| workshop-pack.ps1 : « Pack inachevé : ne publiez pas le contenu de … » | Le script s'est arrêté avant la fin (erreur affichée plus haut, refus ou Ctrl+C) et a retiré le `meta.cpp` de la sortie s'il y en avait un : corriger la cause, puis relancer le script |
| workshop-pack.ps1 : « Sortie refusée : … » | `-OutDir` dans l'ATAK natif ou sous un dossier Workshop de Steam, sortie qui contient Arma 3, Steam ou un pack natif, ou `-OutDir` non vide sans `workshop-pack-sortie.txt` : choisir un dossier vide ou absent, ou lancer le script sans `-OutDir` |
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
