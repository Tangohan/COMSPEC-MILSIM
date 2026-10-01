/*
    Pose un émetteur RF Fieldwatch (objet vanilla + registre mission).
    Params: [_pos, _range, _label, _meta]
      _meta HashMap : band, signature_id, power_dbm, mac, kind
*/
params [
    ["_pos", [0, 0, 0], [[]], 3],
    ["_range", 80, [0]],
    ["_label", "RF Emitter", [""]],
    "_meta"
];

if ((count _pos) < 2) exitWith { objNull };
_range = (_range max 10) min 500;
if (_label isEqualTo "") then { _label = "RF Emitter"; };
if (isNil "_meta" || {!(_meta isEqualType createHashMap)}) then { _meta = createHashMap; };

private _band = toLower (_meta getOrDefault ["band", "wifi"]);
if (!(_band isEqualType "") || {_band isEqualTo ""}) then { _band = "wifi"; };

private _classname = switch (_band) do {
    case "ble";
    case "tracker": { "Land_MobilePhone_smart_F" };
    case "camera": { "Land_HandyCam_F" };
    case "phone": { "Land_MobilePhone_old_F" };
    default { "Land_Laptop_unfolded_F" };
};

private _obj = createVehicle [_classname, _pos, [], 0, "CAN_COLLIDE"];
if (isNull _obj) then {
    _obj = createVehicle ["Land_HelipadEmpty_F", _pos, [], 0, "CAN_COLLIDE"];
};
if (isNull _obj) exitWith { objNull };
_obj setPosATL _pos;

private _uid = _meta getOrDefault ["uid", ""];
if (!(_uid isEqualType "") || {_uid isEqualTo ""}) then {
    _uid = format ["rf_%1_%2_%3", _band, round (_pos select 0), round random 9999];
};
private _sig = _meta getOrDefault ["signature_id", ""];
if (!(_sig isEqualType "") || {_sig isEqualTo ""}) then {
    _sig = format ["%1_generic", _band];
};
private _power = _meta getOrDefault ["power_dbm", 10];
if (!(_power isEqualType 0)) then { _power = 10; };
private _mac = _meta getOrDefault ["mac", ""];
if (!(_mac isEqualType "")) then { _mac = ""; };
if (_mac isEqualTo "") then {
    private _h = abs floor ((_pos select 0) * 13 + (_pos select 1) * 7 + random 255);
    _mac = format [
        "%1:%2:%3:%4:%5:%6",
        ["A0", "B2", "C4", "D6", "E8", "FA"] select ((_h) mod 6),
        ["11", "22", "33", "44", "55", "66"] select ((_h / 6) mod 6),
        ["0A", "1B", "2C", "3D", "4E", "5F"] select ((_h / 36) mod 6),
        str ((floor (_h / 2)) mod 90 + 10),
        str ((floor (_h / 3)) mod 90 + 10),
        str ((floor (_h / 5)) mod 90 + 10)
    ];
};

_obj setVariable ["COMSPEC_RfEmitter", true, true];
_obj setVariable ["COMSPEC_RfEmitterUid", _uid, true];
_obj setVariable ["COMSPEC_RfEmitterLabel", _label, true];
_obj setVariable ["COMSPEC_RfEmitterBand", _band, true];
_obj setVariable ["COMSPEC_RfEmitterSignature", _sig, true];
_obj setVariable ["COMSPEC_RfEmitterRange", _range, true];
_obj setVariable ["COMSPEC_RfEmitterPowerDbm", _power, true];
_obj setVariable ["COMSPEC_RfEmitterMac", _mac, true];

private _list = missionNamespace getVariable ["COMSPEC_RfEmitters", []];
_list = _list select { !isNull _x };
_list pushBackUnique _obj;
missionNamespace setVariable ["COMSPEC_RfEmitters", _list, true];

_obj addEventHandler ["Killed", {
    params ["_obj"];
    private _list = missionNamespace getVariable ["COMSPEC_RfEmitters", []];
    _list = _list select { !isNull _x && {_x isNotEqualTo _obj} };
    missionNamespace setVariable ["COMSPEC_RfEmitters", _list, true];
}];
_obj addEventHandler ["Deleted", {
    params ["_obj"];
    private _list = missionNamespace getVariable ["COMSPEC_RfEmitters", []];
    _list = _list select { !isNull _x && {_x isNotEqualTo _obj} };
    missionNamespace setVariable ["COMSPEC_RfEmitters", _list, true];
}];

_obj
