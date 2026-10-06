/*
    Carte : drone appairé (icône et nom choisis par le pilote, position, mode, ligne vers la cible d'une frappe), sa tâche en cours (point, orbite,
    zone de recherche, route numérotée), le tracé en cours de l'outil DRONE de la carte et les pistes capteur
    (carrés rouges : ennemis, jaunes : inconnus, bleus : amis ; estompés quand la piste n'est plus vue).
    Appelé depuis fn_mapOnDraw. Params : [contrôle carte]
*/
params ["_map"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _sq = "#(argb,8,8,3)color(1,1,1,1)";

// Pistes capteur : dernière position vue (elles restent après le vol, jusqu'à « Effacer les pistes »).
{
    _y params ["_o", "_name", "_pos", "_seen", "_clock", "_tag", "", "_num", "_state"];
    // Les cibles vues par le drone restent dans l'app Drone : la carte n'affiche aucune IA ennemie ou inconnue.
    if (_tag isNotEqualTo "AMI") then { continue };
    private _c = +((createHashMapFromArray [["ENNEMI", [0.92, 0.26, 0.21, 1]], ["INCONNU", [0.98, 0.78, 0.15, 1]], ["AMI", [0.3, 0.64, 1, 1]]]) getOrDefault [_tag, [1, 1, 1, 1]]);
    if ((time - _seen) > 60 || {_state isNotEqualTo ""}) then { _c set [3, 0.5]; };
    _map drawIcon [_sq, [0, 0, 0, _c select 3], _pos, 13, 13, 0, "", 0];
    _map drawIcon [_sq, _c, _pos, 10, 10, 0, format [" P%1 %2%3", [_num, 2] call CBA_fnc_formatNumber, _name, ["", " (détruit)"] select (_state isNotEqualTo "")], 1, 0.028, "RobotoCondensed", "right"];
} forEach (missionNamespace getVariable ["COMSPEC_ATAK_DroneTracks", createHashMap]);

// Tracé en cours de l'outil DRONE (pas encore envoyé) : pointillé blanc numéroté.
if ((_s getOrDefault ["mapMode", "SELECT"]) isEqualTo "DRONE") then {
    private _pts = _s getOrDefault ["droneMapPts", []];
    private _w = [1, 1, 1, 0.9];
    {
        if (_forEachIndex > 0) then { _map drawLine [_pts select (_forEachIndex - 1), _x, _w]; };
        _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", _w, _x, 16, 16, 0, str (_forEachIndex + 1), 1, 0.03, "RobotoCondensedBold", "right"];
    } forEach _pts;
    // Zone : premier clic posé, cercle de prévisualisation au curseur (rayon ou rectangle).
    if ((_s getOrDefault ["droneMapSub", "GOTO"]) isEqualTo "ZONE" && {(count _pts) isEqualTo 1}) then {
        private _a = _pts select 0;
        private _cur = _map ctrlMapScreenToWorld getMousePosition;
        if ((_s getOrDefault ["droneZoneKind", "LOITER"]) isEqualTo "HUNT") then {
            private _cx = ((_a select 0) + (_cur select 0)) / 2;
            private _cy = ((_a select 1) + (_cur select 1)) / 2;
            _map drawRectangle [[_cx, _cy, 0], abs ((_cur select 0) - _cx), abs ((_cur select 1) - _cy), 0, [0.92, 0.26, 0.21, 0.8], ""];
        } else {
            private _r = ((_a distance2D _cur) max 30) min 800;
            _map drawEllipse [_a, _r, _r, 0, [1, 1, 1, 0.8], ""];
            _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)", [1, 1, 1, 1], _cur, 1, 1, 0, format ["%1 m", round _r], 1, 0.03, "RobotoCondensedBold", "right"];
        };
    };
};

