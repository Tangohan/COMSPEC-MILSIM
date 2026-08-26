# ATAK web — terminaux qui apparaissent puis disparaissent

## Contexte

Poste ATAK web. Les pastilles des opérateurs en liaison clignotent : elles se posent, disparaissent, puis reviennent.

## Symptôme

Sur la carte, les terminaux « pop then depop » : une lecture d’effectifs vide ou refusée était prise pour « plus personne en liaison ». Un opérateur absent d’une seule lecture disparaissait tout de suite.

## Cause

1. Une lecture refusée ou en pause (poste saturé / accès momentanément refusé) renvoyait une réponse vide. Le poste remplaçait toute la liste par rien, puis la rétablissait à la lecture suivante.
2. En cas d’incident côté base, la liste des effectifs pouvait revenir vide tout en paraissant réussie : même effet.
3. Un contact manquant sur une seule lecture était retiré immédiatement, sans délai de grâce.
4. Sans identifiant stable, une pastille pouvait être recréée à chaque passage (clignotement visuel).

## Correctif

- Une lecture en pause, refusée ou marquée indisponible ne touche plus à la dernière liste valide.
- Une liste vide d’erreur ne vide plus la carte : les opérateurs encore en liaison restent le temps d’un court délai (quelques lectures), puis seulement s’ils manquent vraiment.
- Identifiant de pastille stable (plus de tirage au hasard).
- Côté poste : si la lecture des effectifs échoue vraiment, on ne prétend plus que la liste est vide.

## Toast tchat (demande associée)

Un bandeau s’affiche à l’écran quand un **nouveau** message radio arrive (indicatif + texte raccourci, ou « N nouveaux messages » s’il en arrive plusieurs d’un coup). Pas de bandeau au premier chargement de l’historique, ni pour les messages que l’on vient d’envoyer. Mode silencieux : pas de son supplémentaire (le bandeau reste visible).

## Fichiers touchés

- `public/assets/js/atak-units.js`
- `public/assets/js/atak-map.js`
- `public/assets/js/atak-chat.js`
- `app/Controllers/Api/AtakApiController.php`
- `tests/Unit/AtakUnitsRosterAssetTest.php`
- `tests/Unit/AtakChatToastAssetTest.php`

## Vérification

PHPUnit : `AtakUnitsRosterAssetTest`, `AtakChatToastAssetTest`. Recette : ouvrir la carte avec des opérateurs en liaison ; une coupure momentanée ne doit plus faire clignoter les pastilles. Envoyer un message depuis un autre poste / le jeu : bandeau à l’écran, pas au simple chargement de la page.

## Statut

corrigé (sources)
