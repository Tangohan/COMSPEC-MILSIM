# CHANGELOG — COMSPEC Overwatch v1.7.0
## Configuration Réalisme Centralisée

**Date** : 22 septembre 2026  
**Version** : 1.7.0  
**Branche** : `cursor/audit-realisme-centralisation-317c`  
**Pull Request** : #548

---

## 🎯 Vue d'ensemble

Cette version majeure introduit la **centralisation complète de la configuration réalisme ATAK**, basée sur l'audit réalisé dans PR #545. Elle unifie ~100 paramètres dispersés dans le code (SQF, C#, PHP, JS) en une seule source de vérité côté serveur, avec une interface d'administration moderne et des outils de gestion en jeu.

---

## 🚀 Nouvelles fonctionnalités

### Configuration centralisée
- ✨ **Table `atak_realism_config`** : Source unique pour 105 paramètres répartis en 11 domaines
- ✨ **Schéma JSON complet** : Définition des paramètres avec métadonnées (type, bornes, labels, aide)
- ✨ **3 profils préréglés** : Beginner, Event, Expert (application en un clic)
- ✨ **Overrides par instance** : Modification de relais/zones spécifiques sans toucher aux globaux
- ✨ **Historisation complète** : Versioning, `created_by`, `updated_by`, timestamps

### Interface d'administration
- ✨ **UI dynamique 9 onglets** : Générée automatiquement depuis le schéma JSON
- ✨ **Validation stricte** : Côté serveur (PHP) et client (JavaScript) avec retour visuel
- ✨ **Tooltips contextuels** : Aide intégrée pour chaque paramètre
- ✨ **Page de vérification** : Tests automatiques de l'intégrité de la migration
- ✨ **Calculateurs interactifs** : Portée, débit, effet météo
- ✨ **Historique des modifications** : Consultation des versions précédentes

### Dashboard Roleplay amélioré
- ✨ **Tests serveur en temps réel** : 8 vérifications automatiques (config, cohérence, zones)
- ✨ **Carte interactive Canvas** : Visualisation des zones géographiques roleplay
- ✨ **UI réorganisée** : Layout 2 colonnes (config + monitoring)
- ✨ **Sections compactées** : Network simulation, relay linking, sensors, intel

### Gestion des relais (back-office)
- ✨ **Page réseau de relais** : Vue d'ensemble style "Germinal" (terminal sombre)
- ✨ **Statistiques globales** : Actifs, hors ligne, saturation, erreurs
- ✨ **Détails par relais** : Statut, position, range, connexions, power, throughput, reliability
- ✨ **Color-coding** : Excellent (vert), Bon (bleu), Dégradé (orange), Critique (rouge), Hors ligne (gris)

### Gestion des relais (en jeu)
- ✨ **3 modules Zeus** :
  - **Scanner réseau relais** : Détection auto + upload serveur avec rapport terrain
  - **Tableau de bord relais** : Interface full-screen avec statuts temps réel
  - **Resync relais** : Resynchronisation manuelle avec feedback visuel
- ✨ **Actions ACE/Scroll joueur** : Accès au dashboard depuis laptop/tablette
- ✨ **Interface full-screen** : Liste relais, statistiques, actions (resync, téléport Zeus)
- ✨ **Color-coding dynamique** : OK, Saturé, Endommagé, Hors ligne
- ✨ **Détection d'erreurs** : Config manquante, valeurs incohérentes, relais orphelins

### Extension C# (COMSPEC DLL)
- ✨ **5 nouvelles méthodes** :
  - `GetRealismConfig` : Récupération config complète avec cache (3 min TTL)
  - `GetRealismParam` : Lecture d'un paramètre spécifique (domaine + clé)
  - `ApplyRealismProfile` : Application d'un profil préréglé
  - `CalculateWeatherEffects` : Calcul impact météo sur portée/débit relais
  - `SyncRelay` : Synchronisation relais vers serveur ATHENA
- ✨ **Cache intelligent** : Réduction des appels API redondants
- ✨ **Partial class** : `Extension_Realism.cs` pour éviter de modifier le fichier principal

### Helpers SQF (mod Arma 3)
- ✨ **`fn_getRealismParam`** : Lecture paramètre depuis config centralisée (cache 3 min)
- ✨ **`fn_applyRealismProfile`** : Application profil depuis Zeus/admin
- ✨ **`fn_placeRealismRelay`** : Placement relais avec config centralisée + météo
- ✨ **`fn_updateRelayWeatherEffects`** : Thread mise à jour dynamique range/débit
- ✨ **`fn_zeusModuleScanRelays`** : Module Zeus scanner réseau
- ✨ **`fn_openRelayDashboard`** : Interface dashboard full-screen
- ✨ **`fn_addRelayDashboardActions`** : Ajout actions ACE/scroll
- ✨ **`fn_zeusResyncRelay`** : Module Zeus resync rapide

