/*
    Pré-init passerelle ACE dogtags / plaque d’identification ↔ SSE.
*/
if !(isNil "comspec_sse_aceDogtagBridgeReady") exitWith {};
comspec_sse_aceDogtagBridgeReady = false;

[
    "comspec_sse_aceDogtagBridgeEnabled",
    "CHECKBOX",
    ["Plaque ACE → identité SSE", "Quand on vérifie la plaque d’identification ACE (KO / mort), le nom et le code affichés viennent du profil SSE, et l’action alimente le dossier SSE."],
    ["COMSPEC SSE", "Compatibilité"],
    true,
    1,
    {},
    false
] call CBA_fnc_addSetting;
