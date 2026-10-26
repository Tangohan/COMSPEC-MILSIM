# Classement des traducteurs compté en double

## Contexte

Une personne peut avoir plusieurs rôles de contributeur (traducteur, testeur, etc.). Le classement « Meilleurs contributeurs » et le bloc public « Traducteurs en tête » comptent les textes validés par personne.

## Symptôme

Dès qu’une personne avait un second rôle, ses textes validés étaient comptés une fois de plus par rôle. Le pourcentage du catalogue et la place dans le classement devenaient faux. Le nom affiché pouvait aussi être le nom du compte plutôt que le pseudo public ou l’indicatif.

## Cause

Le décompte joignait chaque texte validé à toutes les lignes de rôle de la même personne. Le nom reprenait l’indicatif, puis le nom du compte, sans le pseudo public.

## Correctif

Le décompte ne joint plus les rôles. Les rôles sont lus à part, après le classement. Le nom public est le pseudo, sinon l’indicatif.

## Fichiers touchés

- `app/Repositories/SiteContributorRepository.php` (`translationLeaders`)

## Vérification

Relecture de la requête : `COUNT(*)` porte uniquement sur les textes, groupés par personne. `php -l` sur le fichier. Pas de jeu de données en base dans cet environnement pour rejouer un classement réel.

## Statut

Corrigé.
