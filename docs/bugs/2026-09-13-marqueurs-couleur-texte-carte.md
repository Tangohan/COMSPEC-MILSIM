# Marqueurs Athena — couleur et texte manquants sur la carte

## Contexte

13 septembre 2026. Les marqueurs remontent bien du jeu vers le poste (icônes visibles). Sur la carte Athena, le libellé métier (ex. « Parcours Bleu », « Hangar du 160th ») n’apparaissait qu’au clic / survol, pas à côté de l’icône. L’infobulle affichait aussi « RepÃ¨re · Ami » au lieu de « Repère · Ami ».

## Symptôme

- Icônes présentes, mais **pas de texte permanent** à côté (contrairement à la carte Arma / à l’attendu TOC).
- Couleur parfois peu lisible sur les glyphes sans libellé adjacent teinté.
- Popup : « RepÃ¨re » (encodage cassé) sous le titre correct « Parcours Bleu ».

## Cause

1. `labelSpanHtml` existait dans `arma-map-markers.js` mais n’était **jamais branché** dans `buildIconSpec` / `leafletDivIcon` (`showLabel: false` côté OTAN).
2. Catalogue `arma-marker-catalog.js` (et index bibliothèque) enregistré en UTF-8 avec libellés déjà double-encodés (`RepÃ¨re`, `FlÃ¨che`…).

## Correctif

1. Afficher le texte métier à droite de l’icône, teinté avec la couleur du marqueur (libellés génériques « Repère » exclus pour éviter le bruit).
2. Corriger les libellés FR les plus courants du catalogue + filet `fixUtf8Mojibake` / `fixLabel` à l’affichage.

## Fichiers touchés

- `public/assets/js/arma-map-markers.js`
- `public/assets/js/arma-marker-catalog.js`
- `docs/bugs/2026-09-13-marqueurs-couleur-texte-carte.md`

## Vérification

1. Recharger Athena (Ctrl+F5).
2. Carte du poste : un repère nommé (ex. Parcours Bleu) montre l’icône **et** le texte à côté, dans la couleur du marqueur.
3. Clic → popup sans « RepÃ¨re » (bien « Repère » si le type est affiché).

## Statut

corrigé (sources web) — à valider après rechargement navigateur
