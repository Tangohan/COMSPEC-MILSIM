# SSE — thème Control Tower, mission dynamique, sas ATAK

## Contexte

Amélioration continue du portail SSE : couleurs type Control Tower, barre de contexte opérationnel, page d’accès alignée sur le hub ATAK.

## Symptôme

- Palette console trop « vert olive » / fonds trop plats par rapport à la maquette Control Tower (`#080B10`, cyan HUD).
- Sélecteur « Théâtre » inutile dans les propositions de barre supérieure.
- Sélecteur « Mission » figé (pas branché sur les cycles de mission).
- Sas d’accès (`/atak/sse`) trop basique vs l’écran de reprise ATAK.

## Cause

Thème console historique ; pas de contexte mission/diffusion en barre ; gate en carte centrée sans composition split branding/formulaire.

## Correctif

- Tokens CSS Control Tower (`#080b10` / `#0d1117` / `#121721` / accent `#00e5ff`) + émeraude sémantique DS.
- Barre : **Mission** (cycles `theatre_mission_cycles`) + **Diffusion** — sans Théâtre.
- Sas : layout split aigle / formulaire, CTA « Entrer dans la session ».

## Fichiers touchés

- `public/assets/css/sse_portal.css`
- `views/atak/sse/_layout.php`, `views/atak/sse/gate.php`
- `app/Controllers/Web/SsePortalController.php`, `app/Support/helpers.php`, `routes/web.php`

## Vérification

- Syntaxe / revue manuelle des vues et routes.
- Mission : liste dynamique ; classification : cookie + bandeau.
- Gate : composition type ATAK.

## Statut

corrigé
