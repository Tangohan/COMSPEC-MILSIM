if (!hasInterface) exitWith {};

// Restaure une liaison précédente (code Athena) si les réglages CBA sont encore vides
private _url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];
if (_url isEqualTo "") then {
    _url = profileNamespace getVariable ["comspec_overwatch_saved_api_url", ""];
    if (!(_url isEqualTo "")) then {
        missionNamespace setVariable ["comspec_overwatch_api_url", _url];
    };
};
private _key = missionNamespace getVariable ["comspec_overwatch_api_key", ""];
if (_key isEqualTo "") then {
    _key = profileNamespace getVariable ["comspec_overwatch_saved_api_key", ""];
    if (!(_key isEqualTo "")) then {
        missionNamespace setVariable ["comspec_overwatch_api_key", _key];
    };
};
private _tenant = missionNamespace getVariable ["comspec_overwatch_tenant_id", ""];
if (_tenant isEqualTo "") then {
    _tenant = profileNamespace getVariable ["comspec_overwatch_saved_tenant_id", ""];
    if (!(_tenant isEqualTo "")) then {
        missionNamespace setVariable ["comspec_overwatch_tenant_id", _tenant];
    };
};

_url = trim (missionNamespace getVariable ["comspec_overwatch_api_url", ""]);
if (_url isEqualTo "") exitWith {
    missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
    missionNamespace setVariable ["COMSPEC_LinkDetail", "Adresse Athena non renseignée", false];
    ["[Athena] Pas d’adresse portail — utilisez Compte Athena (code) ou les réglages CBA."] call comspec_overwatch_connect_fnc_appendLinkLog;
    [] call comspec_overwatch_connect_fnc_updateStatusBadges;
};

private _urlLower = toLower _url;
if (((_urlLower find "https://") != 0) && {(_urlLower find "http://") != 0}) exitWith {
    missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
    missionNamespace setVariable ["COMSPEC_LinkDetail", "Adresse Athena invalide", false];
    [format ["[Athena] Adresse invalide (%1). Exemple : https://athena.ttrd.fr/public", _url]] call comspec_overwatch_connect_fnc_appendLinkLog;
    [] call comspec_overwatch_connect_fnc_updateStatusBadges;
};

missionNamespace setVariable ["COMSPEC_LinkState", "connecting", false];
missionNamespace setVariable ["COMSPEC_LinkDetail", "", false];
[] call comspec_overwatch_connect_fnc_updateStatusBadges;
[format ["[Athena] Connexion vers %1…", [_url] call comspec_overwatch_connect_fnc_portalLabel]] call comspec_overwatch_connect_fnc_appendLinkLog;

_key = missionNamespace getVariable ["comspec_overwatch_api_key", ""];
if (_key isEqualTo "") then {
    ["[Athena] Clé API absente — liez votre compte : K → Compte Athena (saisir un code) avec un code généré sur le site."] call comspec_overwatch_connect_fnc_appendLinkLog;
};

// Vérifie que la DLL Native AOT répond (un stub managé ~30 Ko renvoie une chaîne vide).
private _ping = ["COMSPECExtension" callExtension ["Ping", []]] call comspec_overwatch_connect_fnc_extResult;
if (_ping isEqualTo "" || {(_ping select [0, 3]) != "OK|"}) exitWith {
    missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
    missionNamespace setVariable ["COMSPEC_LinkDetail", "Extension non chargée", false];
    ["[Athena] Extension COMSPECExtension_x64.dll absente ou invalide (réponse vide). Copiez la DLL Native AOT (~5 Mo) dans @COMSPECOverwatch, puis relancez Arma."] call comspec_overwatch_connect_fnc_appendLinkLog;
    if (!(_ping isEqualTo "")) then {
        [format ["[Athena] Ping extension : %1", _ping]] call comspec_overwatch_connect_fnc_appendLinkLog;
    };
    [] call comspec_overwatch_connect_fnc_updateStatusBadges;
};

private _result = ["COMSPECExtension" callExtension ["Connect", [_url, _key]]] call comspec_overwatch_connect_fnc_extResult;
private _parts = _result splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };
private _payload = if (count _parts >= 2) then { _parts select 1 } else { _result };

if (_prefix == "OK") then {
    private _ipResult = ["COMSPECExtension" callExtension ["GetClientIp", []]] call comspec_overwatch_connect_fnc_extResult;
    private _ipParts = _ipResult splitString "|";
    private _ipPrefix = if (count _ipParts >= 1) then { _ipParts select 0 } else { "" };
    private _userIp = if (count _ipParts >= 2) then { _ipParts select 1 } else { "—" };
    if (_ipPrefix == "OK") then {
        missionNamespace setVariable ["COMSPEC_userIp", _userIp, true];
        missionNamespace setVariable ["COMSPEC_LastHealthOk", diag_tickTime, false];
        missionNamespace setVariable ["COMSPEC_LinkState", "linked", false];
        missionNamespace setVariable ["COMSPEC_LinkDetail", "", false];
        private _label = [_url] call comspec_overwatch_connect_fnc_portalLabel;
        [format ["[Athena] Connecté à %1 — adresse client : %2", _label, _userIp]] call comspec_overwatch_connect_fnc_appendLinkLog;
        systemChat format ["[Athena] Connecté à %1", _label];
        [] call comspec_overwatch_connect_fnc_updateLinkDiary;
    } else {
        missionNamespace setVariable ["COMSPEC_userIp", "—", true];
        missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
        missionNamespace setVariable ["COMSPEC_LinkDetail", "Portail injoignable", false];
        private _why = if (count _ipParts >= 2) then { _ipParts select 1 } else { _ipResult };
        [format ["[Athena] Portail injoignable après Connect (%1). Vérifiez l’URL /public et la clé.", _why]] call comspec_overwatch_connect_fnc_appendLinkLog;
    };
} else {
    missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
    missionNamespace setVariable ["COMSPEC_LinkDetail", "Échec de liaison", false];
    if (_result isEqualTo "") then {
        ["[Athena] Connect a renvoyé vide — DLL non Native AOT ou non chargée. Remplacez COMSPECExtension_x64.dll (~5 Mo) dans @COMSPECOverwatch."] call comspec_overwatch_connect_fnc_appendLinkLog;
    } else {
        if (_prefix == "ERR") then {
            [format ["[Athena] Échec : %1", _payload]] call comspec_overwatch_connect_fnc_appendLinkLog;
        } else {
            [format ["[Athena] Réponse extension inattendue : %1", _result]] call comspec_overwatch_connect_fnc_appendLinkLog;
        };
    };
};
[] call comspec_overwatch_connect_fnc_updateStatusBadges;

private _display = uiNamespace getVariable ["COMSPEC_Chat_Display", displayNull];
if (!isNull _display) then {
    private _ipCtrl = _display displayCtrl 1398;
    private _ip = missionNamespace getVariable ["COMSPEC_userIp", "—"];
    if (!isNull _ipCtrl) then { _ipCtrl ctrlSetText ("Votre adresse : " + _ip); };
    private _urlCtrl = _display displayCtrl 1399;
    if (!isNull _urlCtrl) then {
        _urlCtrl ctrlSetText ("Portail : " + ([_url] call comspec_overwatch_connect_fnc_portalLabel));
    };
};
