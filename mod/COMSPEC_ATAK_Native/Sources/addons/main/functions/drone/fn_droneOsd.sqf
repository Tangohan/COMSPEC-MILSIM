/*
    Télémétrie du drone affichée dans l'app (et rafraîchie chaque seconde par fn_droneAction "tick").
    Params : [drone, liaison [barres, distance, ok], "HEAD" | "FEED" | "NAME" | "ICON" | "ICONID"]
      "HEAD" : en-tête de la page, deux lignes compactes (texte structuré) ;
      "FEED" : incrustations de la vue caméra, à la façon d'un DJI : [haut gauche, haut centre, haut droite, bas gauche, bas droite] ;
      "NAME" : nom donné par le pilote (variable publique COMSPEC_DroneName), sinon le nom du modèle ;
      "ICONID" / "ICON" : icône choisie (variable publique COMSPEC_DroneIcon : quad, fixed, hexa, nano, fpv),
                 sinon devinée d'après le modèle ; "ICON" renvoie le chemin de la texture (data\drone_<id>.paa).
*/
params ["_d", ["_l", [0, 0, false]], ["_kind", "HEAD"]];
if (isNull _d) exitWith { ["", ["", "", "", "", ""]] select (_kind isEqualTo "FEED") };
if (_kind isEqualTo "NAME") exitWith {
    private _n = _d getVariable ["COMSPEC_DroneName", ""];
    [_n, getText (configOf _d >> "displayName")] select (_n isEqualTo "")
};
if (_kind in ["ICON", "ICONID"]) exitWith {
    private _id = _d getVariable ["COMSPEC_DroneIcon", ""];
    if !(_id in ["quad", "fixed", "hexa", "nano", "fpv"]) then {
        private _t = toLower typeOf _d;
        _id = switch (true) do {
            case (_d isKindOf "Plane"): { "fixed" };
            case (_d isKindOf "UAV_06_base_F" || {(_t find "hexa") >= 0}): { "hexa" };
            case ((_t find "fpv") >= 0): { "fpv" };
            case ((_t find "hornet") >= 0 || {(_t find "nano") >= 0}): { "nano" };
            default { "quad" };
        };
    };
    [_id, format ["\z\comspec_atak_native\addons\main\data\drone_%1.paa", _id]] select (_kind isEqualTo "ICON")
};
private _modes = createHashMapFromArray [["HOVER", "Stationnaire"], ["FOLLOW", "Suivi"], ["HOME", "Retour au pilote"], ["LAND", "Atterrissage"], ["RTH", "Retour automatique"], ["STRIKE", "FRAPPE EN COURS"], ["HUNT", "Recherche"], ["MANUAL", "Pilotage manuel (terminal UAV)"], ["GOTO", "Vers le point"], ["LOITER", "Orbite"], ["OBSERVE", "Observation"], ["ESCORT", "Escorte"], ["ROUTE", "Route"], ["STANDBY", "Veille"]];
private _m = _d getVariable ["COMSPEC_DroneMode", "HOVER"];
private _f = _d getVariable ["COMSPEC_DroneFollow", objNull];
private _h = round ((getPosATL _d) select 2);
private _ground = ((getPosATL _d) select 2) < 1 && {!(_m in ["STRIKE"])};
private _state = switch (true) do {
    case (_m isEqualTo "STANDBY"): { ["Veille, stationnaire", "Veille, posé"] select ((_d getVariable ["COMSPEC_DroneStandby", "LAND"]) isEqualTo "LAND") };
    case (_ground && {!isEngineOn _d}): { "Au sol, moteurs coupés" };
    case (_ground && {_m isNotEqualTo "LAND"}): { "Au sol, moteurs en marche" };
    default { _modes getOrDefault [_m, _m] };
};
private _cqb = _d getVariable ["COMSPEC_DroneCqb", false];
private _who = ["", format [" (%1)", name _f]] select (_m in ["FOLLOW", "ESCORT"] && {!isNull _f} && {!_ground});
private _bat = round (100 * fuel _d);
private _batHex = ["#c9d4cf", "#f2ab33", "#e5483a"] select ([_bat > 20, _bat > 10 && {_bat <= 20}, _bat <= 10] find true);
private _link = ["<t color='#e5483a'>Hors liaison</t>", format ["Liaison %1/4", _l select 0]] select (_l select 2);
private _armed = (_d getVariable ["COMSPEC_DroneArmed", ""]) isNotEqualTo "";
private _stateHex = switch (true) do { case (_m in ["STRIKE", "RTH"]): { "#e5483a" }; case (_m isEqualTo "STANDBY"): { "#8fb3c9" }; default { "#5cc76b" }; };
private _name = [_d, _l, "NAME"] call comspec_atak_native_fnc_droneOsd;
if (_kind isEqualTo "HEAD") exitWith {
    format ["<img image='%14' size='1.05' color='#5cc76b'/> <t font='RobotoCondensedBold'>%1</t>  <t color='%2'>%3</t>%4%5<br/><t size='0.82' color='#c9d4cf'>H %6 m · %7 km/h · <t color='%8'>Batterie %9 %%</t> · à %10 m · %11 · Grille %12%13</t>",
        _name, _stateHex, _state, ["", " <t color='#f2ab33'>· CQB</t>"] select _cqb, _who,
        _h, round speed _d, _batHex, _bat, _l select 1, _link, [getPosASL _d, 8] call comspec_atak_native_fnc_gridRef,
        ["", format [" · <t color='#e5483a'>Armé : %1</t>", _d getVariable ["COMSPEC_DroneArmedLabel", "charge"]]] select _armed, [_d, _l, "ICON"] call comspec_atak_native_fnc_droneOsd]
};
// Vue caméra : point du sol sous le réticule (là où regarde la nacelle).
private _eye = _d modelToWorldVisualWorld [0, 0.3, -0.3];
private _dir = missionNamespace getVariable ["COMSPEC_ATAK_DroneCamDir", []];
if (_dir isEqualTo []) then { _dir = _d vectorModelToWorldVisual [0, 0.8, -0.6]; };
private _hit = lineIntersectsSurfaces [_eye, _eye vectorAdd (_dir vectorMultiply 3000), _d, objNull, true, 1];
private _under = if ((count _hit) > 0) then { format ["%1 · %2 m", [(_hit select 0) select 0, 8] call comspec_atak_native_fnc_gridRef, round (_eye distance ((_hit select 0) select 0))] } else { "Ciel" };
private _rec = round (time - (missionNamespace getVariable ["COMSPEC_ATAK_DroneCamT0", time]));
private _zoom = missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1];
private _vis = ["", " · NUIT", " · THERMIQUE"] select (missionNamespace getVariable ["COMSPEC_ATAK_DroneVision", 0]);
private _sh = "shadow='2' shadowColor='#000000'";
[
    format ["<t size='0.8' %1><t color='#e5483a'>●</t> REC %2:%3<br/>%4</t>", _sh, [floor (_rec / 60), 2] call CBA_fnc_formatNumber, [_rec mod 60, 2] call CBA_fnc_formatNumber, _name],
    format ["<t size='0.8' align='center' %1>CAP %2° · <t color='%3'>%4</t>%5 · x%6%7</t>", _sh, [round (getDirVisual _d) mod 360, 3] call CBA_fnc_formatNumber, _stateHex, _state, ["", " · CQB"] select _cqb, _zoom, _vis],
    format ["<t size='0.8' align='right' %1>%2<br/><t color='%3'>BAT %4 %%</t>%5</t>", _sh, _link, _batHex, _bat, ["", "<br/><t color='#e5483a' font='RobotoCondensedBold'>ARMÉ</t>"] select _armed],
    format ["<t size='0.8' %1>H %2 m · D %3 m · %4 km/h</t>", _sh, _h, _l select 1, round speed _d],
    format ["<t size='0.8' align='right' %1>%2</t>", _sh, _under]
]
