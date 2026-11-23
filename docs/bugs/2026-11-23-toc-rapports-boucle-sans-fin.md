# TOC web : panneau Rapports, « Maximum call stack size exceeded »

## Contexte
Poste TOC web, onglet Rapports, ouverture quand les rapports ne sont pas encore chargés.

## Symptôme
Console : `Uncaught RangeError: Maximum call stack size exceeded` dans `familyCount` (`toc-reports.js`). Le panneau reste vide.

## Cause
`renderPanel()` (toc-console.js) appelle `reports.load()` à chaque redessin tant que les rapports ne sont pas arrivés ; `load()` appelait `refresh()`, qui rappelle `renderPanel()` : récursion sans fin (5 644 appels avant le dépassement de pile, mesuré en reproduisant la boucle dans Edge).

## Correctif
Le premier chargement est verrouillé (`busy`) : un second `load()` pendant qu'il est en cours ne relance ni requête ni redessin. Le verrou est levé à la réponse et sur l'échec réseau. Reproduction : 1 appel de `renderPanel` au lieu de 5 644.

## Fichiers touchés
`public/assets/js/toc/toc-reports.js`, `tests/Unit/TocReportsLoadGuardAssetTest.php`

## Vérification
Harnais navigateur (Edge headless) avec un `api()` qui ne répond jamais : avant, `RangeError` ; après, 1 redessin. À contrôler sur le TOC en conditions réelles. La requête `GET /api/sse/v1/calques` en 403 vue dans la même console n'est pas traitée ici.

## Statut
corrigé à vérifier sur le TOC
