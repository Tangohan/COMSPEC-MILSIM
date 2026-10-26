/*
    Cartouches des unités et des contacts sur la carte, façon ATAK : boîte sombre décalée du symbole, reliée
    par un trait fin.
      ligne 1 : indicatif en capitales, police à chasse fixe grasse (« STAR 4-1 », « ENI INFANTERIE 1 ») ;
      ligne 2 : plus petite, en gris : distance et azimut depuis moi (« 48 m · 234° ») ou, pour un contact ENI,
                heure de pose et auteur (« 150179 · signalé TA1 », boîte bordée de rouge).
    Tout est dessiné par la carte (drawRectangle, drawLine, drawIcon) : aucun contrôle créé ni déplacé à chaque image.
    Désencombrement : cartouches hors de la carte ignorées, 4 positions essayées autour du symbole puis abandon
    si toutes chevauchent un cartouche déjà posé, nombre plafonné, ligne 2 seulement en zoom rapproché.
    Économie : distance et azimut recalculés au plus deux fois par seconde par unité (cache COMSPEC_ATAK_LblCache),
    liste des contacts ENI refaite une fois par seconde à partir du cache des marqueurs (fn_localDataRefresh).
    Params : [carte, candidats [[priorité, id, position, indicatif, hostile, alpha]...], taille des textes,
              échelle de la carte, style ("FULL" complet | "CALLSIGN" indicatif seul)]
*/
params ["_map", "_cands", "_ts", "_scale", ["_style", "FULL"]];
private _now = diag_tickTime;
private _two = _style isEqualTo "FULL" && {_scale < 0.1};
private _cache = uiNamespace getVariable "COMSPEC_ATAK_LblCache";
if (isNil "_cache" || {(count _cache) > 400}) then { _cache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_LblCache", _cache]; };

// Contacts ENI : marqueurs hostiles (classe o_*) ou titrés « ENI … » (palette du téléphone), liste refaite chaque seconde.
private _eni = uiNamespace getVariable ["COMSPEC_ATAK_EniList", [-1, []]];
if (_now > (_eni select 0)) then {
    private _meta = missionNamespace getVariable "COMSPEC_ATAK_MarkerMeta";
    if (isNil "_meta") then { _meta = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_MarkerMeta", _meta]; };
    private _list = [];
    {
        private _key = _x;
        private _m = _y;
        private _txt = [_m getOrDefault ["text", ""]] call comspec_atak_native_fnc_markerAutoName;
        if !(((toLower (_m getOrDefault ["type", ""])) select [0, 2]) isEqualTo "o_" || {(toUpper (_txt select [0, 4])) in ["ENI ", "ENY "]}) then { continue };
        if ((toUpper (_m getOrDefault ["shape", "ICON"])) isNotEqualTo "ICON") then { continue };
        private _pos = _m getOrDefault ["position", []];
        if ((count _pos) < 2) then { continue };
        // Heure et auteur : notés à la création (XEH_postInitClient, MarkerCreated) ; sinon (arrivé avant moi,
        // marqueur Athena) heure de première vue et auteur retrouvé une fois pour toutes.
        private _mt = _meta getOrDefault [_key, []];
        if ((count _mt) < 2) then {
            private _who = "";
            if ((_key select [0, 15]) isEqualTo "_USER_DEFINED #") then {
                private _oid = parseNumber ((((_key select [15]) splitString "/") param [0, ""]));
                private _u = allUsers findIf { ((getUserInfo _x) param [1, -1]) isEqualTo _oid };
                if (_u >= 0) then {
                    private _info = getUserInfo (allUsers select _u);
                    private _o = _info param [10, objNull];
                    _who = if (_o isEqualType objNull && {!isNull _o}) then { [_o] call comspec_atak_native_fnc_unitCallsign } else { _info param [3, ""] };
                };
            } else {
                private _src = _m getOrDefault ["source", ""];
                if (_src isNotEqualTo "") then { _who = [_src, "Athena"] select (_src isEqualTo "athena"); };
            };
            _mt = [(([dayTime, "HH:MM:SS"] call BIS_fnc_timeToString) splitString ":") joinString "", _who];
            _meta set [_key, _mt];
        };
        _mt params ["_hms", "_who"];
        private _title = toUpper ([_txt, ["ENY CONTACT", "ENI CONTACT"] select ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isEqualTo "fr")] select (_txt isEqualTo ""));
        _list pushBack [_key, [_pos select 0, _pos select 1, 0], _title, [_hms, (format ["%1 · signalé %2", _hms, toUpper _who]) call comspec_atak_native_fnc_tr] select (_who isNotEqualTo "")];
    } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["markers", createHashMap]);
    _eni = [_now + 1, _list];
    uiNamespace setVariable ["COMSPEC_ATAK_EniList", _eni];
};

