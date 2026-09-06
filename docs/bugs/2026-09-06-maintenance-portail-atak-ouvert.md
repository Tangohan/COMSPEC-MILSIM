# Maintenance portail : Arma et ATAK restent ouverts

## Contexte

Une intervention globale fermait le portail Athena. Les opérateurs en mission devaient encore joindre le poste depuis Arma 3 et le téléphone ATAK.

## Symptôme

La carte web et la liaison en jeu pouvaient être coupées en même temps que l’accueil, les dossiers et l’administration.

## Cause

Le garde de maintenance traitait tout le site de la même façon. Les pages du téléphone et une partie des échanges en jeu n’étaient pas distinguées du portail.

## Correctif

Une intervention sur le portail ferme l’accueil, les dossiers, l’administration et le renseignement. Arma 3, le téléphone ATAK et la carte tactique restent utilisables. La connexion au compte reste possible uniquement pour rejoindre cette carte.

## Fichiers touchés

- `app/Support/MaintenanceGuard.php`
- `public/index.php`
- `views/errors/maintenance.php`
- `views/admin/system/maintenance_form.php`

## Vérification

- Test `MaintenanceOperationalBypassTest`
- Accueil et dossiers : page de maintenance
- `/atak`, `/connect` et les échanges en jeu : pas de page de maintenance

## Statut

Corrigé.
