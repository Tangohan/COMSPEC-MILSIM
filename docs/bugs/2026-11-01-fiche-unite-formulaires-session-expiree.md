# Fiche unité : « Session expirée » à l’enregistrement

## Contexte
Fiche unité du back-office (`/back-office/organisation/unites/{id}`) : formulaires d’identité de l’unité et du journal d’activité.

## Symptôme
L’enregistrement renvoyait vers la structure avec « Session expirée. Réessayez. », même avec une session valide.

## Cause
Les formulaires envoyaient le jeton de sécurité sous le nom `_csrf`. Le filtre global l’accepte, mais le contrôleur (`Csrf::verify()`) ne lit que `_csrf_token` : la vérification du contrôleur échouait toujours.

## Correctif
Les champs cachés de la fiche unité s’appellent désormais `_csrf_token` (y compris le nouveau formulaire des postes).

## Fichiers touchés
- `views/admin/organization/unit_sheet.php`

## Vérification
Lecture du code (`Csrf::verify` vs `CsrfPostMiddleware::hasValidToken`) ; rendu de la fiche vérifié. À tester en ligne : enregistrer l’identité d’une unité, une activité et les postes.

## Statut
Corrigé (à confirmer en ligne).
