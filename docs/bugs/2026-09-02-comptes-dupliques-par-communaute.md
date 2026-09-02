# Comptes dupliqués par communauté

## Contexte

Une même personne qui rejoint plusieurs communautés se voyait créer une nouvelle fiche compte à chaque fois (même adresse e-mail, autre identifiant, autre mot de passe possible, autre identifiant Athena). L’annuaire d’administration du site regroupait déjà ces fiches par e-mail, ce qui confirmait le sentiment de doublons.

## Symptôme

- La même adresse apparaît plusieurs fois comme des comptes distincts.
- Changer le mot de passe d’une communauté ne changeait pas les autres.
- L’administration du site, en ouvrant une personne, voyait plusieurs fiches au lieu d’une identité et de dossiers séparés.
- Rejoindre une communauté (invitation, création d’organisation, recrutement) recopiait le compte au lieu d’ajouter une appartenance.

## Cause

La table des comptes portait à la fois l’identité de connexion (e-mail, mot de passe, Steam) et le dossier communautaire (grade, indicatif, statut). L’unicité était `(communauté, e-mail)`. La fonction de rattachement dupliquait donc la ligne pour chaque communauté. Les dossiers RH (`personnel_profiles`, extras) étaient liés uniquement à l’identifiant de fiche, sans communauté : c’est précisément ce qui empêchait de fusionner les comptes sans mélanger les dossiers.

## Correctif

- Une ligne compte par personne (e-mail unique pour les comptes humains).
- Appartenances et fiches communautaires séparées : grade, indicatif, matricule, identifiant Athena de la communauté restent dans le dossier de cette communauté.
- Dossiers RH scopés par personne **et** communauté. On ne recopie jamais un grade ou un matricule d’une unité vers une autre.
- Fusion explicite et journalisée des anciennes fiches du même e-mail : on conserve le compte le plus ancien (ou le plus complet), on rattache les dossiers des autres communautés, on libère les anciennes adresses. Collision Steam : on garde le Steam du compte conservé, l’autre est noté, jamais inventé.
- Invitation, création de communauté et ajout d’un membre : si l’e-mail existe déjà, on ajoute une appartenance, on ne crée pas un second compte.

Après déploiement : relancer la mise à jour du portail. Les membres n’ont rien à refaire, sauf se reconnecter. L’administration du site voit une personne et un dossier par communauté, clairement libellé.

## Fichiers touchés

- `bootstrap/user_community_identity_migration.php`
- `app/Services/Identity/UserIdentityMergeRules.php`
- `app/Services/Identity/UserIdentityMergeService.php`
- `app/Repositories/UserCommunityMembershipRepository.php`
- `app/Repositories/UserRepository.php`
- `app/Repositories/PersonnelProfileRepository.php`
- `app/Repositories/PersonnelExtrasRepository.php`
- `app/Services/Auth/AuthService.php`
- `app/Controllers/Admin/Organization/UserAdminController.php`
- `app/Controllers/Admin/System/SystemUsersController.php`
- `views/admin/system/user_person.php`
- `run-migrations.php`

## Vérification

- Tests unitaires des règles de fusion (survivant, Steam, dossiers RH séparés).
- Tests d’assemblage : pas de second `INSERT` à l’adhésion, connexion sur un seul mot de passe, lecture RH avec la communauté.
- Syntaxe PHP des fichiers compte et fusion.

## Statut

Corrigé (à appliquer par la mise à jour du portail, fusion incluse).
