/*
    Carte : couche guerre électronique (app EW). Appelé depuis fn_mapOnDraw. Params : [contrôle carte]
    - relèvements de goniométrie : trait depuis le point de relevé, cône d'erreur, bande de distance estimée en surbrillance ;
      ils pâlissent puis disparaissent au bout de 15 min ;
    - brouilleurs de mon camp : cercle violet du rayon brouillé ;
    - mon erreur GPS (brouillage, dégâts, ciel masqué, pas de fix) : cercle d'incertitude autour de la position affichée,
      gris sans fix (dernière position connue ou position par le réseau).
*/
params ["_map"];
if (isNull _map) exitWith {};
private _fill = "#(rgb,8,8,3)color(1,1,1,1)";
private _none = "#(argb,8,8,3)color(0,0,0,0)";
{
    _x params ["_from", "_brg", "_b0", "_b1", "_kind", "_t", "_err"];
    private _age = time - _t;
    if (_age > 900) then { continue; };
    private _a = 1 - (_age / 900) * 0.7;
    private _rgb = switch (_kind) do { case "BROUILLEUR": { [0.85, 0.25, 0.95] }; case "GONIO": { [0.9, 0.28, 0.23] }; default { [0.95, 0.67, 0.20] }; };
    private _c = _rgb + [_a];
    private _cf = _rgb + [_a * 0.35];
    _map drawLine [_from, _from getPos [_b1, _brg], _c];
    _map drawLine [_from, _from getPos [_b1, _brg - _err], _cf];
    _map drawLine [_from, _from getPos [_b1, _brg + _err], _cf];
    // Bande de distance : rectangle orienté sur le relèvement, largeur = cône d'erreur à mi-bande.
    private _midD = (_b0 + _b1) / 2;
    _map drawRectangle [_from getPos [_midD, _brg], (_midD * tan _err) max 25, (_b1 - _b0) / 2, _brg, _rgb + [_a * 0.22], _fill];
    _map drawIcon [_none, _c, _from getPos [_b1, _brg], 0, 0, 0, format ["%1 %2°", _kind call comspec_atak_native_fnc_tr, round _brg], 1, 0.024 * (uiNamespace getVariable ["COMSPEC_ATAK_MapTs", 1]), (COMSPEC_ATAK_FontSet select 1), "right"];
    _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", _c, _from, 10, 10, 0, "", 0];
} forEach (missionNamespace getVariable ["COMSPEC_ATAK_EwBearings", []]);
// Numéros tracés (fn_traceAction) : cercle d'incertitude orange, plein si le suivi est actif ; dernière position connue
// (éteint, brouillé, hors couverture) en gris, cercle qui grandit avec son âge ; positions précédentes reliées.
private _clock = [time, serverTime] select isMultiplayer;
private _grow = (missionNamespace getVariable ["comspec_atak_native_trace_grow", 1.5]) max 0;
{
    private _p = _x getOrDefault ["pos", []];
    if ((count _p) < 2) then { continue; };
    private _live = (_x getOrDefault ["status", ""]) in ["OK", "SOS", "PENDING"];
    private _rgb = [[0.6, 0.6, 0.6], [0.95, 0.67, 0.20]] select _live;
    private _age = (_clock - (_x getOrDefault ["at", _clock])) max 0;
    private _r = (_x getOrDefault ["rad", 100]) max 10;
    if (!_live) then { _r = ["aged", [_r, _age, _grow, 5000]] call comspec_atak_native_fnc_traceCalc; };
    private _h = _x getOrDefault ["hist", []];
    for "_k" from 1 to ((count _h) - 1) do {
        _map drawLine [(_h select (_k - 1)) select 0, (_h select _k) select 0, _rgb + [0.45]];
        _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", _rgb + [0.5], (_h select (_k - 1)) select 0, 7, 7, 0, "", 0];
    };
    _map drawEllipse [_p, _r, _r, 0, _rgb + [0.95], ""];
    _map drawEllipse [_p, _r, _r, 0, _rgb + [[0.08, 0.18] select (_x getOrDefault ["track", false])], _fill];
    private _lbl = [format ["Tracé : %1 · dernière position, il y a %2 min", _x get "q", floor (_age / 60)], format ["Tracé : %1 · %2", _x get "q", _x getOrDefault ["hour", ""]]] select _live;
    _map drawIcon ["\A3\ui_f\data\map\markers\military\unknown_CA.paa", _rgb + [1], _p, 14, 14, 0, _lbl call comspec_atak_native_fnc_tr, 1, 0.026 * (uiNamespace getVariable ["COMSPEC_ATAK_MapTs", 1]), (COMSPEC_ATAK_FontSet select 1), "right"];
} forEach (missionNamespace getVariable ["COMSPEC_ATAK_GeoTracks", []]);
private _now = [time, serverTime] select isMultiplayer;
private _side = str side group player;
{
    _x params [["_pos", [0, 0, 0]], ["_rad", 500], ["_uid", ""], ["_s", ""], ["_until", 1e9]];
    if (_s isEqualTo _side && {_until >= _now}) then {
        // Objet supprimé (Zeus, nettoyage) : rien dessiné (getPosATL objNull = origine de la carte).
        if (_pos isEqualType objNull) then { if (isNull _pos) then { continue; }; _pos = getPosATL _pos; };
        _map drawEllipse [[_pos select 0, _pos select 1, 0], _rad, _rad, 0, [0.85, 0.25, 0.95, 0.9], ""];
        _map drawEllipse [[_pos select 0, _pos select 1, 0], _rad, _rad, 0, [0.85, 0.25, 0.95, 0.08], _fill];
        _map drawIcon [_none, [0.85, 0.25, 0.95, 1], [_pos select 0, _pos select 1, 0], 0, 0, 0, ("BROUILLEUR") call comspec_atak_native_fnc_tr, 2, 0.024 * (uiNamespace getVariable ["COMSPEC_ATAK_MapTs", 1]), (COMSPEC_ATAK_FontSet select 1), "center"];
    };
} forEach (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]);
([] call comspec_atak_native_fnc_ewEffects) params ["", "", "", "", "_gpsErr", "_off", "_gpsSt"];
if (_gpsErr > 0 && {profileNamespace getVariable ["COMSPEC_ATAK_AccRings", false]}) then {
    // Même point que mon icône (fn_mapOnDraw, position lissée de l'image) : le cercle reste centré sur le point affiché.
    private _p = uiNamespace getVariable ["COMSPEC_ATAK_SelfGpsPos", (getPosATL player) vectorAdd _off];
    // Erreur de quelques mètres : pas de cercle (plus petit que l'icône).
    if (_gpsErr >= 8 || {_gpsSt isNotEqualTo "FIX"}) then {
        _map drawEllipse [[_p select 0, _p select 1, 0], _gpsErr, _gpsErr, 0, [[0.95, 0.67, 0.20, 0.8], [0.62, 0.66, 0.64, 0.8]] select (_gpsSt isNotEqualTo "FIX"), ""];
    };
};
