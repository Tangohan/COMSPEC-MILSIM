/*
    Montre connectée (app Garmin) : ce que mesure une montre de sport, sur le joueur local, toutes les 2 s.
      pas et distances (mêmes règles que l'app Distances), activités reconnues seules (course, marche, véhicule, vol,
      saut, nage, bateau), fréquence cardiaque (celle d'ACE quand elle existe, sinon estimée d'après l'effort,
      la fatigue et le stress), stress (tirs proches, ennemi proche, blessures, combat), Body Battery (se vide avec
      l'effort et le stress, se recharge au repos, au sommeil et hors du jeu), sommeil / repos (inconscient, ou
      immobile allongé ou assis au calme plus de 2 min).
    Gardé sur ce PC (profileNamespace COMSPEC_ATAK_Garmin), par jour réel : 14 jours et 40 activités ; envoyé au site à chaque
    écriture du profil (toutes les 5 min, Garmin.Report de COMSPEC Link 2.0.87 ; fiche du membre).
    Montre Garmin (objet COMSPEC_ATAK_GarminWatch dans l'emplacement montre, ou COMSPEC_ATAK_GarminWatchItem en objet divers)
    obligatoire si le réglage serveur comspec_atak_native_garmin_watch est coché (par défaut) : sans elle, aucune mesure.
    Params : [mode : "tick" | "save" (fin de mission : activité en cours close et profil écrit)
              | "push" (app Resynch : profil écrit et renvoyé au site tout de suite, activité en cours gardée ;
                retour [heure, état] de l'envoi)]
    Mémoire : missionNamespace COMSPEC_ATAK_Gm (HashMap) : bb, real (dernier instant réel vu [jour, seconde du jour]), hr, stress, goal,
              days (jour -> HashMap), acts (liste), cur (activité en cours), last (position), rest (secondes de repos)
*/
params [["_mode", "tick"]];
if (!hasInterface) exitWith { false };
// Instant « réel » [jour, seconde du jour] (jour du mois arrondi à 31 : sert aux écarts, pas à une date exacte). Gardés à
// part : en une seule valeur (~6,5e10 s) le flottant 32 bits de SQF n'a plus qu'un pas de 4096 s.
private _real = { systemTime params ["_y", "_mo", "_d", "_h", "_mi", "_s"]; [(_y * 12 + _mo) * 31 + _d, _h * 3600 + _mi * 60 + _s] };
private _dayKey = { systemTime params ["_y", "_mo", "_d"]; format ["%1-%2-%3", _y, [str _mo, "0" + str _mo] select (_mo < 10), [str _d, "0" + str _d] select (_d < 10)] };
private _gm = missionNamespace getVariable ["COMSPEC_ATAK_Gm", createHashMap];
if ((count _gm) isEqualTo 0) then {
    // Premier passage : relu du profil (paires), recharge de la Body Battery pendant le temps passé hors du jeu.
    private _p = profileNamespace getVariable ["COMSPEC_ATAK_Garmin", []];
    _gm = createHashMapFromArray _p;
    private _days = createHashMap;
    { _days set [_x select 0, createHashMapFromArray (_x select 1)]; } forEach (_gm getOrDefault ["days", []]);
    _gm set ["days", _days];
    _gm set ["acts", (_gm getOrDefault ["acts", []]) apply { createHashMapFromArray _x }];
    private _now = [] call _real;
    // Ancien profil (une seule valeur, imprécise) : pas de recharge hors du jeu cette fois.
    private _then = _gm getOrDefault ["real", _now];
    if !(_then isEqualTypeArray [0, 0]) then { _then = _now; };
    private _away = (((_now select 0) - (_then select 0)) * 86400 + ((_now select 1) - (_then select 1))) max 0;
    private _bb = _gm getOrDefault ["bb", 75];
    private _gain = ((_away / 3600) * 12) min (100 - _bb);
    _gm set ["bb", _bb + _gain];
    _gm set ["away", [_away, _gain]];
    // Batterie de la montre : rechargée hors du jeu (25 % par heure, comme posée sur son chargeur).
    _gm set ["wbat", ((_gm getOrDefault ["wbat", 100]) + (_away / 3600) * 25) min 100];
    _gm set ["real", _now];
    // Fréquence de repos propre au joueur (52 à 65), stable d'une partie à l'autre.
    private _seed = 0; { _seed = (_seed * 31 + _x) mod 997; } forEach (toArray (getPlayerUID player));
    _gm set ["rest_hr", 52 + (_seed mod 14)];
    _gm set ["hr", _gm get "rest_hr"];
    _gm set ["stress", 15];
    missionNamespace setVariable ["COMSPEC_ATAK_Gm", _gm];
};
private _days = _gm get "days";
private _today = [] call _dayKey;
private _day = _days getOrDefault [_today, createHashMap];
_days set [_today, _day];
private _add = { params ["_k", "_v"]; _day set [_k, (_day getOrDefault [_k, 0]) + _v]; };

