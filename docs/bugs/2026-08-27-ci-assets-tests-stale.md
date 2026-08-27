# Tests d’assets ATAK/SSE désynchronisés

## Contexte

Job CI `php` rouge sur la PR portail missions (et déjà sur `main`).

## Symptôme

8 échecs PHPUnit « asset » : versions Overwatch, libellé « Tout dégager », regex CSS cluster, fenêtre Container trop courte, seuils aéronefs, nom PBO SSE.

## Cause

Les tests figeaient d’anciennes chaînes alors que le code avait évolué (1.4.86, backoff `7 max` / `2.5 max`, `span` vs `button` pointer-events, build `comspec_sse_%%C.pbo`, etc.).

## Correctif

Réaligner les assertions sur le comportement actuel ; capitaliser « Les contacts… » dans le `title` du bouton ; élargir le bloc Container lu par le test wiring ; retirer 3 ignores PHPStan orphelins du baseline.

## Fichiers touchés

- `tests/Unit/AtakCombatJournalAssetTest.php`
- `tests/Unit/AtakSceneIngestAssetTest.php`
- `tests/Unit/AtakZenEdenAssetTest.php`
- `tests/Unit/AtakMapClearViewAssetTest.php`
- `tests/Unit/AtakMapToolbarAssetTest.php`
- `tests/Unit/AtakModReportWiringAssetTest.php`
- `tests/Unit/SseWorkshopPackAssetTest.php`
- `views/atak.php`
- `phpstan-baseline.neon`

## Vérification

`./vendor/bin/phpunit` → 456 tests, 0 failure.  
`vendor/bin/phpstan analyse` → OK.

## Statut

Corrigé
