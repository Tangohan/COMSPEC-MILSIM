# Audit — filtrage `tenant_id` et isolement des communautés

Objectif : à mesure que la plateforme accueille **plusieurs tenants** (communautés), garantir qu’**aucune lecture ou écriture métier** ne mélange les données entre tenants. Une erreur sur une clause `WHERE tenant_id = ?` peut exposer des données sensibles.

## Règles

1. **Source de vérité session** : le `tenant_id` actif vient de la session utilisateur après authentification (et éventuellement `switchToTenant` / URL `/c/{slug}`). Ne jamais faire confiance à un `tenant_id` fourni par le client seul sans recoupement.
2. **Requêtes SQL** : toute requête sur une table partitionnée par `tenant_id` doit inclure `tenant_id` dans le `WHERE` (ou jointure équivalente vérifiable).
3. **IDs métier** : accès à un enregistrement par `id` (topic, document, etc.) doit **toujours** être couplé à `tenant_id = :current_tenant` pour éviter l’énumération cross-tenant.
4. **Super-admin / cross-tenant** : réservé aux routes d’administration système, avec contrôle d’accès explicite — ne pas réutiliser les mêmes méthodes repository que le périmètre membre.

## Revue par couche (checklist)

| Couche | Vérifier |
|--------|----------|
| Repositories | Présence de `tenant_id` dans SELECT/UPDATE/DELETE ; pas de `SELECT *` sans filtre tenant sur tables multi-tenant. |
| Controllers | `tenant_id` issu du contexte auth, pas du corps de requête non validé. |
| API / JSON | Même discipline ; pas d’ID d’objet d’un autre tenant via paramètre. |
| Jobs / scripts | Si un job traite un tenant, scoper explicitement ; pas de requête « globale » par erreur. |

## Fichiers à auditer en priorité (non exhaustif)

- `app/Repositories/*Repository.php` — tout dépôt touchant `users`, `forum_*`, `documents`, `personnel_*`, `training_*`, `atak_*`, courrier, etc.
- Contrôleurs forum : catégories, sujets, messages — déjà modélisés avec `tenant_id` sur les tables ; vérifier les chemins « modération » et « admin ».

## Tests manuels — forum multi-espaces (non-régression)

À répéter après tout changement sur le routage communauté ou la session.

1. Créer **deux** tenants (communautés) A et B avec des slugs distincts.
2. Compte utilisateur **U** présent uniquement sur le tenant A : se connecter avec le slug A, poster un sujet sur le forum A.
3. Créer un utilisateur **V** uniquement sur B ; vérifier que le forum B ne liste pas les sujets de A.
4. Utilisateur avec compte sur A et B (même email, deux lignes `users`) : utiliser `/c/{slug}/forum` ou **changer de communauté** depuis le tableau de bord ; vérifier que la liste des sujets et les URLs de sujet correspondent au tenant actif.
5. Tenter d’accéder à une URL de sujet du tenant A en étant positionné sur le tenant B (si l’URL encode l’ID) : doit **échouer** ou rediriger (404 / accès refusé), pas afficher le contenu.

## Automatisation future

Lorsque le projet introduit PHPUnit (ou autre), ajouter des tests d’intégration qui : créent deux tenants, insèrent des données minimales, et assertent l’isolement via les repositories.

---

*Complète la Phase 1 du plan plateforme (audit filtrage `tenant_id`).*
