# ATHENA Academy — accueil connecté au LMS / live LMS entrance

## FR

`/academy` est une entrée dans le LMS ATHENA existant, avec une hero photographique pleine largeur, un effet de flou et la DA marketing noir/menthe du site. L’image fournie par l’utilisateur est servie localement depuis `public/assets/images/academy-hero.jpg`. Source : <https://forwardobservations.com/cdn/shop/files/87321613-A40C-4309-AD42-9DF6C492EDB1_4472x.jpg?v=1693081265>.

### Compte, communauté et accès

Les visiteurs voient une invitation à se connecter. Les sessions ouvertes passent par le même `AuthMiddleware` que `/formations` : compte actif, validité de session, communauté, restrictions et permissions sont vérifiés par l’application. L’accueil utilise `TenantContext::id()` pour la communauté effective ; aucun ID de compte ou de tenant transmis dans l’URL n’est utilisé.

L’interface distingue les états visiteur, compte sans communauté, communauté sans formule payante active, accès actif et indisponibilité de la vérification. Le nom du compte, la communauté, la formule, les formations publiées et la reprise du cursus proviennent du serveur. Les réponses personnalisées sont `private, no-store`. Les routes pédagogiques conservent le contrôle de formule Standard/Pro payante active ; les restrictions du module formation sont également appliquées. Le prestataire de paiement reste celui déjà configuré dans ATHENA, sans nouvelle intégration.

### Réutilisation du LMS existant

`AcademyLmsBridge` appelle `TrainingService::getCatalogue()` et `TrainingEnrollmentRepository::listByUserId()` avec le compte et la communauté courants. Il conserve les règles existantes : contenus communauté/plateforme, visibilité, masquage des parcours et priorité aux copies communautaires. La progression vient de `getGlobalProgress()`. Les inscriptions retirées, révoquées ou expirées sont exclues des activités en cours ; les approbations en attente restent à 0 %.

Les anciens modules `legacy_training_modules` sont également récupérés lorsque `TRAINING_LEGACY_ENABLED` l’autorise. Un module ancien ne remplace jamais un parcours LMS moderne portant le même slug. Les cours sont présentés sous forme de listes, sans grille de cartes ni cursus de démonstration injecté.

Les formations s’ouvrent dans le lecteur existant `/formations/{slug}` : inscriptions, leçons, ressources, évaluations, corrections et attestations continuent d’utiliser leurs contrôleurs et leurs règles d’accès habituels. Les outils réutilisés sont le catalogue et sa recherche avancée, les inscriptions personnelles, les sessions, les compétences et les codes d’accès. Les instructeurs disposent des liens vers le Studio, le suivi des inscriptions, les rapports, les attestations et les publications. Leurs routes natives restent responsables de leurs autorisations.

Aucun cours ni résultat fictif n’est créé à l’ouverture. Un catalogue vide est affiché comme vide. L’auteur d’un cursus Academy supplémentaire part désormais d’un formulaire FR/EN vierge. Les contenus publiés du LMS conservent les langues dans lesquelles leurs auteurs les ont rédigés ; les libellés de l’interface Academy sont FR/EN.

Les tables `academy_courses` et `academy_learners`, ajoutées par `migrations/20261029130000_athena_academy.sql`, restent utilisées pour les fonctions Academy supplémentaires (carnet privé et cursus en sept étapes). Si ces tables n’ont pas encore été migrées, les formations et outils du LMS existant restent accessibles ; les fonctions supplémentaires signalent leur indisponibilité. Aucune nouvelle migration n’est nécessaire pour cette intégration au LMS existant.

### Disclaimer à l’ouverture

Le composant commun `views/academy/disclaimer.php` utilise un dialogue natif accessible. À la première visite, il affiche le disclaimer de préversion et l’absence de qualification officielle. Après validation explicite, seul le choix `accepted` est écrit dans `localStorage` sous `athena:academy:disclaimer:2026-10-29-v1`.

La version fait partie de la clé : changer `data-disclaimer-version` réaffiche le texte après une modification du disclaimer. Le bouton « Lire l’avertissement » permet de le rouvrir. Fermer avec Échap ne mémorise aucune validation. Un refus du navigateur d’accéder au localStorage ne casse pas la page ; le dialogue peut être validé sans persistance. Le localStorage ne contient aucun identifiant, droit d’accès ou résultat et ne remplace jamais les contrôles côté serveur.

### Vérifications

Tests PHP : rendu des onze espaces et des états d’accès de l’accueil en FR/EN, source des formations, portée compte/tenant du bridge, récupération des modules anciens sans doublon, progression, droits payants et isolation des données sur MariaDB jetable. Les tests du repository refusent toute base autre qu’une base isolée nommée `academy_test` et restent facultatifs sans `ACADEMY_TEST_DSN`.

```sh
php vendor/phpunit/phpunit/phpunit --no-configuration --bootstrap tests/bootstrap.php \
  tests/Unit/AcademyViewsTest.php tests/Unit/AcademyLiveHomeTest.php \
  tests/Unit/AcademyLmsBridgeTest.php tests/Unit/AcademyCurriculumTest.php \
  tests/Unit/AcademyAccessMiddlewareTest.php tests/Unit/ManualBillingTest.php \
  tests/Contract/AcademyRepositoryTest.php
node tests/Frontend/academy-disclaimer.test.cjs
```

Cinq tests JavaScript vérifient l’ouverture, l’acceptation, la réouverture, le changement de clé de version et le fonctionnement quand le stockage est refusé. Ils exécutent les gestionnaires du script dans un DOM simulé ; ce ne sont pas des tests visuels dans un navigateur.

Le lien public `/public/formations` redirige vers la connexion ; l’intégration s’appuie sur les sources de cette même application présentes dans le dépôt. Aucun compte externe n’a été utilisé. Aucun déploiement ni accès à la base de production n’a été effectué ; la recette visuelle et la vérification des comptes réels restent à réaliser sur l’hébergement cible.

## EN

`/academy` now uses the existing ATHENA LMS as its source of courses and progress. The public entrance has a full-width blurred photographic hero using the user-supplied image, editorial lists and the site's existing black/mint marketing design. Signed-in requests reuse the native authentication middleware and effective tenant context. Guest, missing-community, unpaid, active and unavailable states are resolved server-side; account/tenant IDs from the URL cannot select learning data.

`AcademyLmsBridge` reuses the native course catalogue, enrolments and progress service. Legacy modules are included when enabled, without overriding modern courses sharing a slug. Courses open in the original player so native enrolment, assessment, resource and certificate policies remain in effect. The Academy links to existing catalogue filters, sessions, skills, access codes and instructor tools. No demonstration course is automatically published or injected; additional Academy curricula start with a blank bilingual form.

A native disclaimer dialog appears on first use. Explicit acknowledgement is stored under a versioned localStorage key. The notice can be reopened, a version change triggers a new acknowledgement, and blocked browser storage does not break the page. Access rights always remain server-controlled. Existing published learning content retains its authored languages; Academy interface labels support French and English.

The existing LMS works without the optional Academy tables. Apply the previously supplied Academy migration only for its additional notes/curriculum features. No new schema migration or payment-provider integration is introduced here. PHP tests cover account/tenant scope, real catalogue integration, access states, progress and isolated MariaDB persistence; five JavaScript handler tests cover the disclaimer. No production deployment, live-account verification or visual browser QA was performed.
