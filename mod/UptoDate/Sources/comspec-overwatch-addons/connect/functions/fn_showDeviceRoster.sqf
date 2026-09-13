/*
    Peuple la vue "Effectifs" de la tablette (idc 9314) avec la liste des unités en liaison
    (callsign + rôle + référence de grille). Best effort — silencieux en cas d'échec, ne doit jamais
    gêner le reste du dialog. Appelé en spawn depuis fn_deviceToggleView.sqf.

    Params (optionnel) : [_display] — défaut : recherche du dialog tablette (idd 9973).
*/
params [["_display", displayNull]];
if (isNull _display) then { _display = findDisplay 9973; };
if (isNull _display) exitWith {};

private _rows = [] call comspec_overwatch_connect_fnc_getUnitsList;
missionNamespace setVariable ["COMSPEC_ReachCache", _rows, false];

if (isNull _display) exitWith {}; // le joueur a pu fermer le dialog pendant la requête réseau
private _listCtrl = _display displayCtrl 9314;
if (isNull _listCtrl) exitWith {};

private _recent = [];
{
    _x params ["_callsign", "_gx", "_gy", ["_isSelf", false], ["_wx", 0], ["_wy", 0], ["_role", ""], ["_ageSec", 0], ["_status", "linked"], ["_stamp", diag_tickTime]];
    private _age = _ageSec + (diag_tickTime - _stamp);
    private _st = toLower _status;
    if (_isSelf || {_st in ["linked", "delayed"]} || {_age <= 900}) then {
        _recent pushBack _x;
    };
} forEach _rows;

missionNamespace setVariable ["COMSPEC_DeviceRosterRows", _recent, false];

if (ctrlType _listCtrl == 5) then {
    lbClear _listCtrl;
    if (count _recent == 0) exitWith {
        _listCtrl lbAdd "Aucun contact récent";
    };
    {
        if (_forEachIndex >= 12) exitWith {};
        _x params ["_callsign", "_gx", "_gy", ["_isSelf", false], ["_wx", 0], ["_wy", 0], ["_role", ""], ["_ageSec", 0], ["_status", "linked"]];
        private _roleTxt = if (_role isEqualTo "") then { "Opérateur" } else { _role };
        private _st = toLower _status;
        private _link = if (_st isEqualTo "offline") then { "hors liaison" } else { "en liaison" };
        private _you = if (_isSelf) then { " (vous)" } else { "" };
        private _idx = _listCtrl lbAdd format ["%1%2  %3", _callsign, _you, _link];
        _listCtrl lbSetTooltip [_idx, format ["%1 · %2 %3", _roleTxt, round _gx, round _gy]];
    } forEach _recent;
} else {
    if (count _recent == 0) exitWith {
        _listCtrl ctrlSetStructuredText parseText "<t size='0.5' color='#5a6c7e'>Aucun contact vu dans les quinze dernières minutes.</t>";
    };
    private _maxRows = 8;
    private _lines = [];
    {
        if (_forEachIndex >= _maxRows) exitWith {};
        _x params ["_callsign", "_gx", "_gy", ["_isSelf", false], ["_wx", 0], ["_wy", 0], ["_role", ""], ["_ageSec", 0], ["_status", "linked"]];
        private _roleTxt = if (_role isEqualTo "") then { "Opérateur" } else { _role };
        private _you = if (_isSelf) then { " <t color='#2dd4a8'>(vous)</t>" } else { "" };
        private _st = toLower _status;
        private _linkCol = if (_st isEqualTo "offline") then { "#94a3b8" } else { "#5a9e88" };
        _lines pushBack format [
            "<t size='0.46' color='#d0dce8'>%1%2</t><br/><t size='0.40' color='#8aa0b4'>%3</t>  <t size='0.44' color='%6'>%4 %5</t>",
            _callsign, _you, _roleTxt, round _gx, round _gy, _linkCol
        ];
    } forEach _recent;
    if (count _recent > _maxRows) then {
        _lines pushBack format ["<t size='0.42' color='#5a6c7e'>+%1 autre(s)</t>", (count _recent) - _maxRows];
    };
    _listCtrl ctrlSetStructuredText parseText (_lines joinString "<br/>");
};
