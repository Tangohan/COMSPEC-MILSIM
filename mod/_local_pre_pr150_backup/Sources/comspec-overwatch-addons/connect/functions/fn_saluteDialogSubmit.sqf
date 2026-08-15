/*
    Envoie un SALUTE structuré depuis le dialog (champs S/A/L/U/T/E).
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_Salute_Display", displayNull];
if (isNull _disp) exitWith {};

private _s = ctrlText (_disp displayCtrl 9401);
private _a = ctrlText (_disp displayCtrl 9402);
private _l = ctrlText (_disp displayCtrl 9403);
private _u = ctrlText (_disp displayCtrl 9404);
private _t = ctrlText (_disp displayCtrl 9405);
private _e = ctrlText (_disp displayCtrl 9406);

private _parts = [];
{ if ((trim _x) isNotEqualTo "") then { _parts pushBack _x; }; } forEach [
    format ["S=%1", trim _s],
    format ["A=%1", trim _a],
    format ["L=%1", trim _l],
    format ["U=%1", trim _u],
    format ["T=%1", trim _t],
    format ["E=%1", trim _e]
];

if ((count _parts) < 1) exitWith {
    ["Renseignez au moins un champ SALUTE.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
};

private _body = _parts joinString "|";
["SALUTE", _body, getPos player] call comspec_overwatch_connect_fnc_sendTacticalAlert;
closeDialog 0;
