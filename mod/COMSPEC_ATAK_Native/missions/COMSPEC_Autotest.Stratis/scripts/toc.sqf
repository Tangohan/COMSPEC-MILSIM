// Salle TOC (fn_tocSat, fn_tocBoard, fn_tocRoom, fn_tocPersist) : trois écrans posés devant le joueur en vue satellite,
// horloge et escouade, un groupe allié équipé d'ATAK à 90 m pour le suivi, puis l'onglet SAT du PC du poste.
// Les captures servent à juger l'image satellite dans la texture UI et l'alignement des cadres sur les unités.
// Groupes : « toc » (toutes les étapes), « tocmos » (mosaïque seule), « tocsalle » (salle complète seule).
if !(("toc" call AT_fnc_groupe) || {"tocmos" in AT_Groupes} || {"tocsalle" in AT_Groupes}) exitWith {};
AT_TocAll = "toc" call AT_fnc_groupe;
// Images par seconde moyennes sur une durée (relevé toutes les 0,25 s).
AT_fnc_fps = {
    params [["_dur", 10]];
    private _n = 0; private _sum = 0; private _min = 1000;
    private _end = diag_tickTime + _dur;
    while { diag_tickTime < _end } do { _sum = _sum + diag_fps; _min = _min min diag_fps; _n = _n + 1; uiSleep 0.25; };
    [round (_sum / (_n max 1)), round _min]
};

