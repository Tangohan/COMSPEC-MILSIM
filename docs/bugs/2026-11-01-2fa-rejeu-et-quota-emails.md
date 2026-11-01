# Double vérification : code rejouable, e-mail de secours imposé, quota d'e-mails Hostinger dépassé

## Contexte
Envoi par le SMTP Hostinger (quota quotidien vite atteint) et double vérification par application d'authentification déjà en place sur `/account/security`.

## Symptôme
1. Un code de l'application pouvait servir plusieurs fois pendant sa fenêtre de validité (jusqu'à 2 min 30 avec la tolérance de ±2 pas).
2. Un responsable dont le rôle impose la double vérification se voyait proposer « recevoir un code par e-mail » à la connexion alors qu'il utilisait l'application et n'avait pas choisi l'e-mail.
3. Le quota d'envoi était consommé par des e-mails sans valeur : point SSE quotidien et e-mail à chaque « Passage moteur » manuel, une alerte d'incident par IP pour la même erreur, confirmations d'actions que la personne venait de faire, une notification par message de la messagerie interne.

## Cause
1. `TotpService::verify()` ne mémorisait aucun pas déjà utilisé.
2. `LoginSecurityOtpService::canUseEmailFallback()` et `AuthController::switchLoginOtpChannel()` acceptaient l'e-mail pour tout rôle « obligatoire ».
3. Aucun plafond par destinataire et par type ; la clé anti-tempête des alertes 500 incluait l'IP (120 s, 30/h par clé).

## Correctif
1. `TotpService::matchStep()` + `TotpReplayGuard` (table `user_totp_used_steps`, créée à la demande : la file de migrations active doit rester vide, cf. `MigrationRegistryTest`). Toute vérification passe par `LoginSecurityOtpService::verifyTotpCode()` (connexion, activation, désactivation, console d'exploitation).
2. Application active : e-mail uniquement si le membre a lui-même coché « code par e-mail » ; `beginLoginChallenge()` retombe sur l'application sinon.
3. `MailBudget` : `DISABLED_EVENTS` (SSE, jamais envoyé, même via le compte d'une communauté), `DEFAULT_MUTED` (réactivable dans Administration > Envois d'e-mails), `DAILY_DIGEST_EVENTS` et plafond « un par heure et par destinataire » versés au résumé quotidien (`MEMBER_DAILY_DIGEST`, tâche `mail_ops_digest`). Point SSE : Discord seulement. Alertes 500 : une par signature d'erreur et par heure, aucune pour les routes SSE.

## Fichiers touchés
`app/Services/Auth/{TotpService,TotpReplayGuard,LoginSecurityOtpService,RecoveryCodeService}.php`, `app/Controllers/{Auth/AuthController,Web/AccountController,Web/SsePortalController}.php`, `app/Services/Ops/OpsConsoleStepUp.php`, `app/Services/Email/{MailBudget,MailBudgetGate,OpsMailDigestService,EmailEvents}.php`, `app/Services/EmailService.php`, `app/Services/Monitoring/{ErrorReportMailer,ErrorAlertThrottle}.php`, `app/Services/Sse/SseAnalystDigestService.php`.

## Vérification
`tests/Unit/TotpAppAuthenticatorTest.php`, `tests/Unit/MailVolumeReductionTest.php`, `MailBudgetTest`, `OpsMailDigestGroupingTest`.

## Identifiés, non corrigés ici
- `mail/config_mail.php` (fichier inutilisé, suivi par git) contient un identifiant et un mot de passe SMTP Hostinger en clair : changer ce mot de passe, puis retirer le fichier du dépôt.
- La connexion en jeu par code e-mail (`GameAuthService::requestOtp`) ne tient pas compte de l'application d'authentification.

Traités dans `2026-11-01-2fa-contournee-en-jeu-et-secret-smtp.md` (le mot de passe SMTP reste à changer chez Hostinger).

## Statut
Corrigé (points 1 à 3) ; deux points identifiés ci-dessus.
