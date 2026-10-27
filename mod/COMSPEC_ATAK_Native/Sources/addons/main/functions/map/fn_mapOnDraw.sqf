params ["_map"];
if (isNull _map) exitWith {};

private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap];
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
private _selected = (_state getOrDefault ["selectedEntity",createHashMap]) getOrDefault ["id",""];
private _labels = (["COMSPEC_ATAK_Labels", true, "native_map_labels"] call comspec_atak_native_fnc_pref) select 0;
private _scale = ctrlMapScale _map;
// Boussole fluide : aiguille et cadran suivent le cap à chaque image (fn_mapOverlayUpdate, boussole seulement).
[false, true] call comspec_atak_native_fnc_mapOverlayUpdate;
// Téléphone porté (ou « Suivre ma position ») : la carte suit le joueur à chaque image, sans à-coup.
// fn_mapCenter pose le premier centrage et son décalage (comspec_lastAim) ; ensuite on glisse du même écart que le joueur.
if (!(_state getOrDefault ["interactive", false]) || {_state getOrDefault ["mapFollow", false]}) then {
    private _fwLast = _map getVariable ["comspec_lastAim", []];
    if ((count _fwLast) isEqualTo 2 && {ctrlMapAnimDone _map} && {diag_tickTime - (_map getVariable ["comspec_centerAt", -1]) > 0.6}) then {
        (ctrlPosition _map) params ["_fwX", "_fwY", "_fwW", "_fwH"];
        private _fwVis = _map ctrlMapScreenToWorld [_fwX + _fwW / 2, _fwY + _fwH / 2];
        // Position GPS affichée (fn_gpsPos) : la carte suit mon point et ses anneaux, pas ma position exacte.
        private _fwPos = [] call comspec_atak_native_fnc_gpsPos;
        private _fwDx = (_fwPos select 0) - (_fwVis select 0);
        private _fwDy = (_fwPos select 1) - (_fwVis select 1);
        if ((abs _fwDx) + (abs _fwDy) > 0.25) then {
            private _fwAim = [(_fwLast select 0) + _fwDx, (_fwLast select 1) + _fwDy];
            _map setVariable ["comspec_lastAim", _fwAim];
            _map ctrlMapAnimAdd [0, _scale, _fwAim];
            ctrlMapAnimCommit _map;
        };
    };
};
// Réglages > Carte « Cartouches des unités » : COMPLET (indicatif + distance / azimut), INDICATIF SEUL ou AUCUN.
private _lblStyle = profileNamespace getVariable ["COMSPEC_ATAK_LabelStyle", "FULL"];
private _lblOn = _labels && {_lblStyle isNotEqualTo "NONE"};
// Symboles proportionnels à la taille de l'écran de l'ATAK (mini, porté, plein écran, portrait ou paysage) ;
// textes proportionnels aussi mais jamais sous une taille lisible.
private _kRaw = ((ctrlPosition _map) select 3) / (safeZoneH * 0.7);
private _k = (_kRaw max 0.35) min 1.35;
// Textes : plancher relevé de 0,8 à 0,95 (petite carte du téléphone porté : étiquettes illisibles sinon).
private _kt = (_kRaw max 0.95) min 1.25;
// Étiquettes : blanc cerné de noir (ombre 2), lisible sur tous les fonds de carte.
private _clear = "#(argb,8,8,3)color(0,0,0,0)";
// Réglages > Carte : taille des textes (indicatifs, grilles, libellés) et des symboles. Défaut 1.25 (plus grand qu'avant).
private _ts = ((profileNamespace getVariable ["COMSPEC_ATAK_MapTextScale", 1.25]) max 0.8) min 2.2;
private _is = ((profileNamespace getVariable ["COMSPEC_ATAK_MapIconScale", 1.25]) max 0.8) min 2.2;
uiNamespace setVariable ["COMSPEC_ATAK_MapTs", _ts];
uiNamespace setVariable ["COMSPEC_ATAK_MapIs", _is];
// Marqueurs de la mission (fn_mapGameMarkers) : symbole Arma (32 px pour l'OTAN) ramené à l'échelle de l'écran de l'ATAK,
// comme les unités ; gardé pour fn_mapMarkerText, qui décolle les noms du symbole.
private _mk = 0.62 * ((_kRaw max 0.45) min 1.2) * _is;
uiNamespace setVariable ["COMSPEC_ATAK_MkScale", _mk];
private _bgStyle = [] call comspec_atak_native_fnc_mapStyle;
// Cartouches façon ATAK (fn_mapLabels) en carte plein écran ; la petite carte (téléphone en HUD) garde l'indicatif simple.
private _boxOn = _lblOn && {_kRaw > 0.75} && {_scale < 0.25};
private _lblCands = [];
private _myGroup = group player;
// Couleurs choisies dans les réglages (alliés, moi) ; icône choisie par chaque joueur (variable publique).
private _palette = uiNamespace getVariable "COMSPEC_ATAK_MapPalette";
if (isNil "_palette") then {
    _palette = createHashMapFromArray [["BLUE",[0.28,0.70,1,1]],["CYAN",[0.20,0.90,0.95,1]],["GREEN",[0.36,0.85,0.42,1]],["WHITE",[0.95,0.95,0.95,1]],["YELLOW",[1,0.85,0.15,1]],["ORANGE",[1,0.55,0.15,1]],["PINK",[1,0.45,0.75,1]]];
    uiNamespace setVariable ["COMSPEC_ATAK_MapPalette", _palette];
};
// Couleurs et icônes de marqueurs (CfgMarkerColors / CfgMarkers) lues une fois par classe : la carte est redessinée
// à chaque image, relire la config et compiler les couleurs à chaque marqueur coûtait cher (téléphone en HUD).
// Rend une copie (l'appelant modifie l'alpha).
private _mkColorCache = uiNamespace getVariable "COMSPEC_ATAK_MkColorCache";
if (isNil "_mkColorCache") then { _mkColorCache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_MkColorCache", _mkColorCache]; };
private _mkRgba = {
    params ["_cls", "_def"];
    private _c = _mkColorCache getOrDefault [_cls, []];
    if (_c isEqualTo []) then {
        _c = (getArray (configFile >> "CfgMarkerColors" >> _cls >> "color")) apply { if (_x isEqualType "") then { call compile _x } else { _x } };
        if ((count _c) < 4) then { _c = []; };
        _mkColorCache set [_cls, [_c]];
    } else { _c = _c select 0; };
    if ((count _c) < 4) then { +_def } else { +_c }
};
private _mkIconCache = uiNamespace getVariable "COMSPEC_ATAK_MkIconCache";
if (isNil "_mkIconCache") then { _mkIconCache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_MkIconCache", _mkIconCache]; };
private _allyRgb = _palette getOrDefault [profileNamespace getVariable ["COMSPEC_ATAK_AllyColor","BLUE"],[0.28,0.70,1,1]];
private _selfRgb = _palette getOrDefault [profileNamespace getVariable ["COMSPEC_ATAK_SelfColor","CYAN"],[0.20,0.90,0.95,1]];

// Fond PHOTO (capture de la carte : mod, ou fond d'Athena téléchargé en jeu) : tuiles sous tous les symboles ; en
// SATELLITE, la capture du mod remplace la texture du terrain là où elle existe.
if (_bgStyle in ["PHOTO", "SAT"]) then { [_map, _bgStyle] call comspec_atak_native_fnc_photoMap; };
// Fond de carte (Réglages > Carte ou CALQUES) : voile sous tous les symboles (dessiné en premier).
private _veil = switch (_bgStyle) do {
    case "LIGHT": { [1, 1, 1, 0.38] };
    case "DARK": { [0.01, 0.012, 0.011, 0.68] };
    case "NIGHT": { [0.02, 0.03, 0.08, 0.58] };
    default { [] };
};
if ((count _veil) > 0) then {
    _map drawRectangle [[worldSize / 2, worldSize / 2, 0], worldSize * 1.5, worldSize * 1.5, 0, _veil, "#(rgb,8,8,3)color(1,1,1,1)"];
};
// Heatmap : altitudes du terrain en couleur (bleu bas, vert, jaune, brun, blanc haut). Aucune donnée ennemie.
// Cases visibles seulement, taille adaptée au zoom (environ 48 cases de large), hauteurs gardées en cache.
if (profileNamespace getVariable ["COMSPEC_ATAK_LayerHeat", false]) then {
    private _rng = missionNamespace getVariable ["COMSPEC_ATAK_HeightRange", []];
    if ((count _rng) < 2) then {
        private _lo = 1e9; private _hi = -1e9; private _st = worldSize / 48;
        for "_ix" from 0 to 47 do { for "_iy" from 0 to 47 do {
            private _h = getTerrainHeightASL [(_ix + 0.5) * _st, (_iy + 0.5) * _st];
            if (_h > 0) then { _lo = _lo min _h; _hi = _hi max _h; };
        }; };
        if (_hi < _lo) then { _lo = 0; _hi = 100; };
        _rng = [_lo, (_hi - _lo) max 20];
        missionNamespace setVariable ["COMSPEC_ATAK_HeightRange", _rng];
    };
    _rng params ["_hLo", "_hSpan"];
    (ctrlPosition _map) params ["_mx", "_my", "_mw", "_mh"];
    private _tl = _map ctrlMapScreenToWorld [_mx, _my];
    private _br = _map ctrlMapScreenToWorld [_mx + _mw, _my + _mh];
    private _wx0 = ((_tl select 0) min (_br select 0)) max 0;
    private _wx1 = ((_tl select 0) max (_br select 0)) min worldSize;
    private _wy0 = ((_tl select 1) min (_br select 1)) max 0;
    private _wy1 = ((_tl select 1) max (_br select 1)) min worldSize;
    private _cell = 50;
    { if (((_wx1 - _wx0) / _x) > 48) then { _cell = _x * 2; }; } forEach [50, 100, 200, 400, 800, 1600];
    private _tex = "#(rgb,8,8,3)color(1,1,1,1)";
    // Cases calculées une fois par vue (taille de case + cases visibles) puis seulement redessinées :
    // recalculer couleurs et clés de cache de 2 000 cases à chaque image coûtait cher en HUD.
    private _hSig = [_cell, floor (_wx0 / _cell), floor (_wx1 / _cell), floor (_wy0 / _cell), floor (_wy1 / _cell), _rng];
    private _hDraw = uiNamespace getVariable ["COMSPEC_ATAK_HeatDraw", [[], []]];
    if ((_hDraw select 0) isNotEqualTo _hSig) then {
        private _cache = uiNamespace getVariable ["COMSPEC_ATAK_HeightCache", createHashMap];
        if ((count _cache) > 20000) then { _cache = createHashMap; };
        uiNamespace setVariable ["COMSPEC_ATAK_HeightCache", _cache];
        private _stops = [[0, [0.15, 0.35, 0.85]], [0.25, [0.2, 0.7, 0.35]], [0.5, [0.9, 0.85, 0.25]], [0.75, [0.6, 0.38, 0.18]], [1, [0.97, 0.97, 0.97]]];
        private _half = _cell / 2;
        private _list = [];
        for "_gx" from ((floor (_wx0 / _cell)) * _cell) to _wx1 step _cell do {
            for "_gy" from ((floor (_wy0 / _cell)) * _cell) to _wy1 step _cell do {
                private _k = [_cell, _gx, _gy];
                private _h = _cache getOrDefault [_k, -1e9];
                if (_h < -1e8) then { _h = getTerrainHeightASL [_gx + _half, _gy + _half]; _cache set [_k, _h]; };
                if (_h <= 0) then { continue };
                private _t = (((_h - _hLo) / _hSpan) max 0) min 1;
                private _i = 1;
                while { _i < 4 && {_t > ((_stops select _i) select 0)} } do { _i = _i + 1; };
                (_stops select (_i - 1)) params ["_t0", "_c0"];
                (_stops select _i) params ["_t1", "_c1"];
                private _f = ((_t - _t0) / ((_t1 - _t0) max 0.001)) min 1;
                _list pushBack [[_gx + _half, _gy + _half, 0], _half, [
                    (_c0 select 0) + ((_c1 select 0) - (_c0 select 0)) * _f,
                    (_c0 select 1) + ((_c1 select 1) - (_c0 select 1)) * _f,
                    (_c0 select 2) + ((_c1 select 2) - (_c0 select 2)) * _f, 0.38]];
            };
        };
        _hDraw = [_hSig, _list];
        uiNamespace setVariable ["COMSPEC_ATAK_HeatDraw", _hDraw];
    };
    { _map drawRectangle [_x select 0, _x select 1, _x select 1, 0, _x select 2, _tex]; } forEach (_hDraw select 1);
};

// Noms des villes, villages, lieux-dits et sites (fn_mapPlaceText) : sous tous les symboles. La carte Arma ne les
// écrit plus (COMSPEC_RscMapAtak) ; chaque catégorie se masque dans CALQUES.
[_map, _ts, _kt, _scale] call comspec_atak_native_fnc_mapPlaceText;

// Marqueurs de la mission (symboles, zones, traits) : la carte Arma ne les dessine plus (showMarkers = 0).
[_map, _mk] call comspec_atak_native_fnc_mapGameMarkers;

// GPS : itinéraire en trait épais (bordure sombre, bleu à parcourir, gris déjà parcouru), arrivée en drapeau.
private _route = missionNamespace getVariable ["COMSPEC_ATAK_Route", createHashMap];
if ((count _route) > 0) then {
    private _pts = _route get "pts";
    private _idx = _route getOrDefault ["idx", 0];
    private _mpu = (_map ctrlMapScreenToWorld [0, 0]) distance2D (_map ctrlMapScreenToWorld [0.0035, 0]);
    private _tex = "#(rgb,8,8,3)color(1,1,1,1)";
    private _seg = {
        params ["_a", "_b", "_w", "_c"];
        private _len = _a distance2D _b;
        if (_len < 0.5) exitWith {};
        _map drawRectangle [[((_a select 0) + (_b select 0)) / 2, ((_a select 1) + (_b select 1)) / 2], _w, _len / 2 + _w * 0.6, _a getDir _b, _c, _tex];
    };
    private _proj = _route getOrDefault ["proj", _pts select 0];
    // Tronçons hors de la vue sautés (route de plusieurs km : des centaines de tronçons redessinés à chaque image).
    (ctrlPosition _map) params ["_rcx", "_rcy", "_rcw", "_rch"];
    private _rtl = _map ctrlMapScreenToWorld [_rcx, _rcy];
    private _rbr = _map ctrlMapScreenToWorld [_rcx + _rcw, _rcy + _rch];
    private _rmg = _mpu * 2;
    private _rx0 = ((_rtl select 0) min (_rbr select 0)) - _rmg;
    private _rx1 = ((_rtl select 0) max (_rbr select 0)) + _rmg;
    private _ry0 = ((_rtl select 1) min (_rbr select 1)) - _rmg;
    private _ry1 = ((_rtl select 1) max (_rbr select 1)) + _rmg;
    for "_i" from 0 to ((count _pts) - 2) do {
        private _a = _pts select _i; private _b = _pts select (_i + 1);
        if ((((_a select 0) max (_b select 0)) < _rx0) || {((_a select 0) min (_b select 0)) > _rx1} || {((_a select 1) max (_b select 1)) < _ry0} || {((_a select 1) min (_b select 1)) > _ry1}) then { continue };
        private _past = _i < _idx;
        if (_i isEqualTo _idx) then {
            [_a, _proj, _mpu, [0.45, 0.48, 0.50, 0.75]] call _seg;
            _a = _proj;
        };
        if (_past) then { [_a, _b, _mpu, [0.45, 0.48, 0.50, 0.75]] call _seg; } else {
            [_a, _b, _mpu * 1.45, [0.05, 0.18, 0.40, 0.9]] call _seg;
            [_a, _b, _mpu, [0.26, 0.52, 0.96, 1]] call _seg;
        };
    };
    _map drawIcon ["\A3\ui_f\data\map\markers\military\flag_CA.paa", [0.92, 0.26, 0.21, 1], _route get "dest", 26 * _k * _is, 26 * _k * _is, 0, (_route getOrDefault ["label", ""]) call comspec_atak_native_fnc_tr, 2, 0.028 * _ts, (COMSPEC_ATAK_FontSet select 1), "right"];
};

// Calques Relief (champ de vision / altitudes) et Wave Relay (liens du maillage).
[_map] call comspec_atak_native_fnc_reliefDraw;
[_map] call comspec_atak_native_fnc_meshDraw;
private _perfT = diag_tickTime;
["draw", _map] call comspec_atak_native_fnc_noFly;
[_map] call comspec_atak_native_fnc_droneDraw;
["carte drone (image)", _perfT] call comspec_atak_native_fnc_perfAcc;
["draw", _map] call comspec_atak_native_fnc_mapPing;
// Plan de briefing (app Plan) sous les unités : plan partagé du camp et mon brouillon (plus pâle). Affiché par défaut.
if (profileNamespace getVariable ["COMSPEC_ATAK_PlanOnMap2", true]) then {
    [_map, (missionNamespace getVariable [format ["COMSPEC_ATAK_Plan_%1", side group player], [0, [], ""]]) param [1, []], 0.85] call comspec_atak_native_fnc_planDraw;
    // Salle collaborative rejointe (code) : dessinée comme le plan du camp.
    private _planRoom = uiNamespace getVariable ["COMSPEC_ATAK_PlanRoomId", ""];
    if (_planRoom isNotEqualTo "") then { [_map, (missionNamespace getVariable ["COMSPEC_ATAK_PlanRoom_" + _planRoom, [0, [], ""]]) param [1, []], 0.85] call comspec_atak_native_fnc_planDraw; };
    private _planDraft = missionNamespace getVariable ["COMSPEC_ATAK_PlanDraft", []];
    if ((count _planDraft) > 0) then { [_map, _planDraft, 0.55] call comspec_atak_native_fnc_planDraw; };
};

// Guerre électronique et GPS naturel (fn_gpsFix) : position affichée avec l'erreur GPS, pistes BFT dégradées.
([] call comspec_atak_native_fnc_ewEffects) params ["", "_ewBft", "", "", "", "_ewOff"];
private _selfGps = (getPosASLVisual player) vectorAdd [_ewOff select 0, _ewOff select 1, 0];
_selfGps set [2, 0];
uiNamespace setVariable ["COMSPEC_ATAK_SelfGpsPos", _selfGps];
private _nato = (profileNamespace getVariable ["COMSPEC_ATAK_UnitSymbols", "NATO"]) isEqualTo "NATO";
// Terminal compromis (fn_compromiseView) : flux BFT coupé, seule ma position GPS reste en direct (pistes figées dessinées à la fin).
private _compSkip = ["skip"] call comspec_atak_native_fnc_compromiseView;
private _dcAll = uiNamespace getVariable "COMSPEC_ATAK_MapDrawCache";
if (isNil "_dcAll") then { _dcAll = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_MapDrawCache", _dcAll]; };
// Unités parties : entrées périmées retirées de temps en temps (pas de table sans fin).
if ((count _dcAll) > (300 max (2 * (count (_data getOrDefault ["units", createHashMap]))))) then { private _tNow = diag_tickTime; { if (_tNow > ((_y select 0) + 5)) then { _dcAll deleteAt _x; }; } forEach +_dcAll; };
{
    private _entity = _y;
    private _pos = _entity getOrDefault ["position",[]];
    if ((count _pos) < 2) then { continue };
    if (_compSkip && {!(_entity getOrDefault ["self", false])}) then { continue };
    private _self = _entity getOrDefault ["self",false];
    // Symbole, couleur de base, icône perso et symbole OTAN gardés 1 s par unité (table à part, COMSPEC_ATAK_MapDrawCache) :
    // refaits à chaque image et pour chaque unité avant. Plus dans la piste : fn_localDataRefresh la refait 4 fois par seconde.
    private _dc = _dcAll getOrDefault [_x, []];
    if ((count _dc) < 7 || {diag_tickTime > (_dc select 0)} || {(_dc select 6) isNotEqualTo _nato}) then {
        ([_entity] call comspec_atak_native_fnc_symbology) params ["_sIcon","_sColor"];
        private _ci = "";
        private _custom = _entity getOrDefault ["icon",""];
        if (_custom isNotEqualTo "" && {(_entity getOrDefault ["type",""]) isEqualTo "infantry"}) then {
            _ci = _mkIconCache getOrDefault ["~" + _custom, "§"];
            if (_ci isEqualTo "§") then { _ci = getText (configFile >> "CfgMarkers" >> _custom >> "icon"); _mkIconCache set ["~" + _custom, _ci]; };
        };
        private _nt = ""; private _ne = "";
        if (_nato && {!_self}) then { ([_entity] call comspec_atak_native_fnc_natoSymbol) params ["_nt0", "_ne0"]; _nt = _nt0; _ne = _ne0; };
        _dc = [diag_tickTime + 1, _sIcon, _sColor, _ci, _nt, _ne, _nato];
        _dcAll set [_x, _dc];
    };
    private _icon = _dc select 1;
    private _color = +(_dc select 2);
    private _freshness = _entity getOrDefault ["freshness","LIVE"];
    if (_freshness isEqualTo "STALE") then { _color set [3,0.65]; };
    if (_freshness in ["LOST","OFFLINE"]) then { _color set [3,0.3]; };
    private _size = ([16, 21] select (_x isEqualTo _selected || _self)) * _k * _is;
    if ((_entity getOrDefault ["affiliation",""]) isEqualTo "friend") then { _color = +_allyRgb; };
    if (_self) then { _color = +_selfRgb; };
    // Moi : position GPS affichée (vraie position à l'image près + erreur GPS simulée), la même pour l'icône, les anneaux
    // d'équipe et de sélection, le cartouche et le trait de distance (avant : anneaux et trait restaient sur la vraie position).
    if (_self) then { _pos = _selfGps; };
    if (_ewBft && {!_self}) then { _color set [3, ((_color select 3) min 0.65) * 0.5]; };
    private _dir = _entity getOrDefault ["heading",0];
    if ((_dc select 3) isNotEqualTo "") then { _icon = _dc select 3; _dir = 0; };
    // Fraîcheur gardée après la palette ami / moi (qui remet l'alpha à 1) : piste figée ou perdue toujours estompée.
    if (_freshness isEqualTo "STALE") then { _color set [3, (_color select 3) min 0.65]; };
    if (_freshness in ["LOST","OFFLINE"]) then { _color set [3, (_color select 3) min 0.3]; };
    // Réglages > Carte « Icônes des unités » OTAN : cadre + modificateur MIL-STD-2525 / APP-6 (moi : inchangé),
    // non orienté, un peu plus grand que le point ; échelon (points d'équipe / groupe) au-dessus pour les chefs.
    private _ech = "";
    if (_nato && {!_self}) then {
        _icon = _dc select 4; _ech = _dc select 5; _dir = 0; _size = _size * 1.3;
    };
    // Précision annoncée par le point de position de l'allié (fn_pli) : cercle discret au-delà de 10 m.
    private _acc = _entity getOrDefault ["acc", 0];
    if (!_self && {_acc >= 10}) then { _map drawEllipse [_pos, _acc, _acc, 0, [_color select 0, _color select 1, _color select 2, (_color select 3) * 0.45], ""]; };
    _map drawIcon [_icon,_color,_pos,_size,_size,_dir,"",0];
    if (_lblOn && {!_boxOn} && {_scale < 0.25}) then {
        private _cs = _entity getOrDefault ["callsign",""];
        if (_cs isNotEqualTo "") then { _map drawIcon [_clear,[1,1,1,(_color select 3) max 0.6],_pos,_size,_size,0,_cs,2,0.027 * _kt * _ts,(COMSPEC_ATAK_FontSet select 1),"right"]; };
    };
    if (_ech isNotEqualTo "") then { _map drawIcon [_ech, _color, _pos, _size, _size, 0, "", 0]; };
    // Candidat au cartouche : pas moi (carte « moi »), et pour les autres groupes seulement le chef (un cartouche par
    // groupe, pas un par équipier) ; les unités Athena sans objet et l'unité sélectionnée gardent toujours le leur.
    if (_boxOn && {!_self}) then {
        private _cs = _entity getOrDefault ["callsign", ""];
        private _uo = _entity getOrDefault ["object", objNull];
        private _mine = !isNull _uo && {group _uo isEqualTo _myGroup};
        if (_cs isNotEqualTo "" && {_x isEqualTo _selected || {isNull _uo} || {_mine} || {leader group _uo isEqualTo _uo}}) then {
            private _hostile = (_entity getOrDefault ["affiliation", ""]) isEqualTo "hostile";
            _lblCands pushBack [[[3, 2] select _mine, 1] select _hostile, _x, _pos, toUpper _cs, _hostile, ((_color select 3) max 0.45) min 1];
            if (_x isEqualTo _selected) then { (_lblCands select ((count _lblCands) - 1)) set [0, 0]; };
        };
    };
    // Unité sélectionnée : anneau vert à taille fixe à l'écran, plus large que celui de l'équipe de feu (avant : cercle de
    // 22 m qui grossissait au zoom jusqu'à couvrir la moitié de la carte).
    if (_x isEqualTo _selected) then {
        _map drawIcon ["\A3\ui_f\data\map\markers\military\circle_CA.paa", [0.36, 0.78, 0.42, 0.9], _pos, _size * 2.1, _size * 2.1, 0, "", 0];
    };
    // Équipe de feu (mon groupe) : anneau à la couleur de l'équipe.
    private _o = _entity getOrDefault ["object", objNull];
    if (!isNull _o && {group _o isEqualTo group player} && {(_o getVariable ["COMSPEC_FT", ""]) isNotEqualTo ""}) then {
        // Équipe lue une fois par changement (catalogue + table de 15 entrées refaits à chaque image sinon).
        private _ftSig = [_o getVariable ["COMSPEC_FT", ""], _o getVariable ["COMSPEC_FTRole", ""], (group _o) getVariable ["COMSPEC_FireTeams", []]];
        private _ftc = _o getVariable ["COMSPEC_ftDrawCache", []];
        if ((_ftc param [0, 0]) isNotEqualTo _ftSig) then { _ftc = [_ftSig, [_o] call comspec_atak_native_fnc_ftInfo]; _o setVariable ["COMSPEC_ftDrawCache", _ftc]; };
        private _ft = _ftc select 1;
        if ((_ft get "id") isNotEqualTo "") then {
            private _rc = +(_ft get "rgba"); _rc set [3, (_color select 3) max 0.35];
            _map drawIcon ["\A3\ui_f\data\map\markers\military\circle_CA.paa", _rc, _pos, _size * 1.55, _size * 1.55, 0, "", 0];
        };
    };
} forEach (_data getOrDefault ["units",createHashMap]);

// Listes filtrées du magasin des marqueurs (repères Athena absents du jeu ; marqueurs à cartouche E / N), refaites quand
// sa révision change (au plus 4 fois par seconde) et toutes les 2 s par sécurité : avant, deux passes complètes sur tous
// les marqueurs (mission comprise) à chaque image.
private _mkRev = (_data getOrDefault ["revisions",createHashMap]) getOrDefault ["markers",-1];
private _mkLists = missionNamespace getVariable ["COMSPEC_ATAK_DrawMkLists", []];
if ((count _mkLists) < 4 || {(_mkLists select 0) isNotEqualTo _mkRev} || {diag_tickTime > (_mkLists select 1)}) then {
    private _rem = [];
    private _tag = [];
    {
        private _lc = _y getOrDefault ["local",false];
        if (!_lc) then { _rem pushBack [_x,_y]; };
        if ((_y getOrDefault ["text",""]) isNotEqualTo "" && {!_lc || {((_y getOrDefault ["id",_x]) find "_USER_DEFINED") >= 0}} && {!((toUpper (_y getOrDefault ["shape","ICON"])) in ["POLYLINE","RECTANGLE","ELLIPSE"])}) then { _tag pushBack [_x,_y]; };
    } forEach (_data getOrDefault ["markers",createHashMap]);
    _mkLists = [_mkRev, diag_tickTime + 2, _rem, _tag];
    missionNamespace setVariable ["COMSPEC_ATAK_DrawMkLists", _mkLists];
};
{
    private _marker = _x select 1;
    if (_marker getOrDefault ["local",false]) then { continue };
    private _pos = _marker getOrDefault ["position",[]];
    if ((count _pos) < 2) then { continue };
    private _shape = toUpper (_marker getOrDefault ["shape","ICON"]);
    private _size = _marker getOrDefault ["size",[1,1]];
    switch _shape do {
        case "RECTANGLE": {
            _map drawRectangle [_pos,_size select 0,_size select 1,_marker getOrDefault ["dir",0],[0.36,0.78,0.42,0.65],""];
        };
        case "ELLIPSE": {
            _map drawEllipse [_pos,_size select 0,_size select 1,_marker getOrDefault ["dir",0],[0.36,0.78,0.42,0.65],""];
        };
        default {
            private _type = _marker getOrDefault ["type","mil_dot"];
            private _icon = _mkIconCache getOrDefault [_type, ""];
            if (_icon isEqualTo "") then {
                _icon = getText (configFile >> "CfgMarkers" >> _type >> "icon");
                if (_icon isEqualTo "") then { _icon = "\A3\ui_f\data\map\markers\military\dot_CA.paa"; };
                _mkIconCache set [_type, _icon];
            };
            private _rgba = [_marker getOrDefault ["color","ColorGreen"], [0.36,0.78,0.42,1]] call _mkRgba;
            _rgba set [3,(_rgba select 3) * (_marker getOrDefault ["alpha",1])];
            // Nom du repère : écrit par fn_mapMarkerText, comme ceux des marqueurs du jeu.
            _map drawIcon [_icon,_rgba,_pos,16 * _k * _is,16 * _k * _is,_marker getOrDefault ["dir",0],"",0];
        };
    };
} forEach (_mkLists select 2);

{
    if ((count _x) >= 2) then {
        _map drawLine [_x select 0,_x select 1,[0.36,0.78,0.42,0.8]];
        _map drawArrow [_x select 0,_x select 1,[0.36,0.78,0.42,0.9]];
    };
} forEach (_data getOrDefault ["routes",[]]);

if (profileNamespace getVariable ["COMSPEC_ATAK_ZonesLayer", true]) then {
    {
        if ((count _x) >= 3) then { _map drawPolygon [_x,[0.95,0.67,0.20,0.18]]; };
    } forEach (_data getOrDefault ["zones",[]]);
    missionNamespace setVariable ["COMSPEC_ATAK_ZonesHideRev", -1];
} else {
    // Calque masqué : Overwatch recrée ses marqueurs de zone à chaque synchro, on les remasque.
    // Seulement quand un marqueur a changé (compteur COMSPEC_ATAK_MkRev), au plus toutes les 2 s : avant, tous les
    // marqueurs étaient parcourus toutes les 2 s même sans aucun changement.
    private _zRev = missionNamespace getVariable ["COMSPEC_ATAK_MkRev", 0];
    if (diag_tickTime > (missionNamespace getVariable ["COMSPEC_ATAK_ZonesHideAt", 0]) && {_zRev isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_ZonesHideRev", -1])}) then {
        missionNamespace setVariable ["COMSPEC_ATAK_ZonesHideAt", diag_tickTime + 2];
        missionNamespace setVariable ["COMSPEC_ATAK_ZonesHideRev", _zRev];
        { if ((_x select [0, 11]) isEqualTo "COMSPEC_TZ_" && {(markerAlpha _x) > 0}) then { _x setMarkerAlphaLocal 0; }; } forEach allMapMarkers;
    };
};

private _amber = [0.95,0.67,0.20,1];
private _yellow = [1,0.92,0.15,0.95];
private _dot = "\A3\ui_f\data\map\markers\military\dot_CA.paa";
private _cursor = _state getOrDefault ["cursorPos",[]];
private _dashed = {
    params ["_a","_b","_c"];
    private _n = ((round ((_a distance2D _b) / (_scale * 300))) max 4) min 60;
    for "_i" from 0 to (_n - 1) step 2 do {
        _map drawLine [_a vectorAdd ((_b vectorDiff _a) vectorMultiply (_i / _n)), _a vectorAdd ((_b vectorDiff _a) vectorMultiply ((_i + 1) / _n)), _c];
    };
};

// Zones tactiques Athena (LZ, objectif, zone dangereuse…) et zones roleplay (brouillage, sans couverture).
if (profileNamespace getVariable ["COMSPEC_ATAK_ZonesLayer", true]) then {
    private _zc = createHashMapFromArray [["LZ",[0.36,0.85,0.42,1]],["DZ",[0.36,0.85,0.42,1]],["EXTRACT_POINT",[0.36,0.85,0.42,1]],["RALLY_POINT",[0.28,0.70,1,1]],["SAFE_ZONE",[0.28,0.70,1,1]],["OBJECTIVE",[0.95,0.67,0.20,1]],["DANGER_ZONE",[0.95,0.22,0.18,1]],["NO_GO_AREA",[0.95,0.22,0.18,1]],["RESTRICTED_AREA",[0.95,0.5,0.15,1]]];
    {
        _x params [["_id",""],["_geom","CIRCLE"],["_geo",[]],["_r",0],["_type",""],["_threat",""],["_label",""]];
        private _c = +(_zc getOrDefault [toUpper _type,[0.95,0.67,0.20,1]]);
        if ((toUpper _geom) in ["CIRCLE","RECTANGLE"] && {(count _geo) >= 2}) then {
            private _p = [_geo select 0,_geo select 1,0];
            _map drawEllipse [_p,_r max 20,_r max 20,0,_c,""];
            _map drawEllipse [_p,_r max 20,_r max 20,0,[_c select 0,_c select 1,_c select 2,0.12],"#(rgb,8,8,3)color(1,1,1,1)"];
            _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)",_c,_p,0,0,0,_label,2,0.028 * _ts,(COMSPEC_ATAK_FontSet select 1),"center"];
        } else {
            if ((count _geo) >= 2 && {(_geo select 0) isEqualType []}) then {
                private _pts = _geo apply { [_x select 0,_x select 1,0] };
                if ((toUpper _geom) isEqualTo "POLYGON" && {(count _pts) >= 3}) then { _map drawPolygon [_pts,_c]; } else { for "_i" from 0 to ((count _pts) - 2) do { _map drawLine [_pts select _i,_pts select (_i + 1),_c]; }; };
                _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)",_c,_pts select 0,0,0,0,_label,2,0.028 * _ts,(COMSPEC_ATAK_FontSet select 1),"right"];
            };
        };
    } forEach (missionNamespace getVariable ["COMSPEC_DangerZones",[]]);
    {
        if (_x isEqualType createHashMap) then {
            private _p = _x getOrDefault ["position",[]];
            if ((count _p) >= 2) then {
                private _r = _x getOrDefault ["radius",200];
                private _c = switch (_x getOrDefault ["type",""]) do { case "jammer": {[0.85,0.25,0.95,1]}; case "no_coverage": {[0.6,0.6,0.6,1]}; default {[0.95,0.5,0.15,1]}; };
                _map drawEllipse [[_p select 0,_p select 1,0],_r,_r,0,_c,""];
                _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)",_c,[_p select 0,_p select 1,0],0,0,0,format ["%1 · %2 %%",_x getOrDefault ["name","Zone"],_x getOrDefault ["intensity",0]],2,0.026 * _ts,(COMSPEC_ATAK_FontSet select 0),"center"];
            };
        };
    } forEach (missionNamespace getVariable ["COMSPEC_RoleplayZones",[]]);
};

// Mes charges (app Explosifs) : numéro de pose, mode de mise à feu, temps restant des minuteries, T- de la séquence.
{
    private _e = _x get "obj";
    // Charge détonée ou supprimée dans la fenêtre du cache de fn_exploList (0,5 s) : pas d'icône à l'origine de la carte.
    if (isNull _e) then { continue };
    private _c = [[0.95,0.67,0.20,1], [0.90,0.78,0.29,1], [0.90,0.28,0.23,1], [0.79,0.83,0.81,1]] select ((["atak","command","timer","mine"] find (_x get "kind")) max 0);
    private _txt = format ["C%1", _forEachIndex + 1];
    if ((_x get "remaining") >= 0) then { _txt = format ["%1 · %2 s", _txt, ceil (_x get "remaining")]; };
    private _key = _x get "key";
    private _q = ((uiNamespace getVariable ["COMSPEC_ATAK_Explo", createHashMap]) getOrDefault ["queue", []]) select { ((_x select 1) get "key") isEqualTo _key };
    if ((count _q) > 0) then { _txt = format ["%1 · T-%2", _txt, ((((_q select 0) select 0) - diag_tickTime) max 0) toFixed 1]; _c = [0.90,0.28,0.23,1]; };
    _map drawIcon ["\z\comspec_atak_native\addons\main\data\app_explo.paa", _c, getPosASL _e, 18 * _k * _is, 18 * _k * _is, 0, _txt, 2, 0.03 * _kt * _ts, (COMSPEC_ATAK_FontSet select 1), "right"];
} forEach ([] call comspec_atak_native_fnc_exploList);

// Goniométrie : émetteurs estimés (cercle d'incertitude) et relèvements seuls (azimut tracé sur 3 km).
if (profileNamespace getVariable ["COMSPEC_ATAK_SigintLayer", true]) then {
    private _sc = [0.95,0.35,0.85,1];
    {
        _x params ["_cs","_kind","_p","_r","_n","_brg"];
        private _pp = [_p select 0,_p select 1,0];
        if (_kind isEqualTo "azimuth") then {
            private _end = _pp vectorAdd [3000 * sin _brg,3000 * cos _brg,0];
            [_pp,_end,_sc] call _dashed;
            _map drawIcon [_dot,_sc,_pp,8,8,0,format ["%1 · %2°",_cs,round _brg],2,0.026 * _ts,(COMSPEC_ATAK_FontSet select 0),"right"];
        } else {
            _map drawEllipse [_pp,_r,_r,0,_sc,""];
            _map drawIcon ["\A3\ui_f\data\map\markers\military\unknown_CA.paa",_sc,_pp,18,18,0,(format ["Émetteur probable %1 · ±%2 m · %3 relevés",_cs,round _r,_n]) call comspec_atak_native_fnc_tr,2,0.026 * _ts,(COMSPEC_ATAK_FontSet select 1),"right"];
        };
    } forEach (uiNamespace getVariable ["COMSPEC_ATAK_Sigint",[]]);
};

// Trait jaune joueur -> curseur (outil Distance, en main)
if ((_state getOrDefault ["interactive",false]) && {_state getOrDefault ["mapDistance",true]} && {(count _cursor) >= 2} && {(_state getOrDefault ["mapMode","SELECT"]) in ["SELECT","MARKER"]}) then {
    private _me = _selfGps;
    private _c = [_cursor select 0,_cursor select 1,0];
    [[_me select 0,_me select 1,0],_c,_yellow] call _dashed;
    // Distance, azimut et dénivelé (sol sous le curseur moins le sol sous ma position GPS) avec la pente moyenne.
    private _dz = round ((getTerrainHeightASL _c) - (getTerrainHeightASL [_me select 0, _me select 1]));
    private _dd = _me distance2D _c;
    _map drawIcon [_dot,_yellow,_c,10,10,0,format ["%1 m  %2°  %3%4 m (%5 %%)",round _dd,[round (_me getDir _c),3] call CBA_fnc_formatNumber,["","+"] select (_dz > 0),_dz,round (100 * _dz / (_dd max 1))],2,0.03 * _ts,(COMSPEC_ATAK_FontSet select 1),"right"];
};

// Point de pose choisi d'un clic droit en mode MARQUEUR (fn_markerPlace) : aperçu du type choisi dans un anneau qui pulse.
private _anchor = _state getOrDefault ["markerAnchor", []];
if ((count _anchor) >= 2 && {(_state getOrDefault ["mapMode","SELECT"]) isEqualTo "MARKER"}) then {
    private _ap = [_anchor select 0, _anchor select 1, 0];
    private _pulse = 0.55 + 0.45 * (abs sin (diag_tickTime * 180));
    private _ring = [0.36, 0.78, 0.42, _pulse];
    _map drawIcon ["\A3\ui_f\data\map\markers\military\circle_CA.paa", _ring, _ap, 34 * _is, 34 * _is, 0, "", 0];
    private _ic = getText (configFile >> "CfgMarkers" >> (_state getOrDefault ["markerType", "mil_dot"]) >> "icon");
    if (_ic isNotEqualTo "") then { _map drawIcon [_ic, [1, 1, 1, 0.75], _ap, 20 * _is, 20 * _is, 0, "", 0]; };
    _map drawIcon [_dot, [0.36, 0.78, 0.42, 1], _ap, 6, 6, 0, "  " + (("POINT DE POSE · VALIDER") call comspec_atak_native_fnc_tr), 2, 0.026 * _ts, (COMSPEC_ATAK_FontSet select 1), "right"];
};

// Points de passage : tronçons, étapes nommées et typées, point choisi en mode POINTS DE PASSAGE (fn_wpDraw).
[_map, _k * _is, _ts, _cursor] call comspec_atak_native_fnc_wpDraw;

// Mesure A -> B (ou A -> curseur tant que B n'est pas posé)
private _measure = +(_state getOrDefault ["mapMeasure",[]]);
if ((count _measure) isEqualTo 1 && {(count _cursor) >= 2}) then { _measure pushBack [_cursor select 0,_cursor select 1,0]; };
if ((count _measure) >= 2) then {
    _measure params ["_a","_b"];
    _map drawLine [_a,_b,_amber];
    _map drawIcon [_dot,_amber,_a,14,14,0,"A",2,0.03 * _ts,(COMSPEC_ATAK_FontSet select 1),"right"];
    private _dz = round ((getTerrainHeightASL _b) - (getTerrainHeightASL _a));
    private _dd = _a distance2D _b;
    _map drawIcon [_dot,_amber,_b,14,14,0,format ["%1 m  %2°  %3%4 m (%5 %%)",round _dd,[round (_a getDir _b),3] call CBA_fnc_formatNumber,["","+"] select (_dz > 0),_dz,round (100 * _dz / (_dd max 1))],2,0.03 * _ts,(COMSPEC_ATAK_FontSet select 1),"right"];
};

// Bâtiments numérotés
{
    _x params ["_b","_label"];
    if (isNull _b) then { continue };
    (boundingBoxReal _b) params ["_p1","_p2"];
    _map drawRectangle [getPosASL _b,((_p2 select 0) - (_p1 select 0)) / 2,((_p2 select 1) - (_p1 select 1)) / 2,getDir _b,[0.95,0.67,0.20,0.9],""];
    _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)",[1,1,1,1],getPosASL _b,0,0,0,_label,2,0.032 * _ts,(COMSPEC_ATAK_FontSet select 1),"center"];
} forEach (_state getOrDefault ["mapHouses",[]]);