// Activité en cours close et gardée si elle compte (distance minimale par type).
private _finish = {
    private _c = _gm getOrDefault ["cur", []];
    _gm set ["cur", []];
    if (_c isEqualTo []) exitWith {};
    _c params ["_kind", "_m", "_sec", "_pos", "_start"];
    private _type = switch (_kind) do {
        case "foot": { ["WALK", "RUN"] select (_m / (_sec max 1) > 2.3) };
        case "land": { "DRIVE" }; case "air": { "FLIGHT" }; case "halo": { "JUMP" }; case "sea": { "BOAT" }; default { "SWIM" };
    };
    if (_m < (createHashMapFromArray [["WALK", 400], ["RUN", 400], ["DRIVE", 1000], ["FLIGHT", 2000], ["JUMP", 100], ["BOAT", 500], ["SWIM", 100]] get _type)) exitWith {};
    private _loc = nearestLocations [_pos, ["NameCityCapital", "NameCity", "NameVillage", "NameLocal", "NameMarine"], 4000];
    private _place = if (_loc isEqualTo []) then { getText (configFile >> "CfgWorlds" >> worldName >> "description") } else { text (_loc select 0) };
    private _acts = _gm get "acts";
    _acts pushBack (createHashMapFromArray [["type", _type], ["place", _place], ["m", round _m], ["sec", round _sec], ["day", _today], ["at", _start], ["world", worldName]]);
    if ((count _acts) > 40) then { _gm set ["acts", _acts select [(count _acts) - 40]]; };
    if (_type in ["RUN", "WALK"]) then { [format ["%1_n", toLower _type], 1] call _add; [format ["%1_m", toLower _type], _m] call _add; };
    ["act_n", 1] call _add;
};

