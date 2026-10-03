/* Replie ou déplie le panneau SITUATION à droite de la carte (replié à chaque mission). */
missionNamespace setVariable ["COMSPEC_ATAK_InspOpen", !(missionNamespace getVariable ["COMSPEC_ATAK_InspOpen", false])];
[{ [] call comspec_atak_native_fnc_layoutApply; ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
