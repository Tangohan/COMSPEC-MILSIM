# SSE — logs de migration affichés sur la page (confidentialité / Workspace)

## Contexte

`/public/atak/sse/confidentialite` et Intelligence Workspace montraient un bandeau technique :
`[OK] sse_entity_index (déjà présente)` (répété / collé en tête de page).

## Symptôme

Sortie brute de migrations SQL au-dessus de l’UI SSE (jargon tables / schéma).

## Cause

Les repos SSE appellent des migrations bootstrap au constructeur. Le `$log` par défaut
faisait `echo` → fuite HTTP **avant** `Response::send()` (hors `ob_start` des vues).

## Correctif

1. `$log` défaut = **no-op** dans les migrations SSE ; CLI via `run-migrations.php` passe `$sseCliLog`.
2. Helper `SilentSchemaMigration` (+ `runMany`) sur **tous** les repos SSE concernés.
3. Filet `Application::run()` : buffer d’output jeté avant l’envoi de la `Response`.

## Fichiers touchés

- `app/Core/Application.php`
- `app/Support/SilentSchemaMigration.php`
- `app/Repositories/Sse*.php` (ensureSchema / constructeurs)
- `bootstrap/atak_sse_*_migration.php`
- `run-migrations.php` (`$sseCliLog`)

## Vérification

Recharger Intelligence Workspace / confidentialité : plus de bandeau `[OK] sse_…`.

## Statut

corrigé en code — à déployer
