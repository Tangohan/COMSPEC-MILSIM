// Carte : fonds, marqueurs, positions du joueur et de ses cercles, appui long, route, zone, plan, suppression.
if !("carte" call AT_fnc_groupe) exitWith {};
["carte_fonds", {
    if (["comspec_atak_native_fnc_mapStyle", "comspec_atak_native_fnc_mapCtrl", "comspec_atak_native_fnc_mapCenter"] call AT_fnc_nil) exitWith {};
    [["MAP", 2] call AT_fnc_page, "page MAP non ouverte"] call AT_fnc_check;
    private _idc = createHashMapFromArray [["SAT", 88532], ["HYBRID", 88533], ["SLATE", 88534]];
    {
        [_x] call comspec_atak_native_fnc_mapStyle;
        ["MAP"] call comspec_atak_native_fnc_pageRender;
        uiSleep 2;
        [0.02] call AT_fnc_centre;
        private _c = [] call comspec_atak_native_fnc_mapCtrl;
        [!isNull _c && {ctrlShown _c}, format ["fond %1 : carte non affichée", _x]] call AT_fnc_check;
        [(ctrlIDC _c) isEqualTo (_idc getOrDefault [_x, 88530]), format ["fond %1 : contrôle %2", _x, ctrlIDC _c]] call AT_fnc_check;
        if (_x isEqualTo "PHOTO" && {([] call comspec_atak_native_fnc_mapStyle) isNotEqualTo "PHOTO"}) then { "fond PHOTO : pas de capture pour cette carte, repli TOPO" call AT_fnc_info; };
        [format ["fond_%1", toLower _x]] call AT_fnc_shot;
    } forEach ["TOPO", "LIGHT", "DARK", "NIGHT", "PHOTO", "SAT", "HYBRID", "SLATE"];
    ["TOPO"] call comspec_atak_native_fnc_mapStyle;
    call AT_fnc_done;
}, 120] call AT_fnc_run;