// Hauteurs relevées
{
    _x params ["_p","_alt","_delta"];
    _map drawIcon ["\A3\ui_f\data\map\markers\military\triangle_CA.paa",[0.85,0.85,0.85,1],_p,14,14,0,format ["%1 m (%2%3)",round _alt,["","+"] select (_delta >= 0),round _delta],2,0.03 * _ts,(COMSPEC_ATAK_FontSet select 1),"right"];
} forEach (_state getOrDefault ["mapHeights",[]]);

// Zones plates
{
    _map drawEllipse [_x,12,12,0,[0.36,0.78,0.42,0.9],""];
    _map drawIcon [_dot,[0.36,0.78,0.42,1],_x,10,10,0,format ["LZ %1",_forEachIndex + 1],2,0.03 * _ts,(COMSPEC_ATAK_FontSet select 1),"right"];
} forEach (_state getOrDefault ["mapFlat",[]]);

// Ligne de vue
private _los = _state getOrDefault ["mapLos",[]];
if ((count _los) >= 3) then {
    _los params ["_from","_to","_block"];
    if ((count _block) > 0) then {
        _map drawLine [_from,ASLToAGL _block,[0.36,0.78,0.42,1]];
        _map drawLine [ASLToAGL _block,_to,[0.90,0.25,0.22,1]];
        _map drawIcon [_dot,[0.90,0.25,0.22,1],ASLToAGL _block,12,12,0,("BLOQUÉ") call comspec_atak_native_fnc_tr,2,0.03 * _ts,(COMSPEC_ATAK_FontSet select 1),"right"];
    } else {
        _map drawLine [_from,_to,[0.36,0.78,0.42,1]];
        _map drawIcon [_dot,[0.36,0.78,0.42,1],_to,12,12,0,("VUE OK") call comspec_atak_native_fnc_tr,2,0.03 * _ts,(COMSPEC_ATAK_FontSet select 1),"right"];
    };
};

