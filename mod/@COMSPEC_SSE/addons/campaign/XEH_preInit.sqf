#include "script_component.hpp"
/*
    Réglage CBA des campagnes (COMSPEC SSE › Campagnes).
*/
if (isNil "CBA_fnc_addSetting") exitWith {};
["comspec_sse_camp_athenaSync", "CHECKBOX",
    ["Copier les sauvegardes dans Athena", "À chaque sauvegarde, la campagne est envoyée dans Athena (Bureau SSE › Campagnes) si le serveur est relié (COMSPEC Link 2.0.64). Les drills temporaires ne sont jamais envoyés."],
    ["COMSPEC SSE", "Campagnes"], true, 1, {}, false] call CBA_fnc_addSetting;
