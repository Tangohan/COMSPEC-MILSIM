# ATAK — « Connexion perdue » alors que la carte est ouverte

## Contexte

Poste de commandement sur `athena.ttrd.fr/atak`. La page s’affiche, pastille
« liaison active », mais bandeau rouge et pastille « Connexion perdue ».

## Symptôme

Console : 503 sur `api/atak/weather`, `api/cas`, `api/atak/stats`. Le reste de
la carte (HTML, ping) peut encore répondre.

## Cause

1. Un 503 sur un poll secondaire (météo, CAS, stats) mettait **toute** l’API
   en pause, **y compris le ping**.
2. Pendant la pause, le client renvoyait un 503 **simulé** au ping : le bandeau
   « connexion perdue » restait affiché en boucle.
3. Météo était relue toutes les 3 s, comme les unités.

## Correctif

- Le ping n’est plus mis en pause ; le bandeau ne suit que le ping réel.
- Météo / CAS / stats en 503 ne coupent plus la carte.
- Lecture météo / CAS : réponse vide plutôt qu’une erreur serveur.
- Météo toutes les 30 s. Polls ATAK hors quota « scraping » et hors sas
  maintenance lourd (RBAC à chaque GET).

## Fichiers touchés

- `public/assets/js/atak-socket.js`
- `views/atak.php`
- `app/Controllers/Api/AtakApiController.php`
- `public/index.php`
- `app/Middleware/RateLimitMiddleware.php`

## Vérification

`php -l` sur les fichiers PHP. Recette : ouvrir `/atak`, recharger sans cache ;
le bandeau ne doit plus rester si le ping répond.

## Statut

corrigé (déploiement prod requis)
