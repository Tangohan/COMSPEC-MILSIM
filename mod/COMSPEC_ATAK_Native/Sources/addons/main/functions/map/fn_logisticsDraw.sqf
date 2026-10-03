/*
    Carte : points de livraison des demandes logistiques encore ouvertes (app Logistique).
    Appelé depuis fn_mapOnDraw. Params : [contrôle carte]
*/
params ["_map"];
if (isNull _map) exitWith {};
private _reqs = missionNamespace getVariable ["COMSPEC_ATAK_LogiReqs", createHashMap];
if ((count _reqs) isEqualTo 0) exitWith {};
private _icon = getText (configFile >> "CfgMarkers" >> "mil_pickup" >> "icon");
if (_icon isEqualTo "") then { _icon = "\A3\ui_f\data\map\markers\military\pickup_CA.paa"; };
private _labels = ctrlMapScale _map < 0.3;
{
    private _st = _y getOrDefault ["status", ""];
    if (_st in ["DEMANDEE", "VALIDEE", "EN_ROUTE"]) then {
        private _p = _y getOrDefault ["pos", []];
        if ((count _p) >= 2) then {
            private _c = switch (_y getOrDefault ["prio", ""]) do { case "URGENT": { [0.9, 0.28, 0.23, 1] }; case "PRIORITY": { [0.95, 0.67, 0.20, 1] }; default { [0.36, 0.78, 0.42, 1] }; };
            if (_st isEqualTo "DEMANDEE") then { _c set [3, 0.7]; };
            _map drawIcon [_icon, _c, [_p select 0, _p select 1, 0], 22, 22, 0,
                if (_labels) then { format ["%1 %2", _y getOrDefault ["id", ""], _y getOrDefault ["cs", ""]] } else { "" },
                1, 0.024, "RobotoCondensed", "right"];
        };
    };
} forEach _reqs;
