/*
    Compteurs de performance pour le journal ([PERF] du RPT, jamais rien à l'écran) : temps passé dans le code du mod,
    pour trouver ce qui fait ramer en jeu (retour de TTRD : « le drone fait lag, ou le marquage ? »).

    Point d'accroche commun (ATAK, Overwatch, SSE : tester !isNil "comspec_atak_native_fnc_perfAcc" avant l'appel) :
      ["clé", t0]                         ajoute diag_tickTime - t0 au compteur « clé » : [appels, temps total, pire appel] (s).
      ["clé", t0, seuil ms]               en plus : une ligne « [PERF] Lent : clé : N ms » si l'appel dépasse le seuil
                                          (au plus une ligne par libellé toutes les 10 s, avec le nombre de lignes tues).
      ["clé", t0, seuil ms, image]        image = diag_frameNo relevé avec t0, pour le code ordonnancé (spawn) : si l'image a
                                          changé entre-temps, la mesure est rangée sous « clé (étalé) » et ne compte pas dans
                                          la part de l'image (ce n'est pas un gel d'une image). -1 : non précisé.
      ["clé", t0, seuil ms, image, libellé]  libellé de la ligne « Lent » quand la clé regroupe plusieurs choses
                                          (« DLL native GetAuthState (direct) », « Boucle offline »…).
      ["frame"]    une fois par image (XEH_postInitClient, XEH_postInitServer sur un serveur dédié) : images comptées,
                   images lentes, « Image figée » (150 ms et plus) et « Saccade » (plus de 50 ms et du triple de la moyenne
                   glissante, 6 par minute au plus) avec la part mesurée du mod et ses 5 mesures les plus longues.
      ["report"]   écrit la ligne de bilan et remet les compteurs à zéro (toutes les 30 s) :
                   [PERF] Boucles 30 s : 42 img/s, images lentes (plus de 50 ms) 3 (pire 180 ms), … ; clé n× moy x ms max y ms ; ...
                   (les plus coûteuses d'abord). Rien n'est écrit si rien n'a été mesuré et aucune image n'a ralenti.
                   Les clés négligeables (moins de 1 ms au total et pire appel sous 2 ms) sont comptées ensemble ; le reste
                   tient en lignes de 900 caractères au plus (le RPT coupe les lignes trop longues) : la suite part en
                   « [PERF] Boucles 30 s (suite) ; clé … ».

    Part du mod dans une image : les mesures imbriquées (une clé mesurée à l'intérieur d'une boucle elle-même mesurée) ne
    sont comptées qu'une fois. Une mesure qui se termine englobe toutes celles de l'image commencées après son t0 : elles
    sont marquées « incluses » dans la liste de l'image (COMSPEC_ATAK_PerfFrameL, [t0, durée, clé, de premier niveau]),
    restent citées dans les lignes « Image figée » et « Saccade », mais ne comptent plus dans la somme.
    Le chronomètre (diag_tickTime) est au dixième de milliseconde près après quelques heures de jeu : les moyennes sous
    0,1 ms sont indicatives, les totaux et les pires appels restent fiables.
*/
params [["_key", ""], ["_t0", 0], ["_warnMs", -1], ["_frameNo", -1], ["_what", ""]];

