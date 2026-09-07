# Back-office opérateur : rien de ce qui le concerne

## Contexte

Un membre avec le rôle Opérateur ouvre **Mon back-office** (`/back-office`). La page s’intitule « Mon espace opérationnel ».

## Symptôme

L’écran affiche surtout des cases vides ou techniques : libellé « Tenant », mission absente, identifiant d’appareil, versions, statut brut `active`, « données RP » avec une progression à 100 % sans fonction. Le menu de gauche ne propose presque rien d’utile (tableau de bord, parfois coopérations). L’opérateur ne voit pas ses absences, ses demandes, les prochaines manœuvres, sa fiche ni sa boîte de réception.

## Cause

La racine `/back-office` est ouverte à tout membre connecté, mais la vue `operator_overview` ne chargeait que le nom de la communauté, les unités, une mission en direct (souvent absente) et les terminaux ATAK. Le menu reste celui des responsables, filtré par droits : il ne restait presque plus d’entrées.

## Correctif

La page charge désormais la situation personnelle réelle (grade, fonction, position de service, absences, demandes, manœuvres, messages, arrivée incomplète) et propose les raccourcis du portail. Le menu opérateur ajoute **Ma situation**. Les libellés techniques (tenant, identifiant d’appareil, statut brut) disparaissent.

## Fichiers touchés

- `app/Controllers/Admin/Organization/OrganizationDashboardController.php`
- `views/admin/organization/operator_overview.php`
- `views/partials/ath_sidebar_nav.php`
- `views/partials/back_office_sidebar.php`
- `app/Support/DevDispatchCatalog.php`
- `tests/Unit/OperatorBackOfficeAccessTest.php`
- `tests/Unit/OperatorBackOfficeClarityAssetTest.php`
- `tests/Unit/DevDispatchCatalogTest.php`

## Vérification

- `php -l` sur les fichiers PHP modifiés
- Tests d’assets : l’espace opérateur cite la situation personnelle, plus « Tenant » ni « Mes données RP »
- Parcours attendu : se connecter en opérateur, ouvrir Mon back-office, voir communauté / unité / raccourcis, sans pages d’administration

## Statut

Corrigé
