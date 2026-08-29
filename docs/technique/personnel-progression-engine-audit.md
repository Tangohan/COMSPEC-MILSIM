# Audit — Moteur de gestion des personnels / progression / indicatifs

Date : 2026-08-29  
Objectif : réutiliser l’existant avant de créer de nouvelles tables.

## Verdict

La plateforme dispose déjà d’un **dossier RH riche** (profil, grades référentiel, ORBAT, LMS → qualifications, ancienneté configurable, elevation, matricule séquentiel).  
Il manquait surtout :

1. un **générateur d’indicatifs transactionnel** (callsign = texte libre, double stockage `users` / `personnel_profiles`) ;
2. un **moteur de progression** (parcours, conditions, validations, holds, audit métier) — le framework compétences LMS existe mais n’est pas un moteur de carrière RH ;
3. des **agrégats missions / présences** prêts à brancher sur des conditions.

## Réutiliser (sources de vérité)

| Domaine | SoT | Branchement conditions |
|--------|-----|------------------------|
| Identité / grade | `users` + `users.grade_id` → référentiel | MIN grade / ladder `sort_order` |
| Callsign plateforme | `users.callsign` (à synchroniser avec dossier) | Attribution séquence |
| Matricule | `tenant_matricule_config.next_number` + `MatriculeService` | **Pattern à copier** pour indicatifs |
| Unités / ORBAT | `units`, `personnel_assignments`, `user_units` | Changement d’unité → politique indicatif |
| Formations | `training_enrollments` (`completed`) | `REQUIRED_TRAINING` |
| Qualifications | `personnel_qualifications` (+ lien LMS) | `REQUIRED_QUALIFICATION` |
| Ancienneté | `seniority_periods` / `SeniorityEngine` | `MIN_DAYS_IN_ORGANIZATION` / stage |
| Heures ops | `user_arma_playtime` | `MIN_OPERATION_HOURS` |
| Présences | `community_event_rsvps.checked_in_at` | `MIN_ATTENDANCE` (compteur à exposer) |
| Promotions manuelles | `elevation_requests` | Workflow humain existant |
| Sanctions | `moderation_actions` (pas `member_sanctions`) | `NO_ACTIVE_SANCTION` |
| Cron | `CronJobInterface` + `CronRunner` | Plug `personnel_progression_evaluate` |

## Étendre

- Unifier callsign `users` ↔ `personnel_profiles` à chaque écriture (fait dans `CallsignSequenceService`).
- Compteur check-in RSVP par utilisateur (aujourd’hui listes brutes / KPI tenant).
- Compteur missions accomplies (mission plans / theatre) — **à créer**.
- `operator_status` : passer d’un VARCHAR libre à un catalogue.
- Currency / pratique récente : `last_report_at` playtime + expiry quals ≠ currency ops.

## Créé (lot 1)

Tables (`bootstrap/personnel_progression_engine_migration.php`) :

- `organization_callsign_sequences` (+ reserved ranges, forbidden)
- `personnel_callsign_history`
- `personnel_progression_tracks` / `stages` / `transitions`
- `personnel_progression_condition_groups` / `conditions`
- `personnel_progression_memberships` / `requests` / `holds`
- `personnel_career_events`

Services :

- `CallsignSequenceService` — NUMERIC / PREFIX_NUMERIC / CUSTOM_PATTERN / MANUAL, FOR UPDATE, plages réservées, historique
- `PersonnelProgressionEvaluator` + cron idempotent (no-op tant qu’aucun parcours publié n’est évaluable)

BO :

- Hub `/back-office/organisation/progression`
- Règles d’indicatifs `/back-office/organisation/indicatifs`
- Carte dans le hub effectifs

Permissions :

- `personnel.progression.*`, `personnel.callsign.manage`, `personnel.qualification.grant`

## Risques identifiés

1. **Double callsign** historique (dossier vs compte) — le service d’allocation synchronise les deux.
2. **Pas d’UNIQUE DB** sur `(tenant_id, callsign)` — contrôle applicatif + retries.
3. **Colonel ≠ OF-4** dans les seeds (OF-5 correct) ; bug bandeau = override d’affichage, pas le référentiel.
4. **Deux mondes progression** : LMS `user_progress` vs carrière RH — le moteur RH doit consommer le LMS comme *input*, pas le remplacer.
5. Ne jamais inventer d’équivalence OTAN depuis `sort_order`.

## Créé (lot 2) — quatre axes + currency

Invariant métier : **ne jamais fusionner** grade/niveau, fonction/poste, qualification et capacité opérationnelle.

Tables (`bootstrap/personnel_capability_axes_migration.php`) :

- `personnel_qualification_definitions` (+ `currency_days` ≠ `validity_days`)
- `personnel_qualification_packs` / `pack_items`
- Colonnes currency sur `personnel_qualifications` : `last_practiced_at`, `currency_status`, `currency_expires_at`, `definition_id`
- `personnel_qualification_practice_log`
- `personnel_mentorships`, `personnel_career_objectives`
- `personnel_progression_waivers`, `personnel_qualification_equivalences`
- `personnel_progression_boards` / `board_votes`
- `personnel_temporary_assignments` (ACTING…, `does_not_change_grade`)
- `orbat_billets` / `orbat_billet_holders` (effectif théorique / réel)
- `personnel_operational_capability`, `unit_operational_capability`
- `personnel_evidence_files`

Services :

- `PersonnelCapabilityAxes` — snapshot structuré + invariants explicites
- `QualificationCurrencyService` — VALID admin ≠ CURRENT (pratique ≤ currency_days)
- `OperationalCapabilityService` — readiness / deployable sans toucher au grade
- Cron `personnel_capability_refresh` — batch par membre, erreur isolée

Exemple crédible : grade Opérateur confirmé + ACTING Team Leader + Medic VALID jusqu’en 2027 mais NON_CURRENT → readiness 82 %, **NON DEPLOYABLE**.

## Lots suivants (plan)

3. Éditeur de parcours + conditions ALL/ANY + simulation / impact preview  
4. Demandes / workflow validation multi-niveaux + boards + notifications SLA  
5. Branchement conditions sur données réelles (missions, training, seniority, playtime)  
6. Page membre PROGRESSION + objectifs + carnet + evidence  
7. Seeds COMSPEC (A-10…) + import CSV + API ATAK readiness  

## Grades OTAN (préparation audit dédié)

Seeds FR : LCL=OF-4, COL=OF-5. Aucune dérivation `rank_level → OF-n`.  
Un correctif référentiel séparé peut suivre si des données tenant legacy sont incohérentes.
