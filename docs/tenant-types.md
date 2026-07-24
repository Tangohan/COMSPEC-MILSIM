# Types de Tenant - Documentation

## Vue d'ensemble

Le système de types de tenant permet de créer des communautés avec des accès restreints à certains modules uniquement. Cela permet de proposer des configurations simplifiées adaptées à des besoins spécifiques.

## Types disponibles

### Type Complet (`full`)

**Description** : Accès à tous les modules de la plateforme

**Modules accessibles** :
- Administration
- Forum & discussions
- Documents & équipement
- Personnel & effectifs
- Formation & parcours
- Recrutement
- Opérations & missions
- ATAK (coordination tactique)
- Coopération inter-communautés
- Messagerie interne
- Analytics & tableaux de bord

**Cas d'usage** : Communautés MilSim complètes nécessitant tous les outils de gestion, formation et coordination.

---

### Type Effectifs (`effectifs`)

**Description** : Gestion simplifiée des effectifs uniquement

**Modules accessibles** :
- Administration
- Personnel & effectifs
- Analytics (tableaux de bord basiques)

**Fonctionnalités disponibles** :
- Pseudo et indicatif (CallSign)
- Fonction et rôle
- Affectations aux unités
- Position administrative
- Grades et progression

**Modules non accessibles** :
- Forum, documents, formation, recrutement, opérations, ATAK, coopération, messagerie

**Cas d'usage** : 
- Petites équipes ne nécessitant qu'un registre d'effectifs
- Structures souhaitant uniquement gérer les affectations
- Usage ponctuel pour un événement ou une opération spécifique

---

### Type ATAK (`atak`)

**Description** : Coordination tactique ATAK uniquement

**Modules accessibles** :
- Administration
- ATAK (situation tactique)

**Fonctionnalités disponibles** :
- Vue cartographique tactique
- Coordination des opérateurs
- Partage de position et données tactiques
- Configuration des certificats et accès

**Modules non accessibles** :
- Forum, documents, formation, recrutement, opérations (hors ATAK), personnel, coopération, messagerie

**Cas d'usage** :
- Équipes dédiées à la coordination tactique terrain
- Usage exclusif pour exercices avec ATAK
- Communautés n'utilisant que la cartographie tactique

---

## Création d'une communauté avec type spécifique

### Interface de création

1. Accéder au formulaire de création de communauté
2. Renseigner le nom de la communauté
3. **Sélectionner le type de communauté** via les cartes présentées :
   - Carte "Complet" : grisée, accès total
   - Carte "Effectifs" : bleue, gestion d'effectifs
   - Carte "ATAK" : ambrée, coordination tactique
4. Compléter le reste du formulaire (wizard)
5. Valider la création

### Comportement automatique

- Les modules non autorisés sont **automatiquement masqués** dans la navigation
- Les permissions sont **créées uniquement pour les modules autorisés**
- Le seed de la base de données est **adapté au type** (pas de forum si type ATAK, etc.)

---

## Sécurité et restrictions

### Filtrage de la navigation

Les items de menu correspondant à des modules non autorisés sont automatiquement masqués pour les membres de la communauté.

**Exemple** : Un membre d'une communauté de type "Effectifs" ne verra pas les liens vers le forum, la formation ou ATAK.

### Protection des accès directs

Le middleware `TenantTypeModuleAccessMiddleware` bloque les tentatives d'accès direct via URL.

**Exemple** : Si un utilisateur tente d'accéder à `/forum` sur une communauté de type "ATAK", il sera redirigé vers le tableau de bord avec un message d'erreur.

### Permissions adaptées

Les permissions créées lors du bootstrap sont **limitées aux modules autorisés** :
- Type "Effectifs" : permissions personnel.* uniquement
- Type "ATAK" : permissions atak.* uniquement
- Type "Complet" : toutes les permissions

---

## Administration

### Vue d'ensemble des communautés

L'interface d'administration (`/admin/system/tenants`) affiche :
- **Colonne Type** : badge coloré indiquant le type de chaque communauté
  - Badge gris : Complet
  - Badge bleu : Effectifs
  - Badge ambre : ATAK

