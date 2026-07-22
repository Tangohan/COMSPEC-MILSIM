/*

    Lit URL + Steam + code du dialog.

    Code vide → liaison par Steam (champ 9206 ou getPlayerUID).

    Sinon échange le code court. Persiste et Connect.

*/

if (!hasInterface) exitWith {};



private _display = uiNamespace getVariable ["COMSPEC_AccountLink_Display", displayNull];

if (isNull _display) exitWith {};



private _urlCtrl = _display displayCtrl 9201;

private _codeCtrl = _display displayCtrl 9202;

private _status = _display displayCtrl 9203;

private _steamCtrl = _display displayCtrl 9206;



private _stripQuotes = {
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

private _url = if (!isNull _urlCtrl) then { [ctrlText _urlCtrl] call _stripQuotes } else { "" };
private _code = if (!isNull _codeCtrl) then { toUpper (trim (ctrlText _codeCtrl)) } else { "" };
private _steamManual = if (!isNull _steamCtrl) then { trim (ctrlText _steamCtrl) } else { "" };

private _setStatus = {
    params ["_text", ["_color", "#8aa0b4"]];
    if (!isNull _status) then {
        _status ctrlSetStructuredText parseText format ["<t align='center' size='0.55' color='%1'>%2</t>", _color, _text];
    };
};

if (_url isEqualTo "") exitWith {
    ["Indiquez l’adresse du portail Athena.", "#ff8a7a"] call _setStatus;
};
private _urlLower = toLower _url;
if (((_urlLower find "https://") != 0) && {(_urlLower find "http://") != 0}) exitWith {
    ["Adresse invalide — utilisez https://athena.ttrd.fr/public", "#ff8a7a"] call _setStatus;
};
// Garde l’URL saisie (source de vérité) si le portail renvoie une adresse tronquée.
private _dialogUrl = _url;



private _extStatus = [] call comspec_overwatch_connect_fnc_extensionStatus;

_extStatus params ["_extOk", "_extCode", "_extPing"];

if (!_extOk) exitWith {

    private _msg = if (_extCode isEqualTo "bad_response") then {

        format ["Module Athena réponse inattendue (%1).", _extPing]

    } else {

        ["link", false] call comspec_overwatch_connect_fnc_extensionLoadHint

    };

    [_msg, "#ff8a7a"] call _setStatus;

    private _logMsg = if (_extCode isEqualTo "bad_response") then { _msg } else { ["link", true] call comspec_overwatch_connect_fnc_extensionLoadHint };

    [format ["[Athena] Échec liaison compte : %1", _logMsg]] call comspec_overwatch_connect_fnc_appendLinkLog;

    ["COMSPEC_Warning", [_msg]] call comspec_overwatch_connect_fnc_showNotification;

};



private _steamUid = _steamManual;

if (_steamUid isEqualTo "") then { _steamUid = getPlayerUID player; };



private _useSteam = (_code isEqualTo "" || {count _code < 4});



private _steamUidOk = false;

if (!(_steamUid isEqualTo "")) then {

    private _steamUpper = toUpper _steamUid;

    _steamUidOk = (count _steamUid >= 15)

        || ((_steamUpper find "STEAM_") == 0)

        || ((_steamUpper find "U:1:") >= 0)

        || ((_steamUpper find "/PROFILES/") >= 0);

};



if (_useSteam && !_steamUidOk) exitWith {

    private _msg = "Indiquez votre identifiant Steam (profil Athena), ou passez en multijoueur, ou utilisez un code.";

    [_msg, "#ff8a7a"] call _setStatus;

    [format ["[Athena] Échec liaison Steam : UID='%1'", _steamUid]] call comspec_overwatch_connect_fnc_appendLinkLog;

    ["COMSPEC_Warning", [_msg]] call comspec_overwatch_connect_fnc_showNotification;

};



private _raw = "";

if (_useSteam) then {

    ["Liaison via Steam en cours…", "#8aa0b4"] call _setStatus;

    [format ["[Athena] Liaison Steam (%1) vers %2…", _steamUid, [_url] call comspec_overwatch_connect_fnc_portalLabel]] call comspec_overwatch_connect_fnc_appendLinkLog;

    _raw = ["COMSPECExtension" callExtension ["LinkBySteam", [_url, _steamUid]]] call comspec_overwatch_connect_fnc_extResult;

} else {

    ["Échange du code en cours…", "#8aa0b4"] call _setStatus;

    [format ["[Athena] Échange du code vers %1…", [_url] call comspec_overwatch_connect_fnc_portalLabel]] call comspec_overwatch_connect_fnc_appendLinkLog;

    _raw = ["COMSPECExtension" callExtension ["RedeemGameLink", [_url, _code, _steamUid]]] call comspec_overwatch_connect_fnc_extResult;

};



private _parts = _raw splitString "|";

private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };



