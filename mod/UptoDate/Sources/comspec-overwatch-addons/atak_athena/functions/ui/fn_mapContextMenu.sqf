/*
    Menu contextuel clic droit — terrain / unité / marqueur.
    IDC 88900–88924. Ne bloque pas le zoom ni Map Tools.
*/
params [["_mapCtrl", controlNull], ["_world", []]];
if (isNull _mapCtrl) exitWith {};
private _disp = ctrlParent _mapCtrl;
if (isNull _disp) exitWith {};
private _hit = [_mapCtrl, _world] call comspec_overwatch_atak_athena_fnc_selectMapEntity;
private _mk = missionNamespace getVariable ["COMSPEC_MapSelectedMarker", ""];

private _items = [];
if (!isNull _hit) then {
    _items = [
        ["FOCUS", "Centrer"],
        ["MESSAGE", "Message"],
        ["TASK", "Créer une tâche"],
        ["TRACK", "Suivre"],
        ["HISTORY", "Historique"],
        ["NINE", "Créer une 9-Line"]
    ];
} else {
    if (_mk isNotEqualTo "") then {
        _items = [
            ["EDIT", "Modifier"],
            ["SHARE", "Partager"],
            ["INTEL", "Joindre un renseignement"],
            ["PHOTO", "Photo sur place"],
            ["DELETE", "Supprimer"]
        ];
    } else {
        _items = [
            ["MARKER", "Poser un marqueur"],
            ["PING", "Signaler l’endroit"],
            ["MEASURE", "Mesurer depuis ici"],
            ["TASK", "Créer une tâche"],
            ["NINE", "Créer une 9-Line"],
            ["REPORT", "Contact"],
            ["SIT_CAS", "Blessé"],
            ["SIT_VEH", "Véhicule"],
            ["SIT_CLR", "Zone libre"]
        ];
    };
};

missionNamespace setVariable ["COMSPEC_MapCtxWorld", _world, false];
missionNamespace setVariable ["COMSPEC_MapCtxUnit", _hit, false];
missionNamespace setVariable ["COMSPEC_MapCtxMarker", _mk, false];

