/*
    Envoie vers Athena la photo sélectionnée dans l’inbox Athena
    ou la dernière photo locale Photo Library.
*/
if (!hasInterface) exitWith {};
if (isNil "comspec_overwatch_connect_fnc_captureReconImage") exitWith {
    if (!isNil "cTab_fnc_addNotification") then {
        ["ATHENA", "Module photo Athena indisponible.", 4] call cTab_fnc_addNotification;
    };
};

private _path = "";
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
            if ((count _meta) >= 2) then { _caption = format ["Photo ATAK — %1", _meta select 1]; };
        };
    };
};

// Repli : dernière photo locale Photo Library
if (_path isEqualTo "" && {!isNil "Iceman_fnc_photo_getRecords"}) then {
    private _records = call Iceman_fnc_photo_getRecords;
    if ((_records isEqualType []) && {(count _records) > 0}) then {
        private _rec = _records select ((count _records) - 1);
        if ((_rec isEqualType []) && {(count _rec) > 3}) then {
            _path = _rec select 2;
            private _fn = _rec select 3;
            private _g = if ((count _rec) > 8) then { _rec select 8 } else { mapGridPosition player };
            _caption = format ["Photo ATAK Enhanced — grille %1 (%2)", _g, _fn];
        };
    };
};

if (_path isEqualTo "") exitWith {
    if (!isNil "cTab_fnc_addNotification") then {
        ["ATHENA", "Aucune photo à remonter — capturez d’abord depuis Photos ATAK.", 5] call cTab_fnc_addNotification;
    };
};

if (_caption isEqualTo "") then {
    _caption = format ["Photo ATAK Enhanced — grille %1", mapGridPosition player];
};

[_path, _caption] call comspec_overwatch_connect_fnc_captureReconImage;
if (!isNil "cTab_fnc_addNotification") then {
    ["ATHENA", "Photo envoyée vers Athena.", 4] call cTab_fnc_addNotification;
};
[] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
