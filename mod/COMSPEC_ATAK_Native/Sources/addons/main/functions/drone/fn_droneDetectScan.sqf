/*
    Détecteur de drones (type capteur RF passif) : balayage des liaisons radio des drones autour de moi.
    Params : [mode]  "manual" (bouton BALAYER : bilan en notification) | "auto" (balayage automatique : silencieux,
             au plus un toutes les 2,5 s) | "clear" (efface les détections)
    Réalisme : le récepteur n'entend que ce qui émet. Un drone n'est vu que s'il est vivant, armé (moteur en marche
    ou en vol) et que sa liaison (vidéo 5,8 GHz / C2 2,4 GHz) arrive au-dessus du bruit :
      - portée nominale 2 km (petit quadri) ou 4 km (voilure fixe, rotor lourd), en vue directe ;
      - relief entre nous : -22 dB ; chaque mur ou obstacle traversé : -7 dB (max 4) ; dans un véhicule : -6 dB ;
        pluie (surtout à 5,8 GHz) ; brouillage subi : plancher de bruit relevé ;
      - antenne du téléphone seule (variable d'unité COMSPEC_ATAK_DroneSensor = false, ou réglage de mission
        comspec_atak_native_drone_sensor = false) : -6 dB, soit la moitié de la portée.
    Sortie : un relèvement approximatif (±25° faible à ±5° fort), une bande de distance seulement quand le signal est
    fort (estimée d'après la puissance reçue, donc trop loin si le drone est masqué), jamais de position.
    AMI seulement si le drone est de mon camp et piloté par quelqu'un de mon groupe (on connaît sa propre liaison) ;
    tout le reste est INCONNU. Drone inconnu proche et fort : alerte et vibration (une fois par minute et par drone).
    Détections : missionNamespace COMSPEC_ATAK_DroneDetect =
      [[netId, bande, barres 1-4, relèvement, erreur, bande de distance, premier vu, dernier vu, ami,
        position du relevé, puissance (dBm), portée nominale (m)]...]  (temps en time ; oubliées 60 s après le dernier relevé)
    Renvoie le nombre de drones entendus à ce balayage.
*/
params [["_mode", "manual"]];
private _manual = _mode isEqualTo "manual";
private _now = time;
private _list = missionNamespace getVariable ["COMSPEC_ATAK_DroneDetect", []];
if (_mode isEqualTo "clear") exitWith {
    missionNamespace setVariable ["COMSPEC_ATAK_DroneDetect", []];
    uiNamespace setVariable ["COMSPEC_ATAK_DroneAlerted", createHashMap];
    0
};
if (!_manual && {diag_tickTime < (uiNamespace getVariable ["COMSPEC_ATAK_DroneScanAt", -1e9]) + 2.5}) exitWith { -1 };
if (!alive player || {!([player] call comspec_atak_native_fnc_hasDevice)}) exitWith {
    if (_manual) then { ["WARNING", "Détecteur de drones : aucun appareil", 3, 20] call comspec_atak_native_fnc_notify; };
    -1
};
if ((([] call comspec_atak_native_fnc_deviceHealth) get "state") in ["OFF", "BROKEN"]) exitWith {
    if (_manual) then { ["WARNING", "Détecteur de drones : téléphone éteint ou hors d'usage", 3, 20] call comspec_atak_native_fnc_notify; };
    -1
};
uiNamespace setVariable ["COMSPEC_ATAK_DroneScanAt", diag_tickTime];

private _veh = vehicle player;
private _eye = eyePos player;
private _from = getPosATL player;
_from = [_from select 0, _from select 1, 0];
private _mySide = side group player;
private _myGroup = units group player;
private _sensor = player getVariable ["COMSPEC_ATAK_DroneSensor", missionNamespace getVariable ["comspec_atak_native_drone_sensor", true]];
private _gain = [-6, 0] select _sensor;
// Brouillage subi : le plancher de bruit monte, les signaux faibles disparaissent.
private _jam = ([] call comspec_atak_native_fnc_ewEffects) param [3, 0];
private _floor = -90 + 25 * (_jam max 0);
// Pertes côté récepteur : véhicule, toit au-dessus de la tête.
private _rxLoss = 0;
if (_veh isNotEqualTo player) then { _rxLoss = _rxLoss + 6; };
if (_veh isEqualTo player && {(count (lineIntersectsSurfaces [_eye, _eye vectorAdd [0, 0, 30], player, objNull, true, 1, "GEOM", "NONE"])) > 0}) then { _rxLoss = _rxLoss + 5; };

