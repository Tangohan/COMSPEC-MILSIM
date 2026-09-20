if (!hasInterface) exitWith {};
if (!isNil "COMSPEC_Athena_PostInitDone") exitWith {};
COMSPEC_Athena_PostInitDone = true;
if (isClass (configFile >> "CfgPatches" >> "comspec_atak_native_main")) exitWith {
    diag_log "[COMSPEC ATAK NATIVE][WARN][BOOT] Legacy ATAK client PostInit suppressed";
};

// Ne pas remplacer le calage IceMan s’il est déjà verrouillé (compileFinal).
// Un second calage déplaçait le fond sans les icônes (menu gris vide).
private _forceCheckLayout = {
    if (!isNil "BCE_fnc_ATAK_Check_Layout" && {isFinal BCE_fnc_ATAK_Check_Layout}) exitWith {};
    private _path = "\z\comspec_overwatch\addons\atak_athena\functions\fn_ATAK_Check_Layout.sqf";
    if !(fileExists _path) exitWith {};
    private _code = compile preprocessFileLineNumbers _path;
    if (!(_code isEqualType {})) exitWith {};
    BCE_fnc_ATAK_Check_Layout = _code;
    missionNamespace setVariable ["BCE_fnc_ATAK_Check_Layout", _code];
    uiNamespace setVariable ["BCE_fnc_ATAK_Check_Layout", _code];
};
call _forceCheckLayout;

// Caméra overlay : téléphone = rttN (l’opérateur marche) ; cliché = vue scène puis restauration.
private _forceCamCapture = {
    private _fs = "\z\comspec_overwatch\addons\atak_athena\functions\fn_ATAK_FullScreenCamera.sqf";
    if (fileExists _fs) then {
        private _code = compile preprocessFileLineNumbers _fs;
        if (_code isEqualType {}) then {
            BCE_fnc_ATAK_FullScreenCamera = _code;
            missionNamespace setVariable ["BCE_fnc_ATAK_FullScreenCamera", _code];
            uiNamespace setVariable ["BCE_fnc_ATAK_FullScreenCamera", _code];
        };
    };
    private _tp = "\z\comspec_overwatch\addons\atak_athena\functions\fn_ATAK_TakePicture.sqf";
    if (fileExists _tp) then {
        private _code = compile preprocessFileLineNumbers _tp;
        if (_code isEqualType {}) then {
            BCE_fnc_ATAK_TakePicture = _code;
            missionNamespace setVariable ["BCE_fnc_ATAK_TakePicture", _code];
            uiNamespace setVariable ["BCE_fnc_ATAK_TakePicture", _code];
        };
    };
    if (isNil "COMSPEC_BCE_screenShotOrig" && {!isNil "BCE_fnc_screenShot"}) then {
        missionNamespace setVariable ["COMSPEC_BCE_screenShotOrig", BCE_fnc_screenShot];
    };
};
call _forceCamCapture;
{ [_forceCamCapture, [], _x] call CBA_fnc_waitAndExecute; } forEach [1, 3, 8];

[] call comspec_overwatch_atak_athena_fnc_athena_installPhoneGeolocMap;
[] call comspec_overwatch_atak_athena_fnc_athena_installReachMap;
[] call comspec_overwatch_connect_fnc_superPingInstall;
[] call comspec_overwatch_atak_athena_fnc_athena_installMapHud;
[] call comspec_overwatch_atak_athena_fnc_athena_installBftLabels;
[{ [] call comspec_overwatch_atak_athena_fnc_athena_installBftLabels; }, [], 1] call CBA_fnc_waitAndExecute;
[{ [] call comspec_overwatch_atak_athena_fnc_athena_installBftLabels; }, [], 3] call CBA_fnc_waitAndExecute;
[{ [] call comspec_overwatch_atak_athena_fnc_athena_installBftLabels; }, [], 8] call CBA_fnc_waitAndExecute;
[{ [] call comspec_overwatch_atak_athena_fnc_athena_installBftLabels; }, [], 15] call CBA_fnc_waitAndExecute;
[{ [] call comspec_overwatch_atak_athena_fnc_athena_installBftLabels; }, [], 25] call CBA_fnc_waitAndExecute;
[] call comspec_overwatch_atak_athena_fnc_athena_installReportsLayout;
[{ [] call comspec_overwatch_atak_athena_fnc_athena_installReportsLayout; }, [], 1] call CBA_fnc_waitAndExecute;
[{ [] call comspec_overwatch_atak_athena_fnc_athena_installReportsLayout; }, [], 3] call CBA_fnc_waitAndExecute;
[{ [] call comspec_overwatch_atak_athena_fnc_athena_installReportsLayout; }, [], 8] call CBA_fnc_waitAndExecute;

