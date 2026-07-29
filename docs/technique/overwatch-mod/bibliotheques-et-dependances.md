## Bibliothèques & mods utilisés — COMSPEC Overwatch

Catalogue des **dépendances** du pack `@COMSPECOverwatch` (Arma 3), d’après les `requiredAddons`, la documentation mod et les intégrations optionnelles.

---

## Obligatoires

| Dépendance | Type | Rôle |
|---|---|---|
| **Arma 3** | Jeu | Runtime client / serveur |
| **CBA_A3** (`cba_main`, `cba_xeh`, `cba_settings`) | Mod | Extended Event Handlers, settings, socle commun |
| **A3_Modules_F** | Vanilla | Modules Eden / Zeus (zones roleplay) |
| **COMSPECExtension_x64.dll** | Extension native (.NET 8 AOT) | Liaison réseau avec le portail — fournie avec le pack |

Sans **CBA** et sans la **DLL** à la racine du mod, le pack ne fonctionne pas correctement.

---

## Addons internes du pack

| Addon | Dépend de | Rôle |
|---|---|---|
| `comspec_overwatch_main` | CBA, UI Arma | Métadonnées, logos |
| `comspec_overwatch_connect` | main + CBA + Modules | Cœur Overwatch (hub, liaison, rapports) |
| `comspec_overwatch_atak_athena` | connect + cTab + BCE | Pont tablette ATAK Enhanced / cTab |
| `comspec_overwatch_mavik_compat` | CBA + **Mavic_Core** | Compat settings drone (charge si Mavic présent) |

---

## Mods tiers — pont ATAK / cTab (optionnel mais recommandé)

Requis uniquement pour l’addon `atak_athena` :

| Mod / addon | Identifiant typique | Usage |
|---|---|---|
| **cTab** | `cTab`, `ctab_core` | Tablette / interface ATAK Enhanced |
| **BCE** (Better CAS Environment) | `BCE_Core`, `BCE_cTab` | Apps, photos, feeds, outils CAS liés cTab |

Overwatch consomme les **interfaces publiques** de ces mods (variables / events) — pas de copie de leur code.

---

## Mods tiers — intégrations optionnelles

| Mod | Usage dans Overwatch |
|---|---|
| **ACE3** (interaction / medical) | Menus tactiques ACE, variables médicales, réparation ATAK |
| **KAT Medical** (KAM) | Branche optionnelle : SpO2, airway, pneumothorax (réalisme 1.3.0) |
| **Mavic** (`Mavic_Core`) | Compat CBA via `mavik_compat` |
| **ACRE2** | Détection émission radio proximité (métadonnées BFT) |
| **TFAR** | Idem (fréquence SW) si ACRE absent |

Ces mods ne sont **pas** listés en `requiredAddons` du cœur `connect` : le pack reste chargeable sans eux ; les fonctions associées se désactivent ou se dégradent proprement.

---

## Bibliothèques de build (extension)

| Élément | Détail |
|---|---|
| **.NET 8 SDK** | Compilation de `COMSPECExtension` |
| **Native AOT** (`PublishAot`) | Produit `COMSPECExtension_x64.dll` autonome |
| **Arma 3 Tools / AddonBuilder** | Empaquetage des PBO |

Aucune dépendance NuGet tierce n’est déclarée dans le projet d’extension actuel (SDK standard uniquement).

---

## Outils graphiques (assets)

| Outil | Usage |
|---|---|
| **TexView 2** (Bohemia) | Conversion PNG → `.paa` |
| Assets COMSPEC | Overlays roleplay, cadres tablette/téléphone (originaux — ne pas recopier cTab GPL) |

---

## Ordre de chargement recommandé (launcher)

1. CBA_A3  
2. ACE / KAT / ACRE / TFAR / Mavic (selon pack serveur)  
3. cTab / ATAK Enhanced / BCE (si utilisés)  
4. **@COMSPECOverwatch** en dernier  

---

## Sources de vérité dans le dépôt

- `Sources/.../*/config.cpp` — `requiredAddons`
- `mod/UptoDate/docs/README.md` — prérequis joueur
- `mod/UptoDate/docs/architecture-et-addons.md` — architecture détaillée (équipe)
