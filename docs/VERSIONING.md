# Numéros de version du mod (ATAK natif, COMSPEC Link)

Règle commune à toutes les PR et à tous les fils de travail. Depuis ATAK natif 1.94.0.

## Ce que voit le joueur

```
COMSPEC ATAK 1.94.0 - 2026.10.14 a1b2c3d
             │  │  │   │          └─ commit du dépôt au moment du build (support : retrouver le code exact)
             │  │  │   └─ date du build (UTC)
             │  │  └─ CORRECTIF : correctifs d'une version déjà sortie
             │  └─ MINEUR : une version par sortie (PR seule ou regroupement)
             └─ MAJEUR : 1, ne change que sur décision de TTRD (rupture complète)
```

Affichée dans Réglages, Crédits, Statut, l'app Debug, en tête du journal de session COMSPEC (le .log envoyé au
support), dans le RPT et dans le check de démarrage Discord. Un main.pbo construit sans empreinte affiche
`1.94.0 - dev`.

## Quand monter quel chiffre

| Cas | Exemple | Chiffre |
|---|---|---|
| Nouvelle sortie sur main (nouveauté, refonte, plusieurs correctifs) | 1.93.0 → 1.94.0 | MINEUR +1, CORRECTIF à 0 |
| Regroupement de plusieurs PR natives (une seule PR fusionnée) | 1.94.0 → 1.95.0 | MINEUR +1 **une fois** pour tout le lot |
| Correctif seul d'une version déjà sur main, sans nouveauté | 1.94.0 → 1.94.1 → 1.94.2 | CORRECTIF +1 |
| Rupture complète (sauvegardes, protocole, mod incompatible) | 1.x → 2.0.0 | MAJEUR, TTRD décide |

- Une PR native en attente d'un regroupement ne réserve pas de numéro : le fil de regroupement attribue le numéro
  du lot au moment de fusionner (prochain MINEUR libre).
- Un correctif qui ajoute quoi que ce soit de visible au joueur n'est plus un correctif : MINEUR +1.
- Deux PR ne sortent jamais sous le même numéro : après la fusion d'une autre PR native, fusionner main, reprendre
  le numéro libre suivant et reconstruire main.pbo.

## COMSPEC Link (DLL)

`2.0.N` : N +1 à chaque changement de la DLL, quel qu'il soit. Le main.pbo attend **exactement** cette version
(`LINK_VERSION_STR`) et signale « DLL d'une autre version » sinon. La DLL n'a pas d'empreinte de build.

## Où écrire le numéro

| Fichier | Valeur |
|---|---|
| `mod/COMSPEC_ATAK_Native/Sources/addons/main/script_component.hpp` | `VERSION`, `VERSION_STR` (ATAK natif), `LINK_VERSION_STR` (DLL attendue) |
| `mod/UptoDate/COMSPECExtension/COMSPECExtension.csproj` | `Version`, `InformationalVersion` (Link) |
| `mod/UptoDate/COMSPECExtension/Extension.cs` | `ExtensionVersion` (Link) |
| `CHANGELOG-ATAK.md` | titre de l'entrée : `(ATAK natif 1.94.0)` ou `(ATAK natif 1.94.0, COMSPEC Link 2.0.86)` + « Résumé joueur » |
| Titre de la PR | la même version (l'annonce Discord la cherche dans le changelog) |

Des tests PHP (`tests/Unit/AtakReleaseInfoTest.php`) vérifient que ces valeurs restent alignées et que
`build_info.hpp` reste sans empreinte dans le dépôt.

## L'empreinte de build (date, commit)

- Elle n'est **jamais** écrite à la main ni committée : `Sources/addons/main/build_info.hpp` garde
  `#define BUILD_STAMP ""` dans le dépôt (pas de conflit entre PR).
- Elle est posée juste avant d'empaqueter main.pbo, puis retirée :
  - Windows : `build_mod.bat` le fait tout seul (git et PowerShell ; sans git, le commit vaut `local`).
  - Linux / fils cloud (HEMTT) :
    ```
    python3 mod/COMSPEC_ATAK_Native/tools/stamp_build.py
    (cd mod/COMSPEC_ATAK_Native/Sources && hemtt build)
    cp mod/COMSPEC_ATAK_Native/Sources/.hemttout/build/addons/comspec_atak_native_main.pbo mod/COMSPEC_ATAK_Native/@COMSPEC_ATAK_Native/addons/main.pbo
    python3 mod/COMSPEC_ATAK_Native/tools/stamp_build.py --reset
    ```
  Le commit est le HEAD au moment du build : pour un main.pbo construit dans une PR, c'est le commit parent.
- Elle n'est **jamais comparée** : la vérification des mises à jour, le contrôle main.pbo / DLL et le site ne
  lisent que `MAJEUR.MINEUR.CORRECTIF` (tout ce qui suit un espace est ignoré).
- Elle ne va ni dans les titres du changelog, ni dans les titres de PR, ni dans `mod_version` envoyé au site
  (le site compare et filtre ces versions) : seulement à l'écran, dans le journal et dans le check de démarrage.

## Qui lit les versions

| Lecteur | Ce qu'il lit |
|---|---|
| Site `GET /api/atak/version` (`AtakReleaseInfo`) | `VERSION_STR`, `InformationalVersion` : chiffres et points seulement |
| Notification « mise à jour disponible » (`fn_integrityCheck`) | compare `MAJEUR.MINEUR.CORRECTIF`, ignore l'empreinte |
| Annonce Discord (`.github/scripts/discord_changelog.py`) | version du titre de PR, retrouvée dans les titres de `CHANGELOG-ATAK.md` |
| Signalements, sessions, rapports (`mod_version`) | `VERSION_STR` seul |
