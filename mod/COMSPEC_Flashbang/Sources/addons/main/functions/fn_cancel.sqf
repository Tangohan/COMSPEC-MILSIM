/*
    comspec_fb_fnc_cancel : annule les détonations restantes d'une grenade sur cette machine (engin supprimé).
    [_id] call comspec_fb_fnc_cancel
*/
params [["_id", "", [""]]];
private _ctx = comspec_fb_events getOrDefault [_id, createHashMap];
if (count _ctx == 0) exitWith {};
_ctx set ["cancelled", true];
["cancel", format ["%1 annulée après %2 détonation(s)", _id, _ctx get "done"]] call comspec_fb_fnc_log;
