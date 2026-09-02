# Documents — aperçu PDF bloqué (scripts)

## Contexte

Ouverture d’un PDF publié dans la bibliothèque (lecteur Athena).

## Symptôme

L’aperçu reste vide. La console indique qu’un script extérieur est refusé, puis parfois une ressource « hors ligne ».

## Cause

Le lecteur PDF était chargé depuis un site tiers. En production, seuls les scripts du site (et Tailwind) sont autorisés. Le service d’arrière-plan renvoyait aussi une page « hors ligne » quand ce chargement extérieur échouait.

## Correctif

Le lecteur est servi depuis Athena. Les appels vers d’autres sites ne passent plus par le cache hors ligne du navigateur.

## Fichiers touchés

- `views/documents/show.php`
- `public/assets/vendor/pdfjs/pdf.mjs`
- `public/assets/vendor/pdfjs/pdf.worker.min.mjs`
- `public/.htaccess`
- `public/sw.js`

## Vérification

Ouvrir un PDF publié : les pages s’affichent, page suivante / zoom. Console sans refus de script pour le lecteur.

## Statut

corrigé