private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
if (isNull _d || {!alive _d}) exitWith {};
// Sans liaison, la carte garde la dernière position reçue.
private _linked = missionNamespace getVariable ["COMSPEC_ATAK_DroneLinked", true];
private _pos = if (_linked) then { getPosASL _d } else { missionNamespace getVariable ["COMSPEC_ATAK_DroneLastPos", getPosASL _d] };
if (_linked) then { missionNamespace setVariable ["COMSPEC_ATAK_DroneLastPos", _pos]; };
private _m = _d getVariable ["COMSPEC_DroneMode", "HOVER"];
private _c = [[0.2, 0.9, 0.95, 1], [0.95, 0.3, 0.2, 1]] select (_m isEqualTo "STRIKE");
private _tc = [0.2, 0.9, 0.95, 0.75];
if (_m isEqualTo "STRIKE") then {
    private _t = _d getVariable ["COMSPEC_DroneTgt", []];
    if ((count _t) >= 2) then {
        _map drawLine [_pos, _t, _c];
        _map drawIcon ["\A3\ui_f\data\map\markers\military\destroy_CA.paa", _c, _t, 22, 22, 0, "", 0];
    };
};
// Tâche en cours : point, orbite, observation, zone de recherche.
(_d getVariable ["COMSPEC_DroneTask", []]) params [["_k", ""], ["_tp", []], ["_tr", 0], ["_area", []]];
if ((count _tp) >= 2) then {
    switch (_k) do {
        case "GOTO": {
            _map drawLine [_pos, _tp, _tc];
            _map drawIcon ["\A3\ui_f\data\map\markers\military\objective_CA.paa", _tc, _tp, 20, 20, 0, " ALLER", 1, 0.028, "RobotoCondensedBold", "right"];
        };
        case "LOITER";
        case "OBSERVE": {
            _map drawEllipse [_tp, _tr, _tr, 0, _tc, ""];
            _map drawIcon ["\A3\ui_f\data\map\markers\military\circle_CA.paa", _tc, _tp, 14, 14, 0, [" ORBITE", " OBSERVER"] select (_k isEqualTo "OBSERVE"), 1, 0.028, "RobotoCondensedBold", "right"];
            if (_k isEqualTo "OBSERVE") then { _map drawLine [_pos, _tp, [_tc select 0, _tc select 1, _tc select 2, 0.4]]; };
        };
        case "HUNT": {
            if ((count _area) >= 3) then { _map drawRectangle [_area select 0, _area select 1, _area select 2, 0, [0.92, 0.26, 0.21, 0.8], ""]; } else { _map drawEllipse [_tp, _tr, _tr, 0, [0.92, 0.26, 0.21, 0.8], ""]; };
            _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)", [0.92, 0.26, 0.21, 1], _tp, 1, 1, 0, "RECHERCHE", 1, 0.028, "RobotoCondensedBold", "center"];
        };
        case "ESCORT": {
            private _f = _d getVariable ["COMSPEC_DroneFollow", objNull];
            if (!isNull _f) then { _map drawLine [_pos, getPosASL _f, _tc]; };
        };
    };
};
// Route : étapes numérotées, étape en cours en vert.
(_d getVariable ["COMSPEC_DroneRoute", []]) params [["_rp", []], ["_rm", "ONCE"], ["_ri", 0]];
{
    if (_forEachIndex > 0) then { _map drawLine [_rp select (_forEachIndex - 1), _x, _tc]; };
    _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", [_tc, [0.36, 0.85, 0.42, 1]] select (_forEachIndex isEqualTo _ri), _x, 16, 16, 0, format [" WP%1", _forEachIndex + 1], 1, 0.028, "RobotoCondensedBold", "right"];
} forEach _rp;
if (_rm isEqualTo "LOOP" && {(count _rp) > 2}) then { _map drawLine [_rp select ((count _rp) - 1), _rp select 0, _tc]; };
if ((count _rp) > 0 && {_ri < (count _rp)}) then { _map drawLine [_pos, _rp select _ri, [0.36, 0.85, 0.42, 0.6]]; };

// Drone : icône et nom choisis par le pilote (variables publiques COMSPEC_DroneIcon / COMSPEC_DroneName), nez vers son cap.
if (_m isEqualTo "STANDBY") then { _c = [0.56, 0.7, 0.79, 1]; };
_map drawIcon [[_d, [], "ICON"] call comspec_atak_native_fnc_droneOsd, [_c, [0.6, 0.6, 0.6, 0.8]] select !_linked, _pos, 26, 26, getDir _d,
    format [" %1 %2 m%3%4%5", [_d, [], "NAME"] call comspec_atak_native_fnc_droneOsd, round ((getPosATL _d) select 2), ["", " CQB"] select (_d getVariable ["COMSPEC_DroneCqb", false]),
        ["", " VEILLE"] select (_m isEqualTo "STANDBY"), ["", " (hors liaison)"] select !_linked], 1, 0.03, "RobotoCondensedBold", "right"];
