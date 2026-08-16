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
        ["COMSPEC_AthenaLinkChanged", ["linked"]] call CBA_fnc_localEvent;
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
        ["WARN", "Tx", format ["Rate limit — pause %1 s", round _next], _msg] call comspec_overwatch_connect_fnc_log;
        [format ["[Athena] %1 (pause %2 s)", _msg, round _next], "system"] call comspec_overwatch_connect_fnc_appendLinkLog;
        if ([] call comspec_overwatch_connect_fnc_shouldShowScreenNotification) then {
            systemChat format ["[Athena] Synchronisation ralentie (%1 s).", round _next];
        };
    };
    case "RateLimitClear": {
        missionNamespace setVariable ["COMSPEC_ApiBackoffSec", 2, false];
        missionNamespace setVariable ["COMSPEC_ApiBackoffUntil", 0, false];
    };
    case "BftIdentity": {
        // data = "indicatif\tID_BFT" — lie le suivi Blue Force à l’indicatif Athena
        private _parts = _data splitString toString [9];
        private _cs = if (count _parts > 0) then { trim (_parts select 0) } else { "" };
        private _mid = if (count _parts > 1) then { trim (_parts select 1) } else { "" };
        if (_mid != "") then {
            missionNamespace setVariable ["COMSPEC_MilitaryId", _mid, false];
            missionNamespace setVariable ["COMSPEC_BftId", _mid, false];
            profileNamespace setVariable ["COMSPEC_MilitaryId", _mid];
        };
        if (_cs != "" && {!((toLower _cs) in ["unknown", "inconnu", "operateur"])}) then {
            private _local = trim (missionNamespace getVariable ["COMSPEC_Callsign", ""]);
            if (_local isEqualTo "" || {(toLower _local) in ["unknown", "inconnu", "operateur"]} || {_local isEqualTo (name player)}) then {
                [_cs, true, "bft_athena"] call comspec_overwatch_connect_fnc_setCallsign;
            } else {
                // Indicatif déjà choisi : aligner seulement le groupe BFT si besoin
                if (!isNull player && {local player}) then {
                    private _grp = group player;
                    if (!isNull _grp && {local _grp}) then {
                        private _gid = trim (groupId _grp);
                        if (!(_gid isEqualTo _local)) then {
                            _grp setGroupIdGlobal [_local];
                        };
                    };
                };
            };
        };
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
        ["google_deck_error", _human, format ["%1|%2", _code, _message], "Athena", "ERROR"] call comspec_overwatch_connect_fnc_logFnError;
        ["COMSPEC_Warning", [_human]] call comspec_overwatch_connect_fnc_showNotification;
    };
    case "PhotoUpload": {
        // DLL worker → data = "OK|uploaded|file.jpg" | "OK|duplicate|…" | "ERR|reason|file|…"
        private _parts = _data splitString "|";
        private _status = if ((count _parts) > 0) then { _parts select 0 } else { "" };
        private _detail = if ((count _parts) > 1) then { _parts select 1 } else { "" };
        private _fileHint = if ((count _parts) > 2) then { _parts select 2 } else { _detail };
        if (_fileHint isEqualTo "") then { _fileHint = _detail; };
        private _hintLow = toLower _fileHint;

        private _fnc_matchKeys = {
            params ["_list", "_hint"];
            if (!(_list isEqualType [])) exitWith { [] };
            if (_hint isEqualTo "") exitWith { [] };
            private _out = [];
            {
                private _segs = _x splitString "\/";
                private _base = toLower (_segs select ((count _segs) - 1));
                if (_base isEqualTo _hint || {(_x find _hint) >= 0}) then { _out pushBack _x; };
            } forEach _list;
            _out
        };

        private _pending = missionNamespace getVariable ["COMSPEC_Athena_PhotoPending", []];
        if (!(_pending isEqualType [])) then { _pending = []; };
        private _uploaded = missionNamespace getVariable ["COMSPEC_Athena_PhotoUploaded", []];
        if (!(_uploaded isEqualType [])) then { _uploaded = []; };
        private _failed = missionNamespace getVariable ["COMSPEC_Athena_PhotoFailed", []];
        if (!(_failed isEqualType [])) then { _failed = []; };
        private _matched = [_pending, _hintLow] call _fnc_matchKeys;
        if ((count _matched) < 1) then { _matched = [_uploaded, _hintLow] call _fnc_matchKeys; };
        if ((count _matched) < 1 && {_hintLow isNotEqualTo ""}) then { _matched = [_hintLow]; };

        if (_status isEqualTo "OK" && {_detail in ["uploaded", "duplicate"]}) then {
            {
                private _k = _x;
                _pending = _pending - [_k];
                _failed = _failed - [_k];
                if !(_k in _uploaded) then { _uploaded pushBack _k; };
            } forEach _matched;
            while { (count _uploaded) > 100 } do { _uploaded deleteAt 0; };
            missionNamespace setVariable ["COMSPEC_Athena_PhotoPending", _pending, false];
            missionNamespace setVariable ["COMSPEC_Athena_PhotoFailed", _failed, false];
            missionNamespace setVariable ["COMSPEC_Athena_PhotoUploaded", _uploaded, false];
            ["PhotoUpload", "ok", format ["%1 · %2", _detail, _fileHint], nil, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
            if (_detail isEqualTo "uploaded") then {
                private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];
                if (!(_inbox isEqualType [])) then { _inbox = []; };
                private _cs = "";
                if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
                    _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
                };
                if (_cs isEqualTo "") then { _cs = name player; };
                private _summary = if (_fileHint isEqualTo "") then {
                    "Photo reçue sur ATAK web"
                } else {
                    format ["Photo reçue sur ATAK web — %1", _fileHint]
                };
                _inbox pushBack ["PHOTO", "Photo remontée", _summary, "", [daytime, "HH:MM"] call BIS_fnc_timeToString, _cs];
                while { (count _inbox) > 40 } do { _inbox deleteAt 0; };
                missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];
                ["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;
            };
            if (!isNil "comspec_overwatch_atak_athena_fnc_athena_updatePanel") then {
                [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
            };
        } else {
            if (_status isEqualTo "ERR") then {
                {
                    private _k = _x;
                    _pending = _pending - [_k];
                    _uploaded = _uploaded - [_k];
                    if !(_k in _failed) then { _failed pushBack _k; };
                } forEach _matched;
                while { (count _failed) > 100 } do { _failed deleteAt 0; };
                missionNamespace setVariable ["COMSPEC_Athena_PhotoPending", _pending, false];
                missionNamespace setVariable ["COMSPEC_Athena_PhotoUploaded", _uploaded, false];
                missionNamespace setVariable ["COMSPEC_Athena_PhotoFailed", _failed, false];
                // Persister les introuvables : après crash / PreInit, PhotoSeen est vide
                // et les vieux clichés Photo Library ne doivent pas relancer un scan DLL.
                if ((_detail find "file_not_found") == 0) then {
                    private _dead = profileNamespace getVariable ["COMSPEC_Athena_PhotoDead", []];
                    if (!(_dead isEqualType [])) then { _dead = []; };
                    {
                        private _k = toLower _x;
                        if !(_k in _dead) then { _dead pushBack _k; };
                        private _segs = _k splitString "\/";
                        private _base = _segs select ((count _segs) - 1);
                        if (_base isNotEqualTo "" && {!(_base in _dead)}) then { _dead pushBack _base; };
                    } forEach _matched;
                    if (_hintLow isNotEqualTo "" && {!(_hintLow in _dead)}) then { _dead pushBack _hintLow; };
                    while { (count _dead) > 200 } do { _dead deleteAt 0; };
                    profileNamespace setVariable ["COMSPEC_Athena_PhotoDead", _dead];
                    saveProfileNamespace;
                };
                ["PhotoUpload", "fail", format ["%1 · %2", _detail, _fileHint], _data, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
                private _msg = switch (true) do {
                    case ((_detail find "file_not_found") == 0): { "Photo introuvable sur le disque — capturez à nouveau." };
                    case ((_detail find "not_connected") == 0): { "Pas de liaison Athena — reconnectez-vous puis renvoyez." };
                    case ((_detail find "http_") == 0): { "Le poste de commandement a refusé la photo. Réessayez." };
                    case ((_detail find "network") == 0): { "Liaison réseau instable pendant l’envoi de la photo." };
                    default { "Échec d’envoi de la photo vers ATAK web." };
                };
                if (!isNil "comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback") then {
                    [_msg, "error", 8] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
                };
                if (!isNil "comspec_overwatch_atak_athena_fnc_athena_updatePanel") then {
                    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
                };
            };
        };
    };

    case "PostError": {
        // DLL → échec HTTP fire-and-forget (position, marqueurs, chat async…)
        // data = "code|path|ageSec"
        private _parts = _data splitString "|";
        private _code = if ((count _parts) > 0) then { _parts select 0 } else { "?" };
        private _path = if ((count _parts) > 1) then { _parts select 1 } else { "" };
        private _age = if ((count _parts) > 2) then { _parts select 2 } else { "0" };
        private _label = if (_path isEqualTo "") then { "POST" } else { _path };
        ["HTTP POST", "fail", format ["code %1 · %2 (il y a %3 s)", _code, _label, _age], _data, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
    };
    case "NetworkDisconnected": {
        missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
        missionNamespace setVariable ["COMSPEC_LinkDetail", "Liaison interrompue", false];
        ["WARN", "Athena", "Liaison interrompue", _data] call comspec_overwatch_connect_fnc_log;
        [format ["[Athena] %1", if (_data isEqualTo "") then { "Liaison interrompue" } else { _data }], "system"] call comspec_overwatch_connect_fnc_appendLinkLog;
        ["disconnect"] call comspec_overwatch_connect_fnc_playAtakEnhancedSound;
        [] call comspec_overwatch_connect_fnc_updateStatusBadges;
    };
    case "NetworkReconnected": {
        missionNamespace setVariable ["COMSPEC_LinkState", "linked", false];
        missionNamespace setVariable ["COMSPEC_LinkDetail", "", false];
        missionNamespace setVariable ["COMSPEC_LastHealthOk", diag_tickTime, false];
        ["INFO", "Athena", "Liaison rétablie"] call comspec_overwatch_connect_fnc_log;
        [format ["[Athena] Liaison rétablie"], "system"] call comspec_overwatch_connect_fnc_appendLinkLog;
        ["reconnect"] call comspec_overwatch_connect_fnc_playAtakEnhancedSound;
        [] call comspec_overwatch_connect_fnc_updateStatusBadges;
    };
    default {};
};
