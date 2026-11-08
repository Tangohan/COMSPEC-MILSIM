# Haut-parleur de l'app Musique : boucle oui/non, téléphone et Arsenal inaccessibles

- **Contexte** : retours d'un joueur et d'un serveur partenaire, avec le bouton HAUT-PARLEUR du lecteur.
- **Symptôme** : le réglage alterne entre oui et non, l'app se dérègle ; d'après le retour partenaire, le téléphone et l'Arsenal ACE ne répondent plus ensuite.
- **Cause établie** : `call _sonNow` sans argument transmet `_this` (`["speaker", ""]`) ; `_sliders` vaut alors `"speaker"` et `if (_sliders)` déclenche une erreur de script.
- **Cause probable, non confirmée en jeu** : le clic passe par `CBA_fnc_execNextFrame`. L'erreur interromprait la file d'image suivante avant son vidage : l'action serait rejouée à chaque image (oui/non) et les actions suivantes, dont celles d'ACE, n'iraient plus au bout.
- **Correctif** : appel `[true] call _sonNow`. `fn_formSet` note aussi la valeur du curseur avant de le déplacer, pour ne pas relancer son action.
- **Fichiers touchés** : `fn_musicAction.sqf`, `fn_formSet.sqf`.
- **Vérification** : à tester en jeu, avec `-showScriptErrors` : lancer un morceau, régler le volume du haut-parleur, activer puis couper le haut-parleur, puis ouvrir le téléphone et l'Arsenal ACE.
- **Statut** : corrigé à vérifier en jeu (ATAK 3.1.0).
