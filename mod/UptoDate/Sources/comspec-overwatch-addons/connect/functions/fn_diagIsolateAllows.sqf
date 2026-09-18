/*
    True si cette brique de liaison a le droit de tourner.
    Hors dépannage : toujours true. Pendant le dépannage : seulement
    les briques déjà rallumées une par une.
*/
params [["_id", "", [""]]];
if (!(missionNamespace getVariable ["COMSPEC_DiagIsolateActive", false])) exitWith { true };
if (_id isEqualTo "") exitWith { false };
private _allow = missionNamespace getVariable ["COMSPEC_DiagIsolateAllow", []];
if (!(_allow isEqualType [])) then { _allow = []; };
_id in _allow
