## Architecture du mod COMSPEC Overwatch

Vue d’ensemble pour **moddeurs** et **intégrateurs** — côté client Arma uniquement.

---

## Structure du dépôt build

```text
mod/UptoDate/
├── Sources/comspec-overwatch-addons/
│   ├── main/           → comspec_overwatch_main.pbo
│   ├── connect/        → comspec_overwatch_connect.pbo  (cœur liaison)
│   ├── atak_athena/    → comspec_overwatch_atak_athena.pbo (pont cTab/BCE)
│   └── mavik_compat/   → comspec_overwatch_mavik_compat.pbo
├── COMSPECExtension/   → COMSPECExtension_x64.dll (C# Native AOT)
├── @COMSPECOverwatch/  → sortie build (PBO + DLL + mod.cpp)
├── build_mod.bat
├── workshop-pack.ps1
└── docs/               → documentation source du mod
```

Préfixe PBO : `z\comspec_overwatch\addons\{main|connect|atak_athena|mavik_compat}`

---

## Addons — responsabilités

### main

- Métadonnées pack, logos, patch minimal
- Peu de logique runtime

### connect (addon principal)

| Domaine | Exemples |
|---|---|
| Liaison | Connexion session, **fiche opérateur jeu** (Steam, visage, loadout, versions), position, déconnexion |
| Hub & UI | Dialogs hub, tablette, téléphone, rapports |
| Roleplay | Transmission, zones, dommages, overlays |
| Rapports & intel | SALUTE, SPOTREP, POI, photos recon |
| ACE | Menus tactiques, réparation ATAK |
| Crash recovery | HandleDisconnect, reprise session |

Point d’entrée : **CBA Extended** (`XEH_preInit`, `XEH_postInit`, `XEH_postInitClient`).

### atak_athena

- Pont **Iceman / BCE / cTab** (variables publiques, events CBA)
- Photos, marqueurs, feeds, alertes — **interfaces publiques des mods tiers uniquement**
- Ne pas dupliquer le code BCE

### mavik_compat

- Charge uniquement si **Mavic_Core** présent
- Évite conflits settings CBA drone

---

## Extension native (DLL)

**COMSPECExtension_x64.dll** — bibliothèque native (C# / .NET 8, Native AOT) chargée par Arma via `callExtension`.

Rôle général :

- Assurer la liaison réseau sécurisée entre le client Arma et le portail
- Remonter positions, rapports et médias selon l’état du terminal
- Recevoir ordres / alertes / configuration roleplay côté jeu
- Gérer les callbacks asynchrones (`ExtensionCallback`)

Les détails de protocole et d’authentification **ne sont pas documentés ici**.

Communication SQF : `callExtension` + callbacks d’état (connexion, erreur, coupure réseau…).

---

## Flux principal (vue simplifiée)

```text
[Client Arma]
    → handshake liaison (hub)
    → COMSPECExtension
    → Portail Athena (session communauté)
    → boucles de sync (position, polls, roleplay)
    → Tacmap / TOC mise à jour côté portail
```

---

## Roleplay — fichiers clés (connect)

| Fichier | Rôle |
|---|---|
| `fn_canTransmit.sqf` | Gate envoi liaison |
| `fn_isAtakFunctional.sqf` | État terminal |
| `fn_checkAtakDamage.sqf` | Dommages combat |
| `fn_applyZoneEffects.sqf` | Zones + effets visuels |
| `fn_updateAtakEnhancedRoleplay.sqf` | Overlays hub |
| `fn_pollRoleplayConfig.sqf` | Sync config communauté |
| `fn_initCrashRecovery.sqf` | Reprise serveur |
| `display_hub.hpp` | IDC overlays |
| `modules/module_roleplay_zone.hpp` | Modules Eden |

---

## Intégrations mods tiers

| Mod | Méthode |
|---|---|
| ACE Medical | Variables `ace_medical_*` |
| KAT Medical | Branche optionnelle SpO2, airway, pneumothorax |
| cTab / BCE | addon `atak_athena`, variables Iceman |
| Mavic | addon `mavik_compat` |
| ACRE2 / TFAR | détection radio proximité (optionnel) |

**Licence :** ne pas copier assets/SQF tiers ; consommer uniquement les interfaces publiques.

---

## Conventions SQF

- Tag fonctions : `comspec_overwatch_connect_fnc_*`
- Variables mission : préfixe `COMSPEC_`
- UI sans jargon technique exposé au joueur
- `profileNamespace` : persistance locale (identifiant terminal, indicatif)

---

## Dépendances CBA (connect)

**Required addons :** `cba_main`, `cba_xeh`, `cba_settings`, `comspec_overwatch_main`, `A3_Modules_F` (modules roleplay)

Voir aussi la fiche **Bibliothèques & mods utilisés**.
