# PDF dossier SSE — UI chemise et pages internes

## Contexte

Export PDF « dossier complet » (`SseCasePdfService`) : chemise déjà proche de l’écran,
pages suivantes encore en `h2` + `<pre>` / tableaux gris génériques.

## Symptôme

Rendu papier inégal : page de garde correcte, corps du dossier peu lisible et sans
langage « archive classifiée ».

## Cause

Styles TCPDF 100 % inline, sans helpers partagés ; pas de pied de page natif ;
pièces rédactionnelles et inventaires sans chrome de classification.

## Correctif

- Cadre chemise (filet gauche + bordure), bandeau version, titre « Chemise de dossier »,
  grille de faits alternée, bloc empreintes encadré, tampons recentrés.
- Bandeaux de section numérotés + filet classification.
- Flash / CR en feuille rédactionnelle (encadré) ; tableaux à en-tête coloré ;
  notes en cartes ; preuves en inventaire ; images avec bandeau.
- Pied de page TCPDF (réf. · classification · n° page).

## Fichiers touchés

- `app/Services/Sse/SseCasePdfService.php`

## Vérification

Exporter `/atak/sse/dossiers/{id}/pdf` (ou flux lecteur) : chemise plus nette, pages
01–07 cohérentes, footer avec pagination.

## Statut

corrigé
