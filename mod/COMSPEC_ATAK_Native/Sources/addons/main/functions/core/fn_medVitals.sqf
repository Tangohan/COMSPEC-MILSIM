/*
    Constantes vitales d'un blessé, cohérentes avec la physiologie (retour de jeu 1.97.0 : tension calculée d'après le
    seul volume de sang, 120/70 en plein arrêt cardiaque, SpO2 à 0 %, « volume sanguin » qu'aucun moniteur ne mesure).
    Source : ACE Medical (pouls, tension, volume de sang, arrêt), KAT si présent (SpO2, fréquence respiratoire) ;
    sinon estimation selon la perte de sang (classes d'hémorragie ATLS I à IV).
    Ce qu'un moniteur de terrain peut vraiment afficher :
      - pas de pouls (arrêt, mort) : FC 0, tension et SpO2 « --- » (plus de signal), respiration 0 ;
      - choc grave (systolique < 70) : la SpO2 ne se mesure plus (doigt mal perfusé) ;
      - tension et SpO2 n'existent que mesurées (retour de jeu 1.97.0 : « la pression sanguine, c'est impossible ») :
        un soignant pose brassard et oxymètre (action ACE « Mesurer tension et SpO2 », mode "measure"), la valeur
        reste celle de la mesure (avec son âge) jusqu'à la suivante ; sans mesure : « non mesurée ».
    Params : [unité] -> HashMap ; [unité, "measure"] -> mesure (tension, SpO2) publiée sur l'unité
      state (stable, wounded, critical, unconscious, cardiac_arrest, kia), pulse (bool), hr, sys, dia, map (-1 si
      non mesurable), spo2 (-1 si non mesurable), rr, lossPct (perte estimée en %), hemClass (0 aucune, 1 à 4),
      nibpAge (s depuis la mesure, -1 : jamais mesurée), measured (bool)
*/
params [["_u", objNull], ["_mode", "get"]];
private _out = createHashMap;
if (isNull _u) exitWith { _out set ["state", "none"]; _out };
private _num = { params ["_v", "_d"]; if (isNil "_v") exitWith { _d }; if (_v isEqualType "") exitWith { parseNumber _v }; if (_v isEqualType 0) exitWith { _v }; _d };
private _ace = isClass (configFile >> "CfgPatches" >> "ace_medical");
private _bv = [_u getVariable ["ace_medical_bloodVolume", 6], 6] call _num;
if (!isNil "ace_medical_status_fnc_getBloodVolume") then { _bv = [[_u] call ace_medical_status_fnc_getBloodVolume, _bv] call _num; };
if (!_ace) then { _bv = 6 - 2.5 * (damage _u); };
private _loss = ((1 - _bv / 6) * 100) max 0 min 100;
private _cls = switch (true) do { case (_loss < 5): { 0 }; case (_loss < 15): { 1 }; case (_loss < 30): { 2 }; case (_loss < 40): { 3 }; default { 4 }; };
private _arrest = _u getVariable ["ace_medical_inCardiacArrest", false];
private _uncon = (_u getVariable ["ACE_isUnconscious", false]) || {lifeState _u isEqualTo "INCAPACITATED"};
private _state = switch (true) do {
    case (!alive _u): { "kia" };
    case (_arrest): { "cardiac_arrest" };
    case (_uncon): { "unconscious" };
    case (_cls >= 3): { "critical" };
    case (_cls >= 2 || {(_u getVariable ["ace_medical_woundBleeding", 0]) isEqualType 0 && {(_u getVariable ["ace_medical_woundBleeding", 0]) > 0}}): { "wounded" };
    default { "stable" };
};
_out set ["state", _state];
_out set ["lossPct", round _loss];
_out set ["hemClass", _cls];
// Variation propre au patient, lente (pas de chiffres qui sautent d'une seconde à l'autre).
private _seed = (parseNumber ((getPlayerUID _u) select [10])) + (round ((getPosASL _u) select 0) mod 97);
private _wob = { params ["_amp", "_per"]; _amp * sin ((time / _per) * 360 + _seed * 37) };
if (_state in ["kia", "cardiac_arrest"]) exitWith {
    _out set ["pulse", false];
    _out set ["hr", 0]; _out set ["sys", -1]; _out set ["dia", -1]; _out set ["map", -1]; _out set ["spo2", -1]; _out set ["rr", 0];
    _out set ["nibpAge", -1]; _out set ["measured", false];
    // Mesure sur un patient sans pouls : brassard et oxymètre ne trouvent rien.
    if (_mode isEqualTo "measure") then { _u setVariable ["COMSPEC_ATAK_Nibp", [[time, serverTime] select isMultiplayer, -1, -1, -1], true]; };
    _out
};
_out set ["pulse", true];
// Pouls : ACE s'il est là (il suit déjà douleur, perte de sang, médicaments), sinon ATLS.
private _hr = [_u getVariable ["ace_medical_heartRate", -1], -1] call _num;
if (!isNil "ace_medical_status_fnc_getHeartRate") then { _hr = [[_u] call ace_medical_status_fnc_getHeartRate, _hr] call _num; };
if (_hr <= 0) then { _hr = [72, 92, 112, 130, 148] select _cls; };
_hr = round ((_hr + ([2, 9] call _wob)) max 20 min 220);
// Tension (brassard) et SpO2 (oxymètre) : seulement mesurées par un soignant, gardées sur l'unité (publique).
private _now = [time, serverTime] select isMultiplayer;
if (_mode isEqualTo "measure") then {
    private _sys = -1; private _dia = -1;
    if (!isNil "ace_medical_status_fnc_getBloodPressure") then {
        ([_u] call ace_medical_status_fnc_getBloodPressure) params [["_lo", -1], ["_hi", -1]];
        _sys = [_hi, -1] call _num; _dia = [_lo, -1] call _num;
    };
    // Valeurs ACE absentes ou hors physiologie : ATLS (pression pulsée qui se pince à la classe II, chute à III et IV).
    if (_sys < 40 || {_sys > 250} || {_dia < 20} || {_dia >= _sys}) then {
        _sys = [122, 120, 114, 92, 72] select _cls;
        _dia = [78, 80, 86, 66, 48] select _cls;
    };
    if (_uncon) then { _sys = _sys - 6; _dia = _dia - 4; };
    _sys = round (_sys + random 6 - 3); _dia = round (_dia + random 4 - 2);
    _sys = _sys max 30; _dia = (_dia max 15) min (_sys - 10);
    // SpO2 : KAT si présent ; sinon 97-99 % au repos, baisse en choc grave ; pas de signal sous 70 de systolique.
    private _spo = [_u getVariable ["kat_breathing_airwayStatus", -1], -1] call _num;
    if (_spo <= 0 || {_spo > 100}) then { _spo = ([98, 98, 97, 95, 91] select _cls) - ([0, 2] select _uncon) + ([1, 30] call _wob); };
    if (_sys < 70) then { _spo = -1; };
    _u setVariable ["COMSPEC_ATAK_Nibp", [_now, _sys, _dia, [round (_spo max 50 min 100), -1] select (_spo < 0)], true];
};
(_u getVariable ["COMSPEC_ATAK_Nibp", []]) params [["_at", -1], ["_sys", -1], ["_dia", -1], ["_spo", -1]];
// Pas de mesure, ou mesure d'une autre partie / d'avant une réapparition (heure dans le futur).
if (_at < 0 || {_at > _now + 5}) then { _at = -1; _sys = -1; _dia = -1; _spo = -1; };
_out set ["measured", _at >= 0];
_out set ["sys", _sys]; _out set ["dia", _dia];
_out set ["map", [round ((_sys + 2 * _dia) / 3), -1] select (_sys < 0)];
_out set ["nibpAge", [round (_now - _at), -1] select (_at < 0)];
_out set ["spo2", _spo];
// Fréquence respiratoire : KAT si présent, sinon ATLS (14-20, 20-30, 30-40, > 35 ; plus lente inconscient sous morphine).
private _rr = [_u getVariable ["kat_breathing_breathRate", -1], -1] call _num;
if (_rr <= 0) then { _rr = ([15, 18, 24, 33, 38] select _cls) + ([1.5, 17] call _wob) - ([0, 4] select _uncon); };
_out set ["rr", round (_rr max 4 min 50)];
_out
