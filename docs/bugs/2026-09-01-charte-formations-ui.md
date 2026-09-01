# Charte des formations — lecture trop étroite

## Contexte

Page membre `account/charte-formations`, hors du reste de l’espace compte.

## Symptôme

La charte s’affichait dans un cadre étroit, séparé du menu personnel. Après confirmation, le texte disparaissait entièrement. La case restait loin du document, sans indication de parcours.

## Cause

La vue était un bloc isolé, avec une zone de lecture trop basse, et le document n’était rendu que tant que la prise en compte n’était pas enregistrée.

## Correctif

La page rejoint l’espace compte : résumé (situation, durée de lecture, version), document plus large avec barre de parcours, confirmation juste sous le texte. Une fois enregistrée, le texte reste relisible.

## Fichiers touchés

- `views/rh/charter.php`
- `public/assets/css/account-hub.css`
- `public/assets/js/rh-charter.js`
- `app/Controllers/Web/HrCharterController.php`

## Vérification

Test `HrCharterMemberUiAssetTest` : shell compte, lecteur, barre de parcours, confirmation sous le document, texte relisible après enregistrement.

## Statut

corrigé
