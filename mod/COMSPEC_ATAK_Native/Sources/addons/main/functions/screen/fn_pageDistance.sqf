/*
    App Distances : pas, kilomètres à pied, en véhicule terrestre, en bateau, en vol et en saut (HALO, sous voile),
    temps à pied et heures de pilote par appareil pendant cette mission.
    Compté par le serveur (fn_distanceServer, reçu toutes les 15 s dans COMSPEC_ATAK_DistMe), cumul de toutes les
    missions sur la fiche du membre (site Athena). Params (module, fn_pageRender) : [page, [x, y, largeur, hauteur]]
*/
params [["_page", "DISTANCE"], ["_rect", [0, 0, 1, 1]]];
disableSerialization;
(player getVariable ["COMSPEC_ATAK_DistMe", [[], []]]) params [["_pairs", []], ["_pilot", []]];
private _c = createHashMapFromArray _pairs;
private _get = { params ["_k"]; _c getOrDefault [_k, 0] };
private _km = {
    params ["_m"];
    if (_m < 1000) then { format ["%1 m", round _m] } else { format ["%1 km", (_m / 1000) toFixed 1] }
};
private _dur = {
    params ["_sec"];
    private _m = floor (_sec / 60);
    switch (true) do { case (_sec < 60): { format ["%1 s", round _sec] }; case (_m < 60): { format ["%1 min", _m] }; default { format ["%1 h %2", floor (_m / 60), [str (_m mod 60), "0" + str (_m mod 60)] select ((_m mod 60) < 10)] }; }
};
private _total = 0;
{ _total = _total + ([_x] call _get); } forEach ["foot_m", "land_m", "sea_m", "air_m", "halo_m"];
private _rows = [["hero", "\z\comspec_atak_native\addons\main\data\app_distance.paa", format ["<t size='1.6' font='COMSPEC_BarlowBold'>%1</t><br/><t color='#8a9a93'>parcourus pendant cette mission</t><br/><t size='0.85'>%2 pas</t>", [_total] call _km, round (["steps"] call _get)]]];
if !(missionNamespace getVariable ["comspec_atak_native_distance", true]) then {
    _rows pushBack ["text", "<t color='#f2ab33'>Le serveur a coupé le suivi des distances.</t>"];
};
if (_pairs isEqualTo [] && {!isServer} && {!(missionNamespace getVariable ["COMSPEC_ATAK_PlanSrvOn", false])}) then {
    _rows pushBack ["text", "<t color='#f2ab33'>Le serveur n'a pas le mod ATAK natif : les distances ne sont pas comptées.</t>"];
};
private _foot = ["foot_m"] call _get;
private _footS = ["foot_s"] call _get;
_rows append [
    ["section", "À pied", "Pas estimés d'après l'allure (rien à la nage ni en rampant)"],
    ["info", "Distance", [_foot] call _km],
    ["info", "Pas", str round (["steps"] call _get)],
    ["info", "Temps de marche", [_footS] call _dur],
    ["info", "Allure moyenne", if (_footS < 30) then { "—" } else { format ["%1 km/h", (3.6 * _foot / _footS) toFixed 1] }],
    ["section", "Véhicules", ""],
    ["info", "Véhicule terrestre", format ["%1 · %2", [["land_m"] call _get] call _km, [["land_s"] call _get] call _dur]],
    ["info", "Bateau", format ["%1 · %2", [["sea_m"] call _get] call _km, [["sea_s"] call _get] call _dur]],
    ["info", "Vol", format ["%1 · %2", [["air_m"] call _get] call _km, [["air_s"] call _get] call _dur]],
    ["info", "Saut (HALO, sous voile)", format ["%1 · %2 saut(s)", [["halo_m"] call _get] call _km, round (["halo_jumps"] call _get)]]
];
_rows pushBack ["section", "Heures de pilote", "Aux commandes, moteur allumé, par appareil"];
private _pl = _pilot apply { _x params ["_cls", "_label", "_pk", "_ps", "_pm"]; [_ps, [_label, _cls] select (_label isEqualTo ""), _pm] };
_pl sort false;
{ _x params ["_ps", "_label", "_pm"]; _rows pushBack ["info", _label, format ["%1 · %2", [_ps] call _dur, [_pm] call _km]]; } forEach _pl;
if (_pl isEqualTo []) then { _rows pushBack ["text", "<t color='#8a9a93'>Pas encore d'heures de pilote cette mission.</t>"]; };
(missionNamespace getVariable ["COMSPEC_ATAK_DistSrv", [true, -1]]) params ["_ok", "_at"];
_rows append [
    ["section", "Athena", "Envoyé par le serveur toutes les 10 min. Le cumul de toutes vos missions et la moyenne par mission sont sur votre fiche du site."],
    ["info", "Dernier envoi", if (_at < 0) then { "pas encore" } else { format [["il y a %1 · en échec, renvoyé au prochain envoi", "il y a %1"] select _ok, [serverTime - _at] call _dur] }],
    ["buttons", [["ACTUALISER", { [{ ['DISTANCE'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }, true]]]
];
[_rows, _rect] call comspec_atak_native_fnc_formRender;
true
