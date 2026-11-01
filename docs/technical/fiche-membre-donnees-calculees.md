# Fiche membre Effectifs : données calculées

La fiche membre (`/back-office/ressources/effectifs/membres/{id}`) affiche six éléments qui ne sont pas des champs du dossier. Ils sont assemblés par `App\Services\Effectifs\MemberFileInsightsService`, toujours limités à la communauté courante (`tenant_id` de la session). Chaque bloc est calculé séparément : une donnée indisponible n’empêche pas l’affichage du reste.

| Élément | Source | Calcul |
|---|---|---|
| Présence 90 j et carré des 26 sessions | existant : `community_events` + `community_event_rsvps` (pointage) | `MemberAttendanceSummary` |
| Sanctions | existant : module « Restrictions membres » (`moderation_actions`, périmètre communauté) | `MemberSanctions` |
| Rang « membre N / M » | existant : membres actifs + dates d’engagement | `MemberSeniorityRank` |
| Composition de l’équipe | existant : postes ORBAT `orbat_billets` (effectif prévu `authorized_slots`) + titulaires + affectations + emplois | `TeamComposition` |
| Frise des grades | **nouveau** : `member_grade_changes` + reconstitution | `MemberGradeTimeline` |
| Éligibilité à une élévation | **nouveau** : réglage `tenants.settings.elevation_eligibility` | `ElevationEligibility` |

## Règles de calcul

- **Session** : événement passé, non annulé, de la communauté, postérieur à l’arrivée du membre (la plus tardive entre la création du compte et la date d’engagement). Présent = pointage enregistré ; absent excusé = réponse « absent » (motif autre que « absence non justifiée ») ; absent sans prévenir = inscrit sans pointage ou absence non justifiée ; sans réponse sinon. Taux 90 j = sessions pointées / sessions des 90 derniers jours, comparé aux 90 jours précédents.
- **Rang** : date d’engagement réelle = la plus ancienne entre l’arrivée dans la communauté et l’arrivée avant le site (celle de l’« ancienneté réelle »). Classement des membres actifs du plus ancien au plus récent ; à date égale, le plus petit numéro de compte passe devant. Sans date : compté dans M, pas de rang affiché.
- **Équipe** : unité de l’affectation principale. Chaque poste a un effectif prévu ; une place est pourvue par un titulaire / intérimaire désigné, sinon par un membre de l’unité qui exerce l’emploi rattaché au poste. Une personne n’occupe qu’une place ; les postes gelés ne comptent pas. Saisie : fiche unité, bloc « Postes (TO&E) » (`POST /back-office/organisation/unites/{id}/postes`, droit `organization.orbat.manage` ou `admin.organization`).
- **Grades** : chaque changement réel de `grade_id` passant par `UserRepository::update()` est noté (avant / après, libellés copiés, auteur, origine). L’approbation d’une élévation marque l’origine « élévation validée » (`MemberGradeChangeRepository::withSource`). L’existant est reconstitué à l’affichage depuis le journal (`audit_logs`, modifications « grade » de la fiche) et les demandes d’élévation acceptées avec un grade proposé, uniquement avant le premier changement enregistré. Le grade précédent n’est déduit que d’une trace antérieure : sinon seule la date est affichée.
- **Éligibilité** : critères facultatifs (ancienneté dans le grade en mois, présences sur 90 jours, taux de présence, qualifications valides). Éligible si tous les critères utilisés sont remplis ; « à vérifier » si une donnée manque (date d’obtention du grade inconnue). Indicateur informatif : il ne change aucun grade. Réglage : `/back-office/ressources/effectifs/elevations/criteres` (droit de gestion des grades). Sans critère défini, la fiche n’affiche pas d’éligibilité (communautés existantes inchangées).

## Droits

- Sanctions : consultation pour qui gère le statut des comptes ou les restrictions de membres ; saisie d’un avertissement et levée réservées à `admin.members.moderate` (même droit que l’écran « Restrictions membres »). Les mesures « site » (plateforme) ne sont jamais affichées ni levées depuis la fiche.
- Critères d’élévation : `EffectifsLmsAccess::canSetElevationCriteria()` (gestion des grades). Les qualifications retenues sont filtrées sur le référentiel de la communauté.

## Création de la table `member_grade_changes`

La file active des migrations est vide depuis le 29 octobre 2026 et les tests `MigrationRegistryTest` / `MigrationWebQueueTest` l’exigent (voir `migrations-active-queue.md`). Comme la page publique du joueur (`UserPublicCvRepository`), la table est créée à la demande : `MemberGradeChangeRepository::ensureTable()` exécute uniquement `migrations/2026_11_01_002_member_grade_changes.sql` (`CREATE TABLE IF NOT EXISTS`, idempotent) au premier changement de grade. Tant qu’elle n’existe pas, la frise s’appuie sur la seule reconstitution. Une étape explicite pourra être ajoutée au registre actif quand la file sera rouverte.
