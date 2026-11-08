---
name: livrer-version
description: "Prépare la sortie d'une version du mod ATAK natif ou de COMSPEC Link selon docs/VERSIONING.md, jusqu'à une PR non fusionnée."
---
La référence reste `docs/VERSIONING.md`. Relis-la si un cas n'est pas couvert ici.

## Choisir le numéro
- Nouvelle sortie, ou regroupement de PR (une seule fois pour le lot) : MINEUR +1, CORRECTIF à 0.
- Correctif seul, sans rien de visible de nouveau pour le joueur : CORRECTIF +1.
- MAJEUR et nom affiché : décision de TTRD uniquement.
- COMSPEC Link : `2.0.N`, N +1 à chaque changement de la DLL, quel qu'il soit.
- Deux PR ne sortent jamais sous le même numéro : fusionne main dans la branche, reprends le numéro libre suivant, reconstruis main.pbo.

## Écrire le numéro
| Fichier | Valeurs |
|---|---|
| `mod/COMSPEC_ATAK_Native/Sources/addons/main/script_component.hpp` | `VERSION`, `VERSION_STR`, `VERSION_NAME`, `LINK_VERSION_STR` |
| `mod/UptoDate/COMSPECExtension/COMSPECExtension.csproj` | `Version`, `InformationalVersion` |
| `mod/UptoDate/COMSPECExtension/Extension.cs` | `ExtensionVersion` |
| `CHANGELOG-ATAK.md` | titre `(ATAK natif X.Y.Z)` ou `(ATAK natif X.Y.Z, COMSPEC Link 2.0.N)`, avec « Résumé joueur » FR et EN |
| Titre de la PR | la même version : l'annonce Discord la cherche dans le changelog |

`VERSION_NAME` commence toujours par `VERSION_STR` suivi d'un point.

## Construire
- `build_info.hpp` garde `#define BUILD_STAMP ""` dans le dépôt. L'empreinte n'est jamais écrite à la main.
- Windows : `mod/COMSPEC_ATAK_Native/build_mod.bat`.
- Linux ou session cloud :
  ```
  python3 mod/COMSPEC_ATAK_Native/tools/stamp_build.py
  (cd mod/COMSPEC_ATAK_Native/Sources && hemtt build)
  cp mod/COMSPEC_ATAK_Native/Sources/.hemttout/build/addons/comspec_atak_native_main.pbo mod/COMSPEC_ATAK_Native/@COMSPEC_ATAK_Native/addons/main.pbo
  python3 mod/COMSPEC_ATAK_Native/tools/stamp_build.py --reset
  ```

## Vérifier et livrer
1. Lance `vendor/bin/phpunit --filter AtakReleaseInfoTest`.
2. Ouvre la PR sans la fusionner. Une seule PR native à la fois.
3. Liste ce qu'il faut tester en jeu.
4. La publication Steam reste une action humaine.