private _save = {
    // Jours au-delà des 14 derniers oubliés ; HashMaps rangées en paires (relues au prochain passage).
    private _keys = keys _days; _keys sort false;
    { _days deleteAt _x; } forEach (_keys select [14]);
    _gm set ["real", [] call _real];
    private _out = [];
    { if !(_x in ["days", "acts", "cur", "last", "tick", "saved", "min", "rest", "away", "web", "worn", "hrl", "altl", "alt0", "live"]) then { _out pushBack [_x, _y]; }; } forEach _gm;
    // Courbes gardées pour aujourd'hui et hier seulement (le reste des jours : les totaux).
    { (_days get _x) deleteAt "series"; } forEach (_keys select [2, 12]);
    _out pushBack ["days", (keys _days) apply { private _d = _days get _x; [_x, (keys _d) apply { [_x, _d get _x] }] }];
    _out pushBack ["acts", (_gm get "acts") apply { private _a = _x; (keys _a) apply { [_x, _a get _x] } }];
    profileNamespace setVariable ["COMSPEC_ATAK_Garmin", _out];
    // Fin de mission, resynchro : écrit tout de suite ; passage périodique : profil marqué modifié (fn_profileSave).
    ([["now"], ["dirty"]] select (_mode isEqualTo "tick")) call comspec_atak_native_fnc_profileSave;
    _gm set ["saved", diag_tickTime];
    // Envoi au site (fiche du membre) : la montre en entier, sans les courbes (Garmin.Report de COMSPEC Link 2.0.87).
    if !([] call comspec_atak_native_fnc_bridge) exitWith { _gm set ["web", [diag_tickTime, "SANS LIAISON"]]; };
    private _dayOut = (keys _days) apply {
        private _d = _days get _x;
        private _h = createHashMapFromArray [["day", _x], ["stress_avg", round ((_d getOrDefault ["stress_sum", 0]) / ((_d getOrDefault ["stress_t", 0]) max 1))]];
        { _h set [_x, round (_d getOrDefault [_x, 0])]; } forEach ["steps", "foot_m", "foot_s", "hr_max", "bb_up", "bb_down", "sleep_s", "run_n", "run_m", "walk_n", "walk_m", "act_n"];
        private _r = _d getOrDefault ["hr_rest", 999];
        _h set ["hr_rest", [round _r, 0] select (_r > 300)];
        _h
    };
    private _actOut = (_gm get "acts") apply { createHashMapFromArray [["day", _x get "day"], ["at", _x get "at"], ["type", _x get "type"], ["place", _x get "place"], ["world", _x get "world"], ["m", _x get "m"], ["sec", _x get "sec"]] };
    private _payload = createHashMapFromArray [
        ["player_uid", getPlayerUID player], ["call_sign", [player] call comspec_atak_native_fnc_unitCallsign],
        ["status", createHashMapFromArray [["bb", round (_gm get "bb")], ["hr", round (_gm getOrDefault ["hr", 0])], ["stress", round (_gm getOrDefault ["stress", 0])], ["rest_hr", _gm getOrDefault ["rest_hr", 60]], ["goal", _gm getOrDefault ["goal", 10000]]]],
        ["days", _dayOut], ["acts", _actOut]
    ];
    private _r = "COMSPECExtension" callExtension ["Garmin.Report", [[_payload] call comspec_atak_native_fnc_json]];
    if (_r isEqualType []) then { _r = _r param [0, ""]; };
    _gm set ["web", [diag_tickTime, [[format ["ERREUR %1", _r], "COMSPEC Link 2.0.87 requis"] select (_r isEqualTo ""), "OK"] select ((_r select [0, 3]) isEqualTo "OK|")]];
};

if (_mode isEqualTo "save") exitWith { [] call _finish; [] call _save; true };
if (_mode isEqualTo "push") exitWith { [] call _save; _gm getOrDefault ["web", []] };

// Montre Garmin obligatoire (réglage serveur « ATAK · Garmin ») : sans la « Montre Garmin » dans l'emplacement montre,
// rien n'est mesuré ni envoyé au site. L'activité en cours est close et le dernier point oublié quand on l'enlève (pas de
// saut de distance quand elle revient) ; l'app affiche « Aucune montre Garmin ».
private _worn = !(missionNamespace getVariable ["comspec_atak_native_garmin_watch", true])
    || {((assignedItems player) findIf { _x isKindOf ["COMSPEC_ATAK_GarminWatch", configFile >> "CfgWeapons"] }) >= 0}
    || {((items player) findIf { _x isKindOf ["COMSPEC_ATAK_GarminWatchItem", configFile >> "CfgWeapons"] }) >= 0};
private _was = _gm getOrDefault ["worn", true];
_gm set ["worn", _worn];
if (!_worn) exitWith {
    if (_was) then { [] call _finish; _gm set ["last", []]; };
    false
};

private _now = diag_tickTime;
private _dt = (_now - (_gm getOrDefault ["tick", _now])) min 10;
_gm set ["tick", _now];
_gm set ["real", [] call _real];
if (_dt <= 0) exitWith { true };
private _u = player;
if (!alive _u) exitWith { _gm set ["last", []]; true };

