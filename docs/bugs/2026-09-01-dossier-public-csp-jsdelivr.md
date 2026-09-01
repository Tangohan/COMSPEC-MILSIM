# Dossier public — CSP et bibliothèques jsDelivr

## Contexte

Vue publique d’une fiche personnel (`/personnel/{slug}?view=public`). La CSP du portail autorise `script-src` / `style-src` sur `'self'` (plus Tailwind et Google Fonts), **sans** `cdn.jsdelivr.net`.

## Symptôme

La console signalait des violations CSP :

- feuilles `animate.css` et `aos.css` depuis jsDelivr (`style-src`) ;
- scripts Lucide, Iconify et AOS depuis jsDelivr (`script-src`) ;
- `Failed to load resource 504 (offline)` pour ces mêmes URL (le service worker renvoie 504 quand le réseau / la CSP bloque).

Les icônes et animations concernées n’apparaissaient pas : les fichiers étaient bloqués.

## Cause

Le layout portail chargeait par défaut les packs `icons` et `animation` (`config/cdn_libraries.php`), qui pointaient vers jsDelivr. Aucune vue personnel n’utilise Lucide, Iconify, Animate.css ni AOS. Alpine tombait aussi sur jsDelivr si le fichier local manquait.

## Correctif

- Packs portail par défaut : aucun. Lucide, Iconify, Animate.css et AOS retirés (non utilisés).
- Alpine 3.14.3 servi depuis `public/assets/vendor/alpinejs/` (`'self'`), plus de repli jsDelivr.
- Preconnect jsDelivr uniquement si un pack demandé pointe vraiment vers cet hôte.

La CSP n’a pas été élargie.

## Fichiers touchés

- `config/cdn_libraries.php`
- `app/Support/cdn_media.php`
- `views/layout/main.php`
- `views/partials/cdn_media_libs.php`
- `views/layout/forum.php` (commentaire des packs)
- `public/assets/vendor/alpinejs/alpine.min.js`
- `tests/Unit/PersonnelPublicLayoutCspAssetTest.php`

## Vérification

`php vendor/bin/phpunit tests/Unit/PersonnelPublicLayoutCspAssetTest.php`

La fiche publique ne doit plus demander animate.css, AOS, Lucide ni Iconify. Alpine reste chargé en local.

## Statut

corrigé
