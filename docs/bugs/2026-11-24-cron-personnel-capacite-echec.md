# Cron personnel_capability_refresh en échec

## Contexte
Tâche « Currency & capacité opérationnelle » (toutes les 60 min) : « 4 membre(s) recalculé(s), 16 non-current, 12 erreur(s). »

## Symptôme
Statut Échec dans l'écran Cron ; le résumé ne disait pas pourquoi (messages seulement dans les détails).

## Cause
Cause la plus probable (déduite du code, non confirmée sur la base de production) : `OperationalCapabilityService::snapshotForUser` reprenait `personnel_profiles.operator_status` (texte libre : Actif, Disponible, En formation, Réserve, Indisponible…) en majuscules comme `availability`, alors que la colonne `personnel_operational_capability.availability` est un ENUM de 7 valeurs. Toute valeur hors ENUM fait échouer l'INSERT en SQL strict (« Data truncated »), membre par membre. Les « non-current » n'étaient pas des erreurs.

## Correctif
- `normalizeAvailability()` : mappe le texte libre (accents, français) vers l'ENUM, jamais de valeur hors ENUM.
- Résumé du job : qualifications non-current marquées « (info) », première cause (classe d'exception + message tronqué, valeurs masquées) ajoutée si erreurs.

## Fichiers touchés
app/Services/Personnel/OperationalCapabilityService.php, app/Services/Cron/Jobs/PersonnelCapabilityCronJob.php, tests/Unit/PersonnelCapabilityCronJobTest.php

## Vérification
Tests unitaires (mini runner). À confirmer en prod : relancer la tâche depuis l'écran Cron, 0 erreur attendue ; sinon le résumé affiche désormais la vraie cause.

## Statut
corrigé à vérifier en production
