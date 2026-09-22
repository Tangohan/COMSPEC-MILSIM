# ATAK / Overwatch Beta — loader Halo vs écran d’accueil

**Statut :** corrigé (sources)

## Contexte

Carte ATAK (`/atak/`) avec sas de reprise (« Connexion ATAK » / Entrer), et poste Overwatch Beta.

## Symptôme

- Sur `/atak/` : le loader Halo disparaissait pendant (ou avant) le sas, donc plus visible au bon moment.
- Sur Overwatch Beta : un Halo ajouté sans sas restait bloqué sur « PRÊT » et masquait toute la page (tuiles 401 / `readyState` qui ne passe pas à `complete` → `finish()` refusait de sortir).

## Cause

1. `halo-loader.js` forçait `finish()` au `load` / timeout sans attendre la sortie du sas session sur `/atak/`.
2. Le partial Halo était injecté **avant** le markup du sas : le script ne voyait pas encore l’overlay.
3. Overwatch Beta n’a **pas** d’écran d’accueil ATAK : y coller le Halo sans sas provoquait un blocage infini.

## Correctif

- Sur `/atak/` : Halo conservé jusqu’à « Entrer dans la session » (`__ATAK_SESSION_GATE_PENDING__`, `atak:session-gate-ready`).
- Sur Overwatch Beta : **Halo retiré** — l’accueil ATAK reste sur `/atak/` ; le poste beta ouvre directement l’interface.
- Filet de sécurité Halo : si aucun sas n’est ouvert, forcer la sortie après le délai max (évite un overlay collé sur « PRÊT »).

## Fichiers touchés

- `views/atak-overwatch-beta.php` (retrait Halo)
- `views/atak.php`
- `views/partials/halo_loader.php`
- `public/assets/js/halo-loader.js`
- `public/assets/js/atak-session-profile.js`
- `public/assets/css/halo-loader.css`

## Vérification

1. Ctrl+F5 sur `/atak/` : loader puis sas ; après « Entrer », carte.
2. Ctrl+F5 sur Overwatch Beta : plus de Halo, interface poste immédiatement.
3. Pas de blocage infini du loader hors sas.

## Statut

corrigé (sources) — déploiement portail requis
