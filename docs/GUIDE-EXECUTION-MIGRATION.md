# 📋 Guide d'exécution — Migration configuration réalisme

## ⚠️ Prérequis

- PHP 8.1 ou supérieur
- Accès MySQL/MariaDB avec droits CREATE TABLE
- Composer (pour autoload PSR-4)
- Environnement ATHENA C2 fonctionnel

## 🚀 Exécution du script de migration

### Étape 1 : Vérifier l'environnement

```bash
cd /workspace
php --version  # Doit afficher PHP 8.1+
composer dump-autoload  # Régénérer autoload PSR-4
```

### Étape 2 : Exécuter la migration

```bash
php setup-realism-migration.php
```

**Durée estimée :** 30 secondes à 2 minutes selon le nombre de tenants.

### Étape 3 : Vérifier la migration

#### Via l'interface web (recommandé)

1. Connectez-vous à l'admin ATHENA
2. Accédez à `/admin/atak/realism/verify`
3. Vérifiez que tous les tests sont au vert ✅

#### Via la console

```bash
# Vérifier tables créées
mysql -u athena -p -e "SHOW TABLES LIKE 'atak_%'"

# Compter configs migrées
mysql -u athena -p -e "SELECT COUNT(*) FROM atak_realism_config"

# Afficher une config
mysql -u athena -p -e "SELECT tenant_id, config_name, JSON_PRETTY(config_json) FROM atak_realism_config LIMIT 1"
```

## 📊 Sortie attendue

Le script doit afficher :

```
============================================================
  ATHENA C2 — Migration configuration réalisme
  Version : 1.0.0 (Script unifié ONE-SHOT)
  Date : 2026-09-22 10:52:00
============================================================

📋 Étape 1/6 : Création des tables...
  ✅ Table atak_realism_config créée
  ✅ Table atak_relay_overrides créée

📖 Étape 2/6 : Chargement et validation du schéma JSON...
  ✅ Schéma chargé : 105 paramètres dans 11 domaines
  ✅ Profils disponibles : 3 (beginner, event, expert)
  ✅ Schéma validé : cohérent

🔄 Étape 3/6 : Migration des paramètres...
  Tenant #1 (Demo)... ✅
  Tenant #2 (Production)... ✅

  ✅ Migration complétée : 2 tenant(s) migré(s), 0 déjà existant(s)

🎯 Étape 4/6 : Validation des profils...
  Profil '🟢 Débutant (arcade)'... ✅
  Profil '🟡 Événement (équilibré)'... ✅
  Profil '🔴 Expert (simulation)'... ✅

  ✅ Profils validés : 3

🔍 Étape 5/6 : Validation de la migration...
  ✅ Aucun paramètre orphelin (100% couverture audit PR #545)
  ✅ Incohérences résolues : 12/12
  ✅ Configurations créées : 2

📊 Étape 6/6 : Génération du rapport...
  ✅ Rapport généré : storage/logs/realism-migration-2026-09-22-105200.log

============================================================
✅ Migration complétée avec succès en 47s !
📊 Rapport : storage/logs/realism-migration-2026-09-22-105200.log
============================================================
```

## 🛠️ Résolution de problèmes

### Erreur : "Table already exists"

La migration a déjà été exécutée. Pour re-migrer :

```sql
DROP TABLE IF EXISTS atak_relay_overrides;
DROP TABLE IF EXISTS atak_realism_config;
```

Puis ré-exécutez `php setup-realism-migration.php`.

### Erreur : "Schéma JSON invalide"

Vérifiez le fichier `/workspace/config/realism-schema.json` :

```bash
cat config/realism-schema.json | jq .  # Valider JSON
```

### Erreur : "Config invalide"

Le script s'arrête si une config générée ne passe pas la validation. Vérifiez les logs détaillés pour identifier le paramètre problématique.

### Aucun tenant trouvé

Si la table `tenants` est vide, la migration passe mais ne crée aucune config. C'est normal pour un environnement de développement vierge.

## 📝 Après migration

1. **Tester l'interface admin**
   - Accéder à `/admin/atak/realism/config`
   - Vérifier que les 11 onglets s'affichent correctement
   - Modifier un paramètre et sauvegarder
   - Vérifier l'historique

2. **Tester l'API**
   ```bash
   curl -X GET https://athena.local/api/atak/realism/config \
        -H "Authorization: Bearer $TOKEN"
   ```

3. **Tester en jeu**
   - Lancer Arma 3 avec le mod ATHENA
   - Créer un relais radio en jeu
   - Vérifier que la portée correspond au paramètre configuré
   - Consulter le guide `/docs/technique/guide-test-config-realisme.md`

4. **Nettoyer les anciens scripts** (après validation)
   ```bash
   rm bootstrap/atak_realism_config_migration.php
   rm bootstrap/atak_realism_config_seed.php
   ```

## 🔗 Ressources

- Plan complet : `/docs/technique/PLAN-FINAL-CENTRALISATION-REALISME.md`
- Guide test : `/docs/technique/guide-test-config-realisme.md`
- Schéma JSON : `/config/realism-schema.json`
- Rapport migration : `/storage/logs/realism-migration-*.log`

---

**Note :** Ce script remplace définitivement les anciens scripts de migration dispersés. Il constitue la **source unique de vérité** pour la structure des tables et la migration des données.
