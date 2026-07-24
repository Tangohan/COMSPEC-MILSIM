# Changelog - Types de Tenant

Liste détaillée des fichiers modifiés et ajoutés pour l'implémentation du système de types de tenant.

## Fichiers créés

### 1. `migrations/20260724000001_tenant_type.sql`
**Type** : Migration SQL

**Description** : Ajout de la colonne `tenant_type` dans la table `tenants`.

**Contenu** :
- Nouvelle colonne `VARCHAR(32)` avec valeur par défaut `'full'`
- Index `idx_tenants_type` pour optimiser les requêtes
- UPDATE pour mettre à jour les tenants existants

---

### 2. `app/Services/Community/TenantTypeConfig.php`
**Type** : Service PHP

**Description** : Service central de configuration des types de tenant.

**Responsabilités** :
- Définit les types disponibles (full, effectifs, atak)
- Liste les modules autorisés par type
- Définit les permissions de base par type
- Définit les rôles de base par type
- Configure le seed minimal selon le type
- Fournit des méthodes de validation et normalisation

**Méthodes principales** :
- `availableTypes()` : Liste des types avec labels et descriptions
- `allowedModulesByType()` : Modules accessibles par type
- `basePermissionsByType()` : Permissions à créer selon le type
- `baseRolesByType()` : Rôles à créer selon le type
- `isValidType()` : Validation d'un type
- `normalizeType()` : Normalisation avec fallback sur 'full'
- `moduleAllowed()` : Vérification d'accès à un module
- `getSeedConfig()` : Configuration de seed selon le type

---

### 3. `app/Middleware/TenantTypeModuleAccessMiddleware.php`
**Type** : Middleware PHP

**Description** : Middleware de sécurité bloquant l'accès aux modules non autorisés.

**Fonctionnement** :
1. Récupère le tenant_id de la session
2. Charge le type de tenant depuis la BDD
3. Extrait le module demandé depuis l'URI
4. Vérifie si le module est autorisé pour ce type
5. Redirige vers `/dashboard` si accès refusé

**Map des modules** :
- `/forum/*` → module `forum`
- `/training/*`, `/courses/*` → module `training`
- `/atak/*` → module `atak`
- `/personnel/*` → module `personnel`
- Etc.

---

### 4. `docs/tenant-types.md`
**Type** : Documentation

**Description** : Documentation complète des types de tenant.

**Sections** :
- Vue d'ensemble
- Types disponibles avec cas d'usage
- Guide de création
- Sécurité et restrictions
- Administration
- Migration et compatibilité
- Configuration technique
- FAQ

---

### 5. `docs/TESTING_TENANT_TYPES.md`
**Type** : Documentation de test

**Description** : Guide de test détaillé pour valider l'implémentation.

**Sections** :
- Prérequis
- 10 scénarios de test détaillés
- Checklist finale de validation
- Procédure de rollback

---

## Fichiers modifiés

### 1. `app/Repositories/TenantRepository.php`
**Modifications** :

#### Méthode `create()`
- **Avant** : `public function create(string $name, string $slug, string $planSlug = 'free'): int`
- **Après** : `public function create(string $name, string $slug, string $planSlug = 'free', string $tenantType = 'full'): int`
- Ajout du paramètre `$tenantType` dans l'INSERT

#### Nouvelle méthode `getTenantType()`
```php
public function getTenantType(int $tenantId): string
{
    $tenant = $this->findById($tenantId);
    return $tenant ? (string) ($tenant['tenant_type'] ?? 'full') : 'full';
}
```

#### Méthode `listOverviewForPlatform()`
- Ajout de `t.tenant_type` dans la requête SQL SELECT
- Ajout de `'tenant_type' => (string) ($row['tenant_type'] ?? 'full')` dans le tableau retourné

---

### 2. `app/Services/Community/TenantBootstrapService.php`
**Modifications** :

#### Méthode `createCommunity()`
- Récupération du type depuis `$options['tenant_type']`
- Normalisation via `TenantTypeConfig::normalizeType()`
- Passage du type à `$this->tenantRepository->create()`
- Appel conditionnel du seed selon le type :
  - Si `TYPE_FULL` : seed complet (comportement actuel)
  - Sinon : appel de `seedSimplifiedTenant()`

