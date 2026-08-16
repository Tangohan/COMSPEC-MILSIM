/*
    Envoie vers Athena la photo sélectionnée dans le journal Athena (onglet Photos),
    ou la dernière capture locale disponible.

    Réutilise le bridge (file Pending + ACK PhotoUpload) — pas un appel DLL orphelin.
*/
if (!hasInterface) exitWith {};

if (isNil "comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanPhoto") exitWith {
    [
        "Le module photo n’est pas disponible pour le moment.",
        "error",
        5
    ] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
};

private _path = "";
private _fileName = "";

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
        };
    };
};

if (_path isEqualTo "" && {_fileName isNotEqualTo ""}) then {
    _path = _fileName;
};

if (_path isEqualTo "") exitWith {
    [
        "Aucune photo à renvoyer — prenez d’abord une vue depuis l’app Photos d’ATAK (elle remonte seule vers ATAK web).",
        "warn",
        7
    ] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
};

if (_fileName isEqualTo "") then {
    private _segs = _path splitString "\/";
    _fileName = _segs select ((count _segs) - 1);
};

private _key = toLower _path;
private _base = toLower _fileName;

// Autoriser un nouvel essai : retirer des listes Dead / Failed / Seen.
private _fnc_scrub = {
    params ["_listName", "_ns"];
    private _list = if (_ns isEqualTo "profile") then {
        profileNamespace getVariable [_listName, []]
    } else {
        missionNamespace getVariable [_listName, []]
    };
    if (!(_list isEqualType [])) then { _list = []; };
    private _before = count _list;
    _list = _list - [_key, _base];
    if ((count _list) isNotEqualTo _before) then {
        if (_ns isEqualTo "profile") then {
            profileNamespace setVariable [_listName, _list];
            saveProfileNamespace;
        } else {
            missionNamespace setVariable [_listName, _list, false];
        };
    };
};

["COMSPEC_Athena_PhotoDead", "profile"] call _fnc_scrub;
["COMSPEC_Athena_PhotoFailed", "mission"] call _fnc_scrub;
["COMSPEC_Athena_PhotoSeen", "mission"] call _fnc_scrub;
["COMSPEC_Athena_PhotoUploaded", "mission"] call _fnc_scrub;

// Force = true : le bridge ignore le blocage Dead/Failed pour ce renvoi manuel.
private _ok = [_path, _fileName, false, true] call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanPhoto;
if (!(_ok isEqualType true)) then { _ok = false; };

if (!_ok && {_fileName isNotEqualTo ""} && {(toLower _fileName) isNotEqualTo (toLower _path)}) then {
    private _detail = toLower (str (missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", ""]));
    if ((_detail find "file_not_found") >= 0 || {_detail isEqualTo ""}) then {
        ["COMSPEC_Athena_PhotoDead", "profile"] call _fnc_scrub;
        ["COMSPEC_Athena_PhotoFailed", "mission"] call _fnc_scrub;
        ["COMSPEC_Athena_PhotoSeen", "mission"] call _fnc_scrub;
        _ok = [_fileName, _fileName, false, true] call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanPhoto;
        if (!(_ok isEqualType true)) then { _ok = false; };
    };
};

if (_ok) then {
    private _raw = toUpper (str (missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", ""]));
    if ((_raw find "OK|DUPLICATE") == 0) then {
        [
            "Cette photo a déjà été tentée récemment — attendez un instant ou capturez une nouvelle vue.",
            "warn",
            7
        ] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
    } else {
        [
            "Photo mise en file vers Athena — confirmation dès réception web.",
            "ok",
            5
        ] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
    };
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
