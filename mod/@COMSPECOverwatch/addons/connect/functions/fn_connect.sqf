if (!hasInterface) exitWith {};

// Retire guillemets / espaces colles (profileNamespace, CBA, retour extension).
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

// Restaure une liaison precedente si les reglages CBA sont encore vides
private _url = [missionNamespace getVariable ["comspec_overwatch_api_url", ""]] call _cleanSecret;
// CBA corrompu (bool / valeur tronquée type « h ») → ignorer et retomber sur le profil.
private _urlLooksValid = {
    params ["_u"];
    if (!(_u isEqualType "")) exitWith { false };
    private _l = toLower _u;
    ((count _u) >= 12) && {((_l find "https://") == 0) || {(_l find "http://") == 0}}
};
if (!([_url] call _urlLooksValid)) then {
    if (!(_url isEqualTo "")) then {
        [format ["[Athena] Cached URL ignored (%1) — fallback profile / défaut.", _url]] call comspec_overwatch_connect_fnc_appendLinkLog;
    };
    _url = [profileNamespace getVariable ["comspec_overwatch_saved_api_url", ""]] call _cleanSecret;
    if (!([_url] call _urlLooksValid)) then {
        _url = "https://athena.ttrd.fr/public";
    };
    missionNamespace setVariable ["comspec_overwatch_api_url", _url];
};
private _keyLooksValid = {
    params ["_k"];
    if (!(_k isEqualType "")) exitWith { false };
    // Clés communauté / plateforme : typiquement ≥ 16 car. ; « h » / tronquées CBA → refusées.
    (count _k) >= 16
};
private _key = [missionNamespace getVariable ["comspec_overwatch_api_key", ""]] call _cleanSecret;
private _profileKey = [profileNamespace getVariable ["comspec_overwatch_saved_api_key", ""]] call _cleanSecret;
// Préférer le profil si la mémoire mission est vide, trop courte, ou plus courte que le profil (troncature).
if (
    (_key isEqualTo "")
    || {!([_key] call _keyLooksValid)}
    || {([_profileKey] call _keyLooksValid) && {(count _profileKey) > (count _key)}}
) then {
    if (!(_key isEqualTo "") && {!([_key] call _keyLooksValid)}) then {
        [format ["[Athena] Cle memoire ignoree (%1 car.) — fallback profile.", count _key]] call comspec_overwatch_connect_fnc_appendLinkLog;
    };
    if ([_profileKey] call _keyLooksValid) then {
        _key = _profileKey;
    };
};
if (!(_key isEqualTo "")) then {
    missionNamespace setVariable ["comspec_overwatch_api_key", _key];
};
private _tenant = [missionNamespace getVariable ["comspec_overwatch_tenant_id", ""]] call _cleanSecret;
if (_tenant isEqualTo "") then {
    _tenant = [profileNamespace getVariable ["comspec_overwatch_saved_tenant_id", ""]] call _cleanSecret;
    if (!(_tenant isEqualTo "")) then {
        missionNamespace setVariable ["comspec_overwatch_tenant_id", _tenant];
    };
} else {
    missionNamespace setVariable ["comspec_overwatch_tenant_id", _tenant];
};

_url = [_url] call _cleanSecret;
if (_url isEqualTo "") exitWith {
    missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
    missionNamespace setVariable ["COMSPEC_LinkDetail", "Athena address not specified", false];
    ["[Athena] Pas d'adresse portail — utilisez Compte Athena (code) ou les reglages CBA."] call comspec_overwatch_connect_fnc_appendLinkLog;
    [] call comspec_overwatch_connect_fnc_updateStatusBadges;
};

if (!([_url] call _urlLooksValid)) exitWith {
    missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
    missionNamespace setVariable ["COMSPEC_LinkDetail", "Adresse Athena invalide", false];
    [format ["[Athena] Adresse invalide (%1). Exemple : https://athena.ttrd.fr/public", _url]] call comspec_overwatch_connect_fnc_appendLinkLog;
    [] call comspec_overwatch_connect_fnc_updateStatusBadges;
};

