# Chasse aux erreurs 500 « même famille » (schéma, câblage, appels morts)

## Contexte
Après les erreurs 500 du 01/11 (`grades.tenant_id` sur `/invitations/accept`, contrôleur absent du Container), balayage de tout le code :
schéma reconstruit depuis `migrations/*.sql`, `database/steps`, `bootstrap/` et croisé avec les requêtes brutes ;
PHPStan niveau 2 sur `app/` et `scripts/` ; lecture des routes, du Container, des vues incluses ; journal d’erreurs de prod.

## Symptôme / Cause / Correctif
| Page ou usage | Cause | Correctif |
|---|---|---|
| `/atak/passerelle/creer`, `/rejoindre`, `/{id}/annuler` : erreur 500 | `Request::getParsedBody()` n’existe pas | méthode ajoutée (corps POST) |
| Portail candidat : signaler un message de l’équipe : erreur 500 | `EnlistmentRepository::findCandidatePortalMessage()` jamais écrite | méthode ajoutée, limitée au dossier et à la communauté |
| `/documents/gestion/{id}/historique` : erreur 500 depuis avril | variables `$accessSessions`/`$accessEvents` indéfinies (avertissement → exception) | retirées (la vue ne les utilise pas) |
| `/back-office/documents/nomenclature`, `types`, `publier`, `{id}/nouvelle-version`, `compliance` : page blanche | gabarit `layout.back_office` inexistant (`Response::view` rend vide) | `layout.main` |
| Alerte « période d’essai dépassée » | `"$this->pdo"` interpolé dans la chaîne SQL (Error) | concaténation corrigée |
| Score de disponibilité des unités : aucun membre trouvé | `SELECT user_id … FROM users WHERE unit_id` : colonnes inexistantes | même règle d’appartenance que rosters/ORBAT (`personnel_assignments`, sinon `user_units`) |
| Leçon avec un lien interne `/…#ancre` : erreur 500 | motif `preg_match` invalide (`#` délimiteur dans la classe) | `#` échappé |
| Fiche session Arma : liste d’événements toujours vide | `listUpcomingForTenant()` inexistante (erreur avalée) | `upcomingForTenant()` |
| Confiance POI (ATAK) : erreur | `$ageHours`/`$ageDays` indéfinis | variable unique `$ageDays` |
| Nettoyage notifications / zones ATAK expirées | `->rowCount()` sur l’entier renvoyé par `Database::execute()` | entier renvoyé tel quel |
| Migration rôles (MariaDB) | `catch (PDOException)` sans `\` : jamais intercepté | `\PDOException` |
| Cron `scripts/training-expire.php` : ne tournait plus | parenthèse en trop (erreur de syntaxe) | corrigée |

Journal de prod : `blood_type_detected`, `mission_type`, `fputcsv` escape, `has_attachments`, `upsertConfig`, `u.username`,
`OrganizationDiscordShowcaseController`, `TablePdfExportService`, `DoctrinePublicationService` (argument 8), `AdminAtakFleetController`
(version communauté) et `grades.tenant_id` étaient déjà corrigés sur main ; les nouveaux tests de câblage les couvrent désormais.

## Fichiers touchés
`app/Core/Request.php`, `app/Repositories/{EnlistmentRepository,ProbationOversightRepository,AtakAdvancedIntelligenceRepository,AtakNotificationRepository,AtakZoneThreatRepository}.php`,
`app/Controllers/Admin/AdminDocumentsController.php`, `app/Controllers/Admin/Organization/{AdminDoctrineController,RoleplayGameSessionAdminController}.php`,
`app/Services/Personnel/UnitReadinessService.php`, `app/Services/Rbac/RoleDefinitionCatalog.php`, `app/Support/training_canvas.php`,
`scripts/training-expire.php`, `phpstan-baseline.neon` (exceptions devenues inutiles).

## Vérification
- `tests/Unit/SchemaSweepRegressionTest.php` (échoue sans les correctifs).
- Gardes génériques : `RoutedControllerMethodsExistTest` (chaque route → méthode publique, middlewares instanciables),
  `ContainerDefinitionsConsistencyTest` (`Container::get` vers un service déclaré ; nombre et types des arguments des `new`),
  `TypedDependencyMethodCallsExistTest` (méthodes appelées sur les dépendances typées et sur `$request`),
  `ViewTemplatesExistTest` (vues et partials littéraux), `PhpSourcesParseTest` (scripts, étapes, vues).

## Reste identifié (non corrigé)
- `SseAnalyticalEngineService::stagePreSseIntel()` lit `intel_reports.tenant_id/grid/note`, absents de la table (rattachée à une mission, pas à une communauté) : le signal « intel terrain pré-SSE » ne sort jamais (erreur avalée, pas de 500).

## Statut
Corrigé (sauf point ci-dessus).