// Géométrie de la carte : passage écran -> monde une fois par image.
(ctrlPosition _map) params ["_mx", "_my", "_mw", "_mh"];
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
private _o0 = _map ctrlMapScreenToWorld [_mx, _my];
private _mpx = (((_map ctrlMapScreenToWorld [_mx + 0.1, _my]) select 0) - (_o0 select 0)) / 0.1;
private _mpy = ((_o0 select 1) - ((_map ctrlMapScreenToWorld [_mx, _my + 0.1]) select 1)) / 0.1;
if (_mpx <= 0 || {_mpy <= 0}) exitWith {};
private _fs1 = 0.025 * _ts;
private _fs2 = _fs1 * 0.85;
private _cw1 = _fs1 * 0.58 / _ratio;
private _cw2 = _fs2 * 0.58 / _ratio;
private _padX = _fs1 * 0.45 / _ratio;
private _padY = _fs1 * 0.22;
private _ox = _fs1 * 1.1 / _ratio;
private _oy = _fs1 * 0.9;

// Candidats visibles, triés : sélection, contacts ENI, mon groupe, puis les autres, chacun du plus proche au plus loin.
// Distances et azimuts depuis ma position GPS affichée (fn_mapOnDraw), pas la vraie.
private _me = uiNamespace getVariable ["COMSPEC_ATAK_SelfGpsPos", getPosASL player];
private _vis = [];
{
    _x params ["_prio", "_id", "_pos", "_cs", ["_hostile", false], ["_a", 1]];
    private _sp = _map ctrlMapWorldToScreen _pos;
    if ((_sp select 0) < _mx || {(_sp select 0) > (_mx + _mw)} || {(_sp select 1) < _my} || {(_sp select 1) > (_my + _mh)}) then { continue };
    private _c = _cache getOrDefault [_id, []];
    if ((count _c) < 3 || {_now > (_c select 0)}) then {
        private _d = _me distance2D _pos;
        private _txt = format ["%1 · %2°", [format ["%1 m", round _d], format ["%1 km", ((_d / 1000) toFixed 1) splitString "." joinString ","]] select (_d >= 1000), (round (_me getDir _pos)) mod 360];
        _c = [_now + 0.5, _d, _txt];
        _cache set [_id, _c];
    };
    _vis pushBack [_prio * 1e6 + (_c select 1), _id, _pos, _sp, _cs, _c select 2, _hostile, _a];
} forEach _cands;
{
    _x params ["_id", "_pos", "_title", "_line2"];
    private _sp = _map ctrlMapWorldToScreen _pos;
    if ((_sp select 0) < _mx || {(_sp select 0) > (_mx + _mw)} || {(_sp select 1) < _my} || {(_sp select 1) > (_my + _mh)}) then { continue };
    private _c = _cache getOrDefault [_id, []];
    if ((count _c) < 2 || {_now > (_c select 0)}) then { _c = [_now + 0.5, (_me distance2D _pos) min 999999]; _cache set [_id, _c]; };
    _vis pushBack [1e6 + (_c select 1), _id, _pos, _sp, _title, _line2, true, 1];
} forEach (_eni select 1);
_vis sort true;

