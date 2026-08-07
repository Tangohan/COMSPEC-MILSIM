# Bug — Atelier SSE `/atak/sse/dev` : Database::query() inexistant

## Contexte

Page atelier modèles Arma SSE en production (corrélation `710a4212ab9946df`).

## Symptôme

Erreur fatale : `Call to undefined method App\Core\Database::query()` sur `GET /atak/sse/dev`.

## Cause

`SseArmaModelRepository` appelait `$this->db->query()`, méthode absente de `App\Core\Database`. L’API réelle expose `fetchAll`, `fetchOne`, `insert`, `execute`.

## Correctif

Remplacer les appels par l’API Database du projet.

## Fichiers touchés

- `app/Repositories/SseArmaModelRepository.php`

## Vérification

- Ouvrir `/atak/sse/dev` après déploiement : hub sans erreur 500.
- Lister / créer un modèle.

## Statut

corrigé — mergé dans `main` (PR #172), à redéployer en production
