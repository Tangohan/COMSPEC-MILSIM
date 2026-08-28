# Bug — pseudo du validateur collé sur le dossier du candidat

## Contexte

Candidature acceptée dans une communauté. Le candidat a déjà un compte Athena ailleurs. Capture du tableau administratif : section Identité, champ Nom de personnage.

## Symptôme

Après acceptation, le dossier du nouveau membre affichait le pseudo de la personne qui avait validé (MORPHIDE), comme s’il s’agissait du nom de personnage. Le champ Nom de personnage était en réalité vide (tiret) : le pseudo apparaissait comme nom affiché, ou comme reliquat d’un remplissage silencieux plus ancien.

## Cause

1. L’acceptation dupliquait le compte source tel quel (nom affiché et indicatif de l’autre communauté), sans reprendre le dossier de candidature.
2. Si le compte lié à la candidature n’était pas celui du candidat (e-mail différent), on dupliquait quand même ce compte — y compris celui de la personne qui validait.
3. Un remplissage automatique du nom de personnage depuis un profil de candidature (corrigé la veille) laissait des dossiers déjà faussés.

## Correctif

- Ne pas dupliquer un compte dont l’e-mail n’est pas celui du dossier : créer ou lier le membre à partir de l’e-mail de candidature.
- À la duplication, poser le nom et l’indicatif du dossier, pas ceux de l’autre communauté.
- Après rattachement, retirer nom affiché, indicatif et nom de personnage s’ils reprennent le pseudo de la personne qui a validé.
- Dans le tableau administratif, n’afficher le nom de personnage que s’il diffère du nom de compte.

## Fichiers touchés

- `app/Support/EnlistmentAcceptedIdentity.php`
- `app/Services/Recruitment/EnlistmentAcceptanceProvisioningService.php`
- `app/Repositories/UserRepository.php`
- `views/partials/personnel/file_tableau_admin_tab.php`
- `tests/Unit/EnlistmentAcceptedIdentityTest.php`
- `tests/Unit/EnlistmentAcceptanceIdentityAssetTest.php`

## Vérification

`phpunit tests/Unit/EnlistmentAcceptedIdentityTest.php tests/Unit/EnlistmentAcceptanceIdentityAssetTest.php`

Sur un dossier déjà faussé : corriger le nom affiché (et vider le nom de personnage s’il reprend le pseudo du validateur) dans la fiche personnel.

## Statut

Corrigé (dossiers déjà créés : correction manuelle sur la fiche).
