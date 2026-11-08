# Réponses des IA : toast d'Arma au lieu des notifications du téléphone

- **Contexte** : demande du propriétaire.
- **Symptôme** : chaque réponse d'un groupe IA (ordre reçu, refusé, sans réponse, terminé) remplit le centre de notifications et sonne le téléphone.
- **Cause prouvée par lecture** : `fn_aiOrder` (« send » `_say`, alerte des 30 s, « recv » `_tell`) passait par `fn_notify`.
- **Supposé** : « toast d'Arma » est compris comme la notification du jeu (`BIS_fnc_showNotification`, en haut de l'écran). À corriger si le propriétaire pensait à autre chose.
- **Correctif** : `fn_aiOrder` « toast » et deux classes `CfgNotifications` (`COMSPEC_ATAK_AiReply`, `COMSPEC_ATAK_AiReplyWarn`). La ligne d'état du PC du poste reste. Les appels de feu (`fn_fireSupport`) ne sont pas des réponses d'IA : inchangés.
- **Fichiers touchés** : `functions/teams/fn_aiOrder.sqf`, `config.cpp`.
- **Vérification** : en jeu : donner un ordre à un groupe IA, téléphone en main puis rangé : toast d'Arma lisible à chaque fois, rien de neuf dans le centre de notifications. Vérifier que le toast reste visible téléphone en plein écran.
- **Statut** : corrigé à vérifier en jeu.
