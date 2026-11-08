# Carte : rechargement visuel régulier quand un joueur est sélectionné

- **Contexte** : retour du propriétaire, carte du téléphone, fiche SITUATION d'une unité cliquée.
- **Symptôme** : tant qu'un joueur est sélectionné, la carte se recharge à intervalle régulier.
- **Cause probable, non confirmée en jeu** : `fn_inspectorUpdate` (appelé chaque seconde) redessine toute la page carte (au plus toutes les 2 s) dès que la hauteur du texte diffère de celle du volet. Si le volet ne peut pas atteindre cette hauteur (fiche d'unité plus longue que le résumé), l'écart reste après le redessin et le redessin recommence indéfiniment.
- **Correctif** : la hauteur de texte déjà traitée est gardée ; un nouveau redessin n'a lieu que si cette hauteur change vraiment (ligne en plus ou en moins). Remise à zéro au changement d'unité sélectionnée.
- **Fichiers touchés** : `fn_inspectorUpdate.sqf`.
- **Vérification** : à tester en jeu, sélectionner un joueur et regarder 30 s sans toucher ; puis monter en véhicule (ligne en plus) : un seul redessin.
- **Statut** : corrigé à vérifier en jeu (ATAK 3.1.0).
