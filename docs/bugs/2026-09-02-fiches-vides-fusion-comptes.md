# Fiches vides après la réunion des comptes (un e-mail = un compte)

## Contexte

La mise à jour du 2 septembre 2026 a réuni, pour une même adresse e-mail, les comptes présents dans plusieurs communautés. Un seul compte survit ; les autres sont marqués comme réunis.

## Symptôme

Après la mise à jour, presque toutes les fiches (nom, indicatif, photo, dossier RH) s’affichent vides, alors que les personnes n’ont rien effacé.

## Cause

1. La lecture du dossier prenait la première ligne RH du compte survivant (`LIMIT 1`), souvent une fiche vide de l’ancienne communauté, alors que le dossier rempli était sur une autre ligne (autre communauté) ou encore rattaché à l’ancien identifiant.
2. L’écran recouvrait indicatif, grade et rôle avec des champs vides de la fiche communauté, y compris un statut « en attente » par défaut.
3. La fusion ne recopiait pas la fiche civile (`user_profiles`) si le survivant avait déjà une ligne vide.

Les données n’étaient en général pas effacées : elles restaient sur l’ancien compte ou sur l’autre ligne RH.

## Correctif

- Lecture : prendre la fiche de la communauté en cours, ou à défaut la plus complète.
- Ne plus recouvrir une valeur remplie par un champ vide.
- Reprise automatique à la mise à jour : recopier uniquement les champs vides depuis les comptes réunis (journal `user_identity_merges`).
- L’indicatif ATAK n’est plus unique sur toute la plateforme, seulement dans une communauté.

## Fichiers touchés

- `app/Services/Identity/UserIdentityProfileRestoreService.php`
- `app/Services/Identity/UserIdentityMergeRules.php`
- `app/Services/Identity/UserIdentityMergeService.php`
- `app/Repositories/PersonnelProfileRepository.php`
- `app/Repositories/PersonnelExtrasRepository.php`
- `app/Repositories/UserRepository.php`
- `app/Repositories/UserCommunityMembershipRepository.php`
- `bootstrap/user_community_identity_migration.php`
- `run-migrations.php`
- `scripts/restore-identity-merge-profiles.php`

## Vérification

- Tests unitaires des règles de choix de fiche et de reprise SQLite.
- Contrôle visuel : ouvrir un dossier connu comme rempli avant la fusion.

## Statut

Corrigé (à déployer). Relancer une mise à jour du portail, ou `php scripts/restore-identity-merge-profiles.php`.
