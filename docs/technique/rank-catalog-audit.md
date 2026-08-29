# Audit — Système de grades / rangs / codes OTAN

Date : 2026-08-29  
Branche : `cursor/rank-catalog-otan-fa98`  
Objectif : comprendre pourquoi « Colonel » peut s’afficher en OF-4 **avant** toute correction de données.

---

## Verdict

1. **Les seeds FR_CLASSIC stockent déjà Colonel = OF-5** (et LCL = OF-4). Aucune ligne seed ne mappe Colonel → OF-4.
2. **Aucune fonction PHP ne dérive `OF-{n}` / `OR-{n}` depuis `sort_order` / `rank_order` / un « level ».** Le pattern interdit `rank_level = 4 ⇒ OF-4` **n’existe pas** dans le code actuel.
3. Le symptôme « Colonel · OF-4 » vient presque toujours d’un **découplage d’affichage** :
   - titre libre dossier (`personnel_profiles.rank_display` = « Colonel »)
   - + grade communauté assigné = **Lieutenant-colonel** (`label_otan` = OF-4)
   - le bandeau compose `headerTitle` · `headerShortCode` indépendamment (`GradeDisplayService`).

Référence bug déjà documentée : `docs/bugs/2026-08-28-grade-bandeau-of4-of5.md` (cas inverse LCL + override O-5).

---

## 1. Tables existantes

| Table | Rôle | OTAN |
|-------|------|------|
| `grades` (ex-`grades_referentiel`) | Catalogue global multi-système | `label_otan` (stocké, jamais calculé) |
| `grades_legacy` | Ancienne table tenant | `nato_code` éventuel |
| `grade_systems` | FR_CLASSIC / US_CLASSIC | `country_code` |
| `grade_categories` | OFFICIER, SOUS_OFFICIER, MDR, CIVIL, HORS_GRADE | — |
| `tenant_grade_overrides` | Libellés / ordre / enable par org | **pas d’override OTAN** |
| `users.grade_id` | Grade assigné | FK → `grades.id` |
| `personnel_profiles.rank_display` / `rank_display_override` | Titre / code court bandeau | Peut **masquer** l’OTAN réel |

Colonnes clés sur `grades` : `code`, `label_short`, `label_long`, **`label_otan`**, **`sort_order`**, `is_commissioned`, `grade_system_id`, `grade_category_id`.

---

## 2. Calcul / dérivation OF-OR

Recherche exhaustive (`OF-`, concaténation, `rank_level`, `hierarchy_order → nato`) : **aucun calcul automatique**.

Consommateurs : `GradeDisplayService::getOtan`, header, sidebar, forum, courrier — lecture seule de `label_otan` / `nato_code`.

---

## 3. Seeds FR (état attendu vs stocké)

| Grade | Code | `label_otan` seed | Attendu | Statut seed |
|-------|------|-------------------|---------|-------------|
| Sous-lieutenant | SL | OF-1 | OF-1 | VALID |
| Lieutenant | LT | OF-1 | OF-1 | VALID |
| Capitaine | CNE | OF-2 | OF-2 | VALID |
| Commandant | CDT | OF-3 | OF-3 | VALID |
| Lieutenant-colonel | LCL | OF-4 | OF-4 | VALID |
| **Colonel** | **COL** | **OF-5** | **OF-5** | **VALID** |
| Général de brigade | GBR | OF-6 | OF-6 | VALID |
| Général de division | GDV | OF-7 | OF-7 | VALID |
| Général de corps d’armée | GCA | OF-8 | OF-8 | VALID |
| Général d’armée | GAR | OF-9 | OF-9 | VALID |

NCO/MDR FR : Major OR-9 … Soldat OR-1 — cohérent (Major FR ≠ OF-3).

**Limite :** le seed initial ne s’exécute que si `grade_categories` est vide. Une édition manuelle de `label_otan` en base **n’est jamais réparée** au re-run.

US_CLASSIC utilise O-/E- (domestique), pas OF- — intentionnel.

---

## 4. Cause racine « Colonel · OF-4 »

| Hypothèse | Verdict |
|-----------|---------|
| Seed COL = OF-4 | **Faux** |
| Dérivation sort_order → OF-n | **Faux** |
| Titre dossier « Colonel » + grade LCL (OF-4) | **Oui — cause principale** |
| Admin a modifié `label_otan` COL → OF-4 en live | Possible (données divergentes) |
| Confusion US O-5 (LTC) vs FR OF-4 (LCL) | Fréquente côté utilisateur |

Requête de confirmation :

```sql
SELECT u.id, g.code, g.label_long, g.label_otan,
       pp.rank_display, pp.rank_display_override
FROM users u
LEFT JOIN grades g ON g.id = u.grade_id
LEFT JOIN personnel_profiles pp ON pp.user_id = u.id
WHERE pp.rank_display LIKE '%olonel%' OR g.code IN ('COL','LCL');
```

---

## 5. Lacunes vs architecture cible

- Pas de `rank_catalog` multi-pays / multi-branches (ARMY vs GENDARMERIE) dédié.
- Pas de `tenant_ranks` avec `custom_rank` / OTAN org explicite (seulement overrides de libellés).
- Pas d’historique `personnel_rank_history`.
- Pas de validateur OTAN format + matrice attendue FR.
- Pas de badges VERIFIED / INVALID / UNVERIFIED en BO.
- Pas de `rank_migration_audit`.
- Progression / ORBAT peuvent encore raisonner trop près du code OTAN si mal utilisés.
- `GradeDisplayService` autorise un override libre qui **dissocie** titre et OTAN communauté sans alerte.

---

## 6. Plan de correction (contrôlé)

1. Créer `rank_catalog` + seeds explicites (jamais `hierarchy_order → nato_code`).
2. Couche `tenant_ranks` (personnalisation sans mute silencieuse de l’OTAN canonique).
3. `personnel_rank_history` + `rank_migration_audit`.
4. `RankReferenceValidator` + audit live des `grades` existants.
5. Réparer **uniquement** les mismatches certains (ex. COL stocké OF-4 → OF-5) avec journal.
6. UI BO : badges + correspondance attendue.
7. Tests verrouillant Colonel FR ARMY → OF-5, et `hierarchy_order` indépendant de `nato_code`.
8. Ne pas inventer d’OTAN Gendarmerie incertains → `nato_code = NULL` / UNVERIFIED.

---

## Fichiers clés audités

- `run-migrations.php` (seeds ~1639–1838)
- `migrations/grade_referentiel.sql`
- `app/Repositories/GradeRepository.php`
- `app/Services/GradeDisplayService.php`
- `app/Controllers/Admin/Organization/GradeReferentielController.php`
- `views/partials/athena_caverne_header.php`
- `docs/bugs/2026-08-28-grade-bandeau-of4-of5.md`
