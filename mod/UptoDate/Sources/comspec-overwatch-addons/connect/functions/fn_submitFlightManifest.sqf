/*
    Construit et envoie le manifeste de vol.
    Source de vérité = champs affichés (ce que le joueur voit / corrige).
*/
if (!hasInterface) exitWith {};

private _veh = vehicle player;
private _display = uiNamespace getVariable ["COMSPEC_FlightManifest_Display", displayNull];
if (isNull _display) exitWith {};

private _callsign = trim (ctrlText (_display displayCtrl 1501));
if (_callsign isEqualTo "" || {(toLower _callsign) in ["unknown", "inconnu"]}) then {
    _callsign = _veh getVariable ["COMSPEC_Callsign", ""];
};
if (!(_callsign isEqualType "") || {_callsign isEqualTo ""}) then {
    _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;
};
_callsign = trim _callsign;
if (_callsign isEqualTo "" || {(toLower _callsign) in ["unknown", "inconnu"]}) then {
    _callsign = trim (groupId (group player));
};
if (_callsign isEqualTo "") then { _callsign = "N-01"; };

private _model = trim (ctrlText (_display displayCtrl 1502));
if (_model isEqualTo "" || {_model find "À préciser" == 0}) then {
    if ((vehicle player) isKindOf "Air") then {
        _model = getText (configFile >> "CfgVehicles" >> typeOf _veh >> "displayName");
        if (_model isEqualTo "") then { _model = typeOf _veh; };
    } else {
        _model = "Déclaration sol";
    };
};

private _aircraftType = uiNamespace getVariable ["COMSPEC_FlightManifest_AircraftType", ""];
if (!(_aircraftType isEqualType "") || {_aircraftType isEqualTo ""}) then {
    _aircraftType = [_veh] call comspec_overwatch_connect_fnc_getAircraftType;
};
// Ne jamais envoyer le mot anglais "unknown" comme indicatif (confusion historique côté TOC)
if ((toLower _aircraftType) isEqualTo "unknown") then { _aircraftType = "ground"; };

private _freq = trim (ctrlText (_display displayCtrl 1504));
if (_freq isEqualTo "" || {_freq isEqualTo "Non détectée"}) then {
    _freq = "";
    private _radioState = player call comspec_overwatch_connect_fnc_getRadioState;
    if (_radioState != "" && {_radioState != "N/A|N/A|N/A"}) then {
        private _parts = _radioState splitString "|";
        if (count _parts >= 2) then {
            private _f = _parts select 1;
            if (_f != "" && {_f != "N/A"}) then { _freq = _f; };
        };
    };
};

private _pos = getPosASL _veh;
private _heading = getDir _veh;
private _alt = _pos select 2;

private _laser = trim (ctrlText (_display displayCtrl 1510));
if (_laser isEqualTo "") then { _laser = "1688"; };
private _auth = trim (ctrlText (_display displayCtrl 1511));
private _countStr = trim (ctrlText (_display displayCtrl 1512));
private _count = 1;
if (_countStr != "" && {parseNumber _countStr >= 1}) then { _count = round (parseNumber _countStr); };

private _sideStr = "WEST";
switch (side player) do {
    case east: { _sideStr = "EAST"; };
    case independent: { _sideStr = "GUER"; };
    case civilian: { _sideStr = "CIV"; };
    default { _sideStr = "WEST"; };
};

private _posX = _pos select 0;
private _posY = _pos select 1;
private _fuelPct = 0;
if (_veh isKindOf "AllVehicles" && {!(_veh isKindOf "Man")}) then {
    _fuelPct = (round ((fuel _veh) * 100)) max 0 min 100;
};
private _status = "AVAILABLE";

private _payload = createHashMapFromArray [
    ["mapId", 1],
    ["callsign", _callsign],
    ["call_sign", _callsign],
    ["model", _model],
    ["aircraft_type", _aircraftType],
    ["freq", _freq],
    ["radio_main", _freq],
    ["laser", _laser],
    ["auth", _auth],
    ["auth_code", _auth],
    ["alt", _alt],
    ["altitude", _alt],
    ["heading", _heading],
    ["pos", [_posX, _posY]],
    ["pos_x", _posX],
    ["pos_y", _posY],
    ["side", _sideStr],
    ["aircraft_count", _count],
    ["status", _status],
    ["fuel_pct", _fuelPct],
    ["lastUpdate", floor time],
    ["pilot", [] call comspec_overwatch_connect_fnc_getCallsign]
];

private _json = [_payload] call comspec_overwatch_connect_fnc_hashMapToJson;
if (!(_json isEqualType "") || {_json isEqualTo ""}) exitWith {
    ["Impossible de préparer le manifeste de vol.", "system", "warn"] call comspec_overwatch_connect_fnc_announce;
};

["SendFlightManifest", "attempt", _callsign, nil, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
"COMSPECExtension" callExtension ["SendFlightManifest", [_json]];
[format ["[FLIGHT MANIFEST] Envoi : %1 (%2)", _callsign, _model]] call comspec_overwatch_connect_fnc_appendLinkLog;
["Manifeste de vol transmis (file d’attente).", "system", "info"] call comspec_overwatch_connect_fnc_announce;

if (!isNull _display) then {
    _display closeDisplay 1;
} else {
    closeDialog 0;
};
