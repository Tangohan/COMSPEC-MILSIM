# App Routes : clic gauche perdu après une route, apps superposées ensuite

- **Contexte** : retour du propriétaire après la pose d'une route dans l'app Routes.
- **Symptôme** : clic gauche impossible à refaire ; ensuite, les apps se superposent et ne s'ouvrent plus.
- **Cause prouvée par lecture** : `fn_routeMap` jouait le clic dans `MouseButtonDown`, et un clic sur une étape appelait `#refresh`, qui supprimait aussitôt (`ctrlDelete`) la fiche et les boutons de la barre du mode. Or la carte reçoit aussi les clics faits sur les contrôles posés dessus (constat de `fn_planUi`) : TERMINER ou ANNULER pouvait être supprimé sous la souris. La suppression d'un contrôle pendant son clic fait perdre la capture de la souris au moteur (cas constatés : app Drone 25/10, menu de la carte 2026-11-02). Le même chemin posait aussi une étape parasite sous la barre. Enfin la carte de l'app gardait le focus après un clic, alors qu'Arma dessine le contrôle focalisé devant les autres ; Plan et Carte le rendent, pas Routes.
- **Supposé** : ces défauts expliquent le clic perdu et la superposition. Non reproduit en jeu.
- **Correctif** : clic gauche armé à l'appui, joué au relâchement sans glisser et à l'image suivante ; clic droit joué à l'image suivante ; zone de la barre bloquée pour la carte ; fiche et barre masquées puis supprimées à l'image suivante ; focus rendu au bouton hors écran ; mode de saisie remis à CHOISIR en quittant l'app.
- **Fichiers touchés** : `functions/route/fn_routeMap.sqf`, `functions/route/fn_pageRoute.sqf`, `functions/ui/fn_navigate.sqf`.
- **Vérification** : en jeu : créer une route, poser 4 étapes, cliquer une étape, TERMINER, ANNULER, clic droit, Échap, glisser la carte (aucune étape posée), VALIDER ; cliquer encore sur la carte et le dock, ouvrir 3 apps : aucun reste de Routes, tout répond.
- **Statut** : corrigé à vérifier en jeu.
