/*
    Ordres unifiés : GetOrders (Athena) prioritaire, complété par COMSPEC_Orders
    publié par COMSPEC Link (ordres web). Renvoie une HashMap id -> ordre.
*/
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
private _all = createHashMap;
{ _all set [_x, _y]; } forEach (_data getOrDefault ["legacyTasks", createHashMap]);
{ _all set [_x, _y]; } forEach (_data getOrDefault ["tasks", createHashMap]);
_all