// Marqueur sélectionné
private _selM = _state getOrDefault ["selectedMarker",""];
if (_selM isNotEqualTo "" && {(markerShape _selM) isNotEqualTo ""}) then {
    private _r = (_scale * 600) max 8;
    _map drawEllipse [getMarkerPos _selM,_r,_r,0,[0.36,0.78,0.42,1],""];
};

// Traits joueurs épais ou fléchés et trait / dessin en cours (fn_strokeDraw).
[_map, _cursor] call comspec_atak_native_fnc_strokeDraw;

// App Feux : pièce, cible et ligne de tir.
private _fires = uiNamespace getVariable ["COMSPEC_ATAK_Fires", createHashMap];
private _ft = _fires getOrDefault ["target", []];
if ((count _ft) >= 2) then {
    private _red = [0.90, 0.25, 0.22, 1];
    private _rr = (_scale * 700) max 10;
    _map drawEllipse [_ft, _rr, _rr, 0, _red, ""];
    _map drawIcon ["\A3\ui_f\data\map\markers\military\destroy_CA.paa", _red, _ft, 22, 22, 0, "TGT", 2, 0.032 * _ts, (COMSPEC_ATAK_FontSet select 1), "right"];
    private _gid = _fires getOrDefault ["gun", "MAN"];
    private _g = if (_gid isEqualTo "MAN") then { objNull } else { objectFromNetId _gid };
    if (!isNull _g) then {
        _map drawLine [getPosATL _g, _ft, [0.90, 0.25, 0.22, 0.7]];
        _map drawIcon ["\A3\ui_f\data\map\markers\nato\b_mortar.paa", [0.30, 0.70, 1, 1], getPosATL _g, 22, 22, 0, "GUN", 2, 0.03 * _ts, (COMSPEC_ATAK_FontSet select 1), "right"];
    };
};