// Photo souris aussi depuis la vue casque plein écran (amont : téléphone seulement).
[
    "Better CAS Environment (ScreenShot)", "ScreenShot",
    localize "STR_BCE_Take_ScreenShot",
    {
        private _phone = !isNull (uiNamespace getVariable ["BCE_PhoneCAM_View", displayNull]);
        private _hcam = !isNull (uiNamespace getVariable ["BCE_HCAM_View", displayNull]);
        if ((_phone || {_hcam}) && {isNil "ctabifopen"}) then {
            call BCE_fnc_ATAK_TakePicture;
        };
    },
    "",
    [0xF0, [false, false, false]]
] call CBA_fnc_addKeybind;

// Précharger le cache couleurs BCE avant tout updateInterface (sinon Marker_Color_Array
// vide → index oob → lbSetPictureColor Type Quelconque / Tableau attendu).
if (!isNil "BCE_fnc_getMarkerColor") then {
    "ColorRed" call BCE_fnc_getMarkerColor;
};

// BCE stub BDA_Report (Opened vide) + ATAK_BDA Iceman qui retire BDA_Report du cache :
// on ré-inscrit l’app et on force le refresh des props (PAGE_CTRL / Opened COMSPEC).
// Idem pour BII_Identifi (couche SEEK II dans le tiroir ATAK).
private _ensureAtakApps = {
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_syncAtakApps") then {
        [] call comspec_overwatch_atak_athena_fnc_athena_syncAtakApps;
    };
};
call _ensureAtakApps;
{
    [_ensureAtakApps, [], _x] call CBA_fnc_waitAndExecute;
} forEach [0.5, 2, 5, 10];

// Si IceMan ouvre encore Groups / Group Messages → bascule Messagerie COMSPEC
// sans relancer toute l’ouverture du téléphone (évite double écran et plantage).
private _redirectIcemanGroup = {
    params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];
    if (missionNamespace getVariable ["COMSPEC_ATAK_Comms_iceRedirect", false]) exitWith {};
    missionNamespace setVariable ["COMSPEC_ATAK_Comms_iceRedirect", true, false];
    if (!isNull _group) then {
        {
            _x ctrlShow false;
            _x ctrlEnable false;
        } forEach (allControls _group);
        _group ctrlShow false;
        _group ctrlEnable false;
    };
    ["message"] call comspec_overwatch_atak_athena_fnc_athena_openAtakApp;
    [] spawn {
        uiSleep 0.2;
        ["msghub"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;
        missionNamespace setVariable ["COMSPEC_ATAK_Comms_iceRedirect", false, false];
    };
};
if (!isNil "Iceman_fnc_group_onOpened") then {
    Iceman_fnc_group_onOpened = _redirectIcemanGroup;
    missionNamespace setVariable ["Iceman_fnc_group_onOpened", Iceman_fnc_group_onOpened];
};
[{
    if (isNil "Iceman_fnc_group_onOpened") exitWith {};
    Iceman_fnc_group_onOpened = {
        params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];
        if (missionNamespace getVariable ["COMSPEC_ATAK_Comms_iceRedirect", false]) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_Comms_iceRedirect", true, false];
        if (!isNull _group) then {
            {
                _x ctrlShow false;
                _x ctrlEnable false;
            } forEach (allControls _group);
            _group ctrlShow false;
            _group ctrlEnable false;
        };
        ["message"] call comspec_overwatch_atak_athena_fnc_athena_openAtakApp;
        [] spawn {
            uiSleep 0.2;
            ["msghub"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;
            missionNamespace setVariable ["COMSPEC_ATAK_Comms_iceRedirect", false, false];
        };
    };
    missionNamespace setVariable ["Iceman_fnc_group_onOpened", Iceman_fnc_group_onOpened];
}, [], 8] call CBA_fnc_waitAndExecute;

