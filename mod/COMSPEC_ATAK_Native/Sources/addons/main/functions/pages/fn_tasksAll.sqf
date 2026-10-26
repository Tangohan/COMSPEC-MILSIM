/*
    Ordres unifiés : GetOrders (Athena) prioritaire, complété par COMSPEC_Orders
    publié par COMSPEC Link (ordres web), et ordres émis sans Athena (fn_orderLocal). Renvoie une HashMap id -> ordre.
*/
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
private _all = createHashMap;
{ _all set [_x, _y]; } forEach (_data getOrDefault ["legacyTasks", createHashMap]);
{ _all set [_x, _y]; } forEach (_data getOrDefault ["tasks", createHashMap]);
// Ordres sans Athena (ATAK local, poste TOC : fn_orderLocal).
{ _all set [_x, _y]; } forEach (_data getOrDefault ["localTasks", createHashMap]);
// Rien reçu depuis la reconnexion au serveur : ordres sauvegardés dans le profil (fn_offline), consultables hors réseau.
if ((count _all) isEqualTo 0) then { { _all set [_x, _y]; } forEach (_data getOrDefault ["savedTasks", createHashMap]); };
_all
