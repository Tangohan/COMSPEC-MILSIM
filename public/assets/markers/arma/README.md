# Icônes marqueurs Arma (PAA → PNG)

Déposez ici les PNG convertis depuis les textures marqueurs Arma (`.paa`), en conservant l’arborescence du chemin jeu.

## Packs attendus

| Dossier | Addon |
|---------|--------|
| `a3/` | Arma 3 vanilla |
| `markersplus/` | MarkersPlus |
| `z/mts/` | Metis Marker |
| `ctab/` | cTab |

## Bibliothèque documentation

Régénérer l’index web après ajout de PNG :

```powershell
powershell -File mod/UptoDate/tools/gen-marker-library-index.ps1
```

Fichier généré : `public/assets/js/arma-marker-library-index.js` (consommé par `/documentation/marqueurs` **et** par la carte ATAK / Tacmap).

## Convention de chemin

Texture Arma :

`\A3\ui_f\data\map\markers\military\warning_CA.paa`

Fichier à déposer :

`a3/ui_f/data/map/markers/military/warning_ca.png`

(tout en minuscules, extension `.png`, séparateurs `/`)

## CDN optionnel

Variable d’environnement `ATAK_MARKER_ICONS_CDN` : base URL sans slash final.
Si absente, le portail sert ce dossier (`/assets/markers/arma`).

## Conversion

Converter local (Pal2PacE) — exemple MarkersPlus :

```powershell
# Voir aussi conversion dans les sessions de déploiement marqueurs
```

Sans PNG présent, la carte web conserve le rendu SVG / glyphe existant.
