# Code secret de dossier SSE non vérifié à l’ouverture

## Contexte

Portail SSE privé — les dossiers peuvent recevoir un `unlock_code_hash` à la création (plan Portail SSE).

## Symptôme

Le badge « Code dossier défini » s’affichait, mais tout lecteur dans le périmètre ouvrait le dossier sans jamais saisir le code.

## Cause

Le hash était stocké, jamais contrôlé dans `caseShow` / PDF / comptes rendus.

## Correctif

- `SseCaseRepository::verifyUnlockCode()`
- Sas `/dossiers/{id}/deverrouiller` + session `sse_unlocked_cases`
- Bypass commandement (`atak.sse.grant`)
- Nettoyage à la sortie de session SSE

## Fichiers touchés

- `app/Repositories/SseCaseRepository.php`
- `app/Controllers/Web/SsePortalController.php`
- `views/atak/sse/case_unlock.php`
- `routes/web.php`
- `app/Services/Sse/SseAccessCodeService.php`

## Vérification

- `php -l` sur les fichiers PHP modifiés
- `phpunit --filter SsePortalPlanCoverageTest`

## Statut

corrigé
