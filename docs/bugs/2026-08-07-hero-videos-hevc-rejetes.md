# Accueil — vidéos hero absentes (HEVC / QuickTime)

## Contexte

Page d’accueil Athena (`views/home/index.php`). Fichiers déjà présents en dépôt et en prod :

- `public/assets/video/hero-athena.mp4` (~12 Mo)
- `public/assets/video/hero-athena-2.mp4` (~5 Mo)
- `public/assets/video/hero-athena-3.mp4` (~13 Mo)

Suite de `docs/bugs/2026-07-29-accueil-hero-video-ne-demarre-pas.md` (timeout) et de `docs/VIDEO-HERO-ENCODAGE.md`.

## Symptôme

Sur prod et en local, le hero reste sur le carrousel d’images. Aucune vidéo ne démarre.

Constat prod (2026-08-07) :

- HTML : `data-hero-videos-ready="0"`, aucun bloc `#heroVideoSlides`, aucune balise `<source>` hero
- Fichier : `https://athena.ttrd.fr/public/assets/video/hero-athena.mp4` → **200**, `Content-Type: video/mp4`, `Accept-Ranges: bytes`, taille identique au dépôt
- Sans préfixe `/public/` : `/assets/video/…` → **404** (attendu si `APP_BASE_PATH=/public`)

Ce n’est donc **pas** un 404, un MIME faux, ni un problème de ranges / autoplay.

## Cause

Les trois MP4 sont des conteneurs **QuickTime** (`ftyp` = `qt  `) avec piste vidéo **HEVC** (`hvc1`) et audio AAC (`mp4a`).

Chrome / Edge / Firefox ne décodent pas HEVC. `VideoSourceProbe` les écarte volontairement pour éviter un fond noir ; le carrousel photo prend le relais.

## Correctif

1. **Déjà en place (code)** — sonde + omission des `<video>` si aucune source lisible (`VideoSourceProbe`, `views/home/index.php`).
2. **À faire (médias)** — réencoder en H.264 (+ optionnel WebM), puis remplacer les fichiers **en binaire** (git + FTP / déploiement).

Commande exacte (depuis `public/assets/video/`) :

```bash
# Pour chaque stem : hero-athena, hero-athena-2, hero-athena-3
ffmpeg -y -i hero-athena.mp4 \
  -c:v libx264 -profile:v high -level 4.1 -pix_fmt yuv420p \
  -crf 23 -preset slow \
  -movflags +faststart \
  -an \
  hero-athena.h264.mp4

# Vérifier puis remplacer
ffprobe -v error -select_streams v:0 \
  -show_entries stream=codec_name,profile,pix_fmt -of default=nw=1 hero-athena.h264.mp4
# attendu : codec_name=h264, profile=High, pix_fmt=yuv420p

mv hero-athena.h264.mp4 hero-athena.mp4
```

Script Windows : `scripts/reencode-hero-videos.ps1`.

Détails et WebM : `docs/VIDEO-HERO-ENCODAGE.md`.

## Fichiers touchés

- `docs/bugs/2026-08-07-hero-videos-hevc-rejetes.md` (cette note)
- `docs/VIDEO-HERO-ENCODAGE.md` (commandes in-place)
- `scripts/reencode-hero-videos.ps1` (réencodage local)
- `tests/Unit/VideoSourceProbeTest.php` (garde-fou sonde)
- `views/home/index.php` (commentaire HTML diagnostic si sources écartées)

Aucun nouveau binaire vidéo n’a été généré ici (ffmpeg absent de l’environnement).

## Vérification

1. Après réencodage : `VideoSourceProbe` → `playable: true`, codec `avc1`.
2. Accueil local / prod : `data-hero-videos-ready="1"` et présence de `#heroVideoSlides`.
3. Le fond vidéo remplace les photos en quelques secondes (muted, autoplay).

## Statut

Identifié — correctif médias en attente (réencodage H.264 + déploiement des MP4)
