# Fiche journal — variable de titre manquante

## Contexte

Page publique `/nouveautes/update/00233` (et les autres bulletins). Le journal Nouveautés affiche une fiche dédiée via le partial `dispatch_article.php`. En production, un déploiement mixte était possible : le catalogue connaissait déjà UPDATE 233, mais la variable de niveau de titre n’était pas toujours fournie.

## Symptôme

Ouverture d’un bulletin (lien direct ou depuis Nouveautés) : page d’incident au lieu de la fiche.

Erreur : `Undefined variable $dispatchHeadingTag` dans `views/partials/dispatch_article.php` ligne 6.

`GET /nouveautes/update/00233` → `SitePagesController->dispatch()` → `views/site/dispatch.php` → `layout/marketing.php`.

## Cause

Le partial lisait `$dispatchHeadingTag` dans le branchement « titre autorisé » sans l’avoir d’abord fixé.

Le test `in_array($dispatchHeadingTag ?? 'h1', …)` réussissait grâce au défaut `'h1'`, puis le code utilisait `$dispatchHeadingTag` encore absent. `views/site/dispatch.php` et `SitePagesController::dispatch()` ne passaient pas la variable. Seul le bloc mis en avant du journal (`changelog.php`) la posait (`h3`).

## Correctif

- Le partial pose `$dispatchHeadingTag = $dispatchHeadingTag ?? 'h1'` avant tout usage, puis n’accepte que `h1` / `h2` / `h3`.
- Fiche dédiée : `h1` (contrôleur + vue `dispatch.php`).
- Bulletin mis en avant sous le titre de section du journal : `h3` (déjà en place). Les cartes de liste n’utilisent pas ce partial.

## Fichiers touchés

- `views/partials/dispatch_article.php`
- `views/site/dispatch.php`
- `app/Controllers/Web/SitePagesController.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE 238)
- `tests/Unit/DispatchArticleViewAssetTest.php`
- `tests/Unit/DevDispatchCatalogTest.php`

## Vérification

Tests unitaires : le partial se rend sans variable (titre en `h1`) ; la fiche dédiée pose `h1` ; le catalogue compte 54 UPDATE / 58 bulletins. Recette : ouvrir `/nouveautes/update/00233` — la fiche s’affiche, titre principal `h1`.

## Statut

corrigé (sources) — recharger Nouveautés ; pas de nouveau pack jeu
