/*
    Remplit la page Paramètres (identité, rôle libre, carte, équipe, groupe).
*/
if (!hasInterface) exitWith {};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Settings_group", controlNull];
if (isNull _group) then {
    private _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    if (!isNull _disp) then {
        private _probe = _disp displayCtrl 9841;
        if (!isNull _probe) then {
            _group = ctrlParentControlsGroup _probe;
            if (!isNull _group) then {
                uiNamespace setVariable ["COMSPEC_ATAK_Settings_group", _group];
            };
        };
    };
};
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

private _cs = [true] call comspec_overwatch_connect_fnc_getCallsign;
private _role = [player] call comspec_overwatch_connect_fnc_getUnitRole;
private _atakId = trim (missionNamespace getVariable ["COMSPEC_AtakId", ""]);
if (_atakId isEqualTo "") then {
    _atakId = trim (missionNamespace getVariable ["COMSPEC_MilitaryId", ""]);
};
if (_atakId isEqualTo "") then {
    _atakId = trim (profileNamespace getVariable ["COMSPEC_MilitaryId", ""]);
};
private _terminal = "";
if (!isNil "comspec_overwatch_connect_fnc_getTerminalUid") then {
    _terminal = [] call comspec_overwatch_connect_fnc_getTerminalUid;
};
private _idLine = if (_atakId isEqualTo "") then { "non attribué" } else { _atakId };
if (_idLine isNotEqualTo "non attribué" && {!([_idLine] call comspec_overwatch_connect_fnc_isUsableCallsign)} && {(count _idLine) > 24}) then {
    _idLine = "non attribué";
};
private _termLine = if (_terminal isEqualTo "") then { "—" } else { _terminal };
private _gid = [player] call comspec_overwatch_connect_fnc_inGameGroupLabel;
if (_gid isEqualTo "") then { _gid = "groupe actuel"; };
private _teamColor = assignedTeam player;
private _teamFr = switch (toUpper _teamColor) do {
    case "RED": { "Rouge" };
    case "GREEN": { "Vert" };
    case "BLUE": { "Bleu" };
    case "YELLOW": { "Jaune" };
    default { "Aucune" };
};

private _sum = [9841] call _ctrl;
if (!isNull _sum) then {
    _sum ctrlSetStructuredText parseText format [
        "<t size='0.92'><t color='#8FBEA8'>Identifiant ATAK</t>  %1<br/><t color='#8FBEA8'>Terminal</t>  %2<br/><t color='#8FBEA8'>Groupe actuel</t>  %3 · équipe %4</t>",
        _idLine,
        _termLine,
        _gid,
        _teamFr
    ];
};

private _edit = [9842] call _ctrl;
if (!isNull _edit) then { _edit ctrlSetText _cs; };

private _roleEdit = [9843] call _ctrl;
if (!isNull _roleEdit) then {
    private _shown = _role;
    if ((toLower _shown) isEqualTo "operator") then { _shown = ""; };
    _roleEdit ctrlSetText _shown;
};

private _cbMap = [9850] call _ctrl;
if (!isNull _cbMap) then {
    missionNamespace setVariable ["COMSPEC_AtakBftLabelFilling", true, false];
    private _mode = missionNamespace getVariable ["COMSPEC_BftLabelMode", ""];
    if (_mode isEqualTo "") then {
        _mode = profileNamespace getVariable ["COMSPEC_BftLabelMode", "cs"];
    };
    if (!(_mode isEqualType "") || {!(_mode in ["cs", "cs_role"])}) then { _mode = "cs"; };
    lbClear _cbMap;
    private _mapOpts = [
        ["cs", "Indicatif seul"],
        ["cs_role", "Indicatif et rôle"]
    ];
    private _selM = 0;
    {
        _x params ["_code", "_label"];
        private _i = _cbMap lbAdd _label;
        _cbMap lbSetData [_i, _code];
        if (_code isEqualTo _mode) then { _selM = _i; };
    } forEach _mapOpts;
    _cbMap lbSetCurSel _selM;
    missionNamespace setVariable ["COMSPEC_AtakBftLabelFilling", false, false];
};

