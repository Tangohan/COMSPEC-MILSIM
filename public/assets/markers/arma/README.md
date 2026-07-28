# Icônes marqueurs Arma (PAA → PNG)

Déposez ici les PNG convertis depuis les textures marqueurs Arma (`.paa`), en conservant l’arborescence du chemin jeu.

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

La conversion PAA → PNG n’est pas automatisée ici (Phase 0). Convertissez hors ligne (TexView2, ImageToPAA inverse, etc.) puis uploadez via FTP/déploiement.

Sans PNG présent, la carte web conserve le rendu SVG / glyphe existant.
