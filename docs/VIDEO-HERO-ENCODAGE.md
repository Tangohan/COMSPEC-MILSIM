# Vidéos hero — encodage requis

## Le problème constaté

Le 29/07/2026, la page d'accueil publique n'affichait plus aucune vidéo hero. En ouvrant
directement `https://athena.ttrd.fr/public/assets/video/hero-athena.mp4`, le navigateur
affichait un lecteur **audio seul** : la durée et le son étaient corrects, l'image restait
noire.

Diagnostic sur les **fichiers d’origine** (avant réencodage) :

| Fichier | Conteneur (`ftyp`) | Codec vidéo | Lisible Chrome / Edge / Firefox |
|---|---|---|---|
| `hero-athena.mp4` | `qt  ` (QuickTime) | `hvc1` (HEVC / H.265) | **Non** |
| `hero-athena-2.mp4` | `qt  ` (QuickTime) | `hvc1` (HEVC / H.265) | **Non** |
| `hero-athena-3.mp4` | `qt  ` (QuickTime) | `hvc1` (HEVC / H.265) | **Non** |

Les trois fichiers portaient l'extension `.mp4` mais étaient des **QuickTime encodés en HEVC**.
C'est la sortie par défaut de plusieurs outils Apple (Final Cut, QuickTime, export iPhone).

Chrome, Edge et Firefox ne décodent pas HEVC. Le navigateur accepte pourtant la source
— on lui annonce `video/mp4` — décode la piste audio AAC, et n'affiche rien. D'où le
lecteur noir, alors qu'un repli sur l'affiche aurait été préférable.

**État attendu après réencodage** : ISO BMFF (`isom`) + `avc1` (H.264) + AAC, CFR 30 fps.
Un premier passage avait utilisé `-an` : image OK, **son mort** — voir
`docs/bugs/2026-08-07-hero-videos-hevc-rejetes.md`.

## Ce qui a été corrigé côté code

`App\Support\Media\VideoSourceProbe` lit la marque de conteneur et le codec de piste, puis :

- **écarte la source** si le codec n'est pas décodable (HEVC / QuickTime) ;
- pour une source **jouable** (H.264 / VP9 / AV1), annonce le MIME de conteneur
  (`video/mp4`) **sans** `codecs="avc1"` nu — sinon `canPlayType()` renvoie `""`
  et le navigateur ignore la balise `<source>` (hero figé sur les photos) ;
- si plus aucun emplacement n'a de source exploitable, les balises `<video>` ne sont plus
  rendues du tout et le carrousel d'images porte le hero.

Le hero ne peut donc plus rester noir faute de codec. **Cela ne restaure pas une
vidéo encore en HEVC** : il faut réencoder les fichiers.

## Encodage attendu

À faire en local (installer ffmpeg si besoin, ex. `winget install Gyan.FFmpeg`), puis
committer / transférer les fichiers **en mode binaire**.

### Depuis des MP4 HEVC / QuickTime (cas historique)

Si les fichiers du dépôt sont encore en HEVC, ou pour repartir d’une sauvegarde :

```bash
cd public/assets/video

for stem in hero-athena hero-athena-2 hero-athena-3; do
  ffmpeg -y -i "${stem}.mp4" \
    -vf fps=30 \
    -c:v libx264 -profile:v high -level 4.1 -pix_fmt yuv420p \
    -crf 23 -preset medium \
    -movflags +faststart \
    -c:a aac -b:a 128k -ac 2 \
    "${stem}.h264.mp4"
  ffprobe -v error -show_entries stream=codec_type,codec_name,profile,pix_fmt,r_frame_rate,channels \
    -of default=nw=1 "${stem}.h264.mp4"
  # attendu : h264 High yuv420p 30/1 + aac stereo
  mv "${stem}.h264.mp4" "${stem}.mp4"
done
```

Sous Windows : `pwsh -File scripts/reencode-hero-videos.ps1`  
(option `-KeepWebm` pour générer aussi les `.webm`).

### Depuis une source .mov

```bash
# MP4 / H.264 — source universelle
ffmpeg -i hero-athena-source.mov \
  -vf fps=30 \
  -c:v libx264 -profile:v high -level 4.1 -pix_fmt yuv420p \
  -crf 23 -preset medium \
  -movflags +faststart \
  -c:a aac -b:a 128k -ac 2 \
  hero-athena.mp4

# WebM / VP9 — servi en premier aux navigateurs qui le supportent
ffmpeg -i hero-athena-source.mov \
  -vf fps=30 \
  -c:v libvpx-vp9 -crf 33 -b:v 0 -row-mt 1 \
  -c:a libopus -b:a 96k \
  hero-athena.webm
```

Points qui comptent :

- `-pix_fmt yuv420p` — sans cela, un export en 4:2:2 ou 4:4:4 reste illisible en navigateur.
- `-movflags +faststart` — place l'index en tête, la lecture démarre sans télécharger tout
  le fichier.
- `-profile:v high -level 4.1` — compatible partout, y compris mobile.
- `-vf fps=30` — certains exports QT / Dreamina ont un timebase 120 tbr : sans CFR,
  ffmpeg duplique des centaines de frames et dépasse le level 4.1 (lecture buggy).
- **Garder l’AAC** (`-c:a aac`) : le hero démarre muet pour l’autoplay, mais le bouton
  son / consentement immersif ont besoin d’une piste audio. Ne pas utiliser `-an`.

## Vérification avant transfert

```bash
ffprobe -v error -show_entries stream=codec_type,codec_name,profile,pix_fmt,r_frame_rate,channels \
  -of default=nw=1 hero-athena.mp4
# attendu : h264 High yuv420p 30/1 + aac (channels=2)
```

Côté serveur, la sonde retient la source dès que le codec est `avc1` : aucun changement de
code n'est nécessaire après le remplacement des fichiers.
