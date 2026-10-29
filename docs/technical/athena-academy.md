# ATHENA Academy — préversion 0.1 / pre-release 0.1

## FR — Installation et périmètre

L’accueil se trouve sur `/academy`. Les espaces pédagogiques sont accessibles sur `/academy/dashboard`, `/academy/catalogue`, `/academy/courses`, `/academy/classroom`, `/academy/laboratory`, `/academy/simulators`, `/academy/exams`, `/academy/library`, `/academy/notebook`, `/academy/record` et `/academy/instructor`. Les menus invité et membre proposent l’entrée ATHENA Academy. Les pages, messages, cursus de démonstration et formulaires sont disponibles en français et en anglais via le sélecteur habituel d’ATHENA.

Cette livraison modifie l’application PHP existante. Elle ne crée pas un nouveau site hébergé et ne modifie pas le simulateur ATAK existant. Elle fournit un espace Academy indépendant, un simulateur pédagogique fictif et un cursus de démonstration en trois chapitres. Les contenus métier JTAC et la migration de scénarios du simulateur existant restent à définir et à faire revoir par les instructeurs. Aucune certification officielle n’est délivrée.

### Direction artistique

L’accueil réutilise `views/layout/marketing.php`, son menu, son pied de page et les médias existants d’ATHENA : registre immersif noir/menthe et typographie Inter. Les espaces membres utilisent le registre LMS clair/émeraude, le bandeau module et l’aside partagé `views/partials/lms_nav_aside.php`, avec son menu mobile. `academy.css` consomme les tokens de `design-system.css` ; aucune palette orange ni identité de marque indépendante n’est ajoutée.

### Activation

1. Déployer les fichiers de cette livraison dans l’application PHP 8.4 existante.
2. Appliquer `migrations/20261029130000_athena_academy.sql` au moyen du pipeline de migrations habituel (`php run-migrations.php`). Cette migration ajoute uniquement `academy_courses` et `academy_learners` ; elle ne transforme ni ne supprime les données du LMS existant.
3. Vérifier un compte actif rattaché à une communauté ayant une formule `standard` ou `pro`, un statut d’abonnement `active`, et une période de paiement manuel non échue lorsqu’elle existe. Les formules gratuites, essais, abonnements échus ou en impayé ne donnent pas accès. L’expiration ou une date manuelle invalide bloque l’accès.
4. Pour les instructeurs, utiliser les permissions existantes reconnues par `TrainingLmsStaffAccess`. Tester la validation avec deux comptes distincts : l’auto-validation est interdite.

Aucun nouveau prestataire de paiement n’a été configuré : choix reporté à la demande de l’utilisateur. Les boutons de formule conduisent au parcours de facturation existant. Le droit est vérifié côté serveur à chaque lecture et écriture Academy ; le navigateur ne peut pas activer une formule.

### Parcours pédagogique

Chaque chapitre suit : théorie → démonstration → exercice guidé → simulation autonome → évaluation → débriefing → validation par un instructeur. Les lectures sont déclaratives ; la pratique et l’évaluation sont corrigées côté serveur. En préversion, chaque chapitre utilise une même question pédagogique à trois options pour les étapes de pratique et d’évaluation. L’évaluation dure cinq minutes avec un seuil de réussite de 100 %. Ce format teste le parcours LMS ; il ne constitue pas un examen de qualification JTAC.

Les tentatives, résultats, débriefings et retours instructeur sont conservés. Une évaluation expirée échoue même si la réponse est correcte. Une validation débloque le chapitre suivant. Chaque inscription conserve une copie de sa version du cursus, afin que les nouvelles publications ne changent pas rétroactivement les questions ou les critères d’une inscription en cours. Les écritures de progression sont transactionnelles avec verrouillage de ligne ; les formulaires portent l’étape et le chapitre attendus pour empêcher les doubles soumissions d’avancer deux fois.

### Création et ressources

