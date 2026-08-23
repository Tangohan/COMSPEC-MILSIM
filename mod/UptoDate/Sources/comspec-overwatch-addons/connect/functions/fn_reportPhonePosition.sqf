/*
    Remonte la position d’une personne (surtout IA) comme un contact téléphone ATAK.
    Ne doit pas réutiliser l’identité Steam du client relais.
*/
params [
    ["_unit", objNull, [objNull]]
];
if (!hasInterface) exitWith { false };
if (isNull _unit || {!alive _unit}) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { false };

private _pos = getPosWorld _unit;
if ((abs (_pos select 0) < 1) && { abs (_pos select 1) < 1 }) exitWith { false };

private _last = _unit getVariable ["COMSPEC_PhoneTrackLastAt", -1e9];
if ((diag_tickTime - _last) < 6) exitWith { false };
_unit setVariable ["COMSPEC_PhoneTrackLastAt", diag_tickTime, false];

private _fnc_num = { (_this select 0) toFixed (_this select 1) };
private _callSign = [_unit] call comspec_overwatch_connect_fnc_phoneTrackCallsign;
private _heading = getDir _unit;
private _aslZ = (getPosASL _unit) select 2;
private _side = side group _unit;
private _sideStr = switch (_side) do {
    case east: { "EAST" };
    case resistance: { "GUER" };
    case civilian: { "CIV" };
    default { "WEST" };
};
private _affiliation = switch (_side) do {
    case east: { "hostile" };
    case resistance: { "unknown" };
    case civilian: { "neutral" };
    default { "friend" };
};
private _escCs = (_callSign splitString """" joinString "");
private _groupName = trim (groupId (group _unit));
if (!(_groupName isEqualType "")) then { _groupName = ""; };
_groupName = (_groupName splitString """" joinString "");

private _extra = format [
    "{""phone_geoloc"":true,""is_ai"":%1,""source"":""phone"",""side"":""%2"",""affiliation"":""%3"",""in_vehicle"":%4}",
    if (isPlayer _unit) then { "false" } else { "true" },
    _sideStr,
    _affiliation,
    if ((vehicle _unit) isNotEqualTo _unit) then { "true" } else { "false" }
];

"COMSPECExtension" callExtension ["UpdatePosition", [
    [_pos select 0, 2] call _fnc_num,
    [_pos select 1, 2] call _fnc_num,
    [_heading, 2] call _fnc_num,
    _escCs,
    "Téléphone",
    "stable",
    "",
    "",
    "",
    _extra,
    "",
    _groupName,
    [_aslZ, 3] call _fnc_num
]];
true
