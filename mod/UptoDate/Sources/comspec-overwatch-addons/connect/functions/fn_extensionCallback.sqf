/*
    Callbacks async de COMSPECExtension (RVExtensionRegisterCallback).
    name = "comspec", function = Connected | Error | Debug
    Note : Connect synchrone (1.11+) valide deja l'auth — ces callbacks sont un filet de secours.
*/
params ["_name", "_function", ["_data", ""]];
if (_name != "comspec") exitWith {};

if (!(_function isEqualType "")) then { _function = str _function; };
if (!(_data isEqualType "")) then { _data = str _data; };

switch (_function) do {
    case "Connected": {
        // N'ecrase pas un echec auth deja constate (race async ancienne DLL).
        private _keyLen = count (missionNamespace getVariable ["comspec_overwatch_api_key", ""]);
        if (_keyLen < 1) exitWith {};
        private _uri = _data;
        missionNamespace setVariable ["COMSPEC_LinkState", "linked", false];
        missionNamespace setVariable ["COMSPEC_LinkDetail", "", false];
        missionNamespace setVariable ["COMSPEC_LastHealthOk", diag_tickTime, false];
        private _label = [_uri] call comspec_overwatch_connect_fnc_portalLabel;
        [format ["[Athena] Connecte a %1", _label], "system"] call comspec_overwatch_connect_fnc_appendLinkLog;
        // Pas de bandeau / chat pendant le handshake au démarrage (1 message max ailleurs)
        if (
            !(missionNamespace getVariable ["COMSPEC_HandshakeQuiet", false])
            && {[] call comspec_overwatch_connect_fnc_shouldShowScreenNotification}
        ) then {
            systemChat format ["[Athena] Connecte a %1", _label];
            ["COMSPEC_Info", [format ["Connecte a %1", _label]]] call comspec_overwatch_connect_fnc_showNotification;
        };
        [] call comspec_overwatch_connect_fnc_updateLinkDiary;
        [] call comspec_overwatch_connect_fnc_updateStatusBadges;
    };
    case "Error": {
        private _msg = if (!(_data isEqualTo "")) then { _data } else { "Echec de liaison" };
        missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
        missionNamespace setVariable ["COMSPEC_LinkDetail", _msg, false];
        ["WARN", "Athena", format ["Extension Error: %1", _msg]] call comspec_overwatch_connect_fnc_log;
        [format ["[Athena] %1", _msg], "system"] call comspec_overwatch_connect_fnc_appendLinkLog;
        if ([] call comspec_overwatch_connect_fnc_shouldShowScreenNotification) then {
            systemChat format ["[Athena] %1", _msg];
        };
        ["COMSPEC_Warning", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
        [] call comspec_overwatch_connect_fnc_updateStatusBadges;
    };
    case "RateLimited": {
        // Backoff exponentiel côté SQF (la DLL applique aussi un délai d’envoi).
        private _prev = missionNamespace getVariable ["COMSPEC_ApiBackoffSec", 2];
        if (!(_prev isEqualType 0)) then { _prev = 2; };
        private _next = (_prev * 2) min 60;
        missionNamespace setVariable ["COMSPEC_ApiBackoffSec", _next, false];
        missionNamespace setVariable ["COMSPEC_ApiBackoffUntil", diag_tickTime + _next, false];
        private _msg = if (!(_data isEqualTo "")) then { _data } else {
            "Athena est saturé — synchronisation ralentie quelques instants."
        };
        [format ["[Athena] %1 (pause %2 s)", _msg, round _next], "system"] call comspec_overwatch_connect_fnc_appendLinkLog;
        if ([] call comspec_overwatch_connect_fnc_shouldShowScreenNotification) then {
            systemChat format ["[Athena] Synchronisation ralentie (%1 s).", round _next];
        };
    };
    case "RateLimitClear": {
        missionNamespace setVariable ["COMSPEC_ApiBackoffSec", 2, false];
        missionNamespace setVariable ["COMSPEC_ApiBackoffUntil", 0, false];
    };
    case "Debug": {
        if (!(_data isEqualTo "")) then {
            ["DEBUG", "Ext", _data] call comspec_overwatch_connect_fnc_log;
            [format ["[Debug] %1", _data], "system"] call comspec_overwatch_connect_fnc_appendLinkLog;
        };
    };
    case "google_deck_ready": {
        private _payload = parseSimpleArray _data;
        private _requestId = _payload param [0, ""];
        private _current = missionNamespace getVariable ["COMSPEC_GoogleBriefingRequestId", ""];
        if (_requestId isNotEqualTo "" && {_current isNotEqualTo ""} && {_requestId isNotEqualTo _current}) exitWith {};

        private _presentationId = _payload param [1, ""];
        private _index = _payload param [2, 0];
        private _total = _payload param [3, 1];
        private _path = _payload param [4, ""];
        private _manifestComplete = _payload param [6, false];

        if (_path isEqualTo "") exitWith {
            missionNamespace setVariable ["COMSPEC_GoogleBriefingRequestId", ""];
            ["COMSPEC_Warning", ["Présentation Google renvoyée sans image."]] call comspec_overwatch_connect_fnc_showNotification;
        };

        missionNamespace setVariable ["COMSPEC_GoogleBriefingActive", true];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingPresentationId", _presentationId];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingIndex", _index];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingTotal", _total];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingPath", _path];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingRequestId", ""];

        if (isNull (findDisplay 9970)) then {
            createDialog "COMSPEC_Briefing_Dialog";
        };

        [
            _path,
            format ["Google Slides — diapositive %1", _index + 1],
            _index,
            _total
        ] call comspec_overwatch_connect_fnc_applyGoogleBriefingSlide;

        private _msg = format ["Diapositive %1 sur %2 chargée.", _index + 1, _total];
        ["COMSPEC_Info", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
        if (!_manifestComplete && {_total <= 1}) then {
            ["COMSPEC_Warning", ["Liste des diapositives incomplète — navigation limitée."]] call comspec_overwatch_connect_fnc_showNotification;
        };
    };
    case "google_slide_ready": {
        private _payload = parseSimpleArray _data;
        private _requestId = _payload param [0, ""];
        private _current = missionNamespace getVariable ["COMSPEC_GoogleBriefingRequestId", ""];
        if (_requestId isNotEqualTo "" && {_current isNotEqualTo ""} && {_requestId isNotEqualTo _current}) exitWith {};

        private _path = _payload param [1, ""];
        private _index = _payload param [2, 0];
        private _total = _payload param [3, 1];

        if (_path isEqualTo "") exitWith {
            missionNamespace setVariable ["COMSPEC_GoogleBriefingRequestId", ""];
        };

        missionNamespace setVariable ["COMSPEC_GoogleBriefingActive", true];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingIndex", _index];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingTotal", _total];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingPath", _path];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingRequestId", ""];

        [
            _path,
            format ["Google Slides — diapositive %1", _index + 1],
            _index,
            _total
        ] call comspec_overwatch_connect_fnc_applyGoogleBriefingSlide;
    };
    case "google_deck_error": {
        private _payload = parseSimpleArray _data;
        private _requestId = _payload param [0, ""];
        private _current = missionNamespace getVariable ["COMSPEC_GoogleBriefingRequestId", ""];
        if (_requestId isNotEqualTo "" && {_current isNotEqualTo ""} && {_requestId isNotEqualTo _current}) exitWith {};

        private _code = _payload param [1, "unknown"];
        private _message = _payload param [2, "Échec du chargement."];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingRequestId", ""];

        private _human = switch (_code) do {
            case "private": { "Présentation inaccessible ou non publique." };
            case "not_found": { "Présentation introuvable." };
            case "parse_failed": { "Impossible de lire la présentation (format Google modifié)." };
            case "network": { "Réseau indisponible pour charger la présentation." };
            case "cancelled": { "Chargement annulé." };
            default { if (_message isEqualTo "") then { "Échec du chargement Google Slides." } else { _message } };
        };
        ["COMSPEC_Warning", [_human]] call comspec_overwatch_connect_fnc_showNotification;
    };
    default {};
};
