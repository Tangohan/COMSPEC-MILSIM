/*
    Fond de carte PHOTO : la capture de la carte (fn_mapCapture puis tools/map_stitch.py --pyramid), tuiles z/x/y de
    256 px en mètres Arma, côté « extent », tuile y = 0 au nord. Deux sources :
      - le mod optionnel @COMSPEC_ATAK_Photo (une PBO par carte, classe COMSPEC_ATAK_PhotoMaps), prioritaire s'il est
        chargé ;
      - sinon le fond publié sur Athena (map_stitch.py --upload, tuiles JPEG), téléchargé en jeu par la DLL
        (MapPhoto.*, Extension_MapPhoto.cs) dans Arma 3\COMSPEC_cache\map-photo\<carte>\sat\z\x\y.jpg (Arma ne charge pas une image hors de son
        dossier ou de ceux des mods) et dessiné
        par son chemin absolu à barres obliques (C:/…/y.jpg, comme les images de briefing téléchargées par la DLL) :
        rien à installer, rien à redémarrer. Meta demandée une fois par mission (MapPhoto.Meta, réponse immédiate).
    Params :
      "info"         → [extent, tileSize, minZoom, maxZoom, chemin, extension, source ("pbo" | "web"), tuiles sur
                        Athena, octets, tuiles en cache au départ] pour la carte en cours, [] sans fond
      "prefetch"     demande la meta au démarrage (XEH_postInitClient), redemandée toutes les 5 s tant qu'elle est en route
      "poll"         MapPhoto.Status : tuiles arrivées notées, état renvoyé et gardé (COMSPEC_ATAK_PhotoWebStatus)
      "rows"         lignes de la section « Cartes hors ligne » des Réglages (fn_pageSettings, catégorie Carte)
      "download" | "cancel" | "clear"   boutons de cette section
      [contrôle carte, fond choisi] dessine les tuiles visibles (appelé par fn_mapOnDraw, sous tous les symboles)
    Niveau choisi pour qu'un pixel de tuile fasse au plus un pixel d'écran ; une tuile absente (zone capturée moins
    finement, ou pas encore téléchargée) est remplacée par son ancêtre le plus proche, dessiné en entier sous les
    tuiles fines.
    Fond d'Athena : présence de chaque tuile gardée pour la mission (COMSPEC_ATAK_PhotoWebHave, « z/x/y » → vrai, ou
    l'heure à laquelle la redemander) : le disque n'est jamais testé à l'image. Tuiles inconnues demandées par lots
    (MapPhoto.Want, qui répond tout de suite pour celles déjà en cache), une fois par seconde au plus, avec le relevé
    des arrivées (MapPhoto.Status). Dessiné en PHOTO seulement (JPEG sans transparence : pas par-dessus la vue SATELLITE).
    Taille des icônes : la largeur à l'écran divisée par pixelW, en 480es de la hauteur de la zone d'affichage (unité
    de drawIcon), retouchable par COMSPEC_ATAK_PhotoPxAdjust (profil, défaut 1) si les tuiles se chevauchent ou laissent des joints.
*/
params [["_arg", "info"], ["_style", "PHOTO"]];
private _call = comspec_atak_native_fnc_extensionCall;
private _size = {
    params ["_b"];
    if (_b <= 0) exitWith { "0 Mo" };
    if (_b >= 1073741824) exitWith { format ["%1 Go", (round (_b / 107374182.4)) / 10] };
    format ["%1 Mo", (round (_b / 1048576)) max 1]
};
private _info = uiNamespace getVariable [format ["COMSPEC_ATAK_PhotoInfo_%1", worldName], nil];
if (isNil "_info") then {
    _info = [];
    private _cfg = ("(toLower getText (_x >> 'world')) isEqualTo (toLower worldName)" configClasses (configFile >> "COMSPEC_ATAK_PhotoMaps")) param [0, configNull];
    if (!isNull _cfg && {getNumber (_cfg >> "extent") > 0}) then {
        // zoomPaths[] = {{niveau, chemin}} : niveau rangé dans une autre PBO (une seule dépasserait 2 Go), tuiles x\y.
        private _zp = createHashMap;
        { if (_x isEqualType [] && {(count _x) >= 2}) then { _zp set [_x select 0, _x select 1]; }; } forEach (getArray (_cfg >> "zoomPaths"));
        _info = [getNumber (_cfg >> "extent"), (getNumber (_cfg >> "tileSize")) max 1, getNumber (_cfg >> "minZoom"), getNumber (_cfg >> "maxZoom"), getText (_cfg >> "path"), getText (_cfg >> "ext"), "pbo", 0, 0, 0, _zp];
    };
    uiNamespace setVariable [format ["COMSPEC_ATAK_PhotoInfo_%1", worldName], _info];
};
// Fond d'Athena téléchargé en jeu (MapPhoto.*) : désactivé. Arma ne charge pas une image hors d'un PBO ou du dossier
// de la mission, même sous son propre dossier (« Cannot load texture f:/…/arma 3/comspec_cache/… », TTRD 20/10) :
// les tuiles téléchargées restaient invisibles. Le fond SATELLITE vient seulement du mod @COMSPEC_ATAK_Photo.
if (_arg isEqualTo "info") exitWith { _info };
private _web = (_info param [6, ""]) isEqualTo "web";
private _have = missionNamespace getVariable "COMSPEC_ATAK_PhotoWebHave";
if (isNil "_have") then { _have = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_PhotoWebHave", _have]; };
private _name = getText (configFile >> "CfgWorlds" >> worldName >> "description");
if (_name isEqualTo "") then { _name = worldName; };
private _rerender = { [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };

// Meta d'Athena au démarrage : plus demandée (fond téléchargé désactivé, voir plus haut).
if (_arg isEqualTo "prefetch") exitWith {};

// Section « Cartes hors ligne » des Réglages : rien sans fond (le mod suffit, rien à télécharger).
if (_arg isEqualTo "rows" && {_info isEqualTo []}) exitWith { [] };
// Relevé de la DLL : [en file, téléchargées, octets, erreurs, en cours, état hors ligne, faites, total, tuiles en cache,
// octets en cache] ; arrivées notées présentes, manquées redemandées dans une minute.
if (_arg isEqualTo "poll") exitWith {
    if (!_web) exitWith { [] };
    private _p = (["MapPhoto.Status", [worldName, "sat"]] call _call) splitString "|";
    if ((_p param [0, ""]) isNotEqualTo "OK" || {(count _p) < 13}) exitWith { [] };
    { if (_x isNotEqualTo "-") then { _have set [_x, true]; }; } forEach ((_p select 11) splitString ",");
    private _t = diag_tickTime + 60;
    { if (_x isNotEqualTo "-" && {(_have getOrDefault [_x, 0]) isNotEqualTo true}) then { _have set [_x, _t]; }; } forEach ((_p select 12) splitString ",");
    private _st = (_p select [1, 10]) apply { if (_x in ["idle", "running", "done", "cancelled"]) then { _x } else { parseNumber _x } };
    missionNamespace setVariable ["COMSPEC_ATAK_PhotoWebStatus", _st];
    _st
};

if (_arg isEqualTo "download") exitWith {
    if (!_web) exitWith {};
    private _r = ["MapPhoto.Download", [worldName, "sat", str (_info select 3)]] call _call;
    private _p = _r splitString "|";
    if ((_p param [0, ""]) isEqualTo "OK") then {
        private _todo = parseNumber (_p param [1, "0"]);
        private _tot = (parseNumber (_p param [2, "0"])) max 1;
        ["INFO", ["Fond photo déjà complet sur ce PC", format ["Téléchargement du fond photo : %1 tuiles (environ %2), en tâche de fond", _todo, [(_info select 8) * _todo / _tot] call _size]] select (_todo > 0), 5, 10] call comspec_atak_native_fnc_notify;
    } else {
        ["WARNING", ["Téléchargement du fond photo impossible", "Téléphone non relié à Athena : téléchargement impossible"] select (_r isEqualTo "ERR|not_connected"), 4, 20] call comspec_atak_native_fnc_notify;
    };
    call _rerender;
};

if (_arg isEqualTo "cancel") exitWith {
    ["MapPhoto.Cancel"] call _call;
    ["Téléchargement annulé, les tuiles reçues restent", 2.5] call comspec_atak_native_fnc_toast;
    call _rerender;
};

// Effacer : second appui dans les 5 s pour confirmer.
if (_arg isEqualTo "clear") exitWith {
    if (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_PhotoClearArm", -10]) < 5) then {
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoClearArm", -10];
        ["MapPhoto.Clear", [worldName]] call _call;
        missionNamespace setVariable ["COMSPEC_ATAK_PhotoWebHave", createHashMap];
        if (_web) then { _info set [9, 0]; };
        ["Fond photo effacé de ce PC", 2] call comspec_atak_native_fnc_toast;
    } else {
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoClearArm", diag_tickTime];
    };
    call _rerender;
};

if (_arg isEqualTo "rows") exitWith {
    private _rows = [["section", "Cartes hors ligne", "Fond SATELLITE de la carte en cours, téléchargé depuis Athena et gardé sur ce PC"]];
    switch (true) do {
        case ((_info param [6, ""]) isEqualTo "pbo"): {
            _rows pushBack ["text", format ["<t size='0.8' color='#8a9a93'>Fond SATELLITE de %1 fourni par le mod COMSPEC ATAK Photo : rien à télécharger.</t>", _name]];
        };
        case (_info isEqualTo []): {
            private _ans = missionNamespace getVariable ["COMSPEC_ATAK_PhotoWebAnswer", ""];
            _rows pushBack ["text", format ["<t size='0.8' color='#8a9a93'>%1</t>", switch (true) do {
                case (_ans isEqualTo "ERR|none"): { format ["Aucun fond photo sur Athena pour %1.", _name] };
                case (_ans isEqualTo "ERR|not_connected"): { "Téléphone non relié à Athena : le fond photo se télécharge une fois la liaison faite." };
                case (_ans isEqualTo "OK|pending"): { format ["Recherche du fond photo de %1 sur Athena…", _name] };
                default { "Fond photo d'Athena indisponible (DLL COMSPEC absente ou trop ancienne)." };
            }]];
            _rows pushBack ["buttons", [["CHERCHER SUR ATHENA", {
                missionNamespace setVariable ["COMSPEC_ATAK_PhotoWebNext", 0];
                ["info"] call comspec_atak_native_fnc_photoMap;
                [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }, [], 1.5] call CBA_fnc_waitAndExecute;
            }, false, true, "Redemander à Athena si un fond photo existe pour cette carte"]]];
        };
        default {
            private _st = ["poll"] call comspec_atak_native_fnc_photoMap;
            _st params [["_q", 0], ["_dl", 0], ["_bytes", 0], ["_err", 0], ["_act", 0], ["_state", "idle"], ["_done", 0], ["_total", 0], ["_cached", _info select 9], ["_cBytes", 0]];
            private _tiles = _info select 7;
            private _pct = [0, floor (100 * (_cached min _tiles) / (_tiles max 1))] select (_tiles > 0);
            private _running = _state isEqualTo "running";
            _rows append [
                ["info", "Carte", _name],
                ["info", "Sur Athena", format ["%1 tuiles · %2", _tiles, [_info select 8] call _size]],
                ["info", "Sur ce PC", format ["%1 tuiles · %2 (%3 %4)", _cached, [_cBytes] call _size, _pct, "%"]]
            ];
            if (_state in ["running", "done", "cancelled"]) then {
                private _t = format ["%1 / %2 tuiles (%3 %4)", _done, _total, floor (100 * _done / (_total max 1)), "%"];
                _t = switch (_state) do {
                    case "done": { format ["Terminé : %1", _t] };
                    case "cancelled": { format ["Annulé : %1", _t] };
                    default { _t };
                };
                if (_err > 0) then { _t = _t + format [" · %1 erreur(s)", _err]; };
                _rows pushBack ["info", "Téléchargement", _t];
            };
            private _armed = diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_PhotoClearArm", -10]) < 5;
            _rows pushBack ["buttons", [
                ["TÉLÉCHARGER", { ["download"] call comspec_atak_native_fnc_photoMap; }, true, !_running && {_pct < 100},
                    format ["Tout le fond de %1 sur ce PC, pour l'avoir hors ligne (environ %2, 3 Mo/s au plus ; reprend où il s'était arrêté)", _name, [(_info select 8) * (1 - _pct / 100)] call _size]],
                ["ARRÊTER", { ["cancel"] call comspec_atak_native_fnc_photoMap; }, false, _running, "Arrêter le téléchargement (les tuiles reçues restent)"],
                [["EFFACER", "CONFIRMER"] select _armed, { ["clear"] call comspec_atak_native_fnc_photoMap; }, false, _cached > 0 || {_running},
                    "Effacer le fond photo de cette carte de ce PC (second appui pour confirmer) ; il se recharge à la demande"]
            ]];
            _rows pushBack ["text", "<t size='0.75' color='#8a9a93'>Sans téléchargement, le fond SATELLITE se charge à la demande, là où la carte est regardée.</t>"];
            // Progression : page redessinée toutes les 2 s tant que la catégorie Carte des Réglages est ouverte.
            if (_running && {diag_tickTime >= (uiNamespace getVariable ["COMSPEC_ATAK_PhotoDlRefresh", 0])}) then {
                uiNamespace setVariable ["COMSPEC_ATAK_PhotoDlRefresh", diag_tickTime + 1.9];
                [{
                    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
                    if ((_s getOrDefault ["pageDrawn", ""]) isEqualTo "SETTINGS" && {(_s getOrDefault ["setCat", ""]) isEqualTo "MAP"}) then { ["SETTINGS"] call comspec_atak_native_fnc_pageRender; };
                }, [], 2] call CBA_fnc_waitAndExecute;
            };
        };
    };
    _rows
};

if !(_arg isEqualType controlNull) exitWith {};
private _map = _arg;
if (isNull _map || {_info isEqualTo []}) exitWith {};
if (_web && {_style isNotEqualTo "PHOTO"}) exitWith {};
_info params ["_ext", "_ts", "_zMin", "_zMax", "_path", "_fx"];
private _sep = ["\", "/"] select _web;
(ctrlPosition _map) params ["_cx", "_cy", "_cw", "_ch"];
private _tl = _map ctrlMapScreenToWorld [_cx, _cy];
private _br = _map ctrlMapScreenToWorld [_cx + _cw, _cy + _ch];
private _wM = ((_br select 0) - (_tl select 0)) max 1;
// Mètres par pixel d'écran, puis niveau : tuile de _ts px au moins aussi fine que l'écran.
private _mpp = _wM / ((_cw / pixelW) max 1);
private _z = ceil (log (_ext / (_ts * _mpp)) / log 2);
_z = (_z max _zMin) min _zMax;
// Au plus une soixantaine de tuiles par image (carte en HUD comprise).
private _range = {
    params ["_zz"];
    private _T = _ext / (2 ^ _zz);
    private _n = 2 ^ _zz;
    [_T, (floor ((_tl select 0) / _T)) max 0, (floor (((_br select 0) - 0.001) / _T)) min (_n - 1),
        (floor ((_ext - (_tl select 1)) / _T)) max 0, (floor ((_ext - (_br select 1) - 0.001) / _T)) min (_n - 1)]
};
private _r = [_z] call _range;
while { _z > _zMin && {(((_r select 2) - (_r select 1) + 1) * ((_r select 4) - (_r select 3) + 1)) > 64} } do {
    _z = _z - 1;
    _r = [_z] call _range;
};
_r params ["_T", "_x0", "_x1", "_y0", "_y1"];
if (_x1 < _x0 || {_y1 < _y0}) exitWith {};
private _zPaths = _info param [10, createHashMap];
private _file = {
    params ["_zz", "_tx", "_ty"];
    private _zp = _zPaths getOrDefault [_zz, ""];
    if (_zp isNotEqualTo "") exitWith { _zp + str _tx + _sep + str _ty + _fx };
    _path + str _zz + _sep + str _tx + _sep + str _ty + _fx
};
private _now = diag_tickTime;
private _want = [];
// Mod : présence testée une fois par fichier (fileExists sur la PBO), gardée pour la séance.
private _haveP = uiNamespace getVariable ["COMSPEC_ATAK_PhotoHave", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_PhotoHave", _haveP];
private _exists = if (_web) then {
    // Fond d'Athena : présence connue (vrai), ou à demander (inconnue, ou délai de la dernière réponse passé).
    {
        params ["_zz", "_tx", "_ty"];
        private _n = format ["%1/%2/%3", _zz, _tx, _ty];
        private _k = _have getOrDefault [_n, -1];
        if (_k isEqualTo true) exitWith { true };
        if (_k < _now) then { _want pushBack _n; };
        false
    }
} else {
    {
        private _f = _this call _file;
        private _k = _haveP get _f;
        if (isNil "_k") then { _k = fileExists _f; _haveP set [_f, _k]; };
        _k
    }
};
// drawIcon compte sa taille en 480es de la hauteur de la zone d'affichage (getResolution select 3, l'écran virtuel
// 640 × 480 d'Arma), pas en pixels : en 1440p, interface « Petite » (zone de 792 px), 480 / 792 = 0,606. Mesuré en jeu
// (TTRD 20/10) : 1 débordait, 0,75 aussi, 0,55 laissait des joints de 10 %. Retouche éventuelle :
// COMSPEC_ATAK_PhotoPxAdjust (profil, défaut 1).
private _k = 480 / ((getResolution select 3) max 1) * ((profileNamespace getVariable ["COMSPEC_ATAK_PhotoPxAdjust", 1]) max 0.1);
// Un seul niveau par image : deux hauteurs de capture ne tombent pas au mètre près au même endroit, un mélange de
// tuiles fines et d'ancêtres agrandis donnait une mosaïque décalée (TTRD 20/10). Le niveau le plus fin, capturé
// seulement autour des localités (vue 120 m), ne sert que s'il couvre toute l'image ; aux autres niveaux, une tuile
// absente est de la mer (non photographiée) : le bleu dessiné dessous suffit.
private _fine = [];
private _coarse = createHashMap;
private _collect = {
    _fine = [];
    private _miss = 0;
    for "_ty" from _y0 to _y1 do {
        for "_tx" from _x0 to _x1 do {
            if ([_z, _tx, _ty] call _exists) then { _fine pushBack [_z, _tx, _ty]; } else { _miss = _miss + 1; };
        };
    };
    _miss
};
private _miss = call _collect;
if (_z isEqualTo _zMax && {_miss > 0} && {_z > _zMin}) then {
    _z = _z - 1;
    ([_z] call _range) params ["_T2", "_x02", "_x12", "_y02", "_y12"];
    _T = _T2; _x0 = _x02; _x1 = _x12; _y0 = _y02; _y1 = _y12;
    call _collect;
};
private _draw = {
    params ["_zz", "_tx", "_ty"];
    private _S = _ext / (2 ^ _zz);
    private _px = _k * _S / _mpp;
    _map drawIcon [[_zz, _tx, _ty] call _file, [1, 1, 1, 1], [(_tx + 0.5) * _S, _ext - (_ty + 0.5) * _S, 0], _px, _px, 0, "", 0];
};
// Mer sous la photo, sur toute la carte : les cases entièrement en mer ne sont pas photographiées (noir sinon).
// Bleu des tuiles corrigées (RVB 72, 104, 155).
_map drawRectangle [[worldSize / 2, worldSize / 2, 0], worldSize / 2, worldSize / 2, 0, [0.282, 0.408, 0.608, 1], "#(rgb,8,8,3)color(0.282,0.408,0.608,1)"];
{ _y call _draw; } forEach _coarse;
{ _x call _draw; } forEach _fine;
// Relief des bâtiments (ombre portée, contour des toits) aux zooms proches.
[_map, _wM, _tl, _br] call comspec_atak_native_fnc_photoRelief;
if (!_web) exitWith {};

// Une fois par seconde au plus : arrivées relevées, puis tuiles inconnues demandées d'un coup (celles déjà en cache
// reviennent tout de suite ; en route : redemandées dans 20 s sans nouvelle ; absentes du site : dans 5 min).
if (_now >= (missionNamespace getVariable ["COMSPEC_ATAK_PhotoWebTick", 0])) then {
    missionNamespace setVariable ["COMSPEC_ATAK_PhotoWebTick", _now + 1];
    ["poll"] call comspec_atak_native_fnc_photoMap;
    _want = _want arrayIntersect _want;
    if (_want isNotEqualTo []) then {
        _want resize ((count _want) min 150);
        private _p = (["MapPhoto.Want", [worldName, "sat", _want joinString ","]] call _call) splitString "|";
        if ((_p param [0, ""]) isEqualTo "OK") then {
            { _have set [_x, _now + 20]; } forEach _want;
            { if (_x isNotEqualTo "-") then { _have set [_x, true]; }; } forEach ((_p param [1, "-"]) splitString ",");
            { if (_x isNotEqualTo "-") then { _have set [_x, _now + 300]; }; } forEach ((_p param [2, "-"]) splitString ",");
        } else {
            { _have set [_x, _now + 30]; } forEach _want;
        };
    };
};
// Premier affichage du fond d'Athena dans la mission, pas encore tout sur ce PC : proposer le téléchargement.
if !(missionNamespace getVariable ["COMSPEC_ATAK_PhotoWebHint", false]) then {
    missionNamespace setVariable ["COMSPEC_ATAK_PhotoWebHint", true];
    if ((_info select 9) < (_info select 7)) then {
        ["INFO", format ["Fond photo disponible pour %1 (%2) : il se charge à la demande ; Réglages > Carte pour le télécharger en entier", _name, [_info select 8] call _size], 8, 10, "SETTINGS"] call comspec_atak_native_fnc_notify;
    };
};
