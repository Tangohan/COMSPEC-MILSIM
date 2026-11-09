params ["_map"];
if (isNull _map) exitWith {};

private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap];
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
private _selected = (_state getOrDefault ["selectedEntity",createHashMap]) getOrDefault ["id",""];
private _labels = (["COMSPEC_ATAK_Labels", true, "native_map_labels"] call comspec_atak_native_fnc_pref) select 0;
private _scale = ctrlMapScale _map;
// Zoom mémorisé pour la prochaine ouverture, quelle que soit la façon de zoomer (molette, boutons, glissement) : avant, seuls
// les boutons + / - l'enregistraient (fn_mapZoom) et le zoom revenait à 0,08 à la réouverture. Seulement une fois la carte centrée
// à l'ouverture (mapCentered), sinon la première image dessinée écraserait la valeur mémorisée par l'échelle d'arrivée.
if (_state getOrDefault ["mapCentered", false] && {_scale > 0.0005} && {_scale <= 1}) then {
    private _kept = uiNamespace getVariable ["COMSPEC_ATAK_MapScale", 0.08];
    if (abs (_scale - _kept) > (_kept * 0.02)) then { uiNamespace setVariable ["COMSPEC_ATAK_MapScale", _scale]; };
};
// Guerre électronique et GPS naturel (fn_gpsFix) : position affichée avec l'erreur GPS, pistes BFT dégradées.
([] call comspec_atak_native_fnc_ewEffects) params ["", "_ewBft", "", "", "", "_ewOff"];
// Ma position affichée, une seule par image et posée avant tout dessin : suivi, icône, cône, anneaux, cercle de précision,
// cercles et traits du maillage, trait vers le curseur (avant : fn_meshDraw la lisait avant sa mise à jour, ou lisait la vraie).
private _selfGps = [] call comspec_atak_native_fnc_gpsPos;
_selfGps set [2, 0];
uiNamespace setVariable ["COMSPEC_ATAK_SelfGpsPos", _selfGps];
// Boussole fluide : aiguille et cadran suivent le cap à chaque image (fn_mapOverlayUpdate, boussole seulement).
[false, true] call comspec_atak_native_fnc_mapOverlayUpdate;
// Téléphone porté (ou « Suivre ma position ») : la carte suit le joueur à chaque image, sans à-coup.
// fn_mapCenter pose le premier centrage et son décalage (comspec_lastAim) ; ensuite on glisse du même écart que le joueur.
// Suivi d'une autre unité (fiche de la carte, en main) : même glissement, vers sa position déjà connue des pistes.
private _fwPos = _selfGps;
private _fwId = _state getOrDefault ["mapFollowId", ""];
if (_fwId isNotEqualTo "") then {
    private _fwP = ((_data getOrDefault ["units", createHashMap]) getOrDefault [_fwId, createHashMap]) getOrDefault ["position", []];
    if ((count _fwP) < 2 || {!(_state getOrDefault ["interactive", false])} || {_state getOrDefault ["mapFollow", false]}) then { _state set ["mapFollowId", ""]; _fwId = ""; } else { _fwPos = _fwP; };
};
if (!(_state getOrDefault ["interactive", false]) || {_state getOrDefault ["mapFollow", false]} || {_fwId isNotEqualTo ""}) then {
    private _fwLast = _map getVariable ["comspec_lastAim", []];
    if ((count _fwLast) isEqualTo 2 && {ctrlMapAnimDone _map} && {diag_tickTime - (_map getVariable ["comspec_centerAt", -1]) > 0.6}) then {
        (ctrlPosition _map) params ["_fwX", "_fwY", "_fwW", "_fwH"];
        private _fwVis = _map ctrlMapScreenToWorld [_fwX + _fwW / 2, _fwY + _fwH / 2];
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
// Volet CONFIGURATION de la carte (Étiquettes) : contacts ENI et numéros de grille masquables, comme les indicatifs.
private _eniOn = profileNamespace getVariable ["COMSPEC_ATAK_LblEni", true];
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
// Étiquettes (cartouches, noms des repères) : jamais réduites en vue moyenne ou large (avant : jusqu'à 0,88, texte
// illisible à mi-zoom) ; un peu plus grandes en zoom rapproché, par paliers de 5 % (taille stable pendant le zoom).
private _tsL = (round (_ts * (((1.06 - _scale * 0.7) max 1) min 1.06) * 20)) / 20;
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
// Couleurs de la carte (fn_mapColors) : une table pour tout le dessin (affiliations, alliés et moi choisis dans les
// réglages, états, étiquettes, grille), refaite au changement de fond, de mode nuit ou de réglage.
private _pal = [_bgStyle] call comspec_atak_native_fnc_mapColors;
// Style tactique des marqueurs et traits (Réglages > Carte ; allumé par défaut avec le fond ARDOISE) : pions ronds, plaques
// monospace, zones hachurées, traits à halo, mesure verte. Rien d'autre à calculer quand il est éteint.
private _tac = _pal get "tac";
private _fontU = [COMSPEC_ATAK_FontSet select 1, "EtelkaMonospaceProBold"] select _tac;
if (!_boxOn) then { uiNamespace setVariable ["COMSPEC_ATAK_LblRectsU", []]; };
// Couleurs et icônes de marqueurs (CfgMarkerColors / CfgMarkers) lues une fois par classe : la carte est redessinée
// à chaque image, relire la config et compiler les couleurs à chaque marqueur coûtait cher (téléphone en HUD).
// Rend une copie (l'appelant modifie l'alpha).
private _mkColorCache = uiNamespace getVariable "COMSPEC_ATAK_MkColorCache";
if (isNil "_mkColorCache") then { _mkColorCache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_MkColorCache", _mkColorCache]; };
private _mkRgba = {
    params ["_cls", "_def", ["_type", ""]];
    [_cls, _type, _def] call comspec_atak_native_fnc_markerColor
};
private _mkIconCache = uiNamespace getVariable "COMSPEC_ATAK_MkIconCache";
if (isNil "_mkIconCache") then { _mkIconCache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_MkIconCache", _mkIconCache]; };
private _allyRgb = _pal get "friend";
private _selfRgb = _pal get "self";
private _aStale = _pal get "aStale";
private _aLost = _pal get "aLost";

// Fond PHOTO (capture de la carte : mod, ou fond d'Athena téléchargé en jeu) : tuiles sous tous les symboles ; en
// SATELLITE, la capture du mod remplace la texture du terrain là où elle existe.
if (_bgStyle in ["PHOTO", "SAT"]) then { [_map, _bgStyle] call comspec_atak_native_fnc_photoMap; };
// Fond de carte (Réglages > Carte ou CALQUES) : voile sous tous les symboles (dessiné en premier).
private _veil = switch (_bgStyle) do {
    case "LIGHT": { [1, 1, 1, 0.38] };
    case "DARK": { [0.01, 0.012, 0.011, 0.68] };
    case "NIGHT": { [0.02, 0.03, 0.08, 0.58] };
    // Écran sans carte ardoise (tablette JPC, fn_mapCtrl : carte topo) : voile bleu-gris à la place des couleurs du contrôle.
    case "SLATE": { [[0.06, 0.08, 0.095, 0.72], []] select ((ctrlIDC _map) isEqualTo 88534) };
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
// Téléphone porté (HUD) : pas de toponymes — coût Draw inutile pendant le combat (SQF-C01).
if (_state getOrDefault ["interactive", false]) then {
    [_map, _ts, _kt, _scale] call comspec_atak_native_fnc_mapPlaceText;
};

// Marqueurs de la mission (symboles, zones, traits) : la carte Arma ne les dessine plus (showMarkers = 0).
// Mesures de l'onglet PERFS du Debug (fn_perfAcc) : marqueurs de la mission, repères Athena (halo), zones et routes.
private _perfM = diag_tickTime;
[_map, _mk] call comspec_atak_native_fnc_mapGameMarkers;
["carte marqueurs mission (image)", _perfM] call comspec_atak_native_fnc_perfAcc;

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
    // Style tactique : orange épais à halo léger, flèche à l'arrivée (autant de tronçons que le trait bleu bordé de sombre).
    private _rtH = [[0.05, 0.18, 0.40, 0.9], [1, 0.62, 0.12, 0.28]] select _tac;
    private _rtC = [[0.26, 0.52, 0.96, 1], _pal get "tacOrange"] select _tac;
    private _rtWh = _mpu * ([1.45, 2.4] select _tac);
    private _rtW = _mpu * ([1, 1.25] select _tac);
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
            [_a, _b, _rtWh, _rtH] call _seg;
            [_a, _b, _rtW, _rtC] call _seg;
        };
    };
    if (_tac && {(count _pts) >= 2}) then {
        ["head", _map, [_pts select -1, (_pts select -2) getDir (_pts select -1), _rtC, 18, true, 2, _mpu / 0.0035 * pixelW]] call comspec_atak_native_fnc_planGfx;
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
// Appui maintenu du clic gauche (0,4 s, curseur immobile à l'écran) : pointeur partagé avec mon camp, comme sur la carte du
// jeu, tant que le bouton reste appuyé (fn_mapPing « point… »). Un glissement avant ce délai annule. Mesure sur le curseur réel
// et le point d'appui, en coordonnées d'écran : la dernière position vue par MouseMoving est périmée dès que la carte a bougé
// sans la souris (zoom, suivi, glissement) et annulait l'appui. Rien à calculer hors appui (deux lectures de table).
if (_state getOrDefault ["mapHold", false]) then {
    ["pointTick", _map] call comspec_atak_native_fnc_mapPing;
} else {
    private _holdLc = _state getOrDefault ["mapLc", []];
    if ((count _holdLc) isEqualTo 5 && {_state getOrDefault ["mapLcOk", false]}) then {
        private _hAge = diag_tickTime - (_state getOrDefault ["mapLcAt", diag_tickTime]);
        private _hCur = getMousePosition;
        if ((abs ((_hCur select 0) - (_holdLc select 0))) < pixelW * 8 && {(abs ((_hCur select 1) - (_holdLc select 1))) < pixelH * 8}) then {
            if (_hAge >= 0.4) then {
                _state set ["mapLc", []];
                _state set ["mapHold", true];
                ["pointStart", _map] call comspec_atak_native_fnc_mapPing;
            } else {
                if (_hAge > 0.1) then {
                    private _hs = (10 + 30 * (_hAge / 0.4)) * _is;
                    _map drawIcon ["\A3\ui_f\data\map\markers\military\circle_CA.paa", [1, 0.85, 0.15, 0.4 + 0.5 * (_hAge / 0.4)], _state getOrDefault ["mapLcW", [0, 0, 0]], _hs, _hs, 0, "", 0];
                };
            };
        } else {
            _state set ["mapLcOk", false];
        };
    };
};
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

// « Icônes des unités » : NATO (textures OTAN d'Arma, comme avant), MILSTD (symboles 2525D complets, fn_milSym / fn_milDraw)
// ou DOTS. Le mode est gardé dans le cache de chaque unité (index 6) : changer de réglage refait les symboles.
private _symMode = profileNamespace getVariable ["COMSPEC_ATAK_UnitSymbols", "NATO"];
private _nato = _symMode isEqualTo "NATO";
private _milU = _symMode isEqualTo "MILSTD";
// Thème des symboles MIL-STD (clair, sombre, nuit) d'après le fond de carte : lu par fn_milDraw, posé une fois par image.
uiNamespace setVariable ["COMSPEC_ATAK_MilTheme", ["L", "D", "N"] select ([[0, 1] select (_pal getOrDefault ["dark", false]), 2] select (_bgStyle isEqualTo "NIGHT"))];
// Terminal compromis (fn_compromiseView) : flux BFT coupé, seule ma position GPS reste en direct (pistes figées dessinées à la fin).
private _compSkip = ["skip"] call comspec_atak_native_fnc_compromiseView;
private _tacDisc = "\z\comspec_atak_native\addons\main\data\map_pawn_disc.paa";
private _tacRing = "\z\comspec_atak_native\addons\main\data\map_pawn_ring.paa";
private _tacTick = "\z\comspec_atak_native\addons\main\data\map_pawn_tick.paa";
// Contacts (fn_contactStyle) : taille suivant le zoom (paliers de 5 %), au plus 80 symboles à forme par image (les autres gardent le pion rond), mètres par pixel lus une fois et seulement si une piste périmée est dessinée.
private _ctK = ["scaleK", _scale] call comspec_atak_native_fnc_contactStyle;
private _ctN = 0;
private _ctMpx = 0;
private _dcAll = uiNamespace getVariable "COMSPEC_ATAK_MapDrawCache";
if (isNil "_dcAll") then { _dcAll = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_MapDrawCache", _dcAll]; };
// Unités parties : entrées périmées retirées de temps en temps (pas de table sans fin).
if ((count _dcAll) > (300 max (2 * (count (_data getOrDefault ["units", createHashMap]))))) then { private _tNow = diag_tickTime; { if (_tNow > ((_y select 0) + 5)) then { _dcAll deleteAt _x; }; } forEach +_dcAll; };
// Vue de la carte en coordonnées du monde (marge d'environ 5 % de l'écran) : les unités hors de la vue sont sautées avant
// tout calcul (avant : symbole, couleurs, anneaux et cartouche préparés pour toutes les unités du camp à chaque image,
// même zoomé sur 500 m en téléphone porté).
(ctrlPosition _map) params ["_ucx", "_ucy", "_ucw", "_uch"];
private _uA = _map ctrlMapScreenToWorld [_ucx - 0.05, _ucy - 0.05];
private _uB = _map ctrlMapScreenToWorld [_ucx + _ucw + 0.05, _ucy + _uch + 0.05];
private _ux0 = (_uA select 0) min (_uB select 0); private _ux1 = (_uA select 0) max (_uB select 0);
private _uy0 = (_uA select 1) min (_uB select 1); private _uy1 = (_uA select 1) max (_uB select 1);
// Petite carte (indicatif simple) : un indicatif par case (1/14 de la vue) au dézoom, moi et la sélection toujours ;
// près du bord droit, l'indicatif passe à gauche du symbole (il ne sort plus de la carte).
private _cluW = ((_ux1 - _ux0) / 14) max 1;
private _clu = createHashMap;
// Indicatifs d'une même case : jusqu'à trois, empilés l'un sous l'autre (avant : un seul par case, le second joueur
// restait sans nom). Hauteur d'une ligne en mètres, mesurée une fois par image.
private _lineM = ((((_map ctrlMapScreenToWorld [0.5, 0.5]) distance2D (_map ctrlMapScreenToWorld [0.5, 0.6])) / 0.1) * 0.034 * _kt * _tsL);
private _uxR = _ux1 - (_ux1 - _ux0) * 0.3;
// Mon point GPS (volet de configuration de la carte, fn_mapPrefs « self ») : réglages préparés au changement.
private _selfCfg = ["self"] call comspec_atak_native_fnc_mapPrefs;
private _selfLbl = _selfCfg select 5;
// Formes choisies (fn_mapShape) : préparées au changement de réglage, comparées par unité.
private _shapeSelf = _selfCfg param [6, "ARROW"];
private _shapeAlly = _selfCfg param [7, "AUTO"];
private _shapeSelfNew = ["isNew", _shapeSelf] call comspec_atak_native_fnc_mapShape;
private _shapeAllyNew = ["isNew", _shapeAlly] call comspec_atak_native_fnc_mapShape;
// Symboles MIL-STD : les miens (« MS » déduit, « MS:<pictogramme> » choisi), ceux des alliés (forme MILSTD) ou de toutes
// les unités (Icônes des unités MILSTD). Hampe, échelon et textes compris ; textes seulement en vue rapprochée.
private _msSelf = (_shapeSelf select [0, 2]) isEqualTo "MS";
private _msAlly = _shapeAlly isEqualTo "MILSTD";
private _msText = _scale < 0.06 && {_lblOn};
// Trace (fil d'Ariane) de mes positions, sous les symboles.
if (_selfCfg select 4) then {
    private _tp = ["trailPts"] call comspec_atak_native_fnc_mapPrefs;
    private _tc = +_selfRgb; _tc set [3, 0.55];
    for "_i" from 1 to ((count _tp) - 1) do { _map drawLine [[(_tp select (_i - 1)) select 0, (_tp select (_i - 1)) select 1, 0], [(_tp select _i) select 0, (_tp select _i) select 1, 0], _tc]; };
    if ((count _tp) > 0) then { _map drawLine [[(_tp select -1) select 0, (_tp select -1) select 1, 0], _selfGps, _tc]; };
};
{
    private _entity = _y;
    private _pos = _entity getOrDefault ["position",[]];
    if ((count _pos) < 2) then { continue };
    if (_compSkip && {!(_entity getOrDefault ["self", false])}) then { continue };
    // Hors de la vue (cercle de précision compris) : rien à dessiner. Moi et l'unité sélectionnée toujours traités.
    if (!(_entity getOrDefault ["self", false]) && {_x isNotEqualTo _selected} && {
        private _ua = _entity getOrDefault ["acc", 0];
        ((_pos select 0) + _ua) < _ux0 || {((_pos select 0) - _ua) > _ux1} || {((_pos select 1) + _ua) < _uy0} || {((_pos select 1) - _ua) > _uy1}
    }) then { continue };
    private _self = _entity getOrDefault ["self",false];
    // Téléphone tracé (piste Athena « phone », fn_pollUnits) : toujours le symbole d'émetteur de fn_symbology, non orienté,
    // jamais un cadre OTAN / MIL-STD ni un pion d'infanterie, quel que soit le style de la carte.
    private _phone = _entity getOrDefault ["phone", false];
    // Symbole, couleur de base, icône perso et symbole OTAN gardés 1 s par unité (table à part, COMSPEC_ATAK_MapDrawCache) :
    // refaits à chaque image et pour chaque unité avant. Plus dans la piste : fn_localDataRefresh la refait 4 fois par seconde.
    private _dc = _dcAll getOrDefault [_x, []];
    if ((count _dc) < 10 || {diag_tickTime > (_dc select 0)} || {(_dc select 6) isNotEqualTo _symMode}) then {
        ([_entity] call comspec_atak_native_fnc_symbology) params ["_sIcon","_sColor"];
        private _ci = "";
        private _custom = _entity getOrDefault ["icon",""];
        if (_custom isNotEqualTo "" && {(_entity getOrDefault ["type",""]) isEqualTo "infantry"} && {(_entity getOrDefault ["vtype",""]) isEqualTo ""}) then {
            _ci = _mkIconCache getOrDefault ["~" + _custom, "§"];
            if (_ci isEqualTo "§") then { _ci = getText (configFile >> "CfgMarkers" >> _custom >> "icon"); _mkIconCache set ["~" + _custom, _ci]; };
        };
        private _nt = ""; private _ne = "";
        if (_nato && {!_self}) then { ([_entity] call comspec_atak_native_fnc_natoSymbol) params ["_nt0", "_ne0"]; _nt = _nt0; _ne = _ne0; };
        // Indicatif coupé proprement au-delà de 16 caractères (cartouche et indicatif simple).
        // Lettre du pion (style tactique) : équipe de feu, sinon groupe, sinon indicatif ; gardée avec le reste.
        private _lo = _entity getOrDefault ["object", objNull];
        private _ls = ((_lo getVariable ["COMSPEC_ftDrawCache", []]) param [1, createHashMap]) getOrDefault ["name", ""];
        if (_ls isEqualTo "" && {!isNull _lo}) then { _ls = groupId group _lo; };
        if (_ls isEqualTo "") then { _ls = _entity getOrDefault ["callsign", ""]; };
        // Symbole MIL-STD (index 9) : [descripteur, textes] seulement s'il sert ; mes symboles choisis à part (fn_milSym « self »).
        private _ms = [];
        if (_self && {_msSelf}) then {
            _ms = [["self", _shapeSelf select [3]] call comspec_atak_native_fnc_milSym];
        } else {
            if (!_self && {!_phone} && {_milU || {_msAlly && {(_entity getOrDefault ["affiliation", ""]) isEqualTo "friend"}}}) then { _ms = [["unit", _entity] call comspec_atak_native_fnc_milSym]; };
        };
        if ((count _ms) > 0) then {
            // Désignation (code d'unité de l'ORBAT, sinon groupe Arma) et unité supérieure (ORBAT) : données réelles seulement.
            private _orb = (["orbat"] call comspec_atak_native_fnc_milSym) getOrDefault [_lo, []];
            _ms pushBack [[_orb param [3, ""], groupId group _lo] select ((_orb param [3, ""]) isEqualTo "" && {!isNull _lo}), _orb param [4, ""]];
        };
        _dc = [diag_tickTime + 1, _sIcon, _sColor, _ci, _nt, _ne, _symMode, ["cut", _entity getOrDefault ["callsign", ""], 16] call comspec_atak_native_fnc_mapFmt, toUpper (toString ((toArray _ls) select [0, 1])), _ms];
        _dcAll set [_x, _dc];
    };
    private _icon = _dc select 1;
    private _color = +(_dc select 2);
    private _freshness = _entity getOrDefault ["freshness","LIVE"];
    private _hostile = (_entity getOrDefault ["affiliation", ""]) isEqualTo "hostile";
    private _size = ([16, 21] select (_x isEqualTo _selected || _self)) * _k * _is;
    if ((_entity getOrDefault ["affiliation",""]) isEqualTo "friend") then { _color = +_allyRgb; };
    if (_self) then { _color = +_selfRgb; };
    // Moi : position GPS affichée (vraie position à l'image près + erreur GPS simulée), la même pour l'icône, les anneaux
    // d'équipe et de sélection, le cartouche et le trait de distance (avant : anneaux et trait restaient sur la vraie position).
    if (_self) then { _pos = _selfGps; };
    if (_ewBft && {!_self}) then { _color set [3, ((_color select 3) min 0.65) * 0.5]; };
    private _dir = _entity getOrDefault ["heading",0];
    if ((_dc select 3) isNotEqualTo "" || {_phone}) then { _icon = [_dc select 3, _icon] select _phone; _dir = 0; };
    // Moi : forme, taille et cône de cap choisis (flèche du symbole d'unité par défaut).
    if (_self) then {
        _selfCfg params ["_sIcon", "_sOrient", "_sMul", "_sCone"];
        if (_sIcon isNotEqualTo "") then { _icon = _sIcon; if (!_sOrient) then { _dir = 0; }; };
        _size = _size * _sMul;
        if (_sCone) then {
            private _ed = eyeDirection player;
            private _eh = (_ed select 0) atan2 (_ed select 1);
            private _cl = _size * 3.2 * (["mpx", _map] call comspec_atak_native_fnc_planGfx);
            private _cc = +_color; _cc set [3, 0.22];
            _map drawTriangle [[_pos, _pos getPos [_cl, _eh - 28], _pos getPos [_cl, _eh], _pos, _pos getPos [_cl, _eh], _pos getPos [_cl, _eh + 28]], _cc, "#(rgb,8,8,3)color(1,1,1,1)"];
        };
    };
    // Fraîcheur gardée après la palette ami / moi (qui remet l'alpha à 1) : piste figée ou perdue toujours estompée.
    if (_freshness isEqualTo "STALE") then { _color set [3, (_color select 3) min _aStale]; };
    if (_freshness in ["LOST","OFFLINE"]) then { _color set [3, (_color select 3) min _aLost]; };
    // Réglages > Carte « Icônes des unités » OTAN : cadre + modificateur MIL-STD-2525 / APP-6 (moi : inchangé),
    // non orienté, un peu plus grand que le point ; échelon (points d'équipe / groupe) au-dessus pour les chefs.
    private _ech = "";
    private _tacP = _tac && {!_self} && {!_phone};
    if (_nato && {!_self} && {!_tacP}) then {
        _icon = _dc select 4; _ech = _dc select 5; _dir = 0; _size = _size * 1.3;
    };
    // Précision annoncée par le point de position de l'allié (fn_pli) : cercle discret au-delà de 10 m.
    private _acc = _entity getOrDefault ["acc", 0];
    if (!_self && {_acc >= 10} && {profileNamespace getVariable ["COMSPEC_ATAK_AccRings", false]}) then { _map drawEllipse [_pos, _acc, _acc, 0, [_color select 0, _color select 1, _color select 2, (_color select 3) * 0.45], ""]; };
    if ((count (_dc select 9)) > 0) then {
        (_dc select 9) params ["_msD", "_msT"];
        // Cap : flèche de direction seulement en mouvement (vitesse réelle de l'unité) ; vitesse en texte en vue rapprochée.
        private _mo = _entity getOrDefault ["object", objNull];
        private _spd = if (isNull _mo) then { 0 } else { speed (vehicle _mo) };
        private _txt = if (_msText) then { [_msT select 0, [_msT select 1, format ["%1 km/h", round _spd]] select (_spd > 5 && {(_msT select 1) isEqualTo ""})] } else { [] };
        ["draw", [_map, _msD, _pos, _size * ([2.1, 2.3] select _self), _color select 3, [-1, _entity getOrDefault ["heading", 0]] select (_spd > 5), _txt]] call comspec_atak_native_fnc_milDraw;
    } else {
    if ((_self && {_shapeSelfNew}) || {!_self && {_shapeAllyNew} && {(_entity getOrDefault ["affiliation", ""]) isEqualTo "friend"}}) then {
        ["draw", [_map, [_shapeAlly, _shapeSelf] select _self, _pos, _size * 1.35, _color, _entity getOrDefault ["heading", 0], _dc select 8]] call comspec_atak_native_fnc_mapShape;
    } else {
    if (_tacP && {(_entity getOrDefault ["affiliation", ""]) isNotEqualTo "friend"} && {_ctN < 80}) then {
        // Contact (ennemi, neutre, inconnu, civil) : forme de l'affiliation (losange, carré, carré à bras, triangle) en plus de
        // la couleur ; cap seulement s'il est connu ; âge sous le symbole d'une piste périmée (fn_contactStyle).
        _ctN = _ctN + 1;
        private _ctAge = -1;
        if (_freshness in ["STALE", "LOST", "OFFLINE"]) then {
            _ctAge = diag_tickTime - (_entity getOrDefault ["updated", diag_tickTime]);
            if (_ctMpx isEqualTo 0) then { _ctMpx = ["mpx", _map] call comspec_atak_native_fnc_planGfx; };
        };
        ["draw", [_map, ["aff", _entity] call comspec_atak_native_fnc_contactStyle, _pos, _size * 0.8 * _ctK, _color, [-1, _entity get "heading"] select ("heading" in _entity), _ctAge, [0, 0.019 * _kt * _tsL] select (_scale < 0.12), _ctMpx]] call comspec_atak_native_fnc_contactStyle;
    } else {
    if (_tacP) then {
        // Pion : disque sombre, anneau à la couleur de l'unité, lettre d'équipe, triangle de direction (texture tournée par
        // drawIcon : aucune position à calculer). Textures fixes, 3 à 4 dessins par unité visible.
        private _ps = _size * 1.35;
        private _pa = _color select 3;
        _map drawIcon [_tacDisc, [0.03, 0.045, 0.05, _pa], _pos, _ps, _ps, 0, "", 0];
        _map drawIcon [_tacRing, _color, _pos, _ps, _ps, 0, "", 0];
        if (_ps >= 14) then { _map drawIcon [_clear, [1, 1, 1, _pa], _pos, 0, 0, 0, _dc select 8, 0, _ps * pixelH * 0.6, "EtelkaMonospaceProBold", "center"]; };
        if ("heading" in _entity) then { _map drawIcon [_tacTick, [1, 1, 1, _pa], _pos, _ps * 1.5, _ps * 1.5, _entity get "heading", "", 0]; };
    } else {
        _map drawIcon [_icon,_color,_pos,_size,_size,_dir,"",0];
    };
    };
    };
    };
    // Mon indicatif : AUTO comme les autres unités, ON toujours, OFF jamais (fn_mapPrefs « self »).
    if ((_self && {_selfLbl isEqualTo "ON"}) || {_lblOn && {!_boxOn} && {_scale < 0.25} && {_eniOn || {!_hostile}} && {!_self || {_selfLbl isNotEqualTo "OFF"}}}) then {
        private _cs = _dc select 7;
        private _lrow = 0;
        if (!_self && {_x isNotEqualTo _selected}) then {
            private _ck = [floor ((_pos select 0) / _cluW), floor ((_pos select 1) / _cluW)];
            _lrow = _clu getOrDefault [_ck, 0];
            _clu set [_ck, _lrow + 1];
        };
        if (_cs isNotEqualTo "" && {_lrow < 3}) then { _map drawIcon [_clear,[1,1,1,(_color select 3) max 0.6],[_pos select 0, (_pos select 1) - _lrow * _lineM, 0],_size,_size,0,_cs,2,0.027 * _kt * _tsL,_fontU,["right", "left"] select ((_pos select 0) > _uxR)]; };
    };
    if (_ech isNotEqualTo "" && {(count (_dc select 9)) isEqualTo 0}) then { _map drawIcon [_ech, _color, _pos, _size, _size, 0, "", 0]; };
    // Candidat au cartouche : pas moi (carte « moi »), et pour les autres groupes seulement le chef (un cartouche par
    // groupe, pas un par équipier) ; les unités Athena sans objet et l'unité sélectionnée gardent toujours le leur.
    // Priorité (fn_mapLabels) : sélection 0, mon groupe 1 (et alliés proches, rangés là par fn_mapLabels), ENI 2, autres 3.
    if (_boxOn && {!_self} && {_eniOn || {!_hostile}}) then {
        private _cs = _dc select 7;
        private _uo = _entity getOrDefault ["object", objNull];
        private _mine = !isNull _uo && {group _uo isEqualTo _myGroup};
        if (_cs isNotEqualTo "" && {_x isEqualTo _selected || {isNull _uo} || {_mine} || {leader group _uo isEqualTo _uo}}) then {
            _lblCands pushBack [[[[3, 1] select _mine, 2] select _hostile, 0] select (_x isEqualTo _selected), _x, _pos, toUpper _cs, _hostile, ((_color select 3) max 0.45) min 1, _freshness];
        };
    };
    // Unité sélectionnée (2.9.38) : crochets d'angle fins à la couleur de la sélection (réglages carte) sur un halo très
    // léger, proportionnels à l'icône, qui encadrent le symbole sans le masquer ni cacher l'étiquette ; pulsation discrète.
    // (Avant : gros anneau blanc opaque par-dessus le symbole et l'étiquette.)
    if (_x isEqualTo _selected) then {
        private _px = ((_map ctrlMapScreenToWorld [0.5, 0.5]) distance2D (_map ctrlMapScreenToWorld [0.5 + pixelW * 100, 0.5])) / 100;
        private _h = _size * 0.62 * _px * (1 + 0.06 * (sin (diag_tickTime * 240)));
        private _arm = _h * 0.45;
        private _sc = _pal get "sel";
        private _hc = +(_pal get "selHalo"); _hc set [3, 0.22];
        _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", _hc, _pos, _size * 1.9, _size * 1.9, 0, "", 0];
        {
            _x params ["_sx", "_sy"];
            private _c = [(_pos select 0) + _sx * _h, (_pos select 1) + _sy * _h, 0];
            _map drawLine [_c, [(_c select 0) - _sx * _arm, _c select 1, 0], _sc];
            _map drawLine [_c, [_c select 0, (_c select 1) - _sy * _arm, 0], _sc];
            _map drawLine [[(_c select 0), (_c select 1) - _sy * _px, 0], [(_c select 0) - _sx * _arm, (_c select 1) - _sy * _px, 0], _sc];
            _map drawLine [[(_c select 0) - _sx * _px, _c select 1, 0], [(_c select 0) - _sx * _px, (_c select 1) - _sy * _arm, 0], _sc];
        } forEach [[1, 1], [-1, 1], [1, -1], [-1, -1]];
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
            private _rs = _size * ([1.55, 1.9] select _tacP);
            _map drawIcon ["\A3\ui_f\data\map\markers\military\circle_CA.paa", _rc, _pos, _rs, _rs, 0, "", 0];
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
        if ((_y getOrDefault ["text",""]) isNotEqualTo "" && {((_y getOrDefault ["id",_x]) find "/77000") < 0} && {!_lc || {((_y getOrDefault ["id",_x]) find "_USER_DEFINED") >= 0}} && {!((toUpper (_y getOrDefault ["shape","ICON"])) in ["POLYLINE","RECTANGLE","ELLIPSE"])}) then { _tag pushBack [_x,_y]; };
    } forEach (_data getOrDefault ["markers",createHashMap]);
    // Sécurité 10 s (avant 2 s) : la révision du magasin change déjà à chaque marqueur créé, modifié ou supprimé.
    _mkLists = [_mkRev, diag_tickTime + 10, _rem, _tag];
    missionNamespace setVariable ["COMSPEC_ATAK_DrawMkLists", _mkLists];
};
_perfM = diag_tickTime;
{
    private _marker = _x select 1;
    if (_marker getOrDefault ["local",false]) then { continue };
    private _pos = _marker getOrDefault ["position",[]];
    if ((count _pos) < 2) then { continue };
    private _shape = toUpper (_marker getOrDefault ["shape","ICON"]);
    private _size = _marker getOrDefault ["size",[1,1]];
    // Repère Athena hors de la vue (zone comprise) : sauté. Rayon a + b : couvre aussi un rectangle tourné (coin à √(a²+b²)).
    private _mr = if (_size isEqualType []) then { ((_size param [0, 1]) + (_size param [1, 1])) max 1 } else { 1 };
    if (((_pos select 0) + _mr) < _ux0 || {((_pos select 0) - _mr) > _ux1} || {((_pos select 1) + _mr) < _uy0} || {((_pos select 1) - _mr) > _uy1}) then { continue };
    switch _shape do {
        case "RECTANGLE": {
            if (_tac) then { _map drawRectangle [_pos,_size select 0,_size select 1,_marker getOrDefault ["dir",0],[0.36,0.78,0.42,0.65],_pal get "hatch"]; };
            _map drawRectangle [_pos,_size select 0,_size select 1,_marker getOrDefault ["dir",0],[0.36,0.78,0.42,0.65],""];
        };
        case "ELLIPSE": {
            if (_tac) then { _map drawEllipse [_pos,_size select 0,_size select 1,_marker getOrDefault ["dir",0],[0.36,0.78,0.42,0.65],_pal get "hatch"]; };
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
            // Couleur Arma exacte du repère, pleine (fn_mapColors « lift » : seules les couleurs trop sombres passent à
            // leur version vive sur carte sombre) ; halo dessous pour la lisibilité (2.9.40).
            // Couleur et halo gardés par classe et par fond clair / sombre (avant : recalculés à chaque image et par repère).
            private _mcls = _marker getOrDefault ["color","ColorGreen"];
            private _hk = _mcls + "|" + _type + (["|L", "|D"] select (_pal getOrDefault ["dark", false]));
            private _mch = _mkColorCache getOrDefault [_hk, []];
            if (_mch isEqualTo []) then {
                private _rg = +([[_mcls, [0.36,0.78,0.42,1], _type] call _mkRgba, _mcls] call (_pal get "lift"));
                _mch = [_rg, [_pal get "halo", _pal get "haloLight"] select (((_rg select 0) * 0.3 + (_rg select 1) * 0.59 + (_rg select 2) * 0.11) < 0.25)];
                _mkColorCache set [_hk, _mch];
            };
            private _rgba = +(_mch select 0);
            _rgba set [3, [0, 1] select ((_marker getOrDefault ["alpha",1]) > 0.02)];
            private _isz = 16 * _k * _is;
            private _dirM = _marker getOrDefault ["dir",0];
            _map drawIcon [_icon,_mch select 1,_pos,_isz * 1.3,_isz * 1.3,_dirM,"",0];
            // Nom du repère : écrit par fn_mapMarkerText, comme ceux des marqueurs du jeu.
            _map drawIcon [_icon,_rgba,_pos,_isz,_isz,_dirM,"",0];
        };
    };
} forEach (_mkLists select 2);
["carte repères Athena (image)", _perfM] call comspec_atak_native_fnc_perfAcc;

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
private _yellow = [[1,0.92,0.15,0.95], _pal get "tacGreen"] select _tac;
// Distance (m / km), azimut sur 3 chiffres, dénivelé et pente : même format pour la mesure A-B et le trait vers le curseur.
private _measTxt = {
    params ["_a", "_b"];
    private _dz = round ((getTerrainHeightASL _b) - (getTerrainHeightASL [_a select 0, _a select 1]));
    private _dd = _a distance2D _b;
    private _az = _a getDir _b;
    // Point cardinal hors de fn_tr (fn_cardinal) : « 254 m · 129° SE ».
    format ["%1 %5  %2%3 m (%4 %%)", ["dist", _dd, _az] call comspec_atak_native_fnc_mapFmt, ["","+"] select (_dz > 0), _dz, round (100 * _dz / (_dd max 1)), [_az, 8] call comspec_atak_native_fnc_cardinal]
};
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
    // Table des couleurs par type, créée une fois (avant : recréée à chaque image).
    if (isNil "COMSPEC_ATAK_DZColors") then { COMSPEC_ATAK_DZColors = createHashMapFromArray [["LZ",[0.36,0.85,0.42,1]],["DZ",[0.36,0.85,0.42,1]],["EXTRACT_POINT",[0.36,0.85,0.42,1]],["RALLY_POINT",[0.28,0.70,1,1]],["SAFE_ZONE",[0.28,0.70,1,1]],["OBJECTIVE",[0.95,0.67,0.20,1]],["DANGER_ZONE",[0.95,0.22,0.18,1]],["NO_GO_AREA",[0.95,0.22,0.18,1]],["RESTRICTED_AREA",[0.95,0.5,0.15,1]]]; };
    private _zc = COMSPEC_ATAK_DZColors;
    private _dzTex = ["#(rgb,8,8,3)color(1,1,1,1)", _pal get "hatch"] select _tac;
    private _dzA = [0.12, 0.65] select _tac;
    {
        _x params [["_id",""],["_geom","CIRCLE"],["_geo",[]],["_r",0],["_type",""],["_threat",""],["_label",""]];
        private _c = +(_zc getOrDefault [toUpper _type,[0.95,0.67,0.20,1]]);
        if ((toUpper _geom) in ["CIRCLE","RECTANGLE"] && {(count _geo) >= 2}) then {
            private _p = [_geo select 0,_geo select 1,0];
            _map drawEllipse [_p,_r max 20,_r max 20,0,_c,""];
            _map drawEllipse [_p,_r max 20,_r max 20,0,[_c select 0,_c select 1,_c select 2,_dzA],_dzTex];
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
// File de séquence lue une fois par image (clé -> première échéance) : avant, refiltrée pour chaque charge.
private _exQ = createHashMap;
{ private _qk = (_x select 1) get "key"; if !(_qk in _exQ) then { _exQ set [_qk, _x select 0]; }; } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_Explo", createHashMap]) getOrDefault ["queue", []]);
{
    private _e = _x get "obj";
    // Charge détonée ou supprimée dans la fenêtre du cache de fn_exploList (0,5 s) : pas d'icône à l'origine de la carte.
    if (isNull _e) then { continue };
    private _c = [[0.95,0.67,0.20,1], [0.90,0.78,0.29,1], [0.90,0.28,0.23,1], [0.79,0.83,0.81,1]] select ((["atak","command","timer","mine"] find (_x get "kind")) max 0);
    private _txt = format ["C%1", _forEachIndex + 1];
    if ((_x get "remaining") >= 0) then { _txt = format ["%1 · %2 s", _txt, ceil (_x get "remaining")]; };
    private _qt = _exQ getOrDefault [_x get "key", -1];
    if (_qt isNotEqualTo -1) then { _txt = format ["%1 · T-%2", _txt, ((_qt - diag_tickTime) max 0) toFixed 1]; _c = [0.90,0.28,0.23,1]; };
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
    _map drawIcon [_dot,_yellow,_c,10,10,0,[_me, _c] call _measTxt,2,0.03 * _ts,(COMSPEC_ATAK_FontSet select 1),["right", "left"] select ((_c select 0) > _uxR)];
};

// Point de pose choisi d'un clic droit en mode MARQUEUR (fn_markerPlace) : aperçu du type choisi dans un anneau qui pulse.
private _anchor = _state getOrDefault ["markerAnchor", []];
if ((count _anchor) >= 2 && {(_state getOrDefault ["mapMode","SELECT"]) isEqualTo "MARKER"}) then {
    private _ap = [_anchor select 0, _anchor select 1, 0];
    private _pulse = 0.55 + 0.45 * (abs sin (diag_tickTime * 180));
    private _ring = [0.36, 0.78, 0.42, _pulse];
    _map drawIcon ["\A3\ui_f\data\map\markers\military\circle_CA.paa", _ring, _ap, 34 * _is, 34 * _is, 0, "", 0];
    // Icône lue une fois par type (cache « ~ » des icônes : texte brut de CfgMarkers), pas à chaque image.
    private _apT = "~" + (_state getOrDefault ["markerType", "mil_dot"]);
    private _ic = _mkIconCache getOrDefault [_apT, "§"];
    if (_ic isEqualTo "§") then { _ic = getText (configFile >> "CfgMarkers" >> (_apT select [1]) >> "icon"); _mkIconCache set [_apT, _ic]; };
    if (_ic isNotEqualTo "") then { _map drawIcon [_ic, [1, 1, 1, 0.75], _ap, 20 * _is, 20 * _is, 0, "", 0]; };
    _map drawIcon [_dot, [0.36, 0.78, 0.42, 1], _ap, 6, 6, 0, "  " + (("POINT DE POSE · VALIDER") call comspec_atak_native_fnc_tr), 2, 0.026 * _ts, (COMSPEC_ATAK_FontSet select 1), "right"];
};

// App Zones : plans affichés (bascule « Zones sur la carte ATAK »), dans la vue seulement, sous les routes (fn_zoneDraw).
if (profileNamespace getVariable ["COMSPEC_ATAK_ZonesShow", true]) then { _perfM = diag_tickTime; [_map, _k * _is, _ts] call comspec_atak_native_fnc_zoneDraw; ["carte zones (image)", _perfM] call comspec_atak_native_fnc_perfAcc; };
// Points de passage : tronçons, étapes nommées et typées, point choisi en mode POINTS DE PASSAGE (fn_wpDraw).
[_map, _k * _is, _ts, _cursor] call comspec_atak_native_fnc_wpDraw;
// App Routes : routes affichées, dans la vue seulement, tracé gardé par révision (fn_routeDraw).
_perfM = diag_tickTime;
[_map, _k * _is, _ts] call comspec_atak_native_fnc_routeDraw;
["carte routes (image)", _perfM] call comspec_atak_native_fnc_perfAcc;
// Ordres aux groupes IA du camp en cours (et finis depuis moins d'une minute) : trait du chef à l'objectif (fn_aiOrder).
["draw", [_map, 0.026 * _ts, COMSPEC_ATAK_FontSet select 1]] call comspec_atak_native_fnc_aiOrder;
// Appui feu typé (fn_fireSupport) : portées de la pièce choisie, distance de sécurité, missions de tir du camp.
["draw", [_map, 0.026 * _ts, COMSPEC_ATAK_FontSet select 1]] call comspec_atak_native_fnc_fireSupport;

// Mesure A -> B (ou A -> curseur tant que B n'est pas posé)
private _measure = +(_state getOrDefault ["mapMeasure",[]]);
if ((count _measure) isEqualTo 1 && {(count _cursor) >= 2}) then { _measure pushBack [_cursor select 0,_cursor select 1,0]; };
if ((count _measure) >= 2) then {
    _measure params ["_a","_b"];
    private _mc = [_amber, _pal get "tacGreen"] select _tac;
    if (_tac) then {
        // Style tactique : tirets verts épais, plaque de distance au milieu. Monospace : largeur = caractères x largeur d'un
        // chiffre (mesurée une fois, fn_mapTextW), pas de nouvelle mesure quand le curseur bouge.
        private _mm = ["mpx", _map] call comspec_atak_native_fnc_planGfx;
        ["line", _map, [[_a, _b], _mc, 2.6, true, false, false, _mm]] call comspec_atak_native_fnc_planGfx;
        private _dt = ["dist", _a distance2D _b] call comspec_atak_native_fnc_mapFmt;
        private _fsM = 0.022 * _ts;
        private _hw = ((count _dt) * (["0", "EtelkaMonospaceProBold", 0.02] call comspec_atak_native_fnc_mapTextW) * _fsM / 0.02 / 2 + 4 * pixelW) * (_mm / pixelW);
        private _mid = [((_a select 0) + (_b select 0)) / 2, ((_a select 1) + (_b select 1)) / 2, 0];
        _map drawRectangle [_mid, _hw, _fsM * 0.62 * (_mm / pixelH), 0, _mc, "#(rgb,8,8,3)color(1,1,1,1)"];
        _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)", [0.02, 0.09, 0.05, 1], _mid, 0, 0, 0, _dt, 0, _fsM, "EtelkaMonospaceProBold", "center"];
    } else {
        _map drawLine [_a,_b,_amber];
    };
    _map drawIcon [_dot,_mc,_a,14,14,0,"A",2,0.03 * _ts,_fontU,"right"];
    _map drawIcon [_dot,_mc,_b,14,14,0,[_a, _b] call _measTxt,2,0.03 * _ts,_fontU,["right", "left"] select ((_b select 0) > _uxR)];
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
        // Contacts ENI : déjà dans un cartouche rouge (fn_mapLabels), ou masqués (volet CONFIGURATION).
        if ((_boxOn || {!_eniOn}) && {(((toLower (_m getOrDefault ["type", ""])) select [0, 2]) isEqualTo "o_") || {(toUpper (_title select [0, 4])) in ["ENI ", "ENY "]}}) then { continue };
        private _rgba = [_m getOrDefault ["color", "ColorBlack"], [0.1, 0.1, 0.1, 1], _m getOrDefault ["type", ""]] call _mkRgba;
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
        private _sig = [_title, _e, _n, _fs, _pal get "lblBg"];
        if ((_c getVariable ["comspec_sig", []]) isNotEqualTo _sig) then {
            _c setVariable ["comspec_sig", _sig];
            _c ctrlSetBackgroundColor (_pal get "lblBg");
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
    if ((count _gridCache) > 2000) then { _gridCache = createHashMap; };
    uiNamespace setVariable ["COMSPEC_ATAK_TagGridCache", _gridCache];
};
for "_i" from _used to ((count _pool) - 1) do { private _c = _pool select _i; if (ctrlShown _c) then { _c ctrlShow false; }; };
uiNamespace setVariable ["COMSPEC_ATAK_MarkerTagPool", _pool select { !isNull _x }];

// Noms des repères (fn_mapMarkerText, style au choix) : la carte Arma n'écrit plus le texte des marqueurs.
// Ceux qui ont déjà leur cartouche E / N (ci-dessus) sont sautés ; masquables dans CALQUES (Noms des repères).
[_map, _tsL, _kt, _scale, _tagged, _boxOn] call comspec_atak_native_fnc_mapMarkerText;

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
if (_boxOn) then { [_map, _lblCands, _tsL, _scale, _lblStyle, 21 * _k * _is] call comspec_atak_native_fnc_mapLabels; };

// Numéros de grille sur les bords haut (abscisses) et gauche (ordonnées), à la taille choisie dans Réglages > Carte.
// La carte Arma les dessine en tout petit (COMSPEC_RscMapAtak : sizeExGrid minuscule) ; ici une étiquette tous les
// 100 m, 200 m, 500 m, 1 km... selon le zoom (10 au plus par bord), à droite / au-dessus de la ligne qu'elle nomme.
private _mp = ctrlPosition _map;
_mp params ["_mx", "_my", "_mw", "_mh"];
private _gTl = _map ctrlMapScreenToWorld [_mx, _my];
private _gBr = _map ctrlMapScreenToWorld [_mx + _mw, _my + _mh];
private _spanX = (_gBr select 0) - (_gTl select 0);
private _spanY = (_gTl select 1) - (_gBr select 1);
if (_spanX > 1 && {_spanY > 1} && {profileNamespace getVariable ["COMSPEC_ATAK_LblGrid", true]}) then {
    private _maxN = [6, 10] select (_k > 0.75);
    private _steps = [100, 200, 500, 1000, 2000, 5000, 10000, 20000, 50000];
    private _si = _steps findIf { ((_spanX max _spanY) / _x) <= _maxN };
    if (_si < 0) then { _si = (count _steps) - 1; };
    private _step = _steps select _si;
    private _gs = 0.019 * _k * _ts;
    private _gc = _pal get "grid";
    private _gsh = _pal get "gridSh";
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
        if ((count _g) >= 6) then { _map drawIcon [_none, _gc, [_gx, _yTop, 0], 0, 0, 0, _g select [0, 3], _gsh, _gs, (COMSPEC_ATAK_FontSet select 1), "right"]; };
    };
    // Bord gauche : texte juste au-dessus de la ligne.
    private _y0 = _offY + (ceil (((_gBr select 1) - _offY) / _step)) * _step;
    for "_gy" from _y0 to (_gTl select 1) step _step do {
        private _g = mapGridPosition [_midX, _gy + ([-1, 1] select _northUp)];
        private _sy = ((_map ctrlMapWorldToScreen [_midX, _gy]) select 1) - _gs * 0.6;
        if ((count _g) >= 6 && {_sy > (_my + _gs * 1.4)}) then {
            _map drawIcon [_none, _gc, _map ctrlMapScreenToWorld [_mx + pixelW * 2, _sy], 0, 0, 0, _g select [3, 3], _gsh, _gs, (COMSPEC_ATAK_FontSet select 1), "right"];
        };
    };
};

// Terminal compromis : couches grisées, bandeau, console d'erreurs et pistes figées, au-dessus de tout (fn_compromiseView).
if (_compSkip) then { ["draw", _map] call comspec_atak_native_fnc_compromiseView; };
// Mode pilote et FIRES court : altitude et cap des appareils alliés, cibles (rien en dehors de cet écran).
if ((_state getOrDefault ["quickView", ""]) isNotEqualTo "" && {(_state getOrDefault ["activePage", ""]) isEqualTo "MAP"}) then { ["draw", _map] call comspec_atak_native_fnc_firesQuick; };
