# Structure des emplois et fonctions

`PersonnelJobRoleStructureService` ajoute des métadonnées descriptives au catalogue existant, sans modifier les emplois, titulaires, affectations, unités ou permissions. Le rattachement à une unité est distinct de `personnel_job_roles.source_unit_id`, qui conserve son sens d’origine générée par l’ORBAT.

Appliquer la migration additive et rejouable `20261029223000_personnel_job_role_structure.sql`. Elle crée uniquement `personnel_job_role_structure`, avec une clé `(tenant_id, job_role_id)`. Aucun catalogue ni titulaire n’est resemé ou supprimé. La table ne comporte aucune cascade de suppression : un rattachement ne doit jamais faire disparaître un emploi, une affectation ou une sous-fonction.

Les champs sont `parent_job_role_id`, `linked_unit_id`, `grade_id`, `specialty_id`, `code` (40 caractères maximum) et `kind` (`employment` ou `function`). Parent et unité doivent appartenir à la communauté. Un grade doit être proposé par `GradeRepository::listForTenant()`, ce qui inclut le système sur mesure sélectionné et les adaptations de la communauté. La spécialité doit exister dans `military_specialties`. La hiérarchie est descriptive : aucune permission n’est héritée du parent et aucun titulaire ne reçoit automatiquement ses sous-fonctions.

Le service expose :

- `schemaReady()` pour masquer ou désactiver les nouveaux champs tant que la migration n’est pas appliquée ;
- `options(tenantId)` pour les catalogues de choix (`roles`, `units`, `grades`, `specialties`, `kinds`) ;
- `listMetadata(tenantId)` pour les métadonnées indexées par emploi ;
- `validate(tenantId, roleId, input)` pour les métadonnées normalisées et les erreurs indexées par champ ; `roleId=0` permet la validation avant création ;
- `save(tenantId, roleId, metadata)` pour la sauvegarde d’un emploi existant. Cette méthode revalide les références et la hiérarchie avant l’écriture. Elle ouvre sa transaction uniquement lorsqu’aucune transaction appelante n’est active et ne valide ni n’annule une transaction appartenant au contrôleur.

Les écritures de hiérarchie sont sérialisées par un verrou sur la ligne du tenant, empêchant deux modifications concurrentes de créer une boucle parent/enfant. Le contrôleur doit traiter les erreurs avant de créer l’emploi puis sauvegarder emploi et structure dans une même transaction lorsque les deux sont créés ensemble.

Les références de la communauté et la hiérarchie sont relues avec des lectures verrouillantes pendant la sauvegarde. Une transaction appelante ayant déjà établi un instantané MySQL `REPEATABLE READ` ne peut donc pas réintroduire un parent supprimé ni créer une boucle en ignorant une modification récemment validée. Les suppressions du catalogue doivent prendre le même verrou de communauté avant de vérifier les sous-emplois et les titulaires.

Tests : `tests/Unit/PersonnelJobRoleStructureServiceTest.php`, avec une base SQLite en mémoire et le catalogue de grades natif injecté. Les cas couvrent les références d’une autre communauté, les grades sur mesure applicables, les cycles, les données invalides, la préservation des affectations/permissions/origines ORBAT, le détachement des métadonnées et les transactions appartenant à l’appelant.

`tests/Contract/PersonnelJobRoleStructureMariaDbTest.php` vérifie en outre le rejeu du vrai fichier SQL et les instantanés concurrents sur MariaDB. Ce test n’accepte que la base jetable `rh_structure_test`, sélectionnée par `RH_STRUCTURE_TEST_DSN` ; il est ignoré sans cette variable. Ne jamais le diriger vers une base utilisée par l’application.

L’écran **Effectifs → Emplois et fonctions** permet de créer un emploi ou une fonction, de choisir son parent et ses références, et de ranger ses catégories en sous-catégories. Les écritures utilisent les permissions RH existantes, le tenant de session et un jeton CSRF. La navigation du catalogue et des catégories ainsi que leurs enregistrements se font par fragments ; une erreur conserve le formulaire saisi. Un rattachement devenu indisponible reste visible jusqu’à son remplacement ou son retrait explicite.

Les emplois de dossier restent indépendants des affectations ORBAT. Le réglage `tenants.settings.personnel_job_roles_assignments.sync_jobs_from_assignments`, désactivé par défaut, permet uniquement d’ajouter des emplois lors des prochaines affectations. Le désactiver ne supprime aucune attribution existante. Les sauvegardes d’emplois, y compris depuis le tableau d’attributions et l’ancien dossier, ne réécrivent plus le poste ORBAT.

Une suppression explicite est refusée si l’emploi est encore utilisé par un dossier, un sous-emploi, un poste ORBAT, une affectation temporaire ou une offre de recrutement. Les catégories doivent être vides. Les contrôles sont effectués dans une transaction avec des lectures verrouillantes. Les tests de suppression et d’indépendance des attributions complètent les tests de structure.

L’onglet Catégories présente désormais une hiérarchie avec un seul éditeur. « Créer une catégorie » ajoute un dossier principal ; « + Sous-catégorie » ouvre un formulaire déjà rattaché au dossier choisi. « Ranger dans » sert à déplacer le dossier. Les descendants ne peuvent pas devenir ses parents. La recherche conserve les ancêtres des résultats et le lien « Voir les emplois » filtre le catalogue, sous-catégories comprises.

La lecture des catégories utilise une requête groupée de comptage des emplois. Elle ne charge plus le catalogue des grades/spécialités, les titulaires, les doublons, les alertes RH ou les calculs de phases. Le nombre de sélecteurs reste constant avec la taille du catalogue, au lieu d’un sélecteur de tous les parents pour chaque catégorie. Les statistiques RH du shell sont omises sur ces écrans de référentiel.

Les fragments sont demandés avec `Accept: text/html`, y compris pour les anciens service workers qui les reconnaissent ainsi comme des lectures dynamiques. Le service worker actuel ignore également explicitement les routes back-office et les en-têtes de fragment ; il ne fabrique donc plus de réponse « 504 offline » pour ces lectures privées. Les erreurs réseau restent affichées sans rejouer les écritures. Le script partagé d’aide a été corrigé et sa recherche est vérifiée dans la recette navigateur.
