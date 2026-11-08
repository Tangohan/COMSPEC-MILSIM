# Carte : deux joueurs côte à côte, un seul nom affiché

- **Contexte** : retour du propriétaire, carte du téléphone.
- **Symptôme** : deux joueurs proches n'affichent qu'un nom ; on ne sait pas qui est le second.
- **Cause établie par lecture du code** : cartouches (`fn_mapLabels`) : les unités à moins d'un cartouche l'une de l'autre sont regroupées sous le premier, avec seulement « +N ». Indicatif simple (`fn_mapOnDraw`) : un seul indicatif par case de 1/14 de la vue.
- **Correctif** : cartouche regroupé de deux ou trois unités aux noms courts : les noms sont écrits (« STAR 4-1 · STAR 4-2 »), « +N » au-delà. Indicatif simple : trois indicatifs au plus par case, empilés l'un sous l'autre.
- **Fichiers touchés** : `fn_mapLabels.sqf`, `fn_mapOnDraw.sqf`.
- **Vérification** : à tester en jeu, deux puis quatre joueurs côte à côte, carte plein écran et téléphone porté ; la hauteur de ligne de l'empilement est estimée (non mesurée en jeu).
- **Statut** : corrigé à vérifier en jeu (ATAK 3.1.0).
