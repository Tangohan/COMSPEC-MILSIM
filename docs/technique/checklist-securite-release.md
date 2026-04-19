# Checklist de sécurité release

## 1) Secrets et configuration

- [ ] Aucun secret en clair dans le dépôt (`.env`, clés API, tokens webhook).
- [ ] Rotation préparée (`ATAK_INTEL_SECRETS` contient ancien+nouveau secret durant la fenêtre de transition).
- [ ] Date de retrait de l’ancien secret planifiée et communiquée.
- [ ] Variables de sécurité renseignées: `SESSION_COOKIE_NAME`, `SESSION_COOKIE_MAX_AGE_MS`, `RATE_LIMIT_*`.

## 2) Sessions / cookies / transport

- [ ] Reverse proxy transmet correctement `x-forwarded-proto=https`.
- [ ] Cookies de session observés avec `HttpOnly`, `SameSite=Strict`, `Secure` en production.
- [ ] HTTPS forcé au niveau infra (redirect HTTP → HTTPS + HSTS actif).

## 3) Headers sécurité

- [ ] Présence validée des headers: CSP, HSTS, COOP/CORP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy.
- [ ] CSP testée sur les pages clientes ATAK/web pour éviter régressions fonctionnelles.

## 4) Anti-abus et résilience

- [ ] Rate limit global activé (`RATE_LIMIT_MAX_REQUESTS`) avec seuil validé en charge.
- [ ] Rate limit renforcé sur endpoints sensibles (`AUTH_RATE_LIMIT_MAX_REQUESTS`).
- [ ] Réponses `429` vérifiées (message + `retryAfterSeconds`).
- [ ] Monitoring d’erreurs 401/429 branché (logs centralisés / alerting).

## 5) Audit trail et investigabilité

- [ ] Table `security_audit_log` disponible et alimentée.
- [ ] Endpoint `/api/security/audit` testé avec clé valide.
- [ ] Rétention des journaux définie (durée + purge).
- [ ] Procédure d’investigation incident documentée (qui consulte, comment corréler).

## 6) Validation finale avant go-live

- [ ] Smoke test API (markers, units, intel photo, position).
- [ ] Test d’auth invalide (`401`) et surcharge (`429`) exécutés.
- [ ] Sauvegarde de la base et rollback plan validés.
- [ ] Décision Go/No-Go signée par responsable technique + sécurité.
