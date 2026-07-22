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



private _url = if (!isNull _urlCtrl) then { trim (ctrlText _urlCtrl) } else { "" };

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

    ["COMSPEC_Warning", [_msg]] call BIS_fnc_showNotification;

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

    ["COMSPEC_Warning", [_msg]] call BIS_fnc_showNotification;

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

    ["COMSPEC_Warning", [_msg]] call BIS_fnc_showNotification;

};



private _payload = if (count _parts >= 2) then { _parts select 1 } else { "" };

private _cols = _payload splitString "\t";

if (count _cols < 2) exitWith {

    ["Réponse incomplète d’Athena.", "#ff8a7a"] call _setStatus;

    ["[Athena] Réponse incomplète après liaison."] call comspec_overwatch_connect_fnc_appendLinkLog;

};



private _apiUrl = _cols select 0;

private _apiKey = _cols select 1;

private _tenantId = if (count _cols >= 3) then { _cols select 2 } else { "" };

if (_apiUrl isEqualTo "") then { _apiUrl = _url; };

if (_apiKey isEqualTo "") exitWith {
    ["Liaison refusée : le portail n’a pas renvoyé de clé d’accès. Contactez un administrateur Athena.", "#ff8a7a"] call _setStatus;
    ["[Athena] Réponse OK sans clé d’accès — configuration portail incomplète."] call comspec_overwatch_connect_fnc_appendLinkLog;
    ["COMSPEC_Warning", ["Liaison refusée : clé d’accès manquante côté portail."]] call BIS_fnc_showNotification;
};

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



if (!isNil "cba_settings_fnc_set") then {

    ["comspec_overwatch_api_url", _apiUrl, 0, true] call cba_settings_fnc_set;

    ["comspec_overwatch_api_key", _apiKey, 0, true] call cba_settings_fnc_set;

    if (!(_tenantId isEqualTo "")) then {

        ["comspec_overwatch_tenant_id", _tenantId, 0, true] call cba_settings_fnc_set;

    };

};



if (_useSteam) then {

    ["[Athena] Steam reconnu — établissement de la liaison…"] call comspec_overwatch_connect_fnc_appendLinkLog;

} else {

    ["[Athena] Code accepté — établissement de la liaison…"] call comspec_overwatch_connect_fnc_appendLinkLog;

};

[] call comspec_overwatch_connect_fnc_connect;



private _state = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];

if (_state isEqualTo "linked") then {

    ["Compte lié — liaison Athena établie.", "#7dffb0"] call _setStatus;

    ["COMSPEC_Info", ["Compte Athena connecté."]] call BIS_fnc_showNotification;

    [] call comspec_overwatch_connect_fnc_updateLinkDiary;

    uiSleep 0.8;

    closeDialog 0;

} else {

    private _detail = missionNamespace getVariable ["COMSPEC_LinkDetail", ""];

    private _msg = if (_detail isEqualTo "") then { "Paramètres enregistrés, mais le portail ne répond pas encore." } else { _detail };

    [_msg, "#ffb070"] call _setStatus;

    ["COMSPEC_Warning", [_msg]] call BIS_fnc_showNotification;

};

