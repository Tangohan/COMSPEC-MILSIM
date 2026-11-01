# ORBAT : erreur 500 dès qu’un enrichissement échoue

## Contexte
La page `/orbat` et `/api/orbat/roster` construisent l’arbre avec `OrbatRosterPayload::buildForTenant()`, qui enchaîne une dizaine de requêtes annexes (effectifs, chefs, membres, confidentialité, disponibilité, unités du lecteur, grades).

## Symptôme
Erreur 500 sur l’ORBAT entier. Cas observé : `g.short_name` inexistant dans `grades` après la bascule vers le référentiel (corrigé par la PR #1255 en `label_short`).

## Cause
- Aucune étape d’enrichissement n’était protégée : une seule colonne absente dans une table annexe (`grades`, `user_certifications`, `community_event_rsvps`…) faisait tomber toute la page.
- `gradeShortNamesForUsers()` ne lisait qu’un nom de colonne ; une base restée sur l’ancien schéma (`short_name`) aurait de nouveau planté.
- `UnitRepository::getTree()` bouclait à l’infini si une unité était rattachée à elle-même (ou A → B → A), et faisait disparaître une unité dont le parent avait été supprimé.

## Correctif
- `OrbatRosterPayload::attempt()` : chaque enrichissement est exécuté à part ; en cas d’erreur il est consigné (`[orbat] … indisponible`) et remplacé par une valeur vide. L’arbre s’affiche toujours.
- Grades : colonne détectée (`label_short`, sinon `short_name`, sinon pas de grade).
- `getTree()` : boucle de rattachement coupée, unité orpheline remontée au sommet.
- ORBAT : un rechargement raté (aperçu « voir comme ») affiche désormais un message au lieu d’échouer en silence.

## Fichiers touchés
`app/Support/OrbatRosterPayload.php`, `app/Repositories/UnitRepository.php`, `public/assets/js/orbat-app.js`, `lang/{fr,en}/orbat.php`, `tests/Unit/OrbatRosterResilienceTest.php`.

## Vérification
`tests/Unit/OrbatRosterResilienceTest.php` : boucle / parent supprimé, toutes les requêtes annexes en échec, grade sur l’ancien et le nouveau schéma (les tests du grade et de l’arbre échouent sans le correctif).

## Statut
Corrigé.
