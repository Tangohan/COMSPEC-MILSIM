# Dossier personnel public — bandeau et choix de vue

## Contexte

Fiche personnel : écran de choix (vue publique / vue RH) puis bandeau d’identité de la vue publique.

## Symptôme

- Une petite image carrée (photo de compte) flottait à gauche du portrait opérateur.
- Le bouton **Signaler un problème** s’étirait trop large.
- Indicatif, surnom et matricule se mélangeaient ; pastilles instables.
- Onglets d’espace personnel (Identité, Compétences, Compte…) affichés sur la fiche d’un autre membre.
- Sur l’écran de choix : grand vide blanc entre les annonces (ex. Ancienneté) et le bandeau sombre, puis un autre vide entre les cartes de vue et le pied de page.

## Cause

Le bandeau plaçait photo de compte et portrait côte à côte. Le signalement était un bouton `max-w-md`. Les onglets d’espace personnel s’affichaient hors vue RH, y compris pour un autre membre.

L’écran de choix était dans un second `<main min-h-screen pt-20 pb-24>` à l’intérieur du `<main min-h-[80vh]>` du portail. Le menu est déjà dans le flux (pas superposé) : le `pt-20` creusait un blanc sous les annonces. Les `min-height` étiraient la page après les cartes.

## Correctif

- Photo de compte incrustée dans le cadre du portrait.
- Signalement compact.
- Hiérarchie nom / indicatif / surnom / matricule ; pastilles stables.
- Onglets d’espace personnel uniquement sur **sa** fiche.
- Enveloppe sans `min-h-screen` ni `pt-20` ; écran de choix compact (bandeau + cartes) ; le portail ne force plus 80 vh sur cet écran.

## Fichiers touchés

- `views/personnel/file.php`
- `views/partials/personnel/file_view_gate.php`
- `public/assets/css/personnel-file.css`
- `views/layout/main.php`
- `app/Controllers/Web/PersonnelController.php`
- `tests/Unit/PersonnelPublicFileHeroAssetTest.php`

## Vérification

Test unitaire `PersonnelPublicFileHeroAssetTest` : classes du bandeau public, écran de choix compact, absence de `min-h-screen pt-20 pb-24`. La page de production n’a pas pu être parcourue sans connexion.

## Statut

corrigé
