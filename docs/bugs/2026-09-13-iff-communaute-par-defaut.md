# Identification ami / ennemi : communauté figée à 1

## Contexte

Le défi d’identification (IFF) construisait l’identifiant de mission avec le numéro de communauté, ou **1** si la session n’en fournissait pas.

## Symptôme

Sans communauté clairement identifiée, les défis et réponses pouvaient se mélanger avec une autre opération, ou être visibles hors de la communauté concernée.

## Cause

Valeur de repli à 1 côté serveur, sans contrôle d’offre ni d’appartenance. Un identifiant de mission fourni par le client était accepté tel quel.

## Correctif

La communauté vient de la session ou de la clé de liaison. Sans communauté, l’accès est refusé. Un identifiant de mission n’est retenu que s’il appartient à cette communauté. L’offre « carte et liaisons » est exigée, comme pour le reste de la carte.

## Fichiers touchés

- `app/Controllers/Api/IffController.php`
- `public/assets/js/atak-iff.js`

## Vérification

Tests unitaires de présence du contrôle et de l’absence de repli à 1 dans le script du poste.

## Statut

corrigé
