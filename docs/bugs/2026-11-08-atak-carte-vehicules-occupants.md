# Carte : joueur en véhicule ou en aéronef sans icône d'engin ni liste des occupants

- **Contexte** : retour du propriétaire, carte du téléphone et fiche SITUATION.
- **Symptôme** : un joueur dans un véhicule, un avion ou un hélico gardait l'icône d'un fantassin.
- **Cause établie par lecture du code** : `fn_localDataRefresh` classait la piste d'après l'objet suivi (le joueur, `CAManBase`), jamais d'après son engin.
- **Correctif** : champ de piste `vtype` (air, char, véhicule) quand le joueur est à bord ; `fn_symbology` s'en sert pour l'icône. `type` reste « infantry » : le filtre « masquer les véhicules » du BFT ne cache donc pas l'équipage. Fiche SITUATION : ligne « Occupants » (indicatif et poste, 8 au plus).
- **Fichiers touchés** : `fn_localDataRefresh.sqf`, `fn_symbology.sqf`, `fn_mapOnDraw.sqf`, `fn_inspectorUpdate.sqf`, `i18n/en.txt`.
- **Vérification** : à tester en jeu, monter en voiture, en char, en hélico : icône d'engin sur la carte ; cliquer l'unité : occupants listés. Le mode d'icônes OTAN (déjà adapté aux engins) est inchangé.
- **Statut** : corrigé à vérifier en jeu (ATAK 3.1.0).