### Modification du type

⚠️ **Attention** : Le type d'une communauté ne peut pas être modifié après création. Pour changer de type, il faut créer une nouvelle communauté.

**Raison** : Le changement de type impliquerait :
- Suppression/ajout de permissions
- Modification des rôles
- Réorganisation de la structure de données
- Perte potentielle de données (ex : passer de "Complet" à "ATAK" ferait perdre toutes les discussions du forum)

---

## Migration et compatibilité

### Communautés existantes

Toutes les communautés existantes sont automatiquement de type `full` (Complet) après migration.

**Aucun changement de comportement** pour les communautés existantes.

### Migration SQL

```sql
ALTER TABLE `tenants` 
ADD COLUMN `tenant_type` VARCHAR(32) NOT NULL DEFAULT 'full' AFTER `slug`,
ADD INDEX `idx_tenants_type` (`tenant_type`);
```

La colonne a une valeur par défaut `'full'` pour assurer la compatibilité.

---

## Configuration technique

### Service `TenantTypeConfig`

Le service `App\Services\Community\TenantTypeConfig` centralise :
- La liste des types disponibles
- Les modules autorisés par type
- Les permissions de base par type
- Les rôles de base par type
- La configuration de seed

### Middleware

Le middleware `App\Middleware\TenantTypeModuleAccessMiddleware` :
- Vérifie le type de tenant de la session active
- Extrait le module demandé depuis l'URI
- Bloque l'accès si le module n'est pas autorisé
- Redirige vers le tableau de bord avec un message d'erreur

### Filtrage de navigation

La fonction `navigation_menu_item_visible()` dans `app/Support/navigation_menu.php` :
- Vérifie si l'item de menu a un attribut `module`
- Récupère le type de tenant depuis la session
- Masque l'item si le module n'est pas autorisé

---

## Exemples d'utilisation

### Équipe d'événement ponctuel

**Type recommandé** : Effectifs

Une équipe organisatrice d'un événement peut utiliser le type "Effectifs" pour gérer simplement la liste des participants, leurs affectations et leurs rôles, sans avoir besoin du forum ou de la formation.

### Unité de coordination ATAK

**Type recommandé** : ATAK

Une unité spécialisée dans la coordination tactique terrain peut utiliser le type "ATAK" pour se concentrer exclusivement sur la cartographie et le partage de position.

### Communauté MilSim complète

**Type recommandé** : Complet

Une communauté MilSim active nécessitant tous les outils (recrutement, formation, briefings forum, coordination ATAK) doit choisir le type "Complet".

---

## FAQ

### Puis-je changer le type d'une communauté après création ?

Non, le type est défini à la création et ne peut pas être modifié. Créez une nouvelle communauté si vous avez besoin d'un autre type.

### Les communautés existantes sont-elles affectées ?

Non, toutes les communautés existantes restent de type "Complet" et conservent leur comportement actuel.

### Un membre peut-il voir qu'il est dans une communauté restreinte ?

Oui, indirectement : les modules non disponibles n'apparaissent pas dans la navigation. Mais il n'y a pas d'indication explicite du type de communauté dans l'interface membre.

### Peut-on créer des types personnalisés ?

Non, pour le moment seuls les trois types prédéfinis sont disponibles. L'ajout de types personnalisés nécessiterait une évolution du code.

### Les effectifs d'une communauté "ATAK" sont-ils gérés ?

Oui, le module admin permet toujours de gérer les utilisateurs, rôles et grades basiques. Seul le module "Personnel" complet (avec fiches détaillées) n'est pas accessible.

---

## Support et assistance

Pour toute question ou problème concernant les types de tenant :
1. Consultez cette documentation
2. Vérifiez les logs serveur en cas d'erreur
3. Contactez l'équipe de développement si nécessaire

**Fichiers clés** :
- `app/Services/Community/TenantTypeConfig.php` : configuration des types
- `app/Middleware/TenantTypeModuleAccessMiddleware.php` : sécurité d'accès
- `migrations/20260724000001_tenant_type.sql` : migration BDD
- `views/community/create.php` : interface de sélection
