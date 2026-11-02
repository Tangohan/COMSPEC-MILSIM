# Codes d'invitation prioritaires : la page renvoyait à la liste des candidatures

## Contexte
Rédaction de la formation Academy « ATHENA — Utiliser et administrer la plateforme » : le test qui vérifie chaque
adresse citée (`tests/Unit/AcademyAthenaCourseTest.php`) a relevé que `/back-office/recruitments/codes-invitation`
était captée par une autre route.

## Symptôme
Ouvrir **Codes d'invitation prioritaires** (recherche du back-office ou lien direct) ramenait à la liste des
candidatures, sans message.

## Cause
Dans `routes/web.php`, la route `GET /back-office/recruitments/{id}` (fiche d'une candidature) était déclarée avant
`GET /back-office/recruitments/codes-invitation`. Le routeur sert la première route qui correspond : « codes-invitation »
était lu comme un numéro de dossier, invalide, d'où la redirection vers la liste.

## Correctif
La route de la liste des codes est déclarée avant `{id}`. Les autres pages des codes (`creer`, `{id}`, `{id}/modifier`)
n'étaient pas concernées.

## Fichiers touchés
- `routes/web.php`
- `tests/Unit/AcademyAthenaCourseTest.php` (une adresse écrite telle quelle ne doit pas être captée par une route à paramètre)

## Vérification
Back-office › rechercher « codes d'invitation » : la page des codes s'ouvre.
