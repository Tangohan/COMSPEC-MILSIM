/*
    Message différé du moteur (résultat de laboratoire) affiché à l'opérateur.
    [_title, _lines] call comspec_sse_fnc_bioNotify
*/
params [["_title", ""], ["_lines", []]];
if (!hasInterface) exitWith {};
private _txt = format ["%1 : %2", _title, _lines joinString " "];
if (!isNil "comspec_overwatch_connect_fnc_announce") then {
    [_txt, "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
};
hint ((parseText format ["<t size='1.1' font='PuristaMedium'>%1</t><br/>%2", _title, _lines joinString "<br/>"]) call comspec_sse_fnc_tr);
playSound "FD_Finish_F";
