# Marqueurs Athena — couleur, texte et encodage sur la carte

## Contexte

13 septembre 2026. Les marqueurs remontent bien du jeu vers le poste (icônes visibles). Sur la carte Athena, le libellé métier (ex. « Parcours Bleu », « Hangar du 160th ») n’apparaissait qu’au clic / survol, pas à côté de l’icône. L’infobulle affichait aussi « RepÃ¨re - Ami » au lieu de « Repère - Ami ».

## Symptôme

- Icônes présentes, mais **pas de texte permanent** à côté (contrairement à la carte Arma / à l’attendu TOC).
- Couleur parfois peu lisible sur les glyphes sans libellé adjacent teinté.
- Popup / liste : « RepÃ¨re - Ami » (encodage cassé) sous le titre correct « Parcours Bleu ».
- Les textes figés de l’interface (« REPÈRE CARTE », phrase d’aide) restent corrects — seul le champ dynamique du type / symbole est touché.

## Cause

1. `labelSpanHtml` existait dans `arma-map-markers.js` mais n’était **jamais branché** dans `buildIconSpec` / `leafletDivIcon` (`showLabel: false` côté OTAN).
2. Catalogue `arma-marker-catalog.js` (et index bibliothèque) enregistré en UTF-8 avec libellés déjà double-encodés (`RepÃ¨re`, `FlÃ¨che`…).
3. La popup (`atak-map.js`) et la liste (`atak-markers.js`) affichaient `data.symbolName` **brut**, sans passer par `fixUtf8Mojibake` — alors que `typeLabelFr` / `displayLabelOf` corrigeaient déjà l’encodage. D’où le titre correct (« Parcours Bleu ») et la ligne symbole cassée (« RepÃ¨re - Ami »).

## Correctif

1. Afficher le texte métier à droite de l’icône, teinté avec la couleur du marqueur (libellés génériques « Repère » exclus pour éviter le bruit).
2. Corriger les libellés FR les plus courants du catalogue + filet `fixUtf8Mojibake` / `fixLabel` à l’affichage.
3. Exporter `fixUtf8Mojibake` et l’appliquer dans la popup et la liste ; préférer `typeLabelFr` (déjà décodé) à `symbolName` brut ; ne pas répéter l’affiliation si elle est déjà dans le libellé type.

## Fichiers touchés

- `public/assets/js/arma-map-markers.js`
- `public/assets/js/arma-marker-catalog.js`
- `public/assets/js/atak-map.js`
- `public/assets/js/atak-markers.js`
- `docs/bugs/2026-09-13-marqueurs-couleur-texte-carte.md`

## Vérification

1. Recharger Athena (Ctrl+F5).
2. Carte du poste : un repère nommé (ex. Parcours Bleu) montre l’icône **et** le texte à côté, dans la couleur du marqueur.
3. Clic → popup : plus de « RepÃ¨re » ; on lit « Repère - Ami » (ou le type FR équivalent).

## Statut

corrigé (sources web) — à valider après déploiement / rechargement navigateur
