# Bug — syntaxe flèche PHP dans le portail missions

## Contexte

Fiche détail du portail missions (`/back-office/missions/{id}`), vue PHP.

## Symptôme

Page d’erreur : `syntax error, unexpected token "use", expecting "=>"` à l’ouverture du récapitulatif.

## Cause

Une fonction flèche PHP ne peut pas déclarer de clause `use (...)` (capture automatique des variables du scope parent).

## Correctif

Remplacer `static fn (...) use ($baseUrl): string => ...` par `static fn (...): string => ...`.

## Fichiers touchés

- `views/admin/missions_portal/show.php`

## Vérification

`php -l` OK ; GET `/back-office/missions/1?vue=recapitulatif` (et participants / atak / liaisons) renvoie 200 avec le contenu attendu.

## Statut

Corrigé
