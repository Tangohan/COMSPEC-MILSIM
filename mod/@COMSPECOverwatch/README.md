# COMSPEC Overwatch — Mod Arma 3

Mod Arma 3 pour la liaison avec Athena (carte tactique / ATAK). Envoi de la position du joueur et outils terrain (messagerie, tablette, téléphone, ordres…).

- Description Steam Workshop (BBCode prêt à coller) : [STEAM_DESCRIPTION.md](STEAM_DESCRIPTION.md)
- **Changelog** : [CHANGELOG.md](CHANGELOG.md) — version courante **1.1.7**
- Crédits & sources (cTab, SIT, ctav-b2, etc.) : [CREDITS.md](CREDITS.md)
- **Diffusion Workshop (checklist)** : [PACKAGING.md](PACKAGING.md)
- **Sécurité / limites anti-copie** : [SECURITY.md](SECURITY.md)

## Prérequis

- Arma 3
- **CBA A3** (Community Base Addons)

### Optionnel — app Athena dans ATAK Enhanced (cTab)

Pour l’icône **Athena** dans cTab (inbox alertes/ordres/BDA/photos + envoi rapide + tablette complète) :

- **cTab** / cTab+ et **BCE** (comme pour ATAK Enhancements by Iceman)
- PBO `addons/atak_athena.pbo` (embarqué dans le build si AddonBuilder réussit)
- Dual-send avec ATAK Enhanced (alertes, BDA, messages de groupe, photos) ; poll Athena → inbox cTab ; ordres C2 sans forcer la tablette Chromium si l’app est ouverte
- Ponts lecture seule vers le navigateur : météo mission, contacts Drone Ops, marqueurs utilisateur cTab, itinéraires Route, plans HAHO/HALO, métadonnées Wave Relay / MPU-5
- Chaque pont est un **module** activable / désactivable dans l’admin Athena (« Configuration ATAK »). Le jeu synchronise l’état ; l’app Athena cTab (onglet Modules / Journal modules) et la tablette (Apps → Modules & journal) affichent l’état et le journal des données.
- **Connexion Athena** : icône sur l’écran **Desktop** du téléphone ATAK Enhanced (à côté du raccourci Elevation), bouton dans l’app Athena, et entrée Hub (touche K). Ouvre l’UI de liaison (code / Steam) avec barre de transmission + latence en ms. Dépend de `cTab_Android_dlg` (ATAK Enhanced / BCE) ; sans pack téléphone, utiliser K → Connexion Athena.

Sans cTab/BCE, le cœur Overwatch (`main` + `connect`) fonctionne seul ; `atak_athena` ne se charge simplement pas.

## Installation

1. Télécharger le mod (archive .zip ou dossier `@COMSPECOverwatch`).
2. Extraire dans le dossier d’Arma 3 (ou le répertoire des mods de votre launcher).
3. S’assurer que l’extension `COMSPECExtension_x64.dll` est bien dans le dossier `@COMSPECOverwatch` (fournie avec le mod ou à compiler, voir ci‑dessous).
4. Activer **CBA A3** puis **COMSPEC Overwatch** dans le launcher.

## BattlEye (cause fréquente de « extension non chargée »)

Avec **BattlEye activé**, Arma refuse souvent `COMSPECExtension` tant que l’extension n’est pas whitelistée. Le journal `.rpt` affiche alors un message trompeur :

`Call extension 'COMSPECExtension' could not be loaded: Insufficient system resources…`  
(en français : « Ressources système insuffisantes… »)

Ce n’est **pas** un problème de taille de DLL : une `COMSPECExtension_x64.dll` Native AOT correcte fait ~5 Mo et peut quand même être bloquée.

**Pour tester en solo / éditeur :**
1. Lanceur Arma 3 → paramètres → **désactiver BattlEye**
2. Quitter Arma complètement (pas seulement retour menu)
3. Relancer avec `@COMSPECOverwatch` (Workshop : `Arma 3\!Workshop\@COMSPECOverwatch`)

**En multijoueur protégé BE :** il faut une whitelist BattlEye officielle de l’extension (comme ACE), sinon le module restera bloqué côté client.

