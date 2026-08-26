# Signalement Overwatch — page d’incident au lieu d’un refus temporaire

## Contexte

Production `athena.ttrd.fr`, POST `/api/atak/mod-report` (URI `/public/api/atak/mod-report`). Client Overwatch, sans session portail. Corrélation `34b6eb1e6055ca15`. `APP_ENV=production`. Une mise à jour du poste tournait en parallèle (connexion principale OK, d’autres connexions en « Operation not permitted »).

## Symptôme

Le jeu recevait une page HTML d’incident (« Exception non gérée ») au lieu d’un refus temporaire. Pile : `Database::getPdo()` → `ReconImageRepository::__construct` → `Container::get(AtakApiController)` → routage.

## Cause

1. **Câblage** : toutes les routes ATAK (y compris le signalement d’erreur) construisent `AtakApiController` avec toutes ses dépendances. `ReconImageRepository` ouvrait la base dès le constructeur, alors que le signalement n’en a pas besoin. D’autres dépôts du même graphe (briefing, accès anticipé) faisaient de même.
2. **Infra** : pendant une mise à jour ou un envoi FTP, les connexions supplémentaires échouent (socket `localhost`, trop de sessions). La connexion principale du pipeline peut rester bonne.
3. **Réponse** : une exception non rattrapée avant le contrôleur pouvait encore servir une page HTML au jeu.

## Correctif

- Images recon, diapositives de briefing et accès anticipé : connexion à la première requête, pas au constructeur.
- Le signalement n’injecte plus le dépôt des images recon.
- Filet sur les routes ATAK : refus temporaire en langage clair, sans pile ni chemins de fichiers. Une seule tentative de connexion ; `localhost` est forcé en TCP `127.0.0.1`. L’indice FTP dans le journal serveur est conservé.

## Fichiers touchés

- `app/Controllers/Api/AtakApiController.php`
- `app/Core/Container.php`
- `app/Repositories/ReconImageRepository.php`
- `app/Repositories/TacticalBriefingSlideRepository.php`
- `app/Repositories/TacticalBriefingSlideCommentRepository.php`
- `app/Repositories/AtakBetaRegistrationRepository.php`
- `app/Support/LazyDatabaseConnection.php`
- `app/Support/TacticalApiErrorRenderer.php`
- `app/Middleware/ComspecTacticalApiMiddleware.php`
- `app/Core/Application.php`
- `app/Core/ExceptionHandler.php`
- `app/Core/Database.php`
- `public/index.php`
- `tests/Unit/AtakModReportWiringAssetTest.php`
- `tests/Unit/TacticalApiErrorRendererTest.php`
- `tests/Unit/DatabaseLostConnectionTest.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE 233)

## Vérification

- Tests unitaires ci-dessus.
- Recette : pendant une mise à jour du poste, un signalement Overwatch doit recevoir un refus temporaire lisible, pas une page d’incident. Hors mise à jour, le signalement s’enregistre comme avant.

## Statut

corrigé
