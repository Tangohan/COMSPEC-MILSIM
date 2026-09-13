/*
    Identité observée en jeu pour la fiche opérateur.
    Le Steam UID est l’identifiant de liaison. Le nom / l’indicatif ne servent jamais à associer un compte.
    Params: [_unit]
    Retour: HashMap
*/
params [["_unit", objNull, [objNull]]];

private _out = createHashMap;
_out set ["steam_uid", ""];
_out set ["arma_player_uid", ""];
_out set ["arma_player_name", ""];
_out set ["profile_name", ""];
_out set ["callsign", ""];
_out set ["first_name_detected", ""];
_out set ["last_name_detected", ""];
_out set ["display_name", ""];
_out set ["sex_detected", ""];
_out set ["rank_game", ""];
_out set ["rank_id", -1];
_out set ["role", ""];
_out set ["role_description", ""];
_out set ["group_name", ""];
_out set ["group_id", ""];
_out set ["unit_classname", ""];
_out set ["unit_display", ""];
_out set ["faction", ""];
_out set ["faction_display", ""];
_out set ["side", ""];

if (isNull _unit) exitWith { _out };

private _steam = getPlayerUID _unit;
if (!(_steam isEqualType "")) then { _steam = ""; };
_steam = trim _steam;
if ((count _steam) < 15) then {
    _steam = trim (profileNamespace getVariable ["comspec_overwatch_saved_steam_uid", ""]);
};
_out set ["steam_uid", _steam];
_out set ["arma_player_uid", _steam];
_out set ["player_uid", _steam];

private _armaName = name _unit;
if (!(_armaName isEqualType "")) then { _armaName = str _armaName; };
_armaName = trim _armaName;
_out set ["arma_player_name", _armaName];
_out set ["player_name", _armaName];

private _profName = profileName;
if (!(_profName isEqualType "")) then { _profName = ""; };
_out set ["profile_name", trim _profName];

private _cs = if (_unit isEqualTo player) then {
    [true] call comspec_overwatch_connect_fnc_getCallsign
} else {
    trim (_unit getVariable ["COMSPEC_Callsign", ""])
};
_out set ["callsign", _cs];

private _display = _armaName;
private _first = "";
private _last = "";
if (!isNil "ace_dogtags_fnc_getDogtagData") then {
    private _dog = [_unit] call ace_dogtags_fnc_getDogtagData;
    if (_dog isEqualType [] && {count _dog >= 1}) then {
        private _dogName = _dog select 0;
        if (_dogName isEqualType "") then {
            _dogName = trim _dogName;
            if (_dogName isNotEqualTo "") then { _display = _dogName; };
        };
    };
};
if (_display isNotEqualTo "") then {
    private _bits = _display splitString " ";
    if ((count _bits) >= 2) then {
        _first = _bits select 0;
        _last = (_bits select [1, (count _bits) - 1]) joinString " ";
    };
};
_out set ["display_name", _display];
_out set ["first_name_detected", _first];
_out set ["last_name_detected", _last];

private _womanCfg = configFile >> "CfgVehicles" >> typeOf _unit >> "woman";
if (isNumber _womanCfg) then {
    _out set ["sex_detected", if ((getNumber _womanCfg) == 1) then { "F" } else { "M" }];
    _out set ["sex", _out get "sex_detected"];
};

private _rk = rank _unit;
if (_rk isEqualType "") then { _out set ["rank_game", _rk]; };
private _rkId = rankId _unit;
if (_rkId isEqualType 0) then { _out set ["rank_id", round _rkId]; };

_out set ["role", [_unit] call comspec_overwatch_connect_fnc_getUnitRole];
private _roleDesc = roleDescription _unit;
if (_roleDesc isEqualType "") then {
    _roleDesc = trim _roleDesc;
    if ((_roleDesc find "@") >= 0) then {
        _roleDesc = trim ((_roleDesc splitString "@") select 0);
    };
    _out set ["role_description", _roleDesc];
};

private _grp = group _unit;
if (!isNull _grp) then {
    _out set ["group_name", trim (groupId _grp)];
    _out set ["group_id", netId _grp];
};

private _cls = typeOf _unit;
_out set ["unit_classname", _cls];
private _uDisp = getText (configOf _unit >> "displayName");
if (_uDisp isEqualTo "") then { _uDisp = _cls; };
_out set ["unit_display", _uDisp];

private _fac = faction _unit;
if (!(_fac isEqualType "")) then { _fac = ""; };
_out set ["faction", _fac];
private _facDisp = getText (configFile >> "CfgFactionClasses" >> _fac >> "displayName");
_out set ["faction_display", _facDisp];

private _side = side group _unit;
_out set ["side", switch (_side) do {
    case east: { "EAST" };
    case resistance: { "GUER" };
    case civilian: { "CIV" };
    default { "WEST" };
}];

_out
