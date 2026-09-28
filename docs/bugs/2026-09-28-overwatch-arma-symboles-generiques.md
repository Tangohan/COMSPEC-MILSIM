# Overwatch — kit Arma 3 : formes génériques au lieu des icônes jeu

## Contexte

Tiroir Marqueur, kit **Arma 3**. Les PNG military/handdrawn sont déjà présents sous `public/assets/markers/arma/a3/.../markers/`.

## Symptôme

Tous les symboles Arma se ressemblaient (losanges / carrés / cercles verts génériques). Le « Triangle » n’était pas un triangle. Rendu peu fidèle à la carte Arma 3.

## Cause

`shouldUsePngGlyph` refusait les silhouettes `military` / `handdrawn` (et refusait aussi tout chemin `pngNeedsColorMask`), forçant le repli SVG approximatif. Overwatch Beta ne chargeait pas non plus le CSS du masque couleur.

## Correctif

- Autoriser les PNG military/handdrawn via masque CSS (teinte = couleur du marqueur).
- Vignettes du kit Arma 3 construites avec ce masque.
- Styles masque dans `atak-overwatch-beta.css`.
- Repli SVG amélioré (vrai triangle, drapeau, marques sur losanges) si PNG absent.

## Fichiers touchés

- `public/assets/js/arma-map-markers.js`
- `public/assets/js/atak-overwatch-ops.js`
- `public/assets/css/atak-overwatch-beta.css`
- `app/Support/DevDispatchCatalog.php` (UPDATE #00725)
- tests associés

## Vérification

Ctrl+F5 → Marqueur → Arma 3 : silhouettes distinctes (triangle, warning, flag, start/end…).

## Statut

Corrigé.
