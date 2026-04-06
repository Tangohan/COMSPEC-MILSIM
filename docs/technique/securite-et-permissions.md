# Sécurité et permissions

## Authentification session

- La session est démarrée dans le flux `Application` / middlewares ; l’identité utilisateur courante sert aux contrôleurs et au **RBAC**.
- Les routes sensibles passent par **`AuthMiddleware`** pour exiger une connexion ; **`GuestMiddleware`** force l’inverse (pages d’inscription / connexion).

## RBAC (rôles et permissions)

- **`RbacService`** charge les permissions associées aux rôles (tables `permissions`, `role_permissions`, rattachements utilisateur / tenant selon le schéma).
- Les **slugs de permission** sont utilisés pour autoriser ou refuser une action (forum, documents, back-office, etc.).
- Certaines routes utilisent des middlewares dédiés : **`SystemAdminMiddleware`**, **`OrganizationAdminMiddleware`**, **`TenantResourceAdminMiddleware`**, **`NonDefaultTenantMiddleware`**, etc.

## API tactiques et clé

- Le middleware global **`ComspecTacticalApiMiddleware`** appelle **`ComspecApiKeyAuth::enforceForTacticalPath`** pour les chemins d’API tactiques : en environnement strict, une **clé API** valide doit être fournie (configuration dans `config/tactical_api.php`, secrets côté environnement).
- Objectif : ne pas exposer les endpoints simulation / tactiques sans authentification applicative.

## En-têtes et limites

- **`SecurityHeadersMiddleware`** renforce les en-têtes HTTP (politique de sécurité du navigateur selon configuration).
- **`RateLimitMiddleware`** limite le débit des requêtes pour atténuer les abus.

## Courriel et sécurité compte

- Notifications de connexion, nouvel appareil, tentatives multiples : services sous `app/Services/Auth/` et vues e-mail associées.
- La configuration SMTP et les secrets ne doivent pas être commités (`.env` uniquement sur le serveur).

## Maintenance

- Le garde de maintenance peut bloquer le site pour les utilisateurs non autorisés tout en laissant passer des routes critiques (webhooks, health) — voir liste blanche dans le point d’entrée public.

## Bonnes pratiques déploiement

- HTTPS obligatoire en production ; activer les cookies sécurisés si le site est servi en HTTPS.
- `APP_DEBUG=false`, journaux dans `storage/logs`, sauvegardes chiffrées du stockage relationnel.
- Secrets rotatifs (JWT, clés API, webhooks).

## Voir aussi

- [Configuration et déploiement](configuration-et-deploiement.md)
- [Intégrations externes](integrations.md)
