# Page Alertes — titre d’annonce en colonne étroite

## Contexte

Restyle DSFR de `/alertes` : les cartes d’annonce passent d’un filet visible (colonne grille) à un filet `box-shadow` inset.

## Symptôme

Le titre et le texte d’une annonce s’affichent sur une largeur d’environ 4 px : un mot par ligne, carte illisible.

## Cause

La carte restait en `display: grid` avec `grid-template-columns: 0.25rem minmax(0, 1fr)` alors que le filet (premier enfant) n’était plus affiché. Le contenu se plaçait donc dans la **première** colonne (0,25 rem).

## Correctif

Passer la carte en `display: block` et conserver uniquement le filet coloré en ombre intérieure.

## Fichiers touchés

- `public/assets/css/alerts-page.css`
- `views/alerts/index.php`

## Vérification

Aperçu local des cartes : titre sur une ligne, filet rouge/bleu visible à gauche, bouton Masquer à droite.

## Statut

Corrigé
