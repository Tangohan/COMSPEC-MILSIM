# Ordres aux IA jamais exécutés (ATAK 2.9.26 à 2.9.28)

## Contexte
Retour joueur du 02/11 : « Les ordres aux IA ne fonctionnent pas avec notre ATAK ». RPT client 2.9.28 (mission `__cur_mp`, `isServer=false` : joueur sur un serveur hébergé), aucune ligne liée aux ordres.

## Symptôme
Clic droit sur la carte du téléphone › ORDRE AUX IA › Aller ici (ou PC TOC › GROUPES › ORDRE IA) : bandeau « ordre transmis », puis plus rien. Le groupe ne bouge pas, aucun état (en cours, terminé, impossible) ne revient, rien dans le RPT du joueur.

## Cause
1. `functions/teams/fn_aiOrder.sqf` (2.9.26) : `[_g] call BIS_fnc_netId` et `[_nid] call BIS_fnc_groupFromNetId`. Les deux fonctions BIS vérifient le type de `_this` (`paramsCheck` : groupe / objet pour `BIS_fnc_netId`, chaîne pour `BIS_fnc_groupFromNetId`, lu dans `functions_f.pbo`) ; un tableau est refusé et la fonction sort sans valeur. Le serveur ne retrouvait donc jamais le groupe : erreur sur la machine du serveur seulement (absente du RPT du joueur), aucune réponse. Mêmes appels dans `fn_mapCtxMenu.sqf` (sous-menu, état du groupe) et `fn_tocLaptop.sqf:500` (fiche du groupe). Tous les autres appels du mod passent la valeur nue.
2. App Ordres du téléphone (`fn_pageOrderCompose`) : aucun groupe IA dans la liste des destinataires, et avec Athena connecté (`fn_orderAction`) l'ordre partait à Athena, jamais au groupe IA ; la grille saisie n'était pas transmise comme point (DÉPLACER refusé faute de point).
3. Refus muets ou trompeurs : « ordre transmis » affiché même sans réseau (ordre en file) ; serveur sans le mod ou d'une version antérieure : rien ; groupe protégé / avec un joueur / d'un autre camp : un seul message vague ; pas de ligne de menu quand aucun groupe IA n'est commandable.
4. Groupes figés : simulation dynamique d'Eden (gelé loin des joueurs) ou IA sans déplacement (`PATH` / `MOVE` coupés) : l'ordre « en cours » sans mouvement.

## Correctif
- `fn_aiOrder` : helpers `_gid` / `_gOf` (valeur nue), mode `"netId"` pour le menu et le PC ; nouveau mode `"why"` (motif lisible : serveur sans le mod à jour, pas le droit, aucun groupe IA, groupe disparu, autre camp, avec un joueur, protégé, drone) utilisé au clic et avant l'envoi ; motifs détaillés aussi côté serveur, avec l'id de l'ordre ; attente de réponse 30 s (« pas de réponse du serveur ») sauf ordre en file sans réseau ; exécution : simulation dynamique coupée et `PATH`/`MOVE` rétablis pour les ordres de déplacement ; groupe changé de machine : le donneur est prévenu.
- Traces RPT `[DEBUG][AIORDER]` de bout en bout (demande, envoi, réception serveur, refus, exécution, états, réponse reçue) quand le réglage Overwatch « Niveau de journal » est sur Détaillé.
- `XEH_preInit` : gestionnaires serveur `aiOrdSrv` / `aiOrdState` et `COMSPEC_ATAK_SrvAiOrd` branchés dès le preInit (`isServer`), `COMSPEC_ATAK_SrvAiOrdV` public pour que le client sache si le serveur traite les ordres.
- `fn_mapCtxMenu` : ligne ORDRE AUX IA « indisponible » quand le joueur est chef sans groupe IA ou voit des groupes IA sans en avoir le droit ; un clic affiche le motif.
- App Ordres : groupes IA du camp (« IA · Alpha 1-2 ») dans les destinataires ; ordre à un groupe IA toujours traité sans Athena, au point de la grille ; types sans équivalent (appui aérien, FRAGO, DEM-SSE) signalés.

## Fichiers touchés
`mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/teams/fn_aiOrder.sqf`, `functions/map/fn_mapCtxMenu.sqf`, `functions/toc/fn_tocLaptop.sqf`, `functions/pages/fn_orderLocal.sqf`, `functions/pages/fn_orderAction.sqf`, `functions/pages/fn_pageOrderCompose.sqf`, `XEH_preInit.sqf`, `XEH_postInitServer.sqf`.

## Vérification
`hemtt check` (seulement les 2 erreurs connues `ctrlWebBrowserAction`). À tester en jeu : voir CHANGELOG-ATAK « Pack 2.9.29 › À tester en jeu ».

## Statut
Corrigé dans 2.9.29, à valider en jeu (Eden solo et serveur).