### Web JavaScript
- ✨ **`atak-realism-helper.js`** : Helper centralisé pour chargement config (cache, fallbacks)
- ✨ **`atak-realism-ui-generator.js`** : Générateur dynamique UI admin
- ✨ Refactor **`atak-overwatch-ops.js`** : Utilisation helper centralisé

---

## 🔧 Améliorations

### Base de données
- 🔧 **Script migration unifié** : `setup-realism-migration.php` (création tables + migration + validation)
- 🔧 **Table `atak_relay_overrides`** : Support des modifications par instance
- 🔧 **Repository pattern** : `ConfigSchemaService`, `AtakRealismConfigRepository`

### API REST
- 🔧 **`GET /api/atak/realism/config`** : Récupération config active par domaine
- 🔧 **`GET /api/atak/realism/schema`** : Exposition schéma JSON complet
- 🔧 **`POST /admin/atak/realism/config/save`** : Sauvegarde avec validation stricte
- 🔧 **`GET /api/atak/roleplay/server-tests`** : Tests automatiques roleplay
- 🔧 **`GET /api/atak/relays/{mapId}/{uid}`** : Détails relais spécifique
- 🔧 **`DELETE /api/atak/relays/{mapId}/{uid}`** : Suppression relais

### Configuration mod
- 🔧 **Faction `COMSPEC_ATAK`** : Catégorie Zeus dédiée pour modules relais
- 🔧 **Export 4 fonctions** : `zeusModuleScanRelays`, `openRelayDashboard`, `addRelayDashboardActions`, `zeusResyncRelay`
- 🔧 **Init automatique** : `XEH_postInit.sqf` appelle `fn_addRelayDashboardActions` au boot
- 🔧 **Icône relais** : `app_relay_ca.paa` pour l'application "Relais AT"

---

## 🐛 Corrections de bugs

### SQF
- 🐛 **`fn_diagStatusSnapshot.sqf`** : Correction type mismatch (`Boolean` attendu, `Number` fourni)
- 🐛 **`fn_athena_updateRelay.sqf`** : Affichage mode réalisme (Arcade/Realistic) + signal bars
- 🐛 **`fn_athena_filterDrawerApps.sqf`** : Suppression app "message" dupliquée

### PHP
- 🐛 **`setup-realism-migration.php`** : Correction `Database::connection()` → `Database::getPdo()`
- 🐛 **`AtakRealismApiController.php`** : Suppression code orphelin (ParseError ligne 94)
- 🐛 **`AdminAtakRealismConfigController.php`** : Suppression code orphelin (ParseError ligne 149)

### Merge conflicts
- 🐛 Résolution conflits avec `main` : `fn_athena_updateRelay.sqf` (mode réalisme + signal bars combinés)
- 🐛 Résolution conflits `AtakRealismApiController.php` et `setup-realism-migration.php` (fixes appliqués)

---

## 🔄 Refactoring

### SQF (8 fonctions refactorées)
- 🔄 **`fn_placeAtakRelay.sqf`** : Utilisation `fn_getRealismParam` pour range/slots/throughput
- 🔄 **`fn_isNearLiveRelay.sqf`** : Portée depuis config centralisée
- 🔄 **`fn_getNearestAtakRelay.sqf`** : Range et slots depuis config centralisée
- 🔄 **`fn_syncAtakRelay.sqf`** : Defaults depuis config centralisée
- 🔄 **`fn_moduleAtakRelay.sqf`** : Min/max range depuis config centralisée
- 🔄 **`fn_reportRelaySigint.sqf`** : Range depuis config centralisée
- 🔄 **`fn_syncAtakRealism.sqf`** : Déjà conforme (certificats)
- 🔄 **`fn_athena_updateRelay.sqf`** : Affichage mode réalisme contextuel

### PHP
- 🔄 **`AdminAtakRealismConfigController.php`** : Intégration `ConfigSchemaService` pour validation
- 🔄 **`AdminAtakRoleplayController.php`** : Méthode `serverTests()` pour tests automatiques
- 🔄 **`views/admin/atak_realism/config.php`** : Refonte complète avec UI dynamique

