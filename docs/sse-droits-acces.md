# Droits d'accès du portail SSE

Onglet « Droits d'accès » du bureau SSE (`/atak/sse/droits`) : chaque communauté règle, sans code, qui peut voir, lire, écrire, verser, supprimer, exporter et gérer, section par section. Une règle ne fait que restreindre ou étendre les droits d'origine ; sans règle, le portail se comporte exactement comme avant.

## Modèle

Deux tables (étape `sse_access_rules`, `migrations/2026_11_15_100_sse_access_rules.sql`, retour arrière `migrations/rollback/sse_access_rules_down.sql`) :

- `sse_access_rules` : communauté (`tenant_id`), `section`, `level`, `subject_type` (`role`, `unit`, `unit_tree`, `user`), `subject_id`, `effect` (`allow`, `deny`), `starts_at` et `ends_at` facultatifs, `reason`, `source` (modèle appliqué), `created_by`, `created_at`, `revoked_at`. Une règle retirée reste en base.
- `sse_access_rule_history` : une ligne par création, modèle posé ou retrait (qui, quand, quoi, motif), avec le libellé du bénéficiaire au moment du changement.

Chaque changement est aussi écrit dans le journal d'audit SSE (`sse_audit_log`, objet `access_rule`). Les écrans et le service tolèrent l'absence des tables : sans elles, rien n'est restreint ni étendu.

## Sections

| Clé | Section | Pages (après `atak/sse/`) |
| --- | --- | --- |
| `fichiers` | Fichiers et sujets | `fichiers`, `identites`, `personnes`, `avis` (avis de recherche et détections) |
| `photos` | Photothèque | `fichiers/photos`, versements depuis la photothèque du site |
| `papiers` | Papiers et couvertures | `papiers` |
| `extractions` | Extractions de téléphones | `fichiers/telephones`, `exploitation-numerique` |
| `dossiers` | Dossiers d'enquête | `dossiers`, `interet`, `toiles`, `sites`, `objets`, `documents`, `bibliotheque`, `presentation`, `rapports`, `transmissions`, `collecte`, `validation` |
| `analyse` | Analyse | `workspace`, `croisements`, `rapprochements`, `anomalies`, `chronologie`, `moteur` |
| `lore` | Lore et téléphones du jeu | `fichiers/lore`, `maitre-jeu` |
| `journal` | Journal | `fichiers/journal` |
| `terrain` | Fiches de renseignement | `fiches` |
| `outils` | Modèles, campagnes et éditeur | `campagnes`, `dev`, `editeur` |

Les pages hors section (accueil, recherche, guide, codes d'accès, droits d'accès) ne dépendent d'aucune règle.

## Niveaux

