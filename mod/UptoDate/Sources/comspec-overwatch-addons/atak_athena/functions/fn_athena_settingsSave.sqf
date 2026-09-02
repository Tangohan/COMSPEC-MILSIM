/*
    Enregistre indicatif, rôle libre, affichage carte, équipe de feu et groupe.
*/
if (!hasInterface) exitWith {};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Settings_group", controlNull];
if (isNull _group) exitWith {};

private _ctrl = {
    params ["_idc"];
    private _c = _group controlsGroupCtrl _idc;
    if (isNull _c) then {
        private _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
        if (!isNull _disp) then { _c = _disp displayCtrl _idc; };
    };
    _c
};

private _edit = [9842] call _ctrl;
private _cs = if (!isNull _edit) then { trim (ctrlText _edit) } else { "" };
private _fb = [9847] call _ctrl;

if (_cs isEqualTo "") exitWith {
    if (!isNull _fb) then {
        _fb ctrlSetStructuredText parseText "<t color='#ff8a7a'>Indiquez un indicatif (ex. N-10).</t>";
    };
};

[_cs, true, "settings"] call comspec_overwatch_connect_fnc_setCallsign;

private _roleEdit = [9843] call _ctrl;
private _role = if (!isNull _roleEdit) then { trim (ctrlText _roleEdit) } else { "" };
[_role, true] call comspec_overwatch_connect_fnc_setUnitRole;

private _cbMap = [9850] call _ctrl;
if (!isNull _cbMap) then {
    private _ix = lbCurSel _cbMap;
    private _mode = if (_ix >= 0) then { _cbMap lbData _ix } else { "cs" };
    if (!(_mode in ["cs", "cs_role"])) then { _mode = "cs"; };
    missionNamespace setVariable ["COMSPEC_BftLabelMode", _mode, false];
    profileNamespace setVariable ["COMSPEC_BftLabelMode", _mode];
};

private _cbFire = [9844] call _ctrl;
private _fireData = "";
if (!isNull _cbFire) then {
    private _ix = lbCurSel _cbFire;
    if (_ix >= 0) then { _fireData = _cbFire lbData _ix; };
};

private _ftNote = "";
if (_fireData isEqualTo "") then {
    player assignTeam "MAIN";
    missionNamespace setVariable ["COMSPEC_FireTeamId", 0, false];
    profileNamespace setVariable ["COMSPEC_AssignedTeam", "MAIN"];
    profileNamespace setVariable ["COMSPEC_FireTeamId", 0];
    _ftNote = "sans équipe de feu";
} else {
    if ((["RED", "GREEN", "BLUE", "YELLOW"] find _fireData) >= 0) then {
        player assignTeam _fireData;
        missionNamespace setVariable ["COMSPEC_FireTeamId", 0, false];
        profileNamespace setVariable ["COMSPEC_AssignedTeam", _fireData];
        profileNamespace setVariable ["COMSPEC_FireTeamId", 0];
        private _lab = switch (_fireData) do {
            case "RED": { "rouge" };
            case "GREEN": { "verte" };
            case "BLUE": { "bleue" };
            default { "jaune" };
        };
        _ftNote = format ["équipe %1", _lab];
    } else {
        if ((_fireData select [0, 3]) isEqualTo "FT:") then {
            private _tid = parseNumber (_fireData select [3, 12]);
            if (_tid > 0) then {
                missionNamespace setVariable ["COMSPEC_FireTeamId", _tid, false];
                profileNamespace setVariable ["COMSPEC_FireTeamId", _tid];
                private _roleApi = "member";
                if (((toLower _role) find "chef") >= 0 || {((toLower _role) find "leader") >= 0}) then {
                    _roleApi = "leader";
                };
                private _raw = ["COMSPECExtension" callExtension ["JoinFireTeam", [str _tid, _cs, _roleApi]]] call comspec_overwatch_connect_fnc_extResult;
                private _okJoin = false;
                if (_raw isEqualType "") then {
                    private _u = toUpper _raw;
                    _okJoin = ((_u select [0, 2]) isEqualTo "OK") || {(_u find "[""OK") == 0};
                    if (!_okJoin && {(count _raw) >= 4 && {(_raw select [0, 2]) isEqualTo "["""}}) then {
                        private _parsed = parseSimpleArray _raw;
                        if (_parsed isEqualType [] && {(count _parsed) >= 1}) then {
                            _okJoin = (toUpper (str (_parsed select 0))) find "OK" >= 0;
                        };
                    };
                };
                private _ftColor = "";
                {
                    if ((count _x) >= 3 && {(_x select 0) isEqualTo _tid}) exitWith {
                        _ftColor = toUpper (trim str (_x select 2));
                    };
                } forEach (missionNamespace getVariable ["COMSPEC_FireTeams", []]);
                if (_ftColor in ["RED", "GREEN", "BLUE", "YELLOW"]) then {
                    player assignTeam _ftColor;
                    profileNamespace setVariable ["COMSPEC_AssignedTeam", _ftColor];
                };
                if (_okJoin) then {
                    _ftNote = "équipe Athena rattachée";
                    0 spawn { uiSleep 0.4; [] call comspec_overwatch_connect_fnc_getFireTeams; };
                } else {
                    _ftNote = "équipe mémorisée (liaison poste à confirmer)";
                };
            };
        };
    };
};

private _cbGrp = [9845] call _ctrl;
private _grpNote = "";
if (!isNull _cbGrp) then {
    private _ix = lbCurSel _cbGrp;
    private _nid = if (_ix >= 0) then { _cbGrp lbData _ix } else { "" };
    if (_nid isNotEqualTo "") then {
        private _tgt = groupFromNetId _nid;
        if (!isNull _tgt && {_tgt isNotEqualTo (group player)}) then {
            [player] joinSilent _tgt;
            _grpNote = format ["rejoint %1", groupId _tgt];
        };
    };
};

[] call comspec_overwatch_atak_athena_fnc_athena_phoneProximitySave;
saveProfileNamespace;
[] call comspec_overwatch_connect_fnc_syncPlayerAtakPublicVars;
missionNamespace setVariable ["COMSPEC_lastRole", "", false];
missionNamespace setVariable ["COMSPEC_lastGroup", "", false];
missionNamespace setVariable ["COMSPEC_lastName", "", false];
if (!isNil "comspec_overwatch_connect_fnc_updatePosition") then {
    [player, true] call comspec_overwatch_connect_fnc_updatePosition;
};
if (!isNil "comspec_overwatch_atak_athena_fnc_athena_relabelBft") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_relabelBft;
};

private _msg = format ["Identité enregistrée : %1", _cs];
if (_role isNotEqualTo "") then { _msg = _msg + format [" · %1", _role]; };
if (_ftNote isNotEqualTo "") then { _msg = _msg + format [" · %1", _ftNote]; };
if (_grpNote isNotEqualTo "") then { _msg = _msg + format [" · %1", _grpNote]; };

if (!isNull _fb) then {
    _fb ctrlSetStructuredText parseText format ["<t color='#9ee0c0'>%1</t>", _msg];
};
["COMSPEC_Info", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
[] call comspec_overwatch_atak_athena_fnc_athena_updateSettings;