if (_key isEqualTo "frame") exitWith {
    private _dt = diag_deltaTime;
    private _list = missionNamespace getVariable ["COMSPEC_ATAK_PerfFrameL", []];
    missionNamespace setVariable ["COMSPEC_ATAK_PerfFrameL", []];
    // Fenêtre du bilan : [images, somme des durées, début, images hors focus, classes de durée (16), pire image,
    // moyenne glissante, instants des dernières lignes « Saccade »].
    private _w = missionNamespace getVariable ["COMSPEC_ATAK_PerfWin", []];
    if ((count _w) isNotEqualTo 8) then {
        _w = [0, 0, diag_tickTime, 0, [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], 0, 0.02, []];
        missionNamespace setVariable ["COMSPEC_ATAK_PerfWin", _w];
    };
    _w set [0, (_w select 0) + 1];
    _w set [1, (_w select 1) + _dt];
    if (_dt > (_w select 5)) then { _w set [5, _dt]; };
    // Classes de durée d'image (bornes hautes en secondes) : le 99e centile du bilan se lit sans trier les images.
    private _class = [0.004, 0.006, 0.008, 0.01, 0.012, 0.016, 0.02, 0.025, 0.033, 0.04, 0.05, 0.066, 0.1, 0.15, 0.25] findIf { _dt <= _x };
    if (_class < 0) then { _class = 15; };
    private _hist = _w select 4;
    _hist set [_class, (_hist select _class) + 1];
    // Jeu en arrière-plan (Alt+Tab : le moteur se bride autour de 20 img/s) : images comptées à part, ni lentes ni saccades.
    private _focus = !hasInterface || {isGameFocused};
    if (!_focus) then { _w set [3, (_w select 3) + 1]; };
    private _avg = _w select 6;
    private _hitch = _focus && {_dt > 0.05} && {_dt > 3 * _avg};
    if (_focus) then {
        // Images lentes (plus de 50 ms, moins de 20 img/s) : comptées pour le bilan des 30 s.
        if (_dt > 0.05) then {
            private _sl = missionNamespace getVariable ["COMSPEC_ATAK_PerfSlow", [0, 0]];
            missionNamespace setVariable ["COMSPEC_ATAK_PerfSlow", [(_sl select 0) + 1, (_sl select 1) max _dt]];
        };
        if (!_hitch) then { _w set [6, _avg * 0.95 + _dt * 0.05]; };
    };
    if (_dt < 0.15 && {!_hitch}) exitWith {};
    // Temps mesuré dans le code du mod depuis le passage précédent (une image environ) : part de l'image qui nous
    // revient ; le reste est au moteur et aux autres mods (ACRE, ACE, textures…) ou à du code COMSPEC non mesuré.
    private _mine = 0;
    { if (_x select 3) then { _mine = _mine + (_x select 1); }; } forEach _list;
    // Les 5 mesures les plus longues, celles de premier niveau comme celles qu'elles englobent (« inclus ») : la ligne
    // nomme la boucle et, dedans, ce qui a coûté.
    private _top = _list apply { [_x select 1, _x select 2, [0, 1] select (_x select 3)] };
    _top sort false;
    private _topTxt = ((_top select [0, 5]) apply { format ["%1 %2 ms%3", _x select 1, ((_x select 0) * 1000) toFixed 1, [" (inclus)", ""] select (_x select 2)] }) joinString " + ";
    if (_topTxt isEqualTo "") then { _topTxt = "rien"; };
    if (_dt < 0.15) exitWith {
        // Saccade : 6 lignes par minute au plus.
        private _now = diag_tickTime;
        private _at = (_w select 7) select { _now - _x < 60 };
        if ((count _at) >= 6) exitWith { _w set [7, _at]; };
        _at pushBack _now;
        _w set [7, _at];
        diag_log format ["[COMSPEC ATAK NATIVE][PERF] Saccade : image de %1 ms (moyenne %2 ms), dont environ %3 ms mesurés dans le mod : %4",
            round (_dt * 1000), (_avg * 1000) toFixed 1, round (_mine * 1000), _topTxt];
    };
    if (!hasInterface) exitWith {
        diag_log format ["[COMSPEC ATAK NATIVE][PERF] Image figée : %1 ms, dont environ %2 ms dans les boucles du mod (serveur dédié, joueurs %3, unités %4, marqueurs %5, mesuré %6)",
            round (_dt * 1000), round (_mine * 1000), count allPlayers, count allUnits, count allMapMarkers, _topTxt];
    };
    private _arsenal = !isNull (uiNamespace getVariable ["ace_arsenal_display", displayNull]) || {!isNull (uiNamespace getVariable ["BIS_fnc_arsenal_display", displayNull])};
    private _veh = vehicle player;
    // Contexte : combat (corréler les gels au tir), véhicule, Zeus, taille de la partie, focus. Les mesures viennent en
    // dernier dans la parenthèse (leurs clés peuvent contenir une virgule).
    diag_log format ["[COMSPEC ATAK NATIVE][PERF] Image figée : %1 ms, dont environ %10 ms dans les boucles du mod (arsenal %2, téléphone %3, carte %4, drone %5, cibles marquées %6, page %7, combat %8, suppression %9, focus %11, pause %12, véhicule %13, Zeus %14, unités %15, joueurs %16, marqueurs %17, %18, mesuré %19)",
        round (_dt * 1000), ["non", "ouvert"] select _arsenal, ["rangé", "affiché"] select !isNull ([] call comspec_atak_native_fnc_display), ["non", "ouverte"] select visibleMap,
        ["aucun", "appairé"] select !isNull (missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull]), count (missionNamespace getVariable ["COMSPEC_ATAK_Locks", createHashMap]),
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""],
        behaviour player, round ((getSuppression player) * 100), round (_mine * 1000),
        ["non", "oui"] select _focus, ["non", "oui"] select isGamePaused,
        if (_veh isEqualTo player) then { "à pied" } else { format ["%1 à %2 km/h", typeOf _veh, round (speed _veh)] },
        ["non", "ouvert"] select !isNull (findDisplay 312), count allUnits, count allPlayers, count allMapMarkers,
        ["client", "hôte"] select isServer, _topTxt];
};

