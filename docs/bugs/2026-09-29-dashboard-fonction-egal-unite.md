# Tableau de bord — Fonction répétait le nom d’unité

## Contexte

Espace opérateur · Tableau de bord (`/public/back-office`). Unité et Fonction affichaient la même chaîne (ex. « 24th STS Gold Team SOF TACP »).

## Symptôme

Le champ **Fonction** ne reflétait pas le poste (billet ORBAT) : même libellé que **Unité**.

## Cause

La fonction était dérivée du rôle d’affectation / profil opérationnel, souvent rempli avec le nom de l’unité. Les billets ORBAT (`orbat_billets`) n’étaient pas consultés en priorité.

## Correctif

1. Priorité au titre du billet primaire (`OrbatBilletRepository::primaryBilletsForUser`).
2. Rejet explicite de tout libellé égal au nom d’unité (affectation, profil).
3. Affichage « Non renseignée » si aucun poste distinct n’est disponible.

## Fichiers touchés

- `app/Controllers/Admin/Organization/OrganizationDashboardController.php`
- `views/admin/organization/operator_overview.php`
- `tests/Unit/OperatorBackOfficeClarityAssetTest.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE #733)

## Vérification

- Syntaxe PHP OK sur contrôleur et vue.
- Assertions asset : `primaryBilletsForUser`, titre « Tableau de bord », absence de « Voir la liaison » / doublon démarches.
- Contrôle manuel : Ctrl+F5 sur le tableau de bord ; Unité ≠ Fonction lorsque un billet est pourvu.

## Statut

Corrigé (données amont : si aucun billet n’est pourvu pour l’opérateur, Fonction reste « Non renseignée » — comportement attendu).
