# GPS simulé : le point bouge et sort du cercle de précision alors que le joueur est immobile

- **Contexte** : retour du propriétaire sur la carte du téléphone (ATAK 3.1.0).
- **Symptôme** : joueur à l'arrêt, le point GPS se déplace et sort de son cercle de précision.
- **Cause établie par lecture du code** : `fn_gpsFix` (recalcul toutes les 2 s) retire au hasard l'erreur de base et fait dériver le décalage à chaque calcul, même sans déplacement. Le rayon annoncé change donc à chaque calcul alors que le décalage précédent est gardé.
- **Correctif** : joueur immobile (moins de 2 m, même ciel, même réglage), l'erreur et le décalage sont gardés tels quels. Les décrochages de signal, les sauts de position (fiabilité) et le calcul du ciel (bâtiment, arbres, relief, véhicule) ne changent pas.
- **Fichiers touchés** : `fn_gpsFix.sqf`.
- **Vérification** : à tester en jeu, voir la PR (point fixe à l'arrêt, nouveau tirage après 2 m de marche).
- **Statut** : corrigé à vérifier en jeu (ATAK 3.1.0).
