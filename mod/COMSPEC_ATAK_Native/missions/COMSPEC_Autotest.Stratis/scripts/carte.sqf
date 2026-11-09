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

// Style de la carte (Tactique / Classique) et contacts : repères ennemi, neutre, inconnu et ami, fiche de contact au toucher, dans les
// trois thèmes, en portrait (mini) et en paysage (plein écran). Contacts ENI : losange ; neutre : carré ; inconnu : carré à bras.
["carte_style_contacts", {
    if (["comspec_atak_native_fnc_contactStyle", "comspec_atak_native_fnc_contactCard", "comspec_atak_native_fnc_mapColors", "comspec_atak_native_fnc_theme"] call AT_fnc_nil) exitWith {};
    ["aff", ["o_inf", "n_inf", "u_unknown", "b_inf", "mil_dot"] apply { ["markerAff", _x] call comspec_atak_native_fnc_contactStyle }] call AT_fnc_info;
    [(["markerAff", "o_inf"] call comspec_atak_native_fnc_contactStyle) isEqualTo "hostile", "o_inf n'est pas ennemi"] call AT_fnc_check;
    [(["markerAff", "mil_dot"] call comspec_atak_native_fnc_contactStyle) isEqualTo "", "mil_dot ne doit pas avoir d'affiliation"] call AT_fnc_check;
    private _mode0 = profileNamespace getVariable ["COMSPEC_ATAK_Mode", "MINI"];
    private _orient0 = profileNamespace getVariable ["COMSPEC_ATAK_Orientation", "PORTRAIT"];
    private _style0 = profileNamespace getVariable ["COMSPEC_ATAK_MapDrawStyle", "TACTICAL"];
    private _bg0 = [] call comspec_atak_native_fnc_mapStyle;
    private _mks = [];
    {
        _x params ["_nom", "_type", "_dx", "_dy", "_txt"];
        private _m = createMarkerLocal [format ["cat_contact_%1", _nom], (getPosATL player) vectorAdd [_dx, _dy, 0]];
        _m setMarkerTypeLocal _type;
        _m setMarkerColorLocal "Default";
        _m setMarkerTextLocal _txt;
        _mks pushBack _m;
    } forEach [["eni", "o_inf", 35, 30, "AT ENI"], ["neu", "n_inf", -35, 30, "AT NEUTRE"], ["inc", "u_unknown", 0, 55, "AT INCONNU"], ["ami", "b_inf", 0, -40, "AT AMI"]];
    uiSleep 1;
    private _s = call AT_fnc_etat;
    {
        _x params ["_mode", "_orient", "_nom"];
        profileNamespace setVariable ["COMSPEC_ATAK_Mode", _mode];
        profileNamespace setVariable ["COMSPEC_ATAK_Orientation", _orient];
        [true] call comspec_atak_native_fnc_open;
        [[{ !isNull ([] call comspec_atak_native_fnc_display) }, 8] call AT_fnc_wait, format ["téléphone non ouvert (%1)", _nom]] call AT_fnc_check;
        uiSleep 1.5;
        {
            _x params ["_style", "_themes"];
            profileNamespace setVariable ["COMSPEC_ATAK_MapDrawStyle", _style];
            {
                ["set", _x] call comspec_atak_native_fnc_theme;
                ["SLATE"] call comspec_atak_native_fnc_mapStyle;
                _s set ["selectedMarker", ""];
                ["MAP", 2] call AT_fnc_page;
                [0.006] call AT_fnc_centre;
                [format ["style_%1_%2_%3_contacts", toLower _style, toLower _x, _nom]] call AT_fnc_shot;
                if (_style isEqualTo "TACTICAL") then {
                    // Fiche de contact : repère ennemi touché (pastille en losange, affiliation en toutes lettres, âge, distance et azimut).
                    _s set ["selectedMarker", _mks select 0];
                    ["MAP"] call comspec_atak_native_fnc_pageRender;
                    uiSleep 1;
                    [format ["style_%1_%2_%3_fiche", toLower _style, toLower _x, _nom]] call AT_fnc_shot;
                };
            } forEach _themes;
        } forEach [["TACTICAL", ["DARK", "DAY", "NVG"]], ["STANDARD", ["DARK"]]];
    } forEach [["MINI", "PORTRAIT", "portrait"], ["FULL", "LANDSCAPE", "paysage"]];
    "contacts : losange ennemi, carré neutre, carré à bras inconnu, cercle ami, jamais la couleur seule ; plaques sans chevauchement ; fiche lisible dans les trois thèmes ; Classique garde les symboles OTAN" call AT_fnc_visuel;
    // MARQUER : une piste de contact devient un repère partagé à sa position, la palette de marqueurs retrouvée telle quelle.
    private _aff0 = _s getOrDefault ["markerAff", "o"];
    private _nm = count allMapMarkers;
    private _m = ["mark", createHashMapFromArray [["affiliation", "hostile"], ["callsign", "AT PISTE"], ["position", getPosATL player vectorAdd [20, 20, 0]]]] call comspec_atak_native_fnc_contactStyle;
    if (isNil "_m") then { _m = ""; };
    [(markerShape _m) isNotEqualTo "", "MARQUER : repère non posé"] call AT_fnc_check;
    [(_s getOrDefault ["markerAff", "o"]) isEqualTo _aff0, "MARQUER : palette de marqueurs modifiée"] call AT_fnc_check;
    if (_m isNotEqualTo "") then { [_m] call comspec_atak_native_fnc_markerDelete; };
    { deleteMarkerLocal _x; } forEach _mks;
    _s set ["selectedMarker", ""];
    profileNamespace setVariable ["COMSPEC_ATAK_MapDrawStyle", _style0];
    [_bg0] call comspec_atak_native_fnc_mapStyle;
    ["set", "DARK"] call comspec_atak_native_fnc_theme;
    profileNamespace setVariable ["COMSPEC_ATAK_Mode", _mode0];
    profileNamespace setVariable ["COMSPEC_ATAK_Orientation", _orient0];
    [true] call comspec_atak_native_fnc_open;
    uiSleep 1;
    call AT_fnc_done;
}, 300] call AT_fnc_run;

