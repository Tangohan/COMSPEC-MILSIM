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

// Messages de groupe Iceman → Athena
["Iceman_ATAK_GroupMessage", {
    _this call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanGroup;
}] call CBA_fnc_addEventHandler;

// Photos BCE / Photo Library → upload Athena (après capture locale)
["bce_took_screenshot", {
    // Légère latence : laisser Photo Library enregistrer le fichier d’abord
    _this spawn {
        uiSleep 0.35;
        _this call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanPhoto;
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
    [] call comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers;
}, 6, []] call CBA_fnc_addPerFrameHandler;

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
