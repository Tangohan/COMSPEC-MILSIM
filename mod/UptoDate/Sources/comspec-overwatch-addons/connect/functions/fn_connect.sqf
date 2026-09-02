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
        [format ["[Athena] URL mémoire ignorée (%1) — repli profil / défaut.", _url]] call comspec_overwatch_connect_fnc_appendLinkLog;
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
        [format ["[Athena] Cle memoire ignoree (%1 car.) — repli profil.", count _key]] call comspec_overwatch_connect_fnc_appendLinkLog;
    };
    if ([_profileKey] call _keyLooksValid) then {
        _key = _profileKey;
    };
};
if (!(_key isEqualTo "")) then {
    missionNamespace setVariable ["comspec_overwatch_api_key", _key];
};
private _tenant = "";
// La communauté n’est plus une saisie joueur : Athena la détermine après authentification.

_url = [_url] call _cleanSecret;
if (_url isEqualTo "") exitWith {
    missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
    missionNamespace setVariable ["COMSPEC_LinkDetail", "Adresse Athena non renseignée", false];
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

// One rich profile snapshot after connection; positions remain lightweight.
if ([] call comspec_overwatch_connect_fnc_isReady) then {
    ["REGISTER"] call comspec_overwatch_connect_fnc_syncOperatorProfile;
};
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
_tenant = "";
if (_key isEqualTo "" && {!([] call comspec_overwatch_connect_fnc_isReady)}) then {
    ["[Athena] Session Athena requise — ouvrez la fenêtre de connexion."] call comspec_overwatch_connect_fnc_appendLinkLog;
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
private _modVersion = [] call comspec_overwatch_connect_fnc_getModVersion;
private _bloodType = [] call comspec_overwatch_connect_fnc_getBloodType;
private _result = ["COMSPECExtension" callExtension ["Connect", [_url, _key, _tenant, _steamForConnect, _modVersion, _bloodType]]] call comspec_overwatch_connect_fnc_extResult;
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
            // Silence pendant handshake démarrage (évite défilé d’annonces Athena)
            if (!(missionNamespace getVariable ["COMSPEC_HandshakeQuiet", false])) then {
                [format ["Connecté à %1", _label], "link", "info"] call comspec_overwatch_connect_fnc_announce;
                ["start"] call comspec_overwatch_connect_fnc_playAtakNotification;
            };
            [] call comspec_overwatch_connect_fnc_updateLinkDiary;
            ["COMSPEC_AthenaLinkChanged", ["linked"]] call CBA_fnc_localEvent;
            0 spawn {
                uiSleep 0.5;
                [false] call comspec_overwatch_connect_fnc_syncCallsignFromAthena;
                uiSleep 0.5;
                [] spawn comspec_overwatch_connect_fnc_syncAtakRealism;
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
            if (!(missionNamespace getVariable ["COMSPEC_HandshakeQuiet", false])) then {
                [format ["Connecté à %1", _label], "link", "info"] call comspec_overwatch_connect_fnc_announce;
                ["start"] call comspec_overwatch_connect_fnc_playAtakNotification;
            };
            [] call comspec_overwatch_connect_fnc_updateLinkDiary;
            ["COMSPEC_AthenaLinkChanged", ["linked"]] call CBA_fnc_localEvent;
            0 spawn {
                uiSleep 0.5;
                [false] call comspec_overwatch_connect_fnc_syncCallsignFromAthena;
                uiSleep 0.5;
                [] spawn comspec_overwatch_connect_fnc_syncAtakRealism;
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
                case "mod_steam_blocked": {
                    _detail = "Acces mod refuse — Steam restreint";
                    _logMsg = "Acces au mod refuse pour cet identifiant Steam. Contactez un administrateur de la communaute.";
                };
                case "mod_ip_blocked": {
                    _detail = "Acces mod refuse — reseau restreint";
                    _logMsg = "Acces au mod refuse depuis cette adresse reseau. Contactez un administrateur de la communaute.";
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
    if (_payload in ["mod_steam_blocked", "mod_ip_blocked"]) then {
        ["COMSPEC_Warning", [_logMsg]] call comspec_overwatch_connect_fnc_showNotification;
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