if (AT_TocAll) then {
["toc_salle", {
    if (["comspec_atak_native_fnc_tocSat", "comspec_atak_native_fnc_tocBoard", "comspec_atak_native_fnc_tocRoom"] call AT_fnc_nil) exitWith {};
    if !("COMSPEC_ATAK_Phone" in (items player)) then { player addItem "COMSPEC_ATAK_Phone"; };
    private _side = str side group player;
    private _base = getPosATL player;
    private _dir = getDir player;
    // Terrain dégagé (rien entre le joueur et les écrans : un buisson masquait l'écran satellite au premier passage).
    private _open = [getPos player, 0, 150, 12, 0, 0.15, 0] call BIS_fnc_findSafePos;
    player setPosATL [_open select 0, _open select 1, 0];
    // Groupe allié « ALPHA TOC » à 90 m devant, sur un terrain libre, chaque membre avec un téléphone ATAK.
    private _gp = (player getPos [90, _dir]);
    _gp = [_gp, 0, 60, 4, 0, 0.3, 0] call BIS_fnc_findSafePos;
    private _g = createGroup [side group player, true];
    { private _u = _g createUnit ["B_Soldier_F", [(_gp select 0) + _x, (_gp select 1), 0], [], 0, "NONE"]; _u addItem "COMSPEC_ATAK_Phone"; _u disableAI "PATH"; } forEach [0, 4, 8];
    _g setGroupIdGlobal ["ALPHA TOC"];
    // Trois écrans en arc devant le joueur, à 5 m, tournés vers lui.
    private _scr = [];
    {
        _x params ["_off", "_view"];
        private _p = player getPos [5, _dir + _off];
        private _o = ["spawn", [[_p select 0, _p select 1, 0], (_p getDir player), "COMSPEC_ATAK_Screen_Tripod", format ["TEST %1", _view], "", _view, true, 0, "ALPHA TOC"]] call comspec_atak_native_fnc_tocScreen;
        _scr pushBack _o;
    } forEach [[-35, "SAT"], [0, "CLOCK"], [35, "SQUAD"]];
    [(_scr findIf { isNull _x }) < 0, "écran non posé"] call AT_fnc_check;
    // Chronos du poste et satellite : pris en main par le joueur, centré sur le groupe, suivi du groupe.
    ["do", [_side, "timerAdd", ["H-HOUR", "AT", ((systemTimeUTC select 3) * 60 + (systemTimeUTC select 4) + 20) mod 1440], "Autotest", getPlayerUID player]] call comspec_atak_native_fnc_tocRoom;
    ["do", [_side, "timerAdd", ["RELÈVE", "DOWN", 1800], "Autotest", getPlayerUID player]] call comspec_atak_native_fnc_tocRoom;
    ["do", [_side, "timerAdd", ["ALPHA EN POSITION", "UP", 0], "Autotest", getPlayerUID player]] call comspec_atak_native_fnc_tocRoom;
    private _tm = (["state", _side] call comspec_atak_native_fnc_tocRoom) select 1;
    [(count _tm) >= 3, format ["chronos créés : %1", count _tm]] call AT_fnc_check;
    { if ((_x select 2) isEqualTo "UP") then { ["do", [_side, "timerStart", _x select 0, "Autotest", getPlayerUID player]] call comspec_atak_native_fnc_tocRoom; }; } forEach _tm;
    ["do", [_side, "sat", [0, "take", [_gp select 0, _gp select 1]], "Autotest", getPlayerUID player]] call comspec_atak_native_fnc_tocRoom;
    ["do", [_side, "sat", [0, "center", [_gp select 0, _gp select 1]], "Autotest", getPlayerUID player]] call comspec_atak_native_fnc_tocRoom;
    ["do", [_side, "sat", [0, "zoom", 0.25], "Autotest", getPlayerUID player]] call comspec_atak_native_fnc_tocRoom;
    private _s0 = ((["state", _side] call comspec_atak_native_fnc_tocRoom) select 2) select 0;
    [(_s0 select 7) isEqualTo getPlayerUID player, "satellite non pris en main"] call AT_fnc_check;
    format ["satellite 1 : %1", _s0] call AT_fnc_info;
    // Chaque écran regardé de face : satellite (jour puis nuit), horloge, escouade.
    private _look = {
        params ["_o"];
        player setDir (player getDir _o);
        uiSleep 6;
    };
    [_scr select 0] call _look;
    [{ (["rt", [_side, 0]] call comspec_atak_native_fnc_tocSat) isNotEqualTo "" }, 15] call AT_fnc_wait;
    [(["rt", [_side, 0]] call comspec_atak_native_fnc_tocSat) isNotEqualTo "", "satellite non rendu sur ce poste (écran SAT regardé à 5 m)"] call AT_fnc_check;
    [((_scr select 0) getVariable ["COMSPEC_ATAK_TocTex", ""]) isNotEqualTo "", "texture UI de l'écran SAT absente"] call AT_fnc_check;
    private _t = ["targets", _side] call comspec_atak_native_fnc_tocSat;
    format ["nœuds suivis : %1", _t apply { [_x select 1, _x select 2] }] call AT_fnc_info;
    private _pr = (_t select { (_x select 6) isEqualTo "ALPHA TOC" }) apply { ["project", [_side, 0, _x select 4]] call comspec_atak_native_fnc_tocSat };
    format ["projection des membres d'ALPHA TOC (u, v, largeur) : %1", _pr] call AT_fnc_info;
    "écran SAT : image aérienne en direct, cadres bleus et indicatifs sur les trois soldats d'ALPHA TOC, bandeau Lecture seule" call AT_fnc_visuel;
    ["toc_sat_jour"] call AT_fnc_shot;
    ["do", [_side, "sat", [0, "mode", "TI"], "Autotest", getPlayerUID player]] call comspec_atak_native_fnc_tocRoom;
    uiSleep 3;
    ["toc_sat_thermique"] call AT_fnc_shot;
    [_scr select 1] call _look;
    "écran horloge : heure ZULU et locale en grand, trois chronos (H-HOUR en H-, RELÈVE en -, ALPHA EN POSITION en marche)" call AT_fnc_visuel;
    ["toc_horloge"] call AT_fnc_shot;
    [_scr select 2] call _look;
    "écran d'escouade : plaque ALPHA TOC, trois membres en direct, carte cadrée sur eux" call AT_fnc_visuel;
    ["toc_escouade"] call AT_fnc_shot;
    // PC du poste, onglet SAT : image à la place de la carte, cadres par-dessus.
    ["open", _scr select 1] call comspec_atak_native_fnc_tocLaptop;
    [{ !isNull (uiNamespace getVariable ["COMSPEC_ATAK_TocLapDisp", displayNull]) }, 6] call AT_fnc_wait;
    [!isNull (uiNamespace getVariable ["COMSPEC_ATAK_TocLapDisp", displayNull]), "PC du poste non ouvert"] call AT_fnc_check;
    // Plein jour : image en couleurs (la vision nocturne sature en blanc sous le soleil), puis thermique.
    ["do", [_side, "sat", [0, "mode", "DAY"], "Autotest", getPlayerUID player]] call comspec_atak_native_fnc_tocRoom;
    ["tab", "SAT"] call comspec_atak_native_fnc_tocLaptop;
    uiSleep 4;
    "PC du poste, onglet SAT : image satellite en couleurs puis thermique, cadres sur ALPHA TOC, bandeau Vous avez la main" call AT_fnc_visuel;
    ["toc_pc_sat"] call AT_fnc_shot;
    ["do", [_side, "sat", [0, "mode", "TI"], "Autotest", getPlayerUID player]] call comspec_atak_native_fnc_tocRoom;
    uiSleep 2;
    ["toc_pc_sat_thermique"] call AT_fnc_shot;
    ["tab", "CHRONOS"] call comspec_atak_native_fnc_tocLaptop;
    uiSleep 1.5;
    ["toc_pc_chronos"] call AT_fnc_shot;
    closeDialog 0;
    uiSleep 1;
    // Sauvegarde : écriture immédiate, format 2, trois écrans et l'état du camp.
    ["write"] call comspec_atak_native_fnc_tocPersist;
    private _sv = profileNamespace getVariable [format ["COMSPEC_ATAK_TocSave_%1_%2", worldName, missionName], []];
    [(_sv param [0, 0]) isEqualTo 2, format ["sauvegarde au format %1", _sv param [0, 0]]] call AT_fnc_check;
    [({ ((_x select 4) param [3, ""]) in ["SAT", "CLOCK", "SQUAD"] } count (_sv param [2, []])) >= 3, "écrans absents de la sauvegarde"] call AT_fnc_check;
    [((_sv param [4, []]) findIf { (_x select 0) isEqualTo _side }) >= 0, "état du poste absent de la sauvegarde"] call AT_fnc_check;
    // Ménage : écrans, groupe, état du poste et sauvegarde de test.
    { ["remove", _x] call comspec_atak_native_fnc_tocScreen; } forEach _scr;
    { deleteVehicle _x; } forEach (units _g);
    deleteGroup _g;
    missionNamespace setVariable [format ["COMSPEC_ATAK_TocRoom_%1", _side], nil, true];
    ["clear"] call comspec_atak_native_fnc_tocPersist;
    player setPosATL _base;
    player setDir _dir;
    call AT_fnc_done;
}, 240] call AT_fnc_run;
};

