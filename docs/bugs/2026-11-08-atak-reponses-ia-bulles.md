# Réponses des IA : une bulle au-dessus de la tête, le toast en secours

- **Contexte** : le propriétaire voulait « vraiment un texte au-dessus de la tête ». La note `2026-11-08-atak-reponses-ia-toast.md` supposait le toast d'Arma : supposition fausse pour le rendu principal.
- **Symptôme** : la réponse d'un groupe IA restait en haut de l'écran, sans lien avec celui qui parle.
- **Cause** : `fn_aiOrder` « recv » ne connaissait que `BIS_fnc_showNotification`.
- **Correctif** : nouveau `fn_aiBubble`. À chaque changement d'état (en cours, terminé, impossible, annulé), tout joueur du camp à portée (50 m, réglable) et en ligne de vue voit la bulle au-dessus du chef du groupe. Le donneur d'ordre garde le toast seulement si la bulle n'est pas vue ou si elle est décochée. Fondu, durée de 3,5 à 12 s selon la longueur, 160 caractères, 4 lignes coupées aux espaces, une bulle par IA, 6 au plus, sans chevauchement (la plus lointaine monte). Refus du serveur, alerte des 30 s et « ordre envoyé » : toast (aucune IA ne parle).
- **Coût** : aucun gestionnaire Draw3D sans bulle (posé par la première, retiré par la dernière). Au pire 6 bulles : 30 `drawIcon3D` et 6 `drawLine3D` par image, 24 rayons de ligne de vue par seconde. Non mesuré en jeu.
- **Fichiers touchés** : `functions/teams/fn_aiBubble.sqf`, `functions/teams/fn_aiOrder.sqf`, `config.cpp`, `XEH_preInit.sqf`, `i18n/en.txt`, `i18n/zh_cn.txt`.
- **Vérification** : `hemtt check` ; à tester en jeu (voir PR #1395). Taille et hauteur des textes estimées sans le jeu.
- **Statut** : corrigé à vérifier en jeu.
