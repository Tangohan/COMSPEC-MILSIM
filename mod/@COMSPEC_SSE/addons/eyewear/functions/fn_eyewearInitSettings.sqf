/*
    Réglages CBA des lunettes de sport (COMSPEC SSE › Lunettes). Propres à chaque joueur (0) : c'est un rendu à son écran.
*/
if (isNil "CBA_fnc_addSetting") exitWith { false };
private _cat = ["COMSPEC SSE", "Lunettes"];

["comspec_sse_eyewear_tint", "CHECKBOX", ["Rendu visuel des verres", "Quand vous portez des lunettes de sport COMSPEC (slot lunettes, slot JVN ou slot Lunettes), l'écran prend la teinte du verre : Prizm Road chaud et rosé, Prizm Golf vert, Hurricane gris foncé, Wind violet ou ambre. Coupé quand les JVN sont allumées, en vue extérieure et au Zeus."], _cat, false, 0, {}, false] call comspec_sse_fnc_addSetting;
["comspec_sse_eyewear_tint_force", "SLIDER", ["Force de la teinte des verres", "0 : aucune teinte, 1 : teinte complète du verre."], _cat, [0, 1, 0.6, 2], 0, {}, false] call comspec_sse_fnc_addSetting;
true