missionNamespace setVariable ["COMSPEC_LinkState", "connecting", false];
missionNamespace setVariable ["COMSPEC_LinkDetail", "", false];
[] call comspec_overwatch_connect_fnc_updateStatusBadges;
[format ["[Athena] Connexion vers %1…", [_url] call comspec_overwatch_connect_fnc_portalLabel]] call comspec_overwatch_connect_fnc_appendLinkLog;

// Re-appliquer la clé déjà résolue (évite qu’un EDITBOX CBA ait écrasé missionNamespace entre-temps).
_key = [_key] call _cleanSecret;
if (
    (_key isEqualTo "")
    || {!([_key] call _keyLooksValid)}
) then {
    private _pk2 = [profileNamespace getVariable ["comspec_overwatch_saved_api_key", ""]] call _cleanSecret;
    if ([_pk2] call _keyLooksValid) then { _key = _pk2; };
};
if (!(_key isEqualTo "")) then {
    missionNamespace setVariable ["comspec_overwatch_api_key", _key];
};
_tenant = [missionNamespace getVariable ["comspec_overwatch_tenant_id", ""]] call _cleanSecret;
if (_tenant isEqualTo "") then {
    _tenant = [profileNamespace getVariable ["comspec_overwatch_saved_tenant_id", ""]] call _cleanSecret;
};
if (_key isEqualTo "") then {
    // Tentative automatique : Steam deja lie sur Athena → recupere la cle sans code.
    private _steamUid = getPlayerUID player;
    if ((count _steamUid) < 15) then {
        _steamUid = profileNamespace getVariable ["comspec_overwatch_saved_steam_uid", ""];
    };
    if (!(_steamUid isEqualTo "") && {(count _steamUid) >= 15 || {(toUpper _steamUid) find "STEAM_" == 0}}) then {
        ["[Athena] Cle absente — tentative de liaison via Steam…"] call comspec_overwatch_connect_fnc_appendLinkLog;
        [format ["[Athena] Steam UID detecte : '%1' (longueur %2)", _steamUid, count _steamUid]] call comspec_overwatch_connect_fnc_appendLinkLog;
        private _steamRaw = ["COMSPECExtension" callExtension ["LinkBySteam", [_url, _steamUid]]] call comspec_overwatch_connect_fnc_extResult;
        private _steamParts = _steamRaw splitString "|";
        if ((count _steamParts >= 2) && {(_steamParts select 0) isEqualTo "OK"}) then {
            // Format 1.12+ : OK|url|key|tenant — legacy : OK|url\tkey\ttenant
            private _apiUrl = "";
            private _apiKey = "";
            private _tenantId = "";
            if (count _steamParts >= 4) then {
                _apiUrl = [_steamParts select 1] call _cleanSecret;
                _apiKey = [_steamParts select 2] call _cleanSecret;
                _tenantId = [_steamParts select 3] call _cleanSecret;
            } else {
                private _steamCols = (_steamParts select 1) splitString "\t";
                if (count _steamCols >= 2) then {
                    _apiUrl = [_steamCols select 0] call _cleanSecret;
                    _apiKey = [_steamCols select 1] call _cleanSecret;
                    _tenantId = if (count _steamCols >= 3) then { [_steamCols select 2] call _cleanSecret } else { "" };
                };
            };
            if ((count _apiKey) >= 4) then {
                if ([_apiUrl] call _urlLooksValid) then {
                    _url = _apiUrl;
                    missionNamespace setVariable ["comspec_overwatch_api_url", _apiUrl];
                    profileNamespace setVariable ["comspec_overwatch_saved_api_url", _apiUrl];
                };
                _key = _apiKey;
                missionNamespace setVariable ["comspec_overwatch_api_key", _apiKey];
                profileNamespace setVariable ["comspec_overwatch_saved_api_key", _apiKey];
                if (!(_tenantId isEqualTo "")) then {
                    _tenant = _tenantId;
                    missionNamespace setVariable ["comspec_overwatch_tenant_id", _tenantId];
                    profileNamespace setVariable ["comspec_overwatch_saved_tenant_id", _tenantId];
                };
                saveProfileNamespace;
                ["[Athena] Steam reconnu — cle de liaison obtenue."] call comspec_overwatch_connect_fnc_appendLinkLog;
            };
        } else {
            private _steamErr = if (count _steamParts >= 2) then { _steamParts select 1 } else { _steamRaw };
            [format ["[Athena] Liaison Steam indisponible (%1) — utilisez K → Compte Athena (code ou Steam lie).", _steamErr]] call comspec_overwatch_connect_fnc_appendLinkLog;
        };
    };
};
if (_key isEqualTo "") then {
    ["[Athena] Cle absente — liez votre compte : K → Compte Athena (Steam deja lie sur le site, ou code genere)."] call comspec_overwatch_connect_fnc_appendLinkLog;
};

