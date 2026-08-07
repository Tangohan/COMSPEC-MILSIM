# Accueil — vidéos hero absentes (HEVC) puis son perdu (réencodage `-an`)

## Contexte

Page d’accueil Athena (`views/home/index.php`). Clips :

- `public/assets/video/hero-athena.mp4`
- `public/assets/video/hero-athena-2.mp4`
- `public/assets/video/hero-athena-3.mp4`

Suite de `docs/bugs/2026-07-29-accueil-hero-video-ne-demarre-pas.md` et de `docs/VIDEO-HERO-ENCODAGE.md`.

## Symptôme

1. **Avant correctif H.264** — le hero reste sur le carrousel d’images (`data-hero-videos-ready="0"`).
2. **Après le premier réencodage (ce64a1b5)** — les vidéos passent en H.264 et s’affichent, mais **plus aucun son** (bouton muet / consentement immersif sans effet).

## Cause

1. Les originaux étaient des QuickTime (`ftyp` = `qt  `) **HEVC** (`hvc1`) + AAC — rejetés par `VideoSourceProbe` / illisibles Chrome·Edge·Firefox.
2. Le script / la doc de réencodage utilisaient **`-an`** (suppression audio) en partant du principe « hero muet à l’affichage ». Or le hero démarre seulement *muted pour l’autoplay* ; le son est un opt-in UI. Sans piste AAC, le bouton son ne peut rien jouer.
3. Bonus : un export Dreamina a un timebase 120 tbr — sans `-vf fps=30`, ffmpeg dupliquait ~1900 frames et dépassait le level 4.1 (lecture potentiellement buggy).

## Correctif

1. Réencoder depuis les originaux HEVC+AAC (git `ce64a1b5^`) en **H.264 High + yuv420p + AAC 128k + fps=30 + faststart**.
2. Mettre à jour `scripts/reencode-hero-videos.ps1` et `docs/VIDEO-HERO-ENCODAGE.md` : garder l’audio, forcer 30 fps.
3. Après reprise play/pause, réappliquer le volume immersif (`applyAudioToActive`) pour ne pas rester coincé en muet.

## Fichiers touchés

- `public/assets/video/hero-athena.mp4`
- `public/assets/video/hero-athena-2.mp4`
- `public/assets/video/hero-athena-3.mp4`
- `scripts/reencode-hero-videos.ps1`
- `docs/VIDEO-HERO-ENCODAGE.md`
- `views/home/index.php`
- `docs/bugs/2026-08-07-hero-videos-hevc-rejetes.md` (cette note)

## Vérification

```bash
ffprobe -v error -show_entries stream=codec_type,codec_name,profile,pix_fmt,r_frame_rate,channels \
  -of default=nw=1 public/assets/video/hero-athena.mp4
# h264 High yuv420p 30/1 + aac channels=2
```

Accueil : `data-hero-videos-ready="1"`, clips visibles, consentement / bouton son restaurent l’audio.

## Statut

Corrigé — médias H.264+AAC déployables + script/doc alignés
