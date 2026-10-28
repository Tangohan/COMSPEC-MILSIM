/*
    Capture de toute la carte (ou d'une zone) en tuiles d'images, pour en faire un fond de carte « Plans » :
      "sat"  : vue satellite, objectif vertical (orthophoto), comme Plans ou Google Earth vu de dessus ;
      "3d"   : vue 3D oblique (45°, nord en haut), bâtiments et arbres en relief comme le mode 3D de Plans ;
      "plan" : carte topographique d'Arma (carroyage, routes, courbes de niveau).
    Autant de hauteurs que voulu (20 à 10000 m), une couche par hauteur : plus la hauteur est basse, plus l'image est
    détaillée. tools/map_stitch.py --pyramid en fait un seul fond à niveaux de détail, comme Google Maps (la hauteur la
    plus basse sert là où elle existe, la plus haute ailleurs), pour le site (calque « Photo COMSPEC ») et le fond PHOTO
    du téléphone. Par défaut 300, 800, 1200 et 3500 m.
    Parcours par zones (2.3.7) : la caméra prend une case de la hauteur la plus haute, puis descend sur les cases plus
    basses qui sont dessous, avant de passer à la case haute suivante. Une capture arrêtée laisse des zones finies à
    toutes les hauteurs, et la caméra ne traverse plus la carte d'une hauteur à l'autre.
    Côté PC : build_photo_pack.bat regroupe les captures, assemble le fond et fabrique le pack secondaire
    @COMSPEC_ATAK_Photo (tools/map_stitch.py).
    Chaque tuile est d'abord écrite par le moteur dans le dossier Screenshots du profil Arma, sous un nom qui la décrit :
      cmap_<carte>_<mode>_h<hauteur>_s<pas en m>_w<largeur couverte en m>_o<origine x>-<origine y>_x<colonne>_y<ligne>.png
    (colonne 0 à l'ouest, ligne 0 au sud), puis la DLL (COMSPEC Link 2.0.91, MapCap.*) la vérifie, la corrige (voile de
    l'air retiré, couleurs ; même réglage pour toutes les tuiles d'une hauteur), la compresse en JPEG (1 à 2 Mo au lieu
    de 20 Mo) et la range dans Documents\COMSPEC_MapCapture\<carte>\<rendu>_h<hauteur>\. Une case ne compte comme prise
    que si son image existe vraiment : avant, le moteur cessait d'écrire une fois le dossier Screenshots plein
    (maxScreenShotFolderSizeMB) sans rien dire, et le relevé comptait 3945 cases prises pour 175 images (TTRD, 19/10).
    Cinq images de suite sans fichier, ou moins de 3 Go libres : la capture s'arrête et dit pourquoi.
    Vues basses autour des localités : sous la hauteur choisie (200 m dans le formulaire), seules les cases proches d'une
    capitale (900 m), d'une ville (600 m), d'un village (300 m) ou d'un aéroport (1000 m) sont prises : les toits,
    véhicules et murets y gagnent ; ailleurs, la photo du sol d'Arma (1 à 2 m par pixel) n'a rien de plus à montrer
    que la vue de 300 m (Altis entière à 120 m : 25 000 images, 20 h ; autour des localités : environ 1500).
    Sans la DLL : images laissées dans Screenshots, comptées prises sans vérification (comme avant).
    Vue satellite : objectif étroit (téléobjectif) monté plus haut que la hauteur demandée (6 fois, 4000 m au plus :
    plus haut, le sol perd ses détails dans le voile et les textures lointaines), pour une image presque orthogonale.
    Le cadrage réel est mesuré à l'écran (worldToScreen) et noté dans le journal (RPT), avec les altitudes du terrain
    sous chaque image :
      CMAPL|carte|mode|hauteur demandée|taille carte|altitude caméra|fov|ax|ay|u0|v0|pas|largeur|hauteur|x0|y0|nx|ny|grille|portée
      CMAPS|fichier|x|y|altitude caméra|altitudes (grille × grille, du sud-ouest, lignes vers le nord)
      CMAPG|fichier|x|y|z caméra|fov|x,y,z,u,v;… (vue oblique : 13 × 13 points du sol et leur position à l'écran × 10000)
    map_stitch.py s'en sert pour redresser chaque image sur le relief (orthophoto vraie).
    Suivi du relevé (profil du joueur, COMSPEC_ATAK_MapCapProg) : pour chaque carte, rendu et hauteur, le pas de la
    grille et les cases prises. Relu sur le disque (images réellement présentes) à l'ouverture du formulaire et au
    lancement. Un relevé repris saute ces cases (et les cases entièrement en mer).
    Pendant la capture : état en bas de l'écran (hauteur, image, part faite, temps restant, images rangées, disque
    libre), effacé le temps de chaque photo ; vue lointaine, objets lointains et herbe au maximum ; ciel dégagé à midi
    (pluie et nuages remis à zéro dès qu'ils reviennent) et unités / véhicules cachés en option (vue locale
    seulement) ; tout est remis après. Lancer en solo ou dans l'éditeur.
    Params :
      ["start", [mode ("sat" | "3d" | "plan"), hauteurs (m, ex. [300, 800]), centre ([] : carte entière),
                 rayon (m, avec un centre), recouvrement (0 à 0.5, défaut 0.15), attente par tuile (s, défaut 2),
                 ciel dégagé à midi (défaut vrai), cacher unités et véhicules (défaut vrai),
                 reprendre le relevé (défaut vrai), passer la mer (défaut vrai), garder les PNG d'origine (défaut faux),
                 hauteurs prises autour des localités seulement : en dessous de (m, défaut 0 : partout),
                 autour des villes et aéroports seulement (sans les villages) : en dessous de (m, défaut 0)]]
      ["stop"]   arrête la capture en cours
      ["count", mêmes arguments que start] → [nombre de tuiles à prendre, pas en m] (estimation, sans rien capturer)
      ["progress"] → texte du suivi du relevé de la carte en cours (avec l'état de la capture en cours)
      ["sync"]   relit le relevé sur le disque (DLL) → vrai si relu
      ["reset"]  oublie le suivi du relevé de la carte en cours
      ["zeus"]   formulaire du module Zeus (ZEN) « Capture de la carte »
    Exemple (console de débogage) : ["start", ["sat", [300, 800, 1200, 3500], [], 0]] call comspec_atak_native_fnc_mapCapture;
*/
params [["_act", "start"], ["_arg", []]];
if (!hasInterface) exitWith { false };
// Emprise d'une image vue de la hauteur _h (mètres au sol, largeur × hauteur) : mesurée à l'écran au premier passage.
private _defaultFoot = {
    params ["_mode", "_h"];
    switch (_mode) do {
        case "plan": { [_h * 2, _h * 2 * safeZoneH / safeZoneW] };
        case "3d": { [_h * 1.4, _h * 1.4 * safeZoneH / safeZoneW] };
        default { [_h * 1.1, _h * 1.1 * safeZoneH / safeZoneW] };
    }
};
private _grid = {
    params ["_center", "_radius", "_step"];
    private _x0 = 0; private _y0 = 0; private _x1 = worldSize; private _y1 = worldSize;
    if (_center isNotEqualTo []) then {
        _x0 = ((_center select 0) - _radius) max 0; _y0 = ((_center select 1) - _radius) max 0;
        _x1 = ((_center select 0) + _radius) min worldSize; _y1 = ((_center select 1) + _radius) min worldSize;
    };
    // Vue satellite : grille calée sur des multiples du pas, pour que deux captures d'une même hauteur se raccordent.
    _x0 = (floor (_x0 / _step)) * _step; _y0 = (floor (_y0 / _step)) * _step;
    private _nx = ceil ((_x1 - _x0) / _step) max 1;
    private _ny = ceil ((_y1 - _y0) / _step) max 1;
    [_x0, _y0, _nx, _ny]
};
// Case entièrement en mer (5 × 5 points sur la case agrandie du recouvrement) : rien à photographier.
private _isSea = {
    params ["_px", "_py", "_span"];
    private _land = false;
    for "_j" from -2 to 2 do {
        for "_i" from -2 to 2 do {
            if (!_land && {!(surfaceIsWater [_px + _i * _span / 4, _py + _j * _span / 4])}) then { _land = true; };
        };
    };
    !_land
};
// Suivi du relevé, dans le profil du joueur : « carte|rendu|hauteur » -> [pas, cases prises, cases hors mer de la
// carte entière, date]. Une case = colonne × 10000 + ligne dans la grille calée sur les multiples du pas, la même pour
// toutes les captures d'une hauteur.
private _progAll = { createHashMapFromArray (profileNamespace getVariable ["COMSPEC_ATAK_MapCapProg", []]) };
private _progKey = { params ["_mode", "_h"]; format ["%1|%2|%3", toLower worldName, _mode, round _h] };
private _progPut = {
    params ["_key", "_rec"];
    private _all = call _progAll;
    _all set [_key, _rec];
    profileNamespace setVariable ["COMSPEC_ATAK_MapCapProg", (keys _all) apply { [_x, _all get _x] }];
};
// Date du relevé. Fonctions passées au fil de la capture : elles ne s'appuient sur aucune variable de ce fichier.
private _stamp = {
    private _p2 = { if (_this < 10) then { "0" + str _this } else { str _this } };
    systemTime params ["_yy", "_mo", "_dd", "_hh", "_mi"];
    format ["%1-%2-%3 %4:%5", _yy, _mo call _p2, _dd call _p2, _hh call _p2, _mi call _p2]
};
// Cases hors mer d'une grille (carte entière, ou zone) : comptées une à une jusqu'à 20000 cases, au-delà estimées sur
// 20000 cases tirées au hasard (une grille de 50 m sur Altis en compte plus de 400 000 : le jeu gèlerait).
private _landIn = {
    params ["_x0", "_y0", "_nx", "_ny", "_step"];
    private _cells = _nx * _ny;
    if (_cells <= 20000) exitWith {
        private _n = 0;
        for "_iy" from 0 to (_ny - 1) do {
            for "_ix" from 0 to (_nx - 1) do {
                if !([_x0 + (_ix + 0.5) * _step, _y0 + (_iy + 0.5) * _step, _step * 1.2] call _isSea) then { _n = _n + 1; };
            };
        };
        _n
    };
    private _n = 0;
    for "_k" from 1 to 20000 do {
        if !([_x0 + ((floor random _nx) + 0.5) * _step, _y0 + ((floor random _ny) + 0.5) * _step, _step * 1.2] call _isSea) then { _n = _n + 1; };
    };
    round (_cells * _n / 20000)
};
private _landCount = {
    params ["_step"];
    ((([[], 0, _step] call _grid) + [_step]) call _landIn) max 1
};
// Cases proches d'une localité (vues basses) : HashMap colonne × 10000 + ligne de la grille -> vrai. Rayon selon le type,
// ou la taille de la localité si elle est plus grande.
private _townCells = {
    params ["_x0", "_y0", "_nx", "_ny", "_step", ["_villages", true]];
    private _cells = createHashMap;
    private _radii = createHashMapFromArray [["NameCityCapital", 900], ["NameCity", 600], ["NameVillage", 300], ["Airport", 1000]];
    // Vues très basses : villes, capitale et aéroports seulement (sans les villages).
    if (!_villages) then { _radii deleteAt "NameVillage"; };
    {
        private _p = locationPosition _x;
        private _r = (_radii getOrDefault [type _x, 300]) max (((size _x) select 0) max ((size _x) select 1));
        for "_iy" from (floor (((_p select 1) - _r - _y0) / _step) max 0) to (ceil (((_p select 1) + _r - _y0) / _step) min (_ny - 1)) do {
            for "_ix" from (floor (((_p select 0) - _r - _x0) / _step) max 0) to (ceil (((_p select 0) + _r - _x0) / _step) min (_nx - 1)) do {
                if (([_x0 + (_ix + 0.5) * _step, _y0 + (_iy + 0.5) * _step] distance2D _p) <= _r + _step / 2) then { _cells set [_ix * 10000 + _iy, true]; };
            };
        };
    } forEach (nearestLocations [[worldSize / 2, worldSize / 2], keys _radii, worldSize]);
    _cells
};
// DLL de rangement des tuiles (COMSPEC Link 2.0.91) : présente si MapCap.Dir répond.
private _dllDir = {
    private _r = ["MapCap.Dir"] call comspec_atak_native_fnc_extensionCall;
    if ((_r select [0, 3]) isEqualTo "OK|") then { _r select [3] } else { "" }
};
private _shotDir = {
    private _r = ["MapCap.Dir"] call comspec_atak_native_fnc_extensionCall;
    private _d = if ((_r select [0, 3]) isEqualTo "OK|") then { _r select [3] } else { "" };
    if (_d isNotEqualTo "") exitWith { _d + "\" + worldName };
    format ["Documents\Arma 3\Screenshots (profil par défaut) ou Documents\Arma 3 - Other Profiles\%1\Screenshots", profileName]
};
// Relevé relu sur le disque : images réellement présentes (dossier de capture et Screenshots, celles de Screenshots
// rangées au passage). Remplace les cases notées : une case notée sans image n'est plus comptée prise.
private _sync = {
    private _r = ["MapCap.Scan", [worldName]] call comspec_atak_native_fnc_extensionCall;
    if ((_r select [0, 3]) isNotEqualTo "OK|") exitWith { false };
    private _found = createHashMap;
    for "_i" from 0 to ((parseNumber (_r select [3])) - 1) do {
        private _c = ["MapCap.Chunk", [str _i]] call comspec_atak_native_fnc_extensionCall;
        if ((_c select [0, 3]) isEqualTo "OK|") then {
            {
                (_x splitString "|") params [["_m", ""], ["_h", ""], ["_s", ""], ["_ids", ""]];
                if (_m isNotEqualTo "") then {
                    private _k = format ["%1|%2|%3", toLower worldName, _m, _h];
                    private _bySteps = _found getOrDefault [_k, createHashMap, true];
                    private _l = _bySteps getOrDefault [parseNumber _s, [], true];
                    _l append ((_ids splitString ",") apply { parseNumber _x });
                };
            } forEach ((_c select [3]) splitString endl);
        };
    };
    private _all = call _progAll;
    {
        if ((_x find ((toLower worldName) + "|")) isEqualTo 0) then {
            private _rec = _y;
            if !(_x in _found) then { _all set [_x, [_rec select 0, [], _rec select 2, _rec select 3]]; };
        };
    } forEach +_all;
    {
        private _k = _x;
        // Plusieurs pas pour une hauteur (mesures de séances différentes) : celui qui a le plus d'images.
        private _best = -1; private _ids = [];
        { if ((count _y) > (count _ids)) then { _best = _x; _ids = _y; }; } forEach _y;
        private _rec = _all getOrDefault [_k, []];
        private _land = if (_rec isNotEqualTo [] && {(_rec select 0) isEqualTo _best} && {(_rec select 2) > 0}) then { _rec select 2 } else { [_best] call _landCount };
        _all set [_k, [_best, _ids arrayIntersect _ids, _land, if (_rec isEqualTo []) then { call _stamp } else { _rec select 3 }]];
    } forEach _found;
    profileNamespace setVariable ["COMSPEC_ATAK_MapCapProg", (keys _all) apply { [_x, _all get _x] }];
    [] call comspec_atak_native_fnc_saveProfile;
    true
};
private _progText = {
    private _all = call _progAll;
    private _names = createHashMapFromArray [["sat", "Satellite"], ["3d", "3D oblique"], ["plan", "Plan"]];
    private _rows = [];
    {
        (_x splitString "|") params ["_kw", "_km", "_kh"];
        if (_kw isEqualTo (toLower worldName)) then {
            _y params ["", "_ids", "_total", "_stamp"];
            _rows pushBack [_km, parseNumber _kh, format ["%1 %2 m : %3%4 (%5 / %6 cases hors mer), %7",
                _names getOrDefault [_km, _km], _kh, (floor (100 * (count _ids) / (_total max 1))) min 100, "%", count _ids, _total, _stamp]];
        };
    } forEach _all;
    private _live = missionNamespace getVariable ["COMSPEC_ATAK_MapCapLive", ""];
    if (_rows isEqualTo []) exitWith { [format ["Aucun relevé de %1 pour l'instant.", worldName], _live] select (_live isNotEqualTo "") };
    _rows sort true;
    private _t = (_rows apply { _x select 2 }) joinString endl;
    if (_live isNotEqualTo "") then { _t = "En cours : " + _live + endl + _t; };
    _t
};
// Estimation avant la capture (pas par défaut, ou celui du relevé déjà commencé).
private _estimate = {
    params ["_mode", "_heights", "_center", "_radius", "_overlap", "_resume", "_skipSea", ["_townsBelow", 0], ["_citiesBelow", 0]];
    private _total = 0; private _steps = [];
    private _all = call _progAll;
    {
        ([_mode, _x] call _defaultFoot) params ["_fw", "_fh"];
        private _step = ((round ((_fw min _fh) * (1 - _overlap) / 5)) * 5) max 10;
        private _rec = _all getOrDefault [[_mode, _x] call _progKey, []];
        if (_rec isNotEqualTo []) then { _step = _rec select 0; };
        ([_center, _radius, _step] call _grid) params ["_x0", "_y0", "_nx", "_ny"];
        private _n = switch (true) do {
            case (_x < _citiesBelow): { count ([_x0, _y0, _nx, _ny, _step, false] call _townCells) };
            case (_x < _townsBelow): { count ([_x0, _y0, _nx, _ny, _step] call _townCells) };
            case (_skipSea): { [_x0, _y0, _nx, _ny, _step] call _landIn };
            default { _nx * _ny };
        };
        if (_resume && {_rec isNotEqualTo []}) then {
            // Cases déjà prises dans la zone.
            private _gx0 = round (_x0 / _step); private _gy0 = round (_y0 / _step);
            _n = _n - ({ private _gx = floor (_x / 10000); private _gy = _x mod 10000;
                _gx >= _gx0 && {_gx < _gx0 + _nx} && {_gy >= _gy0} && {_gy < _gy0 + _ny} } count (_rec select 1));
        };
        _total = _total + (_n max 0);
        _steps pushBack _step;
    } forEach _heights;
    [_total, _steps]
};
switch (_act) do {
    case "stop": {
        missionNamespace setVariable ["COMSPEC_ATAK_MapCapStop", true];
        true
    };
    case "count": {
        _arg params [["_mode", "sat"], ["_heights", [400]], ["_center", []], ["_radius", 1000], ["_overlap", 0.15], "", "", "",
            ["_resume", true], ["_skipSea", true], "", ["_townsBelow", 0], ["_citiesBelow", 0]];
        if (_center isEqualType objNull) then { _center = getPosATL _center; };
        [_mode, _heights, _center, _radius, _overlap, _resume, _skipSea, _townsBelow, _citiesBelow] call _estimate
    };
    case "progress": { call _progText };
    case "sync": { call _sync };
    case "reset": {
        private _all = call _progAll;
        { if ((_x find ((toLower worldName) + "|")) isEqualTo 0) then { _all deleteAt _x; }; } forEach (keys _all);
        profileNamespace setVariable ["COMSPEC_ATAK_MapCapProg", (keys _all) apply { [_x, _all get _x] }];
        [] call comspec_atak_native_fnc_saveProfile;
        true
    };
    case "zeus": {
        if (isNil "zen_dialog_fnc_create") exitWith { false };
        if !(missionNamespace getVariable ["COMSPEC_ATAK_MapCapRunning", false]) then { call _sync; };
        ["Capture de la carte", [
            ["COMBO", "Rendu", [["sat", "3d", "plan"], ["Satellite (vue de dessus)", "3D oblique (45°)", "Plan topographique"], 0]],
            ["COMBO", ["Niveaux de zoom", "Une hauteur par niveau de zoom du fond SATELLITE, comme Google Maps : plus bas = vue plus proche et détaillée, plus haut = vue d'ensemble. 120 m : autour des localités seulement si la case ci-dessous est cochée."],
                [[0, 1, 2], ["Standard : 300, 800, 1200, 3500 m", "Avec les villes : 120, 300, 800, 1200, 3500 m", "Villes en détail : 45, 120, 300, 800, 1200, 3500 m", "Personnalisé (hauteurs ci-dessous)"], 1]],
            ["EDIT", ["Hauteurs (m, séparées par des virgules)", "Avec « Personnalisé ». Une couche par hauteur, soit un niveau de zoom du fond, comme Google Maps. Altis entière (images de 1 à 2 Mo) : 3500 m ≈ 35 images, 1200 m ≈ 300, 800 m ≈ 650, 300 m ≈ 4000 images (8 Go, 3 h), 150 m ≈ 16 000 (32 Go, 11 h), 50 m ≈ 140 000 (280 Go) : en dessous de 300 m, plutôt une zone (ville). Estimation affichée au lancement."], "300, 800, 1200, 3500"],
            ["COMBO", "Zone", [[0, 1], ["Autour de la position du module", "Carte entière"], 1]],
            ["SLIDER", "Rayon de la zone (m)", [100, 20000, 1500, 0]],
            ["SLIDER", "Attente par tuile (s)", [0.5, 6, 2, 1]],
            ["CHECKBOX", ["Ciel dégagé à midi", "Soleil haut, ni nuages, ni pluie, ni brume pendant la capture (vue locale, remis après)"], true],
            ["CHECKBOX", ["Cacher unités et véhicules", "Rien de mobile sur les images (vue locale, remis après)"], true],
            ["CHECKBOX", ["Reprendre le relevé", "Saute les cases dont l'image existe déjà (suivi ci-dessous)"], true],
            ["CHECKBOX", ["Passer la mer", "Ne photographie pas les cases entièrement en mer"], true],
            ["CHECKBOX", ["Vues basses autour des localités seulement", "Hauteurs sous 200 m (ex. 120 m) : seulement près des villes, villages, de la capitale et des aéroports ; sous 80 m (ex. 45 m) : villes, capitale et aéroports seulement. Ailleurs, la vue de 300 m montre déjà tout ce que le sol d'Arma a de détails."], true],
            ["CHECKBOX", ["Garder les PNG d'origine", "En plus des JPEG corrigés : environ 20 Mo par image, dans un sous-dossier png"], false],
            ["CHECKBOX", ["Repartir de zéro", "Oublie le suivi de cette carte avant de commencer (les images déjà prises restent sur le disque)"], false],
            ["EDIT:MULTI", ["Suivi du relevé", format ["Part de la carte déjà photographiée, par rendu et hauteur, d'après les images présentes dans %1", call _shotDir]], call _progText]
        ], {
            params ["_values", "_pos"];
            _values params ["_mode", "_preset", "_hs", "_zone", "_radius", "_wait", "_clear", "_hide", "_resume", "_skipSea", "_towns", "_keepPng", "_reset"];
            private _heights = [[300, 800, 1200, 3500], [120, 300, 800, 1200, 3500], [45, 120, 300, 800, 1200, 3500]] param [_preset, []];
            if (_heights isEqualTo []) then { _heights = ((_hs splitString ", ;") apply { parseNumber _x }) select { _x >= 20 && {_x <= 10000} }; };
            if (_heights isEqualTo []) then { _heights = [300, 800, 1200, 3500]; };
            if (_reset) then { ["reset"] call comspec_atak_native_fnc_mapCapture; };
            ["start", [_mode, _heights, [[], _pos] select (_zone isEqualTo 0), _radius, 0.15, _wait, _clear, _hide, _resume && !_reset, _skipSea, _keepPng, [0, 200] select _towns, [0, 80] select _towns]] call comspec_atak_native_fnc_mapCapture;
        }, {}, ASLToAGL _arg] call zen_dialog_fnc_create;
        true
    };
    case "start": {
        if (missionNamespace getVariable ["COMSPEC_ATAK_MapCapRunning", false]) exitWith {
            ["WARNING", "Capture de la carte déjà en cours (Échap pour l'arrêter)", 4, 20] call comspec_atak_native_fnc_notify;
            false
        };
        _arg params [["_mode", "sat"], ["_heights", [400]], ["_center", []], ["_radius", 1000], ["_overlap", 0.15], ["_wait", 2], ["_clear", true], ["_hide", true],
            ["_resume", true], ["_skipSea", true], ["_keepPng", false], ["_townsBelow", 0], ["_citiesBelow", 0]];
        _mode = toLower _mode;
        if !(_mode in ["sat", "3d", "plan"]) then { _mode = "sat"; };
        if (_center isEqualType objNull) then { _center = getPosATL _center; };
        // Hauteurs uniques, de la plus haute à la plus basse (ordre du parcours par zones).
        _heights = (_heights apply { round ((_x max 20) min 10000) });
        _heights = _heights arrayIntersect _heights;
        _heights sort false;
        private _dll = call _dllDir;
        if (_dll isNotEqualTo "") then { call _sync; };
        // Rien à l'écran pendant la capture : estimation annoncée avant, interface Zeus et téléphone fermés.
        ([_mode, _heights, _center, _radius, _overlap, _resume, _skipSea, _townsBelow, _citiesBelow] call _estimate) params ["_total"];
        if (_total isEqualTo 0) exitWith {
            ["SUCCESS", "Rien à relever : toutes les cases de cette zone sont déjà prises (ou en mer). Décocher « Reprendre le relevé » pour les refaire.", 8, 40] call comspec_atak_native_fnc_notify;
            false
        };
        private _mb = [1.6, 20] select (_dll isEqualTo "" || {_mode isEqualTo "plan"});
        ["INFO", format ["Capture de la carte (%1) : environ %2 images (%3 Go, %4), début dans 5 s. Échap pour arrêter, la capture reprend où elle s'est arrêtée.",
            _mode, _total, (round (_total * _mb / 102.4)) / 10, [format ["%1 min", ceil (_total * (_wait + 0.8) / 60)], format ["%1 h", (round (_total * (_wait + 0.8) / 360)) / 10]] select (_total * (_wait + 0.8) > 5400)], 8, 40] call comspec_atak_native_fnc_notify;
        if (_dll isEqualTo "") then {
            ["WARNING", "COMSPEC Link absente : images non vérifiées ni compressées, laissées dans Screenshots (20 Mo chacune, dossier limité par maxScreenShotFolderSizeMB).", 10, 40] call comspec_atak_native_fnc_notify;
        };
        ["INFO", "MAPCAP", format ["Images dans %1", call _shotDir]] call comspec_atak_native_fnc_log;
        if (!isNull (findDisplay 312)) then { (findDisplay 312) closeDisplay 2; };
        [] call comspec_atak_native_fnc_close;
        missionNamespace setVariable ["COMSPEC_ATAK_MapCapRunning", true];
        missionNamespace setVariable ["COMSPEC_ATAK_MapCapStop", false];
        [_mode, _heights, _center, _radius, (_overlap max 0) min 0.5, _wait max 0.3, _clear, _hide, _defaultFoot, _grid,
            _resume, _skipSea, _isSea, _progAll, _progKey, _progPut, _landCount, _shotDir, _stamp, _dll isNotEqualTo "", _keepPng, _total, _landIn, _townsBelow, _townCells, _citiesBelow] spawn {
            params ["_mode", "_heights", "_center", "_radius", "_overlap", "_wait", "_clear", "_hide", "_defaultFoot", "_grid",
                "_resume", "_skipSea", "_isSea", "_progAll", "_progKey", "_progPut", "_landCount", "_shotDir", "_stamp", "_dll", "_keepPng", "_estimated", "_landIn", "_townsBelow", "_townCells", "_citiesBelow"];
            disableSerialization;
            private _layer = "COMSPEC_ATAK_MapCapUI" call BIS_fnc_rscLayer;
            private _status = {
                params ["_txt"];
                _layer cutText [format ["<t size='0.9' font='RobotoCondensed' shadow='2'>%1</t>", _txt call comspec_atak_native_fnc_tr], "PLAIN DOWN", 0, false, true];
            };
            ["Capture de la carte : préparation…"] call _status;
            uiSleep 5.5;
            private _keep = [viewDistance, getObjectViewDistance select 0, getTerrainGrid];
            setViewDistance 12000;
            setObjectViewDistance [12000, 400];
            setTerrainGrid 3.125;
            // Ciel dégagé à midi (vue locale ; en solo ou dans l'éditeur l'heure est remise à la fin).
            private _weather = [date, overcast, fog, rain];
            // _weather select 0 : la date [année, mois, jour, heure, minute] (passer _weather entier donnait un tableau
            // là où setDate attend un nombre : « Type Tableau, Nombre attendu »).
            private _noon = {
                private _d = _this;
                setDate [_d select 0, _d select 1, _d select 2, 12, 0];
                0 setOvercast 0; 0 setRain 0; 0 setFog 0;
            };
            if (_clear) then { (_weather select 0) call _noon; forceWeatherChange; };
            // Joueur invulnérable le temps de la capture : mort ou inconscient, les effets d'écran (flou, gris) se
            // retrouvaient sur toutes les photos (TTRD, 20/10). Remis comme avant à la fin.
            private _dmgKeep = isDamageAllowed player;
            player allowDamage false;
            // Unités, morts et véhicules cachés (localement), le joueur compris.
            private _hidden = [];
            if (_hide) then {
                private _objs = allUnits + allDead + (vehicles select { _x isKindOf "AllVehicles" });
                {
                    if (!isObjectHidden _x) then { _x hideObject true; _hidden pushBack _x; };
                } forEach (_objs arrayIntersect _objs);
            };
            // Échap : arrêt propre (tuiles déjà prises gardées).
            private _eh = (findDisplay 46) displayAddEventHandler ["KeyDown", {
                params ["", "_key"];
                if (_key isEqualTo 1) exitWith { missionNamespace setVariable ["COMSPEC_ATAK_MapCapStop", true]; true };
                false
            }];
            private _cam = objNull;
            private _map = controlNull;
            private _mapDisp = displayNull;
            if (_mode isEqualTo "plan") then {
                // Carte plein écran sans rien autour (pas de légende, pas de curseur visible sur la photo).
                _mapDisp = (findDisplay 46) createDisplay "RscDisplayEmpty";
                _map = _mapDisp ctrlCreate ["RscMapControl", 4100];
                _map ctrlSetPosition [safeZoneX, safeZoneY, safeZoneW, safeZoneH];
                _map ctrlCommit 0;
                _map ctrlMapSetPosition [];
                // Échap ferme cet écran sans passer par l'écran de jeu : même arrêt propre ici.
                _mapDisp displayAddEventHandler ["KeyDown", {
                    params ["", "_key"];
                    if (_key isEqualTo 1) exitWith { missionNamespace setVariable ["COMSPEC_ATAK_MapCapStop", true]; true };
                    false
                }];
            } else {
                _cam = "camera" camCreate [worldSize / 2, worldSize / 2, 500];
                _cam cameraEffect ["Internal", "Back"];
                cameraEffectEnableHUD false;
                showCinemaBorder false;
            };
            private _c = if (_center isEqualTo []) then { [worldSize / 2, worldSize / 2] } else { _center };
            // Grille des altitudes notées sous chaque image satellite (13 × 13 points sur 1,2 pas).
            private _gn = 13;
            // Couche d'une hauteur : [hauteur, altitude caméra, fov, échelle du plan, pas, x0, y0, nx, ny, gx0, gy0, clé,
            // cases prises (HashMap), cases hors mer de la carte, cadrage mesuré, enfants (HashMap case parente -> cases)].
            private _place = {
                params ["_L", "_px", "_py"];
                _L params ["_h", "_alt", "_fov", "_planScale"];
                if (_mode isEqualTo "plan") then {
                    _map ctrlMapAnimAdd [0, _planScale, [_px, _py, 0]];
                    ctrlMapAnimCommit _map;
                } else {
                    private _gz = getTerrainHeightASL [_px, _py];
                    if (_mode isEqualTo "3d") then {
                        // Objectif à 45° vers le nord : il regarde le centre de la tuile depuis le sud.
                        _cam setPosASL [_px, _py - _h, _gz + _h];
                        _cam setVectorDirAndUp [[0, 0.7071, -0.7071], [0, 0.7071, 0.7071]];
                    } else {
                        _cam setPosASL [_px, _py, _gz + _alt];
                        _cam setVectorDirAndUp [[0, 0, -1], [0, 1, 0]];
                    };
                    _cam camSetFov _fov;
                    _cam camCommit 0;
                };
            };
            // 1. Cadrage de chaque hauteur, mesuré une fois au centre de la zone.
            private _layers = [];
            {
                private _h = _x;
                if (missionNamespace getVariable ["COMSPEC_ATAK_MapCapStop", false]) then { break };
                [format ["Capture de la carte : mesure du cadrage à %1 m…", _h]] call _status;
                ([_mode, _h] call _defaultFoot) params ["_fw", "_fh"];
                // Satellite : téléobjectif monté 6 fois plus haut (fov 0,12 au lieu de 0,7), même emprise au sol ;
                // plafond 4000 m, au-delà l'objectif s'ouvre (vu de 9000 m, le sol se perdait dans le voile).
                private _alt = _h;
                private _fov = 0.7;
                if (_mode isEqualTo "sat") then {
                    _alt = (_h * (tan 20.05) / (tan 3.44)) min (4000 max _h);
                    _fov = 2 * (rad (atan ((tan 20.05) * _h / _alt)));
                };
                // Plan : échelle fixée une fois (_fw par défaut) ; l'emprise est ensuite mesurée à cette échelle.
                private _planScale = (_fw / worldSize) * (safeZoneH / safeZoneW) * 1.4;
                private _L = [_h, _alt, _fov, _planScale];
                [_L, _c select 0, _c select 1] call _place;
                if (!isNull _cam) then { preloadCamera (getPosATL _cam); };
                _layer cutText ["", "PLAIN", 0];
                uiSleep (_wait max 1);
                // Cadrage satellite mesuré : position à l'écran (0 à 1) de points du sol autour du centre.
                private _calib = [];
                if (_mode isEqualTo "plan") then {
                    private _l = _map ctrlMapScreenToWorld [safeZoneX, safeZoneY + safeZoneH / 2];
                    private _r = _map ctrlMapScreenToWorld [safeZoneX + safeZoneW, safeZoneY + safeZoneH / 2];
                    private _t = _map ctrlMapScreenToWorld [safeZoneX + safeZoneW / 2, safeZoneY];
                    private _b = _map ctrlMapScreenToWorld [safeZoneX + safeZoneW / 2, safeZoneY + safeZoneH];
                    _fw = (_l distance2D _r) max 10; _fh = (_t distance2D _b) max 10;
                } else {
                    if (_mode isEqualTo "sat") then {
                        private _gz = getTerrainHeightASL _c;
                        private _d = _fw * 0.2;
                        private _uv = {
                            private _s = worldToScreen (ASLToAGL _this);
                            if ((count _s) < 2) exitWith { [] };
                            [((_s select 0) - safeZoneX) / safeZoneW, ((_s select 1) - safeZoneY) / safeZoneH]
                        };
                        private _s0 = [_c select 0, _c select 1, _gz] call _uv;
                        private _se = [(_c select 0) + _d, _c select 1, _gz] call _uv;
                        private _sn = [_c select 0, (_c select 1) + _d, _gz] call _uv;
                        if ((count _s0) isEqualTo 2 && {(count _se) isEqualTo 2} && {(count _sn) isEqualTo 2}) then {
                            private _ax = ((_se select 0) - (_s0 select 0)) * _alt / _d;
                            private _ay = ((_s0 select 1) - (_sn select 1)) * _alt / _d;
                            if (_ax > 0.01 && {_ay > 0.01}) then {
                                _calib = [_ax, _ay, _s0 select 0, _s0 select 1];
                                _fw = _alt / _ax; _fh = _alt / _ay;
                            };
                        };
                    };
                };
                // Pas arrondi à 5 m : deux captures d'une même hauteur (deux villes) gardent le même pas malgré le bruit de
                // la mesure, map_stitch.py les réunit en une seule couche.
                private _step = ((round ((_fw min _fh) * (1 - _overlap) / 5)) * 5) max 10;
                // Suivi : un relevé repris garde la grille déjà commencée (la mesure varie de quelques mètres d'une
                // séance à l'autre) et saute les cases déjà prises.
                private _key = [_mode, _h] call _progKey;
                private _rec = (call _progAll) getOrDefault [_key, []];
                if (_rec isNotEqualTo [] && {(_rec select 0) isNotEqualTo _step}) then {
                    if (_resume) then { _step = _rec select 0; } else { _rec = []; };
                };
                private _done = createHashMapFromArray ((if (_rec isEqualTo [] || {!_resume}) then { [] } else { _rec select 1 }) apply { [_x, true] });
                private _land = if (_rec isNotEqualTo [] && {(_rec select 2) > 0}) then { _rec select 2 } else { [_step] call _landCount };
                ([_center, _radius, _step] call _grid) params ["_x0", "_y0", "_nx", "_ny"];
                if (_nx * _ny > 600000) then {
                    ["WARNING", format ["%1 m : %2 cases, trop pour une capture. Choisir une zone (rayon) pour cette hauteur.", _h, _nx * _ny], 10, 40] call comspec_atak_native_fnc_notify;
                    continue;
                };
                ["INFO", "MAPCAP", format ["%1 h%2 : %3 x %4 tuiles, pas %5 m, emprise %6 x %7 m, origine %8 %9, caméra %10 m fov %11", _mode, _h, _nx, _ny, _step, round _fw, round _fh, round _x0, round _y0, round _alt, _fov]] call comspec_atak_native_fnc_log;
                if (_calib isNotEqualTo []) then {
                    diag_log text format ["CMAPL|%1|%2|%3|%4|%5|%6|%7|%8|%9|%10|%11|%12|%13|%14|%15|%16|%17|%18|%19",
                        worldName, _mode, round _h, worldSize, _alt, _fov, _calib select 0, _calib select 1, _calib select 2, _calib select 3,
                        _step, _fw, _fh, _x0, _y0, _nx, _ny, _gn, _step * 1.2];
                };
                // Vue basse : cases autour des localités seulement ([] : toutes).
                private _towns = switch (true) do {
                    case (_h < _citiesBelow): { [_x0, _y0, _nx, _ny, _step, false] call _townCells };
                    case (_h < _townsBelow): { [_x0, _y0, _nx, _ny, _step] call _townCells };
                    default { [] };
                };
                _layers pushBack [_h, _alt, _fov, _planScale, _step, _x0, _y0, _nx, _ny, round (_x0 / _step), round (_y0 / _step), _key, _done, _land, _calib, createHashMap, round _fw, _towns];
            } forEach _heights;
            // 2. Parcours par zones : chaque case d'une hauteur rattachée à la case de la hauteur au-dessus qui contient son
            // centre. Lignes en aller-retour à chaque niveau : la caméra ne saute jamais d'un bord à l'autre.
            ["Capture de la carte : préparation du parcours…"] call _status;
            for "_k" from 1 to ((count _layers) - 1) do {
                (_layers select (_k - 1)) params ["", "", "", "", "_ps", "_px0", "_py0", "_pnx", "_pny"];
                (_layers select _k) params ["", "", "", "", "_s", "_x0", "_y0", "_nx", "_ny"];
                private _kids = (_layers select _k) select 15;
                for "_iy" from 0 to (_ny - 1) do {
                    private _cy = _y0 + (_iy + 0.5) * _s;
                    private _piy = ((floor ((_cy - _py0) / _ps)) max 0) min (_pny - 1);
                    for "_ix" from 0 to (_nx - 1) do {
                        private _pix = ((floor ((_x0 + (_ix + 0.5) * _s - _px0) / _ps)) max 0) min (_pnx - 1);
                        (_kids getOrDefault [_pix * 10000 + _piy, [], true]) pushBack [_ix, _iy];
                    };
                };
            };
            private _shots = 0;
            private _okCount = 0;
            private _failRun = 0;
            private _failTotal = 0;
            private _pending = 0;
            private _free = -1;
            private _lastSave = diag_tickTime;
            private _t0 = diag_tickTime;
            private _why = "";
            private _names = createHashMapFromArray [["sat", "Satellite"], ["3d", "3D oblique"], ["plan", "Plan"]];
            private _save = {
                {
                    _x params ["_h", "", "", "", "_step", "", "", "", "", "", "", "_key", "_done", "_land"];
                    [_key, [_step, keys _done, _land, call _stamp]] call _progPut;
                } forEach _layers;
            };
            // Images rangées par la DLL depuis le dernier appel : « couche:case » rangées | sans image | en attente | Go libres.
            private _poll = {
                if (!_dll) exitWith {};
                private _r = ["MapCap.Poll"] call comspec_atak_native_fnc_extensionCall;
                if ((_r select [0, 3]) isNotEqualTo "OK|") exitWith {};
                // Champ vide écrit « - » par la DLL (splitString saute les champs vides).
                ((_r select [3]) splitString "|") params [["_ok", "-"], ["_ko", "-"], ["_pend", "0"], ["_gb", "-1"]];
                if (_ok isEqualTo "-") then { _ok = ""; };
                if (_ko isEqualTo "-") then { _ko = ""; };
                {
                    (_x splitString ":") params ["_k", "_id"];
                    ((_layers select (parseNumber _k)) select 12) set [parseNumber _id, true];
                    _okCount = _okCount + 1;
                    _failRun = 0;
                } forEach (_ok splitString ",");
                {
                    _failRun = _failRun + 1;
                    _failTotal = _failTotal + 1;
                    ["WARNING", "MAPCAP", format ["Image absente : case %1", _x]] call comspec_atak_native_fnc_log;
                } forEach (_ko splitString ",");
                _pending = parseNumber _pend;
                _free = parseNumber _gb;
            };
            private _visit = {
                params ["_k", "_ix", "_iy"];
                if (missionNamespace getVariable ["COMSPEC_ATAK_MapCapStop", false]) exitWith {};
                private _L = _layers select _k;
                _L params ["_h", "_alt", "_fov", "", "_step", "_x0", "_y0", "", "", "_gx0", "_gy0", "", "_done", "", "_calib", "", "_fw", "_towns"];
                private _px = _x0 + (_ix + 0.5) * _step;
                private _py = _y0 + (_iy + 0.5) * _step;
                private _id = (_gx0 + _ix) * 10000 + _gy0 + _iy;
                if (!(_resume && {_id in _done}) && {_towns isEqualTo [] || {(_ix * 10000 + _iy) in _towns}}
                    && {!_skipSea || {!([_px, _py, _step * 1.2] call _isSea)}}) then {
                    // Joueur mort ou inconscient malgré tout (tué avant la capture) : arrêt plutôt que des photos floues.
                    if (!alive player || {(lifeState player) isNotEqualTo "HEALTHY" && {(lifeState player) isNotEqualTo "INJURED"}}) exitWith {
                        _why = "Joueur mort ou inconscient : les effets d'écran brouilleraient les photos. Capture arrêtée, la relancer avec un joueur en vie (elle reprend où elle s'est arrêtée).";
                        missionNamespace setVariable ["COMSPEC_ATAK_MapCapStop", true];
                    };
                    [_L, _px, _py] call _place;
                    // Chargement des objets et du terrain détaillé avant la photo (plus long au premier passage).
                    if (!isNull _cam) then { preloadCamera (getPosATL _cam); };
                    // Pluie ou nuages revenus (météo de la mission) : remis à zéro avant la photo.
                    if (_clear && {rain > 0 || {overcast > 0.05}}) then { 0 setOvercast 0; 0 setRain 0; forceWeatherChange; };
                    private _el = diag_tickTime - _t0;
                    private _left = (_estimated - _shots) max 0;
                    private _eta = if (_shots > 3) then { _el / _shots * _left } else { _left * (_wait + 0.8) };
                    private _live = format ["%1 %2 m · image %3 / %4 (%5%6) · reste %7", _names getOrDefault [_mode, _mode], _h, _shots + 1, _estimated,
                        floor (100 * _shots / (_estimated max 1)), "%",
                        [format ["%1 min", ceil (_eta / 60)], format ["%1 h %2", floor (_eta / 3600), (floor ((_eta mod 3600) / 60)) call { if (_this < 10) then { "0" + str _this } else { str _this } }]] select (_eta >= 3600)];
                    missionNamespace setVariable ["COMSPEC_ATAK_MapCapLive", _live];
                    [format ["CAPTURE DE LA CARTE · %1<br/>%2<br/>%3<br/>Échap : arrêter (la capture reprend où elle s'est arrêtée)", worldName, _live,
                        [format ["Images non vérifiées (sans COMSPEC Link) : %1", _shots],
                         format ["Rangées %1 · en traitement %2 · sans image %3 · disque libre %4", _okCount, _pending, _failRun,
                            [format ["%1 Go", _free], "?"] select (_free < 0)]] select _dll]] call _status;
                    uiSleep _wait;
                    // Image précédente écrite et rangée avant la suivante (15 s au plus) : le moteur met une à deux
                    // secondes à écrire un PNG de 20 Mo et abandonne la photo suivante s'il n'a pas fini (une sur deux
                    // manquait à une photo par seconde).
                    if (_dll) then {
                        private _tw = diag_tickTime + 15;
                        call _poll;
                        while { _pending > 0 && {diag_tickTime < _tw} } do { uiSleep 0.2; call _poll; };
                    };
                    // État effacé le temps de la photo (deux images rendues sans lui).
                    _layer cutText ["", "PLAIN", 0];
                    private _fr = diag_frameNo;
                    waitUntil { diag_frameNo > _fr + 2 };
                    private _file = format ["cmap_%1_%2_h%3_s%4_w%5_o%6-%7_x%8_y%9.png", worldName, _mode, round _h, _step, _fw, round _x0, round _y0, _ix, _iy];
                    // Vue oblique (3d) : cadrage de l'image même, relevé avant la photo (caméra en place) : position à
                    // l'écran (0 à 1) d'une grille de 13 × 13 points du sol sur deux pas autour de la case, pour la
                    // redresser sur le sol (map_stitch.py ; les bâtiments penchent vers le nord, effet 3D). Gardé dans le
                    // journal (CMAPG) et à côté de l'image (.grid.txt, par la DLL).
                    private _grid3d = "";
                    if (_mode isEqualTo "3d") then {
                        private _pts = [];
                        for "_j" from 0 to 12 do {
                            for "_i" from 0 to 12 do {
                                private _gx = _px + (_i / 12 - 0.5) * 2 * _step;
                                private _gy = _py + (_j / 12 - 0.5) * 2 * _step;
                                private _sc = worldToScreen [_gx, _gy, 0];
                                if ((count _sc) isEqualTo 2) then {
                                    _pts pushBack format ["%1,%2,%3,%4,%5", round _gx, round _gy, (round ((getTerrainHeightASL [_gx, _gy]) * 10)) / 10,
                                        (((((_sc select 0) - safeZoneX) / safeZoneW) * 10000) toFixed 0), (((((_sc select 1) - safeZoneY) / safeZoneH) * 10000) toFixed 0)];
                                };
                            };
                        };
                        private _cp = getPosASL _cam;
                        _grid3d = format ["%1|%2|%3|%4|%5|%6", _file, round (_cp select 0), round (_cp select 1), round (_cp select 2), _fov, _pts joinString ";"];
                        diag_log text ("CMAPG|" + _grid3d);
                    };
                    // Refus du moteur noté dans le journal (écran éteint ou dossier plein : aucune image).
                    if !(screenshot _file) then { ["WARNING", "MAPCAP", format ["Photo refusée par le moteur : %1", _file]] call comspec_atak_native_fnc_log; };
                    diag_log text format ["CMAPF|%1", _file];
                    if (_calib isNotEqualTo []) then {
                        private _span = _step * 1.2;
                        private _hs = [];
                        for "_j" from 0 to (_gn - 1) do {
                            for "_i" from 0 to (_gn - 1) do {
                                _hs pushBack ((round ((getTerrainHeightASL [_px + (_i / (_gn - 1) - 0.5) * _span, _py + (_j / (_gn - 1) - 0.5) * _span]) * 10)) / 10);
                            };
                        };
                        diag_log text format ["CMAPS|%1|%2|%3|%4|%5", _file, _px, _py, (getPosASL _cam) select 2, _hs joinString ","];
                    };
                    uiSleep 0.35;
                    if (_dll) then {
                        // Case en entier (toFixed : au-delà d'un million, format écrivait « 1.85011e+06 »).
                        ["MapCap.Collect", [_file, worldName, _mode, str (round _h), format ["%1:%2", _k, _id toFixed 0], ["0", "1"] select _keepPng, _grid3d]] call comspec_atak_native_fnc_extensionCall;
                    } else {
                        _done set [_id, true];
                    };
                    _shots = _shots + 1;
                    call _poll;
                    if (_failRun >= 5) then {
                        _why = "Cinq images de suite n'ont pas été écrites par le jeu : dossier Screenshots plein (maxScreenShotFolderSizeMB) ou disque plein.";
                        missionNamespace setVariable ["COMSPEC_ATAK_MapCapStop", true];
                    };
                    if (_free >= 0 && {_free < 3}) then {
                        _why = format ["Disque presque plein (%1 Go libres) : capture arrêtée.", _free];
                        missionNamespace setVariable ["COMSPEC_ATAK_MapCapStop", true];
                    };
                    if (diag_tickTime - _lastSave > 60) then { call _save; _lastSave = diag_tickTime; };
                    // Soleil tenu à midi (l'heure avance pendant une longue capture).
                    if (_clear && {(_shots mod 40) isEqualTo 0}) then { (_weather select 0) call _noon; };
                };
                // Cases des hauteurs plus basses sous celle-ci, en aller-retour.
                if (_k + 1 < count _layers) then {
                    private _kids = ((_layers select (_k + 1)) select 15) getOrDefault [_ix * 10000 + _iy, []];
                    private _rows = createHashMap;
                    { (_rows getOrDefault [_x select 1, [], true]) pushBack (_x select 0); } forEach _kids;
                    private _ys = keys _rows;
                    _ys sort true;
                    {
                        private _y = _x;
                        private _xs = _rows get _y;
                        _xs sort ((_forEachIndex mod 2) isEqualTo 0);
                        { [_k + 1, _x, _y] call _visit; } forEach _xs;
                    } forEach _ys;
                };
            };
            // Deux passes : la seconde reprend seulement les cases dont l'image a manqué (cases prises sautées).
            private _missed = 0;
            for "_pass" from 1 to 2 do {
                if (_layers isEqualTo [] || {missionNamespace getVariable ["COMSPEC_ATAK_MapCapStop", false]}) then { break };
                if (_pass isEqualTo 2) then {
                    // Attente des dernières images de la première passe, puis seulement s'il en manque.
                    private _tw = diag_tickTime + 30;
                    call _poll;
                    while { _pending > 0 && {diag_tickTime < _tw} } do { uiSleep 0.5; call _poll; };
                    if (_missed isEqualTo _failTotal) then { break };
                    _missed = _failTotal;
                };
                (_layers select 0) params ["", "", "", "", "", "", "", "_nx", "_ny"];
                for "_iy" from 0 to (_ny - 1) do {
                    if (missionNamespace getVariable ["COMSPEC_ATAK_MapCapStop", false]) then { break };
                    private _cols = [];
                    for "_ix" from 0 to (_nx - 1) do { _cols pushBack _ix; };
                    if ((_iy mod 2) isEqualTo 1) then { reverse _cols; };
                    { [0, _x, _iy] call _visit; } forEach _cols;
                };
            };
            // Dernières images en traitement (30 s au plus).
            if (_dll) then {
                private _until = diag_tickTime + 30;
                call _poll;
                while { _pending > 0 && {diag_tickTime < _until} } do {
                    [format ["Capture de la carte : rangement des dernières images (%1)…", _pending]] call _status;
                    uiSleep 1;
                    call _poll;
                };
            };
            call _save;
            [] call comspec_atak_native_fnc_saveProfile;
            _layer cutText ["", "PLAIN", 0];
            missionNamespace setVariable ["COMSPEC_ATAK_MapCapLive", nil];
            if (!isNull _cam) then { _cam cameraEffect ["Terminate", "Back"]; camDestroy _cam; };
            if (!isNull _mapDisp) then { _mapDisp closeDisplay 1; };
            (findDisplay 46) displayRemoveEventHandler ["KeyDown", _eh];
            setViewDistance (_keep select 0);
            setObjectViewDistance (_keep select 1);
            setTerrainGrid (_keep select 2);
            { if (!isNull _x) then { _x hideObject false; }; } forEach _hidden;
            player allowDamage _dmgKeep;
            if (_clear) then {
                _weather params ["_date", "_oc", "_fog", "_rain"];
                setDate _date;
                0 setOvercast _oc; 0 setFog _fog; 0 setRain _rain;
                forceWeatherChange;
            };
            missionNamespace setVariable ["COMSPEC_ATAK_MapCapRunning", false];
            if (_why isNotEqualTo "") then { ["ERROR", _why, 15, 50] call comspec_atak_native_fnc_notify; };
            private _summary = _layers apply { format ["%1 m %2%3", _x select 0, (floor (100 * (count (_x select 12)) / ((_x select 13) max 1))) min 100, "%"] };
            ["SUCCESS", format ["Capture de la carte terminée : %1 image(s) en %2 min dans %3. Sur le PC : build_photo_pack.bat (pack @COMSPEC_ATAK_Photo)", [_shots, _okCount] select _dll, round ((diag_tickTime - _t0) / 60), call _shotDir], 12, 40] call comspec_atak_native_fnc_notify;
            ["INFO", format ["Relevé de %1 (%2), part de la carte prise : %3", worldName, _mode, _summary joinString ", "], 12, 40] call comspec_atak_native_fnc_notify;
        };
        true
    };
    default { false };
};
