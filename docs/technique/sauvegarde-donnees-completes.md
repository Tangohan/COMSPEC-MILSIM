# Sauvegarde complète des données (base + fichiers)

Copie de **toutes les données métier** (MySQL + fichiers déposés) dans un dossier horodaté, pour un **rollback** après une migration, une fusion de comptes ou un incident.

Ce n’est pas une sauvegarde hors site : les copies restent sur le serveur (par défaut sous `storage/snapshots/`, hors du web et hors Git). Pour une copie externe, déplacer le dossier vers un disque ou un autre hôte.

## Quand s’en servir

- **Avant** `php setup-database.php` / `php run-migrations.php` en production.
- **Avant** une opération irréversible (fusion de comptes, purge, import massif).
- **Après** un incident, pour revenir à l’état copié.

## Commandes

Depuis la racine du projet, avec le `.env` de l’instance :

```bash
# Copie complète (base + fichiers)
php scripts/data-snapshot.php create --label=avant-migration

# Base seulement, ou fichiers seulement
php scripts/data-snapshot.php create --db-only --label=bdd
php scripts/data-snapshot.php create --storage-only --label=fichiers

# Lister / contrôler
php scripts/data-snapshot.php list
php scripts/data-snapshot.php show 20260902T163045Z_avant-migration
php scripts/data-snapshot.php verify 20260902T163045Z_avant-migration

# Simulation puis restauration réelle (écrase les données actuelles)
php scripts/data-snapshot.php restore 20260902T163045Z_avant-migration --dry-run
php scripts/data-snapshot.php restore 20260902T163045Z_avant-migration --yes
```

`--yes` est obligatoire pour une restauration réelle. `--dry-run` n’écrit rien.

`--prune-storage` : après restauration des fichiers, **supprime** ceux qui n’existaient pas dans la copie (retour strict à l’arborescence d’alors). Sans cette option, les fichiers plus récents restent en place ; seuls ceux présents dans la copie sont écrasés.

```bash
php scripts/data-snapshot.php restore 20260902T163045Z_avant-migration --yes --prune-storage
```

Conserver les N copies les plus récentes (défaut : 10, ou `DATA_SNAPSHOT_KEEP`) :

```bash
php scripts/data-snapshot.php create --label=quotidien --keep=7
php scripts/data-snapshot.php prune --keep=7
```

`--keep=0` : ne retire aucune ancienne copie.

## Contenu d’une copie

Dossier `storage/snapshots/<id>/` :

| Fichier | Rôle |
|---------|------|
| `manifest.json` | Identifiant, date, version applicative, empreinte Git, contenu |
| `database.sql.gz` | Dump MySQL compressé |
| `files/` | Copie des fichiers métier (même arborescence) |
| `files-index.json` | Liste et empreintes des fichiers |
| `SHA256SUMS` | Empreintes pour `verify` |

Fichiers inclus :

- `storage/uploads` (portraits, documents RH, cartes, etc.)
- `storage/documents`
- `storage/intel`
- `storage/atak-mod`
- `storage/atak_terrain`
- `storage/mail-outbox`
- `public/uploads`

Hors copie (volontairement) : code applicatif, `.env`, sessions, caches, journaux, paquets de mise à jour, dossier des copies lui-même.

## Rollback type

1. Activer la maintenance : `php scripts/toggle-maintenance.php on "Restauration des données."`
2. `php scripts/data-snapshot.php restore <id> --dry-run`
3. `php scripts/data-snapshot.php restore <id> --yes`
4. Contrôler une connexion et un dossier connu.
5. `php scripts/toggle-maintenance.php off`

La restauration de la **base remplace** le contenu actuel de la base configurée dans `.env`. Les fichiers sont **réécrits** par-dessus ; avec `--prune-storage`, l’arborescence redevient celle de la copie.

## Réutilisation depuis PHP

```php
use App\Services\Backup\CompleteDataSnapshotService;

$svc = CompleteDataSnapshotService::fromApp();
$copy = $svc->create('avant-fusion');
// … opération risquée …
// $svc->restore($copy['id'], true, true, false, false);
```

## Configuration

| Variable | Rôle |
|----------|------|
| `DATA_SNAPSHOT_DIR` | Dossier des copies (absolu ou relatif à la racine). Défaut : `storage/snapshots`. Interdit sous `public/`. |
| `DATA_SNAPSHOT_KEEP` | Nombre de copies conservées après `create` / `prune`. Défaut : `10`. `0` = illimité. |
| `MYSQLDUMP_BIN` | Chemin du binaire `mysqldump` s’il n’est pas dans le `PATH`. |
| `MYSQL_BIN` | Chemin du binaire `mysql`. |

Si `mysqldump` / `mysql` sont absents (hébergement restreint), le service bascule sur un dump et une restauration PHP (tables, données, vues). Préférer les clients MySQL quand ils sont disponibles : dump cohérent InnoDB (`--single-transaction`) et restauration plus robuste (déclencheurs, routines).

## Sécurité

- Les copies contiennent des **données personnelles** (comptes, dossiers, pièces jointes). Ne pas les committer, ne pas les exposer sur le web.
- Le mot de passe MySQL passe par un fichier client temporaire (droits 0600), pas par la ligne de commande.
- Les chemins restaurés sont filtrés : une entrée `../.env` n’est pas recopiée.

## Voir aussi

- [Configuration et déploiement](configuration-et-deploiement.md)
- [Pilotage mensuel & fiabilisation déploiements](pilotage-mensuel-fiabilisation-deploiements.md)
