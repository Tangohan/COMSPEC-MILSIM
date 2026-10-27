/*
    Pré-init passerelle BII Identifi ↔ COMSPEC SSE.
*/
if !(isNil "comspec_sse_biiBridgeReady") exitWith {};
comspec_sse_biiBridgeReady = false;

[
    "comspec_sse_biiBridgeEnabled",
    "CHECKBOX",
    ["Compatibilité BII Identifi", "Récupère les profils, preuves et scans du mod BII Identifi dans le SSE, et accepte le BII-10 comme terminal biométrique (comme le SEEK)."],
    ["COMSPEC SSE", "Compatibilité"],
    true,
    1,
    {
        // Activée en cours de mission alors qu'elle était coupée au postInit : pose matériel + hooks (idempotent).
        params [["_on", true]];
        if (!_on || {!(missionNamespace getVariable ["comspec_sse_biiPostInitDone", false])}) exitWith {};
        if (missionNamespace getVariable ["comspec_sse_biiHooksInstalled", false]) exitWith {};
        if !([] call comspec_sse_fnc_biiIsPresent) exitWith {};
        [] call comspec_sse_fnc_biiRegisterEquipment;
        [] call comspec_sse_fnc_biiInstallHooks;
        if (hasInterface) then { comspec_sse_biiBridgeReady = true; };
        ["Passerelle BII Identifi activee en cours de mission."] call comspec_sse_fnc_log;
    },
    false
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_biiExportToBii",
    "CHECKBOX",
    ["Renvoyer les identités SSE vers BII", "Après la création d'une identité SSE, la recopie aussi (identité, notes) dans les variables BII_Identifi_*, pour les modules qui utilisent les deux mods."],
    ["COMSPEC SSE", "Compatibilité"],
    true,
    1,
    {},
    false
] call comspec_sse_fnc_addSetting;
