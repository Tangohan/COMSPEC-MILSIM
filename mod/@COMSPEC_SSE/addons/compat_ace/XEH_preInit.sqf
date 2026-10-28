/*
    Pré-init passerelle ACE dogtags / plaque d’identification ↔ SSE.
*/
if !(isNil "comspec_sse_aceDogtagBridgeReady") exitWith {};
comspec_sse_aceDogtagBridgeReady = false;

[
    "comspec_sse_aceDogtagBridgeEnabled",
    "CHECKBOX",
    ["Plaques ACE avec l'identité SSE", "Quand on lit la plaque d’identité ACE d'un blessé ou d'un mort, le nom affiché est celui de son profil SSE, et la lecture s'ajoute au dossier SSE."],
    ["COMSPEC SSE", "Compatibilité"],
    true,
    1,
    {},
    false
] call comspec_sse_fnc_addSetting;
