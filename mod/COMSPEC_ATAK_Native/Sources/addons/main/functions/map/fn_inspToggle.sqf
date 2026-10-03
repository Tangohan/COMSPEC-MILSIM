/* Replie ou déplie le panneau SITUATION à droite de la carte (mémorisé dans le profil). */
profileNamespace setVariable ["COMSPEC_ATAK_InspOpen", !(profileNamespace getVariable ["COMSPEC_ATAK_InspOpen", true])];
[{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
