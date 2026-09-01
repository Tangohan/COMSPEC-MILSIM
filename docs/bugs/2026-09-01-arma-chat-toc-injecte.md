# Chat Arma — messages TOC / Overwatch injectés

## Contexte

En mission, un message envoyé depuis le poste (TOC / ATAK web) apparaissait dans le chat de bord d’Arma, à gauche de l’écran. La ligne ressemblait à un indicatif radio : nom de la communauté, mention TOC, puis le texte.

## Symptôme

Exemple : `[ S.O.A.R - (The Special Operations Action Regi (TOC)] test`

Le chat latéral / global d’Arma recevait ces lignes alors que l’opérateur n’avait rien tapé dans le chat du jeu. Le téléphone et le journal ATAK affichaient aussi le message (attendu).

## Cause

Overwatch relisait les messages du poste et les recopiait dans le chat natif du jeu, sans passer par le réglage « Afficher les notifications à l’écran » (déjà désactivé par défaut). D’autres annonces Overwatch / Zeus / SEEK pouvaient aussi écrire dans ce chat.

## Correctif

Plus aucune écriture Overwatch, ATAK, TOC ou SSE dans le chat natif d’Arma. Les échanges restent dans le téléphone, les messages de groupe et le journal ATAK. Les messages que le joueur tape lui-même dans le chat du jeu ne changent pas. Le réglage des bandeaux à l’écran reste désactivé par défaut et n’alimente plus le chat.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollChatMessages.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_announce.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_receiveOrder.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_orderApplyMoveWaypoint.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_hubSelect.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_zeusAttributesAtak.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_zeusAttributesOverwatch.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_zeusAttributesSse.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_zeusShowPlayerAtak.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_sendSeekData.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf`
- `mod/@COMSPEC_SSE/addons/intel/functions/fn_trainingFeedback.sqf`
- `mod/@COMSPEC_SSE/addons/intel/functions/fn_setScenarioLevel.sqf`
- `mod/@COMSPEC_SSE/addons/core/functions/fn_log.sqf`
- `mod/@COMSPEC_SSE/addons/debug/functions/fn_log.sqf`

## Vérification

Envoyer un message test depuis le poste pendant une mission : le chat d’Arma reste vide ; le téléphone / journal ATAK affiche le fil. Tests unitaires des sources Overwatch.

## Statut

Corrigé en sources — recharger le pack jeu, puis relancer Arma complètement.