// Icônes Desktop ATAK Enhanced (Connexion Athena, messages d’urgence, tchat)
[] call comspec_overwatch_atak_athena_fnc_athena_installDesktopShortcut;
[] call comspec_overwatch_atak_athena_fnc_athena_installPhotoLibraryAthena;

// Dual-send : alertes Iceman → Athena
["Iceman_ATAK_Alerts", {
    _this call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanAlert;
}] call CBA_fnc_addEventHandler;

// Dual-send : BDA Iceman → Athena
["Iceman_ATAK_BDA", {
    _this call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanBda;
}] call CBA_fnc_addEventHandler;

// Dual-send : messages de groupe Iceman → journal radio Athena (TOC web)
["Iceman_ATAK_GroupMessage", {
    _this call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanGroup;
}] call CBA_fnc_addEventHandler;

// Viewshed IceMan → calque temporaire sur la carte du poste
if (isNil "COMSPEC_ViewshedBridgeEH") then {
    COMSPEC_ViewshedBridgeEH = true;
    [{
        if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
        private _state = missionNamespace getVariable ["Iceman_ATAK_Elevation_state", nil];
        if (isNil "_state" || {!(_state isEqualType createHashMap)}) exitWith {};
        private _mode = _state getOrDefault ["mode", ""];
        if (_mode isNotEqualTo "viewshed") exitWith {};
        private _pos = _state getOrDefault ["viewshedPoint", []];
        if ((count _pos) < 2) exitWith {};
        private _fp = format ["%1:%2:%3", round (_pos select 0), round (_pos select 1), round (_state getOrDefault ["radiusM", 500])];
        if (_fp isEqualTo (missionNamespace getVariable ["COMSPEC_LastViewshedFp", ""])) exitWith {};
        missionNamespace setVariable ["COMSPEC_LastViewshedFp", _fp, false];
        [_pos, _state getOrDefault ["radiusM", 500]] call comspec_overwatch_connect_fnc_publishViewshed;
    }, 8, []] call CBA_fnc_addPerFrameHandler;
};

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
            private _lowP = toLower _path;
            private _hasExt = (_lowP find ".jpg") >= 0 || {(_lowP find ".jpeg") >= 0} || {(_lowP find ".png") >= 0};
            if (!_hasExt && {_name isEqualType ""} && {_name isNotEqualTo ""}) then {
                private _base = _path;
                while { (count _base) > 0 && {(_base select [(count _base) - 1, 1]) isEqualTo "\\"} } do {
                    _base = _base select [0, (count _base) - 1];
                };
                _path = if (_base isEqualTo "") then { _name } else { format ["%1\\%2", _base, _name] };
            };
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
                private _lowP = toLower _path;
                private _hasExt = (_lowP find ".jpg") >= 0 || {(_lowP find ".jpeg") >= 0} || {(_lowP find ".png") >= 0};
                if (!_hasExt && {_name isEqualType ""} && {_name isNotEqualTo ""}) then {
                    private _base = _path;
                    while { (count _base) > 0 && {(_base select [(count _base) - 1, 1]) isEqualTo "\\"} } do {
                        _base = _base select [0, (count _base) - 1];
                    };
                    _path = if (_base isEqualTo "") then { _name } else { format ["%1\\%2", _base, _name] };
                };
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
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
    if (missionNamespace getVariable ["COMSPEC_HandshakeQuiet", false]) exitWith {};
    [] call comspec_overwatch_atak_athena_fnc_athena_pollIcemanPhotos;
}, 30, []] call CBA_fnc_addPerFrameHandler;

