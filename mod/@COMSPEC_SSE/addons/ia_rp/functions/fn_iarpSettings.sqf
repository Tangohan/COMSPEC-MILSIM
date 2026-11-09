/*
    Réglages CBA de l'IA RP (COMSPEC SSE › IA RP), posés au preInit sur toutes les machines. Textes : stringtable.xml
    de l'addon (français, anglais), chacun avec son explication et un exemple.
    Globaux (le serveur décide) : texte libre, portée, indicateur, journal, longueur, ordres, /try, Zeus.
    Par joueur : couleurs des commandes. Touche d'ouverture : CBA › Touches (XEH_postInitClient).
*/
if (isNil "CBA_fnc_addSetting") exitWith { false };
private _cat = ["COMSPEC SSE", localize "STR_COMSPEC_IARP_Cat"];
private _add = {
    params ["_var", "_type", "_key", "_value", ["_global", 1]];
    [_var, _type, [localize (_key + "_T"), localize (_key + "_D")], _cat, _value, _global, {}, false] call CBA_fnc_addSetting;
};
["comspec_sse_iarp_enabled", "CHECKBOX", "STR_COMSPEC_IARP_S_Enabled", true] call _add;
["comspec_sse_iarp_range", "SLIDER", "STR_COMSPEC_IARP_S_Range", [5, 60, 20, 0]] call _add;
["comspec_sse_iarp_typing", "CHECKBOX", "STR_COMSPEC_IARP_S_Typing", true] call _add;
["comspec_sse_iarp_maxLen", "SLIDER", "STR_COMSPEC_IARP_S_MaxLen", [100, 1000, 600, 0]] call _add;
["comspec_sse_iarp_orders", "CHECKBOX", "STR_COMSPEC_IARP_S_Orders", true] call _add;
["comspec_sse_iarp_tryChance", "SLIDER", "STR_COMSPEC_IARP_S_Try", [5, 95, 50, 0]] call _add;
["comspec_sse_iarp_log", "CHECKBOX", "STR_COMSPEC_IARP_S_Log", true] call _add;
["comspec_sse_iarp_logMax", "SLIDER", "STR_COMSPEC_IARP_S_LogMax", [50, 2000, 500, 0]] call _add;
["comspec_sse_iarp_zeusFeed", "CHECKBOX", "STR_COMSPEC_IARP_S_Zeus", true] call _add;
["comspec_sse_iarp_colSay", "COLOR", "STR_COMSPEC_IARP_S_ColSay", [0.97, 0.98, 0.95], 0] call _add;
["comspec_sse_iarp_colMe", "COLOR", "STR_COMSPEC_IARP_S_ColMe", [0.8, 0.62, 1], 0] call _add;
["comspec_sse_iarp_colDo", "COLOR", "STR_COMSPEC_IARP_S_ColDo", [0.55, 0.8, 1], 0] call _add;
["comspec_sse_iarp_colTry", "COLOR", "STR_COMSPEC_IARP_S_ColTry", [0.5, 0.95, 0.55], 0] call _add;
["comspec_sse_iarp_colTodo", "COLOR", "STR_COMSPEC_IARP_S_ColTodo", [1, 0.75, 0.35], 0] call _add;
["comspec_sse_iarp_colAi", "COLOR", "STR_COMSPEC_IARP_S_ColAi", [0.62, 0.9, 1], 0] call _add;
true
