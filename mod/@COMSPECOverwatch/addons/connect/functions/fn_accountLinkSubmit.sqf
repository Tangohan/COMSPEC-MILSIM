/*
    Lit URL + code du dialog, appelle RedeemGameLink (extension), persiste et Connect.
*/
if (!hasInterface) exitWith {};

private _display = uiNamespace getVariable ["COMSPEC_AccountLink_Display", displayNull];
if (isNull _display) exitWith {};

private _urlCtrl = _display displayCtrl 9201;
private _codeCtrl = _display displayCtrl 9202;
private _status = _display displayCtrl 9203;

private _url = if (!isNull _urlCtrl) then { trim (ctrlText _urlCtrl) } else { "" };
private _code = if (!isNull _codeCtrl) then { toUpper (trim (ctrlText _codeCtrl)) } else { "" };

private _setStatus = {
    params ["_text", ["_color", "#8aa0b4"]];
    if (!isNull _status) then {
        _status ctrlSetStructuredText parseText format ["<t align='center' size='0.58' color='%1'>%2</t>", _color, _text];
    };
};

if (_url isEqualTo "") exitWith {
    ["Indiquez l’adresse du portail Athena.", "#ff8a7a"] call _setStatus;
};
private _urlLower = toLower _url;
if (((_urlLower find "https://") != 0) && {(_urlLower find "http://") != 0}) exitWith {
    ["Adresse invalide — utilisez https://athena.ttrd.fr/public", "#ff8a7a"] call _setStatus;
};
if (_code isEqualTo "" || {count _code < 4}) exitWith {
    ["Saisissez le code généré sur Athena.", "#ff8a7a"] call _setStatus;
};

["Échange du code en cours…", "#8aa0b4"] call _setStatus;
[format ["[Athena] Échange du code vers %1…", [_url] call comspec_overwatch_connect_fnc_portalLabel]] call comspec_overwatch_connect_fnc_appendLinkLog;

private _steamUid = getPlayerUID player;
private _raw = ["COMSPECExtension" callExtension ["RedeemGameLink", [_url, _code, _steamUid]]] call comspec_overwatch_connect_fnc_extResult;
private _parts = _raw splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };

if (_prefix != "OK") exitWith {
    private _err = if (count _parts >= 2) then { _parts select 1 } else { _raw };
    private _msg = switch (_err) do {
        case "invalid": { "Adresse ou code invalide." };
        case "code_invalid_or_expired": { "Code invalide, déjà utilisé ou expiré — générez-en un nouveau sur Athena." };
        case "invalid_code": { "Code manquant ou trop court." };
        case "not_found": { "Adresse Athena incorrecte (page introuvable). Vérifiez le /public." };
        case "timeout": { "Délai dépassé — vérifiez votre réseau." };
        case "network": { "Impossible de joindre Athena." };
        case "invalid_response": { "Réponse inattendue d’Athena." };
        case "http_503": { "Liaison pas encore activée sur le portail (mise à jour serveur requise). Réessayez plus tard." };
        case "http_500": { "Erreur interne du portail. Réessayez dans un instant." };
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
    ["COMSPEC_Warning", [_msg]] call BIS_fnc_showNotification;
};

private _payload = if (count _parts >= 2) then { _parts select 1 } else { "" };
private _cols = _payload splitString "\t";
if (count _cols < 2) exitWith {
    ["Réponse incomplète d’Athena.", "#ff8a7a"] call _setStatus;
    ["[Athena] Réponse incomplète après échange du code."] call comspec_overwatch_connect_fnc_appendLinkLog;
};

private _apiUrl = _cols select 0;
private _apiKey = _cols select 1;
private _tenantId = if (count _cols >= 3) then { _cols select 2 } else { "" };

if (_apiUrl isEqualTo "") then { _apiUrl = _url; };

missionNamespace setVariable ["comspec_overwatch_api_url", _apiUrl];
missionNamespace setVariable ["comspec_overwatch_api_key", _apiKey];
if (!(_tenantId isEqualTo "")) then {
    missionNamespace setVariable ["comspec_overwatch_tenant_id", _tenantId];
};

profileNamespace setVariable ["comspec_overwatch_saved_api_url", _apiUrl];
profileNamespace setVariable ["comspec_overwatch_saved_api_key", _apiKey];
profileNamespace setVariable ["comspec_overwatch_saved_tenant_id", _tenantId];
saveProfileNamespace;

if (!isNil "cba_settings_fnc_set") then {
    ["comspec_overwatch_api_url", _apiUrl, 0, true] call cba_settings_fnc_set;
    ["comspec_overwatch_api_key", _apiKey, 0, true] call cba_settings_fnc_set;
    if (!(_tenantId isEqualTo "")) then {
        ["comspec_overwatch_tenant_id", _tenantId, 0, true] call cba_settings_fnc_set;
    };
};

["[Athena] Code accepté — établissement de la liaison…"] call comspec_overwatch_connect_fnc_appendLinkLog;
[] call comspec_overwatch_connect_fnc_connect;

private _state = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
if (_state isEqualTo "linked") then {
    ["Compte lié — liaison Athena établie.", "#7dffb0"] call _setStatus;
    ["COMSPEC_Info", ["Compte Athena connecté."]] call BIS_fnc_showNotification;
    uiSleep 0.8;
    closeDialog 0;
} else {
    private _detail = missionNamespace getVariable ["COMSPEC_LinkDetail", ""];
    private _msg = if (_detail isEqualTo "") then { "Paramètres enregistrés, mais le portail ne répond pas encore." } else { _detail };
    [_msg, "#ffb070"] call _setStatus;
    ["COMSPEC_Warning", [_msg]] call BIS_fnc_showNotification;
};