["carte_marqueurs", {
    if (["comspec_atak_native_fnc_markerDrop", "comspec_atak_native_fnc_markerColor", "comspec_atak_native_fnc_markerDelete", "comspec_atak_native_fnc_markerPlace"] call AT_fnc_nil) exitWith {};
    // « Default » : le moteur prend la couleur du type, jamais noire pour un symbole OTAN.
    private _c = ["Default", "b_inf"] call comspec_atak_native_fnc_markerColor;
    [(_c select [0, 3]) isNotEqualTo [0, 0, 0], format ["couleur Default de b_inf noire : %1", _c]] call AT_fnc_check;
    ["MAP", 2] call AT_fnc_page;
    private _pos = (getPosATL player) vectorAdd [40, 25, 0];
    ["open", _pos] call comspec_atak_native_fnc_markerPlace;
    uiSleep 1;
    ["marqueur_palette"] call AT_fnc_shot;
    ["close"] call comspec_atak_native_fnc_markerPlace;
    uiSleep 0.5;
    private _s = call AT_fnc_etat;
    _s set ["markerType", "b_inf"];
    _s set ["markerColor", "Default"];
    private _m = [_pos] call comspec_atak_native_fnc_markerDrop;
    if (isNil "_m") then { _m = ""; };
    [(markerShape _m) isNotEqualTo "", "marqueur non posé"] call AT_fnc_check;
    [(markerColor _m) isEqualTo "Default", format ["couleur posée : « %1 »", markerColor _m]] call AT_fnc_check;
    // Marqueur d'une mission, même couleur, comparé sur deux fonds.
    private _mm = createMarkerLocal ["cat_default", (getPosATL player) vectorAdd [-40, 25, 0]];
    _mm setMarkerTypeLocal "b_inf";
    _mm setMarkerColorLocal "Default";
    _mm setMarkerTextLocal "AT DEFAULT";
    {
        [_x] call comspec_atak_native_fnc_mapStyle;
        ["MAP"] call comspec_atak_native_fnc_pageRender;
        uiSleep 1.5;
        [0.01] call AT_fnc_centre;
        [format ["marqueur_default_%1", toLower _x]] call AT_fnc_shot;
    } forEach ["TOPO", "SLATE"];
    "marqueurs Default : ni noirs ni invisibles sur les deux fonds" call AT_fnc_visuel;
    if (_m isNotEqualTo "") then {
        [_m] call comspec_atak_native_fnc_markerDelete;
        uiSleep 0.5;
        [(markerShape _m) isEqualTo "", "marqueur non supprimé"] call AT_fnc_check;
    };
    deleteMarkerLocal _mm;
    _s set ["markerColor", "AUTO"];
    ["SELECT"] call comspec_atak_native_fnc_mapToolSet;
    ["TOPO"] call comspec_atak_native_fnc_mapStyle;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

["carte_cercles", {
    if (["comspec_atak_native_fnc_mapPrefs"] call AT_fnc_nil) exitWith {};
    ["MAP", 2] call AT_fnc_page;
    profileNamespace setVariable ["COMSPEC_ATAK_AccRings", true];
    // Un allié de mon groupe, pour son cercle de précision.
    private _ia = (group player) createUnit [typeOf player, player getPos [25, 45], [], 0, "NONE"];
    _ia setVariable ["COMSPEC_ATAK_AiAtak", true, true];
    _ia disableAI "PATH";
    uiSleep 3;
    {
        _x params ["_forme", "_echelle"];
        ["selfSet", ["shape", _forme]] call comspec_atak_native_fnc_mapPrefs;
        uiSleep 0.5;
        [_echelle] call AT_fnc_centre;
        [format ["cercles_%1_%2", toLower _forme, round (_echelle * 1000)]] call AT_fnc_shot;
    } forEach [["ARROW", 0.003], ["ARROW", 0.03], ["CIRCLE", 0.003], ["DOT", 0.003], ["DOT", 0.03]];
    ["selfSet", ["shape", "ARROW"]] call comspec_atak_native_fnc_mapPrefs;
    profileNamespace setVariable ["COMSPEC_ATAK_AccRings", false];
    deleteVehicle _ia;
    "symbole du joueur et ses cercles concentriques, à deux échelles" call AT_fnc_visuel;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

["carte_appui_long", {
    if (["comspec_atak_native_fnc_mapMouseButtonDown", "comspec_atak_native_fnc_mapMouseButtonUp"] call AT_fnc_nil) exitWith {};
    ["MAP", 2] call AT_fnc_page;
    ["SELECT"] call comspec_atak_native_fnc_mapToolSet;
    private _map = [] call comspec_atak_native_fnc_mapCtrl;
    // Position réelle de la souris : fn_mapOnDraw annule l'appui si elle s'en éloigne de plus de 8 pixels.
    getMousePosition params ["_mx", "_my"];
    [_map, 0, _mx, _my, false, false, false, false] call comspec_atak_native_fnc_mapMouseButtonDown;
    private _s = call AT_fnc_etat;
    [[{ _s getOrDefault ["mapHold", false] }, 3] call AT_fnc_wait, "appui long non reconnu (souris déplacée pendant le test ?)"] call AT_fnc_check;
    ["appui_long"] call AT_fnc_shot;
    [_map, 0, _mx, _my] call comspec_atak_native_fnc_mapMouseButtonUp;
    uiSleep 0.5;
    call AT_fnc_done;
}, 30] call AT_fnc_run;

["carte_route", {
    if (["comspec_atak_native_fnc_routeAction", "comspec_atak_native_fnc_pageRoute"] call AT_fnc_nil) exitWith {};
    private _id = ["new", ["VEH", "AT ROUTE"]] call comspec_atak_native_fnc_routeAction;
    if !([_id isNotEqualTo "", "route non créée"] call AT_fnc_check) exitWith { call AT_fnc_done; };
    {
        ["stepAdd", [_id, player getPos _x]] call comspec_atak_native_fnc_routeAction;
    } forEach [[20, 0], [250, 60], [500, 120]];
    private _r = ["get", _id] call comspec_atak_native_fnc_routeAction;
    [(count (_r get "steps")) isEqualTo 3, format ["%1 étapes au lieu de 3", count (_r get "steps")]] call AT_fnc_check;
    ["set", [_id, "roads", true]] call comspec_atak_native_fnc_routeAction;
    [{ !(_r getOrDefault ["busy", false]) }, 30] call AT_fnc_wait;
    uiSleep 2;
    [((_r getOrDefault ["legs", []]) findIf { (count _x) > 0 }) >= 0, "aucun tronçon tracé par les routes"] call AT_fnc_check;
    (format ["stats %1", ["stats", _id] call comspec_atak_native_fnc_routeAction]) call AT_fnc_info;
    ["MAP", 2] call AT_fnc_page;
    [0.03] call AT_fnc_centre;
    ["route_carte"] call AT_fnc_shot;
    [["ROUTE", 2] call AT_fnc_page, "app Routes non ouverte"] call AT_fnc_check;
    ["route_app"] call AT_fnc_shot;
    private _mk = _r getOrDefault ["mk", []];
    ["del", _id] call comspec_atak_native_fnc_routeAction;
    uiSleep 0.5;
    [!(_id in (["mine"] call comspec_atak_native_fnc_routeAction)), "route non supprimée"] call AT_fnc_check;
    [(_mk select { (markerShape _x) isNotEqualTo "" }) isEqualTo [], "marqueurs de la route restés sur la carte"] call AT_fnc_check;
    call AT_fnc_done;
}, 90] call AT_fnc_run;

["carte_zone", {
    if (["comspec_atak_native_fnc_zoneAction", "comspec_atak_native_fnc_pageZones"] call AT_fnc_nil) exitWith {};
    private _id = ["new", ["AT ZONE"]] call comspec_atak_native_fnc_zoneAction;
    if !([_id isNotEqualTo "", "plan de zones non créé"] call AT_fnc_check) exitWith { call AT_fnc_done; };
    getPosATL player params ["_x0", "_y0"];
    ["areaSet", [_id, "RECT", [[_x0 - 300, _y0 - 300], [_x0 + 300, _y0 + 300]]]] call comspec_atak_native_fnc_zoneAction;
    ["gen", [_id]] call comspec_atak_native_fnc_zoneAction;
    private _b = ["get", _id] call comspec_atak_native_fnc_zoneAction;
    [{ (_b getOrDefault ["busy", ""]) isEqualTo "" }, 60] call AT_fnc_wait;
    [(count (_b get "zones")) > 0, "découpage sans zone"] call AT_fnc_check;
    ["MAP", 2] call AT_fnc_page;
    [0.03] call AT_fnc_centre;
    ["zone_carte"] call AT_fnc_shot;
    [["ZONES", 2] call AT_fnc_page, "app Zones non ouverte"] call AT_fnc_check;
    ["zone_app"] call AT_fnc_shot;
    ["del", _id] call comspec_atak_native_fnc_zoneAction;
    uiSleep 0.5;
    [!(_id in (["mine"] call comspec_atak_native_fnc_zoneAction)), "plan de zones non supprimé"] call AT_fnc_check;
    call AT_fnc_done;
}, 120] call AT_fnc_run;

["carte_plan", {
    if (["comspec_atak_native_fnc_planAction", "comspec_atak_native_fnc_pagePlan"] call AT_fnc_nil) exitWith {};
    [["PLAN", 2] call AT_fnc_page, "app Plan non ouverte"] call AT_fnc_check;
    ["target", "D"] call comspec_atak_native_fnc_planAction;
    ["tool", ""] call comspec_atak_native_fnc_planAction;
    ["tool", "OBJ"] call comspec_atak_native_fnc_planAction;
    private _n0 = count (["draft"] call comspec_atak_native_fnc_planAction);
    getPosATL player params ["_px", "_py"];
    ["commit", [[_px - 100, _py - 100], [_px + 100, _py + 100]]] call comspec_atak_native_fnc_planAction;
    uiSleep 1;
    private _items = ["draft"] call comspec_atak_native_fnc_planAction;
    [(count _items) isEqualTo (_n0 + 1), "élément non ajouté au brouillon"] call AT_fnc_check;
    ["plan_element"] call AT_fnc_shot;
    if ((count _items) > _n0) then {
        ["select", [(_items select ((count _items) - 1)) select 0, "D"]] call comspec_atak_native_fnc_planAction;
        uiSleep 0.5;
        ["plan_selection"] call AT_fnc_shot;
        ["delete"] call comspec_atak_native_fnc_planAction;
        uiSleep 0.5;
        [(count (["draft"] call comspec_atak_native_fnc_planAction)) isEqualTo _n0, "élément non supprimé"] call AT_fnc_check;
    };
    ["tool", ""] call comspec_atak_native_fnc_planAction;
    call AT_fnc_done;
}, 45] call AT_fnc_run;