// Bandeau d'information de la carte compté comme déjà occupé (cartouche jamais dessous).
private _placed = [_map] call comspec_atak_native_fnc_mapHudRects;
private _max = ([20, 28] select (_style isEqualTo "CALLSIGN")) + (count _placed);
private _fill = "#(rgb,8,8,3)color(1,1,1,1)";
private _none = "#(argb,8,8,3)color(0,0,0,0)";
private _sel = ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["selectedEntity", createHashMap]) getOrDefault ["id", ""];
private _toW = { params ["_sx", "_sy"]; private _w = _map ctrlMapScreenToWorld [_sx, _sy]; [_w select 0, _w select 1, 0] };
{
    if ((count _placed) >= _max) then { break };
    _x params ["", "_id", "_pos", "_sp", "_l1", "_l2", "_hostile", "_a"];
    _sp params ["_sx", "_sy"];
    private _w = (((count _l1) * _cw1) max ([0, (count _l2) * _cw2] select _two)) + _padX * 2;
    private _h = _fs1 * 1.05 + ([0, _fs2 * 1.1] select _two) + _padY * 2;
    // Haut droite, haut gauche, bas droite, bas gauche : première place libre dans la carte.
    private _rect = [];
    {
        _x params ["_dx", "_dy"];
        private _x0 = [_sx - _ox - _w, _sx + _ox] select (_dx > 0);
        private _y0 = [_sy - _oy - _h, _sy + _oy] select (_dy > 0);
        private _r = [_x0, _y0, _x0 + _w, _y0 + _h];
        if (_x0 < _mx || {(_x0 + _w) > (_mx + _mw)} || {_y0 < _my} || {(_y0 + _h) > (_my + _mh)}) then { continue };
        if ((_placed findIf { (_r select 0) < (_x select 2) && {(_r select 2) > (_x select 0)} && {(_r select 1) < (_x select 3)} && {(_r select 3) > (_x select 1)} }) >= 0) then { continue };
        _rect = _r;
        break;
    } forEach [[1, -1], [-1, -1], [1, 1], [-1, 1]];
    if (_rect isEqualTo []) then { continue };
    _placed pushBack _rect;
    _rect params ["_x0", "_y0", "_x1", "_y1"];
    private _ctr = [(_x0 + _x1) / 2, (_y0 + _y1) / 2] call _toW;
    private _hw = (_w / 2) * _mpx;
    private _hh = (_h / 2) * _mpy;
    // Trait du symbole au coin le plus proche du cartouche.
    private _cx = [_x1, _x0] select (_x0 > _sx);
    private _cy = [_y1, _y0] select (_y0 > _sy);
    private _lc = [[0.85, 0.89, 0.92, 0.55 * _a], [0.95, 0.25, 0.21, 0.8]] select _hostile;
    _map drawLine [[_pos select 0, _pos select 1, 0], [_cx, _cy] call _toW, _lc];
    _map drawRectangle [_ctr, _hw, _hh, 0, [0.035, 0.045, 0.055, 0.8 * (_a max 0.6)], _fill];
    if (_hostile) then { _map drawRectangle [_ctr, _hw, _hh, 0, [0.95, 0.25, 0.21, 1], ""]; };
    if (_id isEqualTo _sel) then { _map drawRectangle [_ctr, _hw, _hh, 0, [0.36, 0.78, 0.42, 1], ""]; };
    private _ty = _y0 + _padY + _fs1 * 0.52;
    _map drawIcon [_none, [[1, 1, 1, _a], [1, 0.45, 0.40, 1]] select _hostile, [_x0 + _padX, _ty] call _toW, 0, 0, 0, _l1, 0, _fs1, "EtelkaMonospaceProBold", "right"];
    if (_two) then {
        _map drawIcon [_none, [0.82, 0.87, 0.85, _a max 0.8], [_x0 + _padX, _ty + _fs1 * 0.55 + _fs2 * 0.6] call _toW, 0, 0, 0, _l2, 0, _fs2, "EtelkaMonospacePro", "right"];
    };
} forEach _vis;
