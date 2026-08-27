# Bug — titres flash « Accès refusé » et fiche « Utilisateur non trouvé »

## Contexte

Messages d’erreur visibles sur le portail (toasts / bandeaux flash et fiches personnel).

## Symptôme

- Un simple besoin de se connecter affichait le titre **Accès refusé**.
- Une fiche personnel absente renvoyait le texte brut `Utilisateur non trouvé.` sans page ni action possible.

## Cause

- L’heuristique des titres flash matchait trop large (`authentification|session|connecter`).
- Le contrôleur personnel répondait en corps texte 404/403 sans vue métier.

## Correctif

- Titres flash différenciés : Connexion requise, Session expirée, Compte inaccessible, Accès refusé, Introuvable.
- Messages middleware et pages 403/404 plus actionnables.
- Fiches absentes → page 404 soignée (GET) ou toast + retour annuaire (POST).

## Fichiers touchés

- `app/Support/FlashAlertTitle.php`
- `views/partials/flash_toasts.php`, `views/partials/flash_message.php`
- `views/errors/403.php`, `views/errors/404.php`, `lang/fr/errors.php`, `lang/en/errors.php`
- `app/Middleware/AuthMiddleware.php`, `OrganizationAdminMiddleware.php`, `AccessControlMiddleware.php` (+ autres middlewares « Connectez-vous »)
- `app/Controllers/Web/PersonnelController.php`
- `views/personnel/edit.php`, `views/personnel/file.php`
- `tests/Unit/FlashAlertTitleTest.php`

## Vérification

`phpunit tests/Unit/FlashAlertTitleTest.php` ; contrôle manuel GET `/personnel/{id-inexistant}` → page « Fiche introuvable ».

## Statut

Corrigé