#### Nouvelle méthode `seedSimplifiedTenant()`
```php
private function seedSimplifiedTenant(
    PDO $pdo,
    int $tenantId,
    string $tenantType,
    int $communityOwnerRoleId,
    int $tenantAdminRoleId
): void
```
- Crée uniquement les permissions nécessaires selon le type
- Crée uniquement les rôles nécessaires selon le type
- Applique les seeds spécifiques (ex : panels personnel pour type "effectifs")

---

### 3. `app/Controllers/Web/CommunityController.php`
**Modifications** :

#### Méthode `create()`
- Récupération du type depuis le formulaire : `$request->input('tenant_type', 'full')`
- Normalisation via `TenantTypeConfig::normalizeType()`
- Ajout de `'tenant_type' => $tenantType` dans `$optionsBase`

---

### 4. `app/Support/navigation_menu.php`
**Modifications** :

#### Fonction `navigation_menu_item_visible()`
- Ajout d'un bloc de vérification du type de tenant :
```php
if ($loggedIn && !empty($item['module'])) {
    $tenantId = (int) (\App\Core\Session::get('tenant_id') ?? 0);
    if ($tenantId > 0) {
        try {
            $tenantRepo = \App\Core\Container::get(\App\Repositories\TenantRepository::class);
            $tenant = $tenantRepo->findById($tenantId);
            if ($tenant) {
                $tenantType = (string) ($tenant['tenant_type'] ?? 'full');
                if (!(\App\Services\Community\TenantTypeConfig::moduleAllowed($tenantType, (string) $item['module']))) {
                    return false;
                }
            }
        } catch (\Throwable) {
        }
    }
}
```

---

### 5. `config/navigation.php`
**Modifications** :