// Verifie que l'extension repond. Reponse vide ≠ stub 32 Ko : souvent BattlEye (voir RPT).
private _extStatus = [] call comspec_overwatch_connect_fnc_extensionStatus;
_extStatus params ["_extOk", "_extCode", "_ping"];
if (!_extOk) exitWith {
    missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
    missionNamespace setVariable ["COMSPEC_LinkDetail", "Extension non chargee", false];
    [["connect", true] call comspec_overwatch_connect_fnc_extensionLoadHint] call comspec_overwatch_connect_fnc_appendLinkLog;
    [format ["[Athena] Ping extension : '%1' (code %2, err Arma %3)", _ping, _extCode, missionNamespace getVariable ["COMSPEC_LastExtError", 0]]] call comspec_overwatch_connect_fnc_appendLinkLog;
    [] call comspec_overwatch_connect_fnc_updateStatusBadges;
};

// Connect synchrone : avec cle, valide auth (client-init) — ne pas se fier au whoami public seul.
private _steamForConnect = getPlayerUID player;
if ((count _steamForConnect) < 15) then {
    _steamForConnect = profileNamespace getVariable ["comspec_overwatch_saved_steam_uid", ""];
};
private _result = ["COMSPECExtension" callExtension ["Connect", [_url, _key, _tenant, _steamForConnect]]] call comspec_overwatch_connect_fnc_extResult;
private _parts = _result splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };
private _payload = if (count _parts >= 2) then { _parts select 1 } else { _result };

