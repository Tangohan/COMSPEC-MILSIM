# Intervention Super-Admin dans un tenant

## Architecture et invariants

L'intervention n'est **pas une impersonation** : l'identité et le rôle du gestionnaire restent ceux de son compte plateforme. La route d'entrée, protégée par `SystemAdminMiddleware`, valide le tenant en base puis crée une session d'intervention immuable. Aucun `tenant_id` envoyé par le navigateur n'est repris.

`TenantContext` est l'autorité centrale. À chaque requête, `AuthMiddleware` recharge le véritable compte et son RBAC sur son tenant d'origine. Ce n'est qu'après avoir vérifié `admin.system`, l'identité `platform_admin_id` et le marqueur de contexte que le contexte tenant est activé. Le pont temporaire vers `Session::tenant_id` maintient les anciens repositories tenant-scoped ; il est restauré dans un `finally`. Le bypass RBAC est local à la requête vérifiée.

## Données de session

- `platform_admin_id`, `admin_tenant_id`, `admin_tenant_started_at` ;
- `admin_tenant_session_id`, `admin_tenant_reason` ;
- `platform_admin_tenant_context` (booléen serveur).

La session PHP est régénérée à l'entrée et à la sortie. Une session active en base alimente le bandeau des membres, sans exposer le nom du gestionnaire.

## Audit et secrets

`TenantAdminAuditService` centralise les snapshots CREATE/UPDATE/DELETE, actions, erreurs et restaurations. Le middleware garantit une trace de toute requête mutante, même quand un module n'a pas encore fourni un snapshot métier détaillé. Les intégrations doivent appeler `recordCreate`, `recordUpdate` ou `recordDelete` dans la même unité de travail que leur mutation. Les clés contenant password, token, secret, API key, cookie, JWT ou private key sont remplacées par `[REDACTED]`.

Les lignes d'audit ne sont jamais supprimées et les FK emploient `RESTRICT`. En production, accorder à l'utilisateur SQL applicatif INSERT/SELECT/UPDATE ciblé mais jamais DELETE sur ces quatre tables et exporter périodiquement les journaux vers un stockage WORM.

## Rollback

Le registre fermé `ENTITY_TABLES` empêche toute injection d'identifiant SQL. Le moteur verrouille l'entité (`FOR UPDATE`), contrôle tenant et session, compare l'état actuel à `after_state`, vérifie implicitement les FK dans une transaction puis applique le snapshot. Un conflit produit le message explicite demandé, sans écrasement. Le rollback crée une nouvelle action liée par `rollback_of_action_id`, puis renseigne `rolled_back_by_action_id` sur l'original.

Ajouter un type au registre exige une revue : fidélité du snapshot, colonnes générées, dépendances FK et stratégie de réhydratation DELETE. Les checkpoints sont persistés par la migration ; le rollback groupé devra pré-valider toutes les actions et les exécuter en ordre inverse dans une transaction unique avant son activation UI.

## Déploiement et exploitation

1. Exécuter `migratePlatformAdminTenantIntervention()` via le pipeline de migration.
2. Ouvrir `/admin/system/tenants/{id}/intervention` et saisir le motif.
3. Utiliser le journal enrichi depuis le bandeau permanent.
4. Toujours quitter via « Quitter l'organisation » afin de clôturer la session.

Les exceptions traversant le pipeline sont rattachées au `request_id` généré par `RequestIdMiddleware`. Les erreurs PHP convertibles en exceptions suivent le même chemin via l'`ExceptionHandler`; les arrêts fatals doivent également être corrélés dans le collecteur central de logs d'infrastructure.
