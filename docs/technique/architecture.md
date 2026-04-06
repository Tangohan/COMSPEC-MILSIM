# Architecture applicative

## Vue d’ensemble

L’application est une **application web monolithique** en **PHP** servie depuis le répertoire `public/` (document root). Le flux principal est :

1. **Point d’entrée** : chargement de l’environnement, configuration des erreurs selon `APP_DEBUG`, enregistrement d’un gestionnaire d’erreurs fatales, puis inclusion du bootstrap applicatif.
2. **Maintenance** : avant le routage, une vérification optionnelle interroge le stockage (via `MaintenanceRepository`) ; si la table existe, un garde (`MaintenanceGuard`) peut bloquer les requêtes hors liste blanche (ex. webhooks, health check).
3. **Application** : instanciation de `Application`, chargement des routes depuis `routes/web.php`, puis `run()` qui enchaîne les **middlewares globaux** et le **dispatch** du routeur.
4. **Réponse** : objet `Response` envoyé au client (`send()`).

## Routage

- Les routes sont définies dans **`routes/web.php`** sous forme de closures retournant un configurateur sur `Router` (`get`, `post`, etc.).
- Chaque route associe un **chemin HTTP**, un **contrôleur** (classe + méthode) et éventuellement une **pile de middlewares** (auth, invité, admin système, admin organisation, etc.).
- Les URLs publiques des communautés suivent le schéma **`/c/{slug}`** pour le multi-tenant par identifiant d’URL.

## Couches logicielles

| Couche | Rôle |
|--------|------|
| **Contrôleurs** (`app/Controllers/`) | Réception de la requête, orchestration, renvoi de vues ou JSON. |
| **Services** (`app/Services/`) | Logique métier (RBAC, courriel, formations, etc.). |
| **Dépôts** (`app/Repositories/`) | Accès au stockage relationnel (requêtes paramétrées). |
| **Vues** (`views/`) | Templates PHP pour le HTML. |
| **Middlewares** (`app/Middleware/`) | Transversal : session, droits, limitation de débit, en-têtes de sécurité, API tactiques. |

## Bootstrap et configuration

- **`bootstrap/env.php`** charge les variables d’environnement (fichier `.env`).
- **`bootstrap/app.php`** charge l’autoloader, fusionne les fichiers de **`app/Config/`** (app, database, auth, maintenance, etc.) dans `$GLOBALS['__app_config']` pour le helper `config()`, et enregistre le gestionnaire d’exceptions.
- **`bootstrap/autoload.php`** enregistre le chargeur PSR-4 pour le namespace `App\`.

## Multi-tenant (communautés)

- Une **communauté** (tenant) est identifiée notamment par un **slug** dans l’URL et des données en base rattachées aux utilisateurs et aux ressources.
- Les contrôleurs et services filtrent les opérations selon le **contexte tenant** courant (souvent dérivé de la session et du slug).

## Middlewares globaux (ordre d’exécution)

Dans `Application::run()`, une chaîne enveloppe le dispatch avec (dans l’ordre inverse d’empilement) :

- `ComspecTacticalApiMiddleware` — règles spécifiques aux routes API tactiques / clé.
- `SecurityHeadersMiddleware` — en-têtes HTTP de sécurité.
- `RateLimitMiddleware` — limitation de débit.

Les routes peuvent ajouter d’autres middlewares (authentification, rôles).

## API REST et JSON

- Des contrôleurs sous `app/Controllers/Api/` exposent des endpoints JSON (forum, formations, santé, webhooks, etc.).
- Les réponses suivent les conventions du projet (codes HTTP, corps JSON).

## Fichiers statiques

- Les assets (CSS, JS) sont servis depuis **`public/assets/`** ; le build front (ex. Tailwind) peut alimenter ces fichiers.

## Voir aussi

- [Structure du dépôt](structure-du-depot.md)
- [Sécurité et permissions](securite-et-permissions.md)