private _byId = createHashMap;
{ _byId set [_x select 0, _x]; } forEach _list;
private _alerted = uiNamespace getVariable ["COMSPEC_ATAK_DroneAlerted", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_DroneAlerted", _alerted];
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _heard = 0;
private _newUnknown = [];
private _alerts = [];

{
    private _u = _x;
    if (!alive _u || {!(_u isKindOf "Air")} || {(count crew _u) isEqualTo 0}) then { continue; };
    // Pas de liaison active au sol moteur coupé.
    if (!(isEngineOn _u) && {isTouchingGround _u}) then { continue; };
    private _plane = _u isKindOf "Plane";
    private _bb = boundingBoxReal _u;
    private _small = !_plane && {((_bb select 0) distance (_bb select 1)) < 3.5};
    private _range = [4000, 2000] select _small;
    private _to = getPosASL _u;
    private _d = (_eye distance _to) max 1;
    if (_d > _range * 1.5) then { continue; };
    private _band = switch (true) do {
        case (_small): { "Quadri · vidéo 5,8 GHz / C2 2,4 GHz" };
        case (_plane): { "Voilure fixe · C2 2,4 GHz" };
        default { "Rotor lourd · C2 2,4 GHz" };
    };
    // Bilan de liaison : -90 dBm à la portée nominale, en vue directe.
    private _loss = 0;
    if (terrainIntersectASL [_eye, _to]) then { _loss = _loss + 22; };
    private _obst = (lineIntersectsSurfaces [_eye, _to, _veh, _u, true, 4, "GEOM", "NONE"]) select { !isNull (_x select 2) };
    _loss = _loss + 7 * (count _obst);
    _loss = _loss + rain * ([1, 3] select _small);
    private _rx = -90 + 20 * log (_range / _d) - _loss - _rxLoss + _gain + (random [-2, 0, 2]);
    if (_rx < _floor) then { continue; };
    _heard = _heard + 1;
    private _bars = switch (true) do { case (_rx >= -68): { 4 }; case (_rx >= -77): { 3 }; case (_rx >= -84): { 2 }; default { 1 }; };
    // Relèvement : erreur selon la force du signal, plus les réflexions si des obstacles coupent la vue.
    private _err = ([25, 15, 9, 5] select (_bars - 1)) + ([0, 6] select ((count _obst) > 0 || {_loss >= 22}));
    private _brg = ((_from getDir _u) + (random [-_err, 0, _err]) + 360) mod 360;
    // Distance : estimée d'après la puissance reçue (le récepteur ignore les pertes), seulement si le signal est fort.
    private _rangeBand = "";
    if (_bars >= 3) then {
        private _est = (_range / (10 ^ ((_rx - _gain + 90) / 20))) * (random [0.75, 1, 1.35]);
        _rangeBand = switch (true) do { case (_est < 300): { "< 300 m" }; case (_est < 800): { "300-800 m" }; default { "> 800 m" }; };
    };
    // AMI : drone de mon camp piloté depuis mon groupe ; sinon on ne peut pas savoir.
    private _ops = (UAVControl _u) select { _x isEqualType objNull && {!isNull _x} };
    private _friend = (side group _u) isEqualTo _mySide && {(_ops findIf { _x in _myGroup }) >= 0};
    private _nid = _u call BIS_fnc_netId;
    private _old = _byId getOrDefault [_nid, []];
    private _first = [_now, _old param [6, _now]] select ((count _old) > 0);
    if ((count _old) isEqualTo 0 && {!_friend}) then { _newUnknown pushBack [_brg, _err, _band]; };
    _byId set [_nid, [_nid, _band, _bars, _brg, _err, _rangeBand, _first, _now, _friend, _from, round _rx, _range]];
    if (!_friend && {_rangeBand in ["< 300 m", "300-800 m"]} && {(_now - (_alerted getOrDefault [_nid, -1e9])) > 60}) then {
        _alerted set [_nid, _now];
        _alerts pushBack [_brg, _err, _rangeBand, _band];
    };
} forEach allUnitsUAV;

// Détections non réentendues : gardées 60 s (signal perdu), puis oubliées.
_list = (values _byId) select { (_now - (_x select 7)) <= 60 };
missionNamespace setVariable ["COMSPEC_ATAK_DroneDetect", _list];

if ((count _alerts) > 0) then {
    (_alerts select 0) params ["_brg", "_err", "_rb", "_band"];
    ["WARNING", format ["DRONE INCONNU PROCHE : %1° %2 (±%3°), %4 · %5%6", round _brg, [_brg] call _card, round _err, _rb, _band,
        ["", format [" (+%1 autre(s))", (count _alerts) - 1]] select ((count _alerts) > 1)], 8, 70] call comspec_atak_native_fnc_notify;
    [] call comspec_atak_native_fnc_vibrate;
} else {
    if ((count _newUnknown) > 0 && {!_manual}) then {
        (_newUnknown select 0) params ["_brg", "_err", "_band"];
        ["INFO", format ["Drone inconnu détecté : %1° %2 (±%3°) · %4", round _brg, [_brg] call _card, round _err, _band], 5, 40] call comspec_atak_native_fnc_notify;
    };
};
if (_manual) then {
    if (_heard > 0) then {
        ["SUCCESS", format ["Détecteur : %1 drone(s) entendu(s)", _heard], 4, 30] call comspec_atak_native_fnc_notify;
    } else {
        ["INFO", format ["Détecteur : aucune liaison drone%1", ["", " (brouillage : sensibilité réduite)"] select (_jam > 0.05)], 4, 30] call comspec_atak_native_fnc_notify;
    };
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "DRONEDETECT") then { ["DRONEDETECT"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
};
_heard
