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

En jeu : **Paramètres → Addons → COMSPEC Overwatch → Connexion**

- **Activer COMSPEC Overwatch** : cocher pour activer.
- **URL du nœud ATAK** : URL de base du serveur Node (ex. `https://votre-domaine.com:3001`), sans slash final.
- **Clé d'accès** : optionnel, si votre admin en fournit une.

## Build de l’extension (optionnel)

L’extension C# est dans `../COMSPECExtension`. Pour recompiler :

```bash
cd mod/COMSPECExtension
dotnet publish -c Release -r win-x64 --self-contained false
```

Copier `COMSPECExtension_x64.dll` (et éventuellement les dépendances) dans `@COMSPECOverwatch/`.

## Packaging (zip pour diffusion)

1. Builder les addons en PBO (HEMTT ou Arma 3 Tools).
2. Inclure dans le zip :
   - le dossier `@COMSPECOverwatch` avec les PBO dans `addons/`,
   - `COMSPECExtension_x64.dll` à la racine de `@COMSPECOverwatch`,
   - `mod.cpp`.

Voir le tutoriel sur le site : **ATAK → Tuto mod Arma**.