// Liaison Athena établie : démarrer le watcher DLL + un balayage unique
["COMSPEC_AthenaLinkChanged", {
    params [["_state", ""]];
    if (_state in ["ready", "linked"]) then {
        [] call comspec_overwatch_atak_athena_fnc_athena_installBftLabels;
        if (!isNil "comspec_overwatch_connect_fnc_applyCtabBftCallsign") then {
            [] call comspec_overwatch_connect_fnc_applyCtabBftCallsign;
        };
    };
    if (_state isNotEqualTo "ready") exitWith {};
    [] spawn {
        private _deadline = diag_tickTime + 30;
        waitUntil {
            !(missionNamespace getVariable ["COMSPEC_HandshakeQuiet", false])
            || {diag_tickTime > _deadline}
        };
        if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
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

// Backfill chat groupe + app TASK : seulement téléphone réellement ouvert, après la prise d’équipement.
[{
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
    if (isNull player || {!alive player}) exitWith {};
    if (!isNil "comspec_overwatch_connect_fnc_hasTerminal" && {!([player] call comspec_overwatch_connect_fnc_hasTerminal)}) exitWith {};
    if (!isNil "comspec_overwatch_connect_fnc_uplinkQuiet" && {[] call comspec_overwatch_connect_fnc_uplinkQuiet}) exitWith {};
    if (!isNil "comspec_overwatch_connect_fnc_pollOrders") then {
        [] call comspec_overwatch_connect_fnc_pollOrders;
    };
    if (!isNil "comspec_overwatch_connect_fnc_pollMissionPlan") then {
        [] call comspec_overwatch_connect_fnc_pollMissionPlan;
    };
    if (!isNull ([] call comspec_overwatch_atak_athena_fnc_athena_phoneDisplay)) then {
        [] call comspec_overwatch_atak_athena_fnc_athena_syncOrdersToGroupChat;
    };
}, [], 12] call CBA_fnc_waitAndExecute;
[{
    if (isNull player || {!alive player}) exitWith {};
    if (!isNil "comspec_overwatch_connect_fnc_hasTerminal" && {!([player] call comspec_overwatch_connect_fnc_hasTerminal)}) exitWith {};
    if (!isNil "comspec_overwatch_connect_fnc_uplinkQuiet" && {[] call comspec_overwatch_connect_fnc_uplinkQuiet}) exitWith {};
    if (isNull ([] call comspec_overwatch_atak_athena_fnc_athena_phoneDisplay)) exitWith {};
    [] call comspec_overwatch_atak_athena_fnc_athena_syncOrdersToGroupChat;
}, [], 25] call CBA_fnc_waitAndExecute;

// ACK IceMan Reports (message ouvert) → Athena
{
    [_x, {
        [] call comspec_overwatch_atak_athena_fnc_athena_syncIcemanOrderAck;
    }] call CBA_fnc_addEventHandler;
} forEach ["ctab_messagesUpdated", "ctab_core_messagesUpdated"];

// Rafraîchir le panneau si ouvert
["COMSPEC_AthenaInboxUpdated", {
    private _group = [] call comspec_overwatch_atak_athena_fnc_athena_resolveAthenaGroup;
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

private _fncWrapDblClick = {
    if (!isNil "COMSPEC_Wrapped_OnMapDblClick") exitWith {};
    if (isNil "cTab_fnc_onMapDoubleClick") exitWith {};
    if (isFinal cTab_fnc_onMapDoubleClick) exitWith {
        COMSPEC_Wrapped_OnMapDblClick = "final";
    };
    COMSPEC_Wrapped_OnMapDblClick = true;
    missionNamespace setVariable ["COMSPEC_Prev_cTab_onMapDoubleClick", cTab_fnc_onMapDoubleClick];
    cTab_fnc_onMapDoubleClick = {
        private _r = _this call (missionNamespace getVariable ["COMSPEC_Prev_cTab_onMapDoubleClick", {}]);
        private _pos = [0, 0, 0];
        if (_this isEqualType [] && {(count _this) > 0}) then {
            private _ctrl = _this select 0;
            if (!isNull _ctrl) then {
                private _xC = if ((count _this) > 2) then { _this select 2 } else { 0.5 };
                private _yC = if ((count _this) > 3) then { _this select 3 } else { 0.5 };
                _pos = _ctrl ctrlMapScreenToWorld [_xC, _yC];
            };
        };
        [{
            params ["_pos"];
            if (!isNil "comspec_overwatch_connect_fnc_syncNearbyMapMarkers") then {
                [_pos] call comspec_overwatch_connect_fnc_syncNearbyMapMarkers;
            } else {
                if (!isNil "comspec_overwatch_connect_fnc_forceSyncMapMarkers") then {
                    [false] call comspec_overwatch_connect_fnc_forceSyncMapMarkers;
                };
            };
        }, [_pos], 0.35] call CBA_fnc_waitAndExecute;
        _r
    };
};
[_fncWrapDblClick, [], 3] call CBA_fnc_waitAndExecute;
[_fncWrapDblClick, [], 10] call CBA_fnc_waitAndExecute;

[{
    [] call comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers;
}, 1.5, []] call CBA_fnc_addPerFrameHandler;

// Hook BCE Marker Widget / Dropper → forcer le miroir Athena après pose
private _fncWrapPlaceMarker = {
    if (!isNil "COMSPEC_Wrapped_PlaceMarker") exitWith {};
    if (isNil "cTab_fnc_PlaceMarker") exitWith {};
    if (isFinal cTab_fnc_PlaceMarker) exitWith {
        // IceMan : fonction verrouillée — clic carte + MarkerCreated prennent le relais
        COMSPEC_Wrapped_PlaceMarker = "final";
    };
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
            if (!isNil "comspec_overwatch_connect_fnc_syncNearbyMapMarkers") then {
                [_pos] call comspec_overwatch_connect_fnc_syncNearbyMapMarkers;
            } else {
                if (!isNil "comspec_overwatch_connect_fnc_forceSyncMapMarkers") then {
                    [false] call comspec_overwatch_connect_fnc_forceSyncMapMarkers;
                };
            };
        }, [_pos], 0.25] call CBA_fnc_waitAndExecute;
        _r
    };
};
[_fncWrapPlaceMarker, [], 2] call CBA_fnc_waitAndExecute;
[_fncWrapPlaceMarker, [], 8] call CBA_fnc_waitAndExecute;

// Après pose TAD Dropper (hors PlaceMarker) : resync rapide des marqueurs `_…_DEFINED`
[{
    if (missionNamespace getVariable ["COMSPEC_MarkerSync_Lock", false]) exitWith {};
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
    if (!(missionNamespace getVariable ["comspec_overwatch_sync_map_markers", true])) exitWith {};
    missionNamespace setVariable ["COMSPEC_MarkerSync_Lock", true, false];
    private _dirty = false;
    private _safeMarkers = +allMapMarkers;
    {
        private _n = _x;
        if ((_n select [0, 1]) isNotEqualTo "_") then { continue };
        if !([_n] call comspec_overwatch_connect_fnc_isSyncableMapMarker) then { continue };
        private _sigMap = missionNamespace getVariable ["COMSPEC_Athena_BceMarkerQuickSnap", createHashMap];
        if (!(_sigMap isEqualType createHashMap)) then { _sigMap = createHashMap; };
        private _pos = markerPos _n;
        private _sig = format ["%1|%2|%3|%4|%5", _pos select 0, _pos select 1, markerType _n, markerText _n, markerColor _n];
        if ((_sigMap getOrDefault [_n, ""]) isEqualTo _sig) then { continue };
        private _sent = [_n, false, true] call comspec_overwatch_connect_fnc_syncMapMarker;
        if (_sent) then {
            _sigMap set [_n, _sig];
            missionNamespace setVariable ["COMSPEC_Athena_BceMarkerQuickSnap", _sigMap, false];
            _dirty = true;
        };
    } forEach _safeMarkers;
    if (_dirty) then {
        [] call comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers;
    };
    missionNamespace setVariable ["COMSPEC_MarkerSync_Lock", false, false];
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
}, 20, []] call CBA_fnc_addPerFrameHandler;

[{
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
    [] call comspec_overwatch_atak_athena_fnc_athena_snapshotVideoFeed;
}, 20, []] call CBA_fnc_addPerFrameHandler;

// Alerte vibrante : ATAK allié proche d’un téléphone suivi (rayon dans Paramètres)
private _proxM = profileNamespace getVariable ["COMSPEC_AtakPhoneProximityM", 200];
if (!(_proxM isEqualType 0)) then { _proxM = 200; };
if ((_proxM isNotEqualTo 0) && {_proxM isNotEqualTo 50} && {_proxM isNotEqualTo 100} && {_proxM isNotEqualTo 200} && {_proxM isNotEqualTo 500} && {_proxM isNotEqualTo 1000} && {_proxM isNotEqualTo 2000}) then {
    _proxM = 200;
};
missionNamespace setVariable ["COMSPEC_AtakPhoneProximityM", _proxM, false];
missionNamespace setVariable ["COMSPEC_AtakPhoneProxInside", createHashMap, false];
[{
    [] call comspec_overwatch_atak_athena_fnc_athena_phoneProximityTick;
}, 1.5, []] call CBA_fnc_addPerFrameHandler;

// Bandeau OK/NOK · débit · err sur le téléphone ATAK (quand ouvert)
[{
    private _d = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    if (isNull _d) then {
        _d = uiNamespace getVariable ["cTab_Android_dsp", displayNull];
    };
    if (isNull _d) exitWith {};
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_updateLinkStrip") then {
        [] call comspec_overwatch_atak_athena_fnc_athena_updateLinkStrip;
    };
}, 2, []] call CBA_fnc_addPerFrameHandler;

// Wave Relay IceMan → Relais AT (si IceMan charge après et réécrit la tuile).
private _forceRelaisAt = {
    if (!isNil "Iceman_fnc_wr_onOpened" && {!isFinal Iceman_fnc_wr_onOpened}) then {
        Iceman_fnc_wr_onOpened = {
            params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];
            [_group, _interfaceInit, _isDialog, _settings] call comspec_overwatch_atak_athena_fnc_athena_relayOnOpened;
        };
        missionNamespace setVariable ["Iceman_fnc_wr_onOpened", Iceman_fnc_wr_onOpened];
        uiNamespace setVariable ["Iceman_fnc_wr_onOpened", Iceman_fnc_wr_onOpened];
    };
    if (!isNil "Iceman_fnc_wr_updatePanel" && {!isFinal Iceman_fnc_wr_updatePanel}) then {
        Iceman_fnc_wr_updatePanel = {
            [] call comspec_overwatch_atak_athena_fnc_athena_updateRelay;
        };
        missionNamespace setVariable ["Iceman_fnc_wr_updatePanel", Iceman_fnc_wr_updatePanel];
        uiNamespace setVariable ["Iceman_fnc_wr_updatePanel", Iceman_fnc_wr_updatePanel];
    };
};
call _forceRelaisAt;
{ [_forceRelaisAt, [], _x] call CBA_fnc_waitAndExecute; } forEach [1, 3, 8, 15];

