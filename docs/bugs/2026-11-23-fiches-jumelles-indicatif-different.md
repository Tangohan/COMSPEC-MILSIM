# Fiches jumelles : deux indicatifs différents annoncés comme identiques

## Contexte
Pilotage RH > Fiches jumelles, critère « Indicatif » coché.

## Symptôme
Un groupe « indicatif ya1 » réunit Liam Kaiser (YA1) et Damian Stones (YB1) : deux fiches aux indicatifs visibles différents sont présentées comme jumelles.

## Cause
La détection comparait l'indicatif du dossier RH (`personnel_profiles.callsign`) en priorité, alors que la liste affiche l'indicatif de la communauté (`user_community_profiles.callsign`, sinon celui du compte). Le dossier RH de Damian gardait un ancien « YA1 ».

## Correctif
`PersonnelDuplicateDetectionService::normalizedValue` compare l'indicatif affiché ; l'indicatif du dossier RH ne sert plus que de repli quand aucun n'est affiché. Test de non-régression ajouté.

## Fichiers touchés
- `app/Services/Personnel/PersonnelDuplicateDetectionService.php`
- `tests/Unit/PersonnelDuplicateDetectionServiceTest.php`

## Vérification
Test SQLite ajouté (vérifiable par la CI, pas en local). À contrôler sur le site : la page ne doit plus afficher le groupe « ya1 ». Un ancien indicatif périmé dans le dossier RH reste à corriger à la main.

## Statut
corrigé à vérifier sur le site
