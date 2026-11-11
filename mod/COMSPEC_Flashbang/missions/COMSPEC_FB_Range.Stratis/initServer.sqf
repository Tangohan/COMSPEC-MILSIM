/*
    Champ de test COMSPEC Flashbang (Stratis, piste de l'aérodrome) : tout est posé par script, sur le plat de la piste.
      Départ       caisses de grenades, panneau de consignes
      Zone A       champ ouvert : cercle de lancer, 10 IA de 2 à 35 m, face au cercle ou dos tourné, 2 civils
      Zone B       pièce fermée (Cargo House) : 2 IA dedans, 1 IA dehors derrière le mur
      Zone C       pièce avec fenêtres (Cargo HQ) : IA dedans, IA dehors face à la fenêtre, IA dehors dos tourné
      Zone D       couloir (caserne) : IA au fond du couloir et dans les chambres
      Zone E       hangar (atelier industriel) : IA à 5, 12 et 20 m
      Zone F       délais : caisse des grenades 3 s / 8 s sur la ligne de lancer, pièce fermée à 15 m avec 2 IA
                   (comparer 1,5 s / 3 s / 8 s : la grenade roule jusqu'à la pièce et détone à l'heure)
    Les IA sont du camp du joueur (réglage « Effet sur son camp » actif par défaut), immobiles et invulnérables.
*/
private _p0 = [1650.5, 5467.7, 0];
private _fwd = [sin 15, cos 15, 0];
private _right = [cos 15, -(sin 15), 0];
private _at = {
    params ["_f", ["_r", 0]];
    private _p = _p0 vectorAdd (_fwd vectorMultiply _f) vectorAdd (_right vectorMultiply _r);
    [_p select 0, _p select 1, 0]
};
private _obj = {
    params ["_class", "_pos", ["_dir", 15]];
    private _o = createVehicle [_class, _pos, [], 0, "CAN_COLLIDE"];
    _o setDir _dir;
    _o setPosATL _pos;
    _o setVectorUp [0, 0, 1];
    _o
};
private _grp = createGroup [west, true];
private _civ = createGroup [civilian, true];
private _target = {
    params ["_pos", "_lookAt", ["_away", false], ["_g", grpNull], ["_class", "B_Soldier_F"]];
    if (isNull _g) then { _g = _grp };
    private _u = _g createUnit [_class, [0, 0, 0], [], 0, "CAN_COLLIDE"];
    _u setPosATL _pos;
    private _dir = _pos getDir _lookAt;
    _u setDir ([_dir, _dir + 180] select _away);
    _u setFormDir ([_dir, _dir + 180] select _away);
    _u disableAI "PATH";
    _u setUnitPos "UP";
    _u allowDamage false;
    _u
};

// ------------------------------------------------------------------ départ
private _start = [0, -8] call _at;
private _box = ["COMSPEC_FB_Box", [2, -10] call _at, 105] call _obj;
["COMSPEC_FB_Box_Delay", [-2, -10] call _at, 105] call _obj;
["COMSPEC_FB_Box_Special", [6, -10] call _at, 105] call _obj;
private _sign = ["SignAd_Sponsor_F", [0, -14] call _at, 105] call _obj;
_sign setObjectTextureGlobal [0, "images\consignes.paa"];
missionNamespace setVariable ["COMSPEC_FB_Range_Box", _box, true];
createMarker ["respawn_west", _start];

// ------------------------------------------------------------------ zone A : champ ouvert
private _ga = [25, 0] call _at;
["Land_HelipadCircle_F", _ga] call _obj;
{
    _x params ["_dist", "_angle", "_away"];
    private _p = _ga vectorAdd [_dist * sin (15 + _angle), _dist * cos (15 + _angle), 0];
    [[_p select 0, _p select 1, 0], _ga, _away] call _target;
} forEach [
    [2, 60, false], [4, 120, false], [6, 90, false], [6, 150, true], [10, 70, false],
    [10, 110, true], [15, 90, false], [20, 80, false], [25, 100, false], [35, 90, false]
];
[[30, 10] call _at, _ga, false, _civ, "C_man_1"] call _target;
[[22, -12] call _at, _ga, true, _civ, "C_man_1"] call _target;

// ------------------------------------------------------------------ zone B : pièce fermée
private _b = ["Land_Cargo_House_V1_F", [90, 0] call _at, 105] call _obj;
private _bp = (_b buildingPos -1);
if (count _bp >= 2) then {
    [_bp select 0, _bp select 1, false] call _target;
    [_bp select 1, _bp select 0, true] call _target;
} else {
    [_b modelToWorld [0, 0, 0], [90, 5] call _at, false] call _target;
};
[[90, 9] call _at, getPosATL _b, false] call _target;

// ------------------------------------------------------------------ zone C : pièce avec fenêtres
private _c = ["Land_Cargo_HQ_V1_F", [140, 0] call _at, 105] call _obj;
private _cp = (_c buildingPos -1);
if (_cp isNotEqualTo []) then { [_cp select (floor ((count _cp) / 2)), getPosATL _c, false] call _target };
[[140, 14] call _at, getPosATL _c, false] call _target;
[[140, -14] call _at, getPosATL _c, true] call _target;

// ------------------------------------------------------------------ zone D : couloir
private _d = ["Land_i_Barracks_V1_F", [200, 0] call _at, 15] call _obj;
private _dp = (_d buildingPos -1);
{ [_dp select _x, getPosATL _d, false] call _target } forEach ([0, 3, 6, 9] select { _x < count _dp });

// ------------------------------------------------------------------ zone E : hangar
private _e = ["Land_i_Shed_Ind_F", [270, 0] call _at, 105] call _obj;
{ [[270 + _x, 2] call _at, getPosATL _e, false] call _target } forEach [5, 12, 20];

// ------------------------------------------------------------------ zone F : délais (3 s / 8 s)
private _fLine = [315, 0] call _at;
["COMSPEC_FB_Box_Delay", [315, -4] call _at, 105] call _obj;
["COMSPEC_FB_Box", [315, 4] call _at, 105] call _obj;
["Land_HelipadCircle_F", _fLine] call _obj;
private _f = ["Land_Cargo_House_V1_F", [330, 0] call _at, 105] call _obj;
private _fp = (_f buildingPos -1);
if (_fp isNotEqualTo []) then {
    [_fp select 0, _fLine, false] call _target;
    [(_fp select 0) vectorAdd [0.8, 0.8, 0], _fLine, true] call _target;
} else {
    [_f modelToWorld [0, 0, 0], _fLine, false] call _target;
};

// Repères de carte.
{
    _x params ["_name", "_f", "_text"];
    private _m = createMarker [_name, [_f, 0] call _at];
    _m setMarkerType "mil_dot";
    _m setMarkerText _text;
} forEach [
    ["fb_start", 0, "Départ / caisses"], ["fb_a", 25, "A - champ ouvert"], ["fb_b", 90, "B - pièce fermée"],
    ["fb_c", 140, "C - pièce à fenêtres"], ["fb_d", 200, "D - couloir"], ["fb_e", 270, "E - hangar"],
    ["fb_f", 315, "F - délais (ligne de lancer, pièce à 15 m)"]
];
missionNamespace setVariable ["COMSPEC_FB_Range_Zones", [0, 25, 90, 140, 200, 270, 315] apply { [_x, -6] call _at }, true];
