if (!hasInterface) exitWith {};

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
