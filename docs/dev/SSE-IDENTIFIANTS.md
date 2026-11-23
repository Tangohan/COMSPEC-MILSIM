# SSE : inventaire des identifiants et identifiants des données

## Avant (numéros existants, par entité)

| Entité (table) | Identifiant existant | Où il est généré |
|---|---|---|
| Dossier (`sse_cases`) | `SSE-<année>-<NNNN>` (`reference_code`) | `SseCaseRepository::nextReference` (compte des lignes de l'année) |
| Dossier d'intérêt (`sse_interest_cases`) | `DI-<année>-<id sur 6>` | `SseInterestCaseRepository::create` |
| Fiche de renseignement (`sse_field_notes`) | `FR-<année>-<NNNNNN>` | `SseFieldNoteRepository::nextReferenceCode` |
| Document (`sse_documents`) | `reference_code` | `SseDocumentRepository::nextReference` |
| Site (`sse_sites`) | `SITE-<année>-<NNNN>` | `SseSiteRepository::nextReference` |
| Investigation (`sse_meshes`) | `reference_code` | `SseMeshRepository::nextReference` |
| Avis de recherche (`sse_wanted_notices`) | `ref`, `ref_year`, `ref_seq` (format réglable par communauté) | `SseWantedNoticeRepository::create` |
| Signalement (`sse_alert_reports`) | `ref`, `ref_year`, `ref_seq` | `SseAlertRepository::createReport` |
| Compte rendu (`atak_tactical_reports`) | `report_number` | `AtakTacticalReportRepository::generateReportNumber` |
| Personne (`sse_persons`) | `IDN-<id sur 5>` : dérivé de la clé, rien en base | affichage seulement |
| Identité du registre (`sse_identities`) | `IDT-<id sur 6>` : dérivé de la clé | `SseIdentityCatalog::identityRef` |
| Profil d'IA (`sse_ai_profiles`) | clé numérique ; pointe vers une identité (`identity_id`) | `SseAiProfileRepository` |

Ces numéros restent inchangés. Ils sont par type et par année, sans nom de code ni lien avec l'IA.

## Ajouté (étape 5020 `sse_data_identifiers`)

Table `sse_data_identifiers` : une ligne par donnée (type + clé), pour **personnes, identités du registre, fiches de renseignement, comptes rendus et documents**. Choix : ce sont les données saisies ou reçues en continu, celles qu'une IA de jeu peut incarner ou citer. Les dossiers, avis et sites gardent leur numéro de dossier ; ajouter un type = une entrée de `SseIdentifierService::TYPES`.

| Identifiant | Format | Unicité | Modifiable |
|---|---|---|---|
| Numéro d'identification | `ID-<année>-<séquence ≥ 4 chiffres>` | par communauté, séquence qui ne recule jamais (`sse_identifier_counters`) | non |
| Nom de code | `MOT-NN` (mot neutre de `SseIdentifierGenerator::WORDS`) | par communauté, un nom abandonné reste réservé | oui, responsable, journalisé |
| Nom de fiche | titre de l'auteur, sinon « Fiche personne ID-2026-0042 » | non | oui, responsable, journalisé |
| DSE | `DSE-` + 5 à 8 caractères sans `0 O 1 I` (défaut 6) | par communauté, toutes données confondues | non |
| IA reliée | clé d'un profil `sse_ai_profiles` de la communauté | n/a | oui, responsable, journalisé |

Attribution : à la création, par les dépôts (`SseIdentifierService::onCreated`, jamais bloquant), et au premier affichage d'une fiche. Rattrapage : étape 5020, relançable, n'écrase rien. Unicité garantie par index uniques (concurrence : réessai sur doublon). Journal : `sse_identifier_history` et `sse_audit_log`.
Contrat avec le mod : `docs/technical/ICD-SSE-DSE.md`.
