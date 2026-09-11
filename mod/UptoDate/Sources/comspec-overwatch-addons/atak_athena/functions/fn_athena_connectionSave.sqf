/*
    Enregistre adresse du portail, clé d’accès et identifiant de communauté
    depuis Paramètres ATAK (Rsc natif) vers CBA + profil, puis reconnecte.
*/
if (!hasInterface) exitWith {};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Settings_group", controlNull];
if (isNull _group) then {
    private _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    if (!isNull _disp) then {
        private _probe = _disp displayCtrl 9851;
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

private _cleanSecret = {
    params [["_s", ""]];
    if (!(_s isEqualType "")) then { _s = format ["%1", _s]; };
    _s = trim _s;
    for "_i" from 1 to 2 do {
        private _len = count _s;
        if (_len < 2) exitWith {};
        private _a = _s select [0, 1];
        private _b = _s select [_len - 1, 1];
        if ((_a isEqualTo """" && _b isEqualTo """") || {_a isEqualTo "'" && _b isEqualTo "'"}) then {
            _s = trim (_s select [1, _len - 2]);
        };
    };
    _s
};

private _fb = [9855] call _ctrl;
private _setFb = {
    params ["_text", ["_warn", false]];
    if (isNull _fb) exitWith {};
    private _col = if (_warn) then { "#ff8a7a" } else { "#9ee0c0" };
    _fb ctrlSetStructuredText parseText format ["<t color='%1'>%2</t>", _col, _text];
};

private _urlCtrl = [9851] call _ctrl;
private _keyCtrl = [9852] call _ctrl;
private _tidCtrl = [9853] call _ctrl;

private _url = if (!isNull _urlCtrl) then { [ctrlText _urlCtrl] call _cleanSecret } else { "" };
private _key = if (!isNull _keyCtrl) then { [ctrlText _keyCtrl] call _cleanSecret } else { "" };
private _tenant = if (!isNull _tidCtrl) then { [ctrlText _tidCtrl] call _cleanSecret } else { "" };

if (_url isEqualTo "") then {
    _url = "https://athena.ttrd.fr/public";
};

private _urlLow = toLower _url;
if (!(((_urlLow find "https://") == 0) || {(_urlLow find "http://") == 0}) || {(count _url) < 12}) exitWith {
    ["Adresse du portail invalide. Exemple : https://athena.ttrd.fr/public", true] call _setFb;
};

if ((count _url) > 0) then {
    private _last = _url select [(count _url) - 1, 1];
    if (_last isEqualTo "/") then {
        _url = _url select [0, (count _url) - 1];
    };
};

missionNamespace setVariable ["comspec_overwatch_api_url", _url, false];
profileNamespace setVariable ["comspec_overwatch_saved_api_url", _url];

if (_key isNotEqualTo "") then {
    missionNamespace setVariable ["comspec_overwatch_api_key", _key, false];
    profileNamespace setVariable ["comspec_overwatch_saved_api_key", _key];
} else {
    // Champ vide : conserver la clé déjà mémorisée (mot de passe masqué).
    private _kept = [profileNamespace getVariable ["comspec_overwatch_saved_api_key", ""]] call _cleanSecret;
    if (_kept isNotEqualTo "") then {
        missionNamespace setVariable ["comspec_overwatch_api_key", _kept, false];
        _key = _kept;
    } else {
        missionNamespace setVariable ["comspec_overwatch_api_key", "", false];
    };
};

missionNamespace setVariable ["comspec_overwatch_tenant_id", _tenant, false];
profileNamespace setVariable ["comspec_overwatch_saved_tenant_id", _tenant];
saveProfileNamespace;

if (!isNil "cba_settings_fnc_set") then {
    ["comspec_overwatch_api_url", _url, 0, "client", true] call cba_settings_fnc_set;
    if (_key isNotEqualTo "") then {
        ["comspec_overwatch_api_key", _key, 0, "client", true] call cba_settings_fnc_set;
    };
    ["comspec_overwatch_tenant_id", _tenant, 0, "client", true] call cba_settings_fnc_set;
};

["Enregistrement… reconnexion au poste."] call _setFb;

private _pack = [] call comspec_overwatch_connect_fnc_packVersion;
["COMSPECExtension" callExtension ["Init", [_url, _pack]]] call comspec_overwatch_connect_fnc_extResult;
[] call comspec_overwatch_connect_fnc_connect;

private _state = missionNamespace getVariable ["COMSPEC_LinkState", ""];
private _label = [_url] call comspec_overwatch_connect_fnc_portalLabel;
if (_state isEqualTo "linked") then {
    private _msg = format ["Liaison enregistrée — %1", _label];
    if (_tenant isNotEqualTo "") then {
        _msg = _msg + format [" · communauté %1", _tenant];
    };
    [_msg] call _setFb;
    ["COMSPEC_Info", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
} else {
    private _detail = missionNamespace getVariable ["COMSPEC_LinkDetail", ""];
    if (!(_detail isEqualType "")) then { _detail = ""; };
    private _msg = if (_detail isEqualTo "") then {
        format ["Réglages enregistrés (%1). Connectez-vous ou utilisez un code Appairer.", _label]
    } else {
        format ["Réglages enregistrés (%1). %2", _label, _detail]
    };
    [_msg, false] call _setFb;
};

[] call comspec_overwatch_atak_athena_fnc_athena_updateSettings;
