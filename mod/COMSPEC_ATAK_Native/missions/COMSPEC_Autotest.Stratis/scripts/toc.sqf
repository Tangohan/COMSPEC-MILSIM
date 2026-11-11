// Salle TOC (fn_tocSat, fn_tocBoard, fn_tocRoom, fn_tocPersist) : trois écrans posés devant le joueur en vue satellite,
// horloge et escouade, un groupe allié équipé d'ATAK à 90 m pour le suivi, puis l'onglet SAT du PC du poste.
// Les captures servent à juger l'image satellite dans la texture UI et l'alignement des cadres sur les unités.
if !("toc" call AT_fnc_groupe) exitWith {};

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
