# Digest e-mail SSE via CRON

## Contexte

Les digests RH / LMS partent déjà par cron. Le moteur SSE nocturne produisait
des suggestions sans alerter les analystes par e-mail.

## Correctif

- Événement `SSE_ANALYST_DIGEST` + template `sse_analyst_digest`
- Service `SseAnalystDigestService` (dédup 1× / jour / communauté)
- Cron `sse_analyst_digest` + envoi aussi en fin de `sse_analytical_nightly`
- Destinataires : permissions `atak.sse.*` / `admin.access`
- Préférence compte « Point quotidien renseignement SSE »

## Contenu du mail

Rapprochements à trancher, signaux ouverts, nouvelles fiches terrain (24 h),
dossiers d’intérêt encore actifs.

## Vérification

1. Déployer PHP.
2. `php scripts/cron-run.php sse_analyst_digest` (ou `/cron/run`).
3. Compte avec droit SSE : e-mail reçu si du travail en file ; sinon « rien à signaler ».

## Statut

corrigé en code — à déployer
