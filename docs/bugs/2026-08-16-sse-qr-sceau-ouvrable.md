# QR sceau poste — URL ouvrable

## Contexte

Chemise de dossier SSE : QR « sceau poste de travail ».

## Symptôme

Le QR encodait un texte multiligne : le scan n’ouvrait aucune page.

## Correctif

- Jeton HMAC (`SseSealTokenService`) + URL `/atak/sse/sceau/{token}`.
- Page de vérification publique + CTA ouvrir le dossier si session SSE OK.
- Chemise régénère le QR avec l’URL.

## Fichiers touchés

- `app/Services/Sse/SseSealTokenService.php`
- `app/Support/SseDocumentMarkings.php`
- `views/atak/sse/partials/case_cover.php`
- `views/atak/sse/seal_show.php`
- `app/Controllers/Web/SsePortalController.php`
- `routes/web.php`

## Vérification

Scanner le QR ou coller l’URL → page sceau ; avec session → ouvrir le dossier.

## Statut

Corrigé