### Web JS
- 🔄 **`atak-overwatch-ops.js`** : Utilisation `AtakRealismConfig.load()` + `get()` pour defaults

---

## ✅ Résolution des incohérences (12/12)

1. ✅ **Portée relais** : Alignée à 2000m (SQF, PHP, Web)
2. ✅ **Slots relais** : Alignés à 10 (SQF, PHP)
3. ✅ **Viewshed** : Aligné à 500m (SQF, Overwatch GL)
4. ✅ **Zone roleplay** : Rayon par défaut à 200m (PHP)
5. ✅ **Certificats** : Durée unifiée à 15 jours (SQF, PHP)
6. ✅ **Toggle link_via_relays** : Unique dans roleplay.php
7. ✅ **Seuil critique terminal** : 70% (SQF)
8. ✅ **Waypoints GPS** : Paramétrables depuis config centralisée
9. ✅ **Symbologie** : MIL-STD-2525D paramétrable (pas de duplication)
10. ✅ **Simulation réseau** : Unifiée (portail.disconnect vs client.sim)
11. ✅ **Dommages terminal** : Impact/explosion/feu centralisés
12. ✅ **ISR satellite** : Paramétrable (domaine coverage_viewshed)

---

## 📚 Documentation

### Guides techniques
- 📄 **`GUIDE-EXECUTION-MIGRATION.md`** : Exécution script migration unifié
- 📄 **`GUIDE-INTEGRATION-DASHBOARD-RELAIS-INGAME.md`** : Intégration technique dashboard
- 📄 **`SYNTHESE-FINALE-PHASES-0-3.md`** : Synthèse complète Phases 0-3
- 📄 **`RAPPORT-MERGE-CONFLICTS-MAIN-220926.md`** : Rapport résolution conflits

### Guides utilisateur
- 📄 **`TUTORIEL-JOUEUR-REALISME.md`** : Tutoriel joueur détaillé (18 pages)
- 📄 **`GUIDE-GESTION-RELAIS-ZEUS-INGAME.md`** : Guide Zeus/joueur gestion relais
- 📄 **`GUIDE-DASHBOARD-ROLEPLAY-ENHANCED.md`** : Guide dashboard Roleplay

### Guides C#
- 📄 **`docs/technique/csharp-extension/INTEGRATION-MINIMAL-PHASE3.md`** : Intégration minimale C#
- 📄 **`mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/data/icons/README-PAA-GENERATION.md`** : Génération PAA

---

## 🏗️ Architecture

### Domaines de configuration (11)
1. **radio_relays** (14 paramètres) : Portée, slots, débit, certificats, météo
2. **zones_roleplay** (9 paramètres) : Rayon, intensité, coupure, scrambling intel
3. **network_simulation** (12 paramètres) : Perte paquets, latence, défaillances
4. **certificates** (8 paramètres) : Durée, révocation, péremption
5. **terminal_damage** (11 paramètres) : Impact, explosion, feu, seuils
6. **waypoints** (7 paramètres) : Calcul itinéraires, détection communes
7. **symbology_map** (10 paramètres) : MIL-STD-2525D, axes doctrine, POI
8. **control_measures** (9 paramètres) : LD, LOA, phase lines, objectifs
9. **coverage_viewshed** (10 paramètres) : ISR satellite, latence, couverture
10. **experience_ambiance** (8 paramètres) : Sons, effets visuels, immersion
11. **other_settings** (7 paramètres) : Debug, logs, compatibilité

