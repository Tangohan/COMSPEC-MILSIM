/*
    Réglages CBA de l'équipement (COMSPEC SSE › Équipement). Prise de kit et distance : globaux (le serveur décide) ;
    liste des objets remplacés : propre à chaque joueur.
*/
if (isNil "CBA_fnc_addSetting") exitWith { false };
private _cat = ["COMSPEC SSE", "Équipement"];
private _add = {
    params ["_var", "_type", "_label", "_value", ["_global", 1]];
    [_var, _type, _label, _cat, _value, _global, {}, false] call comspec_sse_fnc_addSetting;
};
["comspec_sse_lo_take", "CHECKBOX", ["Prendre mon kit", "Les caisses de kits (module ou attribut Eden) proposent aux joueurs leur kit Athena ou la tenue de la caisse (menu ACE)."], true] call _add;
["comspec_sse_lo_distance", "SLIDER", ["Distance de la caisse (m)", "Distance maximale entre le joueur et la caisse de kits, vérifiée par le serveur."], [2, 20, 5, 0]] call _add;
["comspec_sse_lo_showReplaced", "CHECKBOX", ["Montrer les objets absents ou remplacés", "À la pose d'un kit, liste les objets absents de vos mods (retirés) et les chargeurs remplacés. Décoché : leur nombre seulement (détail au RPT)."], true, 0] call _add;
true
