#include "script_component.hpp"
/*
    Réglage CBA des campagnes (COMSPEC SSE › Campagnes).
*/
if (isNil "CBA_fnc_addSetting") exitWith {};
["comspec_sse_camp_athenaSync", "CHECKBOX",
    ["Copier les sauvegardes dans Athena", "À chaque sauvegarde, la campagne est aussi envoyée sur Athena (Bureau SSE > Campagnes), si le serveur est connecté via COMSPEC Link. Les drills temporaires ne sont jamais envoyés."],
    ["COMSPEC SSE", "Campagnes"], true, 1, {}, false] call comspec_sse_fnc_addSetting;