// --- Déplacement (règles de fn_distanceServer) ---
private _veh = vehicle _u;
private _pos = getPosASL _u;
private _vz = (velocity _u) select 2;
private _alt = (getPosATL _u) select 2;
private _free = _veh isEqualTo _u && {!isTouchingGround _u} && {_alt > 15} && {_vz < -8 || {"halofreefall" in toLower (animationState _u)}};
private _swim = ((toLower (animationState _u)) select [1, 3]) in ["swm", "sdv", "bsw", "bdv"];
private _kind = switch (true) do {
    case (_veh isKindOf "ParachuteBase"): { "halo" };
    case (_veh isEqualTo _u): { ["foot", "halo"] select _free };
    case (_veh isKindOf "Air"): { "air" };
    case (_veh isKindOf "Ship"): { "sea" };
    default { "land" };
};
if (_kind isEqualTo "foot" && {_swim}) then { _kind = "swim"; };
(_gm getOrDefault ["last", []]) params [["_lu", objNull], ["_lp", []]];
_gm set ["last", [_u, _pos]];
private _d = 0;
if (_lu isEqualTo _u && {_lp isNotEqualTo []}) then {
    _d = if (_kind isEqualTo "halo") then { _lp vectorDistance _pos } else { _lp distance2D _pos };
    private _vmax = createHashMapFromArray [["foot", 14], ["swim", 6], ["land", 130], ["sea", 60], ["air", 900], ["halo", 120]];
    if (_d / _dt > (_vmax get _kind)) then { _d = 0; };
};
private _v = _d / _dt;
private _moving = _v > ([1.2, 0.5] select (_kind in ["foot", "swim"]));
if (_kind isEqualTo "foot" && {_d >= 0.3} && {stance _u isNotEqualTo "PRONE"}) then {
    ["steps", _d / (0.7 max (0.45 + 0.2 * _v) min 1.6)] call _add;
};
if (_kind in ["foot", "swim"] && {_moving}) then { ["foot_m", _d] call _add; ["foot_s", _dt] call _add; };

// --- Activité en cours : même genre, en mouvement ; close après 90 s d'arrêt ou au changement de genre ---
private _cur = _gm getOrDefault ["cur", []];
if (_moving || {_kind isEqualTo "halo"}) then {
    if (_cur isNotEqualTo [] && {(_cur select 0) isNotEqualTo _kind}) then { [] call _finish; _cur = []; };
    if (_cur isEqualTo []) then {
        systemTime params ["", "", "", "_h", "_mi"];
        _cur = [_kind, 0, 0, getPosATL _u, format ["%1:%2", _h, [str _mi, "0" + str _mi] select (_mi < 10)], 0];
        _gm set ["cur", _cur];
    };
    _cur set [1, (_cur select 1) + _d];
    _cur set [2, (_cur select 2) + _dt];
    _cur set [5, 0];
} else {
    if (_cur isNotEqualTo []) then {
        _cur set [5, (_cur select 5) + _dt];
        if ((_cur select 5) > 90) then { [] call _finish; };
    };
};

// --- Stress (0-100) ---
private _enemy = _u findNearestEnemy _u;
private _sT = ((getSuppression _u) * 70) + ([0, 30] select (!isNull _enemy && {(_enemy distance _u) < 300})) + ((damage _u) * 60) + ([0, 15] select (behaviour _u isEqualTo "COMBAT"));
private _uncon = _u getVariable ["ACE_isUnconscious", false] || {lifeState _u isEqualTo "INCAPACITATED"};
private _still = !_moving && {_kind isEqualTo "foot"} && {stance _u isEqualTo "PRONE" || {"sit" in toLower (animationState _u)}};
private _rest = [0, (_gm getOrDefault ["rest", 0]) + _dt] select (_uncon || _still);
_gm set ["rest", _rest];
private _sleeping = _uncon || {_rest > 120 && {_sT < 25}};
if (_sleeping) then { _sT = _sT min 10; };
private _stress = (_gm get "stress") + ((0 max _sT min 100) - (_gm get "stress")) * ((_dt / 30) min 1);
_gm set ["stress", _stress];

