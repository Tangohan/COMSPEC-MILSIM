/*
    Pré-init passerelle BII Identifi ↔ COMSPEC SSE.
*/
if !(isNil "comspec_sse_biiBridgeReady") exitWith {};
comspec_sse_biiBridgeReady = false;

[
    "comspec_sse_biiBridgeEnabled",
    "CHECKBOX",
    ["Passerelle BII Identifi", "Importe les profils / preuves / scans BII-10 dans le modèle SSE COMSPEC et accepte le BII-10 comme matériel SEEK."],
    ["COMSPEC SSE", "Compatibilité"],
    true,
    1,
    {},
    false
] call CBA_fnc_addSetting;

[
    "comspec_sse_biiExportToBii",
    "CHECKBOX",
    ["Exporter SSE → variables BII", "Après génération SSE, recopie identité / notes vers les variables BII_Identifi_* (modules dual-use)."],
    ["COMSPEC SSE", "Compatibilité"],
    true,
    1,
    {},
    false
] call CBA_fnc_addSetting;