Un message UI du type « DLL invalide ~32 Ko » (anciennes versions du mod) était un **faux positif** : SQF voyait seulement une réponse vide et concluait à tort un stub managé.

## Configuration (CBA)

**URL de production** : `https://athena.ttrd.fr/public` (sans slash final).  
Le préfixe `/public` est obligatoire sur ce déploiement : sans lui, les appels `/api/atak/*` renvoient une page introuvable.

**Connexion recommandée (sans toucher aux réglages obscurs)** :
1. Sur Athena (connecté) : page ATAK → panneau compte → **Générer un code de liaison**.
2. En jeu : touche **K** (menu ATAK) → **Connecter mon compte Athena** → coller le code → **Établir la liaison**.
3. Les paramètres sont enregistrés dans votre profil Arma pour les prochaines sessions.

Si Athena répond **503** sur la génération de code : exécuter en prod `php run-migrations.php` (crée `tactical_game_link_codes` via `bootstrap/tactical_game_link_migration.php`). Pas besoin de redéployer les fichiers PHP si la route `POST /atak/game-link` est déjà en place.

**Clé d’accès** (avancé) : en production, renseignez la clé fournie par l’admin (même valeur que `X_COMSPEC_KEY` côté serveur). Sans clé, la génération du QR téléphone est refusée.

Pour modifier les réglages manuellement :
- **ESC** → **Options** → **Jeu** → **Configurer les mods** (Configure Addons)
- Dans la liste des addons, chercher **COMSPEC Overwatch**

- **Activer Overwatch** : cocher pour activer.
- **URL Athena** : URL de base du portail (défaut : `https://athena.ttrd.fr/public`), sans slash final.
- **Clé d’accès Athena** : obligatoire en production si l’admin l’a activée.
- **Identifiant de communauté** : laisser vide si `ATAK_DEFAULT_TENANT_ID` est défini côté serveur ; sinon renseigner l’id numérique fourni par l’admin.

## Raccourcis clavier (CBA)

| Action | Touche par défaut |
|--------|-------------------|
| Menu ATAK | **K** |
| Messagerie | **Ctrl+K** |

Si un raccourci ne répond pas : **ESC → Options → Commandes → Configurer les addons → COMSPEC Overwatch**, puis réassignez.  
Les anciens profils pouvaient avoir « Messagerie » sur **K** seul (sans Ctrl) — les identifiants de raccourcis ont été renouvelés pour forcer les bons défauts.

## Build de l’extension (obligatoire pour la liaison ATAK)

L’extension C# est dans `../COMSPECExtension`. Pour recompiler :

```bash
cd mod\COMSPECExtension
dotnet publish -c Release -r win-x64
```

**Prérequis build :** .NET 8+ SDK et **outils de build C++** (obligatoire pour Native AOT). Installer l’un des deux : [Visual Studio 2022](https://visualstudio.microsoft.com/fr/) avec la charge « Développement Desktop en C++ », ou [Build Tools pour Visual Studio](https://visualstudio.microsoft.com/fr/downloads/#build-tools-for-visual-studio-2022) avec « C++ build tools ». Puis redémarrer le terminal.

Copier tout le contenu de `bin\Release\net8.0\win-x64\publish\` dans `@COMSPECOverwatch\`. Raccourci PowerShell : `Copy-Item "bin\Release\net8.0\win-x64\publish\*" "..\@COMSPECOverwatch\" -Recurse -Force`

## Packaging (zip / Workshop — diffusion propre)

**Ne publiez jamais** le dossier de travail brut (il contient les `.sqf` sources, parfois `net8.0/` et des `.pdb`).

1. Builder : `mod\build_mod.bat` (PBO + DLL Native AOT).
2. Pack filtrant :

```powershell
cd mod
.\workshop-pack.ps1
# ou avec zip :
.\workshop-pack.ps1 -Zip
```

3. Publier **uniquement** `mod\publisher\@COMSPECOverwatch\` (PBO + DLL + `mod.cpp` + `CREDITS.md`).

Checklist complète : [PACKAGING.md](PACKAGING.md). Limites honnêtes : [SECURITY.md](SECURITY.md).

Voir aussi le tutoriel sur le site : **ATAK → Tuto mod Arma**.
