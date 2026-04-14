# Athena — SaaS RH tactique MILSIM Arma 3

Plateforme PHP 8.4 multi-tenant (Athena) pour la gestion RH, formations, documents et intégration tactique (ATAK/Node).

## Prérequis

- PHP 8.4
- MariaDB / MySQL
- Composer
- (Optionnel) Node.js pour le service carte tactique ATAK

## Installation

**Installation rapide (script)** : à la racine du projet, exécuter `php install.php`. Le script vérifie PHP 8.4, crée les dossiers `storage/`, copie `.env.example` → `.env`, lance `composer install` si demandé, puis `setup-database.php` (schéma, extensions DDL, seed) si les options le permettent. Options : `--no-composer`, `--no-migrate`, `--no-seed`. Penser à éditer `.env` avant ou après (DB_*, APP_URL, JWT_SECRET).

**Installation manuelle :**

1. Cloner le dépôt et aller dans le répertoire du projet.

2. Installer les dépendances PHP :
   ```bash
   composer install
   ```

3. Copier la configuration :
   ```bash
   cp .env.example .env
   ```
   Renseigner dans `.env` : `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `APP_URL`, `JWT_SECRET`, `NODE_ATAK_URL` (si utilisé).

4. Initialiser la base de données (schéma SQL, extensions métier, seed) :
   ```bash
   php setup-database.php
   ```
   Compte par défaut après seed : `admin@athena.local` / `admin`.  
   Variante web : ouvrir `public/setup-database.php` dans le navigateur (sortie texte).

5. **Document root** : pointer le serveur web (Apache/Nginx) vers le dossier `public/`.  
   - Exemple Apache : `DocumentRoot /chemin/vers/COMSPEC-MILSIM/public`  
   - Le fichier `public/.htaccess` gère les redirections legacy (`.html` → URLs propres) et le front controller.

6. Droits et répertoires : s’assurer que `storage/logs`, `storage/cache`, `storage/sessions`, `storage/uploads` sont writables par le serveur web.

## Générer vendor pour upload (sans Composer sur le serveur)

Si le serveur n’exécute pas Composer (proc_open désactivé, etc.), génère `vendor/` en local puis uploade tout le projet :

1. **En local** (PHP + Composer dans le PATH) : à la racine du projet, exécuter  
   `composer install --no-interaction`  
   ou double-cliquer sur **`build-vendor.bat`** (Windows).
2. Le dossier **`vendor/`** est créé. Uploade tout le projet (y compris `vendor/`) sur le serveur (FTP, gestionnaire de fichiers Hostinger, etc.).
3. Ne pas committer `vendor/` dans Git si tu utilises Composer sur le serveur ou en CI ; pour un déploiement manuel par upload, inclure `vendor/` dans l’archive.

## Hébergement Hostinger

- **Mode A** : si accès SSH et possibilité de définir le document root sur `public/`, utiliser la structure telle quelle. Stocker les uploads dans `storage/` (hors webroot si possible).
- **Mode B** : si tout doit rester sous `public_html/`, placer l’application sous `public_html/` et protéger `storage/` par `.htaccess` (deny from all ou équivalent). Ne pas commiter le fichier `.env` ; le créer manuellement avec les identifiants fournis par Hostinger.

## Structure (résumé)

- `public/` — point d’entrée web (`index.php`, `.htaccess`, assets)
- `app/` — Config, Core, Controllers (Web, Admin, Auth), Services, Repositories, Middleware
- `bootstrap/` — chargement env, config, erreurs
- `views/` — vues PHP (layout, auth, personnel, admin, etc.)
- `routes/web.php` — définition des routes
- `migrations/` — `schema.sql`, scripts `.sql` et pipeline PHP (`run-migrations.php`, `bootstrap/core_schema_extensions_migration.php`, etc.)
- `storage/` — logs, cache, sessions, uploads
- `server/` — service Node ATAK (carte temps réel), inchangé

## Routes principales

- `/` — Accueil
- `/login`, `POST /login`, `POST /logout`
- `/dashboard` — Tableau de bord (authentifié)
- `/personnel/me`, `/personnel/{id}`, `/orbat`
- `/documents`, `/documents/{id}/download`
- `/formations`, `/formations/{slug}`
- `/enlistment`, `POST /enlistment`
- `/atak` — Carte tactique (token injecté pour Node)
- `/admin`, `/admin/users`, `/admin/users/create`

## Service Node (ATAK)

Le service dans `server/` reste utilisé pour la carte temps réel. La page PHP `/atak` génère un token (JWT-like) et l’injecte côté client. Pour une intégration complète, le serveur Node doit valider ce token (secret partagé `JWT_SECRET`) et filtrer par `tenant_id`.

## Licence

Propriétaire.