if (_key isEqualTo "report") exitWith {
    // Mode profilage (fn_profiler) : c'est lui qui fait le bilan, toutes les 10 s.
    if (missionNamespace getVariable ["COMSPEC_ATAK_Prof", false]) exitWith {};
    private _acc = missionNamespace getVariable ["COMSPEC_ATAK_PerfAcc", createHashMap];
    missionNamespace setVariable ["COMSPEC_ATAK_PerfAcc", createHashMap];
    (missionNamespace getVariable ["COMSPEC_ATAK_PerfSlow", [0, 0]]) params ["_slowN", "_slowMax"];
    missionNamespace setVariable ["COMSPEC_ATAK_PerfSlow", [0, 0]];
    // Fenêtre des images (["frame"]) : moyenne réelle d'images par seconde (images comptées / durée), 99e centile de la
    // durée d'image, images hors focus. Sans boucle d'image (rien compté) : diag_fps de l'instant, comme avant.
    private _w = missionNamespace getVariable ["COMSPEC_ATAK_PerfWin", []];
    private _frames = _w param [0, 0];
    private _span = (diag_tickTime - (_w param [2, diag_tickTime])) max 0.001;
    private _fps = if (_frames > 0) then { round (_frames / _span) } else { round diag_fps };
    private _bounds = [4, 6, 8, 10, 12, 16, 20, 25, 33, 40, 50, 66, 100, 150, 250, 999];
    private _p99 = 0;
    if (_frames > 0) then {
        private _hist = _w select 4;
        private _left = ceil (_frames * 0.01);
        private _i = 15;
        while { _i > 0 && {_left > (_hist select _i)} } do { _left = _left - (_hist select _i); _i = _i - 1; };
        _p99 = _bounds select _i;
    };
    private _worst = round ((_w param [5, 0]) * 1000);
    private _unfocused = _w param [3, 0];
    missionNamespace setVariable ["COMSPEC_ATAK_PerfWin", [0, 0, diag_tickTime, 0, [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], 0, _w param [6, 0.02], _w param [7, []]]];
    private _rows = (keys _acc) apply { [(_acc get _x) select 1, _x] };
    _rows sort false;
    // Dernier bilan gardé pour l'onglet PERFS du Debug : 12 plus coûteux [clé, appels, moy ms, max ms, total ms] et
    // rendus complets de page par page sur la même fenêtre (fn_pageRender).
    private _renders = uiNamespace getVariable ["COMSPEC_ATAK_RenderCount", createHashMap];
    uiNamespace setVariable ["COMSPEC_ATAK_RenderCount", createHashMap];
    missionNamespace setVariable ["COMSPEC_ATAK_PerfLast", [diag_tickTime, _fps, _slowN, round (_slowMax * 1000),
        (_rows select [0, 12]) apply { private _c = _acc get (_x select 1); [_x select 1, _c select 0, (_c select 1) / ((_c select 0) max 1) * 1000, (_c select 2) * 1000, (_c select 1) * 1000] },
        _renders toArray false,
        // Suivis (3.0.1) : dessin de la carte (marqueurs, repères Athena et leur halo, zones, routes, cartes des apps
        // Zones et Routes) et papiers du SSE (conditions des menus ACE), montrés même hors des 12 plus coûteux.
        (["carte marqueurs mission (image)", "carte repères Athena (image)", "carte zones (image)", "carte routes (image)",
            "carte Zones (image)", "carte Routes (image)", "SSE papiers (idDocsOf)"] select { _x in _acc }) apply {
            private _c = _acc get _x; [_x, _c select 0, (_c select 1) / ((_c select 0) max 1) * 1000, (_c select 2) * 1000, (_c select 1) * 1000] }]];
    if ((count _acc) isEqualTo 0 && {_slowN isEqualTo 0}) exitWith {};
    private _parts = [];
    private _minorN = 0;
    private _minorT = 0;
    {
        private _c = _acc get (_x select 1);
        if ((_c select 1) < 0.001 && {(_c select 2) < 0.002}) then { _minorN = _minorN + 1; _minorT = _minorT + (_c select 1); continue; };
        _parts pushBack format ["%1 %2× moy %3 ms max %4 ms", _x select 1, _c select 0, ((_c select 1) / ((_c select 0) max 1) * 1000) toFixed 2, ((_c select 2) * 1000) toFixed 1];
    } forEach _rows;
    if (_minorN > 0) then { _parts pushBack format ["%1 autres clés négligeables, %2 ms en tout", _minorN, (_minorT * 1000) toFixed 1]; };
    // Lignes de 900 caractères au plus : la première porte l'en-tête (350 caractères environ), les suivantes la suite.
    private _lines = [];
    private _cur = [];
    private _len = 350;
    {
        if (_len + (count _x) + 3 > 900 && {_cur isNotEqualTo []}) then { _lines pushBack _cur; _cur = []; _len = 45; };
        _cur pushBack _x;
        _len = _len + (count _x) + 3;
    } forEach _parts;
    _lines pushBack _cur;
    // Serveur (dédié ou hôte) : taille de la partie et diffusions du graphe du maillage sur la fenêtre (fn_meshGraph).
    private _srv = "";
    if (isServer) then {
        private _pubs = missionNamespace getVariable ["COMSPEC_ATAK_MeshPubs", 0];
        private _pubsWin = (_pubs - (missionNamespace getVariable ["COMSPEC_ATAK_PerfMeshPubs", 0])) max 0;
        missionNamespace setVariable ["COMSPEC_ATAK_PerfMeshPubs", _pubs];
        _srv = format [", serveur : img/s mini %1, joueurs %2, unités %3, nœuds du maillage %4, diffusions du graphe %5 (environ %6 caractères)",
            [round (1 / ((_w param [5, 0]) max 0.001)), round diag_fpsMin] select (_frames isEqualTo 0), count allPlayers, count allUnits,
            (missionNamespace getVariable ["COMSPEC_ATAK_MeshStats", createHashMap]) getOrDefault ["nodes", 0], _pubsWin,
            [0, count str (missionNamespace getVariable ["COMSPEC_ATAK_MeshRef", []])] select (_pubsWin > 0)];
    };
    private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
    diag_log format ["[COMSPEC ATAK NATIVE][PERF] Boucles 30 s : %1 img/s, images lentes (plus de 50 ms) %2 (pire %3 ms), drone %4, cibles marquées %5, page %6, image au 99e centile %8 ms, pire image %9 ms, focus %10 (%11 images hors focus), pause %12%13 ; %7",
        _fps, _slowN, round (_slowMax * 1000), ["aucun", "appairé"] select (!isNull _d), count (missionNamespace getVariable ["COMSPEC_ATAK_Locks", createHashMap]),
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""], [(_lines select 0) joinString " ; ", "rien mesuré"] select ((count _parts) isEqualTo 0),
        _p99, _worst, ["non", "oui"] select (!hasInterface || {isGameFocused}), _unfocused, ["non", "oui"] select isGamePaused, _srv];
    { diag_log format ["[COMSPEC ATAK NATIVE][PERF] Boucles 30 s (suite) ; %1", _x joinString " ; "]; } forEach (_lines select [1]);
};

