# Pastilles de rapports absentes sur la carte web

## Contexte

En mission, les rapports (SPOTREP, IMINI, etc.) apparaissent comme des pastilles 3D compactes : rectangle sombre, barre colorée, type en capitales, temps ou distance en seconde ligne. Sur la carte du poste, les mêmes objets n’étaient que de petites icônes ou des ronds colorés, sans ce langage.

## Symptôme

Le commandement ne reconnaissait pas d’un coup d’œil un SPOTREP ou un IMINI sur la carte Athena / ATAK : il fallait ouvrir la fiche ou le journal.

## Cause

Le rendu carte web (marqueurs Arma, tableau de situation, signalements TACMAP) n’employait pas la pastille type Arma/ACE. Les rapports tactiques n’étaient pas non plus posés systématiquement à leur position.

## Correctif

Pastilles HTML/CSS calquées sur le libellé 3D du jeu, réutilisées sur la carte du poste, TACMAP / Overwatch et la vue terrain d’une opération. Couleur de barre selon le type ; deuxième ligne = temps écoulé si l’heure d’émission est connue.

## Fichiers touchés

- `public/assets/js/tactical-marker-chip.js`
- `public/assets/css/tactical-marker-chip.css`
- `public/assets/js/arma-map-markers.js`
- `public/assets/js/atak-map.js`
- `public/assets/js/atak-sitrep.js`
- `public/assets/js/comspec-operational-map.js`
- `public/assets/js/ops-workspace-planning.js`
- `views/atak.php`, `views/tacmap.php`, `views/overwatch/index.php`

## Vérification

Fixture `tmp-tactical-marker-chips-preview.html` (SPOTREP or, IMINI rouge, SITREP, CONTACT). Tests `TacticalMarkerChipAssetTest`.

## Statut

corrigé
