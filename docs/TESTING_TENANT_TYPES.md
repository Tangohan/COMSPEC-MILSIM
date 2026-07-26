# Guide de test - Types de tenant

Ce document décrit les étapes de test pour valider le bon fonctionnement du système de types de tenant.

## Prérequis

1. Base de données à jour avec la migration `20260724000001_tenant_type.sql`
2. Accès administrateur au portail
3. Compte utilisateur pour créer des communautés

## Tests à effectuer

### 1. Migration de la base de données

**Objectif** : Vérifier que la colonne `tenant_type` a été ajoutée correctement.

**Commandes** :
```bash
# Exécuter la migration
php run-migrations.php

# Vérifier la structure
mysql -u [user] -p [database] -e "DESCRIBE tenants;"
```

**Résultat attendu** :
- Colonne `tenant_type` présente après `slug`
- Type : `VARCHAR(32)`
- Valeur par défaut : `'full'`
- Index `idx_tenants_type` créé

**Validation** :
- [ ] La colonne `tenant_type` existe
- [ ] Les tenants existants ont `tenant_type = 'full'`

---

### 2. Interface de création de communauté

**Objectif** : Vérifier l'affichage du sélecteur de type.

**Étapes** :
1. Se connecter avec un compte utilisateur
2. Accéder à `/communities/create`
3. Observer le formulaire

**Résultat attendu** :
- Trois cartes cliquables présentes :
  - **Complet** (grise) : "Accès à tous les modules..."
  - **Effectifs** (bleue) : "Gestion simplifiée : Pseudo, Indicatif..."
  - **ATAK** (ambrée) : "Uniquement le module ATAK..."
- La carte "Complet" est sélectionnée par défaut
- Cliquer sur une carte la sélectionne (bordure verte)

**Validation** :
- [ ] Les trois cartes sont visibles
- [ ] Les descriptions sont claires et en français
- [ ] La sélection fonctionne (radio button)
- [ ] "Complet" est sélectionné par défaut

---

### 3. Création d'une communauté type "Effectifs"

**Objectif** : Créer une communauté limitée aux modules Personnel et Analytics.

**Étapes** :
1. Aller sur `/communities/create`
2. Remplir le formulaire :
   - Nom : "Test Effectifs"
   - **Sélectionner le type "Effectifs"**
   - Compléter le wizard (locale, timezone, etc.)
3. Valider la création
4. Se connecter à la communauté créée

**Résultat attendu** :
- Communauté créée avec succès
- Navigation affichée :
  - ✅ Briefing / Dashboard
  - ✅ Personnel / Effectifs
  - ✅ Analytics (si dans le menu)
  - ❌ Forum
  - ❌ Documents
  - ❌ Formation
  - ❌ ATAK
  - ❌ Opérations
  - ❌ Recrutement

**Tests d'accès direct** :
```
/forum          → Redirection vers /dashboard + message d'erreur
/training       → Redirection vers /dashboard + message d'erreur
/documents      → Redirection vers /dashboard + message d'erreur
/personnel      → ✅ Accessible
```

**Validation** :
- [ ] Les modules non autorisés sont masqués dans la navigation
- [ ] L'accès direct aux URLs des modules interdits est bloqué
- [ ] Le module Personnel est accessible
- [ ] Message d'erreur clair : "Ce module n'est pas accessible pour votre type de communauté"

---

### 4. Création d'une communauté type "ATAK"

**Objectif** : Créer une communauté limitée au module ATAK uniquement.

**Étapes** :
1. Aller sur `/communities/create`
2. Remplir le formulaire :
   - Nom : "Test ATAK"
   - **Sélectionner le type "ATAK"**
   - Compléter le wizard
3. Valider la création
4. Se connecter à la communauté créée

**Résultat attendu** :
- Communauté créée avec succès
- Navigation affichée :
  - ✅ Briefing / Dashboard
  - ✅ ATAK (situation tactique)
  - ❌ Forum
  - ❌ Documents
  - ❌ Formation
  - ❌ Personnel
  - ❌ Opérations (hors ATAK)
  - ❌ Recrutement

**Tests d'accès direct** :
```
/forum          → Redirection vers /dashboard + message d'erreur
/personnel      → Redirection vers /dashboard + message d'erreur
/atak           → ✅ Accessible
```

**Validation** :
- [ ] Seuls Admin et ATAK sont accessibles
- [ ] L'accès direct aux autres modules est bloqué
- [ ] Le module ATAK fonctionne normalement

---

### 5. Création d'une communauté type "Complet"

**Objectif** : Vérifier que le comportement par défaut est inchangé.

**Étapes** :
1. Aller sur `/communities/create`
2. Remplir le formulaire :
   - Nom : "Test Complet"
   - **Laisser "Complet" sélectionné** (défaut)
   - Compléter le wizard
3. Valider la création
4. Se connecter à la communauté créée

**Résultat attendu** :
- Communauté créée avec succès
- Navigation affichée :
  - ✅ Forum
  - ✅ Documents
  - ✅ Formation
  - ✅ Personnel
  - ✅ ATAK
  - ✅ Opérations
  - ✅ Recrutement
  - ✅ Tous les autres modules

**Validation** :
- [ ] Tous les modules sont accessibles
- [ ] Comportement identique à avant cette PR
- [ ] Aucune régression détectée

---

### 6. Interface d'administration

**Objectif** : Vérifier l'affichage des types dans l'interface admin.

**Étapes** :
1. Se connecter avec un compte admin plateforme
2. Accéder à `/admin/system/tenants`
3. Observer le tableau des communautés

