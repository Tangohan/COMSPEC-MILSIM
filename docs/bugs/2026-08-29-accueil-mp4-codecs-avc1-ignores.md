# Accueil — MP4 H.264 ignorés (`codecs="avc1"`)

## Symptôme

Les fichiers `hero-athena*.mp4` sont bien en H.264 (`avc1` / `isom`) et servis en
`video/mp4`, mais le hero reste sur les photos : aucune vidéo ne démarre.

## Cause

`VideoSourceProbe` ajoutait `type="video/mp4; codecs=\"avc1\""` sur chaque `<source>`.

Or `HTMLMediaElement.canPlayType('video/mp4; codecs="avc1"')` renvoie `""` (non supporté)
dans Chrome / Edge / Firefox / Safari : un fourcc nu n’est pas un RFC 6381 valide
(il faut un profil, ex. `avc1.640028`).

Conséquences :
1. le navigateur **ignore** la balise `<source>` au parsing ;
2. le JS `pruneUnplayableSources()` **supprime** aussi la source avant `play()`.

Les HEVC restent correctement écartés côté serveur ; le bug ne touchait que les
MP4 pourtant jouables.

## Correctif

- Sources **jouables** : MIME de conteneur seul (`video/mp4`), sans `codecs=`.
- Sources **indécodables** : toujours rejetées ; le codec reste dans le diagnostic.
- Filet JS : si le type complet échoue mais le MIME de base passe, on conserve la
  source et on retire le `codecs=` incomplet.

## Fichiers

- `app/Support/Media/VideoSourceProbe.php`
- `views/home/index.php`
- `tests/Unit/VideoSourceProbeTest.php`

## Vérification

1. Accueil : les 3 clips démarrent (autoplay muet).
2. `ffprobe` : `h264` / `avc1` sur chaque fichier.
3. Dans le HTML : `<source … type="video/mp4">` (sans `codecs="avc1"`).
