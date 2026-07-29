/*

    Capture BCE / Photo Library → upload Athena (UploadReconImage) — automatique vers ATAK web.

    Params: [_filePath, _fileName, _silent]

      _silent : true = pas de bandeau succès (remontée auto) ; les erreurs restent visibles si non silencieux.

    Retour: true si l’envoi a été accepté, false sinon.

*/

params [

    ["_filePath", "", [""]],

    ["_fileName", "", [""]],

    ["_silent", false, [false]]

];



if (!hasInterface) exitWith { false };

if (!(["iceman_photo"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith { false };

if (_filePath isEqualTo "") exitWith { false };



if (!isNil "comspec_overwatch_atak_athena_fnc_athena_rememberLocalPhoto") then {

    [_filePath, _fileName] call comspec_overwatch_atak_athena_fnc_athena_rememberLocalPhoto;

};



if (isNil "comspec_overwatch_connect_fnc_captureReconImage") exitWith { false };



// Éviter double-upload si déjà traité dans la seconde

private _last = missionNamespace getVariable ["COMSPEC_Athena_LastPhotoUpload", ["", 0]];

if ((_last select 0) isEqualTo _filePath && { (diag_tickTime - (_last select 1)) < 5 }) exitWith { true };

missionNamespace setVariable ["COMSPEC_Athena_LastPhotoUpload", [_filePath, diag_tickTime], false];



private _grid = mapGridPosition player;

private _caption = format ["Photo ATAK Enhanced — grille %1", _grid];

if (_fileName isNotEqualTo "") then {

    _caption = _caption + format [" (%1)", _fileName];

};



private _device = "CTAB";

private _feedId = "";



private _droneState = missionNamespace getVariable ["Iceman_ATAK_DroneOps_state", createHashMap];

private _drone = objNull;

if (_droneState isEqualType createHashMap) then {

    _drone = _droneState getOrDefault ["drone", objNull];

};

if (isNull _drone) then {

    private _uav = getConnectedUAV player;

    if (!isNull _uav && {alive _uav}) then { _drone = _uav; };

};



if (!isNull _drone && {alive _drone}) then {

    _device = "DRONE";

    private _netId = netId _drone;

    if (_netId isEqualTo "") then { _netId = str _drone; };

    _feedId = format ["drone:%1", _netId];

    _caption = format ["Photo drone — grille %1", mapGridPosition _drone];

    if (_fileName isNotEqualTo "") then { _caption = _caption + format [" (%1)", _fileName]; };

};



private _ok = [_filePath, _caption, _device, _feedId] call comspec_overwatch_connect_fnc_captureReconImage;

if (!(_ok isEqualType true)) then { _ok = false; };

// Repli : le chemin BCE/Photo Library pointe souvent vers un .jpg qui n’existe pas
// (Arma_ScreenShot_Extension écrit un .png, ou n’écrit rien). On retente le poll Iceman
// au lieu de screenshot + « dernière capture » (doublon casque / tablette).
if (!_ok && {canSuspend}) then {
    private _detail = toLower (str (missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", ""]));
    if ((_detail find "file_not_found") >= 0) then {
        ["WARN", "Photo", "Cliché ATAK introuvable sur disque — repli poll Photo Library", _filePath] call comspec_overwatch_connect_fnc_log;
        ["UploadReconImage", "warn", "Repli poll Photo Library", _filePath, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
    };
};

if (!_ok) exitWith {

    if (!_silent) then {

        private _detail = missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", ""];

        private _msg = "L’envoi de la photo a échoué.";

        private _d = toLower (str _detail);

        if ((_d find "file_not_found") >= 0) then {

            _msg = "Cliché non trouvé dans Screenshots du profil Arma — vérifiez l’extension BCE (Arma_ScreenShot) et HDR ≥ 16.";

        };

        if ((_d find "file_too_large") >= 0) then {

            _msg = "La photo est trop volumineuse pour être transmise.";

        };

        if ((_d find "not_connected") >= 0) then {

            _msg = "Pas de liaison Athena active — reconnectez-vous, puis la photo partira automatiquement.";

        };

        if ((_d find "network") >= 0 || {(_d find "timeout") >= 0}) then {

            _msg = "Liaison dégradée — la photo n’a pas pu partir. Nouvel essai automatique sous peu.";

        };

        [_msg, "error", 8] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;

    };

    [format ["Échec photo (%1)", if (_fileName isEqualTo "") then { "sans nom" } else { _fileName }]] call comspec_overwatch_connect_fnc_appendModuleLog;

    false

};



[format ["Photo envoyée (%1)", _fileName]] call comspec_overwatch_connect_fnc_appendModuleLog;



private _key = toLower _filePath;

private _uploaded = missionNamespace getVariable ["COMSPEC_Athena_PhotoUploaded", []];

if (!(_uploaded isEqualType [])) then { _uploaded = []; };

if !(_key in _uploaded) then {

    _uploaded pushBack _key;

    while { (count _uploaded) > 100 } do { _uploaded deleteAt 0; };

    missionNamespace setVariable ["COMSPEC_Athena_PhotoUploaded", _uploaded, false];

};

private _seen = missionNamespace getVariable ["COMSPEC_Athena_PhotoSeen", []];

if (!(_seen isEqualType [])) then { _seen = []; };

if !(_key in _seen) then {

    _seen pushBack _key;

    while { (count _seen) > 100 } do { _seen deleteAt 0; };

    missionNamespace setVariable ["COMSPEC_Athena_PhotoSeen", _seen, false];

};



private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];

if (!(_inbox isEqualType [])) then { _inbox = []; };



private _cs = "";

if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {

    _cs = [] call comspec_overwatch_connect_fnc_getCallsign;

};

if (_cs isEqualTo "") then { _cs = name player; };



private _summary = switch (_device) do {

    case "DRONE": {

        if (_fileName isEqualTo "") then {

            format ["Vue drone — grille %1", _grid]

        } else {

            format ["Vue drone — %1 (grille %2)", _fileName, _grid]

        };

    };

    case "HELMET": {

        if (_fileName isEqualTo "") then {

            format ["Vue casque — grille %1", _grid]

        } else {

            format ["Vue casque — %1 (grille %2)", _fileName, _grid]

        };

    };

    default {

        if (_fileName isEqualTo "") then {

            format ["Remontée depuis ATAK — grille %1", _grid]

        } else {

            format ["Remontée depuis ATAK — %1 (grille %2)", _fileName, _grid]

        };

    };

};



private _dupPhoto = false;

if ((count _inbox) > 0) then {

    private _prev = _inbox select ((count _inbox) - 1);

    _dupPhoto = (_prev select 0) isEqualTo "PHOTO" && {(_prev select 2) isEqualTo _summary};

};

if (!_dupPhoto) then {

    _inbox pushBack [

        "PHOTO",

        "Photo remontée",

        _summary,

        _grid,

        [daytime, "HH:MM"] call BIS_fnc_timeToString,

        _cs

    ];

    while { (count _inbox) > 40 } do { _inbox deleteAt 0; };

    missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];

    ["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;

};



if (!_silent) then {

    [

        "Photo envoyée vers Athena.",

        "ok",

        5

    ] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;

    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;

} else {

    // Remontée auto : rafraîchir le panneau si ouvert, sans spam de bandeau

    private _group = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];

    if (!isNull _group && {ctrlShown _group}) then {

        [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;

    };

};



true

