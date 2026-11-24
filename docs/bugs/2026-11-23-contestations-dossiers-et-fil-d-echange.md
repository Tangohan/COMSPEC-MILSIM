# Contestations : référence de dossier, fil d'échange et vue de modération

## Contexte
Demande de TTRD après l'audit du bannissement : pas de référence unique par contestation, pas de vraie page d'échange, pas de notification ni de vue correcte côté administration.

## Symptôme
Une contestation n'avait qu'un texte et une réponse, aucun numéro à citer, aucun échange possible, aucun marqueur « non lu » ni file filtrable pour la modération.

## Cause
Table platform_ban_appeals limitée à une déclaration et une réponse ; aucune notion de statut, d'assignation ni de fil.

## Correctif
Étape 5080 : référence CTS-AA-NNNN (colonne unique, compteur par année incrémenté par une instruction verrouillante), statut, assignation, non lu, fil de messages avec notes internes ; rattrapage des dossiers existants. Page de suivi avec fil, statuts et réponse limitée en débit. File de modération (administration et communauté dans son périmètre), page de dossier, export CSV, carte « À traiter ». Journal d'audit avec la référence. E-mails sans contenu.

## Fichiers touchés
database/steps/5080_platform_ban_appeal_cases.php, PlatformBanAppealRepository, PlatformBanAppealCaseService, BanAppealCaseController, PlatformBanAppealController, vues admin et auth, CSS, lang, tests/Unit/PlatformBanAppealCasesTest.php.

## Vérification
Mini exécuteur avec SQLite : PlatformBanAppealCasesTest, PlatformBanAuditFixesTest, PlatformBanAppealWorkflowTest. Captures du fil, de la file et du dossier.

## Statut
corrigé (migration 5080 à passer en production)
