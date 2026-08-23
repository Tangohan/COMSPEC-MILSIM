# PDF dossier SSE — marge droite trop large

## Contexte

Export PDF « dossier complet » (`SseCasePdfService`), page de garde « Chemise de dossier ».

## Symptôme

Le bloc (bandeaux, tableaux, filet de pied de page) est collé à gauche : marge gauche normale, grande bande vide à droite. Les filets rouges s’arrêtent avec le contenu au lieu d’aller d’une marge à l’autre.

## Cause

TCPDF interprète `width="3"` (sans unité) comme **des pixels**, pas des millimètres. Sur un tableau à deux colonnes, la première cellule devient minuscule et la seconde **garde sa largeur initiale** (moitié de la page). Le contenu n’occupe donc qu’environ 50 % de la largeur utile.

Même schéma sur les bandeaux de section, les titres et les cartes de notes. Le pied de page enchaînait deux `Cell(0, …)` : le numéro de page ne pouvait plus se caler sur la vraie marge droite.

## Correctif

- Filets de classification en pourcentages qui totalisent 100 % (`1.6%` + `98.4%`).
- Titres de section : `1.6%` + `1.8%` + `96.6%`.
- Pied de page : filet et trois colonnes calés sur `lMargin` / `rMargin`.

## Fichiers touchés

- `app/Services/Sse/SseCasePdfService.php`

## Vérification

Réexporter `/atak/sse/dossiers/{id}/pdf` : bandeaux et tableaux d’une marge à l’autre, « Page x / n » aligné à droite, plus de colonne vide.

## Statut

Corrigé
