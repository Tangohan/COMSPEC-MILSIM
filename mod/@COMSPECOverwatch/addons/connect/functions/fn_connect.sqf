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
    // Tentative automatique : Steam déjà lié sur Athena → récupère la clé sans code.
    private _steamUid = getPlayerUID player;
    if ((count _steamUid) < 15) then {
        _steamUid = profileNamespace getVariable ["comspec_overwatch_saved_steam_uid", ""];
    };
    if (!(_steamUid isEqualTo "") && {(count _steamUid) >= 15 || {(toUpper _steamUid) find "STEAM_" == 0}}) then {
        ["[Athena] Clé absente — tentative de liaison via Steam…"] call comspec_overwatch_connect_fnc_appendLinkLog;
        [format ["[Athena] Steam UID détecté : '%1' (longueur %2)", _steamUid, count _steamUid]] call comspec_overwatch_connect_fnc_appendLinkLog;
        private _steamRaw = ["COMSPECExtension" callExtension ["LinkBySteam", [_url, _steamUid]]] call comspec_overwatch_connect_fnc_extResult;
        private _steamParts = _steamRaw splitString "|";
        if ((count _steamParts >= 2) && {(_steamParts select 0) isEqualTo "OK"}) then {
            private _steamCols = (_steamParts select 1) splitString "\t";
            if (count _steamCols >= 2) then {
                private _apiUrl = _steamCols select 0;
                private _apiKey = _steamCols select 1;
                private _tenantId = if (count _steamCols >= 3) then { _steamCols select 2 } else { "" };
                if (!(_apiUrl isEqualTo "")) then {
                    _url = _apiUrl;
                    missionNamespace setVariable ["comspec_overwatch_api_url", _apiUrl];
                    profileNamespace setVariable ["comspec_overwatch_saved_api_url", _apiUrl];
                };
                _key = _apiKey;
                missionNamespace setVariable ["comspec_overwatch_api_key", _apiKey];
                profileNamespace setVariable ["comspec_overwatch_saved_api_key", _apiKey];
                if (!(_tenantId isEqualTo "")) then {
                    missionNamespace setVariable ["comspec_overwatch_tenant_id", _tenantId];
                    profileNamespace setVariable ["comspec_overwatch_saved_tenant_id", _tenantId];
                };
                saveProfileNamespace;
                ["[Athena] Steam reconnu — clé de liaison obtenue."] call comspec_overwatch_connect_fnc_appendLinkLog;
            };
        } else {
            private _steamErr = if (count _steamParts >= 2) then { _steamParts select 1 } else { _steamRaw };
            [format ["[Athena] Liaison Steam indisponible (%1) — utilisez K → Compte Athena (code ou Steam lié).", _steamErr]] call comspec_overwatch_connect_fnc_appendLinkLog;
        };
    };
};
if (_key isEqualTo "") then {
    ["[Athena] Clé absente — liez votre compte : K → Compte Athena (Steam déjà lié sur le site, ou code généré)."] call comspec_overwatch_connect_fnc_appendLinkLog;
};

// Vérifie que l’extension répond. Réponse vide ≠ stub 32 Ko : souvent BattlEye (voir RPT).
private _extStatus = [] call comspec_overwatch_connect_fnc_extensionStatus;
_extStatus params ["_extOk", "_extCode", "_ping"];
if (!_extOk) exitWith {
    missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
    missionNamespace setVariable ["COMSPEC_LinkDetail", "Extension non chargée", false];
    [["connect", true] call comspec_overwatch_connect_fnc_extensionLoadHint] call comspec_overwatch_connect_fnc_appendLinkLog;
    [format ["[Athena] Ping extension : '%1' (code %2, err Arma %3)", _ping, _extCode, missionNamespace getVariable ["COMSPEC_LastExtError", 0]]] call comspec_overwatch_connect_fnc_appendLinkLog;
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
        // Sans clé : portail joignable mais compte non lié (whoami est public).
        if (_key isEqualTo "") then {
            missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
            missionNamespace setVariable ["COMSPEC_LinkDetail", "Compte non lié", false];
            private _label = [_url] call comspec_overwatch_connect_fnc_portalLabel;
            [format ["[Athena] Portail joignable (%1, %2) mais compte non lié — utilisez Steam (multijoueur) ou un code.", _label, _userIp]] call comspec_overwatch_connect_fnc_appendLinkLog;
        } else {
            missionNamespace setVariable ["COMSPEC_LinkState", "linked", false];
            missionNamespace setVariable ["COMSPEC_LinkDetail", "", false];
            private _label = [_url] call comspec_overwatch_connect_fnc_portalLabel;
            [format ["[Athena] Connecté à %1 — adresse client : %2", _label, _userIp]] call comspec_overwatch_connect_fnc_appendLinkLog;
            systemChat format ["[Athena] Connecté à %1", _label];
            [] call comspec_overwatch_connect_fnc_updateLinkDiary;
        };
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
        ["[Athena] Connect a renvoyé vide alors que Ping était OK — réessayez ; vérifiez le réseau / le journal Arma."] call comspec_overwatch_connect_fnc_appendLinkLog;
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
