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

if (isNull _display) exitWith {}; // le joueur a pu fermer le dialog pendant la requête réseau
private _listCtrl = _display displayCtrl 9314;
if (isNull _listCtrl) exitWith {};

if (count _rows == 0) exitWith {
    _listCtrl ctrlSetStructuredText parseText "<t size='0.5' color='#5a6c7e'>Aucun contact en liaison.</t>";
};

// Limite d'affichage : l'écran de la tablette n'a la place que pour quelques lignes lisibles.
private _maxRows = 8;
private _lines = [];
{
    if (_forEachIndex >= _maxRows) exitWith {};
    _x params ["_callsign", "_gx", "_gy", ["_isSelf", false], ["_wx", 0], ["_wy", 0], ["_role", ""]];
    private _roleTxt = if (_role isEqualTo "") then { "Operator" } else { _role };
    private _you = if (_isSelf) then { " <t color='#2dd4a8'>(vous)</t>" } else { "" };
    _lines pushBack format [
        "<t size='0.46' color='#d0dce8'>%1%2</t><br/><t size='0.40' color='#8aa0b4'>%3</t>  <t size='0.44' color='#5a9e88'>%4 %5</t>",
        _callsign, _you, _roleTxt, round _gx, round _gy
    ];
} forEach _rows;
if (count _rows > _maxRows) then {
    _lines pushBack format ["<t size='0.42' color='#5a6c7e'>+%1 autre(s)</t>", (count _rows) - _maxRows];
};

_listCtrl ctrlSetStructuredText parseText (_lines joinString "<br/>");
