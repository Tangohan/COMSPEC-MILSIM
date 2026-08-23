/*
    Ajoute « Poste Athena » dans Photo Library et envoie la photo choisie au poste.
*/
if (!hasInterface) exitWith {};

private _wrap = {
    if (!isNil "COMSPEC_PhotoLibraryAthenaWrapped") exitWith { true };
    if (isNil "Iceman_fnc_photo_refresh" || {isNil "Iceman_fnc_photo_sendSelected"}) exitWith { false };

    COMSPEC_PhotoLibraryAthenaWrapped = true;
    missionNamespace setVariable ["COMSPEC_PhotoRefreshOrig", Iceman_fnc_photo_refresh];
    missionNamespace setVariable ["COMSPEC_PhotoSendOrig", Iceman_fnc_photo_sendSelected];

    Iceman_fnc_photo_refresh = {
        private _orig = missionNamespace getVariable ["COMSPEC_PhotoRefreshOrig", {}];
        private _ok = [] call _orig;
        if (!(_ok isEqualType true)) then { _ok = true; };

        private _controls = [];
        if (!isNil "Iceman_fnc_photo_findControls") then {
            _controls = [] call Iceman_fnc_photo_findControls;
        };
        if (!(_controls isEqualType createHashMap)) exitWith { _ok };

        private _recipient = _controls getOrDefault ["9431", controlNull];
        if (isNull _recipient) exitWith { _ok };

        private _hasAthena = false;
        for "_i" from 0 to ((lbSize _recipient) - 1) do {
            if ((_recipient lbData _i) isEqualTo "ATHENA") then { _hasAthena = true; };
        };
        if (!_hasAthena) then {
            private _row = _recipient lbAdd "Poste Athena";
            _recipient lbSetData [_row, "ATHENA"];
        };
        _ok
    };

    Iceman_fnc_photo_sendSelected = {
        private _orig = missionNamespace getVariable ["COMSPEC_PhotoSendOrig", {}];
        private _controls = [];
        if (!isNil "Iceman_fnc_photo_findControls") then {
            _controls = [] call Iceman_fnc_photo_findControls;
        };
        private _recipient = if (_controls isEqualType createHashMap) then {
            _controls getOrDefault ["9431", controlNull]
        } else {
            controlNull
        };
        private _uid = "";
        if (!isNull _recipient && {(lbCurSel _recipient) >= 0}) then {
            _uid = _recipient lbData (lbCurSel _recipient);
        };

        if (_uid isNotEqualTo "ATHENA") exitWith {
            [] call _orig
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

        private _path = _record param [2, ""];
        private _fileName = _record param [3, ""];
        if (_path isEqualTo "" && {_fileName isNotEqualTo ""}) then { _path = _fileName; };
        if (_path isEqualTo "") exitWith {
            if (!isNil "cTab_fnc_addNotification") then {
                ["PHOTOS", "Cette photo n’a pas de fichier local transmissible.", 4] call cTab_fnc_addNotification;
            };
            false
        };
        if (_fileName isEqualTo "") then {
            private _segs = _path splitString "\/";
            _fileName = _segs select ((count _segs) - 1);
        };

        if (isNil "comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanPhoto") exitWith {
            if (!isNil "cTab_fnc_addNotification") then {
                ["PHOTOS", "Module photo Athena indisponible.", 4] call cTab_fnc_addNotification;
            };
            false
        };

        private _ok = [_path, _fileName, false, true] call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanPhoto;
        if (!(_ok isEqualType true)) then { _ok = false; };

        if (_ok) then {
            if (!isNil "cTab_fnc_addNotification") then {
                ["PHOTOS", "Photo mise en file vers le poste Athena.", 4] call cTab_fnc_addNotification;
            };
            playSound "cTab_mailSent";
        } else {
            private _detail = toLower (str (missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", ""]));
            private _msg = "L’envoi vers le poste a échoué.";
            if ((_detail find "file_not_found") >= 0) then {
                _msg = "Fichier introuvable — reprenez une vue, puis renvoyez.";
            };
            if ((_detail find "not_connected") >= 0) then {
                _msg = "Pas de liaison Athena — reconnectez-vous, puis renvoyez.";
            };
            if (!isNil "cTab_fnc_addNotification") then {
                ["PHOTOS", _msg, 6] call cTab_fnc_addNotification;
            };
        };
        _ok
    };

    true
};

if !([] call _wrap) then {
    { [_wrap, [], _x] call CBA_fnc_waitAndExecute; } forEach [2, 6, 12, 20];
};
