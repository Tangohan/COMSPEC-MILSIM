# Badge « Hors liaison » instable sur les fiches terminal

## Contexte

Onglet Terminaux du poste ATAK : cartes d’opérateurs (indicatif à gauche, état de liaison à droite).

## Symptôme

Le badge d’état (souvent « Hors liaison ») sautait visuellement : il changeait de place selon la longueur de l’indicatif, se cassait sur deux lignes, ou paraissait bouger par rapport au titre.

## Cause

L’en-tête était un flex sans largeur réservée pour le badge. Titre et badge se disputaient la place (`align-items: baseline`, pas de `nowrap`). L’animation d’alerte appliquait aussi un `scale`, ce qui faisait paraître le badge en mouvement.

## Correctif

- En-tête en flex figé : titre elliptique, badge à largeur réservée, `nowrap`, aligné en haut à droite.
- Pulsation d’alerte par opacité / liseré, sans changement d’échelle.
- Versions Overwatch et liaison Athena affichées sur des lignes dédiées, plus dans Type.

## Fichiers touchés

- `public/assets/css/atak.css`
- `public/assets/js/atak-terminals.js`
- `views/atak.php`
- `tests/Unit/AtakTerminalsAssetTest.php`

## Vérification

Test unitaire `AtakTerminalsAssetTest` : largeur réservée, `nowrap`, pas de `scale` sur le badge de fiche, Type sans version enfouie, lignes Overwatch / Liaison Athena.

## Statut

corrigé
