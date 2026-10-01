# Architecture — Organisation, Duty Position et Mon Service

**Date :** 2026-10-01  
**Statut :** fondation (lot 1)  
**Contexte :** Athena ne doit plus se penser « Admin / Membres », mais comme une organisation MILSIM où chaque membre a un **poste**, un **périmètre** et des **responsabilités concrètes**.

## Invariants (déjà partiellement en place)

Ne jamais fusionner :

| Axe | Objet Athena | Exemple |
| --- | --- | --- |
| Grade | `grades` / `tenant_ranks` | SGT |
| Poste organique | `orbat_billets` + `orbat_billet_holders` | Opérateur N-10 |
| Duty mission | `mission_duty_assignments` (nouveau) | Chef de groupe / N-10 Actual pendant OP SILENT WATCH |
| Qualification | `personnel_qualifications` | MEDIC, JTAC — **éligibilité**, pas permission |
| Rôle technique Athena | 4 rôles max (ci-dessous) | MEMBER configure rarement des permissions |

Réf. existante : `OrgDomainModel`, `docs/technique/personnel-progression-engine-audit.md`.

## Rôles techniques Athena (plateforme / tenant)

| Rôle | Fonction |
| --- | --- |
| `OWNER` | Propriétaire du tenant (`community_owner`) |
| `ADMIN` | Configuration technique (`tenant_admin`) |
| `MANAGER` | Organisation / missions (`hr`, presets commandement) |
| `MEMBER` | Utilisateur normal (`member` / Opérateur) |

Les **postes** (billets) et **duty** portent le travail métier. Les admin ne cochent plus 40 permissions par rôle MILSIM.

## Modèle cible

```
Tenant
 ├ Organisation (units + billets + chaîne)
 ├ Personnel (grade + quals + poste organique + duty mission)
 ├ Permissions (rôle technique + capacités du poste + délégations)
 └ Workflow (work_tasks, rapports, demandes, ordres)
```

## Lot 1 livré

1. **Capability templates** de postes (`PositionCapabilityCatalog`) : S1–S6, commandement, chef de groupe, medic, JTAC, opérateur — sans matrice de permissions individuelles.
2. **Colonne** `orbat_billets.capability_template` pour lier un billet à un template.
3. **`work_tasks`** : moteur universel de tâches avec `assigned_user_id` **ou** `assigned_billet_id` / `assigned_position_slug` (poste de permanence).
4. **`mission_duty_assignments`** : organisation opérationnelle distincte du poste organique.
5. **`duty_roster_slots`** : créneaux de permanence (S2/S3/S4/S6 Duty).
6. **Mon Service** : enrichissement de `/aujourdhui` (contexte grade / unité / poste / duty + tâches du poste).
7. **Template org** `MilsimOrgTemplateService` : État-major (CMD + S1–S6) + groupe de combat (chef, adjoint, chef d’équipe, medic, opérateurs).

## Lots suivants (non livrés ici)

- UI drag & drop d’affectation ORBAT
- Cellules métier S2/S3/S4 (SSE, SITREP, RESUPPLY) branchées sur `work_tasks`
- Escalade CONTACTREP / ordres (CREATED → … → COMPLETED)
- Délégations temporaires
- AAR contributions + dossier d’activité (sans XP arcade)
- Duty roster planner UI

## Mapping héritage

| Ancien / ambigu | Nouveau sens |
| --- | --- |
| `PersonnelDutyPositionService` (`status_in_training` / `status_active_duty`) | **Statut d’intégration**, pas un poste MILSIM |
| `personnel_job_roles` | Fonction / MOS — éligibilité et libellé |
| `personnel_temporary_assignments` | Intérim / détachement RH |
| `mission_duty_assignments` | Affectation **mission** (indicatif, expire fin d’OP) |
| Kits `access_kit_*` | Habilitations techniques transverses, pas postes S2/S3 |
