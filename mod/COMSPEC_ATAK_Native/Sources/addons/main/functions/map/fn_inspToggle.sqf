/* Replie ou déplie le panneau SITUATION à droite de la carte (replié à chaque mission).
   Params : ["full"] : bascule la vue CONFIGURATION (panneau élargi, toutes les sections dépliées et expliquées). */
params [["_what", ""]];
if (_what isEqualTo "full") then {
    missionNamespace setVariable ["COMSPEC_ATAK_InspFull", !(missionNamespace getVariable ["COMSPEC_ATAK_InspFull", false])];
} else {
    missionNamespace setVariable ["COMSPEC_ATAK_InspOpen", !(missionNamespace getVariable ["COMSPEC_ATAK_InspOpen", false])];
    // Panneau replié : il se rouvrira à sa largeur normale.
    missionNamespace setVariable ["COMSPEC_ATAK_InspFull", false];
};
[{ [] call comspec_atak_native_fnc_layoutApply; ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
