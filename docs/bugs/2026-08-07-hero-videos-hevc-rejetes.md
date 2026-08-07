# Accueil — vidéos hero absentes (HEVC), son perdu (`-an`), puis son bloqué (autoplay)

## Contexte

Page d’accueil Athena (`views/home/index.php`). Clips :

- `public/assets/video/hero-athena.mp4`
- `public/assets/video/hero-athena-2.mp4`
- `public/assets/video/hero-athena-3.mp4`

Suite de `docs/bugs/2026-07-29-accueil-hero-video-ne-demarre-pas.md` et de `docs/VIDEO-HERO-ENCODAGE.md`.

## Symptôme

1. **HEVC** — hero sur photos seulement (`data-hero-videos-ready="0"`).
2. **Réencodage `-an`** — image OK, **aucune piste audio** dans les MP4.
3. **Après MP4 H.264+AAC** — image OK, piste AAC présente, mais **bouton son / consentement immersif restent sans effet**.

## Cause

1. Originaux QuickTime **HEVC** + AAC — rejetés par `VideoSourceProbe`.
2. Premier réencodage avec **`-an`** (doc/script) → fichiers muets.
3. JS hero : le son était réactivé **après** `play()` (promesse / frame), donc **hors geste utilisateur**. Chrome/Firefox/Safari bloquent l’audio. Trois balises `<video>` distinctes : unlocker seulement le clip actif ne suffit pas pour la rotation. Condition `layerReady` pouvait aussi empêcher l’unmute synchrone.

## Correctif

1. MP4 **H.264 + AAC + fps=30 + faststart** ; script/doc sans `-an`.
2. `unlockSoundFromUserGesture()` : pendant le clic (consentement, muet, volume, lien), unlock **tous** les clips (`muted` propriété + attribut), puis mute les inactifs.
3. Autoplay toujours muet au chargement ; le son ne revient que via geste. Préférence `full` en localStorage → lien « activer le son » plutôt qu’unmute fantôme.
4. Icônes muet/son alignées sur l’état initial réel.

## Fichiers touchés

- `public/assets/video/hero-athena*.mp4`
- `scripts/reencode-hero-videos.ps1`
- `docs/VIDEO-HERO-ENCODAGE.md`
- `views/home/index.php`
- `docs/bugs/2026-08-07-hero-videos-hevc-rejetes.md`

## Vérification

```bash
ffprobe … hero-athena.mp4   # h264 + aac stereo
```

Accueil : clips visibles ; clic « Activer le son » / bouton muet / oui immersif → audio audible, y compris après changement de slide.

## Statut

Corrigé — médias + unlock audio multi-vidéo