// Murs à plusieurs dalles (un affichage par dalle), bandeau horloge 4:1 et plaque de nom 4:1, de jour puis de nuit :
// les dalles d'index > 0 doivent montrer leur image de jour comme de nuit.
if (AT_TocAll) then {
["toc_murs", {
    if (["comspec_atak_native_fnc_tocBoard"] call AT_fnc_nil) exitWith {};
    if !(isClass (configFile >> "CfgVehicles" >> "COMSPEC_ATAK_Screen_Wall2x2_Quad")) exitWith { "murs 3D absents de ce build" call AT_fnc_skip; };
    private _base = getPosATL player;
    private _dir = getDir player;
    private _date = date;
    private _open = [getPos player, 0, 150, 14, 0, 0.15, 0] call BIS_fnc_findSafePos;
    player setPosATL [_open select 0, _open select 1, 0];
    private _g = createGroup [side group player, true];
    { private _u = _g createUnit ["B_Soldier_F", player getPos [60 + _x, _dir], [], 0, "NONE"]; _u addItem "COMSPEC_ATAK_Phone"; _u disableAI "PATH"; } forEach [0, 4];
    _g setGroupIdGlobal ["BRAVO TOC"];
    private _put = {
        params ["_cls", "_off", "_dist", "_view", "_squad", ["_pans", ""]];
        private _p = player getPos [_dist, _dir + _off];
        ["spawn", [[_p select 0, _p select 1, 0], (_p getDir player), _cls, "MUR", "", _view, false, 0, _squad, _pans]] call comspec_atak_native_fnc_tocScreen
    };
    private _wall = ["COMSPEC_ATAK_Screen_Wall2x2_Quad", 0, 7, "SPLIT", "", "SAT1, CLOCK, SQ:BRAVO TOC"] call _put;
    private _strip = ["COMSPEC_ATAK_Screen_ClockStrip", -40, 5, "STRIP", ""] call _put;
    private _plate = ["COMSPEC_ATAK_Screen_NamePlate", 40, 5, "PLATE", "BRAVO TOC"] call _put;
    uiSleep 2;
    private _px = _wall getVariable ["COMSPEC_ATAK_TocPanels", []];
    [(count _px) isEqualTo 3, format ["relais des dalles : %1 au lieu de 3", count _px]] call AT_fnc_check;
    format ["dalles : %1", _px apply { [_x getVariable ["COMSPEC_ATAK_TocPanel", -1], (_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]] }] call AT_fnc_info;
    [((_strip getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isEqualTo "STRIP", "bandeau sans affichage STRIP"] call AT_fnc_check;
    [((_plate getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isEqualTo "PLATE", "plaque sans affichage PLATE"] call AT_fnc_check;
    ["do", [str side group player, "sat", [0, "center", getPos (leader _g)], "Autotest", getPlayerUID player]] call comspec_atak_native_fnc_tocRoom;
    ["do", [str side group player, "sat", [0, "zoom", 0.25], "Autotest", getPlayerUID player]] call comspec_atak_native_fnc_tocRoom;
    private _look = { params ["_o"]; player setDir (player getDir _o); uiSleep 8; };
    [_wall] call _look;
    format ["matières des dalles (jour) : %1", getObjectMaterials _wall] call AT_fnc_info;
    format ["textures des dalles (jour) : %1", getObjectTextures _wall] call AT_fnc_info;
    "mur 2x2 de jour : dalle 1 carte et flux, dalle 2 satellite, dalle 3 horloge, dalle 4 escouade BRAVO TOC, toutes allumées" call AT_fnc_visuel;
    ["toc_mur_jour"] call AT_fnc_shot;
    [_strip] call _look;
    "bandeau 4:1 : heure ZULU en rouge et heure locale en jaune, lisibles" call AT_fnc_visuel;
    ["toc_bandeau"] call AT_fnc_shot;
    [_plate] call _look;
    "plaque 4:1 : BRAVO TOC en grand, nœuds ATAK dessous" call AT_fnc_visuel;
    ["toc_plaque"] call AT_fnc_shot;
    // Nuit : matière de nuit sur chaque dalle.
    setDate [_date select 0, _date select 1, _date select 2, 23, 0];
    [_wall] call _look;
    uiSleep 4;
    format ["matières des dalles (nuit) : %1", getObjectMaterials _wall] call AT_fnc_info;
    "mur 2x2 de nuit : les quatre dalles allumées" call AT_fnc_visuel;
    ["toc_mur_nuit"] call AT_fnc_shot;
    setDate _date;
    uiSleep 1;
    { ["remove", _x] call comspec_atak_native_fnc_tocScreen; } forEach [_wall, _strip, _plate];
    { deleteVehicle _x; } forEach (units _g);
    deleteGroup _g;
    missionNamespace setVariable [format ["COMSPEC_ATAK_TocRoom_%1", str side group player], nil, true];
    player setPosATL _base;
    player setDir _dir;
    call AT_fnc_done;
}, 200] call AT_fnc_run;
};

// Mosaïque de caméras : 9 drones du camp en vol, écran en 2x2 puis 3x3 (plafond de 8 vignettes : la 9e passe à son
// tour), puis choix case par case avec une source détruite (PAS DE SIGNAL). Images par seconde relevées à chaque fois.
if (AT_TocAll || {"tocmos" in AT_Groupes}) then {
["toc_mosaique", {
    if (["comspec_atak_native_fnc_tocMosaic"] call AT_fnc_nil) exitWith {};
    // Le poste reçoit les drones par l'ATAK le plus proche : le joueur porte un téléphone.
    if !("COMSPEC_ATAK_Phone" in (items player)) then { player addItem "COMSPEC_ATAK_Phone"; };
    private _base = getPosATL player;
    private _dir = getDir player;
    private _open = [getPos player, 0, 250, 14, 0, 0.05, 0] call BIS_fnc_findSafePos;
    player setPosATL [_open select 0, _open select 1, 0];
    private _c = player getPos [80, _dir];
    private _uavs = [];
    for "_i" from 0 to 8 do {
        private _p = _c getPos [25, _i * 40];
        private _u = createVehicle ["B_UAV_01_F", [_p select 0, _p select 1, 40], [], 0, "FLY"];
        createVehicleCrew _u;
        _u flyInHeight 40;
        _u setDir (_i * 40);
        _uavs pushBack _u;
    };
    private _p = player getPos [5, _dir];
    private _o = ["spawn", [[_p select 0, _p select 1, 0], (_p getDir player), "COMSPEC_ATAK_Screen_Tripod", "MOSAÏQUE", "", "SPLIT", false]] call comspec_atak_native_fnc_tocScreen;
    uiSleep 1;
    ["apply", [_o, "MOS|2|AUTO"]] call comspec_atak_native_fnc_tocScreen;
    uiSleep 2;
    private _src = ["sources"] call comspec_atak_native_fnc_tocCam;
    [(count _src) >= 9, format ["sources de caméras : %1 au lieu de 9 drones au moins", count _src]] call AT_fnc_check;
    player setDir (_dir + 180);
    uiSleep 3;
    private _f0 = [8] call AT_fnc_fps;
    format ["FPS sans écran regardé : moyenne %1, minimum %2", _f0 select 0, _f0 select 1] call AT_fnc_info;
    player setDir (player getDir _o);
    uiSleep 8;
    private _f4 = [10] call AT_fnc_fps;
    private _live4 = { !isNull (_x select 0) } count (values (uiNamespace getVariable ["COMSPEC_ATAK_TocMosCams", createHashMap]));
    format ["FPS mosaïque 2x2 regardée : moyenne %1, minimum %2, caméras rendues %3", _f4 select 0, _f4 select 1, _live4] call AT_fnc_info;
    [_live4 >= 4, format ["mosaïque 2x2 : %1 caméra(s) rendue(s) au lieu de 4", _live4]] call AT_fnc_check;
    "mosaïque 2x2 : quatre vues de drones, indicatif, état de la liaison et heure ZULU sur chaque vignette" call AT_fnc_visuel;
    ["toc_mosaique_2x2"] call AT_fnc_shot;
    ["apply", [_o, "MOS|3|AUTO"]] call comspec_atak_native_fnc_tocScreen;
    uiSleep 8;
    private _f9 = [10] call AT_fnc_fps;
    private _live9 = { !isNull (_x select 0) } count (values (uiNamespace getVariable ["COMSPEC_ATAK_TocMosCams", createHashMap]));
    format ["FPS mosaïque 3x3 regardée : moyenne %1, minimum %2, caméras rendues %3 (plafond %4)", _f9 select 0, _f9 select 1, _live9, ["budget"] call comspec_atak_native_fnc_tocMosaic] call AT_fnc_info;
    "mosaïque 3x3 : huit vues, la neuvième « À son tour dans un instant » ou en vue (passage à tour de rôle)" call AT_fnc_visuel;
    ["toc_mosaique_3x3"] call AT_fnc_shot;
    // Case par case : deux drones choisis, une case libre, un drone détruit (pas de signal).
    private _ids = _uavs apply { netId _x };
    ["view", [_o, "mosaic", [2, "MANUAL", "", [_ids select 0, "", _ids select 2, _ids select 3], 0]]] call comspec_atak_native_fnc_tocScreen;
    uiSleep 1;
    { deleteVehicle _x; } forEach (crew (_uavs select 3)); deleteVehicle (_uavs select 3);
    uiSleep 5;
    "mosaïque case par case : deux vues, une CASE LIBRE, une PAS DE SIGNAL" call AT_fnc_visuel;
    ["toc_mosaique_manuelle"] call AT_fnc_shot;
    ["remove", _o] call comspec_atak_native_fnc_tocScreen;
    { if (!isNull _x) then { { deleteVehicle _x; } forEach (crew _x); deleteVehicle _x; }; } forEach _uavs;
    player setPosATL _base;
    player setDir _dir;
    call AT_fnc_done;
}, 200] call AT_fnc_run;
};

// Salle TOC complète (fn_tocHall, fn_tocHallUi) de nuit : pose par le module, vue d'ensemble, chaque affichage de près,
// satellite zoomé sur des soldats (alignement des cadres), PC maître onglet par onglet avec des données réalistes, menu
// ACE ouvert, actions ACE exécutées et contrôlées, puis sauvegarde -> tout supprimé -> reprise, comparée.
if (AT_TocAll || {"tocsalle" in AT_Groupes}) then {
["toc_salle_complete", {
    if (["comspec_atak_native_fnc_tocHall", "comspec_atak_native_fnc_tocHallUi"] call AT_fnc_nil) exitWith {};
    if !("COMSPEC_ATAK_Phone" in (items player)) then { player addItem "COMSPEC_ATAK_Phone"; };
    private _side = str side group player;
    private _base = getPosATL player;
    private _dir0 = getDir player;
    private _date = date;
    private _open = [getPos player, 0, 300, 18, 0, 0.04, 0] call BIS_fnc_findSafePos;
    private _o0 = [_open select 0, _open select 1, 0];
    player setPosATL _o0;
    // Deux groupes alliés équipés d'ATAK : ALPHA à 120 m (satellite), BRAVO plus loin (plaques, escouade).
    private _mkGrp = {
        params ["_name", "_dist", "_ang", "_n"];
        private _g = createGroup [side group player, true];
        private _c = _o0 getPos [_dist, _dir0 + _ang];
        for "_i" from 0 to (_n - 1) do { private _u = _g createUnit ["B_Soldier_F", _c getPos [_i * 5, 90], [], 0, "NONE"]; _u addItem "COMSPEC_ATAK_Phone"; _u disableAI "PATH"; };
        _g setGroupIdGlobal [_name];
        _g
    };
    private _ga = ["ALPHA", 120, 30, 3] call _mkGrp;
    private _gb = ["BRAVO", 300, -40, 4] call _mkGrp;
    setDate [_date select 0, _date select 1, _date select 2, 23, 10];
    uiSleep 4;
    private _hid = ["spawnRoom", [_o0, _dir0, "M", "TOC TEST", _side, ""]] call comspec_atak_native_fnc_tocHall;
    uiSleep 3;
    private _scr = ["screens", _hid] call comspec_atak_native_fnc_tocHall;
    private _hall = ["get", _hid] call comspec_atak_native_fnc_tocHall;
    private _pcs = ((_hall param [4, []]) select { _x select 4 }) apply { ((nearestObjects [ASLToAGL (_x select 1), [_x select 0], 1]) param [0, objNull]) };
    format ["salle %1 : %2 écran(s) lié(s), %3 objet(s) de mobilier, %4 PC", _hid, count _scr, count (_hall param [4, []]), count _pcs] call AT_fnc_info;
    [(count _scr) >= 9, format ["salle moyenne : %1 écran(s) au lieu de 9 au moins", count _scr]] call AT_fnc_check;
    [(count _pcs) isEqualTo 3, format ["salle moyenne : %1 PC au lieu de 3", count _pcs]] call AT_fnc_check;
    { format ["écran %1 · %2 · %3 dalle(s)", typeOf _x, (_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""], count (_x getVariable ["COMSPEC_ATAK_TocPanels", []])] call AT_fnc_info; } forEach _scr;
    // Données réalistes au poste.
    private _do = { params ["_act", "_args"]; ["do", [_side, _act, _args, "TOC TEST · Autotest", getPlayerUID player]] call comspec_atak_native_fnc_tocRoom; };
    ["timerAdd", ["H-HOUR", "AT", ((systemTimeUTC select 3) * 60 + (systemTimeUTC select 4) + 25) mod 1440]] call _do;
    ["timerAdd", ["RELÈVE ALPHA", "DOWN", 1500]] call _do;
    ["log", ["NOTE", "ALPHA en position sur la crête nord, rien à signaler"]] call _do;
    ["exSet", [["Franchissement LD", "ROMEO", "ALPHA", "23:20"], ["Prise du carrefour", "SIERRA", "BRAVO", "23:35"], ["Objectif bouclé", "TANGO", "ALPHA", "23:50"], ["Exfiltration", "UNIFORM", "TOUS", "00:30"]]] call _do;
    private _ex = (["state", _side] call comspec_atak_native_fnc_tocRoom) select 4;
    ["exDone", (_ex select 0) select 0] call _do;
    ["comspec_atak_native_medevac", [createHashMapFromArray [["from", "BRAVO 2"], ["uid", ""], ["grid", "031058"], ["t1", "1"], ["t2", "2"], ["t3", "0"], ["rem", "fumée verte"], ["pos", getPos (leader _gb)]], _side]] call CBA_fnc_localEvent;
    ["comspec_atak_native_bda", [createHashMapFromArray [["by", "ALPHA 1"], ["ekia", "3"], ["grid", "032061"]], _side]] call CBA_fnc_localEvent;
    ["comspec_atak_native_alert", ["MAN DOWN", "BRAVO 3", getPos (leader _gb), "031058", "blessé au bras", _side]] call CBA_fnc_localEvent;
    ["comspec_atak_native_logi", ["NEW", _side, [["cs", "ALPHA 2"], ["uid", ""], ["grid", "033060"], ["label", "Munitions 5,56 mm"], ["qty", "4"], ["note", "caisse au point B"], ["pos", getPos (leader _ga)]]]] call CBA_fnc_localEvent;
    uiSleep 1;
    private _rq = (["state", _side] call comspec_atak_native_fnc_tocRoom) select 5;
    [(count _rq) >= 2, format ["demandes reçues : %1 au lieu de 2 (MEDEVAC, ravitaillement)", count _rq]] call AT_fnc_check;
    ["reqSet", [(_rq select 0) select 0, "PRISE", "hélico décolle, ETA 8 min"]] call _do;
    private _kc = (["state", _side] call comspec_atak_native_fnc_tocRoom) select 8;
    format ["pertes : %1", _kc] call AT_fnc_info;
    [(count _kc) >= 2, "pertes et résultats : homme à terre et BDA non comptés"] call AT_fnc_check;
    ["sat", [0, "take", getPos (leader _ga)]] call _do;
    ["sat", [0, "center", getPos (leader _ga)]] call _do;
    ["sat", [0, "zoom", 0.07]] call _do;
    ["sat", [0, "mode", "TI"]] call _do;
    // Vue d'ensemble depuis l'arrière des postes.
    private _at = { params ["_x0", "_y0"]; private _p = _o0 getPos [sqrt (_x0 ^ 2 + _y0 ^ 2), _dir0 + (_x0 atan2 _y0)]; [_p select 0, _p select 1, 0] };
    player setPosATL ([0, -1.5] call _at);
    player setDir _dir0;
    uiSleep 12;
    "salle de nuit : mur 3x2 (carte, SAT 1, SAT 2, horloge, main courante, demandes), deux 98 pouces (mosaïque, escouade), bandeau horloge, plaques, bureaux et PC" call AT_fnc_visuel;
    ["toc_salle_ensemble"] call AT_fnc_shot;
    // Chaque écran de près.
    {
        private _o = _x;
        private _v = (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""];
        private _d = [3.2, 4.2] select ((getNumber (configOf _o >> "comspec_screenPanels")) > 1);
        (boundingBoxReal _o) params ["_p1", "_p2"];
        private _ctr = _o modelToWorld [0, 0, ((_p1 select 2) + (_p2 select 2)) / 2];
        private _p = _ctr getPos [_d, getDir _o];
        player setPosATL [_p select 0, _p select 1, 0];
        player setDir (player getDir _o);
        uiSleep 6;
        ["toc_ecran_" + (toLower (_v + "_" + str _forEachIndex))] call AT_fnc_shot;
    } forEach ((_scr select { (typeOf _x) isNotEqualTo "COMSPEC_ATAK_Screen_NamePlate" }) + ((_scr select { (typeOf _x) isEqualTo "COMSPEC_ATAK_Screen_NamePlate" }) select [0, 1]));
    // Actions ACE de l'écran, exécutées pour de vrai, état contrôlé.
    private _t = _scr select { ((_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isEqualTo "MOSAIC" } param [0, objNull];
    if (!isNull _t && {!isNil "COMSPEC_ATAK_TocDo"}) then {
        { _x params ["_k", "_want"];
            [_t, _k] call COMSPEC_ATAK_TocDo; uiSleep 1.2;
            private _got = (_t getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""];
            [_got isEqualTo _want, format ["action ACE %1 : affichage %2 au lieu de %3", _k, _got, _want]] call AT_fnc_check;
        } forEach [["clock", "CLOCK"], ["sat2", "SAT"], ["log", "LOG"], ["excheck", "EXCHECK"], ["kills", "KILLS"], ["req", "REQ"], ["sq|ALPHA", "SQUAD"], ["MOS|3|AUTO", "MOSAIC"], ["split", "SPLIT"], ["map", "MAP"], ["MOS|2|AUTO", "MOSAIC"]];
        [((_t getVariable ["COMSPEC_ATAK_TocScr", []]) param [8, -1]) isEqualTo 1, "action ACE SAT 2 : satellite 2 non retenu"] call AT_fnc_check;
        [((_t getVariable ["COMSPEC_ATAK_TocScr", []]) param [9, ""]) isEqualTo "ALPHA", "action ACE escouade : groupe non retenu"] call AT_fnc_check;
        // Arbre du menu ACE (vérifié par script : noms et profondeur).
        private _kids = missionNamespace getVariable ["COMSPEC_ATAK_WallKids", {}];
        private _walk = {
            params ["_nodes", "_depth"];
            private _out = [];
            { _out pushBack format ["%1%2", [_depth, "-"] call { params ["_d", "_c"]; private _s = ""; for "_i" from 1 to _d do { _s = _s + "  "; }; _s }, ((_x select 0) param [1, "?"])]; _out append ([_x select 1, _depth + 1] call _walk); } forEach _nodes;
            _out
        };
        private _target = _t; private _player = player;
        private _tree = [[_target, _player] call { params ["_target", "_player"]; call _kids }, 0] call _walk;
        format ["menu ACE (mosaïque) : %1 entrée(s) : %2", count _tree, _tree joinString " | "] call AT_fnc_info;
    };
    private _wall = _scr select { (getNumber (configOf _x >> "comspec_screenPanels")) > 1 } param [0, objNull];
    if (!isNull _wall) then {
        private _kids = missionNamespace getVariable ["COMSPEC_ATAK_WallKids", {}];
        private _tree = [_wall, player] call { params ["_target", "_player"]; call _kids };
        format ["menu ACE (mur) : groupes %1", _tree apply { (_x select 0) param [1, "?"] }] call AT_fnc_info;
        // Menu ACE ouvert devant le mur (racine).
        (boundingBoxReal _wall) params ["_p1", "_p2"];
        private _p = (_wall modelToWorld [0, 0, 1.4]) getPos [2.5, getDir _wall];
        player setPosATL [_p select 0, _p select 1, 0];
        player setDir (player getDir _wall);
        uiSleep 2;
        if (!isNil "ace_interact_menu_fnc_keyDown") then {
            [0] call ace_interact_menu_fnc_keyDown;
            uiSleep 2.5;
            ["toc_ace_mur"] call AT_fnc_shot;
            [0, false] call ace_interact_menu_fnc_keyUp;
        };
    };
    // PC maître : chaque onglet, rempli.
    private _pc = _pcs param [1, objNull];
    if (!isNull _pc) then {
        private _p = _pc getPos [0.9, (getDir _pc) + 180];
        player setPosATL [_p select 0, _p select 1, 0];
        player setDir (player getDir _pc);
        ["open", _pc] call comspec_atak_native_fnc_tocPost;
        [{ !isNull (uiNamespace getVariable ["COMSPEC_ATAK_TocLapDisp", displayNull]) }, 6] call AT_fnc_wait;
        [!isNull (uiNamespace getVariable ["COMSPEC_ATAK_TocLapDisp", displayNull]), "PC maître non ouvert"] call AT_fnc_check;
        {
            ["tab", _x] call comspec_atak_native_fnc_tocLaptop;
            if (_x isEqualTo "SALLE") then {
                private _tiles = (uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap]) getOrDefault ["hallTiles", []];
                private _first = _tiles select { ctrlShown _x } param [1, controlNull];
                if (!isNull _first) then { ["pick", _first getVariable ["COMSPEC_ATAK_Id", ""]] call comspec_atak_native_fnc_tocHallUi; };
            };
            uiSleep 2.5;
            ["toc_pc_" + (toLower (_x splitString " " joinString "_"))] call AT_fnc_shot;
            // Mise en page de l'onglet : bouton sans texte, texte coupé, chevauchement, hors de l'écran du PC.
            private _def = ["audit"] call comspec_atak_native_fnc_tocLaptop;
            [(count _def) isEqualTo 0, format ["PC du poste, onglet %1 : %2", _x, _def joinString " ; "]] call AT_fnc_check;
        } forEach ["SALLE", "SAT", "CHRONOS", "MAIN COURANTE", "EXCHECK", "PERTES", "DEMANDES", "RADIO", "GROUPES", "ORBAT", "FLUX", "ORDRES", "CAMP", "RÉSEAU"];
        // Scène, extinction, rallumage depuis le PC maître.
        ["do", ["sceneSave", [_hid, "OPÉRATION"]]] call comspec_atak_native_fnc_tocHall;
        ["do", ["powerAll", [_hid, false]]] call comspec_atak_native_fnc_tocHall;
        uiSleep 1;
        [({ !(_x getVariable ["COMSPEC_ATAK_TocOff", false]) } count _scr) isEqualTo 0, "TOUT ÉTEINDRE : des écrans restent allumés"] call AT_fnc_check;
        ["do", ["powerAll", [_hid, true]]] call comspec_atak_native_fnc_tocHall;
        closeDialog 0;
        uiSleep 1;
    };
    // Sauvegarde -> tout supprimé -> reprise : la salle revient pareille.
    ["write"] call comspec_atak_native_fnc_tocPersist;
    private _key = format ["COMSPEC_ATAK_TocSave_%1_%2", worldName, missionName];
    private _saved = +(profileNamespace getVariable [_key, []]);
    private _sig = { private _l = (["screens", _hid] call comspec_atak_native_fnc_tocHall) apply { [typeOf _x, (_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""], count (_x getVariable ["COMSPEC_ATAK_TocPanels", []]), _x getVariable ["COMSPEC_ATAK_TocUid", ""]] }; _l sort true; _l };
    private _before = call _sig;
    private _scenes = count ((["get", _hid] call comspec_atak_native_fnc_tocHall) param [3, []]);
    private _props = (["get", _hid] call comspec_atak_native_fnc_tocHall) param [4, []];
    // Pas d'écriture pendant la suppression (elle effacerait la sauvegarde) : file d'écriture marquée occupée.
    missionNamespace setVariable ["COMSPEC_ATAK_TocSaveQueued", true];
    { ["remove", _x] call comspec_atak_native_fnc_tocScreen; } forEach (["screens", _hid] call comspec_atak_native_fnc_tocHall);
    { private _o = (nearestObjects [ASLToAGL (_x select 1), [_x select 0], 1]) param [0, objNull]; if (!isNull _o) then { deleteVehicle _o; }; } forEach _props;
    missionNamespace setVariable ["COMSPEC_ATAK_TocHalls", [], true];
    uiSleep 2;
    [(count (["screens", _hid] call comspec_atak_native_fnc_tocHall)) isEqualTo 0, "écrans restés après suppression"] call AT_fnc_check;
    profileNamespace setVariable [_key, _saved];
    missionNamespace setVariable ["COMSPEC_ATAK_TocSaveQueued", false];
    missionNamespace setVariable ["COMSPEC_ATAK_TocRestored", false];
    ["restore"] call comspec_atak_native_fnc_tocPersist;
    uiSleep 4;
    private _after = call _sig;
    format ["sauvegarde : avant %1 écran(s), après reprise %2", count _before, count _after] call AT_fnc_info;
    [_after isEqualTo _before, format ["salle reprise différente : avant %1 | après %2", _before, _after] select [0, 900]] call AT_fnc_check;
    [(count ((["get", _hid] call comspec_atak_native_fnc_tocHall) param [3, []])) isEqualTo _scenes, "scènes non reprises"] call AT_fnc_check;
    private _pcs2 = ((["get", _hid] call comspec_atak_native_fnc_tocHall) param [4, []]) select { _x select 4 };
    [(count _pcs2) isEqualTo 3, format ["PC repris : %1 au lieu de 3", count _pcs2]] call AT_fnc_check;
    ["sceneApply", [_hid, "OPÉRATION"]] call { ["do", _this] call comspec_atak_native_fnc_tocHall };
    // Ménage.
    missionNamespace setVariable ["COMSPEC_ATAK_TocSaveQueued", true];
    { ["remove", _x] call comspec_atak_native_fnc_tocScreen; } forEach (["screens", _hid] call comspec_atak_native_fnc_tocHall);
    { private _o = (nearestObjects [ASLToAGL (_x select 1), [_x select 0], 1]) param [0, objNull]; if (!isNull _o) then { deleteVehicle _o; }; } forEach ((["get", _hid] call comspec_atak_native_fnc_tocHall) param [4, []]);
    missionNamespace setVariable ["COMSPEC_ATAK_TocHalls", [], true];
    profileNamespace setVariable [_key, nil];
    missionNamespace setVariable ["COMSPEC_ATAK_TocSaveQueued", false];
    { { deleteVehicle _x; } forEach (units _x); deleteGroup _x; } forEach [_ga, _gb];
    missionNamespace setVariable [format ["COMSPEC_ATAK_TocRoom_%1", _side], nil, true];
    setDate _date;
    player setPosATL _base;
    player setDir _dir0;
    call AT_fnc_done;
}, 420] call AT_fnc_run;
};
