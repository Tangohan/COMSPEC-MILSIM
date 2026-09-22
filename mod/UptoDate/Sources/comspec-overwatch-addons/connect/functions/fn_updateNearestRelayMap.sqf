/*
    Mât Relais AT le plus proche : pastille sur la carte, emprise de portée,
    alerte en quittant la zone.
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };

private _mk = "comspec_relay_nearest";
private _ring = "comspec_relay_range";
private _info = createHashMap;
if (!isNil "comspec_overwatch_connect_fnc_getNearestAtakRelay") then {
    _info = [] call comspec_overwatch_connect_fnc_getNearestAtakRelay;
};
if (!(_info isEqualType createHashMap) || {(count (keys _info)) < 1}) exitWith {
    if (_mk in allMapMarkers) then { deleteMarkerLocal _mk; };
    if (_ring in allMapMarkers) then { deleteMarkerLocal _ring; };
    missionNamespace setVariable ["COMSPEC_RelayWasInRange", nil, false];
    false
};

private _pos = _info getOrDefault ["pos", []];
if (!(_pos isEqualType []) || {(count _pos) < 2}) exitWith {
    if (_mk in allMapMarkers) then { deleteMarkerLocal _mk; };
    if (_ring in allMapMarkers) then { deleteMarkerLocal _ring; };
    false
};
private _name = _info getOrDefault ["name", "Relais AT"];
private _range = _info getOrDefault ["range", 2000];
if (!(_range isEqualType 0) || {_range < 50}) then { _range = 2000; };
private _inRange = _info getOrDefault ["in_range", false];
private _alive = _info getOrDefault ["alive", false];
private _col = if (!_alive) then { "ColorRed" } else {
    if (_inRange) then { "ColorGreen" } else { "ColorYellow" };
};

private _muted = (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 0]) + 1;
missionNamespace setVariable ["COMSPEC_MarkerEhMuted", _muted, false];

if (!(_mk in allMapMarkers)) then {
    createMarkerLocal [_mk, _pos];
    _mk setMarkerTypeLocal "mil_box";
    _mk setMarkerSizeLocal [0.9, 0.9];
};
_mk setMarkerPosLocal _pos;
_mk setMarkerColorLocal _col;
_mk setMarkerTextLocal _name;

if (!(_ring in allMapMarkers)) then {
    createMarkerLocal [_ring, _pos];
    _ring setMarkerShapeLocal "ELLIPSE";
    _ring setMarkerBrushLocal "Border";
};
_ring setMarkerPosLocal _pos;
_ring setMarkerSizeLocal [_range, _range];
_ring setMarkerColorLocal _col;
_ring setMarkerAlphaLocal 0.55;

private _unmute = (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 1]) - 1;
if (_unmute < 0) then { _unmute = 0; };
missionNamespace setVariable ["COMSPEC_MarkerEhMuted", _unmute, false];

private _was = missionNamespace getVariable ["COMSPEC_RelayWasInRange", nil];
if (!isNil "_was" && {_was} && {!_inRange} && {_alive}) then {
    private _last = missionNamespace getVariable ["COMSPEC_RelayLeftAt", -1e9];
    if ((diag_tickTime - _last) > 20) then {
        missionNamespace setVariable ["COMSPEC_RelayLeftAt", diag_tickTime, false];
        [format ["Vous quittez la portée du relais %1.", _name], "link", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    };
};
missionNamespace setVariable ["COMSPEC_RelayWasInRange", _inRange, false];
true
