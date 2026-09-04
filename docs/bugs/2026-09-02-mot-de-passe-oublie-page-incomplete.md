# Page mot de passe oublié incomplète et enregistrement fragile

## Contexte

Le lien « Mot de passe oublié » existait depuis la connexion. La page dédiée restait un ancien écran, et l’enregistrement du nouveau mot de passe passait par une mise à jour filtrée par communauté.

## Symptôme

La page de récupération ne ressemblait pas à la connexion. Après la réunion des comptes, le nouveau mot de passe pouvait ne pas s’enregistrer (filtre communauté sur le compte survivant).

## Cause

1. L’écran `forgot-password` n’avait pas été repris sur le modèle actuel (bandeau, textes métier).
2. `UserRepository::update()` ajoutait `AND tenant_id = ?` lorsque l’appartenance n’était pas reconnue, donc le hash n’était pas écrit.

## Correctif

- Pages mot de passe oublié et nouveau mot de passe alignées sur la connexion.
- Service dédié : lien à usage unique (2 h), e-mail sans révéler si l’adresse existe, mot de passe écrit sur l’identifiant du compte.

## Fichiers touchés

- `app/Services/Auth/PasswordResetService.php`
- `app/Controllers/Auth/AuthController.php`
- `views/auth/forgot-password.php`
- `views/auth/reset-password.php`
- `views/auth/login.php`
- `app/Repositories/UserRepository.php`
- `app/Repositories/PasswordResetRepository.php`

## Vérification

- Connexion → Mot de passe oublié : formulaire complet, même présentation.
- Après l’e-mail : page nouveau mot de passe, confirmation, retour à la connexion.

## Statut

Corrigé.
