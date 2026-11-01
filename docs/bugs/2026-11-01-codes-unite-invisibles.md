# Codes unité invisibles en production

## Contexte
Back-office › Effectifs › Affectations (vue « Par unité »), tableur, fiche et ORBAT, après la mise en ligne des codes unité (PR #1230).

## Symptôme
Aucun code unité ni identifiant administratif affiché ; la page Affectations indiquait « Les codes seront attribués à la prochaine ouverture de cette page » à chaque ouverture. Le filtre « Toutes les unités » était vide.

## Cause
Les tables `tenant_unit_codes` et `unit_codes` sont créées à la demande à partir de `migrations/2026_11_01_002_unit_codes.sql`. Le fichier était découpé en instructions sur chaque « ; », y compris celui du commentaire de colonne « Prochain numéro d unité ; ne redescend jamais ». La première instruction partait tronquée, MySQL la refusait, l’erreur était seulement journalisée et le service renvoyait une liste de codes vide. Les tests passaient car ils créaient les tables à la main en SQLite.

## Correctif
- Découpage SQL qui ignore les « ; » entre guillemets et les commentaires (`UnitCodeRepository::statements()`), et commentaire de colonne sans point-virgule.
- À la première lecture, tables créées, communautés numérotées et codes de toute la communauté calculés (sans attendre une modification d’unité) ; code de communauté proposé depuis le nom.
- Message d’erreur explicite si le calcul échoue encore, au lieu de promettre une attribution.
- Vue « Par unité » refaite (cartes en organigramme, code et identifiant visibles, titulaires avec grade, rôle et emploi, postes pourvus et vacants de l’ORBAT).

## Fichiers touchés
- `app/Repositories/UnitCodeRepository.php`, `migrations/2026_11_01_002_unit_codes.sql`
- `app/Services/Effectifs/AffectationsMatrixService.php`, `views/admin/effectifs_workspace/affectations.php`
- `public/assets/css/back-office-effectifs-maquette.css`, `public/assets/js/eff-affectations.js`

## Vérification
`tests/Unit/UnitCodeFirstReadTest.php` : le fichier SQL réel donne deux instructions complètes ; une première lecture sans table ni action préalable crée les tables et renvoie les codes et identifiants (le test échoue sur l’ancien code). `EffectifsShellRenderTest` couvre la nouvelle vue.

## Statut
Corrigé (branche `site/affectations-unites`).

## Suite (01/11, migration en production)
L'étape `unit_codes` de run-migrations échouait : `errno 121 Duplicate key` à la création de `tenant_unit_codes`.
Cause : le nom de clé étrangère `fk_tuc_tenant` existe déjà dans la base (table `tenant_usage_counters`) ; sous MySQL, un nom
de contrainte est unique dans tout le schéma. Contraintes renommées `fk_tenant_unit_codes_tenant`, `fk_unit_codes_unit`,
`fk_unit_codes_tenant`. Relancer `php8.4 run-migrations.php` (l'étape en échec est rejouée).
