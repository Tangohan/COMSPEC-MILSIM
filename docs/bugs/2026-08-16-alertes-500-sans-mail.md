# Alertes 500 SSE sans e-mail reçu

## Contexte

Pages SSE en 500 fréquentes ; aucun courriel d’incident reçu malgré le texte « équipe prévenue ».

## Symptôme

Incidents visibles côté utilisateur, boîte `ERROR_ALERT_EMAIL` vide / silencieuse.

## Cause

Plusieurs filets silencieux cumulés :

1. **`ERROR_ALERT_EMAIL` vide** → `ErrorReportMailer` sort sans log exploitable.
2. **`MAIL_QUEUE=true`** → l’alerte était mise en file `async_jobs` ; sans `worker-jobs.php` régulier (souvent le cas sur Hostinger), **aucun envoi**.
3. **Throttle** (120 s / même erreur+IP, max 30/h) → rafale SSE = 1 mail puis silence.
4. Échec SMTP avalé dans un `catch` vide.

## Correctif

- Envoi **immédiat** des `error_alert` (`forceImmediate`, ignore la file).
- Destinataire de secours : premier `SECURITY_ALERT_EMAILS`.
- Journal local systématique : `storage/logs/error-alerts.log` (copie + motifs de skip).
- Repli `mail()` PHP si le transport applicatif échoue.
- Commentaires `.env.example`.

## Fichiers touchés

- `app/Services/EmailService.php`
- `app/Services/Monitoring/ErrorReportMailer.php`
- `.env.example`
- `docs/bugs/2026-08-16-alertes-500-sans-mail.md`

## Vérification prod

1. `.env` : `ERROR_ALERT_ENABLED=true` et `ERROR_ALERT_EMAIL=votre@adresse`.
2. Déployer le correctif.
3. Provoquer une 500 contrôlée ou lire `storage/logs/error-alerts.log` après un incident SSE.
4. Si `skip=no_recipient` : renseigner l’e-mail.
5. Si `skip=throttled` : normal en rafale — la 1ʳᵉ alerte doit tout de même arriver + copie dans le log.

## Statut

corrigé (config prod à vérifier)
