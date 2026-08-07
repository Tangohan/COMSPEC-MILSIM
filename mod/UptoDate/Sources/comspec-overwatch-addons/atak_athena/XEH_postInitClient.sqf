if (!hasInterface) exitWith {};

// Forcer notre Check_Layout (sans `_fade` / nil) — BCE et le cache CfgFunctions
// peuvent sinon garder l’ancienne version jusqu’à un redémarrage Arma incomplet.
private _forceCheckLayout = {
    private _path = "\z\comspec_overwatch\addons\atak_athena\functions\fn_ATAK_Check_Layout.sqf";
    if !(fileExists _path) exitWith {};
    private _code = compile preprocessFileLineNumbers _path;
    if (!(_code isEqualType {})) exitWith {};
    BCE_fnc_ATAK_Check_Layout = _code;
    missionNamespace setVariable ["BCE_fnc_ATAK_Check_Layout", _code];
    uiNamespace setVariable ["BCE_fnc_ATAK_Check_Layout", _code];
};
call _forceCheckLayout;
{ [_forceCheckLayout, [], _x] call CBA_fnc_waitAndExecute; } forEach [1, 3, 8];

// Précharger le cache couleurs BCE avant tout updateInterface (sinon Marker_Color_Array
// vide → index oob → lbSetPictureColor Type Quelconque / Tableau attendu).
if (!isNil "BCE_fnc_getMarkerColor") then {
    "ColorRed" call BCE_fnc_getMarkerColor;
};

// BCE stub BDA_Report (Opened vide) + ATAK_BDA Iceman qui retire BDA_Report du cache :
// on ré-inscrit l’app et on force le refresh des props (PAGE_CTRL / Opened COMSPEC).
private _ensureBdaReportApp = {
    if (isNil "BCE_fnc_ATAK_setAPPs_props") exitWith {};
    if (!isClass (configFile >> "ATAK_APPs" >> "BDA_Report")) exitWith {};

    private _apps = + (profileNamespace getVariable ["BCE_ATAK_APPs", []]);
    if !(_apps isEqualType []) then { _apps = []; };

    private _changed = false;
    if !("BDA_Report" in _apps) then {
        _apps pushBack "BDA_Report";
        _changed = true;
    };

    if (_changed) then {
        profileNamespace setVariable ["BCE_ATAK_APPs", _apps];
        saveProfileNamespace;
    };

    // Toujours rafraîchir le HashMap props depuis le config (Opened / PAGE_CTRL).
    [_apps] call BCE_fnc_ATAK_setAPPs_props;
};
{
    [_ensureBdaReportApp, [], _x] call CBA_fnc_waitAndExecute;
} forEach [2, 5, 10, 12];

// Icônes Desktop ATAK Enhanced (Connexion Athena, messages d’urgence, tchat)
[] call comspec_overwatch_atak_athena_fnc_athena_installDesktopShortcut;

// Dual-send : alertes Iceman → Athena
["Iceman_ATAK_Alerts", {
    _this call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanAlert;
}] call CBA_fnc_addEventHandler;

// Dual-send : BDA Iceman → Athena
["Iceman_ATAK_BDA", {
    _this call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanBda;
}] call CBA_fnc_addEventHandler;

// Messages de groupe Iceman → journal local Athena (pas le TOC web)
["Iceman_ATAK_GroupMessage", {
    _this call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanGroup;
}] call CBA_fnc_addEventHandler;

// Contact permanent HQ dans la messagerie ATAK / cTab
[] call comspec_overwatch_atak_athena_fnc_athena_installHqContact;
// cTab peut charger après nous — retenter le wrap
[{ [] call comspec_overwatch_atak_athena_fnc_athena_installHqContact; }, [], 5] call CBA_fnc_waitAndExecute;
[{ [] call comspec_overwatch_atak_athena_fnc_athena_installHqContact; }, [], 15] call CBA_fnc_waitAndExecute;

