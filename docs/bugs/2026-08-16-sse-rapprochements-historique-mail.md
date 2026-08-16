# Rapprochements — historique + e-mail après passage moteur

**Date :** 2026-08-16  
**Statut :** corrigé

## Contexte

La page `/atak/sse/rapprochements` ne montrait que la file « à traiter ».
Le digest e-mail partait au cron nocturne, mais pas après un lancement manuel du moteur.

## Symptôme

- Pas d’historique des validations / rejets.
- Bouton « Lancer un passage maintenant » sans alerte e-mail aux analystes.

## Correctif

- Section **Historique des décisions** (validé / rejeté / reporté) avec date et auteur.
- Après un passage manuel : envoi du digest e-mail (même moteur que le cron), dédupliqué par `run_id`.
- Le passage nocturne continue d’envoyer le digest en fin de pipeline.

## Fichiers touchés

- `views/atak/sse/suggestions.php`
- `app/Controllers/Web/SsePortalController.php`
- `app/Repositories/SseSuggestionQueueRepository.php`
- `app/Services/Sse/SseAnalystDigestService.php`
- `public/assets/css/sse_portal.css`

## Vérification

1. Valider ou rejeter une proposition → elle apparaît en historique.
2. Lancer un passage moteur avec file non vide et préférences e-mail SSE actives → message de confirmation + réception du mail.
3. Relancer le même passage (même run) → pas de second envoi (dédup).

## Statut

Livré — à déployer sur Athena.
