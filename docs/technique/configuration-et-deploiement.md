# Configuration et déploiement

## Variables d’environnement

Le fichier **`.env`** (non versionné) reprend le modèle **`.env.example`**. Principales familles :

| Famille | Exemples | Rôle |
|---------|----------|------|
| Application | `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_BASE_PATH`, `APP_LOCALE`, `APP_TIMEZONE` | Identité, URL publique, chemin si sous-dossier, fuseau horaire. |
| Stockage relationnel | `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_CHARSET` | Connexion MySQL. |
| Session | `SESSION_LIFETIME`, `SESSION_SECURE_COOKIE` | Durée et cookie sécurisé (HTTPS). |
| Sécurité | `JWT_SECRET` | Jetons signés si utilisés. |
| Maintenance | `MAINTENANCE_ENABLED`, `MAINTENANCE_MESSAGE`, `MAINTENANCE_ALLOWED_IPS`, `MAINTENANCE_TOKEN` | Mode maintenance et contournements. |
| Courriel | `MAIL_*` | Transport (fichier local, SMTP, fournisseurs), expéditeur, chiffrement. |
| Logs | `LOG_CHANNEL`, `LOG_LEVEL`, `LOG_PATH` | Journalisation. |

En **production**, `APP_DEBUG` doit rester **`false`** pour ne pas exposer les détails d’erreur aux visiteurs ; le point d’entrée journalise les erreurs fatales et n’affiche le détail qu’en mode debug.

## Fichiers de configuration fusionnés

Le bootstrap charge **`app/Config/*.php`** (app, database, auth, maintenance, units, forum…) et les expose via **`config()`**. D’autres fichiers dans **`config/`** (navigation, email détaillé, presets) complètent selon les modules.

## Migrations

- Les scripts SQL sont dans **`migrations/`** ; des scripts PHP dans **`bootstrap/`** ou **`run-migrations.php`** peuvent orchestrer l’application des versions.
- Toujours **sauvegarder** le stockage relationnel avant migration en production.

## Déploiement type

1. Cloner le dépôt, installer les dépendances Composer (`composer install --no-dev` en prod si applicable).
2. Copier `.env.example` vers `.env`, renseigner URL, base, courriel, secrets.
3. Pointer le **vhost** vers **`public/`** (document root).
4. Appliquer les migrations et vérifier les droits d’écriture sur **`storage/`** et chemins d’upload configurés.
5. Construire les assets front si nécessaire (`npm ci` / `npm run build` selon `package.json`).
6. Vérifier **HTTPS**, cookies sécurisés, et sauvegardes planifiées.

## Santé et maintenance

- Des routes comme **`/api/health`** peuvent servir aux sondes de disponibilité.
- Le mode maintenance laisse souvent passer les webhooks (ex. Stripe) et le health check via une liste blanche dans le point d’entrée.

## Voir aussi

- [Sécurité et permissions](securite-et-permissions.md)
- [Intégrations externes](integrations.md)
