/*
    Scan Fieldwatch : émetteurs RF à portée du joueur, remontée SendRfHit.
    Params: [_unit, _sensorCallsign, _upload]
    Retour: liste de HashMap (hits locaux)
*/
params [
    ["_unit", objNull, [objNull]],
    ["_sensorCallsign", "", [""]],
    ["_upload", true, [true]]
];

if (isNull _unit) exitWith { [] };
if (_sensorCallsign isEqualTo "") then {
    _sensorCallsign = _unit getVariable ["COMSPEC_CallSign", name _unit];
};

private _list = missionNamespace getVariable ["COMSPEC_RfEmitters", []];
_list = _list select { !isNull _x && {alive _x} && {_x getVariable ["COMSPEC_RfEmitter", false]} };

private _hits = [];
private _fnc_num = { (_this select 0) toFixed (_this select 1) };
private _now = time;
private _lastUpload = _unit getVariable ["COMSPEC_RfScanUploadAt", -99];
private _canUpload = _upload && {(_now - _lastUpload) >= 2.5};

{
    private _emitter = _x;
    private _range = _emitter getVariable ["COMSPEC_RfEmitterRange", 80];
    if (!(_range isEqualType 0)) then { _range = 80; };
    private _dist = _emitter distance2D _unit;
    if (_dist > _range) then { continue };

    private _band = toLower (_emitter getVariable ["COMSPEC_RfEmitterBand", "wifi"]);
    private _label = _emitter getVariable ["COMSPEC_RfEmitterLabel", "RF"];
    private _uid = _emitter getVariable ["COMSPEC_RfEmitterUid", ""];
    private _sig = _emitter getVariable ["COMSPEC_RfEmitterSignature", ""];
    private _mac = _emitter getVariable ["COMSPEC_RfEmitterMac", ""];
    private _power = _emitter getVariable ["COMSPEC_RfEmitterPowerDbm", 10];
    if (!(_power isEqualType 0)) then { _power = 10; };

    // Atténuation simple : perte ~0.55 dB/m + bruit
    private _rssi = _power - (_dist * 0.55) - (random 4);
    _rssi = (_rssi max -110) min 20;

    private _epos = getPosATL _emitter;
    private _hit = createHashMap;
    _hit set ["uid", _uid];
    _hit set ["label", _label];
    _hit set ["band", _band];
    _hit set ["signature_id", _sig];
    _hit set ["mac", _mac];
    _hit set ["dist", _dist];
    _hit set ["signal_dbm", _rssi];
    _hit set ["pos", [_epos select 0, _epos select 1]];
    _hits pushBack _hit;

    if (_canUpload) then {
        "COMSPECExtension" callExtension ["SendRfHit", [
            _uid,
            _label,
            _band,
            _sig,
            [_epos select 0, 2] call _fnc_num,
            [_epos select 1, 2] call _fnc_num,
            [_rssi, 1] call _fnc_num,
            _sensorCallsign,
            _mac
        ]];
    };
} forEach _list;

if (_canUpload && {(count _hits) > 0}) then {
    _unit setVariable ["COMSPEC_RfScanUploadAt", _now, false];
};

_unit setVariable ["COMSPEC_RfLastHits", _hits, false];
_hits