// Photos BCE / Photo Library → signal unique vers la DLL (queue + watcher Screenshots).
// Plus de retries SQF agressifs : resolve/upload et FileSystemWatcher sont côté extension.
["bce_took_screenshot", {
    [] call comspec_overwatch_connect_fnc_markBcePhotoCapture;
    _this spawn {
        uiSleep 0.35;
        private _path = "";
        private _name = "";
        if (_this isEqualType []) then {
            if ((count _this) > 0) then { _path = _this select 0; };
            if ((count _this) > 1) then { _name = _this select 1; };
        } else {
            if (_this isEqualType "") then { _path = _this; };
        };
        if (_path isEqualType "" && {_path isNotEqualTo ""}) then {
            [_path, _name, true] call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanPhoto;
        } else {
            // Sans chemin : un seul balayage Photo Library (marquage vu, pas de spam).
            [] call comspec_overwatch_atak_athena_fnc_athena_pollIcemanPhotos;
        };
    };
}] call CBA_fnc_addEventHandler;

// Événements Quick Pictures / Photo Library Iceman (noms variables selon version)
{
    [_x, {
        [] call comspec_overwatch_connect_fnc_markBcePhotoCapture;
        _this spawn {
            uiSleep 0.35;
            private _path = "";
            private _name = "";
            if (_this isEqualType []) then {
                if ((count _this) > 0) then { _path = _this select 0; };
                if ((count _this) > 1) then { _name = _this select 1; };
                if ((_path isEqualType []) && {(count _path) > 3}) then {
                    _name = _path select 3;
                    _path = _path select 2;
                };
            };
            if (_path isEqualType "" && {_path isNotEqualTo ""}) then {
                [_path, _name, true] call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanPhoto;
            } else {
                [] call comspec_overwatch_atak_athena_fnc_athena_pollIcemanPhotos;
            };
        };
    }] call CBA_fnc_addEventHandler;
} forEach [
    "Iceman_ATAK_Photo",
    "Iceman_photo_taken",
    "Iceman_ATAK_QuickPicture",
    "BCE_photoTaken"
];

// Repli lent : uniquement pour records sans EH (le watcher DLL couvre Screenshots).
[{
    private _ready = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
    private _link = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
    if (!_ready && {_link isNotEqualTo "linked"}) exitWith {};
    [] call comspec_overwatch_atak_athena_fnc_athena_pollIcemanPhotos;
}, 30, []] call CBA_fnc_addPerFrameHandler;

// Liaison Athena établie : démarrer le watcher DLL + un balayage unique
["COMSPEC_AthenaLinkChanged", {
    [] spawn {
        uiSleep 0.5;
        ["COMSPECExtension" callExtension ["StartPhotoWatcher", []]] call comspec_overwatch_connect_fnc_extResult;
        [] call comspec_overwatch_atak_athena_fnc_athena_pollIcemanPhotos;
    };
}] call CBA_fnc_addEventHandler;

// Miroir : envoi COMSPEC → diffusion Iceman (Alerts / BDA)
["COMSPEC_TacticalAlertSent", {
    _this call comspec_overwatch_atak_athena_fnc_athena_bridgeComspecSent;
}] call CBA_fnc_addEventHandler;

// Ordres Athena → notification cTab
["COMSPEC_OrderReceived", {
    _this call comspec_overwatch_atak_athena_fnc_athena_onOrderReceived;
}] call CBA_fnc_addEventHandler;