L’instructeur crée un cursus avec un formulaire FR/EN, ajoute ou retire des chapitres et définit une question, trois options et une réponse correcte par chapitre. Il peut ajouter des ressources `document`, `slides`, `diagram` ou `audio`, avec des titres et URL HTTPS dans les deux langues. Les documents, diapositives et schémas s’ouvrent dans un nouvel onglet ; les enregistrements audio disposent d’un lecteur. Les ressources externes doivent autoriser la lecture par le navigateur et respecter la politique CSP de l’hébergement. Aucun téléversement ni exécution de HTML arbitraire n’est ajouté.

Une nouvelle version peut être enregistrée en brouillon ou publiée dans le catalogue de la communauté. Un brouillon enregistré peut être publié ensuite. Le mode JSON avancé permet aussi de créer une nouvelle version à partir d’un contenu existant. Le formulaire visuel nécessite JavaScript ; le mode JSON reste utilisable sans JavaScript.

Le simulateur indépendant ne modifie pas le dossier stagiaire. Le carnet est privé, conserve jusqu’à 30 000 caractères, propose l’impression des fiches et un QR code vers la salle de cours, qui reste protégée par les mêmes contrôles d’accès. Les notes personnelles sont exclues de la requête de suivi instructeur.

### Vérification et limites

26 tests / 200 assertions passent sous PHP 8.4 et MariaDB 11.4 : droits payants, contrôles GET/POST, traduction FR/EN, rendu des onze espaces, échappement des notes, progression, rejeu, expiration, publication, copies de cursus, isolation stagiaire/communauté et rollback des mutations. La syntaxe PHP et JavaScript a également été contrôlée.

Commande des tests :

```sh
php vendor/phpunit/phpunit/phpunit --no-configuration --bootstrap tests/bootstrap.php \
  tests/Unit/AcademyCurriculumTest.php \
  tests/Unit/AcademyViewsTest.php \
  tests/Unit/AcademyAccessMiddlewareTest.php \
  tests/Contract/AcademyRepositoryTest.php \
  tests/Unit/ManualBillingTest.php
```

Le test de repository est facultatif sans `ACADEMY_TEST_DSN`. Pour l’exécuter, utiliser une base MariaDB jetable nommée strictement `academy_test`, avec l’utilisateur root et sans mot de passe, sur un réseau Docker de test isolé, sans port exposé. Ce test efface uniquement les tables Academy de cette base de test et refuse toute autre base. Ne jamais le diriger vers une base applicative.

La migration et les tests ont été exécutés sur une base de test jetable, sans modification de la base applicative. Aucun déploiement en production ni contrôle visuel dans un navigateur n’a été effectué dans cette session. L’interface comprend une mise en page responsive ; la recette visuelle et la validation des contenus métier restent à réaliser sur l’hébergement cible.

## EN — Setup and scope

Open `/academy` for the bilingual home page and `/academy/dashboard` for the protected learning space. Deploy the changed files to the existing PHP 8.4 application and apply `migrations/20261029130000_athena_academy.sql` through the normal migration pipeline. The two added tables are isolated from the existing LMS.

Every Academy learning read and write requires an active user session and a current paid Standard or Pro community plan. Free plans, trials, expired manual periods and overdue subscriptions are rejected server-side. No new payment provider has been configured; the choice was deferred by the user. Existing ATHENA billing remains the source of subscription status.

The pre-release supplies a three-chapter demonstration curriculum, seven sequential learning stages, a fictional independent practice simulator, server-timed assessments, private notes, a classroom QR code, printable study cards, learner records and instructor review. Instructors create bilingual courses and resource links using a structured form or advanced JSON mode, save drafts and publish them within their community. Enrolments retain immutable content snapshots. Learners cannot validate themselves. Personal notebooks are never returned by the staff-review query.

This is a foundation for the Academy, not an official JTAC qualification programme. Subject-specific content and integration of scenarios from the existing ATAK simulator require separate definition and review. The existing ATAK simulator has not been changed. Documents, slides and interactive diagrams use external HTTPS resources; audio resources have a player, subject to the hosting CSP and resource server permissions.

Validation: 26 tests and 200 assertions passed on PHP 8.4 and disposable MariaDB 11.4, plus PHP/JavaScript syntax checks. Production migration, deployment and visual browser QA have not been performed. Use `ACADEMY_TEST_DSN` only with an isolated disposable database named `academy_test`; the repository tests clear its Academy tables.
