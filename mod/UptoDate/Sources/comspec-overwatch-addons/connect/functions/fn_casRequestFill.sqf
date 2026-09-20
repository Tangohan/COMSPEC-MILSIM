/*
    Remplit type, grille et notes du formulaire d'appui.
    Params optionnel : position monde pour la grille.
*/
params [["_world", []]];
if (!hasInterface) exitWith {};

if (!(_world isEqualType []) || {(count _world) < 2}) then {
    _world = missionNamespace getVariable ["COMSPEC_CasPrefillPos", []];
};
if (!(_world isEqualType []) || {(count _world) < 2}) then { _world = getPos player; };

private _grid = [9702] call comspec_overwatch_connect_fnc_casRequestCtrl;
if (!isNull _grid) then {
    _grid ctrlSetText format ["Grille %1", mapGridPosition _world];
};
private _notes = [9703] call comspec_overwatch_connect_fnc_casRequestCtrl;
if (!isNull _notes) then { _notes ctrlSetText ""; };

private _combo = [9701] call comspec_overwatch_connect_fnc_casRequestCtrl;
if (!isNull _combo) then {
    lbClear _combo;
    {
        _x params ["_label", "_code"];
        private _i = _combo lbAdd _label;
        _combo lbSetData [_i, _code];
    } forEach [
        ["Appui immédiat (danger proche)", "CAS"],
        ["Reconnaissance aérienne", "RECON_AIR"],
        ["Couverture / survol", "COVER"],
        ["Extraction aérienne", "EXTRACT"]
    ];
    _combo lbSetCurSel 0;
};

missionNamespace setVariable ["COMSPEC_CasPrefillPos", [], false];
