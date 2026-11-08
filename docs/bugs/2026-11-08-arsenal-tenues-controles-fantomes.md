# Écran Tenues de l'Arsenal ACE : panneaux vides et textes superposés à l'ouverture

- **Contexte** : capture du propriétaire en ouvrant ATHENA · TENUES.
- **Symptôme** : grand panneau vert vide en haut à gauche, fenêtre « FERMER / VALIDER » vide au milieu, « PRIMER » sur « SÉLECTIONNER », « SOAR - BREACHER TEST » sur un texte d'invite, « Pas encore de copie locale » coupé.
- **Cause établie par lecture** : chaque élément de la capture est un contrôle créé masqué dans le groupe `COMSPEC_ArsenalOverlay` d'`arsenalOverlayShow` : le menu clic droit (en 0,0, 16 rangées), la fenêtre de saisie, le bouton SUPPRIMER (coupé en « PRIMER », à la même place que SÉLECTIONNER), l'invite du champ de nom. Ils sont visibles ensemble dès que le groupe est affiché.
- **Cause supposée, non vue en jeu** : `_g ctrlShow true` réaffiche les enfants du groupe, ce qui survient à chaque ouverture et au retour de « Mes équipements ». Le correctif n'en dépend pas : un test en jeu tranchera. Le code d'ACE ne touche pas à nos contrôles (`ace_arsenal.pbo` lu).
- **Cause du texte coupé** : deux lignes dans une case d'une hauteur de ligne, sous les boutons.
- **Correctif** : `COMSPEC_ArsenalSettle` remet chaque contrôle dans son état dès l'affichage du groupe. Fermés, le menu, la saisie et leur fond ont une surface nulle (position rendue à la demande), et `menuClose` s'applique toujours. Un rechargement encore en cours n'empêche plus de redessiner (30 s au plus). État de la liste sur toute la hauteur de l'en-tête, textes raccourcis, texte complet en info-bulle.
- **Fichiers touchés** : `connect/functions/fn_arsenalOverlayShow.sqf`, `fn_arsenalOverlayAction.sqf`, `fn_arsenalOverlayBeginLoad.sqf`, `fn_arsenalOverlayFill.sqf`, `i18n/en.txt`, `i18n/zh_cn.txt`.
- **Vérification** : `hemtt check` ; à tester en jeu (voir PR #1395).
- **Statut** : corrigé à vérifier en jeu.