`exists` (voir l'existence), `read` (lire), `write` (écrire), `add` (verser), `delete` (supprimer), `export` (exporter, imprimer), `manage` (gérer : réglages, tri, rattachements de la section).

Un refus sur `exists` emporte tous les niveaux ; un refus sur `read` emporte `write`, `add`, `delete` et `export` (on n'écrit pas ce qu'on ne lit pas) mais laisse `exists`. Un accord sur un niveau (sauf `manage`) donne aussi `exists`. Les cases sans objet (par exemple écrire dans le journal) n'existent pas : aucune règle ne s'y pose.

## Droits d'origine (garde actuelle de chaque case)

Ils sont décrits une seule fois, dans `App\Support\SseAccessCatalog` (profils), et lus par `SseAccessPolicy`. Les contrôleurs ne les répètent plus.

| Profil | Droit |
| --- | --- |
| `entrant` | toute personne entrée au bureau (porte d'entrée), invités compris |
| `account` | tout compte identifié entré au bureau (jamais une session invitée) |
| `manage` | `atak.sse.case.manage`, `atak.sse.grant` ou `admin.access` |
| `files_manage` | `manage` ou `atak.sse.files.admin` |
| `files_admin` | `atak.sse.files.admin` ou `admin.access` |
| `grant` | `atak.sse.grant` ou `admin.access` |
| `export` | `atak.sse.export` ou `admin.access` |
| `lore` | `atak.sse.lore.manage` ou `admin.access` |
| `field_write` | `atak.sse.access`, `atak.sse.case.manage` ou `admin.access` |

| Section | exists, read | write | add | delete | export | manage |
| --- | --- | --- | --- | --- | --- | --- |
| `fichiers` | entrant | files_manage | account | files_manage | entrant | files_admin |
| `photos` | entrant | account | account | files_manage | entrant | files_admin |
| `papiers` | entrant | manage | manage | manage | manage | manage |
| `extractions` | entrant | manage | manage | manage | account | files_admin |
| `dossiers` | entrant | manage | manage | manage | export | grant |
| `analyse` | entrant | manage | manage | manage | export | grant |
| `lore` | lore | lore | lore | lore | lore | lore |
| `journal` | entrant | sans objet | sans objet | sans objet | sans objet | sans objet |
| `terrain` | entrant | field_write | field_write | manage | export | manage |
| `outils` | entrant | manage | manage | manage | entrant | manage |

Quelques actions gardent leur propre garde d'origine, passée à la politique comme « legacy » : mentions et signalements de papiers (`manage`), rattachement d'une extraction à une fiche (`files_manage`), API `/api/sse/v1` en écriture (`manage` ou `atak.access`), pages du back-office (administrateur de la communauté compris).

## Ordre de résolution

`SseAccessPolicy::allows(?userId, section, level, context)` :

1. section ou niveau inconnu, ou case sans objet : refus ;
2. session invitée (code d'accès), appel sans compte, communauté sans règle ou administrateur de la plateforme : la garde d'origine décide seule ;
3. un refus explicite actif (membre, niveau d'accès, unité, unité et sous-unités) : refus. Il l'emporte sur tout le reste ;
4. la garde d'origine ouvre : accès ;
5. un accord actif, et la porte d'entrée passée (droit d'entrer et engagement de confidentialité de la session, ou le droit d'entrer seul pour les API et les écrans hors portail) : accès ;
6. sinon refus.

Les dates de début et de fin sont jugées à l'heure de la requête. Un accord n'ouvre jamais le portail à quelqu'un qui n'a pas passé la porte.

## Qui modifie les droits

Seul le droit de gestion SSE existant (`atak.sse.grant` ou `admin.access`, profil `grant`) ouvre l'onglet et écrit des règles. Aucune règle ne l'étend ni ne le retire : les gestionnaires ne peuvent pas être verrouillés dehors. Garde-fous, tous dans `SseAccessRuleService` :

- pas d'escalade : on n'accorde que les niveaux qu'on possède soi-même, et on ne lève un refus que sur un niveau qu'on possède ;
- le niveau d'accès, l'unité ou le membre appartient à la communauté ; un membre masqué ou anonymisé par l'annuaire n'est pas nommable ;
- motif d'au moins 5 caractères pour une dérogation individuelle, un refus ou le niveau `manage` ;
- un refus ne peut pas retirer la lecture d'une section à tous les gestionnaires SSE ;
- une règle identique n'est jamais doublée ; 2000 règles actives au plus par communauté ; 60 changements par heure et par personne ;
- CSRF sur toute écriture, retours limités à l'onglet, aucun identifiant d'une autre communauté accepté.

L'onglet ne montre ni identité SSE, ni couverture, ni contenu : il parle de droits. Les personnes citées suivent les règles de visibilité de l'annuaire : un membre masqué n'existe pas, un membre anonymisé n'est que compté.

## Exemples

- Les analystes lisent mais n'écrivent pas : modèle « Analystes : lecture seulement » posé sur leur niveau d'accès, avec « refuser aussi les autres niveaux » pour fermer l'écriture à ceux qui l'avaient déjà.
- Un renfort pour une enquête : dérogation à un membre, `dossiers` en `read` et `write`, jusqu'à la fin de la mission, motif « Renfort enquête Alpha ».
- Fermer la photothèque à une unité : refus `photos` / `read` sur l'unité et ses sous-unités ; les photos versées au dossier SSE disparaissent aussi de la photothèque du site pour elle.

## Gardes branchées

Lecture de section (intergiciel `SseSectionAccessMiddleware`, après la porte) ; fichiers, photothèque, extractions et lore (`SseFilesContext`, `SseFileRecordController`, `SseLoreController`, `SseFileController`) ; dossiers, export, recherche globale (`SsePortalController`) ; papiers (`SseIdentityRegistryController`, `SseIdWorkshopController`, `MemberIdentityPapersController`) ; exploitation numérique ; fiches de renseignement ; modèles et campagnes ; espace d'exploitation ; photothèque du site (`PhotoLibraryController` : lire, verser, retirer) ; JNET (renseignement et cibles) ; back-office (fichiers SSE, remontées) ; API `/api/sse/v1` (écriture ; lecture : refus seulement) ; navigation du bureau.

## Gardes laissées, et pourquoi

- `canGrant()` (codes d'accès, habilitations, déverrouillage de dossier sans code) et la porte d'entrée (`SseAccessCodeService`) : c'est le droit de gestion lui-même, que les règles ne modifient pas.
- Niveaux de diffusion (`SseClearanceService`) : plafond de lecture par classification, indépendant des sections.
- API du jeu sans compte (clé de la communauté : `/api/sse/persons`, `notes`, `campaigns`, `identity`, `sites`, `/api/game/v1/*`) : l'appelant n'a pas de compte, les règles par membre ne s'y appliquent pas ; les réponses ne changent pas.
- Liens de menu (menu Opérateur, recherche du back-office) : affichage seulement, la page cible applique la politique.

## Tests

`tests/Unit/SseAccessPolicyTest.php` (résolution, matrice avant/après des gardes d'origine sur des profils types, garde-fous, historique, simulation), `tests/Unit/SseAccessRightsWiringTest.php` (câblage, CSRF, communautés, gardes remplacées, migration, vues, traductions).

## Droits simples : position, qualification, identité

L'onglet s'ouvre sur « Qui peut quoi » (`/atak/sse/droits`) : une ligne par section, trois colonnes (voir, écrire, gérer), des puces par bénéficiaire. La grille à sept niveaux reste à `/atak/sse/droits/grille`. Aucune migration : la colonne `subject_type` (VARCHAR(12)) reçoit trois valeurs de plus.

| Bénéficiaire | `subject_type` | `subject_id` | Qui est couvert |
| --- | --- | --- | --- |
| Position de service | `position` | `roles.id` du rôle d'affichage (position administrative : « En service actif »… et positions créées par la communauté) | tous ceux qui portent la position, dans n'importe quelle unité |
| Fonction (emploi) | `job` | `personnel_job_roles.id` | tous ceux qui ont la fonction (chef de section, adjoint, instructeur…) |
| Qualification | `qualif` | `personnel_qualification_definitions.id` | titulaires VALIDES : statut obtenue, valide ou bientôt échue, et échéance non passée ; expirée, suspendue, révoquée ou en cours : aucun accès |
| Identité | `user` | `users.id` | un membre précis, nommé comme l'annuaire le montre (jamais un membre masqué ou anonymisé) |

Un accès simple pose plusieurs niveaux : voir (existence, lecture), écrire (+ écriture, versement), gérer (tout). Chaque puce apparaît dans sa colonne la plus haute. Les « modèles en situation » (Renseignement, Fiches de renseignement ouvertes, Une position gère les dossiers) demandent un bénéficiaire par étape. Avant tout enregistrement, une phrase donne l'effet (« 14 personnes gagneraient l'accès »).

« Par identité » couvre le membre précis. Limiter un droit à une identité SSE précise du registre (un seul dossier) n'est pas livré : les gardes jugent la section, pas l'enregistrement, il faudrait une portée par fiche dans chaque garde.

Évaluation : les positions se lisent avec les niveaux d'accès déjà chargés ; fonctions et qualifications valides ne sont lues que si une règle de ce type existe, une fois par membre, en une requête pour tous les membres évalués (« qui a accès », aperçu). Rien n'est gardé d'une requête à l'autre ; un changement de position ou de qualification oublie la lecture en cours (`SseAccessPolicy::forgetMember`).

## Bloc « Accès SSE » de la fiche effectif

Fiche d'un membre (`/back-office/ressources/effectifs/membres/{id}`), onglet « Accès », carte « Accès SSE » ; la fenêtre s'ouvre aussi par `?ouvrir=acces-sse`. Réservée au droit de gestion SSE (invisible sinon). Elle montre les droits par section (voir, écrire, gérer) avec leur origine (droit d'origine, position, fonction, qualification, identité, unité, niveau d'accès), « Donner un accès » et « Retirer un accès » (règle d'identité datée avec motif, aperçu en une phrase), « Retirer » en un clic sur une règle d'identité du membre, et l'historique. Tout passe par `SseAccessMemberRights` puis `SseAccessRuleService` : mêmes garde-fous, communauté de la session.
