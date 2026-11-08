# App Routes : nom automatique des étapes faux ou en double

- **Contexte** : retour du propriétaire, app Routes.
- **Symptôme** : noms automatiques incorrects (numéro décalé, doublons).
- **Cause établie par lecture du code** : `_autoName` (`fn_routeAction`) comptait les étapes du même type + 1. Après une suppression au milieu, deux étapes recevaient le même nom. Au changement de type d'une étape (`stepSet`), le type était posé avant le calcul : l'étape se comptait elle-même (« CP 2 » au lieu de « CP 1 », « SP 2 » au lieu de « SP »).
- **Correctif** : le nom prend le plus petit numéro non utilisé par les autres étapes (SP et FIN sans numéro tant qu'ils sont libres) ; l'étape renommée est exclue du relevé.
- **Fichiers touchés** : `fn_routeAction.sqf`.
- **Vérification** : à tester en jeu, poser SP, WP, WP, WP, supprimer le deuxième WP, en poser un : « WP 2 » (pas de doublon) ; passer un WP en CP : « CP 1 ».
- **Non traité** : « clic gauche impossible à refaire après une route » et « apps qui se superposent et ne s'ouvrent plus » : cause non trouvée par lecture (voir la PR).
- **Statut** : corrigé à vérifier en jeu (ATAK 3.1.0).
