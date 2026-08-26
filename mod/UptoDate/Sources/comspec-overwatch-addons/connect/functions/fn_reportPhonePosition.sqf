/*
    Remonte la position d’une personne (surtout IA) comme un contact téléphone ATAK.
    Ne doit pas réutiliser l’identité Steam du client relais.
    Les champs détaillés ne sont publiés que si Zeus les a cochés (COMSPEC_PhoneReveal).
*/
params [
    ["_unit", objNull, [objNull]]
];
if (!hasInterface) exitWith { false };
if (isNull _unit || {!alive _unit}) exitWith { false };
if (
    !isNil "comspec_overwatch_connect_fnc_shouldSkipEnemyAiTransmit"
    && { [_unit] call comspec_overwatch_connect_fnc_shouldSkipEnemyAiTransmit }
) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { false };

private _pos = getPosWorld _unit;
if ((abs (_pos select 0) < 1) && { abs (_pos select 1) < 1 }) exitWith { false };

private _last = _unit getVariable ["COMSPEC_PhoneTrackLastAt", -1e9];
if ((diag_tickTime - _last) < 6) exitWith { false };
_unit setVariable ["COMSPEC_PhoneTrackLastAt", diag_tickTime, false];

private _fnc_num = { (_this select 0) toFixed (_this select 1) };
private _jb = {
    params ["_v"];
    if (_v) then { "true" } else { "false" }
};

private _callSign = [_unit] call comspec_overwatch_connect_fnc_phoneTrackCallsign;
private _showHdg = [_unit, "heading"] call comspec_overwatch_connect_fnc_phoneRevealHas;
private _showAlt = [_unit, "altitude"] call comspec_overwatch_connect_fnc_phoneRevealHas;
private _showId = [_unit, "identity"] call comspec_overwatch_connect_fnc_phoneRevealHas;
private _showAff = [_unit, "affiliation"] call comspec_overwatch_connect_fnc_phoneRevealHas;
private _showVeh = [_unit, "vehicle"] call comspec_overwatch_connect_fnc_phoneRevealHas;
private _showGrid = [_unit, "grid"] call comspec_overwatch_connect_fnc_phoneRevealHas;
private _showUpd = [_unit, "updated"] call comspec_overwatch_connect_fnc_phoneRevealHas;

private _headingStr = if (_showHdg) then { [getDir _unit, 2] call _fnc_num } else { "" };
private _aslStr = if (_showAlt) then { [(getPosASL _unit) select 2, 3] call _fnc_num } else { "" };

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
private _groupName = "";
if (_showId) then {
    _groupName = trim (groupId (group _unit));
    if (!(_groupName isEqualType "")) then { _groupName = "" };
    _groupName = (_groupName splitString """" joinString "");
};

private _parts = [
    format [
        """phone_geoloc"":true,""is_ai"":%1,""source"":""phone""",
        if (isPlayer _unit) then { "false" } else { "true" }
    ],
    format [
        """reveal"":{""identity"":%1,""grid"":%2,""altitude"":%3,""heading"":%4,""updated"":%5,""affiliation"":%6,""vehicle"":%7}",
        [_showId] call _jb,
        [_showGrid] call _jb,
        [_showAlt] call _jb,
        [_showHdg] call _jb,
        [_showUpd] call _jb,
        [_showAff] call _jb,
        [_showVeh] call _jb
    ]
];
if (_showId) then {
    _parts pushBack format ["""display_name"":""%1""", _escCs];
};
if (_showAff) then {
    _parts pushBack format ["""side"":""%1"",""affiliation"":""%2""", _sideStr, _affiliation];
};
if (_showVeh) then {
    _parts pushBack format [
        """in_vehicle"":%1",
        if ((vehicle _unit) isNotEqualTo _unit) then { "true" } else { "false" }
    ];
};
private _extra = "{" + (_parts joinString ",") + "}";

"COMSPECExtension" callExtension ["UpdatePosition", [
    [_pos select 0, 2] call _fnc_num,
    [_pos select 1, 2] call _fnc_num,
    _headingStr,
    _escCs,
    "Téléphone",
    "",
    "",
    "",
    "",
    _extra,
    "",
    _groupName,
    _aslStr
]];
true