// Rafraîchir le panneau si ouvert
["COMSPEC_AthenaInboxUpdated", {
    private _group = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
    if (!isNull _group && {ctrlShown _group}) then {
        [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
    };
}] call CBA_fnc_addEventHandler;

// Ponts ATAK Enhanced / cTab → Athena (hors features COMSPEC natives)
// Sync immédiat dès qu’un repère utilisateur cTab / ATAK change
{
    [_x, {
        [] call comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers;
    }] call CBA_fnc_addEventHandler;
} forEach [
    "ctab_userMarkerListUpdated",
    "cTab_userMarkerListUpdated",
    "ctab_userMarkerUpdated",
    "Iceman_ATAK_UserMarkerUpdated",
    "Iceman_ATAK_MarkersUpdated"
];

[{
    if (!isNil "COMSPEC_Wrapped_OnMapDblClick") exitWith {};
    if (isNil "cTab_fnc_onMapDoubleClick") exitWith {};
    COMSPEC_Wrapped_OnMapDblClick = true;
    missionNamespace setVariable ["COMSPEC_Prev_cTab_onMapDoubleClick", cTab_fnc_onMapDoubleClick];
    cTab_fnc_onMapDoubleClick = {
        private _r = _this call (missionNamespace getVariable ["COMSPEC_Prev_cTab_onMapDoubleClick", {}]);
        [{
            if (!isNil "comspec_overwatch_connect_fnc_forceSyncMapMarkers") then {
                [false] call comspec_overwatch_connect_fnc_forceSyncMapMarkers;
            };
        }, [], 0.35] call CBA_fnc_waitAndExecute;
        _r
    };
}, [], 3] call CBA_fnc_waitAndExecute;
[{
    if (!isNil "COMSPEC_Wrapped_OnMapDblClick") exitWith {};
    if (isNil "cTab_fnc_onMapDoubleClick") exitWith {};
    COMSPEC_Wrapped_OnMapDblClick = true;
    missionNamespace setVariable ["COMSPEC_Prev_cTab_onMapDoubleClick", cTab_fnc_onMapDoubleClick];
    cTab_fnc_onMapDoubleClick = {
        private _r = _this call (missionNamespace getVariable ["COMSPEC_Prev_cTab_onMapDoubleClick", {}]);
        [{
            if (!isNil "comspec_overwatch_connect_fnc_forceSyncMapMarkers") then {
                [false] call comspec_overwatch_connect_fnc_forceSyncMapMarkers;
            };
        }, [], 0.35] call CBA_fnc_waitAndExecute;
        _r
    };
}, [], 10] call CBA_fnc_waitAndExecute;

[{
    [] call comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers;
}, 1.5, []] call CBA_fnc_addPerFrameHandler;

// Hook BCE Marker Widget / Dropper → forcer le miroir Athena après pose
[{
    if (!isNil "COMSPEC_Wrapped_PlaceMarker") exitWith {};
    if (isNil "cTab_fnc_PlaceMarker") exitWith {};
    COMSPEC_Wrapped_PlaceMarker = true;
    missionNamespace setVariable ["COMSPEC_Prev_cTab_PlaceMarker", cTab_fnc_PlaceMarker];
    cTab_fnc_PlaceMarker = {
        private _args = _this;
        private _prev = missionNamespace getVariable ["COMSPEC_Prev_cTab_PlaceMarker", {}];
        private _r = _args call _prev;
        private _pos = [0, 0, 0];
        if (_args isEqualType []) then {
            if ((count _args) > 0 && {(_args select 0) isEqualType []}) then {
                _pos = _args select 0;
            };
        };
        [{
            params ["_pos"];
            if (!isNil "comspec_overwatch_connect_fnc_forceSyncMapMarkers") then {
                [false] call comspec_overwatch_connect_fnc_forceSyncMapMarkers;
            } else {
                {
                    private _n = _x;
                    if !([_n] call comspec_overwatch_connect_fnc_isSyncableMapMarker) then { continue };
                    if ((_n select [0, 1]) isNotEqualTo "_") then { continue };
                    private _mp = markerPos _n;
                    if ((_mp distance2D _pos) < 25) then {
                        [_n, false, true] call comspec_overwatch_connect_fnc_syncMapMarker;
                    };
                } forEach allMapMarkers;
                [] call comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers;
            };
        }, [_pos], 0.2] call CBA_fnc_waitAndExecute;
        _r
    };
}, [], 2] call CBA_fnc_waitAndExecute;
[{
    // BCE peut charger après nous
    if (!isNil "COMSPEC_Wrapped_PlaceMarker") exitWith {};
    if (isNil "cTab_fnc_PlaceMarker") exitWith {};
    COMSPEC_Wrapped_PlaceMarker = true;
    missionNamespace setVariable ["COMSPEC_Prev_cTab_PlaceMarker", cTab_fnc_PlaceMarker];
    cTab_fnc_PlaceMarker = {
        private _args = _this;
        private _prev = missionNamespace getVariable ["COMSPEC_Prev_cTab_PlaceMarker", {}];
        private _r = _args call _prev;
        private _pos = [0, 0, 0];
        if (_args isEqualType []) then {
            if ((count _args) > 0 && {(_args select 0) isEqualType []}) then {
                _pos = _args select 0;
            };
        };
        [{
            params ["_pos"];
            if (!isNil "comspec_overwatch_connect_fnc_forceSyncMapMarkers") then {
                [false] call comspec_overwatch_connect_fnc_forceSyncMapMarkers;
            } else {
                {
                    private _n = _x;
                    if !([_n] call comspec_overwatch_connect_fnc_isSyncableMapMarker) then { continue };
                    if ((_n select [0, 1]) isNotEqualTo "_") then { continue };
                    private _mp = markerPos _n;
                    if ((_mp distance2D _pos) < 25) then {
                        [_n, false, true] call comspec_overwatch_connect_fnc_syncMapMarker;
                    };
                } forEach allMapMarkers;
                [] call comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers;
            };
        }, [_pos], 0.2] call CBA_fnc_waitAndExecute;
        _r
    };
}, [], 8] call CBA_fnc_waitAndExecute;

// Après pose TAD Dropper (hors PlaceMarker) : resync rapide des marqueurs `_…_DEFINED`
[{
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
    if (!(missionNamespace getVariable ["comspec_overwatch_sync_map_markers", true])) exitWith {};
    private _dirty = false;
    {
        private _n = _x;
        if ((_n select [0, 1]) isNotEqualTo "_") then { continue };
        if !([_n] call comspec_overwatch_connect_fnc_isSyncableMapMarker) then { continue };
        private _sigMap = missionNamespace getVariable ["COMSPEC_Athena_BceMarkerQuickSnap", createHashMap];
        if (!(_sigMap isEqualType createHashMap)) then { _sigMap = createHashMap; };
        private _pos = markerPos _n;
        private _sig = format ["%1|%2|%3|%4|%5", _pos select 0, _pos select 1, markerType _n, markerText _n, markerColor _n];
        if ((_sigMap getOrDefault [_n, ""]) isEqualTo _sig) then { continue };
        _sigMap set [_n, _sig];
        missionNamespace setVariable ["COMSPEC_Athena_BceMarkerQuickSnap", _sigMap, false];
        [_n, false] call comspec_overwatch_connect_fnc_syncMapMarker;
        _dirty = true;
    } forEach allMapMarkers;
    if (_dirty) then {
        [] call comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers;
    };
}, 2, []] call CBA_fnc_addPerFrameHandler;

[{
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
    [] call comspec_overwatch_atak_athena_fnc_athena_bridgeWeather;
}, 45, []] call CBA_fnc_addPerFrameHandler;

[{
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
    [] call comspec_overwatch_atak_athena_fnc_athena_bridgeDroneContacts;
}, 8, []] call CBA_fnc_addPerFrameHandler;

[{
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
    [] call comspec_overwatch_atak_athena_fnc_athena_bridgeRoute;
}, 10, []] call CBA_fnc_addPerFrameHandler;

[{
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
    [] call comspec_overwatch_atak_athena_fnc_athena_bridgeJump;
}, 12, []] call CBA_fnc_addPerFrameHandler;

[{
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
    [] call comspec_overwatch_atak_athena_fnc_athena_bridgeVideoFeeds;
}, 10, []] call CBA_fnc_addPerFrameHandler;

[{
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
    [] call comspec_overwatch_atak_athena_fnc_athena_snapshotVideoFeed;
}, 20, []] call CBA_fnc_addPerFrameHandler;
