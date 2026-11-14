#!/usr/bin/env python3
"""
Variantes légères des images du site. Les originaux ne sont ni modifiés ni supprimés : ils peuvent être
nommés depuis la base et restent le repli des pages.

  - public/assets/images/_thumbs/<sha1 du nom de fichier>.webp : vignette 400 px de chaque image de premier
    niveau de public/assets/images, pour la médiathèque du Studio formation (views/admin/training/studio_edit.php).
    Une image sans vignette s'affiche comme avant, depuis l'original.
  - public/assets/images/opt/ et public/assets/img/ : variantes des images nommées par le code (liste JOBS).

Outil facultatif, à relancer après l'ajout d'images (Pillow requis : pip install pillow) :
    python3 tools/build-image-variants.py            # variantes manquantes seulement
    python3 tools/build-image-variants.py --force    # tout régénérer
Aucune étape de build n'en dépend. Contrôle : tests/Unit/PerfVagueFrontTest.php.
"""
from __future__ import annotations

import hashlib
import os
import subprocess
import sys

from PIL import Image, ImageOps

Image.MAX_IMAGE_PIXELS = None  # certains originaux dépassent 80 mégapixels

ROOT = os.path.normpath(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..'))
ASSETS = os.path.join(ROOT, 'public', 'assets')
FORCE = '--force' in sys.argv
WEBP = dict(format='WEBP', quality=80, method=6)

# (source, variante, largeur maximale, options d'enregistrement)
JOBS = [
    ('images/fog-team.jpg', 'images/opt/fog-team-960.webp', 960, WEBP),
    ('images/fog-team.jpg', 'images/opt/fog-team-2048.webp', 2048, WEBP),
    ('images/academy-hero.jpg', 'images/opt/academy-hero-960.webp', 960, WEBP),
    ('images/academy-hero.jpg', 'images/opt/academy-hero-1818.webp', 1818, WEBP),
    ('images/formation-de-specialite.jpg', 'images/opt/formation-de-specialite-1600.jpg', 1600,
     dict(format='JPEG', quality=82, optimize=True, progressive=True)),
    ('img/atak-link-lost-icon.png', 'img/atak-link-lost-icon-96.png', 96, dict(format='PNG', optimize=True)),
    ('img/atak-fx/broken-screen.png', 'img/atak-fx/broken-screen.webp', 2048, dict(format='WEBP', quality=88, method=6)),
    ('img/atak-fx/glitch-hud.png', 'img/atak-fx/glitch-hud.webp', 2048, dict(format='WEBP', quality=88, method=6)),
]
THUMB_WIDTH = 400
THUMB_EXTENSIONS = ('jpg', 'jpeg', 'png', 'webp', 'gif')


def long_path(path: str) -> str:
    """Windows : préfixe des chemins longs (certains noms d'images dépassent 200 caractères)."""
    path = os.path.abspath(path)
    return '\\\\?\\' + path if os.name == 'nt' and not path.startswith('\\\\?\\') else path


def resized(source: str, width: int) -> Image.Image:
    image = ImageOps.exif_transpose(Image.open(long_path(source)))
    if image.width > width:
        image = image.resize((width, round(image.height * width / image.width)), Image.LANCZOS)
    return image


def write(image: Image.Image, target: str, options: dict) -> int:
    os.makedirs(os.path.dirname(target), exist_ok=True)
    if options['format'] == 'JPEG':
        image = image.convert('RGB')
    elif image.mode not in ('RGB', 'RGBA'):
        image = image.convert('RGBA' if image.mode == 'P' or 'A' in image.getbands() else 'RGB')
    image.save(long_path(target), **options)
    return os.path.getsize(long_path(target))


def main() -> int:
    for source, target, width, options in JOBS:
        src, dst = os.path.join(ASSETS, source), os.path.join(ASSETS, target)
        if os.path.exists(long_path(dst)) and not FORCE:
            continue
        size = write(resized(src, width), dst, options)
        print(f'{source} ({os.path.getsize(long_path(src))} o) -> {target} ({size} o)')

    # Noms tels que git les stocke (octets UTF-8) : le sha1 est le même sous Windows et sur le serveur.
    listing = subprocess.run(
        ['git', '-c', 'core.quotepath=off', 'ls-files', '-z', 'public/assets/images'],
        cwd=ROOT, capture_output=True, check=True,
    ).stdout.split(b'\0')
    made = failed = 0
    for raw in listing:
        name = raw.decode('utf-8')[len('public/assets/images/'):] if raw else ''
        if name == '' or '/' in name or name.rsplit('.', 1)[-1].lower() not in THUMB_EXTENSIONS:
            continue
        target = os.path.join(ASSETS, 'images', '_thumbs', hashlib.sha1(name.encode('utf-8')).hexdigest() + '.webp')
        if os.path.exists(target) and not FORCE:
            continue
        try:
            write(resized(os.path.join(ASSETS, 'images', name), THUMB_WIDTH), target, dict(format='WEBP', quality=72, method=6))
            made += 1
        except Exception as error:  # image illisible : la médiathèque garde l'original
            failed += 1
            print('échec', name.encode('ascii', 'replace').decode(), error)
    print(f'vignettes : {made} écrite(s), {failed} échec(s)')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
