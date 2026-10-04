/*
    Dessin d'une image du rejeu sur la carte de l'app AAR (événement Draw).
    Traces : positions des 6 images précédentes. Pertes amies jusqu'à l'instant affiché (COMSPEC_ATAK_AarEvents).
    Params : [contrôle carte]
*/
params ["_map"];
private _log = missionNamespace getVariable ["COMSPEC_ATAK_Aar", []];
if ((count _log) isEqualTo 0) exitWith {};
private _p = uiNamespace getVariable ["COMSPEC_ATAK_AarPlay", createHashMap];
private _i = (floor (_p getOrDefault ["idx", 0])) min ((count _log) - 1);
(_log select _i) params ["_t", "", "_units"];
// Vue gardée d'un rendu à l'autre (la page se redessine à chaque bouton).
private _cp = ctrlPosition _map;
uiNamespace setVariable ["COMSPEC_ATAK_AarView", [ctrlMapScale _map, _map ctrlMapScreenToWorld [(_cp select 0) + (_cp select 2) / 2, (_cp select 1) + (_cp select 3) / 2]]];
private _palette = createHashMapFromArray [["BLUE",[0.28,0.70,1,1]],["CYAN",[0.20,0.90,0.95,1]],["GREEN",[0.36,0.85,0.42,1]],["WHITE",[0.95,0.95,0.95,1]],["YELLOW",[1,0.85,0.15,1]],["ORANGE",[1,0.55,0.15,1]],["PINK",[1,0.45,0.75,1]]];
private _ally = _palette getOrDefault [profileNamespace getVariable ["COMSPEC_ATAK_AllyColor","BLUE"],[0.28,0.70,1,1]];
private _self = _palette getOrDefault [profileNamespace getVariable ["COMSPEC_ATAK_SelfColor","CYAN"],[0.20,0.90,0.95,1]];
private _labels = (ctrlMapScale _map) < 0.12;
if (_p getOrDefault ["trails", true]) then {
    private _prev = _log select [(_i - 6) max 0, (_i - ((_i - 6) max 0)) + 1];
    private _tracks = createHashMap;
    { { private _k = _x select 3; private _l = _tracks getOrDefault [_k, []]; _l pushBack [_x select 0, _x select 1]; _tracks set [_k, _l]; } forEach (_x select 2); } forEach _prev;
    {
        private _c = [[_ally select 0, _ally select 1, _ally select 2, 0.45], [_self select 0, _self select 1, _self select 2, 0.7]] select (_x isEqualTo (name player));
        for "_j" from 0 to ((count _y) - 2) do { _map drawLine [_y select _j, _y select (_j + 1), _c]; };
    } forEach _tracks;
};
{
    _x params ["_x0", "_y0", "_dir", "_name", "_grp", "_me", "_veh"];
    private _icon = ["\A3\ui_f\data\map\vehicleicons\iconMan_ca.paa", "\A3\ui_f\data\map\vehicleicons\iconCar_ca.paa"] select _veh;
    _map drawIcon [_icon, [_ally, _self] select _me, [_x0, _y0], [20, 26] select _me, [20, 26] select _me, _dir, ["", format ["%1 (%2)", _name, _grp]] select (_labels || _me), 1, 0.03, "RobotoCondensed", "right"];
} forEach _units;
{
    _x params ["_et", "_ex", "_ey", "_en"];
    if (_et <= _t) then { _map drawIcon ["\A3\ui_f\data\map\markers\military\destroy_CA.paa", [0.9, 0.2, 0.2, 0.9], [_ex, _ey], 18, 18, 0, ["", _en] select _labels, 1, 0.028, "RobotoCondensed", "right"]; };
} forEach (missionNamespace getVariable ["COMSPEC_ATAK_AarEvents", []]);
