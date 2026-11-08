/*
    Plan de briefing partagé du camp sur la carte d'Arma de chaque joueur du camp (toutes les 5 s, fn_planWeb « tick ») :
    marqueurs locaux à ce poste (fn_markerOwned, préfixe privé), refaits quand la révision du plan change, sans réseau ni
    envoi au site (le plan y va déjà par fn_planWeb) et sans rien montrer au camp adverse. 150 marqueurs au plus, 15 par image.
    La carte du téléphone saute ces marqueurs : elle dessine le plan elle-même (fn_planDraw).
*/
if (!hasInterface || {isNull player}) exitWith {};
if (isNil "COMSPEC_ATAK_PlanNativeStep") then { COMSPEC_ATAK_PlanNativeStep = {
    params ["_todo", "_cols", "_tok", "_made"];
    if (_tok isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_PlanNativeTok", -1])) exitWith {};
    {
        _x params [["_id", ""], ["_kind", ""], ["_pts", []], ["_text", ""], ["_ck", "WHITE"]];
        if ((count _pts) >= 1) then {
            private _p0 = _pts select 0;
            private _m = ["make", [0, 0, [_p0 select 0, _p0 select 1, 0]]] call comspec_atak_native_fnc_markerOwned;
            if (_m isNotEqualTo "") then {
                if ((count _pts) >= 2) then {
                    private _flat = [];
                    { _flat append [round (_x select 0), round (_x select 1)]; } forEach (_pts select [0, 200]);
                    _m setMarkerShapeLocal "POLYLINE";
                    _m setMarkerPolylineLocal _flat;
                } else {
                    _m setMarkerTypeLocal "mil_dot";
                };
                _m setMarkerTextLocal ([_text, _kind] select (_text isEqualTo ""));
                _m setMarkerColorLocal (_cols getOrDefault [_ck, "ColorWhite"]);
                _made pushBack _m;
            };
        };
    } forEach (_todo select [0, 15]);
    if ((count _todo) > 15) then {
        [{ _this call COMSPEC_ATAK_PlanNativeStep; }, [_todo select [15], _cols, _tok, _made]] call comspec_atak_native_fnc_nextFrame;
    };
}; };
private _plan = missionNamespace getVariable [format ["COMSPEC_ATAK_Plan_%1", side group player], []];
_plan params [["_rev", -1], ["_items", []]];
if ((missionNamespace getVariable ["COMSPEC_ATAK_PlanNativeRev", -2]) isEqualTo _rev) exitWith {};
missionNamespace setVariable ["COMSPEC_ATAK_PlanNativeRev", _rev];
private _tok = (missionNamespace getVariable ["COMSPEC_ATAK_PlanNativeTok", 0]) + 1;
missionNamespace setVariable ["COMSPEC_ATAK_PlanNativeTok", _tok];
{ deleteMarkerLocal _x; } forEach (missionNamespace getVariable ["COMSPEC_ATAK_PlanNativeMk", []]);
private _made = [];
missionNamespace setVariable ["COMSPEC_ATAK_PlanNativeMk", _made];
if ((count _items) isEqualTo 0) exitWith {};
private _armaCols = ([] call comspec_atak_native_fnc_planCatalog) getOrDefault ["arma", createHashMap];
[_items select [0, 150], _armaCols, _tok, _made] call COMSPEC_ATAK_PlanNativeStep;