// Téléphone tracé : ni « Infanterie… » ni « AMI / ALLIÉ » sur la carte, que la position vienne de RECHERCHER (GÉOLOC par
// IMEI) ou d'une piste Athena « Téléphone » (suivi Zeus / ordre de géoloc, fn_pollUnits) ; symbole d'émetteur.
["carte_telephone_trace", {
    if (["comspec_atak_native_fnc_mapSearch", "comspec_atak_native_fnc_phoneIdent", "comspec_atak_native_fnc_symbology", "comspec_atak_native_fnc_natoSymbol", "comspec_atak_native_fnc_unitCard", "comspec_atak_native_fnc_localDataRefresh", "comspec_atak_native_fnc_storeSet"] call AT_fnc_nil) exitWith {};
    [["MAP", 2] call AT_fnc_page, "page MAP non ouverte"] call AT_fnc_check;
    // Mot d'infanterie ou d'allié dans un texte affiché (balises de texte structuré écartées).
    private _bad = {
        params ["_t"];
        private _w = (toLower _t) splitString " ·:,;.()[]<>/='""#-+";
        (_w findIf { _x in ["ami", "amie", "allié", "alliée", "allie", "alliÉ", "friend", "frd", "infanterie", "infantry"] }) >= 0
    };
    // 1) RECHERCHER > IMEI : téléphone d'une IA ennemie équipée.
    private _grp = createGroup [east, true];
    private _ia = _grp createUnit ["O_Soldier_F", player getPos [45, 60], [], 0, "NONE"];
    _ia setVariable ["COMSPEC_ATAK_AiAtak", true, true];
    _ia disableAI "PATH";
    _ia disableAI "TARGET";
    _ia disableAI "AUTOTARGET";
    uiSleep 1;
    private _imei = ([_ia] call comspec_atak_native_fnc_phoneIdent) select 1;
    private _st = uiNamespace getVariable ["COMSPEC_ATAK_MapSearch", createHashMap];
    uiNamespace setVariable ["COMSPEC_ATAK_MapSearch", _st];
    uiNamespace setVariable ["COMSPEC_ATAK_MapSearchCtl", [controlNull, controlNull]];
    uiNamespace setVariable ["COMSPEC_ATAK_GeoNext", 0];
    _st set ["type", "IMEI"];
    _st set ["q", _imei];
    ["go"] call comspec_atak_native_fnc_mapSearch;
    private _found = [{ (((uiNamespace getVariable ["COMSPEC_ATAK_MapSearch", createHashMap]) getOrDefault ["hit", createHashMap]) getOrDefault ["status", ""]) in ["OK", "SOS"] }, 12] call AT_fnc_wait;
    private _hit = _st getOrDefault ["hit", createHashMap];
    if (_found) then {
        private _lbl = [_hit getOrDefault ["owner", ""], _hit getOrDefault ["q", ""]] select ((_hit getOrDefault ["owner", ""]) isEqualTo "");
        [_lbl isNotEqualTo "", "RECHERCHER : téléphone localisé sans libellé"] call AT_fnc_check;
        [!([_lbl] call _bad), format ["RECHERCHER : libellé « %1 »", _lbl]] call AT_fnc_check;
        private _card = ["cardText"] call comspec_atak_native_fnc_mapSearch;
        [!([_card] call _bad), format ["RECHERCHER : fiche « %1 »", _card]] call AT_fnc_check;
    } else {
        (format ["RECHERCHER : téléphone non localisé ici (%1 %2), partie GÉOLOC non vérifiée", _hit getOrDefault ["status", "?"], (_st getOrDefault ["msg", []]) param [0, ""]]) call AT_fnc_info;
    };
    // 2) Piste Athena « Téléphone » telle que fn_pollUnits la range (Athena coupée ici : magasin rempli à la main).
    private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
    private _ru0 = _data getOrDefault ["remoteUnits", createHashMap];
    private _id = "athena:tél. at cible";
    ["remoteUnits", createHashMapFromArray [[_id, createHashMapFromArray [
        ["id", _id], ["callsign", "Tél. AT Cible"], ["position", (getPosATL player) vectorAdd [-45, 35, 0]], ["heading", 0],
        ["affiliation", "unknown"], ["type", "phone"], ["phone", true], ["freshness", "LIVE"], ["updated", diag_tickTime],
        ["role", "Téléphone"], ["status", "linked"]
    ]]], -1, "at_tel_trace"] call comspec_atak_native_fnc_storeSet;
    [] call comspec_atak_native_fnc_localDataRefresh;
    private _u = (_data getOrDefault ["units", createHashMap]) getOrDefault [_id, createHashMap];
    [(count _u) > 0, "piste téléphone absente de la carte"] call AT_fnc_check;
    if ((count _u) > 0) then {
        [(_u getOrDefault ["affiliation", ""]) isNotEqualTo "friend", "piste téléphone rangée en allié"] call AT_fnc_check;
        private _ic = ([_u] call comspec_atak_native_fnc_symbology) select 0;
        [((toLower _ic) find "transmitter") >= 0, format ["symbole de la piste téléphone : %1", _ic]] call AT_fnc_check;
        private _nt = ([_u] call comspec_atak_native_fnc_natoSymbol) select 0;
        [((toLower _nt) find "transmitter") >= 0, format ["symbole OTAN de la piste téléphone : %1", _nt]] call AT_fnc_check;
        (call AT_fnc_etat) set ["selectedEntity", _u];
        ["mapCard"] call comspec_atak_native_fnc_unitCard;
        uiSleep 1;
        private _txt = ((uiNamespace getVariable ["COMSPEC_ATAK_UnitCard", createHashMap]) getOrDefault ["texts", ["", ""]]) joinString " ";
        [!([_txt] call _bad), format ["fiche de la piste téléphone : « %1 »", _txt]] call AT_fnc_check;
    };
    [0.012] call AT_fnc_centre;
    ["telephone_trace_carte"] call AT_fnc_shot;
    "téléphones tracés : symbole d'émetteur, libellé IMEI ou « Tél. … », fiche « TÉLÉPHONE », jamais infanterie ni allié" call AT_fnc_visuel;
    (call AT_fnc_etat) set ["selectedEntity", createHashMap];
    ["mapCard"] call comspec_atak_native_fnc_unitCard;
    ["clear"] call comspec_atak_native_fnc_mapSearch;
    ["remoteUnits", _ru0, -1, "at_tel_trace_fin"] call comspec_atak_native_fnc_storeSet;
    [] call comspec_atak_native_fnc_localDataRefresh;
    deleteVehicle _ia;
    call AT_fnc_done;
}, 90] call AT_fnc_run;
// Fiche d'unité de la carte (fn_unitCard « mapCard ») : à pied, pas de bloc ENGIN ; à bord d'un avion, silhouette d'avion,
// pastille d'état du vol et bloc ENGIN (place, altitude sol, vitesse, cap). Rien de coupé, fiche dans la carte, pas refaite
// à chaque seconde.
["carte_fiche_unite", {
    if (["comspec_atak_native_fnc_unitCard", "comspec_atak_native_fnc_localDataRefresh", "comspec_atak_native_fnc_mapCenter"] call AT_fnc_nil) exitWith {};
    [["MAP", 2] call AT_fnc_page, "page MAP non ouverte"] call AT_fnc_check;
    private _fiche = { uiNamespace getVariable ["COMSPEC_ATAK_UnitCard", createHashMap] };
    // Ouvre la fiche d'un objet (piste de la carte si elle existe), attend son contenu.
    private _ouvre = {
        params ["_o"];
        [] call comspec_atak_native_fnc_localDataRefresh;
        private _u = ["resolve", _o] call comspec_atak_native_fnc_unitCard;
        (call AT_fnc_etat) set ["selectedMarker", ""];
        (call AT_fnc_etat) set ["selectedEntity", _u];
        ["mapCard"] call comspec_atak_native_fnc_unitCard;
        [{ ((call _fiche) getOrDefault ["texts", []]) isNotEqualTo [] }, 4] call AT_fnc_wait
    };
    // Rien de coupé, fiche entière dans la carte.
    private _tient = {
        params ["_nom"];
        private _c = call _fiche;
        { [_x, format ["%1 : texte", _nom]] call AT_fnc_tient; } forEach ([_c get "title", _c get "role", _c get "head"] + (_c getOrDefault ["pills", []]) + ((_c getOrDefault ["tiles", []]) apply { _x select 1 }));
        private _geo = uiNamespace getVariable ["COMSPEC_ATAK_UnitCardGeom", []];
        private _bg = (_c getOrDefault ["ctrls", []]) param [1, controlNull];
        if ((count _geo) >= 4 && {!isNull _bg}) then {
            _geo params ["", "_by", "", "_mh"];
            (ctrlPosition _bg) params ["", "_y", "", "_h"];
            [_y >= _by - 0.001 && {(_y + _h) <= (_by + _mh + 0.001)}, format ["%1 : fiche hors de la carte (%2 + %3)", _nom, _y, _h]] call AT_fnc_check;
        };
        // Un tour de rafraîchissement : la fiche n'est pas refaite (pas de boucle de remesure).
        private _c0 = _c get "ctrls";
        ["tick"] call comspec_atak_native_fnc_unitCard;
        [((call _fiche) getOrDefault ["ctrls", []]) isEqualTo _c0, format ["%1 : fiche refaite au rafraîchissement", _nom]] call AT_fnc_check;
    };
    // 1) À pied : un équipier de mon groupe, à 30 m.
    private _ia = (group player) createUnit [typeOf player, player getPos [30, 45], [], 0, "NONE"];
    _ia setVariable ["COMSPEC_ATAK_AiAtak", true, true];
    _ia disableAI "PATH";
    uiSleep 2;
    [[_ia] call _ouvre, "fiche à pied non ouverte"] call AT_fnc_check;
    private _c = call _fiche;
    [!(_c getOrDefault ["aboard", true]) && {isNull (_c getOrDefault ["head", controlNull])}, "à pied : bloc ENGIN présent"] call AT_fnc_check;
    private _lbl = (_c getOrDefault ["tiles", []]) apply { _x select 0 };
    [!("PLACE" in _lbl) && {"GRILLE" in _lbl} && {"DERNIER SIGNAL" in _lbl}, format ["à pied : tuiles %1", _lbl]] call AT_fnc_check;
    ["à pied"] call _tient;
    [0.01] call AT_fnc_centre;
    ["fiche_unite_pied"] call AT_fnc_shot;
    // 2) En avion : A-164 en vol à 500 m, pilote IA de mon groupe.
    private _pos = player getPos [1500, 90];
    private _av = createVehicle ["B_Plane_CAS_01_dynamicLoadout_F", [_pos select 0, _pos select 1, 500], [], 0, "FLY"];
    _av allowDamage false;
    _av setPosASL [_pos select 0, _pos select 1, 500 + getTerrainHeightASL _pos];
    _av setDir 270;
    _av setVelocityModelSpace [0, 110, 0];
    private _pil = (group player) createUnit ["B_Pilot_F", player getPos [5, 0], [], 0, "NONE"];
    _pil setVariable ["COMSPEC_ATAK_AiAtak", true, true];
    _pil allowDamage false;
    _pil moveInDriver _av;
    _av flyInHeight 500;
    uiSleep 3;
    [(objectParent _pil) isEqualTo _av, "pilote pas à bord"] call AT_fnc_check;
    [[_pil] call _ouvre, "fiche de l'aviateur non ouverte"] call AT_fnc_check;
    _c = call _fiche;
    [_c getOrDefault ["aboard", false] && {!isNull (_c getOrDefault ["head", controlNull])}, "en avion : bloc ENGIN absent"] call AT_fnc_check;
    [((toLower (_c getOrDefault ["icon", ""])) find "iconplane") >= 0, format ["en avion : icône %1", _c getOrDefault ["icon", ""]]] call AT_fnc_check;
    _lbl = (_c getOrDefault ["tiles", []]) apply { _x select 0 };
    [({ _x in _lbl } count ["PLACE", "ALT. SOL", "VITESSE", "CAP"]) isEqualTo 4, format ["en avion : tuiles %1", _lbl]] call AT_fnc_check;
    private _etat = ctrlText ((_c getOrDefault ["pills", []]) param [1, controlNull]);
    [_etat isNotEqualTo "", "en avion : pastille d'état du vol vide"] call AT_fnc_check;
    (format ["pastille du vol : %1 ; textes : %2", _etat, (_c getOrDefault ["texts", []]) joinString " | "]) call AT_fnc_info;
    ["en avion"] call _tient;
    [_av, 0.08] call comspec_atak_native_fnc_mapCenter;
    uiSleep 1;
    ["fiche_unite_avion"] call AT_fnc_shot;
    "fiche d'unité : silhouette d'avion, pastilles APTE et EN VOL, bloc ENGIN (A-164, AVION, place, altitude, vitesse, cap), grille, distance, azimut avec point cardinal, « il y a … s »" call AT_fnc_visuel;
    (call AT_fnc_etat) set ["selectedEntity", createHashMap];
    ["mapCard"] call comspec_atak_native_fnc_unitCard;
    deleteVehicle _pil;
    deleteVehicle _av;
    deleteVehicle _ia;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

// Un seul trait par paire d'alliés : maillage injecté avec des liens en double (A-B puis B-A, deux nœuds pour le même
// allié) ; fn_meshDraw expose les traits de l'image (uiNamespace COMSPEC_ATAK_MeshDrawn, [objet A, objet B, source]).
["carte_un_trait", {
    if (["comspec_atak_native_fnc_meshDraw", "comspec_atak_native_fnc_waveRelayAction", "comspec_atak_native_fnc_mpuLink"] call AT_fnc_nil) exitWith {};
    if (["active"] call comspec_atak_native_fnc_mpuLink) exitWith { "liaison réaliste active : maillage lu du graphe, pas d'injection" call AT_fnc_skip; };
    ["MAP", 2] call AT_fnc_page;
    private _old = [profileNamespace getVariable ["COMSPEC_ATAK_MeshOnMap", false], profileNamespace getVariable ["COMSPEC_ATAK_MeshLinks", false]];
    profileNamespace setVariable ["COMSPEC_ATAK_MeshOnMap", true];
    profileNamespace setVariable ["COMSPEC_ATAK_MeshLinks", true];
    private _a = (group player) createUnit [typeOf player, player getPos [12, 300], [], 0, "NONE"];
    private _b = (group player) createUnit [typeOf player, player getPos [9, 330], [], 0, "NONE"];
    { _x disableAI "PATH"; _x setVariable ["COMSPEC_ATAK_AiAtak", true, true]; } forEach [_a, _b];
    private _ant = { params ["_u"]; (eyePos _u) vectorAdd [0, 0, 0.5] };
    private _mesh = createHashMapFromArray [
        ["nodes", [[player, [player] call _ant, "MOI", "me", 3000], [_a, [_a] call _ant, "A", "node", 3000], [_b, [_b] call _ant, "B", "node", 3000], [_a, [_a] call _ant, "A bis", "node", 3000]]],
        ["links", [[0, 1, 90], [1, 0, 90], [0, 2, 80], [2, 0, 80], [1, 2, 70], [3, 0, 85], [3, 1, 99], [2, 3, 60]]],
        ["hops", [0, 1, 1, 1]], ["me", 0], ["gw", -1], ["gwLabel", ""], ["range", 3000]
    ];
    [0.004] call AT_fnc_centre;
    // Cache de l'action « scan » (3 s) rafraîchi pendant l'attente : la carte dessine ce maillage-là.
    private _fin = diag_tickTime + 2;
    while { diag_tickTime < _fin } do { uiNamespace setVariable ["COMSPEC_ATAK_Mesh", [diag_tickTime, _mesh]]; uiSleep 0.2; };
    private _dr = +(uiNamespace getVariable ["COMSPEC_ATAK_MeshDrawn", []]);
    private _keys = [];
    {
        _x params ["_oa", "_ob"];
        private _k = [str _oa, str _ob];
        _k sort true;
        _keys pushBackUnique (_k joinString "|");
    } forEach _dr;
    (format ["traits dessinés : %1", _dr apply { format ["%1-%2 (%3)", name (_x select 0), name (_x select 1), _x select 2] }]) call AT_fnc_info;
    [(count _dr) > 0, "aucun trait entre alliés dessiné (calque Wave Relay ?)"] call AT_fnc_check;
    [(count _keys) isEqualTo (count _dr), format ["%1 traits pour %2 paires : une paire dessinée deux fois", count _dr, count _keys]] call AT_fnc_check;
    [(_dr findIf { (_x select 0) isEqualTo (_x select 1) }) < 0, "trait d'un allié vers lui-même (deux nœuds du même porteur)"] call AT_fnc_check;
    [(count _dr) <= 2, format ["%1 traits pour 3 unités (au plus 2 attendus)", count _dr]] call AT_fnc_check;
    uiNamespace setVariable ["COMSPEC_ATAK_Mesh", [diag_tickTime, _mesh]];
    ["carte_un_trait"] call AT_fnc_shot;
    "un seul trait vert entre moi et chaque allié proche, aucun trait doublé ni décalé" call AT_fnc_visuel;
    profileNamespace setVariable ["COMSPEC_ATAK_MeshOnMap", _old select 0];
    profileNamespace setVariable ["COMSPEC_ATAK_MeshLinks", _old select 1];
    uiNamespace setVariable ["COMSPEC_ATAK_Mesh", nil];
    missionNamespace setVariable ["COMSPEC_ATAK_MeshKeep", nil];
    deleteVehicle _a;
    deleteVehicle _b;
    call AT_fnc_done;
}, 40] call AT_fnc_run;

// Fond des noms (Réglages > Carte) : chaque niveau sur le fond satellite, un allié proche nommé, capture par niveau.
["carte_fond_noms", {
    if (["comspec_atak_native_fnc_mapPrefs", "comspec_atak_native_fnc_mapStyle"] call AT_fnc_nil) exitWith {};
    ["MAP", 2] call AT_fnc_page;
    private _ia = (group player) createUnit [typeOf player, player getPos [15, 300], [], 0, "NONE"];
    _ia setVariable ["COMSPEC_ATAK_AiAtak", true, true];
    _ia disableAI "PATH";
    private _style = [] call comspec_atak_native_fnc_mapStyle;
    ["SAT"] call comspec_atak_native_fnc_mapStyle;
    ["MAP"] call comspec_atak_native_fnc_pageRender;
    uiSleep 2;
    [0.01] call AT_fnc_centre;
    {
        _x params ["_nom", "_a", "_ton"];
        ["nameBgSet", ["tone", _ton]] call comspec_atak_native_fnc_mapPrefs;
        ["nameBgSet", ["alpha", _a]] call comspec_atak_native_fnc_mapPrefs;
        uiSleep 1;
        private _now = uiNamespace getVariable ["COMSPEC_ATAK_NameBgNow", []];
        if (_a <= 0) then {
            [_now isEqualTo [], format ["%1 : un fond reste dessiné (%2)", _nom, _now]] call AT_fnc_check;
        } else {
            [(count _now) isEqualTo 4 && {abs ((((_now select 0) select 3)) - _a) < 0.01}, format ["%1 : fond attendu à %2, état %3", _nom, _a, _now]] call AT_fnc_check;
            [(_now param [1, false]) isEqualTo (_ton isEqualTo "LIGHT"), format ["%1 : couleur du fond (%2)", _nom, _ton]] call AT_fnc_check;
        };
        [format ["carte_fond_noms_%1", _nom]] call AT_fnc_shot;
    } forEach [["non", 0, "DARK"], ["leger", 0.45, "DARK"], ["normal", 0.7, "DARK"], ["opaque", 0.92, "DARK"], ["clair", 0.7, "LIGHT"]];
    // Défaut : LÉGER sombre.
    { profileNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_NameBgAlpha", "COMSPEC_ATAK_NameBgTone"];
    uiNamespace setVariable ["COMSPEC_ATAK_NameBgCfg", []];
    uiSleep 1;
    private _def = uiNamespace getVariable ["COMSPEC_ATAK_NameBgNow", []];
    [(count _def) isEqualTo 4 && {abs ((((_def select 0) select 3)) - 0.45) < 0.01} && {!(_def select 1)}, format ["défaut attendu LÉGER sombre : %1", _def]] call AT_fnc_check;
    ["carte_fond_noms"] call AT_fnc_shot;
    "indicatifs, noms des lieux et des repères, distance au curseur sur un fond à la taille exacte du texte, du bon côté du symbole" call AT_fnc_visuel;
    [_style] call comspec_atak_native_fnc_mapStyle;
    deleteVehicle _ia;
    call AT_fnc_done;
}, 60] call AT_fnc_run;
