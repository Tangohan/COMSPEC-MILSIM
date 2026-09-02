# Perte des données de fiches après la fusion des comptes

## Contexte

La mise à jour « un compte, plusieurs communautés » (PR #344) fusionne automatiquement les lignes `users` qui partagent la même adresse e-mail. La fusion a bien abouti. Après coup, les fiches (dossier RH, prénom/nom, indicatif, grade) apparaissaient vides pour presque tout le monde.

## Symptôme

Les pages de dossier et de profil s’ouvrent, mais les champs sont vides : nom de personnage, indicatif, photo, état civil. Les personnes n’ont pas disparu de l’annuaire, leurs dossiers semblent effacés.

## Cause

Trois mécanismes se cumulent, sans supprimer la plupart des données :

1. **Mauvaise ligne lue.** Après fusion, un même compte peut avoir deux dossiers RH (communauté d’origine vide, communauté réelle remplie). La lecture `WHERE user_id = ? LIMIT 1` prenait la première ligne, souvent vide.
2. **Le dossier n’était pas déplacé.** Si le compte survivant avait déjà une ligne RH (même vide), le dossier rempli restait accroché au compte absorbé (`merged+…@merged.invalid`). L’écran interroge le survivant → fiche vide.
3. **Masque communauté.** La fiche communauté créée vide (statut « pending », indicatif nul) recouvrait l’indicatif, le grade et le rôle encore présents sur le compte.

Le journal `user_identity_merges` et les lignes des comptes absorbés conservent de quoi reprendre.

## Correctif

- Lire le dossier de la communauté en cours, ou à défaut le plus complet.
- Ne plus recouvrir un champ rempli par une valeur vide ou un statut « en attente » fantôme.
- Reprendre les dossiers depuis les comptes absorbés et le journal, sans écraser une valeur déjà saisie.
- L’indicatif Athena n’est plus unique sur toute la plateforme : il l’est par communauté.

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

- Tests unitaires : choix de la ligne RH, remplissage des vides, restauration SQLite.
- En production : lancer la mise à jour du portail, ou `php scripts/restore-identity-merge-profiles.php`, puis ouvrir une fiche membre connue comme remplie avant la fusion.

## Statut

Corrigé (données reprises à la prochaine mise à jour, ou tout de suite via le script).
