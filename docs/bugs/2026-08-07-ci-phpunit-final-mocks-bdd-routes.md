# CI PHPUnit — échecs préexistants (final mocks, BDD, routes)

## Contexte

Job GitHub Actions `CI / php` rouge sur main et sur la PR 2FA (#167), sans lien avec le TOTP.

## Symptôme

`Tests: … Errors: 25, Failures: 5` — PHPUnit exit ≠ 0 ; PHPStan non atteint.

## Cause

Plusieurs causes distinctes :

1. **Mocks de classes `final`** — PHPUnit 11 refuse `createMock()` sur `ConfigurationUpdateRepository`, `PedagogyRepository`, `RoleAssignmentLogRepository`, `FeatureGateService`, etc.
2. **BDD absente en CI** — `MilitaryReferentialIntegrityTest` et constructeurs API connectent MySQL dès le `setUp` / `__construct`.
3. **`Response::body()` manquant** — contrats API appelaient un getter inexistant.
4. **`ModuleReleaseAccessResolver`** — une règle sans canal était normalisée en `PROD`, donc ignorée sur `TEST`.
5. **Secret Stripe `whsec_`** — découpe `substr(..., 5)` au lieu de 6 caractères.
6. **Routes access-control** — contrôleur présent, routes absentes de `routes/web.php`.

## Correctif

- Bootstrap tests + `dg/bypass-finals`
- Lazy-init ATAK dans `OperationsApiController` ; skip militaire si BDD down
- Getter `Response::body()` ; fix scope canaux ; fix `whsec_` ; routes + DI access-control

## Fichiers touchés

- `tests/bootstrap.php`, `phpunit.xml`, `composer.json`
- `app/Core/Response.php`, `app/Services/Platform/ModuleReleaseAccessResolver.php`
- `app/Support/Stripe/StripeWebhookSignature.php`
- `app/Controllers/Api/OperationsApiController.php`
- `routes/web.php`, `app/Core/Container.php`
- tests unitaires / contrats concernés

## Vérification

`vendor/bin/phpunit` puis `vendor/bin/phpstan analyse --memory-limit=1G`

## Statut

corrigé
