# Accueil public — vidéos hero ne démarrent pas

## Contexte

Page d’accueil (`views/home/index.php`) — clips `hero-athena*.mp4` déposés sur le serveur (`public/assets/video/`).

## Symptôme

Le hero reste sur les images statiques ; les MP4 ne s’affichent pas alors que les fichiers sont bien en FTP et l’URL répond en 200.

## Cause

1. **Pas un bug de nommage** : `data-stem="hero-athena-2"` et `src="…/hero-athena.mp4"` sont sur **deux slides différents** du carrousel (normal).
2. **Fichiers OK côté HTTP** : `https://athena.ttrd.fr/public/assets/video/hero-athena.mp4` → 200, `Content-Type: video/mp4`, ranges supportés.
3. **Timeout JS trop agressif** : repli sur les images après **5 s** si aucune frame peinte — insuffisant pour des MP4 de ~12 Mo (chargement + décodage, surtout si le `moov` n’est pas en tête du fichier).

## Correctif

- Délai de décodage dynamique selon la taille (`data-bytes`, 14–40 s).
- Re-vérification tant que la vidéo charge (`readyState` / `networkState`).
- `preload="auto"` sur tous les clips présents ; poster JPG sur le premier clip.

## Fichiers touchés

- `views/home/index.php`

## Vérification

1. Ouvrir `https://athena.ttrd.fr/` (redirige vers `/public/`).
2. Attendre ~15 s : la vidéo hero doit remplacer le fond photo.
3. Si échec persistant : réencoder en **H.264 + AAC**, option **fast start** (moov au début), idéalement aussi un `.webm`.

## Statut

Corrigé (timeout / preload). **Suite 2026-08-07** : les MP4 restent en HEVC / QuickTime ;
la sonde les écarte et le hero reste en photos — voir
`docs/bugs/2026-08-07-hero-videos-hevc-rejetes.md` et `docs/VIDEO-HERO-ENCODAGE.md`.