**Résultat attendu** :
- Colonne "Type" présente entre "Communauté" et "Formule"
- Badges colorés :
  - Badge gris "Complet" pour les communautés full
  - Badge bleu "Effectifs" pour les communautés effectifs
  - Badge ambre "ATAK" pour les communautés atak
- Les trois communautés de test sont listées avec leur type

**Validation** :
- [ ] La colonne Type est visible
- [ ] Les badges sont correctement affichés
- [ ] Les couleurs sont distinctes
- [ ] Le tableau reste lisible

---

### 7. Communautés existantes

**Objectif** : Vérifier que les communautés existantes ne sont pas affectées.

**Étapes** :
1. Se connecter à une communauté créée **avant** cette PR
2. Vérifier la navigation
3. Tester l'accès aux différents modules

**Résultat attendu** :
- Tous les modules sont accessibles (type `full` par défaut)
- Aucun changement de comportement
- Dans l'interface admin, ces communautés affichent le badge gris "Complet"

**Validation** :
- [ ] Les communautés existantes fonctionnent normalement
- [ ] Aucune régression détectée
- [ ] Le type affiché est "Complet"

---

### 8. Permissions et rôles

**Objectif** : Vérifier que les permissions créées sont cohérentes avec le type.

**Étapes** :
1. Pour chaque communauté de test, vérifier en BDD :
   ```sql
   SELECT p.slug, p.module
   FROM permissions p
   WHERE p.tenant_id = [tenant_id]
   ORDER BY p.module, p.slug;
   ```

**Résultat attendu** :

**Type Effectifs** :
- Permissions admin.* présentes
- Permissions personnel.* présentes
- Permissions analytics.* présentes (si elles existent)
- **Aucune** permission forum.*, training.*, atak.*, etc.

**Type ATAK** :
- Permissions admin.* présentes
- Permissions atak.* présentes
- **Aucune** permission forum.*, training.*, personnel.*, etc.

**Type Complet** :
- Toutes les permissions présentes (comportement standard)

**Validation** :
- [ ] Les permissions sont correctement filtrées pour type "Effectifs"
- [ ] Les permissions sont correctement filtrées pour type "ATAK"
- [ ] Les permissions sont complètes pour type "Complet"

---

### 9. Seeds de données

**Objectif** : Vérifier que le bootstrap ne crée pas de données inutiles.

**Étapes** :
1. Pour la communauté "Test Effectifs", vérifier :
   ```sql
   SELECT COUNT(*) FROM forum_categories WHERE tenant_id = [tenant_id];
   SELECT COUNT(*) FROM training_courses WHERE tenant_id = [tenant_id];
   ```

2. Pour la communauté "Test ATAK", vérifier :
   ```sql
   SELECT COUNT(*) FROM forum_categories WHERE tenant_id = [tenant_id];
   SELECT COUNT(*) FROM personnel_panels WHERE tenant_id = [tenant_id];
   ```

**Résultat attendu** :

**Type Effectifs** :
- Aucune catégorie de forum
- Aucun parcours de formation
- Panels personnel créés ✅

**Type ATAK** :
- Aucune catégorie de forum
- Aucun panel personnel
- Configuration ATAK présente ✅

**Validation** :
- [ ] Pas de seed inutile pour "Effectifs"
- [ ] Pas de seed inutile pour "ATAK"
- [ ] Seed complet pour "Complet"

---

### 10. Test de régression

**Objectif** : S'assurer qu'aucune fonctionnalité existante n'est cassée.

**Checklist générale** :
- [ ] Création de communauté "Complet" fonctionne
- [ ] Login/logout fonctionnent
- [ ] Navigation dans une communauté existante fonctionne
- [ ] Forum accessible (communauté Complet)
- [ ] Formation accessible (communauté Complet)
- [ ] ATAK accessible (communauté Complet)
- [ ] Personnel accessible (communauté Complet)
- [ ] Interface admin fonctionne
- [ ] Changement de communauté fonctionne

---

## Checklist finale

Avant de valider la PR, s'assurer que :

**Fonctionnel** :
- [ ] Les trois types de tenant sont créables
- [ ] Le filtrage des modules fonctionne correctement
- [ ] Les accès directs sont bloqués
- [ ] Les communautés existantes ne sont pas affectées

**Interface** :
- [ ] Le sélecteur de type est ergonomique
- [ ] Les descriptions sont claires (français, sans jargon)
- [ ] L'interface admin affiche correctement les types
- [ ] Les messages d'erreur sont explicites

**Sécurité** :
- [ ] Le middleware bloque les accès non autorisés
- [ ] Les permissions sont correctement filtrées
- [ ] Pas de contournement possible via URL directe

**Base de données** :
- [ ] La migration s'exécute sans erreur
- [ ] Les tenants existants ont tenant_type = 'full'
- [ ] Les nouveaux tenants ont le bon type

**Documentation** :
- [ ] La documentation tenant-types.md est complète
- [ ] Les exemples d'utilisation sont clairs
- [ ] La FAQ répond aux questions courantes

---

## Rapporter un problème

Si un test échoue :
1. Noter le scénario de test concerné
2. Copier le message d'erreur exact
3. Vérifier les logs serveur
4. Créer un commentaire sur la PR avec :
   - Scénario de test
   - Résultat attendu
   - Résultat obtenu
   - Logs pertinents

---

## Rollback

En cas de problème majeur après déploiement :

```sql
-- Passer tous les tenants en mode "full"
UPDATE tenants SET tenant_type = 'full';

-- Supprimer la colonne (en dernier recours)
ALTER TABLE tenants DROP COLUMN tenant_type;
DROP INDEX idx_tenants_type ON tenants;
```

⚠️ **Attention** : Cela fera perdre l'information de type pour toutes les communautés.