if (_prefix == "OK") then {
    // whoami = IP publique uniquement ; le vrai succes auth est deja dans Connect (si cle presente).
    private _ipResult = ["COMSPECExtension" callExtension ["GetClientIp", []]] call comspec_overwatch_connect_fnc_extResult;
    private _ipParts = _ipResult splitString "|";
    private _ipPrefix = if (count _ipParts >= 1) then { _ipParts select 0 } else { "" };
    private _userIp = if (count _ipParts >= 2) then { _ipParts select 1 } else { "—" };
    if (_ipPrefix == "OK") then {
        missionNamespace setVariable ["COMSPEC_userIp", _userIp, true];
        missionNamespace setVariable ["COMSPEC_LastHealthOk", diag_tickTime, false];
        if (_key isEqualTo "") then {
            missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
            missionNamespace setVariable ["COMSPEC_LinkDetail", "Compte non lie", false];
            private _label = [_url] call comspec_overwatch_connect_fnc_portalLabel;
            [format ["[Athena] Portail joignable (%1, %2) mais compte non lie — utilisez Steam (multijoueur) ou un code.", _label, _userIp]] call comspec_overwatch_connect_fnc_appendLinkLog;
        } else {
            missionNamespace setVariable ["COMSPEC_LinkState", "linked", false];
            missionNamespace setVariable ["COMSPEC_LinkDetail", "", false];
            private _label = [_url] call comspec_overwatch_connect_fnc_portalLabel;
            [format ["[Athena] Connecte a %1 — adresse client : %2", _label, _userIp]] call comspec_overwatch_connect_fnc_appendLinkLog;
            [format ["Connected to %1", _label], "link", "info"] call comspec_overwatch_connect_fnc_announce;
            ["start"] call comspec_overwatch_connect_fnc_playAtakNotification;
            [] call comspec_overwatch_connect_fnc_updateLinkDiary;
            0 spawn {
                uiSleep 0.5;
                [false] call comspec_overwatch_connect_fnc_syncCallsignFromAthena;
            };
        };
    } else {
        // Auth Connect OK mais whoami KO : on garde linked si cle presente (whoami n'est pas l'auth).
        missionNamespace setVariable ["COMSPEC_userIp", "—", true];
        if (_key isEqualTo "") then {
            missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
            missionNamespace setVariable ["COMSPEC_LinkDetail", "Portail injoignable", false];
            private _why = if (count _ipParts >= 2) then { _ipParts select 1 } else { _ipResult };
            [format ["[Athena] Portail injoignable apres Connect (%1).", _why]] call comspec_overwatch_connect_fnc_appendLinkLog;
        } else {
            missionNamespace setVariable ["COMSPEC_LinkState", "linked", false];
            missionNamespace setVariable ["COMSPEC_LinkDetail", "", false];
            private _label = [_url] call comspec_overwatch_connect_fnc_portalLabel;
            [format ["[Athena] Liaison etablie avec %1 (adresse client indisponible).", _label]] call comspec_overwatch_connect_fnc_appendLinkLog;
            [format ["Connected to %1", _label], "link", "info"] call comspec_overwatch_connect_fnc_announce;
            ["start"] call comspec_overwatch_connect_fnc_playAtakNotification;
            [] call comspec_overwatch_connect_fnc_updateLinkDiary;
            0 spawn {
                uiSleep 0.5;
                [false] call comspec_overwatch_connect_fnc_syncCallsignFromAthena;
            };
        };
    };
} else {
    missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
    private _detail = "Echec de liaison";
    private _logMsg = _payload;
    if (_result isEqualTo "") then {
        _detail = "Module Athena sans reponse";
        _logMsg = "Connect a renvoye vide alors que Ping etait OK — reessayez ; verifiez le reseau / le journal Arma.";
    } else {
        if (_prefix == "ERR") then {
            switch (_payload) do {
                case "unauthorized": {
                    _detail = "Acces refuse — cle Athena invalide";
                    _logMsg = "Acces refuse (cle Athena invalide ou mal enregistree).";
                };
                case "tenant_required": {
                    _detail = "Communaute manquante — refaites la liaison";
                    _logMsg = "Communaute Athena manquante — generez un nouveau code et refaites la liaison.";
                };
                case "steam_not_linked": {
                    _detail = "Compte Athena non lie a ce Steam";
                    _logMsg = "Aucun compte Athena lie a ce Steam pour cette communaute — utilisez un code de liaison.";
                };
                case "account_disabled": {
                    _detail = "Compte Athena non autorise";
                    _logMsg = "Ce compte Athena n'est pas autorise.";
                };
                case "invalid_steam": {
                    _detail = "Identifiant Steam invalide";
                    _logMsg = "Identifiant Steam non reconnu — relancez la liaison.";
                };
                case "steam_required": {
                    _detail = "Identifiant Steam requis";
                    _logMsg = "Identifiant Steam requis — mettez a jour le mod puis reconnectez-vous.";
                };
                case "invalid_url": {
                    _detail = "Adresse Athena invalide";
                    _logMsg = "Adresse portail invalide.";
                };
                case "not_found": {
                    _detail = "Service Athena introuvable";
                    _logMsg = "Service introuvable — verifiez l'adresse (.../public).";
                };
                case "timeout": {
                    _detail = "Delai depasse";
                    _logMsg = "Delai depasse — verifiez votre reseau.";
                };
                case "network": {
                    _detail = "Portail injoignable";
                    _logMsg = "Impossible de joindre Athena.";
                };
                default {
                    _detail = "Echec de liaison";
                    _logMsg = _payload;
                };
            };
        } else {
            _logMsg = format ["Reponse extension inattendue : %1", _result];
        };
    };
    missionNamespace setVariable ["COMSPEC_LinkDetail", _detail, false];
    [format ["[Athena] %1", _logMsg]] call comspec_overwatch_connect_fnc_appendLinkLog;
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
