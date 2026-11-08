/*
    GPS routier : calcule l'itinéraire par la route entre ma position et une destination (moteur fn_roadPath : A* sur le
    réseau routier d'Arma, pistes comprises, réseau gardé pour la session). Lancé en spawn : quelques secondes au plus
    sur une grande carte, sans geler le jeu.
    Params : [destination ATL, libellé, silencieux (recalcul)]
    Résultat dans missionNamespace COMSPEC_ATAK_Route (HashMap) : pts, cum (distances cumulées), turns, dest, label, total.
*/
params [["_dest", [0, 0, 0]], ["_label", "Destination"], ["_quiet", false]];
// Calcul déjà en cours (recalcul hors route silencieux compris) : la dernière demande du joueur est gardée et lancée à la
// fin du calcul en cours (avant : perdue sans rien dire, l'ancien itinéraire restait).
if (missionNamespace getVariable ["COMSPEC_ATAK_RouteBusy", false]) exitWith {
    if !(_quiet) then { missionNamespace setVariable ["COMSPEC_ATAK_RoutePending", [_dest, _label]]; };
    false
};
missionNamespace setVariable ["COMSPEC_ATAK_RouteBusy", true];
// Jeton de génération : « Arrêter le guidage » (fn_gpsAction) l'incrémente, le résultat d'un calcul lancé avant est jeté.
private _gen = missionNamespace getVariable ["COMSPEC_ATAK_RouteGen", 0];
private _from = getPosATL vehicle player;
private _pts = [];
if ((_from distance2D _dest) < 150) then {
    // Destination toute proche : ligne droite.
    _pts = [_from, _dest];
} else {
    // Moteur routier partagé avec le tracé ROUTES de la carte (fn_roadPath) : plus court chemin, pistes un peu pénalisées
    // (on préfère les routes), accroche jusqu'à 900 m, ma position et la destination gardées en bouts de l'itinéraire.
    // Réseau coupé : le morceau manquant passe en ligne droite au lieu de tout l'itinéraire.
    ([[_from, _dest], createHashMapFromArray [["mode", "SHORT"], ["bias", createHashMapFromArray [["TRACK", 1.35], ["TRAIL", 1.35]]],
        ["snap", 250], ["snapFar", 900], ["keepEnds", true], ["raw", true], ["maxNodes", 60000]]] call comspec_atak_native_fnc_roadPath) params ["_rp", "_st", "_inf"];
    _pts = _rp;
    if (!_quiet && {_inf getOrDefault ["failed", false]} && {(missionNamespace getVariable ["COMSPEC_ATAK_RouteGen", 0]) isEqualTo _gen}) then {
        if (_st isEqualTo "DIRECT") then { ["WARNING", "Pas d'itinéraire routier trouvé : cap direct affiché", 4, 30] call comspec_atak_native_fnc_notify; } else {
            ["WARNING", "Itinéraire routier incomplet : un tronçon en ligne droite", 4, 30] call comspec_atak_native_fnc_notify;
        };
    };
};
// Simplifie (points trop proches ou alignés) et calcule les virages.
private _simple = [_pts select 0];
for "_i" from 1 to ((count _pts) - 2) do {
    private _a = _simple select ((count _simple) - 1); private _b = _pts select _i; private _c = _pts select (_i + 1);
    private _turn = abs ((((_b getDir _c) - (_a getDir _b)) + 540) mod 360 - 180);
    if ((_a distance2D _b) > 12 && {_turn > 4 || {(_a distance2D _b) > 250}}) then { _simple pushBack _b; };
};
_simple pushBack (_pts select ((count _pts) - 1));
private _cum = [0];
for "_i" from 1 to ((count _simple) - 1) do { _cum pushBack ((_cum select (_i - 1)) + ((_simple select (_i - 1)) distance2D (_simple select _i))); };
private _turns = [];
for "_i" from 1 to ((count _simple) - 2) do {
    private _t = ((((_simple select _i) getDir (_simple select (_i + 1))) - ((_simple select (_i - 1)) getDir (_simple select _i))) + 540) mod 360 - 180;
    if ((abs _t) > 28) then { _turns pushBack [_i, _t]; };
};
// Guidage arrêté pendant le calcul : rien n'est écrit ni annoncé (la demande en attente, posée après l'arrêt, part quand même).
private _live = (missionNamespace getVariable ["COMSPEC_ATAK_RouteGen", 0]) isEqualTo _gen;
if (_live) then {
    missionNamespace setVariable ["COMSPEC_ATAK_Route", createHashMapFromArray [
        ["pts", _simple], ["cum", _cum], ["turns", _turns], ["dest", _dest], ["label", _label], ["total", _cum select ((count _cum) - 1)], ["idx", 0], ["off", 0], ["at", time]
    ]];
};
missionNamespace setVariable ["COMSPEC_ATAK_RouteBusy", false];
if (!_quiet && {_live}) then {
    private _t = _cum select ((count _cum) - 1);
    ["SUCCESS", format ["Itinéraire vers %1 : %2", _label, [format ["%1 m", round _t], format ["%1 km", (_t / 1000) toFixed 1]] select (_t >= 1000)], 4, 30] call comspec_atak_native_fnc_notify;
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    _s set ["mapFollow", true];
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MAP") then { ["MAP"] call comspec_atak_native_fnc_pageRender; }; }] call comspec_atak_native_fnc_nextFrame;
};
private _pending = missionNamespace getVariable ["COMSPEC_ATAK_RoutePending", []];
if ((count _pending) isEqualTo 2) then {
    missionNamespace setVariable ["COMSPEC_ATAK_RoutePending", nil];
    [_pending select 0, _pending select 1] spawn comspec_atak_native_fnc_routeCompute;
};
true
