/*
    Remplit la page Paramètres (identité, rôle, carte, équipe, groupe, liaison).
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
if (!isNull _group) then {
    private _body = _group controlsGroupCtrl 9839;
    if (!isNull _body) then {
        _group = _body;
        uiNamespace setVariable ["COMSPEC_ATAK_Settings_group", _body];
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

private _cbEcoti = [9862] call _ctrl;
if (!isNull _cbEcoti) then {
    missionNamespace setVariable ["COMSPEC_AtakEcotiHudFilling", true, false];
    private _ecotiOn = missionNamespace getVariable ["comspec_overwatch_ecoti_hud", false];
    if (!(_ecotiOn isEqualType true)) then { _ecotiOn = false; };
    private _profEcoti = profileNamespace getVariable ["COMSPEC_EcotiHudEnabled", "UNSET"];
    if (_profEcoti isEqualType true) then { _ecotiOn = _profEcoti; };
    lbClear _cbEcoti;
    private _iOff = _cbEcoti lbAdd "Désactivé";
    _cbEcoti lbSetData [_iOff, "0"];
    private _iOn = _cbEcoti lbAdd "Activé sous JVN";
    _cbEcoti lbSetData [_iOn, "1"];
    _cbEcoti lbSetCurSel (if (_ecotiOn) then { _iOn } else { _iOff });
    missionNamespace setVariable ["COMSPEC_AtakEcotiHudFilling", false, false];
};

private _cbEcotiCut = [9871] call _ctrl;
if (!isNull _cbEcotiCut) then {
    missionNamespace setVariable ["COMSPEC_AtakEcotiCutFilling", true, false];
    private _cutOn = missionNamespace getVariable ["comspec_overwatch_ecoti_building_cutaway", false];
    if (!(_cutOn isEqualType true)) then { _cutOn = false; };
    private _profCut = profileNamespace getVariable ["COMSPEC_EcotiCutawayEnabled", "UNSET"];
    if (_profCut isEqualType true) then { _cutOn = _profCut; };
    lbClear _cbEcotiCut;
    private _iCutOff = _cbEcotiCut lbAdd "Désactivé";
    _cbEcotiCut lbSetData [_iCutOff, "0"];
    private _iCutOn = _cbEcotiCut lbAdd "Activé (bâtiment désigné)";
    _cbEcotiCut lbSetData [_iCutOn, "1"];
    _cbEcotiCut lbSetCurSel (if (_cutOn) then { _iCutOn } else { _iCutOff });
    missionNamespace setVariable ["COMSPEC_AtakEcotiCutFilling", false, false];
};

private _cbLinkStrip = [9882] call _ctrl;
if (!isNull _cbLinkStrip) then {
    missionNamespace setVariable ["COMSPEC_AtakLinkStripFilling", true, false];
    private _stripOn = missionNamespace getVariable ["comspec_overwatch_show_link_strip", true];
    if (!(_stripOn isEqualType true)) then { _stripOn = true; };
    private _profStrip = profileNamespace getVariable ["COMSPEC_LinkStripVisible", "UNSET"];
    if (_profStrip isEqualType true) then { _stripOn = _profStrip; };
    lbClear _cbLinkStrip;
    private _iStripOff = _cbLinkStrip lbAdd "Masquée";
    _cbLinkStrip lbSetData [_iStripOff, "0"];
    private _iStripOn = _cbLinkStrip lbAdd "Affichée";
    _cbLinkStrip lbSetData [_iStripOn, "1"];
    _cbLinkStrip lbSetCurSel (if (_stripOn) then { _iStripOn } else { _iStripOff });
    missionNamespace setVariable ["COMSPEC_AtakLinkStripFilling", false, false];
};

private _cbLinkSim = [9883] call _ctrl;
if (!isNull _cbLinkSim) then {
    missionNamespace setVariable ["COMSPEC_AtakLinkSimFilling", true, false];
    private _simOn = missionNamespace getVariable ["comspec_overwatch_link_degrade_sim", false];
    if (!(_simOn isEqualType true)) then { _simOn = false; };
    private _profSim = profileNamespace getVariable ["COMSPEC_LinkDegradeSimEnabled", "UNSET"];
    if (_profSim isEqualType true) then { _simOn = _profSim; };
    lbClear _cbLinkSim;
    private _iSimOff = _cbLinkSim lbAdd "Désactivée";
    _cbLinkSim lbSetData [_iSimOff, "0"];
    private _iSimOn = _cbLinkSim lbAdd "Activée";
    _cbLinkSim lbSetData [_iSimOn, "1"];
    _cbLinkSim lbSetCurSel (if (_simOn) then { _iSimOn } else { _iSimOff });
    missionNamespace setVariable ["COMSPEC_AtakLinkSimFilling", false, false];
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

private _cleanSecret = {
    params [["_s", ""]];
    if (!(_s isEqualType "")) then { _s = format ["%1", _s]; };
    trim _s
};

private _url = [] call comspec_overwatch_connect_fnc_portalUrl;
private _key = [missionNamespace getVariable ["comspec_overwatch_api_key", ""]] call _cleanSecret;
if ((count _key) < 8) then {
    _key = [profileNamespace getVariable ["comspec_overwatch_saved_api_key", ""]] call _cleanSecret;
};
private _tenant = [missionNamespace getVariable ["comspec_overwatch_tenant_id", ""]] call _cleanSecret;
if (_tenant isEqualTo "") then {
    _tenant = [profileNamespace getVariable ["comspec_overwatch_saved_tenant_id", ""]] call _cleanSecret;
};

private _portalEdit = [9851] call _ctrl;
if (!isNull _portalEdit) then { _portalEdit ctrlSetText _url; };

private _keyEdit = [9852] call _ctrl;
if (!isNull _keyEdit) then {
    // Ne pas réafficher la clé en clair : laisser vide si déjà mémorisée.
    if ((ctrlText _keyEdit) isEqualTo "") then {
        _keyEdit ctrlSetText "";
        if ((count _key) >= 8) then {
            _keyEdit ctrlSetTooltip format ["Clé déjà mémorisée (%1 caractères). Laissez vide pour la conserver, ou saisissez-en une nouvelle.", count _key];
        };
    };
};

private _tidEdit = [9853] call _ctrl;
if (!isNull _tidEdit) then { _tidEdit ctrlSetText _tenant; };

private _linkFb = [9855] call _ctrl;
if (!isNull _linkFb) then {
    private _tenantName = missionNamespace getVariable ["comspec_tenant_name", ""];
    if (!(_tenantName isEqualType "")) then { _tenantName = ""; };
    private _linkState = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
    if (!(_linkState isEqualType "")) then { _linkState = "offline"; };
    private _ready = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
    if (!(_ready isEqualType true)) then { _ready = false; };
    private _statusLine = if (_ready || {_linkState isEqualTo "linked"}) then {
        "<t color='#9ee0c0'>Liaison OK — préférez Appairer si vous devez reconnecter.</t>"
    } else {
        "<t color='#ffd27a'>À régler — utilisez Appairer sur le portail, puis Entrer en jeu.</t>"
    };
    private _keyNote = if ((count _key) >= 8) then { format ["Clé mémorisée (%1 car.)", count _key] } else { "Aucune clé mémorisée" };
    private _commNote = if (_tenantName isNotEqualTo "") then {
        format ["Communauté : %1", _tenantName]
    } else {
        if (_tenant isNotEqualTo "") then { format ["Identifiant saisi : %1", _tenant] } else { "Identifiant de communauté non renseigné" };
    };
    _linkFb ctrlSetStructuredText parseText format [
        "<t size='1.05'>%1<br/>%2 · %3</t>",
        _statusLine,
        _keyNote,
        _commNote
    ];
};

private _advOpen = _group getVariable ["COMSPEC_AtakLinkAdvanced", false];
{
    private _c = [_x] call _ctrl;
    if (!isNull _c) then { _c ctrlShow _advOpen; };
} forEach [9851, 9852, 9853, 9854, 9858, 9859, 9860];
private _tog = [9857] call _ctrl;
if (!isNull _tog) then {
    _tog ctrlSetText (if (_advOpen) then { "Masquer les réglages avancés" } else { "Afficher les réglages avancés" });
};