private _dt = diag_tickTime - _t0;
// Code ordonnancé dont la mesure couvre plusieurs images : temps d'horloge, pas un gel. Rangé à part.
private _spread = _frameNo >= 0 && {_frameNo isNotEqualTo diag_frameNo};
if (_spread) then { _key = _key + " (étalé)"; };
private _acc = missionNamespace getVariable ["COMSPEC_ATAK_PerfAcc", createHashMap];
private _c = _acc getOrDefault [_key, []];
if ((count _c) isEqualTo 0) then { _c = [0, 0, 0]; _acc set [_key, _c]; };
_c set [0, (_c select 0) + 1];
_c set [1, (_c select 1) + _dt];
if (_dt > (_c select 2)) then { _c set [2, _dt]; };
missionNamespace setVariable ["COMSPEC_ATAK_PerfAcc", _acc];
// Mesure des performances (fn_perfLog, réglage décoché par défaut) : mêmes mesures, bilans [COMSPEC perf] à part.
if (missionNamespace getVariable ["COMSPEC_ATAK_PerfLog", false]) then { ["acc", [_key, _dt]] call comspec_atak_native_fnc_perfLog; };
if (!_spread) then {
    // Temps mesuré dans l'image en cours, sans double compte : les lignes « Image figée » et « Saccade » disent ainsi
    // quelle part de l'image a été passée dans le code mesuré du mod (le reste : moteur, autres mods).
    private _list = missionNamespace getVariable "COMSPEC_ATAK_PerfFrameL";
    if (isNil "_list") then { _list = []; missionNamespace setVariable ["COMSPEC_ATAK_PerfFrameL", _list]; };
    private _i = (count _list) - 1;
    while { _i >= 0 && {((_list select _i) select 0) >= _t0} } do { (_list select _i) set [3, false]; _i = _i - 1; };
    if ((count _list) < 64) then { _list pushBack [_t0, _dt, _key, true]; };
    // Seuil dépassé : une ligne par libellé toutes les 10 s au plus.
    if (_warnMs > 0 && {_dt * 1000 >= _warnMs}) then {
        if (_what isEqualTo "") then { _what = _key; };
        private _warn = missionNamespace getVariable "COMSPEC_ATAK_PerfWarn";
        if (isNil "_warn") then { _warn = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_PerfWarn", _warn]; };
        (_warn getOrDefault [_what, [-100, 0]]) params ["_at", "_muted"];
        if (diag_tickTime - _at < 10) exitWith { _warn set [_what, [_at, _muted + 1]]; };
        _warn set [_what, [diag_tickTime, 0]];
        diag_log format ["[COMSPEC ATAK NATIVE][PERF] Lent : %1 : %2 ms (seuil %3 ms%4)", _what, round (_dt * 1000), _warnMs,
            ["", format [", %1 autres fois depuis la ligne précédente", _muted]] select (_muted > 0)];
    };
};
// Mode profilage : ce qui a tourné pendant l'image (saccades) et les pics de plus de 5 ms (20 par bilan).
if (missionNamespace getVariable ["COMSPEC_ATAK_Prof", false]) then {
    COMSPEC_ATAK_ProfFrame pushBack [_key, _dt];
    if (_dt > 0.005 && {(count COMSPEC_ATAK_ProfSpikes) < 20}) then { COMSPEC_ATAK_ProfSpikes pushBack [_key, _dt]; };
};
