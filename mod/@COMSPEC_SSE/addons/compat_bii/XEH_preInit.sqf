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
    {},
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
