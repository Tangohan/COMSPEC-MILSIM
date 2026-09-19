/*
    Transfère vers le poste la photo sélectionnée, ou toutes les photos locales.
    Params: [_all]
*/
params [["_all", false, [true]]];

if (!hasInterface) exitWith { false };

if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {
    if (!isNil "cTab_fnc_addNotification") then {
        ["PHOTOS", "Pas de liaison Athena — reconnectez-vous, puis transférez.", 6] call cTab_fnc_addNotification;
    };
    false
};

if (isNil "comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanPhoto") exitWith {
    if (!isNil "cTab_fnc_addNotification") then {
        ["PHOTOS", "Transfert vers le poste indisponible pour le moment.", 4] call cTab_fnc_addNotification;
    };
    false
};

private _fnc_notifyFail = {
    params ["_fallback"];
    private _detail = toLower (str (missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", ""]));
    private _msg = _fallback;
    if ((_detail find "file_not_found") >= 0) then {
        _msg = "Fichier introuvable — reprenez une vue, puis transférez.";
    };
    if ((_detail find "not_connected") >= 0) then {
        _msg = "Pas de liaison Athena — reconnectez-vous, puis transférez.";
    };
    if (!isNil "cTab_fnc_addNotification") then {
        ["PHOTOS", _msg, 6] call cTab_fnc_addNotification;
    };
};

private _fnc_sendOne = {
    params ["_record", ["_silent", false]];
    if (!(_record isEqualType []) || {_record isEqualTo []}) exitWith { false };

    private _path = _record param [2, ""];
    private _fileName = _record param [3, ""];
    if (_path isEqualTo "" && {_fileName isNotEqualTo ""}) then { _path = _fileName; };
    if (_path isEqualTo "") exitWith { false };
    if (_fileName isEqualTo "") then {
        private _segs = _path splitString "\/";
        _fileName = _segs select ((count _segs) - 1);
    };

    private _upload = _path;
    if (!isNil "comspec_overwatch_connect_fnc_extResult") then {
        private _raw = ["COMSPECExtension" callExtension ["StageCapture", [_path]]] call comspec_overwatch_connect_fnc_extResult;
        if ((_raw isEqualType "") && {((count _raw) >= 4)} && {(_raw select [0, 3]) isEqualTo "OK|"}) then {
            private _body = trim (_raw select [3, (count _raw) - 3]);
            if (_body isNotEqualTo "") then { _upload = _body; };
        };
    };

    private _ok = [_upload, _fileName, _silent, true] call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanPhoto;
    if (!(_ok isEqualType true)) then { _ok = false; };
    if (_ok && {!isNil "comspec_overwatch_atak_athena_fnc_athena_removeIcemanPhoto"}) then {
        [_record, !_silent] call comspec_overwatch_atak_athena_fnc_athena_removeIcemanPhoto;
    };
    _ok
};

if (_all) exitWith {
    if (missionNamespace getVariable ["COMSPEC_PhotoXferBusy", false]) exitWith { false };
    if (isNil "Iceman_fnc_photo_getRecords") exitWith { false };

    missionNamespace setVariable ["COMSPEC_PhotoXferBusy", true, false];
    [_fnc_sendOne, _fnc_notifyFail] spawn {
        params ["_fnc_sendOne", "_fnc_notifyFail"];
        private _records = [] call Iceman_fnc_photo_getRecords;
        if (!(_records isEqualType [])) then { _records = []; };
        private _locals = _records select {
            (_x isEqualType [])
            && {(_x param [1, ""]) isEqualTo "local"}
            && {((_x param [2, ""]) isNotEqualTo "") || {(_x param [3, ""]) isNotEqualTo ""}}
        };

        if (_locals isEqualTo []) exitWith {
            missionNamespace setVariable ["COMSPEC_PhotoXferBusy", false, false];
            if (!isNil "cTab_fnc_addNotification") then {
                ["PHOTOS", "Aucune photo locale à transférer.", 4] call cTab_fnc_addNotification;
            };
        };

        if (!isNil "cTab_fnc_addNotification") then {
            ["PHOTOS", format ["Transfert de %1 photo%2 vers le poste…", count _locals, ["", "s"] select ((count _locals) > 1)], 4] call cTab_fnc_addNotification;
        };

        private _sent = 0;
        {
            if ([_x, true] call _fnc_sendOne) then { _sent = _sent + 1; };
            uiSleep 0.35;
        } forEach _locals;

        if (_sent > 0 && {!isNil "Iceman_fnc_photo_refresh"}) then {
            [] call Iceman_fnc_photo_refresh;
        };

        missionNamespace setVariable ["COMSPEC_PhotoXferBusy", false, false];
        if (_sent > 0) then {
            if (!isNil "cTab_fnc_addNotification") then {
                ["PHOTOS", format ["%1 photo%2 transférée%2 vers le poste.", _sent, ["", "s"] select (_sent > 1)], 5] call cTab_fnc_addNotification;
            };
            playSound "cTab_mailSent";
        } else {
            ["L’envoi vers le poste a échoué."] call _fnc_notifyFail;
        };
    };
    true
};

private _record = [];
if (!isNil "Iceman_fnc_photo_getSelectedRecord") then {
    _record = [] call Iceman_fnc_photo_getSelectedRecord;
};
if (!(_record isEqualType []) || {_record isEqualTo []}) exitWith {
    if (!isNil "cTab_fnc_addNotification") then {
        ["PHOTOS", "Sélectionnez d’abord une photo.", 3] call cTab_fnc_addNotification;
    };
    false
};

private _origin = _record param [1, ""];
if (_origin isEqualTo "received") exitWith {
    if (!isNil "cTab_fnc_addNotification") then {
        ["PHOTOS", "Cette photo vient d’un autre téléphone — transférez vos vues locales.", 5] call cTab_fnc_addNotification;
    };
    false
};

private _path = _record param [2, ""];
private _fileName = _record param [3, ""];
if (_path isEqualTo "" && {_fileName isNotEqualTo ""}) then { _path = _fileName; };
if (_path isEqualTo "") exitWith {
    if (!isNil "cTab_fnc_addNotification") then {
        ["PHOTOS", "Cette photo n’est pas disponible en local.", 4] call cTab_fnc_addNotification;
    };
    false
};

private _ok = [_record, false] call _fnc_sendOne;
if (_ok) then {
    if (!isNil "cTab_fnc_addNotification") then {
        ["PHOTOS", "Photo transférée vers le poste.", 4] call cTab_fnc_addNotification;
    };
    playSound "cTab_mailSent";
} else {
    ["L’envoi vers le poste a échoué."] call _fnc_notifyFail;
};
_ok
