# Lot 7 : tests de non-régression, constats

Ce document accompagne les tests ajoutés par la branche `test/audit-lot7-non-regression`. Les écarts connus et acceptés sont figés dans les tests, avec leur raison : les tests empêchent les NOUVEAUX écarts. Les écarts ci-dessous sont des FAIT relevés par ces tests sur `origin/main` (aucun code applicatif n'a été modifié).

## Ce qui est couvert

| Plan | Test | Contenu |
|---|---|---|
| 7.1 | `tests/Unit/BackOfficeRouteMapTest.php` | `routes/web.php` est chargé dans un vrai `Router` (sans base ni serveur). Chaque route de `/back-office`, `/backoffice`, `/admin` a `AuthMiddleware` en premier et un middleware de droit, sauf 10 contrôleurs figés qui vérifient le droit eux-mêmes ; toute route `/admin` exige `SystemAdminMiddleware` ; chaque famille d'onglets garde la garde de son ancienne page ; aucune route n'est masquée par une route enregistrée avant elle ; les 240 chemins cités par l'inventaire (document 1) ont une route et une garde. |
| 7.5 | `tests/Unit/BackOfficeRedirectMapTest.php` | Les fonctions de redirection (151 routes) sont exécutées, leur cible est cherchée dans la carte : cible existante, un seul saut, onglet connu, 301 pour `BackOfficeLegacyRedirects`. |
| 7.4 | `tests/Unit/BackOfficeMenuProfilesTest.php` | Le menu latéral est rendu sans base pour 7 profils (gestionnaire, assistance, RH, recruteur, responsable des accès, modérateur, membre). Instantané `tests/fixtures/audit-backoffice/menu-<profil>.txt`. Contrôles : chaque lien a une route, pas d'ancienne adresse, pas d'onglet inconnu, pas de doublon, et le lien s'ouvre pour le profil qui le voit (porte de la route évaluée avec ses droits). |
| 7.9 | `tests/Unit/BackOfficeUiStaticChecksTest.php` | Pas de `confirm()` natif nouveau, pas de `<h1>` de plus dans une vue, pas de surtitre nouveau, tout tableau garde un état vide. L'existant est figé dans `tests/fixtures/audit-backoffice/ui-baseline.json`. |
| 7.2 / 7.3 | `tests/Unit/SecurityAuditCoverageTest.php` | Chaque point S1 à S16 est relié au test qui le couvre (ou à un écart déclaré). S14 et S15, sans test jusque-là, sont couverts. |
| 6 | `tests/Unit/AuditReportQueriesTest.php` | Les requêtes des rapports de cohérence tournent contre le schéma des migrations (chargé en SQLite) et trouvent l'écart attendu ; les rapports n'écrivent rien. |

Pour régénérer un instantané après un changement voulu : `UPDATE_MENU_SNAPSHOTS=1` (menus) ou `UPDATE_UI_BASELINE=1` (interface), puis relire la différence avant de la valider.

## Écarts réels, sécurité d'abord

1. **S6 resté ouvert pour 29 contrôleurs.** `OrganizationAdminMiddleware` laisse passer `site.support` sur tout `/back-office`, lecture et écriture. 29 contrôleurs servis derrière ce seul middleware n'ont AUCUN contrôle de droit interne (67 routes de lecture, 89 d'écriture) : le lot 0 n'a corrigé que `OrganizationSettingsController`. Parmi eux : `TenantInitialSetupController`, `OrganizationCommunityController`, `TenantAlertsController`, `TenantMiniArticlesController`, les référentiels (grades, décorations, équipements, qualifications), l'ORBAT (unités, groupes, équipes), le recrutement (offres, codes d'invitation, questions Discord), l'ancienneté, les passes RH. Liste figée dans `BackOfficeRouteMapTest::KNOWN_ORG_ONLY_CONTROLLERS`. À confirmer à l'exécution : le test lit le code, il ne simule pas un compte d'assistance.
2. **S14 : `POST /api/athena/v2/dev/seed`** reste ouvert à tout administrateur de communauté (le plan 0.14 demande `admin.system`). Hors production seulement : la méthode répond 404 en production et exige le jeton CSRF (désormais figé par un test).
3. **S7 partiel.** `AccessControlMiddleware` n'est attaché à aucune route ; le journal d'audit n'est branché que par `AuthMiddleware` sur les sessions d'intervention. Aucun test ne fige la décision (brancher ou retirer).
4. **Lien mort pour le profil modérateur.** Le menu montre « Sanctions & absences » (`/back-office/moderation`) à qui a `admin.members.moderate`, mais `OrganizationAdminMiddleware` n'a pas d'exception pour ce chemin : un modérateur sans autre droit est renvoyé au portail.
5. **Profil assistance.** Un compte `site.support` passe `OrganizationAdminMiddleware` sur toutes les pages, mais son menu est celui d'un simple membre (21 liens, aucun lien de gestion).

## Autres écarts réels

- **`POST /back-office/media/collections` n'est jamais atteinte** : `POST /back-office/media/{id}` (`updateItem`) est enregistrée avant et capte l'adresse avec l'identifiant « collections ». Le formulaire « Créer une collection » de `views/admin/organization/media_index.php` appelle donc `updateItem`.
- **`GET /admin/users` est déclarée deux fois** (`routes/web.php` 1620 et 2501) : la redirection vers `/back-office/users` n'est jamais atteinte.
- **Quatre redirections en deux sauts** : `/backoffice/users` (puis Effectifs), `/admin/users/create` (puis Effectifs › Nouveau), `/admin/atak/realism/config` (puis l'onglet Réglages réalisme), `/api/training/progress/lesson` (puis le catalogue).
- **136 redirections de page déplacée répondent 302**, pas 301 (règle du plan : 301). Le nombre est figé.
- **Entrée de menu « Dossiers RH »** : `a-traiter?onglet=surveiller`, onglet inconnu (déjà noté dans l'inventaire, PER-F26).
- **Doublon de menu** : « Overwatch » (`/atak`) apparaît deux fois pour un cadre délégué (menu de gestion et « Mon espace »).
- **Routes à barre finale** (`/admin/`, `/back-office/`, `/backoffice/`) : le chemin est normalisé sans barre finale (`Request::normalizePathFromServer`), ces routes ne répondent jamais. Sans effet, à retirer.
- **Interface** : 161 vues du back-office ont leur propre `<h1>` alors que l'en-tête du shell en porte un (risque de double titre ; `shell.php` des Effectifs et `partage/index.php` en ont deux dans le même fichier) ; 179 `confirm()` natifs dans 109 fichiers (vues et scripts du back-office) ; 95 surtitres (`eyebrow`, `kicker`) dans 71 vues ; 1 vue avec un tableau sans état vide (`views/admin/organization/partials/doctrine_sop.php`).

### Suivi : vague de tri (`tests/Unit/VagueTriTest.php`)

- Corrigé : `POST /back-office/media/collections` atteinte (déclarée avant `/back-office/media/{id}`) ; doublon `GET /admin/users` et routes à barre finale retirés ; les quatre chaînes arrivent en un saut ; 135 des 136 redirections passent en 301 (reste `/back-office/renseignement`, entrée de module vers sa page par défaut) ; « Overwatch » n'est plus répété pour un cadre délégué.
- « Dossiers RH » : l'onglet `surveiller` existe bien, c'est une section de la page « À traiter » (`RhActionInboxService::SECTIONS`), pas un onglet de `BackOfficeTabs`. L'entrée ouvre désormais « À traiter », comme sa première page.
- Reste ouvert, relevé au passage : `/back-office/ressources/effectifs/alertes` répond 301 vers `a-traiter?onglet=alertes`, qui répond à son tour 302 vers `a-traiter?onglet=surveiller` (`RhDossierWorkspaceController::alerts`) : deux sauts que la carte ne voit pas, la seconde redirection étant dans un contrôleur.
- Les écarts de sécurité 1 à 5 ci-dessus ne sont pas touchés par cette vague.

## Couverture S1 à S16

| Point | État | Test |
|---|---|---|
| S1 JNET | couvert | `SecurityAuditLot1Test::testJnetKeepsSseIntelligenceBehindTheSseGates` |
| S2 API carte tactique | couvert | `SecurityAuditLot1Test::testTacticalMarkersWriteNeedsEditorRightAndCsrf` |
| S3 reset roleplay | couvert | `SecurityAuditLot1Test::testRoleplayResetIsAPostWithCsrf` |
| S4 élévation | couvert | `SecurityAuditLot1Test::testElevationThatGrantsAccessNeedsAnAccessManager` |
| S5 setup communauté | couvert | `SecurityAuditLot1Test::testCommunitySetupNeedsAManager` |
| S6 paramètres communauté | partiel | `SecurityAuditLot2Test` ne teste que `OrganizationSettingsController` ; le reste est figé en ratchet (écart 1) |
| S7 audit et accès | partiel | API de règles testée ; `AccessControlMiddleware` non décidé (écart 3) |
| S8 droits de qualification | couvert | `SecurityAuditLot3Test::testQualificationGrantsSayTheyAreNotApplied` |
| S9 position administrative | couvert | `SecurityAuditLot2Test::testLegacyDutyPositionRoutesNeedStatusRight` |
| S10 documents RH | couvert | `SecurityAuditLot3Test::testHrDocumentVaultNeedsAFileRight` |
| S11 décorations | couvert | `SecurityAuditLot2Test::testDecorationGrantChecksTheCommunity` |
| S12 onboarding | couvert | `SecurityAuditLot3Test::testOnboardingGradeAndAccessNeedTheirOwnRights` |
| S13 API tactiques | couvert | `SecurityAuditLot3Test::testTacticalIngestNeedsKeyOrManager` |
| S14 dev seed | partiel | `SecurityAuditCoverageTest::testS14DevSeedIsClosedInProductionAndNeedsCsrf` (écart 2) |
| S15 flux publics par jeton | couvert (nouveau) | `SecurityAuditCoverageTest::testS15…` |
| S16 GET qui écrivent | couvert | `SecurityAuditLot3Test::testPagesNoLongerWriteOnGet`, `GetRoutesDoNotReadPostInputTest` |

## Ce qui n'a pas pu être testé

- 7.1 « statut attendu par profil » (200, 301, 403) : il faut une session et une base ; seul l'écart de garde de la route est testé. La déclaration unique (5a) n'existe pas encore.
- 7.2 matrice 10 profils × pages : seule la cohérence menu et porte de route (OrganizationAdminMiddleware, TenantResourceAdminMiddleware) est testée, pour 7 profils. Les contrôles internes des contrôleurs ne sont pas exécutés.
- 7.3 : les tests de sécurité existants lisent le code source ; aucun ne reproduit le défaut sur un tenant de test.
- 7.4 : le menu est rendu hors communauté chargée (formule complète, aucune pastille, aucun favori). Il n'est pas comparé à la maquette du document 7.
- 7.6, 7.7, 7.10, 7.11 : hors périmètre de cette branche.
- 7.9 : « 10 pages à 375 px » et mode nuit en CI ne sont pas couverts (aucun navigateur).
