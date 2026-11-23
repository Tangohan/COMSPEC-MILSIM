# Espace aérien : impossible de valider une QT depuis la vue Pilotes

## Contexte
Back-office > Espace aérien, onglet Pilotes (`?vue=pilotes`).

## Symptôme
La carte d'un pilote affiche « QT prêt — en attente d'un instructeur », sans aucun bouton. On ne voit pas comment valider la qualification.

## Cause
La validation n'existait que dans la file « À valider » de l'onglet Ligne de vol. La vue Pilotes n'avait ni action ni lien vers cette file.

## Correctif
Sous le palier en attente, la carte affiche les mêmes actions que la file : « Valider après vol d'évaluation » et « Ajourner », avec les mêmes règles (instructeur de la filière, jamais sa propre proposition). Si la proposition n'est pas encore créée (palier atteint, relevé du mod à venir), un message l'explique et renvoie à la file.

## Fichiers touchés
- `views/admin/airspace/index.php`
- `public/assets/css/espace-aerien.css`

## Vérification
`php -l` de la vue. Le rendu reste à vérifier sur le site.

## Statut
corrigé à vérifier sur le site