private _cbFire = [9844] call _ctrl;
if (!isNull _cbFire) then {
    lbClear _cbFire;
    private _curFt = missionNamespace getVariable ["COMSPEC_FireTeamId", 0];
    if (!(_curFt isEqualType 0)) then { _curFt = parseNumber str _curFt; };
    private _selF = 0;

    private _i0 = _cbFire lbAdd "Sans équipe de feu";
    _cbFire lbSetData [_i0, ""];

    {
        _x params ["_code", "_label"];
        private _i = _cbFire lbAdd _label;
        _cbFire lbSetData [_i, _code];
        if ((toUpper _teamColor) isEqualTo _code && {_curFt < 1}) then { _selF = _i; };
    } forEach [
        ["RED", "Équipe rouge"],
        ["GREEN", "Équipe verte"],
        ["BLUE", "Équipe bleue"],
        ["YELLOW", "Équipe jaune"]
    ];

    private _teams = missionNamespace getVariable ["COMSPEC_FireTeams", []];
    private _csLow = toLower _cs;
    {
        if ((count _x) < 2) then { continue };
        private _tid = _x select 0;
        private _label = _x select 1;
        private _i = _cbFire lbAdd format ["Athena — %1", _label];
        _cbFire lbSetData [_i, format ["FT:%1", _tid]];
        if (_tid isEqualTo _curFt && {_curFt > 0}) then { _selF = _i; };
        if (_selF isEqualTo 0 && {(count _x) >= 6}) then {
            {
                _x params [["_mCs", ""]];
                if ((toLower (trim _mCs)) isEqualTo _csLow) then { _selF = _i; };
            } forEach (_x select 5);
        };
    } forEach _teams;
    _cbFire lbSetCurSel _selF;
};

private _cbGrp = [9845] call _ctrl;
if (!isNull _cbGrp) then {
    lbClear _cbGrp;
    private _myGrp = group player;
    private _stayLabel = if (_gid isEqualTo "groupe actuel") then {
        "Rester dans le groupe actuel"
    } else {
        format ["Rester dans %1", _gid]
    };
    private _iStay = _cbGrp lbAdd _stayLabel;
    _cbGrp lbSetData [_iStay, ""];
    private _selG = 0;
    private _side = side _myGrp;
    {
        private _g = _x;
        if (_g isEqualTo _myGrp) then { continue };
        if (side _g isNotEqualTo _side) then { continue };
        private _units = units _g select { isPlayer _x || {alive _x} };
        if ((count _units) < 1) then { continue };
        private _lead = leader _g;
        if (isNull _lead) then { _lead = _units select 0; };
        private _nameG = [_lead] call comspec_overwatch_connect_fnc_inGameGroupLabel;
        if (_nameG isEqualTo "") then { continue };
        private _nid = netId _g;
        if (_nid isEqualTo "") then { continue };
        private _i = _cbGrp lbAdd format ["%1 (%2)", _nameG, count _units];
        _cbGrp lbSetData [_i, _nid];
    } forEach allGroups;
    _cbGrp lbSetCurSel 0;
};

private _cbProx = [9849] call _ctrl;
if (!isNull _cbProx) then {
    missionNamespace setVariable ["COMSPEC_AtakPhoneProxFilling", true, false];
    private _presets = [
        [0, "Désactivée"],
        [50, "50 mètres"],
        [100, "100 mètres"],
        [200, "200 mètres"],
        [500, "500 mètres"],
        [1000, "1 kilomètre"],
        [2000, "2 kilomètres"]
    ];
    private _cur = missionNamespace getVariable ["COMSPEC_AtakPhoneProximityM", 200];
    if (!(_cur isEqualType 0)) then { _cur = 200; };
    lbClear _cbProx;
    private _selP = 3;
    {
        _x params ["_meters", "_label"];
        private _i = _cbProx lbAdd _label;
        _cbProx lbSetData [_i, str _meters];
        if (_meters isEqualTo _cur) then { _selP = _i; };
    } forEach _presets;
    _cbProx lbSetCurSel _selP;
    missionNamespace setVariable ["COMSPEC_AtakPhoneProxFilling", false, false];
};

private _fb = [9847] call _ctrl;
if (!isNull _fb && {ctrlText _fb isEqualTo ""}) then {
    private _hint = if (_cs isEqualTo "") then {
        "Indiquez votre indicatif (ex. YB1). Le rôle se saisit librement. Ne mettez pas le nom de la communauté dans l’indicatif."
    } else {
        "Indicatif, rôle (texte libre), affichage sur la carte, équipe de feu et groupe. Enregistrez pour appliquer."
    };
    _fb ctrlSetStructuredText parseText format ["<t size='0.9'>%1</t>", _hint];
};