### Stack technique
- **Front-end** : Vanilla JS (ES6), Canvas API, AJAX, Bootstrap 5
- **Back-end** : PHP 8.1+, PDO, Repository pattern
- **Base de données** : MySQL/MariaDB
- **Mod Arma 3** : SQF, C# (NativeAOT), CBA, ACE3
- **Extension** : COMSPEC DLL (C#, partial class)

---

## ⚙️ Migration

### Prérequis
- PHP 8.1+ avec PDO
- MySQL/MariaDB
- Arma 3 Tools (pour génération PAA)
- Visual Studio 2022 (pour compilation C#)

### Étapes
1. **Base de données** :
   ```bash
   php setup-realism-migration.php --tenant-id=1
   ```

2. **Vérification** :
   - Accéder à `/admin/atak/realism/verify`
   - Tous les tests doivent être ✅

3. **Extension C#** :
   - Compiler `Extension.cs` + `Extension_Realism.cs`
   - Copier `COMSPECExtension.dll` dans `@comspec_overwatch\`

4. **Icône relais** :
   - Générer `app_relay_ca.paa` avec Arma 3 Tools
   - Copier dans `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/data/icons/`

5. **Rebuild mod** :
   ```bash
   # Utiliser Arma 3 Tools ou Addon Builder
   # Créer @comspec_overwatch.pbo
   ```

---

## 🧪 Tests recommandés

### 1. Configuration admin
- [ ] Modifier paramètres dans 9 domaines
- [ ] Appliquer profil "beginner" puis "expert"
- [ ] Vérifier historique des modifications
- [ ] Tester validation (valeurs hors bornes)

### 2. Dashboard Roleplay
- [ ] Vérifier tests serveur (8 checks)
- [ ] Interagir avec carte des zones
- [ ] Modifier config réseau/relais/sensors

### 3. Page réseau relais
- [ ] Vérifier statistiques globales
- [ ] Cliquer "Voir détails" sur un relais
- [ ] Supprimer un relais

### 4. Zeus en jeu
- [ ] Placer 5 relais ATAK
- [ ] Module "Scanner réseau relais"
- [ ] Module "Tableau de bord relais"
- [ ] Téléporter à un relais
- [ ] Module "Resync relais" sur un relais dégradé

### 5. Joueur en jeu
- [ ] Trouver laptop/tablette
- [ ] ACE → ATAK → Tableau de bord relais
- [ ] Vérifier liste des relais
- [ ] Double-clic pour détails

### 6. Extension C#
```sqf
// Debug console Arma 3
private _cfg = "COMSPECExtension" callExtension ["GetRealismConfig", []];
hint _cfg;

private _range = "COMSPECExtension" callExtension ["GetRealismParam", ["radio_relays", "relay_range_m"]];
hint _range;
```

---

## ⚠️ Breaking Changes

### Aucun (rétrocompatibilité maintenue)
- ✅ Anciennes colonnes/champs **conservés** jusqu'à Phase 4
- ✅ Anciens écrans **accessibles** avec `?legacy=1`
- ✅ Pas de régression fonctionnelle

### Migration manuelle requise
- ⚠️ Exécuter `setup-realism-migration.php` pour chaque tenant
- ⚠️ Recompiler extension C# avec nouvelles méthodes
- ⚠️ Générer PAA pour icône relais

---

## 🔮 Prochaines étapes (Phase 4)

### Cleanup (après validation terrain)
- [ ] Supprimer colonnes dupliquées dans anciennes tables
- [ ] Supprimer `server_control.php` (ancien toggle `link_via_relays`)
- [ ] Supprimer `views/admin/atak_realism/index.php` (ancien écran)
- [ ] Supprimer ancienne vue `roleplay.php`
- [ ] Vérifier paramètres orphelins

### ISR/Satellite (Phase future)
- [ ] Tasking depuis web (AOI, capteur EO/thermique)
- [ ] Latence passage satellite
- [ ] Signatures approximatives (contacts thermiques)
- [ ] Intégration météo (nuages bloquent EO)
- [ ] Consommation débit relais pour download imagerie

---

## 📊 Statistiques

- **Fichiers créés** : 27 (SQF, PHP, JS, MD)
- **Fichiers modifiés** : 18 (SQF, PHP, C#, JS, config)
- **Lignes de code ajoutées** : ~8,500
- **Paramètres centralisés** : 105 (11 domaines)
- **Incohérences résolues** : 12/12
- **Profils préréglés** : 3 (beginner, event, expert)
- **Modules Zeus** : 3 (scanner, dashboard, resync)
- **Fonctions SQF** : 8 refactorées + 8 créées
- **Méthodes C#** : 5 nouvelles
- **Endpoints API** : 6 nouveaux
- **Pages documentation** : 7 guides (60+ pages)

---

## 🙏 Crédits

**Développement** : Cursor Cloud Agent  
**Architecture** : Centralisation config réalisme ATAK  
**Audit initial** : PR #545  
**Pull Request** : #548  
**Projet** : ATHENA C2 (COMSPEC-MILSIM)

---

## 📞 Support

- **Documentation** : `/workspace/docs/`
- **Guides** : `TUTORIEL-JOUEUR-REALISME.md`, `GUIDE-GESTION-RELAIS-ZEUS-INGAME.md`
- **Vérification** : `/admin/atak/realism/verify`
- **Logs** : RPT Arma 3 `[COMSPEC Overwatch]`

---

**Version** : 1.7.0  
**Date de release** : 22 septembre 2026  
**Statut** : ✅ Prêt pour validation terrain
