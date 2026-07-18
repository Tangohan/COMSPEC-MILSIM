# Packages de mise à jour applicative (mode manuel)

Ce document est technique (génération / déploiement). L’interface admin n’expose pas ce jargon.

## Structure du ZIP

```text
update-1.4.0.zip
├── manifest.json
├── files/          # arborescence relative à la racine projet
├── migrations/     # SQL optionnels listés dans le manifeste
└── scripts/        # pre_update.php / post_update.php optionnels
```

## manifest.json

```json
{
  "version": "1.4.0",
  "minimum_version": "1.3.0",
  "checksum": "<sha256 du payload>",
  "maintenance_required": true,
  "php_min": "8.4.0",
  "files_to_delete": [
    "public/assets/legacy.js"
  ],
  "migrations": [
    "20260718_add_notifications.sql"
  ],
  "signature": "<hmac optionnel>"
}
```

Le `checksum` est le SHA-256 de la liste triée `dossier/chemin:sha256(fichier)` pour `files/`, `migrations/` et `scripts/`.

Si `UPDATE_PACKAGE_HMAC_SECRET` est défini, `signature` = HMAC-SHA256 de  
`version|minimum_version|checksum` (checksum en minuscules).

## Chemins protégés (jamais écrasés)

`.env*`, `storage/`, `uploads/`, `logs/`, `backups/`, `public/uploads/`, `app/Config/database.local.php`, `node_modules/`, `.git/`

## Déploiement (racine web fixe)

1. Upload via `/admin/system/updates`
2. Validation + aperçu
3. Sauvegarde des fichiers touchés sous `storage/backups/app-updates/`
4. Overlay sur l’arbre live + migrations + scripts
5. Mise à jour de `storage/app_version.json`
6. Contrôle de santé ; rollback auto en cas d’échec

Distinct des canaux modules (`/admin/system/deployment`).
