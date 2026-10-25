/*
    Dessin libre de la carte (fn_mapOnDraw) : traits joueurs épais / fléchés, et tracé en cours.
    Arma dessine les polylignes sur un pixel ; les traits posés avec une épaisseur ou une flèche (variable publique
    COMSPEC_ATAK_SK_<nom> = [épaisseur, flèche, fermé]) sont repassés ici, à leur couleur, identiques pour tous.
    La liste des traits stylés suit les marqueurs changés (pas de parcours de tous les marqueurs à chaque image).
    Params : [carte, position du curseur (monde)]
*/
params ["_map", ["_cursor", []]];
private _now = diag_tickTime;
// Traits (POLYLINE) connus : nom -> style ([] tant que sa variable de style n'est pas arrivée). Tenus à jour par les
// marqueurs changés (EH, carnet COMSPEC_ATAK_MkDirtySk) ; tous les marqueurs ne sont parcourus qu'au premier dessin et
// toutes les 30 s. Avant : parcours de tous les marqueurs toutes les 2 s, à chaque fois une petite saccade.
private _known = uiNamespace getVariable ["COMSPEC_ATAK_SkKnown", createHashMap];
private _dirty = missionNamespace getVariable ["COMSPEC_ATAK_MkDirtySk", createHashMap];
private _styleOf = { missionNamespace getVariable [format ["COMSPEC_ATAK_SK_%1", _this regexReplace ["[^A-Za-z0-9]", "_"]], []] };
private _list = uiNamespace getVariable ["COMSPEC_ATAK_SkList", []];
private _rebuild = false;
if (_now > (uiNamespace getVariable ["COMSPEC_ATAK_SkFull", -1]) || {"" in _dirty}) then {
    uiNamespace setVariable ["COMSPEC_ATAK_SkFull", _now + 30];
    missionNamespace setVariable ["COMSPEC_ATAK_MkDirtySk", createHashMap];
    _known = createHashMap;
    { if ((markerShape _x) isEqualTo "POLYLINE") then { _known set [_x, _x call _styleOf]; }; } forEach allMapMarkers;
    uiNamespace setVariable ["COMSPEC_ATAK_SkKnown", _known];
    _rebuild = true;
} else {
    if ((count _dirty) > 0) then {
        missionNamespace setVariable ["COMSPEC_ATAK_MkDirtySk", createHashMap];
        { if ((markerShape _x) isEqualTo "POLYLINE") then { _known set [_x, _x call _styleOf]; } else { _known deleteAt _x; }; } forEach (keys _dirty);
        _rebuild = true;
    };
    // Style publié après la création du trait (variable publique arrivée plus tard) : relu toutes les 2 s, traits seulement.
    if (_now > (uiNamespace getVariable ["COMSPEC_ATAK_SkScan", -1])) then {
        uiNamespace setVariable ["COMSPEC_ATAK_SkScan", _now + 2];
        { private _st = _x call _styleOf; if (_st isNotEqualTo _y) then { _known set [_x, _st]; _rebuild = true; }; } forEach _known;
    };
};
if (_rebuild) then {
    _list = [];
    { if ((count _y) >= 2) then { _list pushBack [_x, _y]; }; } forEach _known;
    uiNamespace setVariable ["COMSPEC_ATAK_SkList", _list];
};
private _colCache = uiNamespace getVariable "COMSPEC_ATAK_SkColors";
if (isNil "_colCache") then { _colCache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_SkColors", _colCache]; };
private _rgbaOf = {
    params ["_cls"];
    private _c = _colCache getOrDefault [_cls, []];
    if ((count _c) isEqualTo 0) then {
        _c = (getArray (configFile >> "CfgMarkerColors" >> _cls >> "color")) apply { if (_x isEqualType "") then { call compile _x } else { _x } };
        if ((count _c) < 4) then { _c = [0.9, 0.1, 0.1, 1]; };
        _colCache set [_cls, _c];
    };
    +_c
};
private _mpx = ["mpx", _map] call comspec_atak_native_fnc_planGfx;
{
    _x params ["_m", "_st"];
    if ((markerShape _m) isEqualTo "" || {(markerAlpha _m) <= 0}) then { continue };
    _st params [["_w", 2], ["_arrow", false]];
    private _flat = markerPolyline _m;
    private _pts = [];
    for "_i" from 0 to ((count _flat) - 2) step 2 do { _pts pushBack [_flat select _i, _flat select (_i + 1), 0]; };
    private _c = [markerColor _m] call _rgbaOf;
    _c set [3, (_c select 3) * (markerAlpha _m)];
    ["line", _map, [_pts, _c, (_w max 1 min 3) * 1.3, false, _arrow, false, _mpx]] call comspec_atak_native_fnc_planGfx;
} forEach _list;

// Tracé en cours (trait A-B suivant le curseur, ou dessin à main levée) au style choisi.
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _stroke = +(_state getOrDefault ["drawStroke", []]);
if ((count _stroke) > 0) then {
    if ((_state getOrDefault ["mapMode", ""]) isEqualTo "LINE" && {(count _cursor) >= 2}) then { _stroke pushBack [_cursor select 0, _cursor select 1, 0]; };
    private _c = [profileNamespace getVariable ["COMSPEC_ATAK_DrawColor", "ColorRed"]] call _rgbaOf;
    private _closed = (profileNamespace getVariable ["COMSPEC_ATAK_DrawClosed", false]) && {(_state getOrDefault ["mapMode", ""]) isEqualTo "DRAW"};
    if (_closed && {(count _stroke) > 2}) then {
        // Fermeture montrée en pointillé fin jusqu'au point de départ.
        _map drawLine [_stroke select -1, _stroke select 0, [_c select 0, _c select 1, _c select 2, 0.45]];
    };
    ["line", _map, [_stroke apply { [_x select 0, _x select 1, 0] }, _c, ((profileNamespace getVariable ["COMSPEC_ATAK_DrawWidth", 2]) max 1 min 3) * 1.3, false,
        (profileNamespace getVariable ["COMSPEC_ATAK_DrawArrow", false]) && !_closed, false, _mpx]] call comspec_atak_native_fnc_planGfx;
};
