/*
    Réglages CBA de l'IA RP (COMSPEC SSE › IA RP), posés au preInit sur toutes les machines. Textes : stringtable.xml
    de l'addon (français, anglais), chacun avec son explication et un exemple.
    Globaux (le serveur décide) : texte libre, portée, indicateur, journal, longueur, ordres (durée et distance de relâche
    d'un « Stop »), réaction aux tirs proches, témoins, émotions des IA (dont la peur des armes), agressivité des IA
    (niveau, intensité des dégâts, mortel, assommer, arme sortie, moteur de corps à corps, gestes TSP), /try, Zeus.
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
["comspec_sse_iarp_haltTime", "SLIDER", "STR_COMSPEC_IARP_S_HaltTime", [15, 600, 120, 0]] call _add;
["comspec_sse_iarp_haltDist", "SLIDER", "STR_COMSPEC_IARP_S_HaltDist", [5, 100, 25, 0]] call _add;
["comspec_sse_iarp_shots", "CHECKBOX", "STR_COMSPEC_IARP_S_Shots", true] call _add;
["comspec_sse_iarp_shotRange", "SLIDER", "STR_COMSPEC_IARP_S_ShotRange", [10, 69, 40, 0]] call _add;
["comspec_sse_iarp_witness", "CHECKBOX", "STR_COMSPEC_IARP_S_Witness", true] call _add;
["comspec_sse_iarp_witnessRange", "SLIDER", "STR_COMSPEC_IARP_S_WitnessRange", [5, 60, 30, 0]] call _add;
// Émotions des IA (réglage global, enregistrable dans la mission : Eden › Paramètres CBA › Mission) : interrupteur, puis
// une case par émotion. Une IA réglée dans Eden ou par le Zeus (profil « emotions ») l'emporte sur ces cases.
["comspec_sse_iarp_emoOn", "CHECKBOX", "STR_COMSPEC_IARP_S_EmoOn", true] call _add;
{ [format ["comspec_sse_iarp_emo_%1", _x], "CHECKBOX", format ["STR_COMSPEC_IARP_S_Emo_%1", _x], true] call _add; } forEach ["peur", "armes", "nervosite", "colere", "mefiance", "soulagement"];
// Agressivité des IA envers les joueurs (fn_iarpAggro, fn_iarpMelee) : coupée par défaut. Une IA réglée dans Eden ou par
// le Zeus (niveau, trait, intensité, mortel) l'emporte sur ces réglages ; la colère doit rester cochée dans ses émotions.
["comspec_sse_iarp_aggro", "LIST", "STR_COMSPEC_IARP_S_Aggro", [["off", "verbal", "physical"],
    [localize "STR_COMSPEC_IARP_S_Aggro_off", localize "STR_COMSPEC_IARP_S_Aggro_verbal", localize "STR_COMSPEC_IARP_S_Aggro_physical"], 0]] call _add;
["comspec_sse_iarp_aggroDamage", "SLIDER", "STR_COMSPEC_IARP_S_AggroDamage", [0, 100, 50, 0]] call _add;
["comspec_sse_iarp_aggroLethal", "CHECKBOX", "STR_COMSPEC_IARP_S_AggroLethal", false] call _add;
["comspec_sse_iarp_aggroKo", "CHECKBOX", "STR_COMSPEC_IARP_S_AggroKo", false] call _add;
["comspec_sse_iarp_aggroWeapon", "CHECKBOX", "STR_COMSPEC_IARP_S_AggroWeapon", false] call _add;
// Corps à corps : TSP Melee s'il est chargé (CfgPatches « tsp_melee »), sinon le moteur intégré ; gestes de TSP Animate.
["comspec_sse_iarp_aggroMelee", "LIST", "STR_COMSPEC_IARP_S_AggroMelee", [["auto", "tsp", "sse"],
    [localize "STR_COMSPEC_IARP_S_AggroMelee_auto", localize "STR_COMSPEC_IARP_S_AggroMelee_tsp", localize "STR_COMSPEC_IARP_S_AggroMelee_sse"], 0]] call _add;
["comspec_sse_iarp_aggroTspAnim", "CHECKBOX", "STR_COMSPEC_IARP_S_AggroTspAnim", true] call _add;
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