private _fncEnsure = {
    params ["_d", "_idc", "_class"];
    private _c = _d displayCtrl _idc;
    if (isNull _c) then { _c = _d ctrlCreate [_class, _idc]; };
    _c
};
private _scr = _mapCtrl ctrlMapWorldToScreen _world;
_scr params ["_sx", "_sy"];
private _rowH = 0.026;
private _w = 0.17;
private _i = 0;
{
    _x params ["_id", "_lab"];
    private _idc = 88900 + _i;
    private _b = [_disp, _idc, "RscButton"] call _fncEnsure;
    _b ctrlSetPosition [_sx, _sy + (_i * _rowH), _w, _rowH];
    _b ctrlSetText _lab;
    _b ctrlSetFont "RobotoCondensed";
    _b ctrlSetFontHeight 0.016;
    _b ctrlSetBackgroundColor [0.08, 0.08, 0.08, 0.96];
    _b ctrlSetTextColor [0.94, 0.95, 0.96, 1];
    _b ctrlShow true;
    _b ctrlEnable true;
    _b ctrlCommit 0;
    if (isNil {_b getVariable "COMSPEC_CtxWired"}) then {
        _b setVariable ["COMSPEC_CtxWired", true];
        _b ctrlAddEventHandler ["ButtonClick", {
            params ["_ctrl"];
            private _id = _ctrl getVariable ["COMSPEC_CtxId", ""];
            private _world = missionNamespace getVariable ["COMSPEC_MapCtxWorld", []];
            private _u = missionNamespace getVariable ["COMSPEC_MapCtxUnit", objNull];
            private _mk = missionNamespace getVariable ["COMSPEC_MapCtxMarker", ""];
            switch (_id) do {
                case "PING": { [_world] call comspec_overwatch_atak_athena_fnc_mapQuickPing };
                case "MEASURE": {
                    missionNamespace setVariable ["COMSPEC_MapMeasureA", _world, false];
                    ["measure"] call comspec_overwatch_atak_athena_fnc_setActiveTool;
                };
                case "NINE": {
                    missionNamespace setVariable ["COMSPEC_CasPrefillPos", _world, false];
                    if (!isNil "comspec_overwatch_connect_fnc_casRequestShow") then {
                        [_world] call comspec_overwatch_connect_fnc_casRequestShow;
                    };
                };
                case "TASK": {
                    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openTask") then {
                        [] call comspec_overwatch_atak_athena_fnc_athena_openTask;
                    };
                };
                case "REPORT": { ["CONTACT", _world] call comspec_overwatch_atak_athena_fnc_mapSitrep };
                case "SIT_CAS": { ["CASUALTY", _world] call comspec_overwatch_atak_athena_fnc_mapSitrep };
                case "SIT_VEH": { ["VEHICLE", _world] call comspec_overwatch_atak_athena_fnc_mapSitrep };
                case "SIT_CLR": { ["CLEAR", _world] call comspec_overwatch_atak_athena_fnc_mapSitrep };
                case "INTEL": { ["CONTACT", _world] call comspec_overwatch_atak_athena_fnc_mapSitrep };
                case "PHOTO": { [_world, "Photo"] call comspec_overwatch_atak_athena_fnc_mapPhotoIntel };
                case "FOCUS": {
                    if (!isNull _u) then {
                        private _m = (uiNamespace getVariable ["cTab_Android_dlg", displayNull]) displayCtrl 1201;
                        if (!isNull _m) then { _m ctrlMapAnimAdd [0.4, 0.05, getPos _u]; ctrlMapAnimCommit _m; };
                    };
                };
                case "TRACK": {
                    missionNamespace setVariable ["COMSPEC_MapTrackUnit", _u, false];
                    ["INFO", "Suivi de l’opérateur"] call comspec_overwatch_atak_athena_fnc_showNotification;
                };
                case "MESSAGE": {
                    if (!isNil "comspec_overwatch_connect_fnc_sendIntel") then {
                        [player, "CHAT", format ["Message à %1", name _u], "", "INFANTRY"] call comspec_overwatch_connect_fnc_sendIntel;
                    };
                };
                case "HISTORY": { [] call comspec_overwatch_atak_athena_fnc_mapReplay };
                case "MARKER": {
                    if (!isNil "comspec_overwatch_connect_fnc_placeMarkerFromTablet") then {
                        [_world select 0, _world select 1] call comspec_overwatch_connect_fnc_placeMarkerFromTablet;
                    };
                };
                case "SHARE": {
                    private _g = [markerPos _mk] call comspec_overwatch_atak_athena_fnc_formatGrid;
                    copyToClipboard format ["%1 %2", markerText _mk, _g];
                    ["INFO", "Marqueur copié"] call comspec_overwatch_atak_athena_fnc_showNotification;
                };
                case "EDIT": {
                    ["INFO", "Renommez ce marqueur depuis les outils carte"] call comspec_overwatch_atak_athena_fnc_showNotification;
                };
                case "DELETE": {
                    if (_mk isNotEqualTo "") then { deleteMarkerLocal _mk };
                };
                default { };
            };
            private _d = ctrlParent _ctrl;
            private _n = 0;
            while { _n < 16 } do {
                private _c = _d displayCtrl (88900 + _n);
                if (!isNull _c) then { _c ctrlShow false };
                _n = _n + 1;
            };
        }];
    };
    _b setVariable ["COMSPEC_CtxId", _id];
    _i = _i + 1;
} forEach _items;
while { _i < 16 } do {
    private _c = _disp displayCtrl (88900 + _i);
    if (!isNull _c) then { _c ctrlShow false };
    _i = _i + 1;
};