#### Ajout de l'attribut `module` aux items de menu
- Exemple : `['label' => 'Accueil du forum', ..., 'module' => 'forum', ...]`
- Items modifiés :
  - Forum : `'module' => 'forum'`
  - Messagerie interne : `'module' => 'messages'`
  - (D'autres items peuvent être ajoutés selon les besoins)

⚠️ **Note** : Tous les items de menu n'ont pas encore l'attribut `module`. Cela peut être complété progressivement selon les besoins.

---

### 6. `views/community/create.php`
**Modifications** :

#### Ajout du sélecteur de type
Après le champ "Nom affiché", ajout d'une section "Type de communauté" :

```php
<div class="md:col-span-2">
    <label class="mb-2 block text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Type de communauté</label>
    <p class="mb-3 text-xs leading-relaxed text-slate-600">Choisissez le profil adapté à votre besoin. Les profils simplifiés donnent accès uniquement aux modules essentiels.</p>
    <div class="grid gap-3 md:grid-cols-3">
        <?php
        $tenantTypes = \App\Services\Community\TenantTypeConfig::availableTypes();
        foreach ($tenantTypes as $typeSlug => $typeInfo):
        ?>
        <label class="flex cursor-pointer flex-col rounded-2xl border-2 border-slate-200 bg-white px-4 py-4 transition hover:border-emerald-500 has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50">
            <input type="radio" name="tenant_type" value="<?= htmlspecialchars($typeSlug, ENT_QUOTES, 'UTF-8') ?>" class="peer sr-only" <?= $typeSlug === 'full' ? 'checked' : '' ?>>
            <span class="mb-1 text-sm font-black text-slate-900"><?= htmlspecialchars($typeInfo['label'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="text-xs leading-relaxed text-slate-600"><?= htmlspecialchars($typeInfo['description'], ENT_QUOTES, 'UTF-8') ?></span>
        </label>
        <?php endforeach; ?>
    </div>
</div>
```

**Design** :
- Trois cartes côte à côte (responsive)
- Radio buttons masqués (accessible)
- Bordure verte au focus/sélection
- Type "Complet" sélectionné par défaut

---

### 7. `views/admin/system/tenants_index.php`
**Modifications** :

#### Ajout d'une colonne "Type"
- Header : `<th>Type</th>` entre "Communauté" et "Formule"
- Colspan modifié : `colspan="7"` au lieu de `"6"`

#### Affichage du badge de type
```php
$tenantType = (string) ($t['tenant_type'] ?? 'full');
$tenantTypeLabels = [
    'full' => ['label' => 'Complet', 'color' => 'slate'],
    'effectifs' => ['label' => 'Effectifs', 'color' => 'blue'],
    'atak' => ['label' => 'ATAK', 'color' => 'amber'],
];
$typeInfo = $tenantTypeLabels[$tenantType] ?? ['label' => $tenantType, 'color' => 'slate'];
```

```html
<td class="px-4 py-3">
    <span class="inline-flex rounded-md bg-<?= htmlspecialchars($typeInfo['color'], ENT_QUOTES, 'UTF-8') ?>-50 px-2 py-1 text-xs font-semibold text-<?= htmlspecialchars($typeInfo['color'], ENT_QUOTES, 'UTF-8') ?>-950">
        <?= htmlspecialchars($typeInfo['label'], ENT_QUOTES, 'UTF-8') ?>
    </span>
</td>
```

**Badges** :
- Complet : fond gris (`bg-slate-50`), texte gris foncé
- Effectifs : fond bleu clair (`bg-blue-50`), texte bleu foncé
- ATAK : fond ambre clair (`bg-amber-50`), texte ambre foncé

---

## Résumé des changements

### Nouveaux fichiers : 5
- 1 migration SQL
- 2 services/middleware PHP
- 2 fichiers de documentation

### Fichiers modifiés : 7
- 4 fichiers PHP backend (repos, services, contrôleurs)
- 1 fichier de support (navigation)
- 1 fichier de configuration (navigation)
- 1 vue (création communauté)
- 1 vue admin (liste tenants)

### Lignes de code approximatives
- **Ajoutées** : ~1 200 lignes
  - Code PHP : ~450 lignes
  - Documentation : ~750 lignes
- **Modifiées** : ~50 lignes
- **Total** : ~1 250 lignes

---

## Impact sur le code existant

### Rétrocompatibilité
✅ **Totale** : Les communautés existantes passent automatiquement en type `'full'` et conservent leur comportement actuel.

### Nouveaux concepts introduits
1. **Type de tenant** : Nouvelle propriété définissant le périmètre fonctionnel
2. **Filtrage de navigation** : Masquage automatique des modules non autorisés
3. **Middleware de sécurité** : Blocage des accès directs aux URLs interdites
4. **Bootstrap conditionnel** : Seed adapté selon le type

### Points d'extension
- Ajout de nouveaux types : modifier `TenantTypeConfig`
- Ajout de modules : compléter `allowedModulesByType()`
- Personnalisation des seeds : modifier `seedSimplifiedTenant()`

---

## Tests recommandés

Voir le fichier `TESTING_TENANT_TYPES.md` pour la checklist complète.

**Tests prioritaires** :
1. ✅ Création des trois types de tenant
2. ✅ Filtrage de navigation selon le type
3. ✅ Blocage d'accès aux modules interdits
4. ✅ Communautés existantes inchangées
5. ✅ Affichage dans l'interface admin

---

## Prochaines étapes possibles

### Court terme
- Compléter l'ajout de l'attribut `module` dans `config/navigation.php`
- Ajouter des tests unitaires pour `TenantTypeConfig`
- Ajouter des tests fonctionnels pour le middleware

### Moyen terme
- Permettre la modification du type après création (avec migration de données)
- Ajouter une interface admin pour changer le type d'un tenant
- Statistiques par type dans l'interface opérateur

### Long terme
- Types personnalisés définis par l'administrateur
- Templates de types prédéfinis supplémentaires
- Export/import de configuration de type

---

## Auteur et date

**Feature** : Système de types de tenant simplifiés  
**Date** : 2026-07-24  
**Branche** : `cursor/tenant-type-simple-8112`  
**PR** : #145  
**Commits** : 4
1. `d0f4e933` - feat: ajout du système de types de tenant simplifiés
2. `f8f6df52` - feat: affichage du type de tenant dans l'interface admin
3. `3c8cec7f` - docs: ajout de la documentation sur les types de tenant
4. `fbe7c521` - docs: guide de test complet pour les types de tenant
