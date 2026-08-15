# COMSPEC Overwatch — Mod Arma 3

Mod Arma 3 pour la liaison avec l’overlay ATAK / Tacmap COMSPEC. Envoi de la position du joueur vers le nœud ATAK.

## Prérequis

- Arma 3
- **CBA A3** (Community Base Addons)

## Installation

1. Télécharger le mod (archive .zip ou dossier `@COMSPECOverwatch`).
2. Extraire dans le dossier d’Arma 3 (ou le répertoire des mods de votre launcher).
3. S’assurer que l’extension `COMSPECExtension_x64.dll` est bien dans le dossier `@COMSPECOverwatch` (fournie avec le mod ou à compiler, voir ci‑dessous).
4. Activer **CBA A3** puis **COMSPEC Overwatch** dans le launcher.

## Configuration (CBA)

**Valeur par défaut** : l’URL du nœud est définie sur `http://atak.athena.ttrd.fr` (HTTP, sans SSL). En utilisation standard, aucun réglage n’est nécessaire.

Pour modifier les réglages en jeu :
- **ESC** → **Options** → **Jeu** → **Configurer les mods** (Configure Addons)
- Dans la liste des addons, chercher **COMSPEC Overwatch** ou **COMSPEC Overwatch (Connexion)** → ouvrir **Connexion**

- **Activer COMSPEC Overwatch** : cocher pour activer.
- **URL du nœud ATAK** : URL de base du serveur Node (défaut : `http://atak.athena.ttrd.fr`), sans slash final. Utiliser HTTP si pas de certificat SSL.
- **Clé d'accès** : optionnel, si votre admin en fournit une.

## Build de l’extension (obligatoire pour la liaison ATAK)

L’extension C# est dans `../COMSPECExtension`. Pour recompiler :

```bash
cd mod\COMSPECExtension
dotnet publish -c Release -r win-x64
```

**Prérequis build :** .NET 8+ SDK et **outils de build C++** (obligatoire pour Native AOT). Installer l’un des deux : [Visual Studio 2022](https://visualstudio.microsoft.com/fr/) avec la charge « Développement Desktop en C++ », ou [Build Tools pour Visual Studio](https://visualstudio.microsoft.com/fr/downloads/#build-tools-for-visual-studio-2022) avec « C++ build tools ». Puis redémarrer le terminal.

Copier tout le contenu de `bin\Release\net8.0\win-x64\publish\` dans `@COMSPECOverwatch\`. Raccourci PowerShell : `Copy-Item "bin\Release\net8.0\win-x64\publish\*" "..\@COMSPECOverwatch\" -Recurse -Force`

## Packaging (zip pour diffusion)

1. Builder les addons en PBO (HEMTT ou Arma 3 Tools).
2. Inclure dans le zip :
   - le dossier `@COMSPECOverwatch` avec les PBO dans `addons/`,
   - `COMSPECExtension_x64.dll` à la racine de `@COMSPECOverwatch`,
   - `mod.cpp`.

Voir le tutoriel sur le site : **ATAK → Tuto mod Arma**.