// Cartouches des marqueurs (style plan de mission) : cercle de couleur + boîte noire « nom / E / N ».
// Seuls les marqueurs posés par les joueurs ou Athena et ayant un titre ; réglage profil COMSPEC_ATAK_MarkerTags.
private _pool = uiNamespace getVariable ["COMSPEC_ATAK_MarkerTagPool", []];
private _used = 0;
private _tagged = [];
if (((["COMSPEC_ATAK_MarkerTags", true, "native_marker_tags"] call comspec_atak_native_fnc_pref) select 0) && {_k > 0.75} && {_scale < 0.12}) then {
    private _disp = ctrlParent _map;
    private _mp = ctrlPosition _map;
    private _gridCache = uiNamespace getVariable ["COMSPEC_ATAK_TagGridCache", createHashMap];
    private _fs = (((_mp select 3) * 0.024) max (safeZoneH * 0.010)) * _ts;
    {
        if (_used >= 30) then { break };
        private _m = _x select 1;
        private _id = _m getOrDefault ["id", _x select 0];
        private _title = _m getOrDefault ["text", ""];
        if (_title isEqualTo "") then { continue };
        if ((_m getOrDefault ["local", false]) && {(_id find "_USER_DEFINED") < 0}) then { continue };
        if ((toUpper (_m getOrDefault ["shape", "ICON"])) in ["POLYLINE", "RECTANGLE", "ELLIPSE"]) then { continue };
        private _pos = _m getOrDefault ["position", []];
        if ((count _pos) < 2) then { continue };
        private _sp = _map ctrlMapWorldToScreen _pos;
        if ((_sp select 0) < (_mp select 0) || {(_sp select 0) > ((_mp select 0) + (_mp select 2))} || {(_sp select 1) < (_mp select 1)} || {(_sp select 1) > ((_mp select 1) + (_mp select 3))}) then { continue };
        // Nom automatique seulement pour les marqueurs gardés par les tests ci-dessus (avant : pour tous, à chaque image).
        _title = [_title] call comspec_atak_native_fnc_markerAutoName;
        // Contacts ENI : déjà dans un cartouche rouge (fn_mapLabels).
        if (_boxOn && {(((toLower (_m getOrDefault ["type", ""])) select [0, 2]) isEqualTo "o_") || {(toUpper (_title select [0, 4])) in ["ENI ", "ENY "]}}) then { continue };
        private _rgba = [_m getOrDefault ["color", "ColorBlack"], [0.1, 0.1, 0.1, 1]] call _mkRgba;
        _rgba set [3, 1];
        private _r = (_scale * 900) max 12;
        _map drawEllipse [_pos, _r, _r, 0, _rgba, ""];
        _map drawEllipse [_pos, _r * 0.93, _r * 0.93, 0, _rgba, ""];
        // Coordonnées : grille 10 chiffres coupée en E / N, mises en cache par position.
        private _key = format ["%1|%2|%3", _id, round (_pos select 0), round (_pos select 1)];
        private _g = _gridCache getOrDefault [_key, ""];
        if (_g isEqualTo "") then {
            _g = [_pos, 10] call comspec_atak_native_fnc_gridRef;
            _gridCache set [_key, _g];
        };
        (_g splitString " ") params [["_e", ""], ["_n", ""]];
        // Boîte gardée dans la carte (contrôle posé sur l'écran, rien ne le découpe) : à gauche de l'anneau si elle
        // dépasse à droite, remontée si elle dépasse en bas ; sinon pas de cartouche (le nom reste dessiné sur la carte).
        private _w = (_fs * 0.42 * (((count _title) max 9) + 1)) max (_fs * 4);
        private _ringPx = (_map ctrlMapWorldToScreen [(_pos select 0) + _r, _pos select 1]) select 0;
        private _rect = [_ringPx + _fs * 0.3, (_sp select 1) - _fs * 0.2, _w, _fs * 3.3];
        if (((_rect select 0) + _w) > ((_mp select 0) + (_mp select 2))) then {
            _rect set [0, ((_map ctrlMapWorldToScreen [(_pos select 0) - _r, _pos select 1]) select 0) - _fs * 0.3 - _w];
        };
        _rect set [1, ((_rect select 1) min ((_mp select 1) + (_mp select 3) - (_rect select 3))) max (_mp select 1)];
        if ((_rect select 0) < (_mp select 0) || {((_rect select 1) + (_rect select 3)) > ((_mp select 1) + (_mp select 3))}) then { continue };
        private _c = _pool param [_used, controlNull];
        if (isNull _c) then {
            _c = _disp ctrlCreate ["COMSPEC_RscStructuredText", -1];
            _pool set [_used, _c];
        };
        // Texte et taille touchés seulement quand ils changent : refaire parseText de 30 cartouches à chaque image
        // faisait chuter les images par seconde (carte en plein écran ou téléphone en HUD).
        private _sig = [_title, _e, _n, _fs];
        if ((_c getVariable ["comspec_sig", []]) isNotEqualTo _sig) then {
            _c setVariable ["comspec_sig", _sig];
            _c ctrlSetBackgroundColor [0.02, 0.02, 0.02, 0.86];
            _c ctrlSetStructuredText parseText ((format ["<t size='%4' font='COMSPEC_BarlowBold' color='#ffffff'>%1<br/>%2E<br/>%3N</t>", toUpper ([_title, "<", "&lt;"] call CBA_fnc_replace), _e, _n, 1]) call comspec_atak_native_fnc_tr);
            _c ctrlSetFontHeight _fs;
        };
        if ((_c getVariable ["comspec_rect", []]) isNotEqualTo _rect) then {
            _c setVariable ["comspec_rect", _rect];
            _c ctrlSetPosition _rect;
            _c ctrlCommit 0;
        };
        if !(ctrlShown _c) then { _c ctrlShow true; };
        _used = _used + 1;
        _tagged pushBack _id;
    } forEach (_mkLists select 3);
    if ((count _gridCache) > 400) then { _gridCache = createHashMap; };
    uiNamespace setVariable ["COMSPEC_ATAK_TagGridCache", _gridCache];
};
for "_i" from _used to ((count _pool) - 1) do { private _c = _pool select _i; if (ctrlShown _c) then { _c ctrlShow false; }; };
uiNamespace setVariable ["COMSPEC_ATAK_MarkerTagPool", _pool select { !isNull _x }];

