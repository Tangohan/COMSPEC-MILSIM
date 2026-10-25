# Fiche personnel : disponibilité vide de sens et candidature mal collée

## Contexte

Onglet Portrait d’une fiche personnel. Trois zones étaient en cause : le pourcentage de disponibilité, le bloc temps de jeu, et la carte « Dernière candidature recrutement ».

## Symptôme

- La disponibilité affichait un pourcentage (souvent 1 %) qui ne bougeait pas avec les présences.
- Le temps de jeu était tassé dans la grille de synthèse, en petites cartes.
- La candidature écartait les intitulés de leurs valeurs. Un dossier déjà rattaché à un compte restait libellé « Examinée (suite à traiter ou compte à rattacher) ».

## Cause

- À l’intégration, le dossier reçoit un chiffre de disponibilité égal à 1. La fiche le montrait comme un vrai pourcentage, et le suivi de parcours le comparait au seuil de 30 %.
- Les présences des 45 derniers jours étaient déjà calculées pour l’effectif, mais la fiche ne les lisait pas.
- La candidature avait sa propre table de libellés, distincte du bureau recrutement, et une grille sur deux colonnes qui laissait la dernière ligne seule.

## Correctif

- La fiche affiche le pourcentage de présences confirmées sur les opérations des 45 derniers jours, avec le décompte. Sans opération, elle indique « Pas encore mesurée ».
- Le temps de jeu est une section à part : cumul, 7 et 30 jours, répartition, semaines, rôles. L’activité des 30 jours y est regroupée.
- La candidature reprend l’étape du bureau recrutement. Chaque intitulé est sur la même ligne que sa valeur.

## Fichiers touchés

- `app/Repositories/UnitRepository.php`
- `app/Controllers/Web/PersonnelController.php`
- `app/Services/Personnel/PersonnelCompletenessService.php`
- `views/personnel/file.php`
- `views/partials/personnel/file_playtime.php`
- `app/Support/DevDispatchCatalog.php`

## Vérification

- Contrôle de syntaxe PHP des fichiers touchés.
- La page en ligne demande une connexion : le rendu réel du dossier Damian n’a pas pu être ouvert depuis ici.
- Aperçu statique des trois blocs (disponibilité, temps de jeu, candidature) dans le navigateur.

## Statut

Corrigé
