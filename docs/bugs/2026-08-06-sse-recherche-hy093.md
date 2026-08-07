# Recherche SSE — HY093 Invalid parameter number

## Contexte

`GET /atak/sse/recherche?q=test` en production → exception PDO `SQLSTATE[HY093]: Invalid parameter number`.

## Symptôme

- Page d’erreur / signalement d’incident
- Stack : `SseCaseRepository::listForTenant` → `Database::fetchAll` → `PDOStatement::execute`

## Cause

PDO (préparation native) n’accepte **pas** de réutiliser le même nom de paramètre plusieurs fois dans une requête.

```sql
reference_code LIKE :search OR title LIKE :search OR summary LIKE :search
```

avec un seul `params['search']` → HY093.

Même motif présent sur toiles, Pré-SSE et lab numérique.

## Correctif

Paramètres distincts (`:search_ref`, `:search_title`, `:search_summary`, etc.) avec la même valeur LIKE.

## Fichiers touchés

- `app/Repositories/SseCaseRepository.php`
- `app/Repositories/SseMeshRepository.php`
- `app/Repositories/SseInterestCaseRepository.php`
- `app/Repositories/SseDigitalLabRepository.php`

## Vérification

- [ ] Déployer PHP
- [ ] `/atak/sse/recherche?q=test` → 200 (résultats ou vide, pas 500)
- [ ] Filtres dossiers / toiles / Pré-SSE / supports numériques avec texte

## Statut

corrigé — déploiement requis
