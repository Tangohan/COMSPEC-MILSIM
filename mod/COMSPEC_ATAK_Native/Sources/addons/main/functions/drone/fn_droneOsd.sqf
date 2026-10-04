/*
    Télémétrie du drone affichée dans l'app (et rafraîchie chaque seconde par fn_droneAction "tick").
    Params : [drone, liaison [barres, distance, ok]]. Retourne un texte structuré.
*/
params ["_d", ["_l", [0, 0, false]]];
if (isNull _d) exitWith { "" };
private _modes = createHashMapFromArray [["HOVER", "Stationnaire"], ["FOLLOW", "Suivi"], ["HOME", "Retour au pilote"], ["LAND", "Atterrissage"], ["RTH", "Retour auto (liaison perdue)"], ["STRIKE", "FRAPPE EN COURS"], ["HUNT", "Recherche"], ["MANUAL", "Pilotage manuel (terminal UAV)"]];
private _m = _d getVariable ["COMSPEC_DroneMode", "HOVER"];
private _f = _d getVariable ["COMSPEC_DroneFollow", objNull];
private _bars = ["▂▄▆█" select [0, _l select 0], "<t color='#e5483a'>HORS LIAISON</t>"] select !(_l select 2);
format ["<t font='RobotoCondensedBold'>%1</t>  <t color='%2'>%3</t>%4<br/><t size='0.85' color='#c9d4cf'>Alt %5 m · %6 km/h · carburant %7 %% · %8 m · liaison %9</t><br/><t size='0.8' color='#8a9a93'>%10 · %11</t>",
    getText (configOf _d >> "displayName"),
    ["#5cc76b", "#e5483a"] select (_m in ["STRIKE", "RTH"]), _modes getOrDefault [_m, _m],
    ["", format [" <t size='0.85' color='#8a9a93'>(%1)</t>", name _f]] select (_m isEqualTo "FOLLOW" && {!isNull _f}),
    round ((getPosATL _d) select 2), round speed _d, round (100 * fuel _d), _l select 1, _bars,
    [(getPosASL _d), 8] call comspec_atak_native_fnc_gridRef,
    ["Non armé", format ["<t color='#f2ab33'>Armé : %1</t>", _d getVariable ["COMSPEC_DroneArmedLabel", "charge"]]] select ((_d getVariable ["COMSPEC_DroneArmed", ""]) isNotEqualTo "")]
