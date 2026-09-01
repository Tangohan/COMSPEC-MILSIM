# Dossier personnel — vide sous le choix de vue

## Contexte

L’écran de choix Vue publique / Vue RH (accès RH) laissait un grand espace blanc avant le pied de page, et le pied de page paraissait décroché.

## Symptôme

Sous les deux cartes, une bande vide ; le pied de page n’enchaîne pas. Impression d’une page trop haute pour si peu de contenu.

## Cause

Le dossier était dans un bloc déjà cadré par la page, tout en forçant encore une hauteur d’écran et un large padding bas. Le pied de page ajoutait une marge haute.

## Correctif

Le choix de vue n’étire plus la page. Les marges autour des cartes sont réduites. Le pied de page se colle au contenu.

## Fichiers touchés

- `views/personnel/file.php`
- `views/partials/personnel/file_view_gate.php`
- `views/layout/main.php`
- `public/assets/css/portal-footer.css`
- `app/Controllers/Web/PersonnelController.php`

## Vérification

Assertions d’assets : plus de `min-h-screen pt-20 pb-24` sur le dossier, classe compacte, paddings du choix réduits.

## Statut

Corrigé
