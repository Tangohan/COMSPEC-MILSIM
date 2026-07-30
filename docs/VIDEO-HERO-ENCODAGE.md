# Vidéos hero — encodage requis

## Le problème constaté

Le 29/07/2026, la page d'accueil publique n'affichait plus aucune vidéo hero. En ouvrant
directement `https://athena.ttrd.fr/public/assets/video/hero-athena.mp4`, le navigateur
affichait un lecteur **audio seul** : la durée et le son étaient corrects, l'image restait
noire.

Diagnostic sur les fichiers du dépôt :

| Fichier | Conteneur (`ftyp`) | Codec vidéo | Lisible Chrome / Edge / Firefox |
|---|---|---|---|
| `hero-athena.mp4` | `qt  ` (QuickTime) | `hvc1` (HEVC / H.265) | **Non** |
| `hero-athena-2.mp4` | `qt  ` (QuickTime) | `hvc1` (HEVC / H.265) | **Non** |
| `hero-athena-3.mp4` | `qt  ` (QuickTime) | `hvc1` (HEVC / H.265) | **Non** |

Les trois fichiers portent l'extension `.mp4` mais sont des **QuickTime encodés en HEVC**.
C'est la sortie par défaut de plusieurs outils Apple (Final Cut, QuickTime, export iPhone).

Chrome, Edge et Firefox ne décodent pas HEVC. Le navigateur accepte pourtant la source
— on lui annonce `video/mp4` — décode la piste audio AAC, et n'affiche rien. D'où le
lecteur noir, alors qu'un repli sur l'affiche aurait été préférable.

## Ce qui a été corrigé côté code

`App\Support\Media\VideoSourceProbe` lit la marque de conteneur et le codec de piste, puis :

- annonce le **type MIME réel** (`video/quicktime; codecs="hvc1"`) au lieu d'un
  `video/mp4` optimiste ;
- **écarte la source** si le codec n'est pas décodable par ces navigateurs ;
- si plus aucun emplacement n'a de source exploitable, les balises `<video>` ne sont plus
  rendues du tout et le carrousel d'images porte le hero.

Le hero ne peut donc plus rester noir. **Cela ne restaure pas la vidéo** : il faut
réencoder les fichiers.

## Encodage attendu

À faire en local (ffmpeg n'est pas disponible dans l'environnement de développement
distant), puis transférer les fichiers **en mode binaire**.

```bash
# MP4 / H.264 — source universelle
ffmpeg -i hero-athena-source.mov \
  -c:v libx264 -profile:v high -level 4.1 -pix_fmt yuv420p \
  -crf 23 -preset slow \
  -movflags +faststart \
  -c:a aac -b:a 128k \
  hero-athena.mp4

# WebM / VP9 — servi en premier aux navigateurs qui le supportent
ffmpeg -i hero-athena-source.mov \
  -c:v libvpx-vp9 -crf 33 -b:v 0 -row-mt 1 \
  -c:a libopus -b:a 96k \
  hero-athena.webm
```

Points qui comptent :

- `-pix_fmt yuv420p` — sans cela, un export en 4:2:2 ou 4:4:4 reste illisible en navigateur.
- `-movflags +faststart` — place l'index en tête, la lecture démarre sans télécharger tout
  le fichier.
- `-profile:v high -level 4.1` — compatible partout, y compris mobile.
- Le hero est **muet à l'affichage** : la piste audio peut être supprimée (`-an`) pour
  alléger les fichiers.

## Vérification avant transfert

```bash
ffprobe -v error -select_streams v:0 \
  -show_entries stream=codec_name,profile,pix_fmt -of default=nw=1 hero-athena.mp4
# attendu : codec_name=h264, profile=High, pix_fmt=yuv420p
```

Côté serveur, la sonde retient la source dès que le codec est `avc1` : aucun changement de
code n'est nécessaire après le remplacement des fichiers.
