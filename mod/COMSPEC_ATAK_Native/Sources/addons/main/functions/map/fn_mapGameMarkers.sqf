/*
    Marqueurs de la mission (allMapMarkers) sur la carte du téléphone, dessinés par l'ATAK : la carte Arma ne les dessine
    plus (showMarkers = 0 dans COMSPEC_RscMapAtak). Arma les dessinait à taille fixe en pixels (symboles OTAN de 32 px),
    énormes sur le petit écran du téléphone porté à côté de textes à l'échelle de l'écran. Ici :
      ICON       symbole du marqueur à l'échelle de l'écran de l'ATAK (comme les unités), réglage « taille des symboles » ;
               point simple (…_dot) doublé et éclairci s'il est noir, sinon invisible ;
      RECTANGLE  zone à la brosse du marqueur (Solid, Border, hachures...) ;
      ELLIPSE    idem ;
      POLYLINE   trait d'un pixel (les traits épais ou fléchés sont repassés par fn_strokeDraw).
    Style tactique (fn_mapColors « tac ») : cercle fin autour des symboles nommés (points clés, couleur gardée avec l'entrée) ;
    zones pleines hachurées (brosse du moteur), contour doublé d'un pixel ; les brosses choisies par la mission restent.
    Propriétés relues pour le seul marqueur créé, modifié ou supprimé (relecture complète toutes les 5 min) ; position des symboles lue
    à chaque image (une cible suivie bouge). Les noms sont écrits par fn_mapMarkerText.
    Params : [carte, facteur des symboles (écran x réglage)]
*/
params ["_map", "_mk"];
private _now = diag_tickTime;
// Cache : [prochaine relecture complète, entrées dans l'ordre des marqueurs, index nom -> rang].
// Caches des marqueurs dans missionNamespace (uiNamespace survit à la mission : marqueurs de la partie précédente
// dessinés jusqu'à 30 s après un redémarrage, les icônes à l'origine de la carte).
private _cache = missionNamespace getVariable ["COMSPEC_ATAK_GmList", [-1, [], createHashMap]];
if ((count _cache) < 3 || {!((_cache select 2) isEqualType createHashMap)}) then { _cache = [-1, [], createHashMap]; };
// Marqueurs changés depuis la dernière image (EH MarkerCreated / Deleted / Updated, XEH_postInitClient) : seuls eux sont
// relus. Avant, un seul marqueur déplacé (piste Athena, marqueur suivi d'une mission) faisait relire toutes les propriétés
// de tous les marqueurs, plusieurs fois par seconde : saccade régulière carte ouverte, surtout téléphone porté.
private _dirty = missionNamespace getVariable ["COMSPEC_ATAK_MkDirtyGm", createHashMap];
// Carte sombre (fn_mapColors) : couleurs trop sombres éclaircies (marqueur noir invisible sinon) ; changement de fond =
// relecture complète.
private _pal = [] call comspec_atak_native_fnc_mapColors;
private _lift = _pal get "lift";
private _tac = _pal get "tac";
// Traits en attente : un trait tracé sur la carte du jeu est créé vide puis rempli (markerPolyline), sans nouvel événement
// de marqueur : son entrée restait vide jusqu'à la relecture complète de 5 min. Les traits créés sans points sont revus
// toutes les 0,5 s pendant 20 s (rien à faire quand la liste est vide).
private _pend = missionNamespace getVariable "COMSPEC_ATAK_GmPend";
if (isNil "_pend") then { _pend = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_GmPend", _pend]; };
private _dead = missionNamespace getVariable "COMSPEC_ATAK_GmDead";
if (isNil "_dead") then { _dead = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_GmDead", _dead]; };
private _pendDue = (count _pend) > 0 && {_now > (missionNamespace getVariable ["COMSPEC_ATAK_GmPendAt", 0])};
private _full = _now > (_cache select 0) || {"" in _dirty} || {(_cache param [3, false]) isNotEqualTo (_pal get "dark")} || {(_cache param [4, false]) isNotEqualTo _tac};
if (_full || {(count _dirty) > 0} || {_pendDue}) then {
    missionNamespace setVariable ["COMSPEC_ATAK_MkDirtyGm", createHashMap];
    if (_pendDue) then {
        missionNamespace setVariable ["COMSPEC_ATAK_GmPendAt", _now + 0.5];
        { _dirty set [_x, true]; if (_now > (_pend get _x)) then { _pend deleteAt _x; _dead set [_x, true]; }; } forEach (keys _pend);
    };
    private _cfg = uiNamespace getVariable "COMSPEC_ATAK_GmCfg";
    if (isNil "_cfg") then { _cfg = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_GmCfg", _cfg]; };
    private _rgbOf = {
        params ["_cls", "_type"];
        private _key = "c:" + _cls + "|" + _type;
        private _c = _cfg getOrDefault [_key, []];
        if (_c isEqualTo []) then {
            // « Default » : couleur propre au type de marqueur (fn_markerColor).
            _c = [_cls, _type] call comspec_atak_native_fnc_markerColor;
            _cfg set [_key, _c];
        };
        [+_c, _cls] call _lift
    };
    // Entrée de dessin d'un marqueur ; [] : rien à dessiner (supprimé, transparent, sans image).
    private _entry = {
        // Marqueurs natifs des routes et plans (fn_markerOwned) : le tracé est déjà dessiné par fn_routeDraw / fn_zoneDraw.
        if ((_x find "/77000") > 0 || {(_x find "comspec_shape_prv_") isEqualTo 0}) exitWith { [] };
        private _a = markerAlpha _x;
        if (_a <= 0.02) exitWith { [] };
        private _shape = markerShape _x;
        switch (_shape) do {
            case "ICON": {
                private _type = markerType _x;
                private _ic = _cfg getOrDefault ["i:" + _type, []];
                if (_ic isEqualTo []) then {
                    _ic = [getText (configFile >> "CfgMarkers" >> _type >> "icon"), getNumber (configFile >> "CfgMarkers" >> _type >> "size"), getNumber (configFile >> "CfgMarkers" >> _type >> "shadow")];
                    if ((_ic select 1) <= 0) then { _ic set [1, 32]; };
                    _cfg set ["i:" + _type, _ic];
                };
                if ((_ic select 0) isEqualTo "" || {(toLower _type) isEqualTo "empty"}) exitWith { [] };
                // Icône : couleur pleine (alpha 1, comme la fiche du marqueur) ; halo sombre dessous, clair sous une
                // couleur sombre (marqueur noir sur carte claire), calculé ici une fois par changement du marqueur.
                private _c = [markerColor _x, _type] call _rgbOf;
                _c set [3, 1];
                (markerSize _x) params [["_sw", 1], ["_sh", 1]];
                // Point simple (mil_dot, hd_dot) : le point ne remplit qu'un quart de son image, noir par défaut ; dessiné
                // ainsi il faisait 7 px sombres sur la carte, invisible. Agrandi, et éclairci s'il est trop sombre.
                if ((count _type) > 3 && {((toLower _type) select [(count _type) - 3]) isEqualTo "dot"}) then {
                    _sw = _sw * 2; _sh = _sh * 2;
                    if (((_c select 0) + (_c select 1) + (_c select 2)) < 0.45) then { _c = [0.96, 0.84, 0.25, _c select 3]; };
                };
                private _halo = [_pal get "halo", _pal get "haloLight"] select (((_c select 0) * 0.3 + (_c select 1) * 0.59 + (_c select 2) * 0.11) < 0.25);
                private _keyC = if ((markerText _x) isNotEqualTo "") then { [_c select 0, _c select 1, _c select 2, 0.75] } else { [] };
                // Repère de contact (o_ n_ u_ b_ c_) en style tactique : forme de l'affiliation sous le symbole (fn_contactStyle),
                // couleur de la forme et couleur du symbole gardées avec l'entrée (symbole éclairci s'il se perdrait sur le fond sombre).
                private _aff = ["markerAff", _type] call comspec_atak_native_fnc_contactStyle;
                private _cFr = [];
                private _cIc = _c;
                if (_tac && {_aff isNotEqualTo ""}) then {
                    _cFr = ["rgb", _aff] call comspec_atak_native_fnc_contactStyle;
                    if (((_c select 0) * 0.3 + (_c select 1) * 0.59 + (_c select 2) * 0.11) < 0.35) then { _cIc = +_cFr; };
                };
                [0, _x, _ic select 0, _c, (_ic select 1) * (abs _sw), (_ic select 1) * (abs _sh), markerDir _x, [0, 1] select ((_ic select 2) > 0), _halo, _keyC, _aff, _cFr, _cIc]
            };
            case "POLYLINE": {
                private _flat = markerPolyline _x;
                if ((count _flat) < 4) exitWith { if !(_x in _pend || {_x in _dead}) then { _pend set [_x, _now + 20]; }; [] };
                _pend deleteAt _x;
                private _c = [markerColor _x, ""] call _rgbOf;
                _c set [3, (_c select 3) * _a];
                private _pts = [];
                for "_i" from 0 to ((count _flat) - 2) step 2 do { _pts pushBack [_flat select _i, _flat select (_i + 1), 0]; };
                // Boîte du trait [xmin, ymin, xmax, ymax] : trait hors de la vue non dessiné.
                private _xs = _pts apply { _x select 0 };
                private _ys = _pts apply { _x select 1 };
                [1, _x, _pts, _c, [selectMin _xs, selectMin _ys, selectMax _xs, selectMax _ys]]
            };
            case "RECTANGLE";
            case "ELLIPSE": {
                private _brush = markerBrush _x;
                private _br = _cfg getOrDefault ["b:" + _brush, []];
                if (_br isEqualTo []) then {
                    _br = [getText (configFile >> "CfgMarkerBrushes" >> _brush >> "texture"), (getNumber (configFile >> "CfgMarkerBrushes" >> _brush >> "drawBorder")) > 0];
                    _cfg set ["b:" + _brush, _br];
                };
                private _c = [markerColor _x, ""] call _rgbOf;
                _c set [3, (_c select 3) * _a];
                (markerSize _x) params [["_sw", 1], ["_sh", 1]];
                // Boîte de la zone (rayon de la diagonale : couvre toute rotation) : zone hors de la vue non dessinée.
                private _mp = getMarkerPos _x;
                private _rr = sqrt (_sw * _sw + _sh * _sh);
                [[2, 3] select (_shape isEqualTo "ELLIPSE"), _x, _mp, _c, _sw, _sh, markerDir _x, _br select 0, _br select 1,
                    [(_mp select 0) - _rr, (_mp select 1) - _rr, (_mp select 0) + _rr, (_mp select 1) + _rr],
                    // Style tactique : zone pleine hachurée ; brosse de la mission gardée, « Border » reste sans remplissage.
                    switch (true) do {
                        case ((_br select 0) in ["", "#(argb,8,8,3)color(1,1,1,1)"]): { _pal get "hatch" };
                        case ((_br select 0) isEqualTo "#(argb,8,8,3)color(0,0,0,0)"): { "" };
                        default { _br select 0 };
                    }]
            };
            default { [] };
        }
    };
    if (_full) then {
        // Relecture complète : au premier dessin, si trop de marqueurs ont changé d'un coup, et toutes les 5 min par sécurité (les EH marqueurs couvrent le reste).
        _dead = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_GmDead", _dead];
        private _list = [];
        private _index = createHashMap;
        {
            private _e = call _entry;
            if ((count _e) > 0) then { _index set [_x, count _list]; _list pushBack _e; };
        } forEach allMapMarkers;
        _cache = [_now + 300, _list, _index, _pal get "dark", _tac];
    } else {
        // Marqueur changé : son entrée est remplacée à sa place (ordre de dessin gardé), ajoutée s'il est nouveau,
        // vidée s'il a disparu ; la liste est recompactée à la prochaine relecture complète.
        _cache params ["", "_list", "_index"];
        {
            private _e = call _entry;
            private _i = _index getOrDefault [_x, -1];
            if (_i >= 0) then { _list set [_i, _e]; } else {
                if ((count _e) > 0) then { _index set [_x, count _list]; _list pushBack _e; };
            };
        } forEach (keys _dirty);
    };
    missionNamespace setVariable ["COMSPEC_ATAK_GmList", _cache];
};

(ctrlPosition _map) params ["_mx", "_my", "_mw", "_mh"];
private _mg = 0.04;
// Vue en coordonnées du monde [xmin, ymin, xmax, ymax] (marge comprise) : traits et zones qui n'y touchent pas sautés.
private _wa = _map ctrlMapScreenToWorld [_mx - _mg, _my - _mg];
private _wb = _map ctrlMapScreenToWorld [_mx + _mw + _mg, _my + _mh + _mg];
private _view = [(_wa select 0) min (_wb select 0), (_wa select 1) min (_wb select 1), (_wa select 0) max (_wb select 0), (_wa select 1) max (_wb select 1)];
private _off = {
    params ["_bb"];
    (count _bb) > 3 && {(_bb select 2) < (_view select 0) || {(_bb select 0) > (_view select 2)} || {(_bb select 3) < (_view select 1)} || {(_bb select 1) > (_view select 3)}}
};
// Vue éloignée : un symbole par case (1/16 de la vue) avec « +n » pour les autres, au lieu d'un tas de symboles qui se
// recouvrent (ou de rien). Case comptée à chaque image (une opération de table par symbole visible), rien en vue proche.
private _clustOn = (ctrlMapScale _map) >= 0.12;
private _cw = (((_view select 2) - (_view select 0)) / 16) max 1;
private _clu = createHashMap;
private _keyRing = "\z\comspec_atak_native\addons\main\data\map_keyring.paa";
// Étiquettes (fn_mapLbl) : symboles relevés pendant son image de calcul.
private _LF = uiNamespace getVariable ["COMSPEC_ATAK_LblF", createHashMap];
private _lay = _LF getOrDefault ["lay", false];
private _syms = _LF getOrDefault ["syms", []];
(_LF getOrDefault ["s", [0, 0]]) params ["_lmx", "_lmy"];
(_LF getOrDefault ["o", [0, 0]]) params ["_lox", "_loy"];
private _lkx = _LF getOrDefault ["kx", 1];
private _lky = _LF getOrDefault ["ky", 1];
private _frN = 0;
// Un pixel en mètres, une fois par image et seulement en style tactique (contour doublé des zones).
private _wpx = if (_tac) then { (((_view select 2) - (_view select 0)) / (_mw + 2 * _mg)) * pixelW } else { 0 };
{
    if ((count _x) isEqualTo 0) then { continue };
    switch (_x select 0) do {
        case 0: {
            _x params ["", "_m", "_icon", "_c", "_w", "_h", "_dir", "_shadow", ["_halo", []], ["_keyC", []], ["_aff", ""], ["_cFr", []], ["_cIc", []]];
            private _pos = getMarkerPos _m;
            if ((_pos select 0) < (_view select 0) || {(_pos select 0) > (_view select 2)} || {(_pos select 1) < (_view select 1)} || {(_pos select 1) > (_view select 3)}) then { continue };
            if (_clustOn) then {
                private _ck = [floor ((_pos select 0) / _cw), floor ((_pos select 1) / _cw)];
                private _cc = _clu getOrDefault [_ck, []];
                if ((count _cc) > 0) then { _cc set [0, (_cc select 0) + 1]; continue };
                _clu set [_ck, [1, _pos, _c]];
            };
            private _iw = (_w * _mk) max 7; private _ih = (_h * _mk) max 7;
            // Forme d'affiliation sous le symbole (zoom rapproché seulement, 60 formes au plus par image).
            private _framed = _tac && {!_clustOn} && {(count _cFr) > 0} && {_frN < 60};
            if (_framed) then {
                _frN = _frN + 1;
                ["frame", [_map, _aff, _pos, (_iw max _ih) * 0.8, _cFr]] call comspec_atak_native_fnc_contactStyle;
            } else {
                if ((count _halo) > 0) then { _map drawIcon [_icon, _halo, _pos, _iw * 1.3, _ih * 1.3, _dir, "", 0]; };
            };
            _map drawIcon [_icon, [_c, _cIc] select (_framed && {(count _cIc) > 0}), _pos, _iw, _ih, _dir, "", _shadow];
            if (_tac && {!_clustOn} && {!_framed} && {(count _keyC) > 0}) then { private _kr = (_iw max _ih) * 2; _map drawIcon [_keyRing, _keyC, _pos, _kr, _kr, 0, "", 0]; };
            // Symbole à ne pas recouvrir par une étiquette (fn_mapLbl, image de calcul seulement).
            if (_lay) then { _syms pushBack [_lmx + ((_pos select 0) - _lox) / _lkx, _lmy + (_loy - (_pos select 1)) / _lky, (_iw max _ih) * 0.55 * pixelW, (_iw max _ih) * 0.55 * pixelH]; };
        };
        case 1: {
            _x params ["", "", "_pts", "_c", ["_bb", []]];
            if ([_bb] call _off) then { continue };
            for "_i" from 0 to ((count _pts) - 2) do { _map drawLine [_pts select _i, _pts select (_i + 1), _c]; };
        };
        default {
            _x params ["_kind", "", "_pos", "_c", "_w", "_h", "_dir", "_tex", "_border", ["_bb", []], ["_tacTex", ""]];
            if ([_bb] call _off) then { continue };
            if (_tac) then {
                if (_kind isEqualTo 2) then {
                    if (_tacTex isNotEqualTo "") then { _map drawRectangle [_pos, _w, _h, _dir, _c, _tacTex]; };
                    _map drawRectangle [_pos, _w, _h, _dir, _c, ""];
                    _map drawRectangle [_pos, _w + _wpx, _h + _wpx, _dir, _c, ""];
                } else {
                    if (_tacTex isNotEqualTo "") then { _map drawEllipse [_pos, _w, _h, _dir, _c, _tacTex]; };
                    _map drawEllipse [_pos, _w, _h, _dir, _c, ""];
                    _map drawEllipse [_pos, _w + _wpx, _h + _wpx, _dir, _c, ""];
                };
                continue
            };
            if (_kind isEqualTo 2) then {
                _map drawRectangle [_pos, _w, _h, _dir, _c, _tex];
                if (_border && {_tex isNotEqualTo ""}) then { _map drawRectangle [_pos, _w, _h, _dir, _c, ""]; };
            } else {
                _map drawEllipse [_pos, _w, _h, _dir, _c, _tex];
                if (_border && {_tex isNotEqualTo ""}) then { _map drawEllipse [_pos, _w, _h, _dir, _c, ""]; };
            };
        };
    };
} forEach (_cache select 1);
// Relevé de diagnostic toutes les 30 s dans le RPT (retour « les images et zones n'apparaissent plus ») : entrées gardées
// par genre (symbole, trait, rectangle, ellipse) et marqueurs du jeu, pour savoir si le manque vient de la lecture ou du dessin.
if (_now > (missionNamespace getVariable ["COMSPEC_ATAK_GmDiagAt", 0])) then {
    missionNamespace setVariable ["COMSPEC_ATAK_GmDiagAt", _now + 30];
    private _kinds = [0, 0, 0, 0];
    { if ((count _x) > 0) then { _kinds set [_x select 0, (_kinds select (_x select 0)) + 1]; }; } forEach (_cache select 1);
    diag_log format ["[COMSPEC ATAK NATIVE][MAP] marqueurs du jeu : %1 au total, gardés : %2 symboles, %3 traits, %4 rectangles, %5 ellipses ; échelle %6", count allMapMarkers, _kinds select 0, _kinds select 1, _kinds select 2, _kinds select 3, ctrlMapScale _map toFixed 3];
};
// Nombre de symboles regroupés dans chaque case de la vue éloignée.
if (_clustOn) then {
    private _clr = "#(argb,8,8,3)color(0,0,0,0)";
    private _cs = ((_LF getOrDefault ["fs", createHashMap]) getOrDefault ["z", 0.026 * (uiNamespace getVariable ["COMSPEC_ATAK_MapTs", 1.25])]) * (_LF getOrDefault ["tk", 1]);
    {
        if ((_y select 0) > 1) then { _map drawIcon [_clr, [1, 1, 1, 1], _y select 1, 8, 8, 0, format ["+%1", (_y select 0) - 1], 2, _cs, [COMSPEC_ATAK_FontSet select 1, "EtelkaMonospaceProBold"] select _tac, "right"]; };
    } forEach _clu;
};