// Liste du tiroir : Relais AT visible, Wave Relay IceMan retiré (sinon deux tuiles).
private _wrapDrawerApps = {
    if (isNil "BCE_fnc_ATAK_getAPPs") exitWith {};
    if (isFinal BCE_fnc_ATAK_getAPPs) exitWith {};
    if (isNil "COMSPEC_BCE_getAPPsOrig") then {
        COMSPEC_BCE_getAPPsOrig = BCE_fnc_ATAK_getAPPs;
    };
    BCE_fnc_ATAK_getAPPs = {
        private _apps = _this call COMSPEC_BCE_getAPPsOrig;
        if (!(_apps isEqualType [])) exitWith { _apps };
        if (isNil "comspec_overwatch_atak_athena_fnc_athena_filterDrawerApps") exitWith { _apps };
        _apps = [_apps] call comspec_overwatch_atak_athena_fnc_athena_filterDrawerApps;
        if (!isNil "BCE_fnc_ATAK_setAPPs_props" && {(count _apps) > 0}) then {
            [_apps] call BCE_fnc_ATAK_setAPPs_props;
        };
        _apps
    };
};
call _wrapDrawerApps;
{ [_wrapDrawerApps, [], _x] call CBA_fnc_waitAndExecute; } forEach [1, 3, 8, 15];
