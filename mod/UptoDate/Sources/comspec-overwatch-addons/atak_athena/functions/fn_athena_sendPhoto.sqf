/*
    Envoie vers Athena la photo sélectionnée dans le journal Athena (onglet Photos),
    ou la dernière capture locale disponible.
*/
if (!hasInterface) exitWith {};

if (isNil "comspec_overwatch_connect_fnc_captureReconImage") exitWith {
    [
        "Le module photo n’est pas disponible pour le moment.",
        "error",
        5
    ] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
};

private _path = "";
private _fileName = "";
private _caption = "";

private _listCtrl = controlNull;
private _group = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
if (!isNull _group) then { _listCtrl = _group controlsGroupCtrl 9710; };

if (!isNull _listCtrl) then {
    private _sel = lbCurSel _listCtrl;
    private _entries = _listCtrl getVariable ["COMSPEC_Athena_Entries", []];
    if (_sel >= 0 && {_sel < count _entries}) then {
        (_entries select _sel) params ["_kind", "", "", "", ["_meta", []]];
        if (_kind isEqualTo "photo" && {(_meta isEqualType []) && {(count _meta) >= 1}}) then {
            _path = _meta select 0;
            if ((count _meta) >= 2) then {
                _fileName = _meta select 1;
                _caption = format ["Photo ATAK — %1", _fileName];
            };
        };
    };
};

// Repli : collecteur unifié (Photo Library + cache Quick Pictures)
if (_path isEqualTo "" && {!isNil "comspec_overwatch_atak_athena_fnc_athena_collectLocalPhotos"}) then {
    private _photos = [] call comspec_overwatch_atak_athena_fnc_athena_collectLocalPhotos;
    if ((_photos isEqualType []) && {(count _photos) > 0}) then {
        private _rec = _photos select 0;
        if ((_rec isEqualType []) && {(count _rec) > 0}) then {
            _path = _rec select 0;
            _fileName = if ((count _rec) > 1) then { _rec select 1 } else { "" };
            private _g = if ((count _rec) > 2) then { _rec select 2 } else { mapGridPosition player };
            _caption = format ["Photo ATAK Enhanced — grille %1 (%2)", _g, _fileName];
        };
    };
};

// Si le chemin stocké est vide / inutilisable, remonter via le nom de fichier (résolu côté extension).
if (_path isEqualTo "" && {_fileName isNotEqualTo ""}) then {
    _path = _fileName;
};

if (_path isEqualTo "") exitWith {
    [
        "Aucune photo à remonter — ouvrez l’onglet Photos, sélectionnez une capture, ou prenez d’abord une vue depuis l’app Photos d’ATAK.",
        "warn",
        7
    ] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
};

if (_caption isEqualTo "") then {
    _caption = format ["Photo ATAK Enhanced — grille %1", mapGridPosition player];
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
} else {
    private _hasHcam = ("ItemcTabHCam" in (items player + assignedItems player))
        || {((headgear player) in (missionNamespace getVariable ["cTab_helmetClass_has_HCam", []]))};
    if (_hasHcam) then {
        _device = "HELMET";
        _feedId = format ["helmet:%1", getPlayerUID player];
    };
};

private _ok = [_path, _caption, _device, _feedId] call comspec_overwatch_connect_fnc_captureReconImage;
if (!(_ok isEqualType true)) then { _ok = false; };

// Second essai : nom de fichier seul (Iceman affiche souvent .jpg alors qu’Arma écrit .png).
if (!_ok && {_fileName isNotEqualTo ""} && {(toLower _fileName) isNotEqualTo (toLower _path)}) then {
    private _detail = toLower (str (missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", ""]));
    if ((_detail find "file_not_found") >= 0) then {
        _ok = [_fileName, _caption, _device, _feedId] call comspec_overwatch_connect_fnc_captureReconImage;
        if (!(_ok isEqualType true)) then { _ok = false; };
    };
};

if (_ok) then {
    [
        "Photo envoyée vers Athena.",
        "ok",
        5
    ] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
} else {
    private _detail = missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", ""];
    private _msg = "L’envoi de la photo a échoué.";
    private _d = toLower (str _detail);
    if ((_d find "file_not_found") >= 0) then {
        _msg = "Fichier introuvable sur le poste — reprenez une vue depuis l’app Photos d’ATAK, puis renvoyez.";
    };
    if ((_d find "file_too_large") >= 0) then {
        _msg = "La photo est trop volumineuse pour être transmise. Reprenez une vue plus légère, puis renvoyez.";
    };
    if ((_d find "not_connected") >= 0) then {
        _msg = "Pas de liaison Athena active — reconnectez-vous depuis le panneau Liaison, puis renvoyez.";
    };
    if ((_d find "network") >= 0 || {(_d find "timeout") >= 0}) then {
        _msg = "Liaison dégradée — la photo n’a pas pu partir. Réessayez dans un instant.";
    };
    [
        _msg,
        "error",
        8
    ] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
};

[] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
