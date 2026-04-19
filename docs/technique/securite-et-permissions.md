# Sécurité et permissions

## Authentification session

- La session est démarrée dans le flux `Application` / middlewares ; l’identité utilisateur courante sert aux contrôleurs et au **RBAC**.
- Les routes sensibles passent par **`AuthMiddleware`** pour exiger une connexion ; **`GuestMiddleware`** force l’inverse (pages d’inscription / connexion).

## RBAC (rôles et permissions)

- **`RbacService`** charge les permissions associées aux rôles (tables `permissions`, `role_permissions`, rattachements utilisateur / tenant selon le schéma).
- Les **slugs de permission** sont utilisés pour autoriser ou refuser une action (forum, documents, back-office, etc.).
- Certaines routes utilisent des middlewares dédiés : **`SystemAdminMiddleware`**, **`OrganizationAdminMiddleware`**, **`TenantResourceAdminMiddleware`**, **`NonDefaultTenantMiddleware`**, etc.

## API tactiques et clé

- Le middleware global **`ComspecTacticalApiMiddleware`** appelle **`ComspecApiKeyAuth::enforceForTacticalPath`** pour les chemins d’API tactiques : en **production** (`APP_ENV=production`), ou si **`TACTICAL_API_STRICT=true`**, une **clé API** valide doit être fournie (configuration dans `config/tactical_api.php`, secrets `X_COMSPEC_KEY` / `ATAK_INTEL_SECRET` côté environnement). Sans secret configuré dans ces modes, les chemins protégés répondent **503** ; clé invalide → **401**.
- Hors production et sans `TACTICAL_API_STRICT`, l’absence de secret laisse les chemins protégés accessibles (pratique de dev uniquement) ; dès qu’un secret est défini, la clé est exigée.
- Les contrôles inline ATAK (`armaInlineAuthOk`) suivent la même logique que le middleware (y compris `hash_equals` sur Bearer / en-têtes).
- Objectif : ne pas exposer les endpoints simulation / tactiques sans authentification applicative.

## En-têtes et limites

- **`SecurityHeadersMiddleware`** renforce les en-têtes HTTP (politique de sécurité du navigateur selon configuration).
- **`RateLimitMiddleware`** limite le débit des requêtes pour atténuer les abus (formulaires sensibles, forum, et par préfixes **`POST` / `PATCH` / `PUT` / `DELETE`** sur `/api/training/`, `/api/me/`, `/api/admin/`, `/api/back-office/`). Les dépassements sur les routes `/api/*` renvoient une réponse **JSON** 429.

## Alertes erreurs (exploitation)

- Les exceptions non gérées et certaines erreurs fatales déclenchent un envoi optionnel via **`ErrorReportMailer`** (événement `error_alert`), si **`ERROR_ALERT_EMAIL`** est renseigné et **`ERROR_ALERT_ENABLED`** activé. Le corps du message contient la trace et le contexte (identifiants de session, `Request-ID`, etc.) ; les réponses **`/api/*`** restent génériques (JSON 500 sans stack).
- Anti-spam : **`ERROR_ALERT_COOLDOWN_SECONDS`** et **`ERROR_ALERT_MAX_PER_HOUR`** (fichiers sous `storage/cache/error-alerts/`).

## Courriel et sécurité compte

- Notifications de connexion, nouvel appareil, tentatives multiples : services sous `app/Services/Auth/` et vues e-mail associées.
- La configuration SMTP et les secrets ne doivent pas être commités (`.env` uniquement sur le serveur).

## Maintenance

- Le garde de maintenance peut bloquer le site pour les utilisateurs non autorisés tout en laissant passer des routes critiques (webhooks, health) — voir liste blanche dans le point d’entrée public.

## Bonnes pratiques déploiement

- HTTPS obligatoire en production ; activer les cookies sécurisés si le site est servi en HTTPS.
- `APP_DEBUG=false`, journaux dans `storage/logs`, sauvegardes chiffrées du stockage relationnel.
- Secrets rotatifs (JWT, clés API, webhooks).

## Politique opérationnelle (API tactique Node.js)

### Sessions et cookies

- Cookie de session durci : `HttpOnly`, `SameSite=Strict`, `Path=/`, `Secure` activé automatiquement quand la requête est en TLS (`x-forwarded-proto=https`), durée configurable via `SESSION_COOKIE_MAX_AGE_MS`.
- Nom de cookie dédié (`SESSION_COOKIE_NAME`, défaut `__Host-comspec.sid`) pour éviter les collisions inter-applications.
- CORS autorise les credentials pour garder un comportement prévisible des clients web contrôlés.

### Rotation et stockage des secrets

- Les secrets d’API tactique ne doivent pas être commités ; stockage exclusivement via variables d’environnement.
- Rotation sans downtime supportée via `ATAK_INTEL_SECRETS` (liste CSV des secrets actifs) ; compatibilité conservée avec `ATAK_INTEL_SECRET` et `X_COMSPEC_KEY`.
- Recommandation: garder un chevauchement court (ancien + nouveau secret) puis retirer l’ancien après validation clients.

### Headers de sécurité standardisés

- En-têtes imposés côté API : `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, `Cross-Origin-Resource-Policy`, `Strict-Transport-Security`, `Content-Security-Policy`.
- Objectif : réduire clickjacking, MIME sniffing, exfiltration passive et surface d’attaque navigateur.

### Stratégie anti-abus

- Rate limiting global et sur routes sensibles (auth/ATAK) via fenêtre glissante configurable (`RATE_LIMIT_WINDOW_MS`, `RATE_LIMIT_MAX_REQUESTS`, `AUTH_RATE_LIMIT_MAX_REQUESTS`).
- Réponse normalisée `429` avec `retryAfterSeconds`.
- Audit trail sécurité en base (`security_audit_log`) : refus d’authentification, blocage rate-limit, événements sensibles (ex: uploads intel, update position).
- Consultation d’audit dédiée via `GET /api/security/audit` (protégée par clé API tactique).

## Voir aussi

- [Configuration et déploiement](configuration-et-deploiement.md)
- [Intégrations externes](integrations.md)
- [Checklist de sécurité release](checklist-securite-release.md)
