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
3. Pointer le **vhost** vers **`public/`** (document root). Sur le VPS Athena : `APP_BASE_PATH` **vide** et rewrite interne `/public` → `/` (voir `docs/nginx.example.conf`) pour que les mods Workshop déjà installés (`https://athena.ttrd.fr/public/api/…`) continuent de parler au site sans mise à jour Steam. Ne pas republier le Workshop pour ce seul motif. Ne pas régénérer `X_COMSPEC_KEY` / JWT / secrets recopiés.
4. Appliquer les migrations et vérifier les droits d’écriture sur **`storage/`** et chemins d’upload configurés.
5. Construire les assets front si nécessaire (`npm ci` / `npm run build` selon `package.json`).
6. Vérifier **HTTPS**, cookies sécurisés, et sauvegardes planifiées.
7. Lancer les smoke tests post-déploiement (`php scripts/post-deploy-smoke-tests.php --base-url=https://votre-domaine.tld`).

Production Athena : chaque `git push` sur `main` déclenche l’Action **Deploy VPS** (`docs` : `DEPLOY.md`, workflow `.github/workflows/deploy-vps.yml`). Le VPS fait `git pull` ; le `.env` et les uploads restent sur le disque.

### QR codes (pairing téléphone ATAK, courrier)

`vendor/` n’est **pas** versionné : sans `composer install`, `endroid/qr-code` est absent. Le générateur bascule alors sur **`phpqrcode/`** (présent dans le dépôt) + PNG zlib (`ext-zlib`), sans GD.

Sur le serveur Athena (après déploiement du code) :

```bash
cd /chemin/vers/COMSPEC-MILSIM
# Fichiers critiques QR (si déploiement partiel) :
#   app/Services/Qr/QrPngGenerator.php
#   app/Controllers/Api/AtakApiController.php   (phonePairingQrImage)
#   app/Services/Courrier/CourrierQrService.php
#   composer.json
#   phpqrcode/   (dossier entier à la racine)
composer install --no-dev --no-interaction --optimize-autoloader
php -m | grep -E 'gd|zlib'
php scripts/smoke-qr-png.php
```

- **`ext-zlib`** : requis (fallback PNG sans GD).
- **`ext-gd`** : recommandé (Endroid PngWriter + phpqrcode GD). Sous Debian/Ubuntu : `sudo apt install php-gd` puis redémarrer PHP-FPM / Apache.
- Vérifier que le dossier **`phpqrcode/`** est bien déployé à la racine du projet.
- Contrôle rapide : ouvrir `/api/atak/phone-pairing/{token}/qr.png` — doit renvoyer `Content-Type: image/png` (signature `\x89PNG`), **pas** le texte `QR unavailable`.
- Si le jeu affiche encore « QR indisponible » alors que le code court (ex. `XFC56D`) est OK : le pairing API marche, c’est uniquement le PNG — déployer `QrPngGenerator` + `composer install` suffit en général (pas besoin de republier le mod).

**Cause classique en prod (avant ce fix)** : `phonePairingQrImage` ne faisait que Endroid `PngWriter` ; sans `vendor/` ou sans GD → HTTP **503** `QR unavailable` → l’extension Arma renvoie `ERR|unavailable` → dialog fallback.

## Santé et maintenance

- Des routes comme **`/api/health`** peuvent servir aux sondes de disponibilité.
- Le mode maintenance laisse souvent passer les webhooks (ex. Stripe) et le health check via une liste blanche dans le point d’entrée.

## Voir aussi

- [Sécurité et permissions](securite-et-permissions.md)
- [Intégrations externes](integrations.md)
- [Pilotage mensuel & fiabilisation déploiements](pilotage-mensuel-fiabilisation-deploiements.md)
