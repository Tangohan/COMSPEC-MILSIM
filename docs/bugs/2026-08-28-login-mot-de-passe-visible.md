# Bug — Mot de passe visible à la connexion

## Contexte

Page de connexion du portail. Le champ mot de passe doit rester masqué (points), avec un bouton pour l’afficher à la demande.

## Symptôme

Les caractères tapés s’affichaient en clair, comme un champ texte ordinaire.

## Cause

Le champ n’avait pas de `type="password"` dans le HTML : le type était posé par un script externe, souvent bloqué par la politique de sécurité de la page. Sans ce script, le navigateur traite le champ comme du texte.

## Correctif

Le champ est un vrai champ mot de passe dès le HTML. Afficher / masquer passe par un bouton de la page, sans dépendre du script externe. Le lien « mot de passe oublié » mène à la page prévue.

## Fichiers touchés

- `views/auth/login.php`
- `views/auth/register.php`
- `public/assets/js/auth_forms.js`
- `tests/Unit/LoginPasswordMaskAssetTest.php`

## Vérification

- Ouvrir la page de connexion : le champ est de type mot de passe.
- Taper quelques caractères : des points, pas le texte.
- Le bouton Afficher / Masquer inverse le masquage.
- Test unitaire : `type="password"` présent, pas de type conditionnel seul.

## Statut

corrigé
