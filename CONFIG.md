# Configuration et déploiement (Athena / COMSPEC MILSIM)

## Déploiement PHP

1. **Document root** : pointer le serveur web (Apache/Nginx) vers le dossier `public/`.
2. **Installation** : à la racine du projet, exécuter `php install.php` (ou ouvrir `public/install.php` dans le navigateur).
3. **Fichier `.env`** : copier `.env.example` vers `.env` et renseigner au minimum :
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`
   - `APP_URL` (URL publique du site)
   - `JWT_SECRET` (clé secrète pour tokens)
4. **Migrations** : l’install lance `run-migrations.php` (schéma SQL + seed). Compte admin par défaut : `admin@athena.local` / `admin`.

## Fichiers de configuration

| Fichier | Rôle |
|--------|------|
| `.env` | Variables d’environnement (DB, APP_URL, maintenance, logs, etc.). Ne pas commiter. |
| `app/Config/app.php` | Nom, env, debug, timezone, locale, options maintenance et log. |
| `app/Config/database.php` | Connexion MySQL. |
| `app/Config/auth.php` | Session, mot de passe, verrouillage login. |
| `app/Config/maintenance.php` | Mode maintenance (surcharge possible via `storage/maintenance.json`). |
| `app/Config/units.php` | Types d’unités (organisation, groupe, équipe, section, escouade, etc.) pour l’ORBAT. |

## Mode maintenance

- **Activation par `.env`** : `MAINTENANCE_ENABLED=true` et optionnellement `MAINTENANCE_MESSAGE`, `MAINTENANCE_ALLOWED_IPS` (IPs séparées par des virgules).
- **Surcharge par fichier** : créer ou modifier `storage/maintenance.json` :
  ```json
  {
    "enabled": true,
    "message": "Maintenance en cours. Retour vers 14h.",
    "allowed_ips": ["1.2.3.4"]
  }
  ```
  Ce fichier est prioritaire sur `.env`. En maintenance, le site renvoie une page 503 (sauf pour les IP autorisées).

- **Activation / Désactivation**  
  - **CLI** : `php scripts/toggle-maintenance.php on` ou `php scripts/toggle-maintenance.php off`  
  - **Web** : définir `MAINTENANCE_TOKEN` dans `.env`, puis appeler :
    - `GET /maintenance-toggle.php?token=VOTRE_TOKEN&action=on`
    - `GET /maintenance-toggle.php?token=VOTRE_TOKEN&action=off`

## Gestion des unités / équipes / groupements

- **ORBAT** (affichage) : menu « ORBAT » ou URL `/orbat` (arbre des unités).
- **Admin** : `/admin` → « Unités / Équipes / Groupes » ou `/admin/units`.
  - Création, modification, suppression d’unités.
  - Types configurables dans `app/Config/units.php` (organisation, branche, groupe, équipe, section, escouade, etc.).
  - Unité parente, commandant (utilisateur), code et ordre d’affichage.

## Logs

- `LOG_CHANNEL` et `LOG_LEVEL` dans `.env`.
- Chemin des fichiers de log : `LOG_PATH` (défaut : `storage/logs`). S’assurer que le dossier existe et est inscriptible.
