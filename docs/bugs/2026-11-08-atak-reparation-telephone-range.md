# ATAK : la réparation du téléphone semble ne rien faire (il se range et ne se rouvre pas)

- **Contexte** : action ACE « Réparer le téléphone » (kit de réparation ATAK ou caisse à outils), `fn_repairStart`, puis `fn_deviceRepair`.
- **Symptôme** : le téléphone se range au début de la réparation ; à la fin, seule une notification s'affiche, le téléphone reste rangé, le téléphone porté (HUD) ne revient pas, et l'écran de démarrage attendu ne se voit jamais.
- **Cause** : avec ACE, `fn_repairStart` range le téléphone (`fn_close`, `COMSPEC_ATAK_HudWanted` à faux) pendant la barre de progression et rien ne le rouvre à la fin. `fn_deviceRepair` pose bien l'extinction de 6 s et appelle `fn_deviceOverlay`, mais celui-ci sort tout de suite sans écran ouvert : quand le joueur rouvre le téléphone plus tard, les 6 s sont passées. Le drapeau du téléphone porté restait aussi à faux.
- **Correctif** : état d'avant (ouvert, en main ou porté, HUD voulu) relevé avant de ranger et passé aux rappels de fin et d'interruption (`COMSPEC_ATAK_RepairDone`, `COMSPEC_ATAK_RepairReopen`) : le drapeau est rendu, le téléphone est rouvert comme il était, et le rendu de page affiche le redémarrage (écran noir puis écran de démarrage COMSPEC ATAK). Sans ACE, inchangé (le téléphone restait ouvert).
- **Fichiers touchés** : `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/core/fn_repairStart.sqf`.
- **Vérification** : en jeu avec ACE : abîmer le téléphone (Zeus ou choc), téléphone en main, action ACE Réparer : barre de progression, téléphone rangé, puis à la fin il se rouvre en main et montre le redémarrage ; même chose téléphone porté (HUD) ; interrompre la réparation (bouger) : téléphone rouvert, non réparé. Sans ACE : comme avant.
- **Statut** : corrigé à vérifier en jeu (ATAK 3.1.0).