if (_prefix != "OK") exitWith {

    private _err = if (count _parts >= 2) then { _parts select 1 } else { _raw };

    if (_raw isEqualTo "") then { _err = "extension_empty"; };

    if (_err isEqualTo "") then { _err = "empty"; };

    private _msg = switch (_err) do {

        case "invalid": { "Adresse ou identifiant invalide." };

        case "invalid_url": { "Adresse du portail invalide — utilisez https://athena.ttrd.fr/public" };

        case "invalid_op": { "Le module Athena était occupé. Réessayez dans une seconde." };

        case "busy_retry": { "Échange en cours côté module — réessayez dans une seconde." };

        case "steam_not_linked": {

            "Aucun compte Athena lié à ce Steam. Vérifiez l’identifiant sur votre profil Athena, ou générez un code."

        };

        case "no_steam_uid": {

            "Identifiant Steam invalide. Collez celui du profil Athena (souvent 17 chiffres)."

        };

        case "server_outdated": {

            "Le portail n’a pas encore la liaison Steam — déployez la mise à jour Athena, ou utilisez un code."

        };

        case "invalid_steam": { "Identifiant Steam invalide." };

        case "account_disabled": { "Ce compte Athena n’est pas autorisé à se lier." };

        case "code_invalid_or_expired": { "Code invalide, déjà utilisé ou expiré — générez-en un nouveau sur Athena." };

        case "code_already_used": { "Ce code a déjà été utilisé — générez-en un nouveau sur Athena." };

        case "code_expired": { "Ce code a expiré — générez-en un nouveau sur Athena (valable 30 min)." };

        case "invalid_code": { "Code manquant ou trop court." };

        case "not_found": { "Athena n’a pas trouvé cette ressource. Vérifiez l’adresse (…/public)." };

        case "timeout": { "Délai dépassé — vérifiez votre réseau." };

        case "network": { "Impossible de joindre Athena." };

        case "invalid_response": { "Réponse inattendue d’Athena." };

        case "http_503": { "Liaison pas encore activée sur le portail. Contactez un administrateur Athena." };

        case "http_500": { "Erreur interne du portail. Réessayez dans un instant." };

        case "extension_empty": {

            "Le module Athena est chargé, mais l’échange n’a pas répondu. Réessayez."

        };

        case "empty": { "Réponse incomplète du module Athena. Réessayez." };

        default {

            if (_err find "http_" == 0) then {

                format ["Erreur serveur (%1).", _err select [5, (count _err) - 5]]

            } else {

                format ["Liaison impossible (%1).", _err]

            }

        };

    };

    [_msg, "#ff8a7a"] call _setStatus;

    [format ["[Athena] Échec liaison compte : %1", _msg]] call comspec_overwatch_connect_fnc_appendLinkLog;

    if (!(_raw isEqualTo "")) then {

        [format ["[Athena] Réponse brute extension : %1", _raw]] call comspec_overwatch_connect_fnc_appendLinkLog;

    };

    ["COMSPEC_Warning", [_msg]] call comspec_overwatch_connect_fnc_showNotification;

};



// Format DLL 1.12+ : OK|apiUrl|apiKey|tenantId  (legacy tab : OK|apiUrl\tapiKey\ttenantId)
private _apiUrl = "";
private _apiKey = "";
private _tenantId = "";
private _parseOk = false;
if (count _parts >= 4) then {
    _apiUrl = [_parts select 1] call _stripQuotes;
    _apiKey = [_parts select 2] call _stripQuotes;
    _tenantId = [_parts select 3] call _stripQuotes;
    _parseOk = true;
} else {
    private _payload = if (count _parts >= 2) then { _parts select 1 } else { "" };
    private _cols = _payload splitString "\t";
    if (count _cols >= 2) then {
        _apiUrl = [_cols select 0] call _stripQuotes;
        _apiKey = [_cols select 1] call _stripQuotes;
        _tenantId = if (count _cols >= 3) then { [_cols select 2] call _stripQuotes } else { "" };
        _parseOk = true;
    };
};

if (!_parseOk) exitWith {
    ["Réponse incomplète d’Athena.", "#ff8a7a"] call _setStatus;
    ["[Athena] Réponse incomplète après liaison."] call comspec_overwatch_connect_fnc_appendLinkLog;
};

// Si l’URL renvoyée est tronquée / corrompue (ex. « h »), garder celle du dialog.
// Même si elle « semble » valide : préférer l’URL saisie (celle qui a réussi le redeem).
_apiUrl = _dialogUrl;