// --- Fréquence cardiaque ---
private _restHr = _gm get "rest_hr";
private _ace = _u getVariable ["ace_medical_heartRate", -1];
private _hr = if (_ace isEqualType 0 && {_ace > 0}) then { _ace } else {
    private _move = switch (true) do {
        case (_kind isEqualTo "halo"): { 55 };
        case (_kind in ["foot", "swim"]): { if (_v < 0.3) then { 0 } else { if (_v < 2) then { 20 + 5 * _v } else { if (_v < 4) then { 45 + 12 * _v } else { 70 + 10 * _v } } } };
        case (_kind isEqualTo "air" && {currentPilot _veh isEqualTo _u}): { 15 };
        default { 5 };
    };
    private _t = (_restHr + _move + (getFatigue _u) * 35 + _stress * 0.35 - ([0, 6] select _sleeping)) min 195;
    (_gm get "hr") + (_t - (_gm get "hr")) * ((_dt / 20) min 1)
};
_gm set ["hr", _hr];
_day set ["hr_max", (_day getOrDefault ["hr_max", 0]) max _hr];
if (!_moving && {_kind isEqualTo "foot"}) then { _day set ["hr_rest", (_day getOrDefault ["hr_rest", 999]) min _hr]; };

// --- Body Battery ---
private _effort = 0 max ((_hr - _restHr) / 100) min 1;
private _bb = _gm get "bb";
private _delta = if (_sleeping) then { 20 } else { if (!_moving && {_stress < 25}) then { 8 } else { -(6 + 30 * _effort + 10 * (_stress / 100)) } };
_delta = _delta * _dt / 3600;
private _nb = 5 max (_bb + _delta) min 100;
if (_nb > _bb) then { ["bb_up", _nb - _bb] call _add; } else { ["bb_down", _bb - _nb] call _add; };
_gm set ["bb", _nb];
if (_sleeping) then { ["sleep_s", _dt] call _add; };
["stress_sum", _stress * _dt] call _add;
["stress_t", _dt] call _add;

// --- Montre au poignet (fn_watch) : courbes en direct (FC toutes les 10 s sur 12 min, altitude toutes les 15 s sur
// 30 min), dénivelé du jour à pied (paliers de 3 m), batterie (0,35 %/h, 2 %/h pendant une activité suivie au GPS) ---
private _altNow = _pos select 2;
private _a0 = _gm getOrDefault ["alt0", _altNow];
if (_kind in ["foot", "swim"]) then {
    if (_altNow - _a0 >= 3) then { ["up_m", _altNow - _a0] call _add; _a0 = _altNow; };
    if (_a0 - _altNow >= 3) then { ["down_m", _a0 - _altNow] call _add; _a0 = _altNow; };
} else { _a0 = _altNow; };
_gm set ["alt0", _a0];
private _live = _gm getOrDefault ["live", 0];
if (_now - _live >= 10) then {
    _gm set ["live", _now];
    private _hl = _gm getOrDefault ["hrl", []];
    _hl pushBack round _hr;
    _gm set ["hrl", _hl select [((count _hl) - 72) max 0]];
    if ((round (_now / 10)) mod 3 isNotEqualTo 1) then {
        private _al = _gm getOrDefault ["altl", []];
        _al pushBack round _altNow;
        _gm set ["altl", _al select [((count _al) - 120) max 0]];
    };
};
_gm set ["wbat", 3 max ((_gm getOrDefault ["wbat", 100]) - ([0.35, 2] select ((_gm getOrDefault ["cur", []]) isNotEqualTo [])) * _dt / 3600)];

// --- Courbe du jour : un point toutes les 5 min réelles [heure décimale, BB, stress, FC, repos] ---
private _min = [(_gm get "real") select 0, floor (((_gm get "real") select 1) / 300)];
if (_min isNotEqualTo (_gm getOrDefault ["min", -1])) then {
    _gm set ["min", _min];
    systemTime params ["", "", "", "_h", "_mi"];
    private _ser = _day getOrDefault ["series", []];
    _ser pushBack [_h + _mi / 60, round _nb, round _stress, round _hr, [0, 1] select _sleeping];
    if ((count _ser) > 288) then { _ser deleteAt 0; };
    _day set ["series", _ser];
};
// Profil écrit toutes les 15 min (saveProfileNamespace écrit tout le profil d'un coup : petite saccade) ; aussi en fin de
// mission (save) et à la resynchro (push). Premier enregistrement 1 min après le début.
if ((_now - (_gm getOrDefault ["saved", _now - 840])) > 900) then { [] call _save; };
true
