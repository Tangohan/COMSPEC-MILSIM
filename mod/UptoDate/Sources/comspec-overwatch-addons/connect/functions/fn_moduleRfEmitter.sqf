/*
    Module Zeus/Eden : pose un émetteur RF Fieldwatch.
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
private _label = _logic getVariable ["EmitterLabel", "IPCam-Lobby"];
if (!(_label isEqualType "") || {_label isEqualTo ""}) then { _label = "IPCam-Lobby"; };

private _range = _logic getVariable ["RangeM", 80];
if (!(_range isEqualType 0)) then { _range = 80; };
_range = (_range max 10) min 500;

private _meta = createHashMap;
_meta set ["band", _logic getVariable ["EmitterBand", "wifi"]];
_meta set ["signature_id", _logic getVariable ["EmitterSignature", "wifi_ipcam"]];
_meta set ["power_dbm", _logic getVariable ["PowerDbm", 10]];
_meta set ["mac", _logic getVariable ["EmitterMac", ""]];

[_pos, _range, _label, _meta] call comspec_overwatch_connect_fnc_placeRfEmitter;
deleteVehicle _logic;
true
