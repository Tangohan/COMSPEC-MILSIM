if (!hasInterface) exitWith {};

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

// Photos BCE / Photo Library → upload Athena (après capture locale)
["bce_took_screenshot", {
    _this spawn {
        uiSleep 0.35;
        _this call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanPhoto;
    };
}] call CBA_fnc_addEventHandler;

// Événements Quick Pictures / Photo Library Iceman (noms variables selon version)
{
    [_x, {
        _this spawn {
            uiSleep 0.35;
            private _path = "";
            private _name = "";
            if (_this isEqualType []) then {
                if ((count _this) > 0) then { _path = _this select 0; };
                if ((count _this) > 1) then { _name = _this select 1; };
                // Certains EH passent [recordArray]
                if ((_path isEqualType []) && {(count _path) > 3}) then {
                    _name = _path select 3;
                    _path = _path select 2;
                };
            };
            if (_path isEqualType "" && {_path isNotEqualTo ""}) then {
                [_path, _name] call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanPhoto;
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

// Repli : surveillance périodique Photo Library (Quick Pictures sans EH)
[{
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
    [] call comspec_overwatch_atak_athena_fnc_athena_pollIcemanPhotos;
}, 2.5, []] call CBA_fnc_addPerFrameHandler;

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
    [] call comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers;
}, 1.5, []] call CBA_fnc_addPerFrameHandler;

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
