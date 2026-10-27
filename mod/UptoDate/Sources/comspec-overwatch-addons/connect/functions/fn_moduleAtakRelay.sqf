/*
    Module Zeus/Eden : pose un mât relais ATAK détruisible.
*/
private _args = if (_this isEqualType []) then { _this } else { [_this] };
private _logic = objNull;
private _activated = true;
private _a0 = _args param [0, objNull];
if (_a0 isEqualType objNull) then {
    _logic = _a0;
    _activated = _args param [2, true];
} else {
    if (_a0 isEqualType "") then {
        private _a1 = _args param [1, objNull];
        if (_a1 isEqualType objNull) then {
            _logic = _a1;
            _activated = _args param [3, true];
        } else {
            // is3DEN = 1 : BI appelle [mode, [logic, activated, curatorPlaced]], mission comprise ; on n'agit que sur "init".
            if (_a1 isEqualType [] && {(toLower _a0) isEqualTo "init"}) then {
                _logic = _a1 param [0, objNull, [objNull]];
                _activated = _a1 param [1, true, [true]];
            };
        };
    };
};
if (is3DEN) exitWith { true };
if (isNull _logic) exitWith { false };
// isGlobal = 1 : la fonction tourne sur chaque machine ; seul le serveur pose l'objet (sinon un doublon par client/JIP).
if (!isServer) exitWith { true };
if (!(_activated isEqualType true)) then { _activated = true; };
if (!_activated) exitWith { true };

private _pos = getPosATL _logic;
private _name = _logic getVariable ["RelayName", "Relais ATAK"];
if (!(_name isEqualType "") || {_name isEqualTo ""}) then { _name = "Relais ATAK"; };

// Récupérer valeurs par défaut depuis config centralisée
private _defaultRange = 2000;
private _defaultSlots = 8;
private _defaultRangeMin = 50;
private _defaultRangeMax = 8000;
// Réglage réalisme seulement s'il vient d'une fonction de la config du jeu (CfgFunctions, finale) : realism_config
// n'est pas construit, ce nom reste vide et un client pouvait le remplir par publicVariable pour faire exécuter son code.
if (isClass (configFile >> "CfgFunctions" >> "ATHENA") && {!isNil "ATHENA_fnc_getRealismParam"}) then {
    _defaultRange = ["radio_relays", "relay_range_m", 2000] call ATHENA_fnc_getRealismParam;
    _defaultSlots = ["radio_relays", "max_relay_connections", 10] call ATHENA_fnc_getRealismParam;
    _defaultRangeMin = ["radio_relays", "relay_range_min_m", 50] call ATHENA_fnc_getRealismParam;
    _defaultRangeMax = ["radio_relays", "relay_range_max_m", 8000] call ATHENA_fnc_getRealismParam;
};

private _range = _logic getVariable ["RangeM", _defaultRange];
if (!(_range isEqualType 0)) then { _range = _defaultRange; };
_range = (_range max _defaultRangeMin) min _defaultRangeMax;

private _meta = createHashMap;
_meta set ["identity", _logic getVariable ["RelayIdentity", _name]];
_meta set ["ip", _logic getVariable ["RelayIp", ""]];
_meta set ["gateway", _logic getVariable ["RelayGateway", ""]];
_meta set ["certificate", _logic getVariable ["RelayCertificate", ""]];
_meta set ["slots", _logic getVariable ["RelaySlots", _defaultSlots]];
_meta set ["power_w", _logic getVariable ["RelayPowerW", 25]];
_meta set ["throughput_mbps", _logic getVariable ["RelayThroughput", 12]];
_meta set ["reliability_pct", _logic getVariable ["RelayReliability", 92]];

[_pos, _range, _name, _meta] call comspec_overwatch_connect_fnc_placeAtakRelay;
deleteVehicle _logic;
true
