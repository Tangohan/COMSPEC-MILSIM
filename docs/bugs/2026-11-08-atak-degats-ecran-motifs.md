# Dégâts d'écran : variété de fêlures et de bris

- **Contexte** : demande du propriétaire : plus de variété, désactivable, à faible coût.
- **Symptôme** : un seul dessin procédural (étoile) sur trois textures par niveau.
- **Correctif** : huit motifs procéduraux tirés au premier choc : étoile, coin écrasé, balle, fissure traversante, toile, gravillons, verre en éclats, rayures. Un deuxième motif s'ajoute au niveau 3 et sur écran détruit. Réglages › Fêlures détaillées (`COMSPEC_ATAK_CrackFx`) les retire et garde les textures simples.
- **Coût** : aucun calcul à l'image. Un contrôle fixe par trait, créé une fois par choc ou changement d'orientation, plafonné à 40, 70, 100 ou 130 selon les dégâts ; même boucle d'affichage par seconde qu'avant. Dessin non mesuré en jeu.
- **Non fait** : aucune nouvelle texture PBO (les huit motifs ne demandent aucun fichier).
- **Fichiers touchés** : `functions/ui/fn_deviceOverlay.sqf`, `functions/pages/fn_pageSettings.sqf`, `i18n/en.txt`.
- **Vérification** : en jeu : abîmer le téléphone à plusieurs reprises (réparer entre deux) pour voir les motifs ; niveaux 1, 2, 3 et détruit ; portrait et paysage ; Réglages › Fêlures détaillées : marche et arrêt ; pas de clic bloqué ; surveiller les images par seconde, écran très abîmé.
- **Statut** : corrigé à vérifier en jeu.
