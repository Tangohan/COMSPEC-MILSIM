# Connect / ATAK — FRAGO et ordres C2 peu lisibles sur mobile

## Contexte

Après appariement `/connect` → carte `/atak` (session téléphone), l’onglet
Ordres affichait des cartes denses, payload FRAGO en une seule ligne.

## Symptôme

FRAGO et ordres C2 difficiles à lire sur téléphone (texte compact, petits
boutons, pas de rubriques Situation / Mission / …).

## Cause

Rendu unique « desktop » dans `ATAKOrders.renderList()` : payload brut,
sans cards différenciées FRAGO / C2 ni cibles tactiles larges.

## Correctif

- Cartes distinctes (badge FRAGO / C2, accents type)
- FRAGO découpé en rubriques SMEAC
- Boutons pleine largeur / grille 2 colonnes en mobile + session téléphone
- Cache assets `1.4.9`

## Fichiers touchés

- `public/assets/js/atak-orders.js`
- `public/assets/css/atak.css`
- `views/atak.php` (hint onglet)
- `storage/app_version.json`

## Vérification

Ouvrir `/connect` → carte → onglet Ordres : FRAGO avec rubriques, ACK
en gros bouton.

## Statut

Corrigé
