# SSE — menu contextuel (clic droit)

## Contexte

Sur les pages du bureau SSE (investigations / toiles, dossiers, identités, tableaux…), le clic droit du navigateur n’offrait aucune action métier.

## Symptôme

Clic droit sur une carte d’investigation (`/atak/sse/toiles`) ou sur le canevas : menu système du navigateur uniquement.

## Cause

Aucun gestionnaire de menu contextuel n’était branché dans le workspace SSE.

## Correctif

- Script générique `sse-context-menu.js` chargé par `_layout.php` : cartes, lignes de tableau, fiches identité, zone vide (actions de page).
- Sur le canevas d’une toile : actions nœud (fiche, lien, copier, retirer) et actions canevas (réorganiser, recadrer, ajouter ici).
- Styles `.sse-ctx-menu*` dans `sse_portal.css`.

## Fichiers touchés

- `public/assets/js/sse-context-menu.js`
- `public/assets/js/sse-mesh.js`
- `public/assets/css/sse_portal.css`
- `views/atak/sse/_layout.php`
- `views/atak/sse/meshes.php`
- `views/atak/sse/mesh_show.php`

## Vérification

1. `/atak/sse/toiles` → clic droit sur une carte : Ouvrir / nouvel onglet / copier la référence.
2. Ouvrir une toile → clic droit sur un nœud et sur le fond du canevas.
3. Autres listes (dossiers, documents, sites) → clic droit sur une ligne avec « Ouvrir ».

## Statut

corrigé