// Noms des repères (fn_mapMarkerText, style au choix) : la carte Arma n'écrit plus le texte des marqueurs.
// Ceux qui ont déjà leur cartouche E / N (ci-dessus) sont sautés ; masquables dans CALQUES (Noms des repères).
[_map, _ts, _kt, _scale, _tagged, _boxOn] call comspec_atak_native_fnc_mapMarkerText;

// Calques Logistique (points de largage) et guerre électronique (gonio, brouilleurs).
if (profileNamespace getVariable ["COMSPEC_ATAK_LayerLogi", true]) then { [_map] call comspec_atak_native_fnc_logisticsDraw; };
if (profileNamespace getVariable ["COMSPEC_ATAK_LayerEw", true]) then { [_map] call comspec_atak_native_fnc_ewDraw; };
// Outil JTAC IP → cible (fn_jtacTool).
if ((count (_state getOrDefault ["jtacTool", []])) > 0) then { ["draw", _map] call comspec_atak_native_fnc_jtacTool; };
// Résultat du bouton RECHERCHER (fn_mapSearch) : cercle de précision et anneau pulsant, seulement s'il y en a un.
if ((count ((uiNamespace getVariable ["COMSPEC_ATAK_MapSearch", createHashMap]) getOrDefault ["hit", createHashMap])) > 0) then { ["draw", _map] call comspec_atak_native_fnc_mapSearch; };
if (profileNamespace getVariable ["COMSPEC_ATAK_LayerDrone", true]) then { [_map] call comspec_atak_native_fnc_droneDetectDraw; };
// Calques des modules externes : configFile >> "COMSPEC_ATAK_MapLayers" >> classe >> function (reçoit [carte]).
private _ext = uiNamespace getVariable "COMSPEC_ATAK_MapLayersExt";
if (isNil "_ext") then {
    _ext = ("true" configClasses (configFile >> "COMSPEC_ATAK_MapLayers")) apply { getText (_x >> "function") } select { _x isNotEqualTo "" };
    uiNamespace setVariable ["COMSPEC_ATAK_MapLayersExt", _ext];
};
{ if (!isNil _x) then { [_map] call (missionNamespace getVariable _x); }; } forEach _ext;

