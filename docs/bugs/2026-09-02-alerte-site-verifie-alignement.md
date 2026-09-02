# Barre d’alerte — « Site vérifié » sous le titre

## Contexte

Barre d’annonce sous le menu (style compact Critique / Info / etc.), notamment sur le tableau de bord membre. Les annonces officielles du site portent le libellé « Site vérifié ».

## Symptôme

Sur une barre Critique, le titre restait à gauche et « Site vérifié » (coche verte + texte) s’affichait **sous** le message, collé à gauche. Le bouton pour masquer l’annonce restait tout à droite. Le badge officiel paraissait un sous-titre, pas une mention de la barre.

## Cause

Le badge était ajouté **dans** le bloc texte de la barre (`banner-content`), déjà en colonne (titre, puis détail). Il s’empilait donc sous le titre.

## Correctif

Le badge et le bouton de masquage forment un groupe à **droite** de la barre (`banner-end`, flex, `margin-left: auto`). Le titre et le détail restent à gauche. Libellé inchangé : « Site vérifié ».

## Fichiers touchés

- `views/partials/navbar_info_banners.php`
- `public/assets/css/navbar-info-banners.css`
- `tests/Unit/NavbarInfoBannerVerifiedAlignAssetTest.php`

## Vérification

Test unitaire d’actifs : le badge n’est plus ajouté dans le bloc titre ; le CSS pousse le groupe à droite. Mesure locale à 1280 px : « Critique » et le titre à gauche, « Site vérifié » vers 1174 px, croix de masquage en dernier (~1255 px), même ligne.

## Statut

Corrigé.
