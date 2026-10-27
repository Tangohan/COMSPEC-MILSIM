/*
    Montre Garmin au poignet (Forerunner 965 ; coloris noire, coyote, FreeCandy, drapeau US selon l'objet porté :
    propriété comspec_watchFrame de l'objet, photos de TTRD), ouverte avec la touche « Montre » du jeu (O par défaut) quand le
    joueur porte la « Montre Garmin » (emplacement montre) ou l'a en objet divers : elle remplace la montre d'Arma.
    Sans montre Garmin, la touche garde son effet habituel.
    Touche Montre : appui court = allumer, puis écran suivant (après le dernier, la montre s'éteint) ; appui long
    (0,5 s) = éteindre.
    Les 5 boutons de la vraie montre sont des touches CBA (COMSPEC ATAK, modifiables aussi dans le téléphone :
    Réglages > Accès et touches > Montre Garmin) :
      LIGHT  appui court : luminosité ; appui long (0,6 s) : menu des commandes (Ctrl+Alt+O)
      UP     écran précédent ; menu : remonter ; minuteur en réglage : +1 min ; musique (commandes) : piste, retour ou volume (Ctrl+O)
      DOWN   écran suivant ; menu : descendre ; minuteur en réglage : -1 min ; musique : piste, avance ou volume
             (touche Montre du jeu, O)
      START  action de l'écran : chrono, minuteur, marquer la position, zoom de la carte, étalonner l'altimètre,
             trouver le téléphone, lecture de la musique ; menu : valider (Maj+O)
      BACK   retour au cadran, tour du chrono, remise à zéro ; menu : fermer ; sur le cadran : éteindre (Alt+O)
    Plus : vue agrandie au centre de l'écran (Ctrl+Maj+O), et sans touche par défaut : allumer / éteindre, menu des
    commandes, chrono, tour, minuteur, marquer ma position, mode nuit, ne pas déranger, trouver mon téléphone.
    Écrans (AMOLED, comme la montre) :
      FACE     cadran : heure du jeu, date, Bluetooth, batterie de la montre, Body Battery (arc bleu), objectif de pas
               (arc vert), fréquence cardiaque, pas, lever ou coucher du soleil, température, stress ;
      HR       fréquence cardiaque : valeur, zone (1 à 5), courbe des 12 dernières minutes, repos et maximum du jour ;
      ALT      altimètre-baromètre : altitude barométrique (dérive avec la météo, START l'étalonne sur le GPS),
               pression, courbe des 30 dernières minutes, dénivelé positif et négatif du jour ;
      MAP      carte nord en haut : mon point (GPS, mêmes erreurs que le téléphone), les alliés du suivi,
               point marqué, cap, largeur affichée (START : zoom suivant) ;
      COMPASS  boussole : cap, point cardinal, couronne qui tourne, coordonnées MGRS ; START marque ma position, la
               couronne montre alors la direction du point et sa distance (BACK l'efface) ; un point de la carte de
               l'ATAK s'y envoie aussi (clic droit > Envoyer à la montre, téléphone allumé) ;
      JUMP     Jumpmaster (HALO / HAHO) : hauteur sol, vitesse de chute, altitude d'ouverture, direction et distance du
               point (DIP) ; START : régler l'altitude d'ouverture (UP / DOWN, 100 m), puis armer. Armée, la montre
               vibre à 300 m de l'ouverture puis à l'ouverture, même éteinte ; elle se désarme une fois posé ;
      CHRONO   chronomètre (START départ / arrêt, BACK tour ou remise à zéro) et activité reconnue en cours ;
      TIMER    minuteur (START : régler la durée avec UP / DOWN, puis lancer ; pause ; BACK : remise à zéro) ;
               la montre vibre à la fin ;
      PHONE    téléphone porté et allumé : batterie, 3 dernières notifications
               (START : faire sonner le téléphone). Le partage Bluetooth du téléphone n'entre pas en compte ;
      MUSIC    lecteur du téléphone : morceau en cours, position, file. START : lecture ou pause. BACK ouvre les
               commandes, puis passe de la piste (±) à l'avance (±15 s), au volume, puis les referme. Tant que les
               commandes sont ouvertes, UP / DOWN ne changent plus d'écran. Le cadran montre le morceau à la place
               du stress ;
      DAY      ma journée : pas, distance, stress, Body Battery, sommeil / repos.
    Menu des commandes : vue agrandie, mode nuit (écran rouge), rouge automatique avec les JVN, ne pas déranger (pas de
    bandeau ni de vibration), mode furtif (Bluetooth coupé, écran au minimum), luminosité, heure H au groupe, marquer ma
    position, trouver mon téléphone, éteindre.
    Heure H (chef de groupe) : compte à rebours de la durée du minuteur envoyé aux montres de tout le groupe (heure du
    serveur) ; elles vibrent à H-1 min puis trois fois au top ; le cadran et le minuteur affichent H-mm:ss. Relancée
    pendant le décompte : annulée pour tout le groupe.
    Cadran : phase et éclairement de la lune (préparer les opérations de nuit).
    Notifications du téléphone (liaison active, hors « ne pas déranger ») : bandeau sur la montre ouverte pendant
    5 s, quel que soit l'écran. La montre n'apparaît que sur demande du joueur (rien ne s'ouvre tout seul).
    Textes en Roboto Condensed du jeu (COMSPEC_Barlow*) : la Barlow embarquée (COMSPEC_BarlowC*) s'affiche mal en jeu.
    Données de fn_garminTick (missionNamespace COMSPEC_ATAK_Gm). Textures : tools/gen_garmin_watch.py.
    Calque COMSPEC_ATAK_Watch (COMSPEC_RscTitleGarmin) ; état uiNamespace COMSPEC_ATAK_Watch (affichage) et
    missionNamespace COMSPEC_ATAK_WatchM (chrono, minuteur, point marqué, étalonnage).
    Réglages client : comspec_atak_native_watch_size (0 petite, 1 moyenne, 2 grande), comspec_atak_native_watch_side
    (0 gauche, 1 centre, 2 droite).
    Params : [action : "down" | "up" (touche Montre, renvoie true si la Garmin la prend) | "open" | "close" | "toggle" |
              "next" | "prev" | "start" | "back" | "lightDown" | "lightUp" | "lightHold" | "menu" | "big" | "chrono" | "lap" |
              "timer" | "mark" | "night" | "dnd" | "find" | "hhour" | "jump" | "stealth" | "ring" (fin du minuteur) | "buzz" |
              "send" ([position, nom] : point envoyé par l'ATAK) | "hhRecv" ([fin, envoyeur, id], remoteExecCall du chef) |
              "hhWarn" / "hhTop" (id) | "jumpTick" (PFH 0,2 s, armée) | "tick" (PFH 0,1 s allumée), argument]
    Retour des touches : true si la montre est portée (la touche est prise), sinon false.
*/
params [["_act", "tick"], ["_arg", []]];
disableSerialization;
if (!hasInterface) exitWith { false };
private _pages = ["FACE", "HR", "ALT", "MAP", "COMPASS", "JUMP", "CHRONO", "TIMER", "PHONE", "MUSIC", "DAY"];
private _menuItems = [
    ["big", "Vue agrandie"], ["night", "Mode nuit"], ["nvAuto", "Rouge auto (JVN)"], ["dnd", "Ne pas déranger"],
    ["stealth", "Mode furtif"], ["bright", "Luminosité"], ["hh", "Heure H au groupe"], ["mark", "Marquer ma position"],
    ["find", "Trouver mon téléphone"], ["off", "Éteindre"]
];
private _w = uiNamespace getVariable ["COMSPEC_ATAK_Watch", createHashMap];
if ((count _w) isEqualTo 0) then {
    _w = createHashMapFromArray [["open", false], ["page", 0], ["built", ""], ["down", -1], ["lightDown", -1], ["pfh", -1], ["seq", -1],
        ["banner", ["", "", 0]], ["slow", 0], ["big", false], ["night", false], ["dnd", false], ["bright", 2], ["menu", -1], ["edit", false], ["zoom", 1]];
    uiNamespace setVariable ["COMSPEC_ATAK_Watch", _w];
};
private _m = missionNamespace getVariable ["COMSPEC_ATAK_WatchM", createHashMap];
if ((count _m) isEqualTo 0) then {
    _m = createHashMapFromArray [["run", false], ["t0", 0], ["acc", 0], ["laps", []], ["tdur", 300], ["tend", -1], ["tleft", 300], ["tid", 0], ["wpt", []], ["altOff", 0]];
    missionNamespace setVariable ["COMSPEC_ATAK_WatchM", _m];
};
private _tr = { _this call comspec_atak_native_fnc_tr };
private _tex = { format ["\z\comspec_atak_native\addons\main\data\%1.paa", _this] };
private _has = {
    alive player && {lifeState player isNotEqualTo "INCAPACITATED"} && {!(player getVariable ["ACE_isUnconscious", false])}
    && {((assignedItems player) findIf { _x isKindOf ["COMSPEC_ATAK_GarminWatch", configFile >> "CfgWeapons"] }) >= 0
        || {((items player) findIf { _x isKindOf ["COMSPEC_ATAK_GarminWatchItem", configFile >> "CfgWeapons"] }) >= 0}}
};
private _linkNow = {
    !(_w getOrDefault ["stealth", false]) && {([] call comspec_atak_native_fnc_canUse) isEqualTo ""}
};
// Heure du serveur (même base sur toutes les montres du groupe pour l'heure H).
private _now = [time, serverTime] select isMultiplayer;
// Durée en secondes -> « 1:02:03 », « 12:34 » ou, avec dixièmes, « 12:34.5 ».
private _fmtT = {
    params ["_s", ["_tenth", false]];
    _s = _s max 0;
    private _h = floor (_s / 3600);
    private _mi = floor ((_s mod 3600) / 60);
    private _se = floor (_s mod 60);
    private _two = { [str _this, "0" + str _this] select (_this < 10) };
    private _o = if (_h > 0) then { format ["%1:%2:%3", _h, _mi call _two, _se call _two] } else { format ["%1:%2", _mi call _two, _se call _two] };
    if (_tenth && {_h isEqualTo 0}) then { _o = _o + "." + str (floor ((_s * 10) mod 10)); };
    _o
};
// Dérive de l'altimètre barométrique (mètres) : suit la pression météo tant qu'il n'est pas étalonné.
private _drift = { round (40 * (overcast - 0.25) + 8 * sin (time * 0.05)) };
// Bandeau en haut de l'écran : titre et texte (français, traduits ici), durée en secondes.
private _toast = {
    params ["_t", ["_b", ""], ["_dur", 3]];
    _w set ["banner", [_t call _tr, _b call _tr, diag_tickTime + _dur]];
    _w set ["slow", 0];
};
// Vibration de la montre (son de vibration d'Overwatch, très bas) : coupée par « ne pas déranger » et la discrétion.
private _buzz = {
    if (_w getOrDefault ["dnd", false]) exitWith {};
    if (profileNamespace getVariable ["COMSPEC_ATAK_Silent", false]) exitWith {};
    if (!isNil "comspec_overwatch_connect_fnc_playAtakVibrate") then { [0.3] call comspec_overwatch_connect_fnc_playAtakVibrate; };
};
private _close = {
    ("COMSPEC_ATAK_Watch" call BIS_fnc_rscLayer) cutText ["", "PLAIN"];
    if ((_w get "pfh") >= 0) then { [_w get "pfh"] call CBA_fnc_removePerFrameHandler; };
    _w set ["pfh", -1];
    _w set ["open", false];
    _w set ["built", ""];
    _w set ["menu", -1];
    _w set ["edit", false];
};
private _open = {
    if !(call _has) exitWith {};
    ("COMSPEC_ATAK_Watch" call BIS_fnc_rscLayer) cutRsc ["COMSPEC_RscTitleGarmin", "PLAIN", 0, false];
    _w set ["open", true];
    _w set ["built", ""];
    _w set ["slow", 0];
    _w set ["menu", -1];
    _w set ["edit", false];
    // Pas de bandeau pour les notifications reçues montre éteinte.
    _w set ["seq", (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["notifHistSeq", 0]];
    if ((_w get "pfh") < 0) then {
        _w set ["pfh", [{ ["tick"] call comspec_atak_native_fnc_watch; }, 0.1] call CBA_fnc_addPerFrameHandler];
    };
    ["tick"] call comspec_atak_native_fnc_watch;
};
private _redraw = { _w set ["slow", 0]; if (_w get "open") then { ["tick"] call comspec_atak_native_fnc_watch; }; };
// Écran donné, montre allumée si besoin (touches directes : chrono, minuteur, point…).
private _show = {
    params ["_pg"];
    _w set ["page", (_pages find _pg) max 0];
    _w set ["menu", -1];
    if !(_w get "open") then { call _open; };
};
// UP (-1) / DOWN (+1) ; _closeAtEnd : touche Montre du jeu (après le dernier écran, la montre s'éteint).
private _nav = {
    params ["_step", ["_closeAtEnd", false]];
    if !(_w get "open") exitWith { _w set ["page", 0]; call _open; };
    if ((_w get "menu") >= 0) exitWith { _w set ["menu", ((_w get "menu") + _step) max 0 min ((count _menuItems) - 1)]; call _redraw; };
    if (_w get "edit") exitWith {
        if ((_pages select (_w get "page")) isEqualTo "JUMP") then {
            _m set ["jOpen", ((_m getOrDefault ["jOpen", 1200]) - _step * 100) max 300 min 9000];
        } else {
            _m set ["tdur", ((_m get "tdur") - _step * 60) max 60 min 5940];
            _m set ["tleft", _m get "tdur"];
        };
        call _redraw;
    };
    // Lecteur, une fois les commandes ouvertes (BACK) : UP / DOWN ne changent plus d'écran.
    // Sans ça, la touche Montre continue de passer à l'écran suivant.
    if ((_pages select (_w get "page")) isEqualTo "MUSIC" && {(_w getOrDefault ["mus", 0]) > 0}) exitWith {
        switch (_w get "mus") do {
            case 2: { ["seek", _step * 15] call comspec_atak_native_fnc_musicAction; };
            case 3: { ["volStep", _step * -10] call comspec_atak_native_fnc_musicAction; };
            default {
                if (_step > 0) then { ["next"] call comspec_atak_native_fnc_musicAction; } else { ["prev"] call comspec_atak_native_fnc_musicAction; };
                call _buzz;
            };
        };
        call _redraw;
    };
    private _p = (_w get "page") + _step;
    if (_closeAtEnd && {_p >= (count _pages)}) exitWith { call _close; };
    _w set ["page", (_p + (count _pages)) mod (count _pages)];
    call _redraw;
};
private _do = {
    params ["_what"];
    switch (_what) do {
        case "big": { _w set ["big", !(_w get "big")]; _w set ["menu", -1]; };
        case "night": { _w set ["night", !(_w get "night")]; _w set ["menu", -1]; [["Mode nuit désactivé", "Mode nuit activé"] select (_w get "night")] call _toast; };
        case "dnd": { _w set ["dnd", !(_w get "dnd")]; _w set ["menu", -1]; [["Ne pas déranger désactivé", "Ne pas déranger activé"] select (_w get "dnd")] call _toast; };
        case "bright": {
            _w set ["bright", ((_w get "bright") + 1) mod 3];
            [format ["Luminosité : %1", (["Faible", "Moyenne", "Maximale"] select (_w get "bright")) call _tr]] call _toast;
        };
        case "mark": {
            private _pos = [] call comspec_atak_native_fnc_gpsPos;
            _m set ["wpt", [_pos, [daytime, "HH:MM"] call BIS_fnc_timeToString]];
            _w set ["menu", -1];
            call _buzz;
            ["Position marquée", format ["Point %1 · %2", mapGridPosition _pos, (_m get "wpt") select 1]] call _toast;
        };
        case "find": {
            _w set ["menu", -1];
            if (call _linkNow) then {
                if (!isNil "comspec_overwatch_connect_fnc_playAtakVibrate") then { [1] call comspec_overwatch_connect_fnc_playAtakVibrate; };
                [] call comspec_atak_native_fnc_vibrate;
                ["Trouver mon téléphone", "Le téléphone sonne et vibre"] call _toast;
            } else {
                ["Trouver mon téléphone", "Téléphone éteint ou absent"] call _toast;
            };
        };
        case "off": { call _close; };
        case "chrono": {
            if (_m get "run") then {
                _m set ["acc", (_m get "acc") + diag_tickTime - (_m get "t0")];
                _m set ["run", false];
            } else {
                _m set ["t0", diag_tickTime];
                _m set ["run", true];
            };
            call _buzz;
        };
        case "lap": {
            if (_m get "run") then {
                private _tot = (_m get "acc") + diag_tickTime - (_m get "t0");
                private _laps = _m get "laps";
                private _prev = [0, _laps select ((count _laps) - 1)] select ((count _laps) > 0);
                _laps pushBack _tot;
                call _buzz;
                [format ["Tour %1", count _laps], [_tot - _prev, true] call _fmtT] call _toast;
            };
        };
        case "timer": {
            if (_w get "edit") exitWith { _w set ["edit", false]; _m set ["tleft", _m get "tdur"]; ["tgo"] call _do; };
            if ((_m get "tend") >= 0) exitWith {
                _m set ["tleft", ((_m get "tend") - diag_tickTime) max 0];
                _m set ["tend", -1];
                _m set ["tid", (_m get "tid") + 1];
            };
            if ((_m get "tleft") >= (_m get "tdur")) exitWith { _w set ["edit", true]; };
            ["tgo"] call _do;
        };
        case "tgo": {
            private _end = diag_tickTime + (_m get "tleft");
            private _id = (_m get "tid") + 1;
            _m set ["tend", _end];
            _m set ["tid", _id];
            [{
                params ["_id", "_end"];
                diag_tickTime >= _end || {((missionNamespace getVariable ["COMSPEC_ATAK_WatchM", createHashMap]) getOrDefault ["tid", -1]) isNotEqualTo _id}
            }, {
                params ["_id"];
                if (((missionNamespace getVariable ["COMSPEC_ATAK_WatchM", createHashMap]) getOrDefault ["tid", -1]) isEqualTo _id) then { ["ring"] call comspec_atak_native_fnc_watch; };
            }, [_id, _end]] call CBA_fnc_waitUntilAndExecute;
        };
        case "treset": {
            _m set ["tend", -1];
            _m set ["tid", (_m get "tid") + 1];
            _m set ["tleft", _m get "tdur"];
        };
        case "calib": {
            _m set ["altOff", call _drift];
            call _buzz;
            ["Altimètre étalonné", "Sur l'altitude GPS"] call _toast;
        };
        case "nvAuto": {
            _w set ["nvAuto", !(_w getOrDefault ["nvAuto", true])];
            _w set ["menu", -1];
            [["Rouge auto désactivé", "Rouge auto avec les JVN"] select (_w get "nvAuto")] call _toast;
        };
        case "stealth": {
            _w set ["stealth", !(_w getOrDefault ["stealth", false])];
            _w set ["menu", -1];
            if (_w get "stealth") then { ["Mode furtif activé", "Bluetooth coupé, écran au minimum"] call _toast; } else { ["Mode furtif désactivé", "Bluetooth rétabli"] call _toast; };
        };
        // Heure H : le chef de groupe envoie la fin du compte à rebours (heure du serveur) à toutes les montres du groupe.
        case "hh": {
            _w set ["menu", -1];
            if ((leader group player) isNotEqualTo player) exitWith { ["Heure H", "Réservée au chef de groupe"] call _toast; };
            private _hh = _m getOrDefault ["hh", []];
            if (_hh isNotEqualTo [] && {(_hh select 0) > _now}) exitWith {
                ["hhRecv", [-1, name player, -1]] remoteExecCall ["comspec_atak_native_fnc_watch", units group player];
            };
            ["hhRecv", [_now + (_m get "tdur"), name player, floor random 1e6]] remoteExecCall ["comspec_atak_native_fnc_watch", units group player];
        };
        // Jumpmaster : START règle l'altitude d'ouverture (UP / DOWN), START arme ; armée, START désarme.
        case "jump": {
            if (_w get "edit") exitWith { _w set ["edit", false]; ["jarm"] call _do; };
            if (_m getOrDefault ["jArm", false]) exitWith { _m set ["jArm", false]; ["Jumpmaster désarmé"] call _toast; };
            _w set ["edit", true];
        };
        case "jarm": {
            _m set ["jArm", true];
            _m set ["jStage", 0];
            _m set ["jFall", false];
            call _buzz;
            [format ["Ouverture à %1 m", _m getOrDefault ["jOpen", 1200]], "Vibre à +300 m puis à l'ouverture"] call _toast;
            if ((_m getOrDefault ["jPfh", -1]) < 0) then {
                _m set ["jPfh", [{ ["jumpTick"] call comspec_atak_native_fnc_watch; }, 0.2] call CBA_fnc_addPerFrameHandler];
            };
        };
    };
};
private _start = {
    if !(_w get "open") exitWith { _w set ["page", 0]; call _open; };
    private _mi = _w get "menu";
    if (_mi >= 0) exitWith { [(_menuItems select _mi) select 0] call _do; call _redraw; };
    switch (_pages select (_w get "page")) do {
        case "FACE": { _w set ["page", _pages find "CHRONO"]; if !(_m get "run") then { ["chrono"] call _do; }; };
        case "CHRONO": { ["chrono"] call _do; };
        case "TIMER": { ["timer"] call _do; };
        case "MAP": { _w set ["zoom", ((_w get "zoom") + 1) mod 4]; _w set ["built", ""]; };
        case "COMPASS": { ["mark"] call _do; };
        case "JUMP": { ["jump"] call _do; };
        case "ALT": { ["calib"] call _do; };
        case "PHONE": { ["find"] call _do; };
        case "MUSIC": {
            private _mu = [] call comspec_atak_native_fnc_musicState;
            if ((_mu get "kind") isEqualTo "" && {(count (_mu get "queue")) isEqualTo 0}) exitWith {
                ["Musique", "Lancez un morceau sur le téléphone"] call _toast;
            };
            ["toggle"] call comspec_atak_native_fnc_musicAction;
            call _buzz;
        };
        default { _w set ["menu", 0]; };
    };
    call _redraw;
};
private _back = {
    if !(_w get "open") exitWith {};
    if ((_w get "menu") >= 0) exitWith { _w set ["menu", -1]; call _redraw; };
    if (_w get "edit") exitWith { _w set ["edit", false]; if ((_pages select (_w get "page")) isEqualTo "TIMER") then { _m set ["tleft", _m get "tdur"]; }; call _redraw; };
    private _pg = _pages select (_w get "page");
    switch (true) do {
        case (_pg isEqualTo "CHRONO" && {_m get "run"}): { ["lap"] call _do; };
        case (_pg isEqualTo "CHRONO" && {(_m get "acc") > 0}): { _m set ["acc", 0]; _m set ["laps", []]; ["Chrono remis à zéro"] call _toast; };
        case (_pg isEqualTo "TIMER" && {(_m get "tend") >= 0 || {(_m get "tleft") < (_m get "tdur")}}): { ["treset"] call _do; };
        case (_pg isEqualTo "COMPASS" && {(_m get "wpt") isNotEqualTo []}): { _m set ["wpt", []]; ["Point effacé"] call _toast; };
        case (_pg isEqualTo "MUSIC"): {
            private _mode = (_w getOrDefault ["mus", 0]) + 1;
            if (_mode > 3) then { _w set ["mus", 0]; } else { _w set ["mus", _mode]; };
        };
        case (_pg isEqualTo "FACE"): { call _close; };
        default { _w set ["page", 0]; _w set ["mus", 0]; };
    };
    call _redraw;
};

switch (_act) do {
    case "down": {
        if !(call _has) exitWith { false };
        if ((_w get "down") < 0) then { _w set ["down", diag_tickTime]; };
        true
    };
    case "up": {
        private _t0 = _w get "down";
        _w set ["down", -1];
        if (_t0 < 0) exitWith { false };
        if (!(call _has)) exitWith { call _close; false };
        if ((diag_tickTime - _t0) >= 0.5) then { call _close; } else { [1, true] call _nav; };
        true
    };
    case "open": { if !(call _has) exitWith { false }; _w set ["page", 0]; call _open; true };
    case "close": { call _close; call _has };
    case "toggle": { if !(call _has) exitWith { false }; if (_w get "open") then { call _close; } else { _w set ["page", 0]; call _open; }; true };
    case "next": { if !(call _has) exitWith { false }; [1] call _nav; true };
    case "prev": { if !(call _has) exitWith { false }; [-1] call _nav; true };
    case "start": { if !(call _has) exitWith { false }; call _start; true };
    case "back": { if !(call _has) exitWith { false }; call _back; true };
    // LIGHT : le menu s'ouvre de lui-même au bout de 0,6 s de maintien (sans attendre le relâchement, que CBA peut
    // ne pas voir si Ctrl ou Alt est lâché avant) ; relâché avant : luminosité.
    case "lightDown": {
        if !(call _has) exitWith { false };
        if ((_w get "lightDown") >= 0) exitWith { true };
        private _t0 = diag_tickTime;
        _w set ["lightDown", _t0];
        [{
            params ["_t0"];
            private _w = uiNamespace getVariable ["COMSPEC_ATAK_Watch", createHashMap];
            if ((_w getOrDefault ["lightDown", -1]) isEqualTo _t0) then { ["lightHold"] call comspec_atak_native_fnc_watch; };
        }, [_t0], 0.6] call CBA_fnc_waitAndExecute;
        true
    };
    case "lightHold": {
        _w set ["lightDown", -1];
        if !(call _has) exitWith { false };
        if !(_w get "open") then { _w set ["page", 0]; call _open; };
        _w set ["menu", [0, -1] select ((_w get "menu") >= 0)];
        _w set ["edit", false];
        call _redraw;
        true
    };
    case "lightUp": {
        private _t0 = _w get "lightDown";
        _w set ["lightDown", -1];
        if (_t0 < 0 || {!(call _has)}) exitWith { false };
        if !(_w get "open") exitWith { _w set ["page", 0]; call _open; true };
        ["bright"] call _do;
        call _redraw;
        true
    };
    case "menu": {
        if !(call _has) exitWith { false };
        if !(_w get "open") then { call _open; };
        _w set ["menu", [0, -1] select ((_w get "menu") >= 0)];
        _w set ["edit", false];
        call _redraw;
        true
    };
    case "big": {
        if !(call _has) exitWith { false };
        ["big"] call _do;
        if !(_w get "open") then { _w set ["page", 0]; call _open; } else { call _redraw; };
        true
    };
    case "chrono": { if !(call _has) exitWith { false }; ["CHRONO"] call _show; ["chrono"] call _do; call _redraw; true };
    case "lap": { if !(call _has) exitWith { false }; ["CHRONO"] call _show; ["lap"] call _do; call _redraw; true };
    case "timer": { if !(call _has) exitWith { false }; ["TIMER"] call _show; ["timer"] call _do; call _redraw; true };
    case "mark": { if !(call _has) exitWith { false }; ["COMPASS"] call _show; ["mark"] call _do; call _redraw; true };
    case "find": { if !(call _has) exitWith { false }; ["PHONE"] call _show; ["find"] call _do; call _redraw; true };
    case "night": { if !(call _has) exitWith { false }; if !(_w get "open") then { _w set ["page", 0]; call _open; }; ["night"] call _do; call _redraw; true };
    case "dnd": { if !(call _has) exitWith { false }; if !(_w get "open") then { _w set ["page", 0]; call _open; }; ["dnd"] call _do; call _redraw; true };
    case "stealth": { if !(call _has) exitWith { false }; if !(_w get "open") then { _w set ["page", 0]; call _open; }; ["stealth"] call _do; call _redraw; true };
    case "hhour": { if !(call _has) exitWith { false }; ["TIMER"] call _show; ["hh"] call _do; call _redraw; true };
    case "jump": { if !(call _has) exitWith { false }; ["JUMP"] call _show; ["jump"] call _do; call _redraw; true };
    // Point envoyé par l'ATAK (clic droit sur la carte > Envoyer à la montre) : le téléphone doit être allumé.
    case "send": {
        _arg params [["_pos", []], ["_lbl", ""]];
        if (_pos isEqualTo [] || {!(call _has)}) exitWith { false };
        if !(call _linkNow) exitWith { ["Téléphone éteint ou absent", 3] call comspec_atak_native_fnc_toast; false };
        _m set ["wpt", [_pos, _lbl]];
        call _buzz;
        ["Point envoyé à la montre", 2] call comspec_atak_native_fnc_toast;
        [format ["Point reçu : %1", _lbl], "Boussole : cap et distance", 5] call _toast;
        if (_w get "open") then { _w set ["page", _pages find "COMPASS"]; _w set ["menu", -1]; _w set ["edit", false]; call _redraw; };
        true
    };
    // Heure H reçue du chef de groupe (fin < 0 : annulée). Rien ne s'ouvre : la montre vibre, le bandeau attend.
    case "hhRecv": {
        _arg params ["_end", "_from", "_id"];
        if (_end < 0) exitWith {
            _m set ["hh", []];
            if (call _has) then { call _buzz; ["Heure H annulée", _from] call _toast; call _redraw; };
            true
        };
        _m set ["hh", [_end, _from, _id]];
        if (call _has) then {
            call _buzz;
            [format ["Heure H dans %1", [_end - _now] call _fmtT], format ["Envoyée par %1", _from], 6] call _toast;
            call _redraw;
        };
        {
            _x params ["_at", "_what"];
            if (_at > _now) then {
                [{ ([time, serverTime] select isMultiplayer) >= (_this select 0) }, {
                    params ["", "_what", "_id"];
                    [_what, _id] call comspec_atak_native_fnc_watch;
                }, [_at, _what, _id]] call CBA_fnc_waitUntilAndExecute;
            };
        } forEach [[_end - 60, "hhWarn"], [_end, "hhTop"]];
        true
    };
    case "hhWarn": {
        private _hh = _m getOrDefault ["hh", []];
        if (_hh isEqualTo [] || {(_hh select 2) isNotEqualTo _arg} || {!(call _has)}) exitWith { false };
        call _buzz;
        ["Heure H dans 1 min", _hh select 1, 5] call _toast;
        true
    };
    case "hhTop": {
        private _hh = _m getOrDefault ["hh", []];
        if (_hh isEqualTo [] || {(_hh select 2) isNotEqualTo _arg}) exitWith { false };
        _m set ["hh", []];
        if !(call _has) exitWith { false };
        ["TOP ! Heure H", _hh select 1, 8] call _toast;
        call _buzz;
        [{ ["buzz"] call comspec_atak_native_fnc_watch; }, [], 0.6] call CBA_fnc_waitAndExecute;
        [{ ["buzz"] call comspec_atak_native_fnc_watch; }, [], 1.2] call CBA_fnc_waitAndExecute;
        call _redraw;
        true
    };
    // Jumpmaster armé (PFH 0,2 s, montre allumée ou non) : vibre à +300 m puis à l'altitude d'ouverture pendant la chute
    // libre ; désarmé une fois posé après une chute.
    case "jumpTick": {
        private _stop = { [_m getOrDefault ["jPfh", -1]] call CBA_fnc_removePerFrameHandler; _m set ["jPfh", -1]; };
        if (!(_m getOrDefault ["jArm", false]) || {!(call _has)}) exitWith { call _stop; false };
        private _agl = [(getPosATL player) select 2, (getPosASL player) select 2] select (surfaceIsWater (getPosASL player));
        private _vz = (velocity (vehicle player)) select 2;
        private _open = _m getOrDefault ["jOpen", 1200];
        private _stage = _m getOrDefault ["jStage", 0];
        // Chute libre : hors véhicule (pas l'avion ni le parachute ouvert), plus de 15 m/s vers le bas.
        private _free = _vz < -15 && {(vehicle player) isEqualTo player};
        if (_free && {_agl > 50}) then { _m set ["jFall", true]; };
        if (_free && {_stage < 1} && {_agl <= (_open + 300)} && {_agl > _open}) then {
            _m set ["jStage", 1];
            call _buzz;
            ["Ouverture dans 300 m", format ["%1 m sol", round _agl], 4] call _toast;
        };
        if (_free && {_stage < 2} && {_agl <= _open}) then {
            _m set ["jStage", 2];
            ["OUVREZ !", format ["%1 m sol", round _agl], 6] call _toast;
            call _buzz;
            [{ ["buzz"] call comspec_atak_native_fnc_watch; }, [], 0.4] call CBA_fnc_waitAndExecute;
            [{ ["buzz"] call comspec_atak_native_fnc_watch; }, [], 0.8] call CBA_fnc_waitAndExecute;
        };
        if ((_m getOrDefault ["jFall", false]) && {isTouchingGround player || {_agl < 1.5}} && {(vehicle player) isEqualTo player}) then {
            _m set ["jArm", false];
            ["Posé", "Jumpmaster désarmé"] call _toast;
            call _stop;
        };
        true
    };
    case "buzz": { call _buzz; true };
    case "ring": {
        // Fin du minuteur : la montre vibre trois fois ; le bandeau ne paraît que si elle est allumée (rien ne s'ouvre seul).
        ["treset"] call _do;
        if !(call _has) exitWith { false };
        ["Minuteur terminé", [_m get "tdur"] call _fmtT, 8] call _toast;
        call _buzz;
        [{ ["buzz"] call comspec_atak_native_fnc_watch; }, [], 0.8] call CBA_fnc_waitAndExecute;
        [{ ["buzz"] call comspec_atak_native_fnc_watch; }, [], 1.6] call CBA_fnc_waitAndExecute;
        call _redraw;
        true
    };
    case "tick": {
        if !(_w get "open") exitWith { false };
        if !(call _has) exitWith { call _close; false };
        private _d = uiNamespace getVariable ["COMSPEC_ATAK_WatchDisplay", displayNull];
        if (isNull _d) exitWith { false };
        private _page = _pages select ((_w get "page") min ((count _pages) - 1));
        if ((_w get "menu") >= 0) then { _page = "MENU"; };

        // --- Géométrie : montre carrée en pixels, écran rond de rayon 270/1024 de la texture ---
        private _ratio = pixelH / pixelW;
        private _big = _w get "big";
        private _size = if (_big) then { safeZoneH * 0.94 } else { safeZoneH * ([0.40, 0.50, 0.62] select ((missionNamespace getVariable ["comspec_atak_native_watch_size", 1]) max 0 min 2)) };
        private _ww = _size / _ratio;
        private _side = (missionNamespace getVariable ["comspec_atak_native_watch_side", 0]) max 0 min 2;
        private _x0 = [safeZoneX + safeZoneW * 0.02, safeZoneX + (safeZoneW - _ww) / 2, safeZoneX + safeZoneW * 0.98 - _ww] select ([_side, 1] select _big);
        private _y0 = [safeZoneY + safeZoneH - _size * 0.93, safeZoneY + (safeZoneH - _size) / 2] select _big;
        private _cx = _x0 + _ww / 2;
        private _cy = _y0 + _size / 2;
        private _ry = _size * 270 / 1024;
        private _rx = _ry / _ratio;
        private _P = { params ["_u", "_v", "_uw", "_vh"]; [_cx + _u * _rx, _cy + _v * _ry, _uw * _rx, _vh * _ry] };

        // --- Couleurs : luminosité (3 niveaux, au minimum en mode furtif) et mode nuit (tout en rouge ; aussi tout seul
        // quand le joueur met ses JVN, réglage « Rouge auto ») ---
        private _vm = currentVisionMode player;
        if (_vm isEqualType []) then { _vm = _vm param [0, 0]; };
        private _night = (_w get "night") || {(_w getOrDefault ["nvAuto", true]) && {_vm isEqualTo 1}};
        private _stealth = _w getOrDefault ["stealth", false];
        private _lum = if (_stealth) then { 0.35 } else { [0.5, 0.78, 1] select ((_w get "bright") max 0 min 2) };
        private _tone = {
            params ["_r", "_g", "_b", ["_a", 1]];
            if (_night) exitWith { private _l = (_r max _g max _b) * _lum; [_l, _l * 0.14, _l * 0.08, _a] };
            [_r * _lum, _g * _lum, _b * _lum, _a]
        };
        private _H = {
            private _c = _this call _tone;
            private _hx = "0123456789abcdef";
            private _o = "#";
            { private _v = round ((_x max 0 min 1) * 255); _o = _o + (_hx select [floor (_v / 16), 1]) + (_hx select [_v mod 16, 1]); } forEach (_c select [0, 3]);
            _o
        };
        private _cWhite = [0.95, 0.96, 0.96, 1];
        private _cGrey = [0.58, 0.6, 0.62, 1];
        private _cDim = [0.2, 0.21, 0.22, 1];
        private _cBlue = [0.18, 0.58, 1, 1];
        private _cGreen = [0.24, 0.86, 0.52, 1];
        private _cRed = [1, 0.27, 0.33, 1];
        private _cOrange = [1, 0.6, 0.16, 1];
        private _zone = { params ["_v"]; private _p = _v / 190; [1, 2, 3, 4, 5] select (([0.6, 0.7, 0.8, 0.9] findIf { _p < _x }) call { [_this, 4] select (_this < 0) }) };
        private _zoneCol = [[0.58, 0.6, 0.62, 1], [0.18, 0.58, 1, 1], [0.24, 0.86, 0.52, 1], [1, 0.6, 0.16, 1], [1, 0.27, 0.33, 1]];
        private _fR = "COMSPEC_Barlow";
        private _fB = "COMSPEC_BarlowBold";

        // --- Données ---
        private _gm = missionNamespace getVariable ["COMSPEC_ATAK_Gm", createHashMap];
        systemTime params ["_sy", "_smo", "_sd"];
        private _dk = format ["%1-%2-%3", _sy, [str _smo, "0" + str _smo] select (_smo < 10), [str _sd, "0" + str _sd] select (_sd < 10)];
        private _day = (_gm getOrDefault ["days", createHashMap]) getOrDefault [_dk, createHashMap];
        private _hr = round (_gm getOrDefault ["hr", 60]);
        private _bb = round (_gm getOrDefault ["bb", 75]);
        private _stress = round (_gm getOrDefault ["stress", 15]);
        private _steps = round (_day getOrDefault ["steps", 0]);
        private _goal = _gm getOrDefault ["goal", 10000];
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _phoneWhy = [] call comspec_atak_native_fnc_canUse;
        // La montre suit le téléphone porté (emplacement, gilet, sac), dès qu'il est allumé. Le partage Bluetooth
        // du téléphone est un autre réglage : il ne coupe pas cette liaison.
        private _link = _phoneWhy isEqualTo "" && {!_stealth};
        private _veh = vehicle player;
        private _heading = getDir _veh;
        private _alt = ((getPosASL player) select 2) + (call _drift) - (_m get "altOff");
        private _thou = {
            private _s = str (round _this);
            private _o = "";
            private _n = count _s;
            { _o = _o + _x; if (((_n - _forEachIndex - 1) mod 3) isEqualTo 0 && {_forEachIndex < (_n - 1)}) then { _o = _o + " "; }; } forEach (_s splitString "");
            _o
        };
        private _wpt = _m get "wpt";
        // Heure H en cours (fin, envoyeur) : texte « H-mm:ss ».
        private _hh = _m getOrDefault ["hh", []];
        if (_hh isNotEqualTo [] && {(_hh select 0) <= _now}) then { _hh = []; };
        private _hhTxt = if (_hh isEqualTo []) then { "" } else { format ["H-%1", [ceil ((_hh select 0) - _now)] call _fmtT] };
        // Coordonnées MGRS : zone de la carte (ACE si présent, sinon latitude / longitude de CfgWorlds) et grille 8 chiffres.
        private _mgrs = {
            private _zone = uiNamespace getVariable ["COMSPEC_ATAK_MgrsZone", ["", ""]];
            if ((_zone select 0) isNotEqualTo worldName) then {
                private _z = "";
                if (!isNil "ace_common_fnc_getMGRSdata") then {
                    private _r = [] call ace_common_fnc_getMGRSdata;
                    if (_r isEqualType [] && {(count _r) >= 2} && {(_r select 0) isEqualType ""} && {(_r select 1) isEqualType ""}) then { _z = format ["%1 %2", _r select 0, _r select 1]; };
                };
                if (_z isEqualTo "") then {
                    private _cfg = configFile >> "CfgWorlds" >> worldName;
                    private _lat = ((-(getNumber (_cfg >> "latitude"))) max -80) min 83.9;
                    private _lon = getNumber (_cfg >> "longitude");
                    _z = format ["%1%2", ((floor ((_lon + 180) / 6)) mod 60) + 1, "CDEFGHJKLMNPQRSTUVWX" select [floor ((_lat + 80) / 8), 1]];
                };
                _zone = [worldName, _z];
                uiNamespace setVariable ["COMSPEC_ATAK_MgrsZone", _zone];
            };
            format ["%1 %2", _zone select 1, [_this, 8] call comspec_atak_native_fnc_gridRef]
        };
        // Hauteur sol (au-dessus de la mer sur l'eau) et vitesse verticale, pour l'écran Jumpmaster.
        private _agl = [(getPosATL player) select 2, (getPosASL player) select 2] select (surfaceIsWater (getPosASL player));
        private _vz = (velocity _veh) select 2;

        // --- Construction de l'écran (au changement d'écran, de taille, de mode nuit ou de luminosité) ---
        // Coloris : celui de la montre portée (emplacement montre d'abord, puis objets divers).
        private _wc = (assignedItems player) select { _x isKindOf ["COMSPEC_ATAK_GarminWatch", configFile >> "CfgWeapons"] };
        if (_wc isEqualTo []) then { _wc = (items player) select { _x isKindOf ["COMSPEC_ATAK_GarminWatchItem", configFile >> "CfgWeapons"] }; };
        private _skin = if (_wc isEqualTo []) then { "" } else { getText (configFile >> "CfgWeapons" >> (_wc select 0) >> "comspec_watchFrame") };
        if (_skin isEqualTo "") then { _skin = "gw_frame"; };
        private _sig = str [_page, _size, _side, _ratio, _big, _night, _lum, _w get "zoom", _skin];
        private _n = _w getOrDefault ["ctrls", createHashMap];
        if ((_w get "built") isNotEqualTo _sig || {isNull (_n getOrDefault ["frame", controlNull])}) then {
            // 7711-7714 : boîtiers déclarés dans la couche, à garder (sinon la texture 1024 ne se charge plus).
            { if !((ctrlIDC _x) in [7711, 7712, 7713, 7714]) then { ctrlDelete _x; }; } forEach (allControls _d);
            _n = createHashMap;
            _w set ["ctrls", _n];
            _w set ["built", _sig];
            private _mk = {
                params ["_key", "_cls", "_rect"];
                private _c = _d ctrlCreate [_cls, -1];
                _c ctrlSetPosition _rect;
                _c ctrlEnable false;
                _n set [_key, _c];
                _c
            };
            // Image blanche : la classe verte du téléphone, validée avant la couleur, restait un disque vert.
            private _pic = {
                params ["_key", "_t", "_u", "_v", "_s", ["_col", [1, 1, 1, 1]]];
                private _c = [_key, "COMSPEC_RscWatchPic", [_u, _v, _s, _s] call _P] call _mk;
                _c ctrlSetText (_t call _tex);
                _c ctrlSetTextColor (_col call _tone);
                _c ctrlCommit 0;
                _c
            };
            private _txt = {
                params ["_key", "_u", "_v", "_uw", "_vh", "_fh", ["_font", _fB], ["_col", _cWhite], ["_style", 2]];
                private _c = [_key, ["COMSPEC_RscText", "COMSPEC_RscTextRight", "COMSPEC_RscTextCenter"] select ([0, 1, 2] find _style), [_u, _v, _uw, _vh] call _P] call _mk;
                _c ctrlSetFont _font;
                _c ctrlSetFontHeight (_fh * _ry);
                _c ctrlSetTextColor (_col call _tone);
                _c ctrlCommit 0;
                _c
            };
            private _box = {
                params ["_key", "_u", "_v", "_uw", "_vh", "_col"];
                private _c = [_key, "COMSPEC_RscText", [_u, _v, _uw, _vh] call _P] call _mk;
                _c ctrlSetBackgroundColor (_col call _tone);
                _c ctrlCommit 0;
                _c
            };
            private _rich = {
                params ["_key", "_u", "_v", "_uw", "_vh", "_fh"];
                private _c = [_key, "COMSPEC_RscStructuredText", [_u, _v, _uw, _vh] call _P] call _mk;
                _c ctrlSetFont _fR;
                _c ctrlSetFontHeight (_fh * _ry);
                _c ctrlCommit 0;
                _c
            };
            // Arc continu (points serrés) : de l'angle _a0 à _a1 (degrés, 0 en haut, sens horaire), _k points.
            private _arc = {
                params ["_key", "_a0", "_a1", "_k", "_r", "_s"];
                for "_i" from 0 to (_k - 1) do {
                    private _a = _a0 + (_a1 - _a0) * _i / ((_k - 1) max 1);
                    [format ["%1%2", _key, _i], "gw_disc", (_r * sin _a) - _s / 2, (-_r * cos _a) - _s / 2, _s, _cDim] call _pic;
                };
            };
            // Repère des écrans à gauche (comme le défilement des widgets Garmin).
            private _dots = {
                private _k = count _pages;
                for "_i" from 0 to (_k - 1) do {
                    private _a = 270 + (_i - (_k - 1) / 2) * (36 / ((_k - 1) max 1));
                    [format ["pg%1", _i], "gw_disc", (0.9 * sin _a) - 0.025, (-0.9 * cos _a) - 0.025, 0.05, [_cDim, _cWhite] select (_i isEqualTo (_w get "page"))] call _pic;
                };
            };
            // Fond noir de l'écran (disque), contenu, puis la montre par-dessus (l'écran de la texture est transparent).
            ["bg", "gw_disc", -1.02, -1.02, 2.04, [0.004, 0.004, 0.005, 1]] call _pic;
            (_n get "bg") ctrlSetTextColor [0.004, 0.004, 0.005, 1];
            (_n get "bg") ctrlCommit 0;
            switch (_page) do {
                case "FACE": {
                    ["bba", 212, 328, 30, 0.9, 0.07] call _arc;
                    ["sta", 148, 32, 30, 0.9, 0.07] call _arc;
                    ["bt", "gw_bt", -0.22, -0.84, 0.11] call _pic;
                    ["batI", "g_battery", -0.08, -0.84, 0.11, _cGrey] call _pic;
                    ["bat", 0.04, -0.84, 0.32, 0.11, 0.095, _fB, _cGrey, 0] call _txt;
                    ["date", -0.6, -0.68, 1.2, 0.15, 0.13, _fB, _cGrey] call _txt;
                    ["time", -0.9, -0.54, 1.8, 0.56, 0.52] call _txt;
                    {
                        _x params ["_k", "_t", "_u", "_col"];
                        [_k + "I", _t, _u - 0.065, 0.06, 0.13, _col] call _pic;
                        [_k, _u - 0.24, 0.2, 0.48, 0.17, 0.16] call _txt;
                    } forEach [["bbv", "gw_bolt", -0.44, _cBlue], ["hrv", "g_heart", 0, _cRed], ["stv", "g_steps", 0.44, _cGreen]];
                    ["sun", -0.66, 0.42, 1.32, 0.13, 0.11, _fR, _cGrey] call _txt;
                    ["foot", -0.6, 0.55, 1.2, 0.12, 0.095, _fR, _cGrey] call _txt;
                    ["moon", -0.5, 0.67, 1.0, 0.11, 0.085, _fR, _cGrey] call _txt;
                };
                case "HR": {
                    call _dots;
                    ["icon", "g_heart", -0.08, -0.84, 0.16, _cRed] call _pic;
                    ["title", -0.6, -0.67, 1.2, 0.13, 0.1, _fB, _cGrey] call _txt;
                    ["val", -0.7, -0.54, 1.1, 0.42, 0.4, _fB, _cWhite, 1] call _txt;
                    ["unit", 0.42, -0.3, 0.4, 0.16, 0.13, _fR, _cGrey, 0] call _txt;
                    ["zone", -0.7, -0.1, 1.4, 0.15, 0.12] call _txt;
                    for "_i" from 0 to 35 do { [format ["b%1", _i], -0.72 + _i * 0.04, 0.1, 0.03, 0.4, _cDim] call _box; };
                    ["foot", -0.7, 0.56, 1.4, 0.14, 0.11, _fR, _cGrey] call _txt;
                };
                case "ALT": {
                    call _dots;
                    ["icon", "gw_alt", -0.08, -0.86, 0.16, _cGreen] call _pic;
                    ["title", -0.6, -0.7, 1.2, 0.13, 0.1, _fB, _cGrey] call _txt;
                    ["val", -0.75, -0.56, 1.15, 0.4, 0.38, _fB, _cWhite, 1] call _txt;
                    ["unit", 0.42, -0.34, 0.3, 0.16, 0.13, _fR, _cGrey, 0] call _txt;
                    ["baro", -0.7, -0.15, 1.4, 0.14, 0.11, _fR, _cGrey] call _txt;
                    for "_i" from 0 to 35 do { [format ["b%1", _i], -0.72 + _i * 0.04, 0.04, 0.035, 0.42, _cDim] call _box; };
                    ["foot", -0.7, 0.5, 1.4, 0.14, 0.12] call _txt;
                    ["hint", -0.6, 0.66, 1.2, 0.11, 0.085, _fR, _cGrey] call _txt;
                };
                case "MAP": {
                    private _mc = ["map", "COMSPEC_RscMapAtak", [-0.8, -0.8, 1.6, 1.6] call _P] call _mk;
                    _mc ctrlCommit 0;
                    _mc ctrlMapAnimAdd [0, [0.012, 0.025, 0.05, 0.1] select (_w get "zoom"), [] call comspec_atak_native_fnc_gpsPos];
                    ctrlMapAnimCommit _mc;
                    _mc ctrlAddEventHandler ["Draw", { (_this select 0) call comspec_atak_native_fnc_watchMapDraw; }];
                    // Mode nuit : filtre rouge sur la carte.
                    if (_night) then { ["nf", "gw_disc", -1.02, -1.02, 2.04, [0.55, 0, 0, 0.4]] call _pic; (_n get "nf") ctrlSetTextColor [0.55, 0, 0, 0.4]; (_n get "nf") ctrlCommit 0; };
                    ["northBg", -0.32, -0.96, 0.64, 0.16, [0, 0, 0, 0.75]] call _box;
                    ["north", -0.32, -0.96, 0.64, 0.16, 0.1, _fB, _cRed] call _txt;
                    ["gridBg", -0.62, 0.56, 1.24, 0.3, [0, 0, 0, 0.72]] call _box;
                    (_n get "gridBg") ctrlSetBackgroundColor [0, 0, 0, 0.72];
                    (_n get "gridBg") ctrlCommit 0;
                    ["grid", -0.6, 0.58, 1.2, 0.13, 0.11, _fB, _cWhite] call _txt;
                    ["scale", -0.6, 0.7, 1.2, 0.12, 0.09, _fR, _cGrey] call _txt;
                    ["nofix", -0.7, -0.08, 1.4, 0.16, 0.13, _fB, _cOrange] call _txt;
                };
                case "COMPASS": {
                    for "_i" from 0 to 11 do { [format ["l%1", _i], -0.1, -0.1, 0.2, 0.16, [0.11, 0.15] select ((_i mod 3) isEqualTo 0)] call _txt; };
                    for "_i" from 0 to 35 do {
                        if ((_i mod 3) isNotEqualTo 0) then { [format ["t%1", _i], "gw_disc", 0, 0, 0.03, _cGrey] call _pic; };
                    };
                    ["idx", "gw_arrow", -0.06, -0.99, 0.12, _cRed] call _pic;
                    ["wp", "gw_disc", 0, 0, 0.1, _cBlue] call _pic;
                    ["val", -0.6, -0.36, 1.2, 0.42, 0.4] call _txt;
                    ["card", -0.6, 0.06, 1.2, 0.17, 0.15, _fB, _cOrange] call _txt;
                    ["foot", -0.55, 0.26, 1.1, 0.13, 0.1, _fB, _cBlue] call _txt;
                    ["hint", -0.4, 0.41, 0.8, 0.11, 0.085, _fR, _cGrey] call _txt;
                    ["mgrs", -0.6, 0.53, 1.2, 0.12, 0.095, _fB, _cWhite] call _txt;
                };
                case "JUMP": {
                    call _dots;
                    ["icon", "g_jump", -0.08, -0.86, 0.16, _cOrange] call _pic;
                    ["title", -0.6, -0.7, 1.2, 0.13, 0.1, _fB, _cGrey] call _txt;
                    ["val", -0.75, -0.57, 1.15, 0.4, 0.38, _fB, _cWhite, 1] call _txt;
                    ["unit", 0.42, -0.35, 0.4, 0.16, 0.12, _fR, _cGrey, 0] call _txt;
                    ["fall", -0.7, -0.15, 1.4, 0.14, 0.12, _fB, _cWhite] call _txt;
                    ["open", -0.7, 0.01, 1.4, 0.14, 0.12, _fB, _cOrange] call _txt;
                    ["dip", -0.7, 0.17, 1.4, 0.13, 0.1, _fR, _cBlue] call _txt;
                    ["state", -0.6, 0.33, 1.2, 0.14, 0.12, _fB, _cGrey] call _txt;
                    ["hint", -0.6, 0.55, 1.2, 0.11, 0.085, _fR, _cGrey] call _txt;
                };
                case "CHRONO": {
                    call _dots;
                    ["title", -0.6, -0.78, 1.2, 0.13, 0.1, _fB, _cGrey] call _txt;
                    ["val", -0.9, -0.62, 1.8, 0.4, 0.36] call _txt;
                    ["state", -0.6, -0.22, 1.2, 0.13, 0.11, _fB, _cGreen] call _txt;
                    for "_i" from 0 to 2 do {
                        ["lapL" + str _i, -0.6, -0.04 + _i * 0.15, 0.6, 0.13, 0.11, _fR, _cGrey, 0] call _txt;
                        ["lap" + str _i, 0, -0.04 + _i * 0.15, 0.6, 0.13, 0.11, _fB, _cWhite, 1] call _txt;
                    };
                    ["auto", -0.66, 0.44, 1.32, 0.12, 0.095, _fR, _cBlue] call _txt;
                    ["hint", -0.6, 0.6, 1.2, 0.11, 0.085, _fR, _cGrey] call _txt;
                };
                case "TIMER": {
                    call _dots;
                    ["ring", 0, 359, 40, 0.78, 0.05] call _arc;
                    ["title", -0.6, -0.6, 1.2, 0.13, 0.1, _fB, _cGrey] call _txt;
                    ["val", -0.8, -0.44, 1.6, 0.42, 0.4] call _txt;
                    ["state", -0.6, -0.02, 1.2, 0.13, 0.11, _fB, _cOrange] call _txt;
                    ["hint", -0.6, 0.17, 1.2, 0.11, 0.085, _fR, _cGrey] call _txt;
                    ["hhl", -0.6, 0.33, 1.2, 0.13, 0.11, _fB, _cOrange] call _txt;
                    ["hhs", -0.6, 0.46, 1.2, 0.11, 0.085, _fR, _cGrey] call _txt;
                };
                case "PHONE": {
                    call _dots;
                    ["icon", "gw_phone", -0.09, -0.86, 0.18] call _pic;
                    ["bt", "gw_bt", 0.1, -0.82, 0.11] call _pic;
                    ["state", -0.75, -0.65, 1.5, 0.15, 0.13] call _txt;
                    ["sub", -0.75, -0.49, 1.5, 0.13, 0.1, _fR, _cGrey] call _txt;
                    ["head", -0.75, -0.32, 1.5, 0.11, 0.09, _fB, _cGrey] call _txt;
                    for "_i" from 0 to 2 do {
                        [format ["sep%1", _i], -0.7, -0.19 + _i * 0.25, 1.4, 0.008, _cDim] call _box;
                        [format ["n%1", _i], -0.72, -0.17 + _i * 0.25, 1.44, 0.24, 0.1] call _rich;
                    };
                    ["hint", -0.6, 0.6, 1.2, 0.11, 0.085, _fR, _cGrey] call _txt;
                };
                case "MUSIC": {
                    call _dots;
                    ["icon", "app_music", -0.07, -0.84, 0.14, _cBlue] call _pic;
                    ["title", -0.65, -0.68, 1.3, 0.12, 0.09, _fB, _cGrey] call _txt;
                    ["track", -0.72, -0.55, 1.44, 0.2, 0.15, _fB, _cWhite] call _txt;
                    ["time", -0.7, -0.34, 1.4, 0.26, 0.24, _fB, _cWhite] call _txt;
                    for "_i" from 0 to 15 do { [format ["mb%1", _i], -0.6 + _i * 0.078, 0.0, 0.062, 0.05, _cDim] call _box; };
                    ["meta", -0.72, 0.1, 1.44, 0.12, 0.085, _fR, _cGrey] call _txt;
                    ["next", -0.72, 0.22, 1.44, 0.12, 0.085, _fR, _cGrey] call _txt;
                    ["hint", -0.74, 0.4, 1.48, 0.12, 0.078, _fR, _cGrey] call _txt;
                    ["hint2", -0.74, 0.52, 1.48, 0.12, 0.078, _fR, _cGrey] call _txt;
                };
                case "DAY": {
                    call _dots;
                    ["title", -0.6, -0.8, 1.2, 0.13, 0.11, _fB, _cGrey] call _txt;
                    {
                        _x params ["_k", "_t", "_col"];
                        private _v = -0.6 + _forEachIndex * 0.3;
                        [_k + "I", _t, -0.74, _v, 0.13, _col] call _pic;
                        [_k + "L", -0.56, _v - 0.01, 0.7, 0.15, 0.11, _fR, _cGrey, 0] call _txt;
                        [_k, -0.1, _v - 0.02, 0.84, 0.16, 0.14, _fB, _cWhite, 1] call _txt;
                        [_k + "B0", -0.56, _v + 0.16, 1.3, 0.035, [0.16, 0.17, 0.18, 1]] call _box;
                        [_k + "B1", -0.56, _v + 0.16, 0.01, 0.035, _col] call _box;
                    } forEach [["steps", "g_steps", _cGreen], ["dist", "g_walk", _cBlue], ["stress", "g_stress", _cOrange], ["bb", "gw_bolt", _cBlue]];
                    ["foot", -0.7, 0.62, 1.4, 0.13, 0.1, _fR, _cGrey] call _txt;
                };
                case "MENU": {
                    ["title", -0.6, -0.8, 1.2, 0.13, 0.1, _fB, _cGrey] call _txt;
                    ["sel", -0.86, -0.12, 1.72, 0.24, [0.13, 0.36, 0.7, 1]] call _box;
                    for "_i" from 0 to 4 do {
                        private _v = -0.6 + _i * 0.24;
                        ["row" + str _i, -0.74, _v + 0.04, 1.0, 0.16, [0.12, 0.14] select (_i isEqualTo 2), [_fR, _fB] select (_i isEqualTo 2), [_cGrey, _cWhite] select (_i isEqualTo 2), 0] call _txt;
                        ["rowV" + str _i, 0.24, _v + 0.05, 0.5, 0.14, 0.1, _fB, [_cGrey, _cWhite] select (_i isEqualTo 2), 1] call _txt;
                    };
                    ["hint", -0.5, 0.68, 1.0, 0.1, 0.08, _fR, _cGrey] call _txt;
                };
            };
            // Bandeau (notifications du téléphone, retour des actions) : caché sauf à l'arrivée.
            ["banBg", -0.8, -0.66, 1.6, 0.46, [0.13, 0.14, 0.15, 0.97]] call _box;
            ["ban", -0.74, -0.62, 1.48, 0.4, 0.105] call _rich;
            ["frame", "COMSPEC_RscWatchPic", [_x0, _y0, _ww, _size]] call _mk;
            (_n get "frame") ctrlSetText (_skin call _tex);
            (_n get "frame") ctrlSetTextColor [1, 1, 1, 1];
            (_n get "frame") ctrlCommit 0;
            { (_n get _x) ctrlShow false; } forEach ["banBg", "ban"];
            _w set ["slow", 0];
        };
        private _g = { _n getOrDefault [_this, controlNull] };
        private _set = { params ["_k", "_t"]; (_k call _g) ctrlSetText ((_t) call _tr); };
        private _col = { params ["_k", "_c"]; private _ctrl = _k call _g; _ctrl ctrlSetTextColor (_c call _tone); _ctrl ctrlCommit 0; };

        // --- Mises à jour rapides (0,1 s) : boussole, carte, chrono, minuteur ---
        if (_page isEqualTo "COMPASS") then {
            private _cards = ["N", "3", "6", "E", "12", "15", "S", "21", "24", "O", "30", "33"];
            for "_i" from 0 to 11 do {
                private _a = _i * 30 - _heading;
                private _c = format ["l%1", _i] call _g;
                _c ctrlSetPosition ([0.8 * (sin _a) - 0.1, -0.8 * (cos _a) - 0.08, 0.2, 0.16] call _P);
                _c ctrlCommit 0;
                _c ctrlSetText ([_cards select _i, (_cards select _i) call _tr] select ((_i mod 3) isEqualTo 0));
                _c ctrlSetTextColor (([_cGrey, _cWhite] select ((_i mod 3) isEqualTo 0)) call _tone);
                if (_i isEqualTo 0) then { _c ctrlSetTextColor (_cRed call _tone); };
                _c ctrlCommit 0;
            };
            for "_i" from 0 to 35 do {
                if ((_i mod 3) isNotEqualTo 0) then {
                    private _a = _i * 10 - _heading;
                    private _c = format ["t%1", _i] call _g;
                    _c ctrlSetPosition ([0.93 * (sin _a) - 0.015, -0.93 * (cos _a) - 0.015, 0.03, 0.03] call _P);
                    _c ctrlCommit 0;
                };
            };
            private _hd = (round _heading) mod 360;
            ("val" call _g) ctrlSetText format ["%1%2°", ["", "0", "00"] select (([100, 10] findIf { _hd >= _x }) call { [_this, 2] select (_this < 0) }), _hd];
            private _dirs = ["N", "NE", "E", "SE", "S", "SO", "O", "NO"];
            ["card", _dirs select ((round (_heading / 45)) mod 8)] call _set;
            private _wp = "wp" call _g;
            if (_wpt isEqualTo []) then {
                _wp ctrlShow false;
                ["foot", "Aucun point marqué"] call _set;
                ["hint", "START : marquer"] call _set;
            } else {
                private _me = [] call comspec_atak_native_fnc_gpsPos;
                private _brg = ((_me getDir (_wpt select 0)) + 360) mod 360;
                private _dist = _me distance2D (_wpt select 0);
                private _a = _brg - _heading;
                _wp ctrlShow true;
                _wp ctrlSetPosition ([0.8 * (sin _a) - 0.05, -0.8 * (cos _a) - 0.05, 0.1, 0.1] call _P);
                _wp ctrlCommit 0;
                ("foot" call _g) ctrlSetText format ["%1 %2 · %3 · %4°", "Point" call _tr, _wpt select 1,
                    [format ["%1 m", round _dist], format ["%1 km", (_dist / 1000) toFixed 2]] select (_dist >= 1000), round _brg];
                ["hint", "BACK : effacer"] call _set;
            };
        };
        if (_page isEqualTo "MAP") then {
            // Téléphone rangé : le suivi n'est plus alimenté. La carte de la montre le relit elle-même.
            private _phone = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["display", displayNull];
            if (isNull _phone && {diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_WatchBftAt", -1]) > 0.5}) then {
                uiNamespace setVariable ["COMSPEC_ATAK_WatchBftAt", diag_tickTime];
                [] call comspec_atak_native_fnc_localDataRefresh;
            };
            private _mc = "map" call _g;
            _mc ctrlMapAnimAdd [0.1, [0.012, 0.025, 0.05, 0.1] select (_w get "zoom"), [] call comspec_atak_native_fnc_gpsPos];
            ctrlMapAnimCommit _mc;
            private _hd = round (getDir (vehicle player));
            private _card = ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_hd / 45)) mod 8);
            ("north" call _g) ctrlSetText format ["%1 %2°", _card call _tr, _hd];
        };
        if (_page isEqualTo "MUSIC") then {
            private _mu = [] call comspec_atak_native_fnc_musicState;
            private _mode = _w getOrDefault ["mus", 0];
            private _clip = { params ["_t", "_n"]; if ((count _t) > _n) then { (_t select [0, (_n - 1) max 1]) + "…" } else { _t } };
            private _kind = _mu get "kind";
            private _paused = _mu get "paused";
            private _len = _mu get "len";
            private _st = _mu getOrDefault ["status", []];
            if (_len <= 0) then { _len = (_st param [2, 0]) / 1000; };
            private _pos = if (_kind isEqualTo "") then { 0 } else { if (_paused) then { _mu get "pausedAt" } else { (_now - (_mu get "start")) max 0 } };
            if (_len > 0) then { _pos = _pos min _len; };
            ["title", ["Musique", "Piste", "Avance", "Volume"] select _mode] call _set;
            private _title = if (_kind isEqualTo "") then { "Aucune lecture" } else { _mu get "title" };
            ("track" call _g) ctrlSetText (([_title, 28] call _clip) call _tr);
            ("time" call _g) ctrlSetText ([_pos] call _fmtT);
            ["time", [_cGrey, [_cBlue, _cOrange] select _paused] select (_kind isNotEqualTo "")] call _col;
            private _fill = if (_len <= 0) then { 0 } else { round ((_pos / _len) min 1) * 16 };
            private _bar = [_cDim, [_cBlue, _cOrange] select _paused] select (_kind isNotEqualTo "");
            for "_i" from 0 to 15 do { [format ["mb%1", _i], [_cDim, _bar] select (_i < _fill)] call _col; };
            private _q = _mu get "queue";
            private _nq = count _q;
            if (_nq isEqualTo 0) then {
                ["meta", "Lancez un morceau sur le téléphone"] call _set;
                ("next" call _g) ctrlSetText "";
            } else {
                ("meta" call _g) ctrlSetText format ["%1/%2 · %3 %4 %5", (_mu get "qi") + 1, _nq, (["Écouteurs", "Haut-parleur"] select (_mu get "speaker")) call _tr, _mu get "vol", "%"];
                private _ni = (_mu get "qi") + 1;
                if (_ni >= _nq) then { _ni = if ((_mu get "repeat") isEqualTo "off") then { -1 } else { 0 }; };
                if (_ni < 0 || {_ni isEqualTo (_mu get "qi")}) then {
                    ("next" call _g) ctrlSetText "";
                } else {
                    ("next" call _g) ctrlSetText format ["%1 %2", "Ensuite" call _tr, [(_q select _ni) select 2, 26] call _clip];
                };
            };
            ["hint", ["START : lecture ou pause", "UP : précédente · DOWN : suivante", "UP : -15 s · DOWN : +15 s", "UP : plus fort · DOWN : moins fort"] select _mode] call _set;
            ["hint2", ["BACK : commandes", "BACK : avance", "BACK : volume", "BACK : quitter les commandes"] select _mode] call _set;
        };
        if (_page isEqualTo "JUMP") then {
            ("val" call _g) ctrlSetText ((round _agl) call _thou);
            ("fall" call _g) ctrlSetText format ["%1 %2 km/h · %3 m/s", "Chute" call _tr, round ((-_vz max 0) * 3.6), round (-_vz max 0)];
        };
        if (_page isEqualTo "CHRONO") then {
            private _run = _m get "run";
            private _tot = (_m get "acc") + ([0, diag_tickTime - (_m get "t0")] select _run);
            ("val" call _g) ctrlSetText ([_tot, true] call _fmtT);
        };
        if (_page isEqualTo "TIMER") then {
            private _left = if ((_m get "tend") >= 0) then { ((_m get "tend") - diag_tickTime) max 0 } else { _m get "tleft" };
            ("val" call _g) ctrlSetText ([ceil _left] call _fmtT);
            private _k = round (40 * _left / ((_m get "tdur") max 1));
            for "_i" from 0 to 39 do { [format ["ring%1", _i], [_cDim, _cOrange] select (_i < _k)] call _col; };
            if (_hhTxt isEqualTo "") then {
                ("hhl" call _g) ctrlSetText "";
                ["hhs", ["Menu : heure H au groupe", ""] select ((leader group player) isNotEqualTo player)] call _set;
            } else {
                ("hhl" call _g) ctrlSetText format ["%1  %2", "Heure H" call _tr, _hhTxt];
                ("hhs" call _g) ctrlSetText format ["%1 %2", "par" call _tr, _hh select 1];
            };
        };

        // --- Bandeau : nouvelle notification du téléphone (liaison active, hors « ne pas déranger ») ou retour d'action ---
        private _seq = _st getOrDefault ["notifHistSeq", 0];
        if (_seq isNotEqualTo (_w get "seq")) then {
            _w set ["seq", _seq];
            private _h = _st getOrDefault ["notifHistory", []];
            if (_link && {!(_w get "dnd")} && {_h isNotEqualTo []}) then {
                private _last = _h select ((count _h) - 1);
                private _msg = (_last getOrDefault ["message", ""]) call _tr;
                if ((count _msg) > 90) then { _msg = (_msg select [0, 88]) + "…"; };
                _w set ["banner", [format ["ATAK  %1", _last getOrDefault ["time", ""]], _msg, diag_tickTime + 5]];
                call _buzz;
            };
        };
        (_w get "banner") params ["_bt", "_bb2", "_bu"];
        private _showBan = diag_tickTime < _bu;
        if ((ctrlShown ("ban" call _g)) isNotEqualTo _showBan) then {
            { (_x call _g) ctrlShow _showBan; } forEach ["banBg", "ban"];
        };
        if (_showBan) then {
            ("ban" call _g) ctrlSetStructuredText parseText format ["<t align='center' font='%5' color='%3'>%1</t><br/><t align='center' color='%4'>%2</t>",
                _bt, _bb2, _cBlue call _H, _cWhite call _H, _fB];
        };

        // --- Mises à jour lentes (1 s) ---
        if (diag_tickTime < (_w get "slow")) exitWith { true };
        _w set ["slow", diag_tickTime + 1];
        switch (_page) do {
            case "FACE": {
                date params ["_y", "_mo", "_dd", "_h", "_mi"];
                private _yy = _y - ([0, 1] select (_mo < 3));
                private _wd = (_yy + floor (_yy / 4) - floor (_yy / 100) + floor (_yy / 400) + ([0, 3, 2, 5, 0, 3, 5, 1, 4, 6, 2, 4] select (_mo - 1)) + _dd) mod 7;
                ("date" call _g) ctrlSetText format ["%1 %2", (["DIM.", "LUN.", "MAR.", "MER.", "JEU.", "VEN.", "SAM."] select _wd) call _tr, _dd];
                ("time" call _g) ctrlSetText format ["%1:%2", _h, [str _mi, "0" + str _mi] select (_mi < 10)];
                ["bt", [_cDim, _cBlue] select _link] call _col;
                private _wb = round (_gm getOrDefault ["wbat", 100]);
                ("bat" call _g) ctrlSetText format ["%1 %2", _wb, "%"];
                ["batI", [_cGrey, _cRed] select (_wb <= 15)] call _col;
                ("bbv" call _g) ctrlSetText str _bb;
                ("hrv" call _g) ctrlSetText str _hr;
                ("stv" call _g) ctrlSetText (_steps call _thou);
                // Lever / coucher du soleil (prochain des deux) et température : une fois par minute.
                private _sun = _w getOrDefault ["sun", [[], [-1, -1]]];
                if ((_sun select 0) isNotEqualTo [_y, _mo, _dd]) then {
                    _sun = [[_y, _mo, _dd], date call BIS_fnc_sunriseSunsetTime];
                    _w set ["sun", _sun];
                };
                (_sun select 1) params [["_rise", -1], ["_sunset", -1]];
                private _temp = ([] call comspec_atak_native_fnc_weather) param [0, 15];
                private _sunTxt = switch (true) do {
                    case (_rise < 0): { "" };
                    case (daytime < _rise || {daytime >= _sunset}): { format ["%1 %2", "Lever" call _tr, [_rise, "HH:MM"] call BIS_fnc_timeToString] };
                    default { format ["%1 %2", "Coucher" call _tr, [_sunset, "HH:MM"] call BIS_fnc_timeToString] };
                };
                ("sun" call _g) ctrlSetText ([format ["%1 · %2 °C", _sunTxt, round _temp], format ["%1 °C", round _temp]] select (_sunTxt isEqualTo ""));
                // Heure H en cours : à la place du soleil, en orange.
                if (_hhTxt isNotEqualTo "") then { ("sun" call _g) ctrlSetText format ["%1  %2", "Heure H" call _tr, _hhTxt]; };
                ["sun", [_cGrey, _cOrange] select (_hhTxt isNotEqualTo "")] call _col;
                // Lune : part éclairée (moonPhase) et croissante / décroissante (comparée au lendemain).
                private _mp = moonPhase date;
                private _mp2 = moonPhase (numberToDate [_y, ((dateToNumber date) + 1 / 365) min 0.9999]);
                ("moon" call _g) ctrlSetText format ["%1 %2 %3 · %4", "Lune" call _tr, round (_mp * 100), "%", (["décroissante", "croissante"] select (_mp2 >= _mp)) call _tr];
                private _stressTxt = format [(["Stress %1 · repos", "Stress %1 · faible", "Stress %1 · moyen", "Stress %1 · élevé"] select ([0, 1, 2, 3] select (([25, 50, 75] findIf { _stress <= _x }) call { [_this, 3] select (_this < 0) }))), _stress];
                private _mu = [] call comspec_atak_native_fnc_musicState;
                if ((_mu get "kind") isNotEqualTo "") then {
                    private _tt = _mu get "title";
                    if ((count _tt) > 22) then { _tt = (_tt select [0, 21]) + "…"; };
                    ("foot" call _g) ctrlSetText format ["%1 · %2", (["Pause", "Lecture"] select (!(_mu get "paused"))) call _tr, _tt];
                } else {
                    ("foot" call _g) ctrlSetText (_stressTxt call _tr);
                };
                for "_i" from 0 to 29 do {
                    [format ["bba%1", _i], [_cDim, _cBlue] select (_i < round (_bb / 100 * 30))] call _col;
                    [format ["sta%1", _i], [_cDim, _cGreen] select (_i < round (((_steps / (_goal max 1)) min 1) * 30))] call _col;
                };
            };
            case "HR": {
                ["title", "FRÉQUENCE CARDIAQUE"] call _set;
                ("val" call _g) ctrlSetText str _hr;
                ["unit", "bpm"] call _set;
                private _z = [_hr] call _zone;
                ["zone", format ["Zone %1 · %2", _z, (["Échauffement", "Échauffement", "Endurance", "Aérobie", "Seuil", "Maximum"] select _z) call _tr]] call _set;
                ["zone", _zoneCol select (_z - 1)] call _col;
                private _l = _gm getOrDefault ["hrl", []];
                private _sl = _l select [((count _l) - 36) max 0];
                for "_i" from 0 to 35 do {
                    private _c = format ["b%1", _i] call _g;
                    private _j = _i - (36 - count _sl);
                    if (_j < 0) then { _c ctrlShow false; } else {
                        private _v = _sl select _j;
                        private _hh = 0.02 + 0.38 * (((_v - 40) / 150) max 0 min 1);
                        _c ctrlShow true;
                        _c ctrlSetPosition ([-0.72 + _i * 0.04, 0.5 - _hh, 0.03, _hh] call _P);
                        _c ctrlCommit 0;
                        _c ctrlSetBackgroundColor ((_zoneCol select (([_v] call _zone) - 1)) call _tone);
                    };
                };
                private _rest = _day getOrDefault ["hr_rest", 999];
                ["foot", format ["Repos %1 · Max %2", [round _rest, "--"] select (_rest > 300), round (_day getOrDefault ["hr_max", _hr])]] call _set;
            };
            case "ALT": {
                ["title", "ALTIMÈTRE"] call _set;
                ("val" call _g) ctrlSetText (_alt call _thou);
                ("unit" call _g) ctrlSetText "m";
                // Baromètre : atmosphère standard, plus la météo (basse pression par temps couvert).
                private _hpa = 1013.25 * ((1 - 2.25577e-5 * ((getPosASL player) select 2)) ^ 5.25588) - 12 * overcast;
                ["baro", format ["Baromètre %1 hPa", _hpa call _thou]] call _set;
                private _l = _gm getOrDefault ["altl", []];
                private _sl = _l select [((count _l) - 36) max 0];
                private _lo = 1e9; private _hi = -1e9;
                { _lo = _lo min _x; _hi = _hi max _x; } forEach _sl;
                if (_hi - _lo < 20) then { _lo = _lo - 10; _hi = _lo + 30; };
                for "_i" from 0 to 35 do {
                    private _c = format ["b%1", _i] call _g;
                    private _j = _i - (36 - count _sl);
                    if (_j < 0) then { _c ctrlShow false; } else {
                        private _hh = 0.03 + 0.39 * ((((_sl select _j) - _lo) / (_hi - _lo)) max 0 min 1);
                        _c ctrlShow true;
                        _c ctrlSetPosition ([-0.72 + _i * 0.04, 0.46 - _hh, 0.035, _hh] call _P);
                        _c ctrlCommit 0;
                        _c ctrlSetBackgroundColor ([0.24, 0.86, 0.52, 0.85] call _tone);
                    };
                };
                ["foot", format ["D+ %1 m   D- %2 m", (_day getOrDefault ["up_m", 0]) call _thou, (_day getOrDefault ["down_m", 0]) call _thou]] call _set;
                ["hint", "START : étalonner sur le GPS"] call _set;
            };
            case "COMPASS": {
                ("mgrs" call _g) ctrlSetText (([] call comspec_atak_native_fnc_gpsPos) call _mgrs);
            };
            case "JUMP": {
                ["title", "JUMPMASTER"] call _set;
                ["unit", "m sol"] call _set;
                private _arm = _m getOrDefault ["jArm", false];
                private _edit = _w get "edit";
                ("open" call _g) ctrlSetText format ["%1 %2 m", "Ouverture" call _tr, (_m getOrDefault ["jOpen", 1200]) call _thou];
                ["open", [_cWhite, _cOrange] select _edit] call _col;
                if (_wpt isEqualTo []) then { ["dip", "Pas de point (DIP) : boussole ou ATAK"] call _set; } else {
                    private _me = [] call comspec_atak_native_fnc_gpsPos;
                    private _dist = _me distance2D (_wpt select 0);
                    ("dip" call _g) ctrlSetText format ["DIP %1 · %2 · %3°", _wpt select 1,
                        [format ["%1 m", round _dist], format ["%1 km", (_dist / 1000) toFixed 1]] select (_dist >= 1000), round (((_me getDir (_wpt select 0)) + 360) mod 360)];
                };
                ["state", switch (true) do { case (_edit): { "Réglage de l'ouverture" }; case (_arm): { "Armé" }; default { "Désarmé" }; }] call _set;
                ["state", switch (true) do { case (_edit): { _cOrange }; case (_arm): { _cGreen }; default { _cGrey }; }] call _col;
                ["hint", switch (true) do { case (_edit): { "UP / DOWN : 100 m · START : armer" }; case (_arm): { "START : désarmer" }; default { "START : régler et armer" }; }] call _set;
            };
            case "MAP": {
                private _fix = [] call comspec_atak_native_fnc_gpsFix;
                ("grid" call _g) ctrlSetText format ["%1 · ±%2 m", ([] call comspec_atak_native_fnc_gpsPos) call _mgrs, round (_fix param [0, 0])];
                // Largeur réelle affichée, mesurée sur la carte (dépend du zoom choisi avec START).
                private _mc = "map" call _g;
                (ctrlPosition _mc) params ["_mx", "_my", "_mw", "_mh"];
                private _span = (_mc ctrlMapScreenToWorld [_mx, _my + _mh / 2]) distance2D (_mc ctrlMapScreenToWorld [_mx + _mw, _my + _mh / 2]);
                private _nAlly = 0;
                {
                    if (!(_y getOrDefault ["self", false]) && {(_y getOrDefault ["affiliation", ""]) isEqualTo "friend"} && {[_y] call comspec_atak_native_fnc_bftFilter}) then { _nAlly = _nAlly + 1; };
                } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["units", createHashMap]);
                private _who = "Aucun allié";
                if (_nAlly isEqualTo 1) then { _who = "1 allié"; };
                if (_nAlly > 1) then { _who = format ["%1 alliés", _nAlly]; };
                ["scale", format ["%1 · %2 · START : zoom", _who, [format ["%1 m", round (_span / 10) * 10], format ["%1 km", (_span / 1000) toFixed 1]] select (_span >= 1000)]] call _set;
                private _ok = (_fix param [1, "FIX"]) in ["FIX", "NET"];
                ("nofix" call _g) ctrlShow !_ok;
                if (!_ok) then { ["nofix", "Recherche GPS…"] call _set; };
            };
            case "CHRONO": {
                ["title", "CHRONO"] call _set;
                private _run = _m get "run";
                ["state", ["Arrêté", "En cours"] select _run] call _set;
                ["state", [_cGrey, _cGreen] select _run] call _col;
                private _laps = _m get "laps";
                private _nl = count _laps;
                for "_i" from 0 to 2 do {
                    private _j = _nl - 1 - _i;
                    if (_j < 0) then {
                        (("lapL" + str _i) call _g) ctrlSetText "";
                        (("lap" + str _i) call _g) ctrlSetText "";
                    } else {
                        private _lt = (_laps select _j) - ([0, _laps select (_j - 1)] select (_j > 0));
                        (("lapL" + str _i) call _g) ctrlSetText format ["%1 %2", "Tour" call _tr, _j + 1];
                        (("lap" + str _i) call _g) ctrlSetText ([_lt, true] call _fmtT);
                    };
                };
                // Activité reconnue seule par la montre (fn_garminTick) : genre, distance, durée.
                private _cur = _gm getOrDefault ["cur", []];
                if (_cur isEqualTo []) then { ["auto", "Aucune activité détectée"] call _set; } else {
                    _cur params ["_kind", "_mm", "_sec"];
                    private _lbl = switch (_kind) do {
                        case "foot": { ["Marche", "Course"] select (_mm / (_sec max 1) > 2.3) };
                        case "land": { "Véhicule" }; case "air": { "Vol" }; case "halo": { "Saut" }; case "sea": { "Bateau" };
                        default { "Nage" };
                    };
                    ("auto" call _g) ctrlSetText format ["%1 · %2 · %3 km · %4", "Auto" call _tr, _lbl call _tr, (_mm / 1000) toFixed 2, [_sec] call _fmtT];
                };
                ["hint", ["START : départ · BACK : remise à zéro", "START : arrêt · BACK : tour"] select _run] call _set;
            };
            case "TIMER": {
                ["title", "MINUTEUR"] call _set;
                private _edit = _w get "edit";
                private _stTxt = switch (true) do {
                    case (_edit): { "Réglage de la durée" };
                    case ((_m get "tend") >= 0): { "En cours" };
                    case ((_m get "tleft") < (_m get "tdur")): { "En pause" };
                    default { "Prêt" };
                };
                ["state", _stTxt] call _set;
                ["hint", switch (true) do {
                    case (_edit): { "UP / DOWN : durée · START : lancer" };
                    case ((_m get "tend") >= 0): { "START : pause · BACK : remise à zéro" };
                    case ((_m get "tleft") < (_m get "tdur")): { "START : reprendre · BACK : remise à zéro" };
                    default { "START : régler et lancer" };
                }] call _set;
                ["val", [_cWhite, _cOrange] select _edit] call _col;
            };
            case "PHONE": {
                ["icon", [_cGrey, _cGreen] select _link] call _col;
                ["bt", [_cDim, _cBlue] select _link] call _col;
                ["state", ["Téléphone déconnecté", "ATAK connecté"] select _link] call _set;
                ["state", [_cGrey, _cGreen] select _link] call _col;
                private _sub = switch (true) do {
                    case (_stealth): { "Mode furtif : Bluetooth coupé" };
                    case (_phoneWhy isEqualTo "item"): { "Pas de téléphone ATAK sur vous" };
                    case (_phoneWhy isEqualTo "battery"): { "Batterie du téléphone vide" };
                    case (_phoneWhy isEqualTo "broken"): { "Téléphone détruit" };
                    case (_phoneWhy isNotEqualTo ""): { "Téléphone éteint" };
                    default { format ["Batterie du téléphone %1 %2", round (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]), "%"] };
                };
                ["sub", _sub] call _set;
                ["head", "NOTIFICATIONS"] call _set;
                private _h = (_st getOrDefault ["notifHistory", []]) select { _link };
                private _last = (_h select [((count _h) - 3) max 0]);
                reverse _last;
                for "_i" from 0 to 2 do {
                    private _c = format ["n%1", _i] call _g;
                    (format ["sep%1", _i] call _g) ctrlShow (_i < count _last);
                    if (_i < count _last) then {
                        private _e = _last select _i;
                        private _msg = (_e getOrDefault ["message", ""]) call _tr;
                        if ((count _msg) > 70) then { _msg = (_msg select [0, 68]) + "…"; };
                        _c ctrlSetStructuredText parseText format ["<t color='%3' size='0.85'>%1</t>  <t color='%4'>%2</t>", _e getOrDefault ["time", ""], _msg, _cGrey call _H, _cWhite call _H];
                    } else {
                        _c ctrlSetStructuredText parseText (if (_i isEqualTo 0) then { format ["<t align='center' color='%2'>%1</t>", "Aucune notification" call _tr, _cGrey call _H] } else { "" });
                    };
                };
                ["hint", "START : faire sonner le téléphone"] call _set;
            };
            case "DAY": {
                ["title", "MA JOURNÉE"] call _set;
                private _foot = _day getOrDefault ["foot_m", 0];
                private _rows = [
                    ["steps", "Pas", format ["%1 / %2", _steps call _thou, _goal call _thou], _steps / (_goal max 1)],
                    ["dist", "Distance", format ["%1 km", (_foot / 1000) toFixed 1], _foot / 10000],
                    ["stress", "Stress", str _stress, _stress / 100],
                    ["bb", "Body Battery", str _bb, _bb / 100]
                ];
                {
                    _x params ["_k", "_lbl", "_val", "_p"];
                    [_k + "L", _lbl] call _set;
                    (_k call _g) ctrlSetText _val;
                    private _b = (_k + "B1") call _g;
                    private _r = ctrlPosition ((_k + "B0") call _g);
                    _b ctrlSetPosition [_r select 0, _r select 1, ((_r select 2) * (_p max 0.01 min 1)), _r select 3];
                    _b ctrlCommit 0;
                } forEach _rows;
                private _sl = round ((_day getOrDefault ["sleep_s", 0]) / 60);
                ["foot", format ["Sommeil / repos %1 h %2", floor (_sl / 60), [str (_sl mod 60), "0" + str (_sl mod 60)] select ((_sl mod 60) < 10)]] call _set;
            };
            case "MENU": {
                ["title", "COMMANDES"] call _set;
                private _sel = _w get "menu";
                for "_i" from 0 to 4 do {
                    private _j = _sel - 2 + _i;
                    if (_j < 0 || {_j >= (count _menuItems)}) then {
                        (("row" + str _i) call _g) ctrlSetText "";
                        (("rowV" + str _i) call _g) ctrlSetText "";
                    } else {
                        (_menuItems select _j) params ["_id", "_lbl"];
                        ["row" + str _i, _lbl] call _set;
                        private _v = switch (_id) do {
                            case "big": { ["Non", "Oui"] select (_w get "big") };
                            case "night": { ["Non", "Oui"] select (_w get "night") };
                            case "dnd": { ["Non", "Oui"] select (_w get "dnd") };
                            case "nvAuto": { ["Non", "Oui"] select (_w getOrDefault ["nvAuto", true]) };
                            case "stealth": { ["Non", "Oui"] select _stealth };
                            case "hh": { _hhTxt };
                            case "bright": { ["Faible", "Moyenne", "Maximale"] select (_w get "bright") };
                            case "mark": { ["", "1 point"] select ((_m get "wpt") isNotEqualTo []) };
                            default { "" };
                        };
                        ["rowV" + str _i, _v] call _set;
                    };
                };
                ["hint", "START : valider · BACK : fermer"] call _set;
            };
        };
        true
    };
    default { false };
};
