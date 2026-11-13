// Apps regroupées, profils de rôle, FIRES court et mode pilote. Chaque étape remet les préférences qu'elle change.
if !("pilote" call AT_fnc_groupe) exitWith {};
["spaces_regroupees", {
    if (["comspec_atak_native_fnc_appSpaces", "comspec_atak_native_fnc_pageSpace"] call AT_fnc_nil) exitWith {};
    [!(["launcherView"] call comspec_atak_native_fnc_appSpaces), "apps regroupées actives sans que personne ne les active"] call AT_fnc_check;
    ["setOn", true] call comspec_atak_native_fnc_appSpaces;
    ["LAUNCHER", 2] call AT_fnc_page;
    ["spaces_lanceur"] call AT_fnc_shot;
    private _sp = ["spaces"] call comspec_atak_native_fnc_appSpaces;
    [(count _sp) >= 3 && {(count _sp) <= 5}, format ["%1 apps regroupées, 3 à 5 attendues", count _sp]] call AT_fnc_check;
    {
        uiNamespace setVariable ["COMSPEC_ATAK_SpaceId", _x select 0];
        [["SPACE", 1] call AT_fnc_page, format ["page de l'espace %1", _x select 0]] call AT_fnc_check;
        [format ["space_%1", toLower (_x select 0)]] call AT_fnc_shot;
    } forEach _sp;
    // Une app interdite disparaît de ces pages, puis revient.
    ["policy", [[], ["Calc"]]] call comspec_atak_native_fnc_appInstall;
    uiSleep 0.5;
    private _ids = { private _o = []; { { { _o pushBack (_x get "id"); } forEach (_x select 1); } forEach (_x select 3); } forEach (["spaces"] call comspec_atak_native_fnc_appSpaces); _o };
    [!("Calc" in (call _ids)), "app interdite encore affichée"] call AT_fnc_check;
    ["policy", [[], []]] call comspec_atak_native_fnc_appInstall;
    uiSleep 0.5;
    ["Calc" in (call _ids), "app non revenue après la levée de l'interdiction"] call AT_fnc_check;
    ["setOn", false] call comspec_atak_native_fnc_appSpaces;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

["spaces_profils", {
    if (["comspec_atak_native_fnc_appSpaces"] call AT_fnc_nil) exitWith {};
    private _all = ["filter", [] call comspec_atak_native_fnc_appList] call comspec_atak_native_fnc_appSpaces;
    [(count _all) isEqualTo (count ([] call comspec_atak_native_fnc_appList)), "un filtre est actif sans profil choisi"] call AT_fnc_check;
    {
        ["setRole", _x] call comspec_atak_native_fnc_appSpaces;
        private _kept = ["filter", [] call comspec_atak_native_fnc_appList] call comspec_atak_native_fnc_appSpaces;
        [(count _kept) < (count _all), format ["profil %1 : aucun filtre", _x]] call AT_fnc_check;
        ["LAUNCHER", 1] call AT_fnc_page;
        [format ["profil_%1", _x]] call AT_fnc_shot;
    } forEach ["pilot", "jtac", "medic", "infantry"];
    ["showAll", true] call comspec_atak_native_fnc_appSpaces;
    [(["role"] call comspec_atak_native_fnc_appSpaces) isEqualTo "all", "« Toutes les apps » garde un filtre"] call AT_fnc_check;
    ["setRole", "all"] call comspec_atak_native_fnc_appSpaces;
    call AT_fnc_done;
}, 40] call AT_fnc_run;

["fires_court", {
    if (["comspec_atak_native_fnc_firesQuick", "comspec_atak_native_fnc_pageQuickMap"] call AT_fnc_nil) exitWith {};
    if !(call AT_fnc_tel) exitWith { "téléphone absent" call AT_fnc_skip; };
    ["FIRESQ"] call comspec_atak_native_fnc_navigate;
    uiSleep 2;
    [((call AT_fnc_etat) getOrDefault ["activePage", ""]) isEqualTo "MAP" && {((call AT_fnc_etat) getOrDefault ["quickView", ""]) isEqualTo "FIRES"}, "écran FIRES court non ouvert"] call AT_fnc_check;
    ["fires_court_vide"] call AT_fnc_shot;
    private _t = player getPos [600, 45];
    ["target", _t] call comspec_atak_native_fnc_firesQuick;
    ["ammo", "ROQUETTES"] call comspec_atak_native_fnc_firesQuick;
    uiSleep 1;
    ["fires_court_cible"] call AT_fnc_shot;
    private _ref = ["send"] call comspec_atak_native_fnc_firesQuick;
    uiSleep 1;
    [_ref isNotEqualTo "", "FIRES court non envoyé"] call AT_fnc_check;
    private _info = ["mineInfo", _ref] call comspec_atak_native_fnc_firesQuick;
    [(markerShape (_info param [0, ""])) isNotEqualTo "", "marqueur FIRES absent de la carte"] call AT_fnc_check;
    [((missionNamespace getVariable ["COMSPEC_ATAK_Pings", []]) select { (_x select 0) distance2D _t < 5 }) isNotEqualTo [], "ping FIRES absent"] call AT_fnc_check;
    ["fires_court_envoye"] call AT_fnc_shot;
    ["cancel", _ref] call comspec_atak_native_fnc_firesQuick;
    uiSleep 0.5;
    [(markerShape (_info param [0, ""])) isEqualTo "", "marqueur resté après la fin de la demande"] call AT_fnc_check;
    [] call comspec_atak_native_fnc_close;
    call AT_fnc_done;
}, 40] call AT_fnc_run;

// FIRES court : choix des destinataires (sélecteur ouvert, vide expliqué, retour à « tous »).
["fires_court_destinataires", {
    if (["comspec_atak_native_fnc_firesQuick", "comspec_atak_native_fnc_pageQuickMap"] call AT_fnc_nil) exitWith {};
    if !(call AT_fnc_tel) exitWith { "téléphone absent" call AT_fnc_skip; };
    ["FIRESQ"] call comspec_atak_native_fnc_navigate;
    uiSleep 2;
    ["picker"] call comspec_atak_native_fnc_firesQuick;
    uiSleep 1;
    [(uiNamespace getVariable ["COMSPEC_ATAK_FiresQ", createHashMap]) getOrDefault ["pick", false], "sélecteur de destinataires non ouvert"] call AT_fnc_check;
    ["fires_court_destinataires_liste"] call AT_fnc_shot;
    private _c = ["cands"] call comspec_atak_native_fnc_firesQuick;
    if ((count _c) > 0) then {
        ["destToggle", netId ((_c select 0) select 1)] call comspec_atak_native_fnc_firesQuick;
        [(count (["recipients"] call comspec_atak_native_fnc_firesQuick)) isEqualTo 1, "un seul destinataire attendu après le choix"] call AT_fnc_check;
        uiSleep 0.5;
        ["fires_court_destinataires_choisi"] call AT_fnc_shot;
        ["destAll"] call comspec_atak_native_fnc_firesQuick;
        [(count (["recipients"] call comspec_atak_native_fnc_firesQuick)) isEqualTo (count _c), "retour à tous les destinataires"] call AT_fnc_check;
    } else {
        "aucun pilote ni JTAC en ligne : le message d'aide avec exemple doit s'afficher" call AT_fnc_visuel;
    };
    ["search", "zzzz"] call comspec_atak_native_fnc_firesQuick;
    uiSleep 0.5;
    ["fires_court_destinataires_recherche"] call AT_fnc_shot;
    ["search", ""] call comspec_atak_native_fnc_firesQuick;
    ["picker"] call comspec_atak_native_fnc_firesQuick;
    [] call comspec_atak_native_fnc_close;
    call AT_fnc_done;
}, 40] call AT_fnc_run;

["pilote_mission", {
    if (["comspec_atak_native_fnc_pilotMode", "comspec_atak_native_fnc_airBrief"] call AT_fnc_nil) exitWith {};
    if !(call AT_fnc_tel) exitWith { "téléphone absent" call AT_fnc_skip; };
    ["setOn", true] call comspec_atak_native_fnc_pilotMode;
    private _g = [player getPos [500, 90], 8] call comspec_atak_native_fnc_gridRef;
    ["store", ["QAUTOTEST", 0, "5", "AUTOTEST", objNull, "12:00", ["AUTOTEST", "-", _g, "Canon", "FIRES"], "", "PILOTE", "game", ""]] call comspec_atak_native_fnc_airBrief;
    ["PILOT"] call comspec_atak_native_fnc_navigate;
    uiSleep 2;
    [((call AT_fnc_etat) getOrDefault ["quickView", ""]) isEqualTo "PILOT", "écran pilote non ouvert"] call AT_fnc_check;
    ["pilote_mission_recue"] call AT_fnc_shot;
    [(count (["missions"] call comspec_atak_native_fnc_firesQuick)) > 0, "mission absente de l'écran pilote"] call AT_fnc_check;
    ["ack", ["QAUTOTEST", "WILCO"]] call comspec_atak_native_fnc_airBrief;
    uiSleep 2;
    ["pilote_mission_acceptee"] call AT_fnc_shot;
    ["ack", ["QAUTOTEST", "DONE"]] call comspec_atak_native_fnc_airBrief;
    uiSleep 2;
    [((["missions"] call comspec_atak_native_fnc_firesQuick) select { (_x get "ref") isEqualTo "QAUTOTEST" }) isEqualTo [], "mission clôturée encore à traiter"] call AT_fnc_check;
    ["del", "QAUTOTEST"] call comspec_atak_native_fnc_airBrief;
    ["MAP"] call comspec_atak_native_fnc_navigate;
    uiSleep 1;
    [((call AT_fnc_etat) getOrDefault ["quickView", ""]) isEqualTo "", "l'app Carte garde l'écran pilote"] call AT_fnc_check;
    [] call comspec_atak_native_fnc_close;
    call AT_fnc_done;
}, 40] call AT_fnc_run;

["pilote_hud", {
    if (["comspec_atak_native_fnc_pilotMode"] call AT_fnc_nil) exitWith {};
    ["setOn", true] call comspec_atak_native_fnc_pilotMode;
    // Aire dégagée (rien à 12 m), sinon l'appareil tombe sur un mur ou un arbre du village : pare-brise fêlé, voyants rouges.
    private _aire = [AT_Base, 30, 400, 12, 0, 0.15, 0] call BIS_fnc_findSafePos;
    private _h = createVehicle ["B_Heli_Light_01_F", [_aire select 0, _aire select 1, 0], [], 0, "CAN_COLLIDE"];
    _h allowDamage false;
    _h setVectorUp (surfaceNormal getPosATL _h);
    _h setDamage 0;
    _h setFuel 1;
    uiSleep 1;
    _h setDamage 0;
    [(damage _h) isEqualTo 0 && {((getAllHitPointsDamage _h) param [2, []]) findIf { _x > 0 } < 0}, "appareil endommagé à l'apparition"] call AT_fnc_check;
    [(fuel _h) isEqualTo 1, "appareil pas plein"] call AT_fnc_check;
    AT_Heli = _h;
    call AT_fnc_nettoie;
    uiSleep 1;
    player moveInDriver _h;
    [[{ (objectParent player) isEqualTo AT_Heli }, 8] call AT_fnc_wait, "pas monté à bord"] call AT_fnc_check;
    uiSleep 2.5;
    [missionNamespace getVariable ["COMSPEC_ATAK_PilotIn", false], "entrée dans l'aéronef non vue"] call AT_fnc_check;
    // Le HUD est retiré tant que le téléphone est en main : attendre qu'il apparaisse au lieu de tester tout de suite.
    [[{ !isNull (uiNamespace getVariable ["COMSPEC_ATAK_PilotHud", controlNull]) }, 6] call AT_fnc_wait, "HUD absent à bord"] call AT_fnc_check;
    ["pilote_hud"] call AT_fnc_shot;
    moveOut player;
    uiSleep 2.5;
    [isNull (uiNamespace getVariable ["COMSPEC_ATAK_PilotHud", controlNull]), "HUD resté hors de l'appareil"] call AT_fnc_check;
    deleteVehicle _h;
    player setPosATL AT_Base;
    ["setOn", false] call comspec_atak_native_fnc_pilotMode;
    call AT_fnc_done;
}, 40] call AT_fnc_run;
