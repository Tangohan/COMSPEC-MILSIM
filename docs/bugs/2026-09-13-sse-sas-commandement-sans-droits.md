# Bug — Sas SSE : commandement bloqué sur le formulaire de code

**Date :** 2026-09-13  
**Statut :** corrigé

## Contexte

Le sas d’entrée du portail de renseignement (`/atak/sse`) est une route publique, sans passage par l’authentification Athena. Un commandement déjà connecté devait pouvoir entrer sans code.

## Symptôme

Un compte commandement / administration, pourtant connecté, voyait le formulaire « saisissez un code temporaire » et ne pouvait pas entrer directement. Les contrôles de droits renvoyaient un refus alors que le rôle était correct.

## Cause

Sur cette route publique, les droits de session n’étaient pas rechargés. Les vérifications d’habilitation interrogeaient donc un catalogue vide, même pour un utilisateur connecté. Le commandement était traité comme un invité.

## Correctif

- Rechargement des droits depuis la session avant toute décision d’entrée sans code.
- Entrée automatique sans code pour le commandement / l’administration et les membres déjà autorisés.
- Écran back-office « Accès renseignement » pour délivrer et révoquer les codes.
- Niveau de diffusion renseignement éditable sur la fiche opérateur (staff).

## Fichiers touchés

- `app/Services/Sse/SseAccessCodeService.php`
- `app/Services/Sse/SseClearanceService.php`
- `app/Controllers/Web/SsePortalController.php`
- `app/Middleware/SsePortalAccessMiddleware.php`
- `app/Controllers/Admin/AdminSseAccessController.php`
- `views/atak/sse/gate.php`
- `views/admin/sse_access/index.php`
- `routes/web.php`

## Vérification

1. Se connecter avec un compte commandement (droit d’octroi ou administration).
2. Ouvrir `/atak/sse` : redirection vers l’engagement de confidentialité, sans formulaire de code.
3. Ouvrir `/back-office/renseignement/acces` : génération et révocation d’un code.
4. Compte sans droit : le formulaire de code reste affiché.
