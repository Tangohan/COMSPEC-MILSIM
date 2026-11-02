# ATAK › Sessions & connexions : « ça remonte encore l'ancien rôle »

## Contexte
Page back-office `back-office/atak/operateurs` (tableur des opérateurs en liaison), son export CSV et la fiche opérateur
ATAK (`back-office/atak/fiche-operateur`).

## Symptôme
La colonne « Rôle » montrait le rôle envoyé par le jeu (« Operator », description du slot Arma, « Team leader — Alpha »…),
pas la fonction attribuée sur le site. Le compte lié était affiché sous son pseudo, sans grade ni affectation.

## Cause
`AdminAtakOperatorsController::buildRoster()` lisait `atak_units.role` (ou `extra.role`), écrit à chaque position par
le mod (`fn_getUnitRole` : rôle personnalisé → slot → équipe de feu → « Operator »). Le champ est toujours alimenté, mais
c'est une déclaration de jeu, sans lien avec les attributions de fonctions (`personnel_profile_job_roles`), le grade du
référentiel ou l'affectation ORBAT.

## Correctif
- Nouveau `App\Services\Atak\AtakOperatorPersonnelService` : pour les comptes liés, nom « Prénom N. » + trigramme
  (`MemberName`), grade abrégé, fonction principale (attributions), unité d'affectation et sa chaîne, avec la
  confidentialité de l'ORBAT (`OrgVisibilityService::filterPersonnelRow`).
- Le rôle du jeu ne reste que comme « Déclaré en jeu », affiché seulement s'il diffère de la fonction (pastille
  « Écart ») ; « Operator » générique ignoré.
- Filtres `unite` / `fonction`, colonnes Grade, Fonction · affectation, Version du mod (`extra.mod_version`).
- Export CSV et fiche opérateur alignés. Membre masqué/anonymisé : ni nom, ni lien, ni Steam, ni portrait.
- Non modifié : `GameAuthService` envoie toujours au jeu `role` = rôle d'accès (`users.role_id`) et `function` =
  fonction ; certaines pages du mod affichent `comspec_profile_role` sous le libellé « Fonction ». À corriger côté mod
  (lire `comspec_profile_function`) si souhaité.

## Fichiers touchés
`app/Controllers/Admin/AdminAtakOperatorsController.php`, `app/Services/Atak/AtakOperatorPersonnelService.php`,
`app/Core/Container.php`, `views/admin/atak_operators/index.php`, `views/admin/atak_operators/profile.php`,
`lang/en/site_auto.php`, `tests/Unit/AtakOperatorsCurrentFunctionTest.php`.

## Vérification
`AtakOperatorsCurrentFunctionTest` (rendu de la page, masquage, rôle déclaré, câblage), `UntranslatedFrenchScannerTest`,
`MemberNamesWiringTest`, `RoutedControllersContainerWiringTest`.

## Statut
Corrigé (PR #1333).