if (_apiKey isEqualTo "") exitWith {
    ["Liaison refusée : le portail n’a pas renvoyé d’accès. Contactez un administrateur Athena.", "#ff8a7a"] call _setStatus;
    ["[Athena] Réponse OK sans clé d’accès — configuration portail incomplète."] call comspec_overwatch_connect_fnc_appendLinkLog;
    ["COMSPEC_Warning", ["Liaison refusée : accès manquant côté portail."]] call comspec_overwatch_connect_fnc_showNotification;
};
[format ["[Athena] Accès reçu (clé %1 car., communauté %2).", count _apiKey, if (_tenantId isEqualTo "") then { "—" } else { _tenantId }]] call comspec_overwatch_connect_fnc_appendLinkLog;

missionNamespace setVariable ["comspec_overwatch_api_url", _apiUrl];

missionNamespace setVariable ["comspec_overwatch_api_key", _apiKey];

if (!(_tenantId isEqualTo "")) then {

    missionNamespace setVariable ["comspec_overwatch_tenant_id", _tenantId];

};



profileNamespace setVariable ["comspec_overwatch_saved_api_url", _apiUrl];

profileNamespace setVariable ["comspec_overwatch_saved_api_key", _apiKey];

profileNamespace setVariable ["comspec_overwatch_saved_tenant_id", _tenantId];

if (!(_steamUid isEqualTo "")) then {

    profileNamespace setVariable ["comspec_overwatch_saved_steam_uid", _steamUid];

};

saveProfileNamespace;



// CBA : URL + communauté seulement. Ne PAS pousser la clé dans un EDITBOX CBA
// (troncature / sync → écrase missionNamespace → Connect 401 juste après redeem OK).
if (!isNil "cba_settings_fnc_set") then {
    ["comspec_overwatch_api_url", _apiUrl, 0, "client", true] call cba_settings_fnc_set;
    if (!(_tenantId isEqualTo "")) then {
        ["comspec_overwatch_tenant_id", _tenantId, 0, "client", true] call cba_settings_fnc_set;
    };
};

if (_useSteam) then {
    ["[Athena] Steam reconnu — etablissement de la liaison…"] call comspec_overwatch_connect_fnc_appendLinkLog;
} else {
    ["[Athena] Code accepte — etablissement de la liaison…"] call comspec_overwatch_connect_fnc_appendLinkLog;
};

// DLL 1.14+ a déjà validé client-init pendant Redeem/Steam et détient la bonne clé.
// Connect avec clé vide : ne pas réécrire la clé DLL avec une valeur SQF/CBA potentiellement tronquée.
private _connectRaw = ["COMSPECExtension" callExtension ["Connect", [_apiUrl, "", _tenantId]]] call comspec_overwatch_connect_fnc_extResult;
private _connectParts = _connectRaw splitString "|";
private _connectOk = ((count _connectParts >= 1) && {(_connectParts select 0) isEqualTo "OK"});

if (_connectOk) then {
    missionNamespace setVariable ["COMSPEC_LinkState", "linked", false];
    missionNamespace setVariable ["COMSPEC_LinkDetail", "", false];
    missionNamespace setVariable ["COMSPEC_LastHealthOk", diag_tickTime, false];
    ["[Athena] Liaison etablie (cle conservee cote module)."] call comspec_overwatch_connect_fnc_appendLinkLog;
    [] call comspec_overwatch_connect_fnc_updateStatusBadges;

    ["Compte lie — liaison Athena etablie.", "#7dffb0"] call _setStatus;
    ["COMSPEC_Info", ["Compte Athena connecte."]] call comspec_overwatch_connect_fnc_showNotification;

    0 spawn {
        uiSleep 0.5;
        [true] call comspec_overwatch_connect_fnc_syncCallsignFromAthena;
    };

    [] call comspec_overwatch_connect_fnc_updateLinkDiary;
    uiSleep 0.8;
    closeDialog 0;
} else {
    // Filet : si Connect a quand meme echoue, retenter le chemin classique (profil).
    [] call comspec_overwatch_connect_fnc_connect;
    private _state = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
    if (_state isEqualTo "linked") then {
        ["Compte lie — liaison Athena etablie.", "#7dffb0"] call _setStatus;
        ["COMSPEC_Info", ["Compte Athena connecte."]] call comspec_overwatch_connect_fnc_showNotification;
        0 spawn {
            uiSleep 0.5;
            [true] call comspec_overwatch_connect_fnc_syncCallsignFromAthena;
        };
        [] call comspec_overwatch_connect_fnc_updateLinkDiary;
        uiSleep 0.8;
        closeDialog 0;
    } else {
        private _detail = missionNamespace getVariable ["COMSPEC_LinkDetail", ""];
        private _msg = if (_detail isEqualTo "") then {
            "Parametres enregistres, mais la liaison n'est pas encore active."
        } else {
            _detail
        };
        [_msg, "#ffb070"] call _setStatus;
        ["COMSPEC_Warning", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
        [format ["[Athena] Liaison incomplete : %1 (connect=%2)", _msg, _connectRaw]] call comspec_overwatch_connect_fnc_appendLinkLog;
    };
};