// Cartouches des unités et contacts ENI (style ATAK), au-dessus de tout sauf les numéros de grille.
if (_boxOn) then { [_map, _lblCands, _ts, _scale, _lblStyle] call comspec_atak_native_fnc_mapLabels; };

// Numéros de grille sur les bords haut (abscisses) et gauche (ordonnées), à la taille choisie dans Réglages > Carte.
// La carte Arma les dessine en tout petit (COMSPEC_RscMapAtak : sizeExGrid minuscule) ; ici une étiquette tous les
// 100 m, 200 m, 500 m, 1 km... selon le zoom (10 au plus par bord), à droite / au-dessus de la ligne qu'elle nomme.
private _mp = ctrlPosition _map;
_mp params ["_mx", "_my", "_mw", "_mh"];
private _gTl = _map ctrlMapScreenToWorld [_mx, _my];
private _gBr = _map ctrlMapScreenToWorld [_mx + _mw, _my + _mh];
private _spanX = (_gBr select 0) - (_gTl select 0);
private _spanY = (_gTl select 1) - (_gBr select 1);
if (_spanX > 1 && {_spanY > 1}) then {
    private _maxN = [6, 10] select (_k > 0.75);
    private _steps = [100, 200, 500, 1000, 2000, 5000, 10000, 20000, 50000];
    private _si = _steps findIf { ((_spanX max _spanY) / _x) <= _maxN };
    if (_si < 0) then { _si = (count _steps) - 1; };
    private _step = _steps select _si;
    private _gs = 0.019 * _k * _ts;
    private _light = _bgStyle in ["DARK", "NIGHT", "PHOTO", "SAT", "HYBRID"];
    private _gc = [[0.05, 0.05, 0.05, 0.95], [0.90, 0.94, 0.91, 0.95]] select _light;
    private _none = "#(argb,8,8,3)color(0,0,0,0)";
    private _cfgGrid = configFile >> "CfgWorlds" >> worldName >> "Grid";
    private _offX = getNumber (_cfgGrid >> "offsetX");
    private _offY = getNumber (_cfgGrid >> "offsetY");
    // Ordonnées croissantes vers le nord ? (comme fn_gridRef)
    private _midX = ((_gTl select 0) + (_gBr select 0)) / 2;
    private _midY = ((_gTl select 1) + (_gBr select 1)) / 2;
    private _northUp = (parseNumber ((mapGridPosition [_midX, _midY + 100]) select [3, 3])) >= (parseNumber ((mapGridPosition [_midX, _midY]) select [3, 3]));
    // Bord haut : un peu sous le bord (le texte est centré verticalement sur le point).
    private _yTop = (_map ctrlMapScreenToWorld [_mx, _my + _gs * 0.75]) select 1;
    private _x0 = _offX + (ceil (((_gTl select 0) - _offX) / _step)) * _step;
    for "_gx" from _x0 to (_gBr select 0) step _step do {
        private _g = mapGridPosition [_gx + 1, _midY];
        if ((count _g) >= 6) then { _map drawIcon [_none, _gc, [_gx, _yTop, 0], 0, 0, 0, _g select [0, 3], [0, 2] select _light, _gs, (COMSPEC_ATAK_FontSet select 1), "right"]; };
    };
    // Bord gauche : texte juste au-dessus de la ligne.
    private _y0 = _offY + (ceil (((_gBr select 1) - _offY) / _step)) * _step;
    for "_gy" from _y0 to (_gTl select 1) step _step do {
        private _g = mapGridPosition [_midX, _gy + ([-1, 1] select _northUp)];
        private _sy = ((_map ctrlMapWorldToScreen [_midX, _gy]) select 1) - _gs * 0.6;
        if ((count _g) >= 6 && {_sy > (_my + _gs * 1.4)}) then {
            _map drawIcon [_none, _gc, _map ctrlMapScreenToWorld [_mx + pixelW * 2, _sy], 0, 0, 0, _g select [3, 3], [0, 2] select _light, _gs, (COMSPEC_ATAK_FontSet select 1), "right"];
        };
    };
};

// Terminal compromis : couches grisées, bandeau, console d'erreurs et pistes figées, au-dessus de tout (fn_compromiseView).
if (_compSkip) then { ["draw", _map] call comspec_atak_native_fnc_compromiseView; };
