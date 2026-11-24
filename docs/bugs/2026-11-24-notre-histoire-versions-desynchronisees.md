# Notre histoire : versions et dates désynchronisées

## Contexte
Page publique /notre-histoire (PublicReleaseHistoryService).

## Symptôme
- « Version en service » ATAK natif affichée 3.1.0 au lieu de 4.32.1 ; SSE 1.9.1 au lieu de 1.17.0 ; Overwatch 1.6.17 au lieu de 1.6.36.
- Les entrées ATAK 4.x apparaissaient datées du 2026-11-03 (en réalité du 14 au 23 novembre) et sous la 3.1.0.

## Cause
- Les entrées du journal 4.x n'avaient aucune date ; elles héritaient de l'entrée datée plus ancienne, alors que la 3.1.0 avait la date d'un bulletin (2026-11-08) et passait devant.
- La « version en service » était la plus récente par date, pas la plus haute, et ne lisait aucune source du mod.

## Correctif
- Version en service lue dans les sources (script_component.hpp, connect/config.cpp, SSE script_mod.hpp, app_version.json), repli sur la plus haute version citée.
- Date explicite `<!-- date: AAAA-MM-JJ -->` sous chaque titre d'entrée (rétro-remplie depuis l'historique Git).
- Cache : format 4 et signature étendue aux fichiers sources.

## Fichiers touchés
app/Services/Platform/PublicReleaseHistoryService.php, views/site/history.php, CHANGELOG-ATAK.md, tests/Unit/PublicHistoryVersionSyncTest.php

## Vérification
Tests PHPUnit PublicHistoryVersionSyncTest et PublicReleaseHistoryServiceTest.

## Statut
corrigé (la version du portail 1.7.0 reste celle de storage/app_version.json)
