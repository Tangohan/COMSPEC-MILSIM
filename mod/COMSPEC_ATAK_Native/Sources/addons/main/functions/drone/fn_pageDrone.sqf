/*
    App Drone : pilotage d'un drone posé par le joueur (quadricoptère ou drone de sac à dos).
    Appairage au pied du pilote, puis six onglets (clé droneTab de l'état) :
      VOL      (PILOT)   : caméra (qualité, vue totale, sens du glisser, source du flux), décollage, stationnaire, suivi,
                           retour, atterrissage, altitude, vitesse, profil CQB, veille, dépannage, identité, transfert ;
      TÂCHES   (TASK)    : tâche sur point (grille ou carte) : aller, orbite, observer, escorte ; frappe ; recherche ;
      CIBLES   (TARGETS) : cibles marquées au viseur (LOCK) à suivre l'une après l'autre dans l'ordre choisi (fn_droneSeq),
                           puis les détections automatiques de la caméra (ex-onglet PISTES) : caméra dessus, marquer, marqueur sur la carte, frapper ;
      PHOTOS   (PHOTO)   : clichés pris (fn_dronePhoto) ; la fiche d'un cliché (titre, description, SSE, confidentiel,
                           envoi) s'ouvre en fenêtre plein écran (fn_dronePhoto "modal") à la place de toute la page ;
      JOURNAL  (LOG)     : chaque événement daté.
      FLOTTE   (FLEET)   : drones appairés au téléphone (fn_droneFleet) : rôle de chacun, PILOTER, ordres à l'essaim, ajout d'un drone.
    Vue caméra (camBind de fn_droneAction : la caméra rend avant que l'image soit posée) :
      PLEIN        : télémétrie, image 16/9, barre de commandes groupée (CAMÉRA, CIBLES, VOL, AFFICHAGE) ;
      MENUS        : image réduite en haut, barre compacte, onglets dessous (clé droneCamSplit) ;
      VUE TOTALE   : téléphone en petit format (profil COMSPEC_ATAK_DroneMiniFull, oui par défaut) : l'image occupe toute
                     la page, deux incrustations courtes, une rangée de boutons si le téléphone est en main ;
      VISEUR PHOTO : (clé droneShotView, fn_dronePhoto "view") image, coins de cadrage et tiers, outils CADRAGE (zoom,
                     recentrer, vision), CIBLE (marquer), SURLIGNER (cadre, cercle tracés à la souris), PHOTO (prendre).
    Libellés des boutons : ce que fait l'appui, en clair ; le détail est dans l'infobulle.
    Sans flux (liaison perdue, drone perdu ou en veille, PiP coupée) : neige « NO DATA » sur l'image, liaison faible :
    défauts de réception TNT (fn_droneStatic).
    Ordres : fn_droneAction ; exécution : fn_droneCmd.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _fs = _l get "fontSmall";
private _mini = _l getOrDefault ["mini", false];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _inter = _s getOrDefault ["interactive", false];
private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
private _act = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_droneAction;", str _a, str _v] };
private _rows = [];
uiNamespace setVariable ["COMSPEC_ATAK_DroneFeedTxt", []];
uiNamespace setVariable ["COMSPEC_ATAK_DroneGimTxt", [controlNull, ""]];
uiNamespace setVariable ["COMSPEC_ATAK_DroneOsd", controlNull];
uiNamespace setVariable ["COMSPEC_ATAK_DroneMenusShown", false];

// Fiche d'un cliché ouverte : fenêtre de transmission sur tout l'écran (fn_dronePhoto "modal"), rien d'autre dessous.
private _phEd = _s getOrDefault ["dronePhEdit", -1];
if (_phEd >= 0 && {_phEd < count (["list"] call comspec_atak_native_fnc_dronePhoto)}) exitWith { ["modal", [_bw, _bh]] call comspec_atak_native_fnc_dronePhoto; true };

// Liste des drones à gauche, comme l'app Vidéo Live (retour de TTRD, 14/10 : « refais le menu drone comme l'app Live
// avec un select à gauche ») : drones de la flotte (choisir = le piloter), puis drones posés à moins de 15 m (choisir =
// l'appairer). Téléphone couché en grand format seulement ; le reste de la page (image, commandes, onglets) occupe la
// colonne de droite, à partir de _ox.
// Drone appairé : la liste est raccourcie à ses lignes et les onglets du drone (VOL, TÂCHES, CIBLES, PHOTOS, FLOTTE,
// JOURNAL) et leur contenu passent dessous, dans la colonne de gauche (retour de TTRD, 19/10 : « faut déplacer le menu
// d'action à gauche, là c'est trop la merde le scroll ») : l'image et sa barre tiennent à droite sans défilement.
private _ox = 0;
private _leftMenus = (_l get "landscape") && {!_mini} && {!isNull _d};
private _menuRect = [];
if ((_l get "landscape") && {!_mini}) then {
    private _lw = (_bw - 0.012) * ([0.3, 0.38] select _leftMenus);
    private _list = ["COMSPEC_RscListBox", [_pad, _pad, _lw, _bh - 2 * _pad]] call comspec_atak_native_fnc_pageCtrl;
    _list ctrlSetFontHeight _fs;
    private _fleet = ["list"] call comspec_atak_native_fnc_droneFleet;
    private _hdr = _list lbAdd (("DRONES APPAIRÉS") call comspec_atak_native_fnc_tr);
    _list lbSetColor [_hdr, [0.36, 0.78, 0.42, 1]];
    {
        (["link", _x] call comspec_atak_native_fnc_droneFleet) params ["_bars", "_dist", "_ok"];
        // Nom, distance, batterie, puis l'état (traduit seul : le nom du drone reste tel quel).
        private _i = _list lbAdd ((format ["%1 · %2 m · %3 %%", [_x, [], "NAME"] call comspec_atak_native_fnc_droneOsd, round (player distance _x), round (100 * fuel _x)])
            + ((["", "Piloté", "Hors liaison"] select ([[2, 0] select _ok, 1] select (_x isEqualTo _d))) call { [" · " + (_this call comspec_atak_native_fnc_tr), ""] select (_this isEqualTo "") })); // i18n:brut (déjà traduit)
        _list lbSetData [_i, "FLY:" + netId _x];
        _list lbSetPicture [_i, "\z\comspec_atak_native\addons\main\data\app_drone.paa"];
        _list lbSetColor [_i, [[0.9, 0.28, 0.23, 1], [0.92, 0.96, 0.93, 1]] select _ok];
        _list lbSetTooltip (([_i, (format ["%1 · liaison %2/4 · %3 m du sol", toLower (_x getVariable ["COMSPEC_DroneMode", "HOVER"]), _bars, round ((getPosATL _x) select 2)]) call comspec_atak_native_fnc_tr]) call comspec_atak_native_fnc_tr);
        if (_x isEqualTo _d) then { _list lbSetCurSel _i; };
    } forEach _fleet;
    if ((count _fleet) isEqualTo 0) then { private _i = _list lbAdd (("Aucun drone appairé") call comspec_atak_native_fnc_tr); _list lbSetColor [_i, [0.54, 0.6, 0.58, 1]]; };
    private _near = (player nearEntities [["Air", "Mavic_drone_base_F"], 15]) select { alive _x && {unitIsUAV _x} && {!(_x in _fleet)} && {(_x getVariable ["COMSPEC_DroneOwner", ""]) in ["", getPlayerUID player]} };
    private _hdr2 = _list lbAdd (("À PORTÉE (MOINS DE 15 M)") call comspec_atak_native_fnc_tr);
    _list lbSetColor [_hdr2, [0.36, 0.78, 0.42, 1]];
    {
        private _i = _list lbAdd ((format ["%1 · %2 m · ", [_x, [], "NAME"] call comspec_atak_native_fnc_droneOsd, round (player distance _x)]) + ("Appairer" call comspec_atak_native_fnc_tr)); // i18n:brut (déjà traduit)
        if ((count _fleet) < 4) then { _list lbSetData [_i, "PAIR:" + netId _x]; };
        _list lbSetPicture [_i, "\z\comspec_atak_native\addons\main\data\app_drone.paa"];
        _list lbSetColor [_i, [0.95, 0.67, 0.2, 1]];
        _list lbSetTooltip (([_i, (["Choisir pour appairer ce drone au téléphone (il devient le drone piloté)", "Flotte complète (4 drones) : libérez-en un d'abord"] select ((count _fleet) >= 4)) call comspec_atak_native_fnc_tr]) call comspec_atak_native_fnc_tr);
    } forEach _near;
    if ((count _near) isEqualTo 0) then { private _i = _list lbAdd (("Aucun drone libre posé près de vous") call comspec_atak_native_fnc_tr); _list lbSetColor [_i, [0.54, 0.6, 0.58, 1]]; };
    _list ctrlAddEventHandler ["LBSelChanged", {
        params ["_c", "_i"];
        // Données : "FLY:<netId>" (drone de la flotte), "PAIR:<netId>" (drone à appairer), "" (titre, ligne vide).
        private _id = _c lbData _i;
        private _p = _id find ":";
        if (_p < 0) exitWith {};
        private _k = _id select [0, _p];
        private _o = objectFromNetId (_id select [_p + 1]);
        if (isNull _o) exitWith {};
        if (_k isEqualTo "FLY") exitWith {
            if (_o isEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull])) exitWith {};
            [{ ["switch", _this] call comspec_atak_native_fnc_droneFleet; }, _o] call CBA_fnc_execNextFrame;
        };
        if (_k isEqualTo "PAIR") then { [{ ["pair", _this] call comspec_atak_native_fnc_droneAction; }, _o] call CBA_fnc_execNextFrame; };
    }];
    if (_leftMenus) then {
        private _lh = (((lbSize _list) + 0.6) * _fs * 1.75) min (_bh * 0.34);
        _list ctrlSetPosition [_pad, _pad, _lw, _lh];
        _list ctrlCommit 0;
        _menuRect = [_pad, _pad * 2 + _lh, _lw, _bh - _pad * 3 - _lh];
    };
    _ox = _pad * 2 + _lw;
    _bw = _bw - _ox;
};

if (isNull _d) exitWith {
    // Drones à portée de main : posés au sol à moins de 15 m, sans pilote humain.
    private _near = (player nearEntities [["Air", "Mavic_drone_base_F"], 15]) select { alive _x && {unitIsUAV _x} && {(_x getVariable ["COMSPEC_DroneOwner", ""]) in ["", getPlayerUID player]} };
    _rows append [
        ["title", "Drone"],
        ["text", "<t color='#8a9a93'>Posez le drone à vos pieds (depuis le sac à dos : Assembler), puis appairez-le au téléphone. La liaison porte à quelques kilomètres, beaucoup moins derrière le relief. Si elle est perdue, le drone rentre seul à son point de décollage.</t>"]
    ];
    if ((count _near) isEqualTo 0) then { _rows pushBack ["text", "<t color='#f2ab33'>Aucun drone posé à moins de 15 m de vous.</t>"]; };
    {
        _rows pushBack ["person", getText (configOf _x >> "picture"),
            format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='#8a9a93'>À %2 m · batterie %3 %%</t>", [_x, [], "NAME"] call comspec_atak_native_fnc_droneOsd, round (player distance _x), round (100 * fuel _x)],
            [["APPAIRER", compile format ["['pair', objectFromNetId %1] call comspec_atak_native_fnc_droneAction;", str netId _x], true]]];
    } forEach _near;
    _rows pushBack ["buttons", [["ACTUALISER", { [{ ["DRONE"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }]]];
    private _lg = missionNamespace getVariable ["COMSPEC_ATAK_DroneLog", []];
    if ((count _lg) > 0) then {
        _rows pushBack ["section", "Journal du dernier vol", format ["%1 événements", count _lg]];
        _rows pushBack ["text", ((_lg select [((count _lg) - 8) max 0]) apply { format ["<t size='0.8'><t color='#8a9a93'>%1</t>  <t color='%3'>%2</t></t>", _x select 0, _x select 1, ["#c9d4cf", _x select 2] select ((_x select 2) isNotEqualTo "")] }) joinString "<br/>"];
    };
    [_rows, [_ox, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};

private _link = ["link"] call comspec_atak_native_fnc_droneAction;
private _tab = _s getOrDefault ["droneTab", "PILOT"];
if (_tab isEqualTo "TRACKS") then { _tab = "TARGETS"; _s set ["droneTab", _tab]; };
private _m = _d getVariable ["COMSPEC_DroneMode", "HOVER"];
private _cqb = _d getVariable ["COMSPEC_DroneCqb", false];
private _alt = _s getOrDefault ["droneAlt", 40];
private _spd = _s getOrDefault ["droneSpd", 40];
private _rad = _s getOrDefault ["droneHuntRad", 250];
private _armed = (_d getVariable ["COMSPEC_DroneArmed", ""]) isNotEqualTo "";
private _strikeOk = missionNamespace getVariable ["comspec_atak_native_drone_strike", true];
// Caméra libérée après un long moment hors écran : rouverte en revenant sur l'app.
if (missionNamespace getVariable ["COMSPEC_ATAK_DroneCamParked", false]) then { ["camEnsure"] call comspec_atak_native_fnc_droneAction; };
private _camOn = !isNull (missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull]);
private _ground = ((getPosATL _d) select 2) < 1 && {_m isNotEqualTo "STRIKE"};
private _conf = _s getOrDefault ["droneConfirm", ["", 0]];
private _confirming = { params ["_k"]; (_conf select 0) isEqualTo _k && {diag_tickTime - (_conf select 1) < 5} };
private _split = _s getOrDefault ["droneCamSplit", false];
private _locksAll = missionNamespace getVariable ["COMSPEC_ATAK_Locks", createHashMap];
private _photos = profileNamespace getVariable ["COMSPEC_ATAK_DronePhotos", []];
if !(_photos isEqualType []) then { _photos = []; };
// App Drone : toujours la caméra du drone (le choix téléphone / casque est sur la vue drone de la carte).
private _src = "DRONE";
// Viseur photo (fn_dronePhoto "view") : seulement caméra ouverte.
private _shotView = _s getOrDefault ["droneShotView", false];
if (_shotView && {!_camOn}) then { _shotView = false; _s set ["droneShotView", false]; };
// Vue à l'écran : "" (menus seuls), "SPLIT" (image réduite et menus), "FULL" (image et barre), "TOTAL" (image seule, petit
// format), "SHOT" (viseur photo : image et outils de prise de vue, sans les onglets).
private _view = switch (true) do {
    case (!_camOn): { "" };
    case (_shotView): { "SHOT" };
    case (_leftMenus): { "FULL" };
    case (_tab isEqualTo "LOG"): { "" };
    case (_split): { "SPLIT" };
    case (_mini && {profileNamespace getVariable ["COMSPEC_ATAK_DroneMiniFull", true]}): { "TOTAL" };
    default { "FULL" };
};

// Télémétrie en haut (deux lignes), rafraîchie chaque seconde. Petit format avec l'image : les incrustations la portent.
private _top = 0;
if (_view isEqualTo "" || {!_mini && {!(_view in ["TOTAL", "SHOT"])}}) then {
    private _osd = ["COMSPEC_RscMapPanel", [_ox, 0, _bw, _fs * 2.6]] call comspec_atak_native_fnc_pageCtrl;
    _osd ctrlSetStructuredText parseText ((([_d, _link] call comspec_atak_native_fnc_droneOsd)) call comspec_atak_native_fnc_tr);
    private _osdH = ((ctrlTextHeight _osd) + _fs * 0.5) max (_fs * 2.6);
    _osd ctrlSetPosition [_ox, 0, _bw, _osdH];
    _osd ctrlCommit 0;
    uiNamespace setVariable ["COMSPEC_ATAK_DroneOsd", _osd];
    _top = _osdH;
};

// Vue caméra : tient dans l'écran du téléphone sans défilement. Image 16/9 centrée dans la place restante, incrustations
// posées sur l'image, barre de commandes dessous.
// Nacelle : glisser sur l'image (cap et site), molette ou touches + / - = zoom continu, flèches ou ZQSD/WASD quand la
// souris est sur l'image, RECENTRER ; un clic sans glisser vise le point (et marque la cible en mode marquage).
// Viseur photo, outil CADRE ou CERCLE choisi : glisser trace le surlignage au lieu de tourner la nacelle.
if (_view isNotEqualTo "") then {
    private _zoom = missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1];
    private _aim = missionNamespace getVariable ["COMSPEC_ATAK_DroneCamTgt", ""];
    private _markMode = uiNamespace getVariable ["COMSPEC_ATAK_DroneMarkMode", false];
    private _vis = missionNamespace getVariable ["COMSPEC_ATAK_DroneVision", 0];
    private _q = ((profileNamespace getVariable ["COMSPEC_ATAK_DroneQuality", 0]) max 0) min 1;
    private _tool = ["", uiNamespace getVariable ["COMSPEC_ATAK_DroneShotTool", ""]] select (_view isEqualTo "SHOT");
    private _shapes = missionNamespace getVariable ["COMSPEC_ATAK_DroneShotMarks", []];
    private _alts = [[15, 30, 60, 100, 150], [2, 3, 5, 8]] select _cqb;
    private _up = (_alts select { _x > _alt }) param [0, -1];
    private _dn = (_alts select { _x < _alt }) param [(count (_alts select { _x < _alt })) - 1, -1];
    private _toTab = { params ["_t"]; compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['droneCamSplit', true]; ['tab', %1] call comspec_atak_native_fnc_droneAction;", str _t] };
    private _zoomBy = { params ["_f"]; compile format ["['zoomBy', %1] call comspec_atak_native_fnc_droneAction; ['gimbalHud'] call comspec_atak_native_fnc_droneAction;", _f] };
    // Boutons : [libellé, code, état ("" | "on" vert | "red" rouge | "main" principal), aide (infobulle), actif].
    // Libellés : ce que fait l'appui, en clair ; le détail est dans l'infobulle.
    private _bShot = ["PHOTO", { ["view"] call comspec_atak_native_fnc_dronePhoto; }, "", "Ouvre le viseur photo : cadrer (glisser sur l'image), zoomer (molette, + / -), marquer une cible, surligner un cadre ou un cercle, puis PRENDRE LA PHOTO"];
    private _bZoomOut = ["ZOOM -", [1 / 1.5] call _zoomBy, "", "Dézoomer (molette vers vous, touche -) ; zoom actuel affiché sur l'image", _zoom > 1];
    private _bZoomIn = ["ZOOM +", [1.5] call _zoomBy, "", "Zoomer jusqu'à x8 (molette vers l'avant, touche +)", _zoom < 8];
    private _bVis = [["VISION JOUR", "VISION NUIT", "THERMIQUE"] select _vis, ["vision"] call _act, ["", "on"] select (_vis > 0), "Change le mode de la caméra : jour → nuit (intensification de lumière) → thermique"];
    // Rangée des modes de vision, comme l'app Vidéo Live : JOUR, VISION NOCTURNE, THERMIQUE (le mode actif en vert).
    private _visRow = [["JOUR", 0], ["VISION NOCTURNE", 1], ["THERMIQUE", 2]] apply { [_x select 0, ["visionSet", _x select 1] call _act, ["", "on"] select (_vis isEqualTo (_x select 1)), "Mode de la caméra du drone"] };
    private _bNadir = [["CAMÉRA VERS LE SOL", "CAMÉRA DEVANT"] select (_aim isEqualTo "NADIR"), ["camAim", ["NADIR", ""] select (_aim isEqualTo "NADIR")] call _act, "", ["Tourne la caméra à la verticale, vers le sol sous le drone", "Remet la caméra devant le drone"] select (_aim isEqualTo "NADIR"), _src isEqualTo "DRONE"];
    private _bCenter = ["RECENTRER", { ["recenter"] call comspec_atak_native_fnc_droneAction; ["gimbalHud"] call comspec_atak_native_fnc_droneAction; }, "", "Remet la caméra droit devant le drone, 37° vers le bas", _src isEqualTo "DRONE"];
    private _bMark = [["MARQUER", "CLIQUEZ LA CIBLE"] select _markMode, ["markMode"] call _act, ["", "red"] select _markMode, "Appuyez, puis cliquez sur une personne ou un véhicule dans l'image : il est marqué (LOCK) et suivi sur la carte. Rappuyez pour arrêter de marquer"];
    private _bTargets = [format ["CIBLES (%1)", count _locksAll], ["TARGETS"] call _toTab, ["", "on"] select ((["status"] call comspec_atak_native_fnc_droneSeq) select 0), "Ouvre la liste des cibles marquées : les faire suivre par le drone, dans l'ordre choisi"];
    private _bUp = [[format ["MONTER %1 m", _up], "MONTER"] select (_up < 0), ["alt", _up] call _act, "", format ["Altitude actuelle %1 m : monter au palier suivant", _alt], _up > 0];
    private _bDn = [[format ["DESCENDRE %1 m", _dn], "DESCENDRE"] select (_dn < 0), ["alt", _dn] call _act, "", format ["Altitude actuelle %1 m : descendre au palier précédent", _alt], _dn > 0];
    private _bHover = ["STATIONNAIRE", ["mode", "hover"] call _act, ["", "on"] select (_m isEqualTo "HOVER"), "Le drone s'arrête et reste en vol stationnaire là où il est"];
    private _bQ = [format ["NETTETÉ : %1", ["BASSE", "HAUTE"] select _q], ["quality", 1 - _q] call _act, "", "Qualité de l'image : basse (512 px, plus fluide) ou haute (1024 px, plus nette, plus lourde)"];
    private _bMenus = [["MENUS DU DRONE", "AGRANDIR L'IMAGE"] select _split, { private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _st set ["droneCamSplit", !(_st getOrDefault ["droneCamSplit", false])]; ["DRONE"] call comspec_atak_native_fnc_pageRender; }, "", ["Réduit l'image et affiche dessous les onglets du drone (vol, tâches, cibles, photos, journal)", "Image à la taille de l'écran, onglets masqués"] select _split];
    private _bTotal = [["PLEIN ÉCRAN", "QUITTER LE PLEIN ÉCRAN"] select (_view isEqualTo "TOTAL"), { profileNamespace setVariable ["COMSPEC_ATAK_DroneMiniFull", !(profileNamespace getVariable ["COMSPEC_ATAK_DroneMiniFull", true])]; ["DRONE"] call comspec_atak_native_fnc_pageRender; }, "", "Téléphone en petit format : l'image du drone occupe tout l'écran"];
    // Secours si l'image reste noire (PiP qui ne rend rien) : la vue du jeu passe sur la caméra du drone (fn_droneAction "direct").
    // Mis dans la rangée principale (retour de TTRD, 13/10 : « permets le plein écran pour compenser le bug »).
    private _bDirect = ["CAMÉRA PLEIN ÉCRAN", ["direct"] call _act, "", "Plein écran sur la caméra du drone, sans passer par l'image du téléphone (utile si elle reste noire). Échap pour revenir au téléphone"];
    private _bClose = ["FERMER", ["cam"] call _act, "", "Coupe l'image du drone (le drone continue ce qu'il fait)"];
    // Diagnostic de l'image noire : la vidéo montrée de 9 façons, lettres A à I (fn_pipCheck "test").
    private _bPipTest = ["TEST VIDÉO", { ["test"] call comspec_atak_native_fnc_pipCheck; }, "", "Image noire ? Montre 30 s la vidéo de plusieurs façons (lettres A à I) : dites-nous lesquelles restent noires"];
    // Viseur photo.
    private _toolCode = { params ["_k"]; compile format ["['tool', %1] call comspec_atak_native_fnc_dronePhoto;", str _k] };
    private _bBox = [["CADRE", "CADRE : TRACEZ"] select (_tool isEqualTo "BOX"), ["BOX"] call _toolCode, ["", "on"] select (_tool isEqualTo "BOX"), "Surligner d'un cadre jaune : appuyez, puis glissez sur l'image (4 surlignages au plus). Rappuyez pour reprendre la main sur la caméra", (count _shapes) < 4 || {_tool isEqualTo "BOX"}];
    private _bCircle = [["CERCLE", "CERCLE : TRACEZ"] select (_tool isEqualTo "CIRCLE"), ["CIRCLE"] call _toolCode, ["", "on"] select (_tool isEqualTo "CIRCLE"), "Surligner d'un cercle jaune : appuyez, puis glissez sur l'image autour de la zone. Rappuyez pour reprendre la main sur la caméra", (count _shapes) < 4 || {_tool isEqualTo "CIRCLE"}];
    private _bErase = [format ["EFFACER (%1)", count _shapes], { ["markClear"] call comspec_atak_native_fnc_dronePhoto; }, "", "Efface les cadres et cercles tracés", (count _shapes) > 0];
    private _bCancel = ["QUITTER LE VISEUR", { ["viewExit"] call comspec_atak_native_fnc_dronePhoto; }, "", "Revient à l'image du drone sans prendre de photo"];
    private _bTake = ["PRENDRE LA PHOTO", { ["take"] call comspec_atak_native_fnc_dronePhoto; }, "main", "Prend la photo telle que cadrée, avec ses surlignages, puis ouvre la fiche de transmission"];
    // Réglages repliés (retour de TTRD : « pas clair ») : PLUS les déplie sur une rangée à part.
    private _more = _s getOrDefault ["droneCamMore", false];
    private _bMore = [["RÉGLAGES", "MASQUER"] select _more, { private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _st set ["droneCamMore", !(_st getOrDefault ["droneCamMore", false])]; ["DRONE"] call comspec_atak_native_fnc_pageRender; }, ["", "on"] select _more, "Autres réglages de la caméra : vers le sol, recentrer, netteté, test vidéo, fermer la caméra"];
    // Groupes de la barre : [titre, boutons]. Titre "*" : rangée principale, boutons plus grands, sans titre.
    // Vue caméra : rangée principale (photo, marquer, zoom, vision), puis le vol, puis les réglages repliés.
    private _labels = !_mini && {_view isEqualTo "SHOT"};
    // CAMÉRA PLEIN ÉCRAN (vue directe) est déjà sur l'image (bouton PLEIN ÉCRAN) : rangée principale allégée, le
    // bouton reste dans les réglages. Onglets à gauche (_leftMenus) : plus de bouton MENUS DU DRONE.
    private _moreRow = [[], [["", [_bNadir, _bCenter, _bQ, _bDirect, _bPipTest, _bClose]]]] select _more;
    private _flyRow = [_bUp, _bDn, _bHover] + ([[_bMenus], []] select _leftMenus);
    private _groups = switch (true) do {
        case (_view isEqualTo "SHOT"): { [["CADRAGE", [_bZoomOut, _bZoomIn, _bCenter, _bVis]], ["CIBLE", [_bMark]], ["SURLIGNER", [_bBox, _bCircle, _bErase]], ["PHOTO", [_bCancel, _bTake]]] };
        case (_view isEqualTo "TOTAL"): { [["", [_bShot, _bMark, _bZoomOut, _bZoomIn, _bTotal]]] select [0, [0, 1] select _inter] };
        case (_mini): { [["", [_bShot, _bMark, _bDirect, _bZoomOut, _bZoomIn]], ["", [_bVis, _bTotal, _bMenus, _bClose]]] };
        case (_view isEqualTo "SPLIT"): { [["*", [_bShot, _bMark, _bZoomOut, _bZoomIn]], ["", _visRow], ["", _flyRow + [_bMore]]] + _moreRow };
        default { [["*", [_bShot, _bMark, _bZoomOut, _bZoomIn]], ["", _visRow], ["", _flyRow + ([[_bTargets], []] select _leftMenus) + [_bMore]]] + _moreRow };
    };
    // Rangées : largeur de chaque bouton selon la longueur de son libellé ; un groupe passe à la rangée suivante quand
    // la rangée serait trop chargée pour rester lisible.
    private _btnH = _fs * ([1.5, 1.3] select _mini);
    private _bfs = _fs * ([0.74, 0.64] select _mini);
    private _lh = [0, _fs * 1.1] select _labels; // titre size 0.72 + interligne
    private _weight = { params ["_lbl"]; (count _lbl) max 6 };
    private _cap = ((_bw - _pad) / (_bfs * 0.6)) max 12;
    // Groupe trop long pour une rangée : coupé, la suite (sans titre) passe à la rangée suivante.
    private _cut = [];
    {
        _x params ["_gl", "_gb"];
        private _part = [];
        private _n = 0;
        {
            private _wt = ([_x select 0] call _weight) + 2;
            if ((count _part) > 0 && {_n + _wt > _cap}) then { _cut pushBack [_gl, _part]; _gl = ["", "*"] select (_gl isEqualTo "*"); _part = []; _n = 0; };
            _part pushBack _x;
            _n = _n + _wt;
        } forEach _gb;
        if ((count _part) > 0) then { _cut pushBack [_gl, _part]; };
    } forEach _groups;
    _groups = _cut;
    // Une rangée principale (titre "*") ne partage pas sa rangée avec un groupe ordinaire.
    private _barRows = [];
    private _cnt = 0;
    private _bigPrev = false;
    {
        private _n = 0;
        { _n = _n + ([_x select 0] call _weight) + 2; } forEach (_x select 1);
        private _big = (_x select 0) isEqualTo "*";
        if ((count _barRows) isEqualTo 0 || {_big isNotEqualTo _bigPrev} || {_cnt > 0 && {_cnt + _n > _cap}}) then { _barRows pushBack []; _cnt = 0; };
        (_barRows select ((count _barRows) - 1)) pushBack _x;
        _cnt = _cnt + _n;
        _bigPrev = _big;
    } forEach _groups;
    private _rowBig = { params ["_row"]; ((_row param [0, ["", []]]) select 0) isEqualTo "*" };
    private _rowH = { params ["_row"]; [_lh + _btnH, _btnH * 1.45] select ([_row] call _rowBig) };
    private _barH = 0;
    { _barH = _barH + ([_x] call _rowH) + _pad * 0.3; } forEach _barRows;
    if ((count _barRows) > 0) then { _barH = _barH + _pad * 0.3; };
    private _k169 = 0.5625 * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
    private _areaH = if (_view isEqualTo "SPLIT") then { (_bw * _k169) min (_bh * 0.45) } else { _bh - _top - _barH };
    // Image 16/9 ajustée à la place disponible (bandes noires sur les côtés ou dessus / dessous).
    private _iw = _bw min (_areaH / _k169);
    private _ih = _iw * _k169;
    private _ix = _ox + (_bw - _iw) / 2;
    private _iy = _top + (_areaH - _ih) / 2;
    private _bg = ["COMSPEC_RscText", [_ox, _top, _bw, _areaH + _barH]] call comspec_atak_native_fnc_pageCtrl;
    _bg ctrlSetBackgroundColor [0, 0, 0, 1];
    // Texture de la caméra : rendu lancé (caméra recréée au besoin) avant que l'image soit posée.
    private _tex = ["camBind"] call comspec_atak_native_fnc_droneAction;
    private _pic = ["COMSPEC_RscPhone", [_ix, _iy, _iw, _ih], _tex] call comspec_atak_native_fnc_pageCtrl;
    // Journal RPT de l'ouverture du flux (fn_pipCheck : pourquoi la vue resterait noire) et texture rebranchée sur
    // l'image après le premier rendu de la caméra.
    ["feedWatch", [_pic, _tex]] call comspec_atak_native_fnc_droneAction;
    // Pas de flux : neige « NO DATA » ; liaison faible : défauts TNT (fn_droneStatic, image rebranchée au besoin).
    ["build", [_ix, _iy, _iw, _ih, _pic]] call comspec_atak_native_fnc_droneStatic;
    // Réticule fin au centre.
    private _cx = _ix + _iw / 2;
    private _cy = _iy + _ih / 2;
    private _rw = _iw * 0.035;
    private _rh = _rw * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
    {
        private _r = ["COMSPEC_RscText", _x] call comspec_atak_native_fnc_pageCtrl;
        _r ctrlSetBackgroundColor ([[1, 1, 1, 0.85], [0.95, 0.3, 0.25, 0.95]] select _markMode);
    } forEach [
        [_cx - _rw * 1.4, _cy - pixelH, _rw, pixelH * 2], [_cx + _rw * 0.4, _cy - pixelH, _rw, pixelH * 2],
        [_cx - pixelW, _cy - _rh * 1.4, pixelW * 2, _rh], [_cx - pixelW, _cy + _rh * 0.4, pixelW * 2, _rh]
    ];
    // Mode marquage : cadre rouge autour de l'image.
    if (_markMode) then {
        {
            private _r = ["COMSPEC_RscText", _x] call comspec_atak_native_fnc_pageCtrl;
            _r ctrlSetBackgroundColor [0.9, 0.25, 0.2, 0.9];
        } forEach [[_ix, _iy, _iw, pixelH * 3], [_ix, _iy + _ih - pixelH * 3, _iw, pixelH * 3], [_ix, _iy, pixelW * 3, _ih], [_ix + _iw - pixelW * 3, _iy, pixelW * 3, _ih]];
    };
    // Viseur photo : coins de cadrage blancs, tiers de l'image en pointillé léger, surlignages déjà tracés.
    // Surlignage : [genre "BOX" | "CIRCLE", u0, v0, u1, v1] en fractions de l'image ; contrôles : 4 bords et un anneau.
    private _place = {
        params ["_ctrls", "_kind", "_r"];
        _r params ["_sx", "_sy", "_sw", "_sh"];
        private _tw = pixelW * 3;
        private _th = pixelH * 3;
        {
            _x ctrlSetPosition ([[_sx, _sy, _sw, _th], [_sx, _sy + _sh - _th, _sw, _th], [_sx, _sy, _tw, _sh], [_sx + _sw - _tw, _sy, _tw, _sh], [_sx, _sy, _sw, _sh]] select _forEachIndex);
            _x ctrlCommit 0;
            _x ctrlShow ([_kind isEqualTo "BOX", _kind isEqualTo "CIRCLE"] select (_forEachIndex isEqualTo 4));
        } forEach _ctrls;
    };
    private _shapeCtrls = {
        private _c = [1, 2, 3, 4] apply {
            private _e = ["COMSPEC_RscText", [0, 0, 0, 0]] call comspec_atak_native_fnc_pageCtrl;
            _e ctrlSetBackgroundColor [1, 0.85, 0.1, 0.95];
            _e ctrlEnable false;
            _e
        };
        private _ring = ["RscPicture", [0, 0, 0, 0], "\A3\ui_f\data\map\markers\military\circle_CA.paa"] call comspec_atak_native_fnc_pageCtrl;
        _ring ctrlSetTextColor [1, 0.85, 0.1, 0.95];
        _ring ctrlEnable false;
        _c pushBack _ring;
        { _x ctrlShow false; } forEach _c;
        _c
    };
    if (_view isEqualTo "SHOT") then {
        private _cl = _iw * 0.06;
        private _ch = _cl * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
        private _m = _iw * 0.03;
        private _mv = _m * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
        {
            _x params ["_kx", "_ky", "_sx", "_sy"];
            { private _b = ["COMSPEC_RscText", _x] call comspec_atak_native_fnc_pageCtrl; _b ctrlSetBackgroundColor [1, 1, 1, 0.9]; _b ctrlEnable false; } forEach [
                [[_kx, _kx - _cl] select (_sx < 0), _ky, _cl, pixelH * 2], [_kx, [_ky, _ky - _ch] select (_sy < 0), pixelW * 2, _ch]];
        } forEach [[_ix + _m, _iy + _mv, 1, 1], [_ix + _iw - _m, _iy + _mv, -1, 1], [_ix + _m, _iy + _ih - _mv, 1, -1], [_ix + _iw - _m, _iy + _ih - _mv, -1, -1]];
        {
            private _b = ["COMSPEC_RscText", _x] call comspec_atak_native_fnc_pageCtrl;
            _b ctrlSetBackgroundColor [1, 1, 1, 0.18];
            _b ctrlEnable false;
        } forEach [[_ix + _iw / 3, _iy, pixelW, _ih], [_ix + _iw * 2 / 3, _iy, pixelW, _ih], [_ix, _iy + _ih / 3, _iw, pixelH], [_ix, _iy + _ih * 2 / 3, _iw, pixelH]];
        {
            _x params ["_kind", "_u0", "_v0", "_u1", "_v1"];
            [call _shapeCtrls, _kind, [_ix + (_u0 min _u1) * _iw, _iy + (_v0 min _v1) * _ih, (abs (_u1 - _u0)) * _iw, (abs (_v1 - _v0)) * _ih]] call _place;
        } forEach _shapes;
        // Bandeau du viseur : ce que fait la souris maintenant.
        private _hd = ["COMSPEC_RscStructuredText", [_ix, _iy + _mv + _ch * 0.2, _iw, _fs * 1.2]] call comspec_atak_native_fnc_pageCtrl;
        _hd ctrlEnable false;
        _hd ctrlSetStructuredText parseText ((format ["<t align='center' size='0.8' font='COMSPEC_BarlowBold' shadow='2' shadowColor='#000000' color='#ffd84d'>VISEUR PHOTO · %1</t>", [
            "glisser : cadrer · molette : zoom · clic : viser le point",
            "glissez sur l'image pour tracer le cadre", "glissez sur l'image pour tracer le cercle"] select ((["", "BOX", "CIRCLE"] find _tool) max 0)]) call comspec_atak_native_fnc_tr);
    };
    // Incrustations : haut gauche, haut centre, haut droite, bas gauche, bas droite (mises à jour chaque seconde).
    // Petite image : deux lignes courtes (FEEDMINI), sans chevauchement ; les trois autres places restent vides.
    private _compact = _mini || {_iw < _fs * 24};
    private _kind = ["FEED", "FEEDMINI"] select _compact;
    uiNamespace setVariable ["COMSPEC_ATAK_DroneFeedKind", _kind];
    private _ih2 = _fs * 2.2;
    private _places = if (_compact) then {
        [[_ix + _pad / 2, _iy + _pad / 4, _iw - _pad, _fs * 1.1], [0, 0, 0, 0], [0, 0, 0, 0], [0, 0, 0, 0], [_ix + _pad / 2, _iy + _ih - _fs * 1.15, _iw - _pad, _fs * 1.1]]
    } else {
        [[_ix + _pad, _iy + _pad / 2, _iw * 0.25, _ih2], [_ix + _iw * 0.25, _iy + _pad / 2, _iw * 0.5, _ih2], [_ix + _iw * 0.75 - _pad, _iy + _pad / 2, _iw * 0.25, _ih2],
            [_ix + _pad, _iy + _ih - _fs * 1.3, _iw * 0.5, _fs * 1.2], [_ix + _iw * 0.5 - _pad, _iy + _ih - _fs * 1.3, _iw * 0.5, _fs * 1.2]]
    };
    // Viseur photo : la place du haut centre est prise par le bandeau du viseur.
    if (_view isEqualTo "SHOT" && {!_compact}) then { _places set [1, [0, 0, 0, 0]]; };
    private _txt = _places apply {
        private _t = ["COMSPEC_RscStructuredText", _x] call comspec_atak_native_fnc_pageCtrl;
        _t ctrlSetBackgroundColor [0, 0, 0, 0];
        _t ctrlEnable false;
        if ((_x select 2) isEqualTo 0) then { _t ctrlShow false; };
        _t
    };
    private _feed = [_d, _link, _kind] call comspec_atak_native_fnc_droneOsd;
    { _x ctrlSetStructuredText parseText (((_feed select _forEachIndex)) call comspec_atak_native_fnc_tr); } forEach _txt;
    uiNamespace setVariable ["COMSPEC_ATAK_DroneFeedTxt", _txt];
    // Angles de la nacelle et aide, au-dessus des incrustations du bas (grande image seulement).
    if (!_compact) then {
        private _gim = ["COMSPEC_RscStructuredText", [_ix + _iw * 0.15, _iy + _ih - _fs * 2.5, _iw * 0.7, _fs * 1.2]] call comspec_atak_native_fnc_pageCtrl;
        _gim ctrlEnable false;
        uiNamespace setVariable ["COMSPEC_ATAK_DroneGimTxt", [_gim, ""]];
        ["gimbalHud"] call comspec_atak_native_fnc_droneAction;
    };
    // Cadres LOCK des cibles marquées et cadre « visé » sous le réticule, suivis image par image.
    ["build", [_ix, _iy, _iw, _ih], "FEED", true] call comspec_atak_native_fnc_droneFlir;
    // Surface de commande de la nacelle (au-dessus de l'image et des cadres).
    // Tracé en cours (viseur, outil CADRE ou CERCLE) : aperçu jaune posé sous la souris (sous la surface de commande).
    private _prev = [[], call _shapeCtrls] select (_tool isNotEqualTo "");
    private _hit = ["COMSPEC_RscButtonInvisible", [_ix, _iy, _iw, _ih]] call comspec_atak_native_fnc_pageCtrl;
    private _disp = [] call comspec_atak_native_fnc_display;
    (ctrlPosition (_disp displayCtrl 88531)) params ["_gx", "_gy"];
    _hit setVariable ["img", [_iw, _ih]];
    _hit setVariable ["rect", [_ix, _iy, _gx + _ix, _gy + _iy]];
    _hit setVariable ["place", _place];
    _hit setVariable ["prev", _prev];
    _hit ctrlSetTooltip ((switch (true) do {
        case (_tool isNotEqualTo ""): { "Glissez pour tracer le surlignage ; rappuyez sur l'outil pour reprendre la main sur la caméra" };
        case (_markMode): { "MARQUAGE : cliquez sur la personne ou le véhicule à marquer · glisser : orienter la caméra" };
        default { "Glisser : orienter la caméra · molette ou + / - : zoom · clic : viser le point · flèches / ZQSD : caméra (Maj : pas fin)" };
    }) call comspec_atak_native_fnc_tr);
    _hit ctrlAddEventHandler ["MouseButtonDown", {
        params ["_c", "_btn", "_mx", "_my"];
        if (_btn isNotEqualTo 0) exitWith {};
        // Surlignage : point de départ en fractions de l'image.
        if ((count (_c getVariable ["prev", []])) > 0) exitWith {
            (_c getVariable ["rect", [0, 0, 0, 0]]) params ["", "", "_ox", "_oy"];
            (_c getVariable ["img", [1, 1]]) params ["_w", "_h"];
            uiNamespace setVariable ["COMSPEC_ATAK_DroneDrag", []];
            uiNamespace setVariable ["COMSPEC_ATAK_DroneAnnot", [((_mx - _ox) / _w) max 0 min 1, ((_my - _oy) / _h) max 0 min 1]];
        };
        uiNamespace setVariable ["COMSPEC_ATAK_DroneDrag", [_mx, _my, ["gimbalGet"] call comspec_atak_native_fnc_droneAction, false]];
    }];
    _hit ctrlAddEventHandler ["MouseMoving", {
        params ["_c", "_mx", "_my"];
        private _an = uiNamespace getVariable ["COMSPEC_ATAK_DroneAnnot", []];
        if ((count _an) isEqualTo 2) exitWith {
            (_c getVariable ["rect", [0, 0, 0, 0]]) params ["_rx", "_ry", "_ox", "_oy"];
            (_c getVariable ["img", [1, 1]]) params ["_w", "_h"];
            private _u = ((_mx - _ox) / _w) max 0 min 1;
            private _v = ((_my - _oy) / _h) max 0 min 1;
            [_c getVariable ["prev", []], uiNamespace getVariable ["COMSPEC_ATAK_DroneShotTool", "BOX"],
                [_rx + ((_an select 0) min _u) * _w, _ry + ((_an select 1) min _v) * _h, (abs (_u - (_an select 0))) * _w, (abs (_v - (_an select 1))) * _h]] call (_c getVariable ["place", {}]);
        };
        private _dr = uiNamespace getVariable ["COMSPEC_ATAK_DroneDrag", []];
        if ((count _dr) < 4) exitWith {};
        _dr params ["_x0", "_y0", "_g0", "_moved"];
        private _dx = _mx - _x0;
        private _dy = _my - _y0;
        if (!_moved && {(abs _dx) < pixelW * 5} && {(abs _dy) < pixelH * 5}) exitWith {};
        _dr set [3, true];
        (_c getVariable ["img", [1, 1]]) params ["_w", "_h"];
        // SAISIR L'IMAGE (défaut) : le point saisi reste sous la souris (glisser d'une largeur d'image = un champ).
        // DIRIGER LA NACELLE (profil COMSPEC_ATAK_DroneDragMode = 1) : la nacelle part du côté où l'on glisse.
        private _sg = [1, -1] select ((profileNamespace getVariable ["COMSPEC_ATAK_DroneDragMode", 0]) isEqualTo 1);
        private _tv = 0.7 / (missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1]);
        private _fovV = 2 * atan _tv;
        private _fovH = 2 * atan (_tv * (missionNamespace getVariable ["COMSPEC_ATAK_DroneCamAspect", 1]));
        ["gimbal", [(_g0 select 0) - _sg * _dx / _w * _fovH, (_g0 select 1) + _sg * _dy / _h * _fovV]] call comspec_atak_native_fnc_droneAction;
        ["gimbalHud"] call comspec_atak_native_fnc_droneAction;
    }];
    _hit ctrlAddEventHandler ["MouseButtonUp", {
        params ["_c", "_btn", "_mx", "_my"];
        if (_btn isNotEqualTo 0) exitWith {};
        private _an = uiNamespace getVariable ["COMSPEC_ATAK_DroneAnnot", []];
        uiNamespace setVariable ["COMSPEC_ATAK_DroneAnnot", []];
        if ((count _an) isEqualTo 2) exitWith {
            (_c getVariable ["rect", [0, 0, 0, 0]]) params ["", "", "_ox", "_oy"];
            (_c getVariable ["img", [1, 1]]) params ["_w", "_h"];
            ["markAdd", [_an select 0, _an select 1, ((_mx - _ox) / _w) max 0 min 1, ((_my - _oy) / _h) max 0 min 1]] call comspec_atak_native_fnc_dronePhoto;
        };
        private _dr = uiNamespace getVariable ["COMSPEC_ATAK_DroneDrag", []];
        uiNamespace setVariable ["COMSPEC_ATAK_DroneDrag", []];
        if ((count _dr) >= 4 && {!(_dr select 3)}) then { ["imgClick", [_mx, _my]] call comspec_atak_native_fnc_droneAction; };
    }];
    _hit ctrlAddEventHandler ["MouseEnter", { uiNamespace setVariable ["COMSPEC_ATAK_DroneHover", true]; }];
    _hit ctrlAddEventHandler ["MouseExit", {
        uiNamespace setVariable ["COMSPEC_ATAK_DroneHover", false];
        uiNamespace setVariable ["COMSPEC_ATAK_DroneDrag", []];
        uiNamespace setVariable ["COMSPEC_ATAK_DroneAnnot", []];
        { _x ctrlShow false; } forEach ((_this select 0) getVariable ["prev", []]);
    }];
    _hit ctrlAddEventHandler ["MouseZChanged", {
        params ["", "_z"];
        ["zoomBy", 1.15 ^ ([-1, 1] select (_z > 0))] call comspec_atak_native_fnc_droneAction;
        ["gimbalHud"] call comspec_atak_native_fnc_droneAction;
        true
    }];
    // Croix rouge en haut à droite de l'image : ferme la caméra d'un clic (retour de TTRD, 13/10), comme FERMER en bas.
    // Posée après la surface de commande de la nacelle (comme PLEIN ÉCRAN ci-dessous) : le dernier contrôle créé est
    // au-dessus et reçoit le clic. Créées avant, la surface les recouvrait et elles ne réagissaient pas (TTRD, 19/10).
    private _xs = _iw * 0.06;
    private _xh = _xs * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
    private _bx = ["COMSPEC_RscButton", [_ix + _iw - _xs - pixelW * 6, _iy + pixelH * 6, _xs, _xh], "X"] call comspec_atak_native_fnc_pageCtrl;
    _bx ctrlSetFont "COMSPEC_BarlowBold";
    _bx ctrlSetFontHeight (_xh * 0.8);
    _bx ctrlSetBackgroundColor [0.85, 0.16, 0.14, 0.95];
    _bx ctrlSetTextColor [1, 1, 1, 1];
    _bx ctrlSetTooltip ("Fermer la caméra (le drone continue ce qu'il fait)" call comspec_atak_native_fnc_tr);
    [_bx, ["cam"] call _act] call comspec_atak_native_fnc_clickNext;
    // PLEIN ÉCRAN sur l'image, comme dans l'app Photo (retour de TTRD, 14/10 : « la vue complète du drone via un bouton,
    // comme pour l'appareil photo ») : bord droit, à mi-hauteur (les incrustations occupent les coins). La vue du jeu
    // passe sur la caméra du drone (fn_droneAction "direct"), Échap revient au téléphone.
    if (_view isNotEqualTo "SHOT") then {
        private _fw = (_iw * 0.18) max (_fs * 5.5);
        private _fb = ["COMSPEC_RscButton", [_ix + _iw - _fw - pixelW * 6, _iy + (_ih - _xh) / 2, _fw, _xh], "PLEIN ÉCRAN"] call comspec_atak_native_fnc_pageCtrl;
        _fb ctrlSetFont "COMSPEC_BarlowBold";
        _fb ctrlSetFontHeight ((_xh * 0.62) min (_fs * 0.95));
        _fb ctrlSetBackgroundColor [0, 0, 0, 0.6];
        _fb ctrlSetTextColor [1, 1, 1, 1];
        _fb ctrlSetTooltip ("Vue complète de la caméra du drone en plein écran. Échap pour revenir au téléphone" call comspec_atak_native_fnc_tr);
        [_fb, ["direct"] call _act] call comspec_atak_native_fnc_clickNext;
    };
    // Clavier sur l'image : un gestionnaire par écran du téléphone (retiré avec lui). Pas proportionnel au champ :
    // un appui tourne d'environ un huitième de l'image, quel que soit le zoom (Maj : un quart de ce pas).
    if ((_disp getVariable ["COMSPEC_DroneKeyEh", -1]) < 0) then {
        _disp setVariable ["COMSPEC_DroneKeyEh", _disp displayAddEventHandler ["KeyDown", {
            params ["", "_key", "_shift"];
            // Saisie dans un champ du téléphone : rien pour la caméra, et la touche ne part pas au jeu (fn_keyBlock).
            if (_this call comspec_atak_native_fnc_keyBlock) exitWith { true };
            // Manette prise : touches de vol au drone sur la page Drone (les flèches restent à la caméra).
            if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "DRONE"
                && {["stickKey", [_key, true]] call comspec_atak_native_fnc_droneAction}) exitWith { true };
            if !(uiNamespace getVariable ["COMSPEC_ATAK_DroneHover", false]) exitWith { false };
            if (isNull (missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull])) exitWith { false };
            if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isNotEqualTo "DRONE") exitWith { false };
            private _st = (2 * atan (0.7 / (missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1]))) * ([0.12, 0.03] select _shift);
            private _v = switch (_key) do {
                case 200; case 17: { [0, _st] };
                case 208; case 31: { [0, -_st] };
                case 203; case 30: { [-_st, 0] };
                case 205; case 32: { [_st, 0] };
                default { [] };
            };
            // Zoom : Page haut / bas, + et - (clavier principal et pavé numérique).
            if (_key in [201, 209, 13, 78, 12, 74]) exitWith { ["zoomBy", [1 / 1.15, 1.15] select (_key in [201, 13, 78])] call comspec_atak_native_fnc_droneAction; ["gimbalHud"] call comspec_atak_native_fnc_droneAction; true };
            if (_v isEqualTo []) exitWith { false };
            ["gimbalNudge", _v] call comspec_atak_native_fnc_droneAction;
            ["gimbalHud"] call comspec_atak_native_fnc_droneAction;
            true
        }]];
        _disp displayAddEventHandler ["KeyUp", { ["stickKey", [_this select 1, false]] call comspec_atak_native_fnc_droneAction }];
    };
    // Barre de commandes sous l'image : groupes titrés (grand format), bouton actif en vert, marquage en rouge.
    private _y = _top + _areaH + _pad * 0.3;
    {
        private _row = _x;
        private _big = [_row] call _rowBig;
        private _wsum = 0;
        { { _wsum = _wsum + ([_x select 0] call _weight); } forEach (_x select 1); } forEach _row;
        private _gw = _pad * 0.8;
        private _unit = (_bw - _pad - ((count _row) - 1) * _gw) / (_wsum max 1);
        private _x0 = _ox + _pad / 2;
        {
            _x params ["_glabel", "_btns"];
            private _span = 0;
            { _span = _span + _unit * ([_x select 0] call _weight); } forEach _btns;
            if (_lh > 0 && {!(_glabel in ["", "*"])}) then {
                private _t = ["COMSPEC_RscStructuredText", [_x0, _y, _span, _lh]] call comspec_atak_native_fnc_pageCtrl;
                _t ctrlSetStructuredText parseText ((format ["<t size='0.72' font='COMSPEC_BarlowBold' color='#8a9a93'>%1</t>", _glabel]) call comspec_atak_native_fnc_tr);
                _t ctrlEnable false;
                private _ln = ["COMSPEC_RscText", [_x0, _y + _lh - pixelH * 3, _span - _pad / 3, pixelH * 2]] call comspec_atak_native_fnc_pageCtrl;
                _ln ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.45];
            };
            private _bx = _x0;
            {
                _x params ["_lbl", "_code", ["_state", ""], ["_tip", ""], ["_en", true]];
                private _bwid = _unit * ([_lbl] call _weight);
                private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select (_state isEqualTo "main"), [_bx, _y + ([_lh, 0] select _big), _bwid - _pad / 3, [_btnH, _btnH * 1.45] select _big], _lbl] call comspec_atak_native_fnc_pageCtrl;
                private _f = (_bfs * ([1, 1.25] select _big)) max COMSPEC_ATAK_FontMin;
                _b ctrlSetFontHeight _f;
                // Libellé plus large que le bouton (traduction, petit écran) : police réduite jusqu'à ce qu'il tienne.
                while { (ctrlTextWidth _b) > (_bwid - _pad / 3) * 0.92 && {_f > _bfs * 0.55} } do { _f = _f * 0.92; _b ctrlSetFontHeight _f; };
                switch (_state) do {
                    case "on": { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
                    case "red": { _b ctrlSetBackgroundColor [0.78, 0.2, 0.16, 0.95]; _b ctrlSetTextColor [1, 1, 1, 1]; };
                    case "main": {};
                    default { _b ctrlSetBackgroundColor [0.12, 0.145, 0.13, 1]; _b ctrlSetTextColor ([[0.45, 0.5, 0.47, 1], [0.92, 0.96, 0.93, 1]] select _en); };
                };
                if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
                _b ctrlEnable _en;
                // Clic différé (fn_clickNext) : la plupart de ces actions redessinent la page.
                [_b, _code] call comspec_atak_native_fnc_clickNext;
                _bx = _bx + _bwid;
            } forEach _btns;
            _x0 = _x0 + _span + _gw;
        } forEach _row;
        _y = _y + ([_row] call _rowH) + _pad * 0.3;
    } forEach _barRows;
    _top = _top + _areaH + _barH;
};
if (_view in ["TOTAL", "SHOT"] || {_view isEqualTo "FULL" && {!_leftMenus}}) exitWith { true };
uiNamespace setVariable ["COMSPEC_ATAK_DroneMenusShown", true];
// Onglets à gauche, caméra fermée : la colonne de droite garde la télémétrie et un grand bouton pour rouvrir l'image.
if (_leftMenus && {!_camOn}) then {
    private _ob = ["COMSPEC_RscButtonPrimary", [_ox + _bw * 0.2, _top + (_bh - _top) * 0.4, _bw * 0.6, _fs * 2.2], "OUVRIR LA CAMÉRA"] call comspec_atak_native_fnc_pageCtrl;
    _ob ctrlSetFontHeight _fs;
    [_ob, ["cam"] call _act] call comspec_atak_native_fnc_clickNext;
};

// Onglets : libellé court et rappel de ce qu'on y trouve.
private _seqOn = (["status"] call comspec_atak_native_fnc_droneSeq) select 0;
private _tabs = [
    ["VOL", ["tab", "PILOT"] call _act, _tab isEqualTo "PILOT"],
    ["TÂCHES", ["tab", "TASK"] call _act, _tab isEqualTo "TASK"],
    [format ["CIBLES (%1)", count _locksAll], ["tab", "TARGETS"] call _act, _tab isEqualTo "TARGETS"],
    [format ["PHOTOS (%1)", count _photos], ["tab", "PHOTO"] call _act, _tab isEqualTo "PHOTO"],
    [format ["FLOTTE (%1)", count (["list"] call comspec_atak_native_fnc_droneFleet)], ["tab", "FLEET"] call _act, _tab isEqualTo "FLEET"],
    ["JOURNAL", ["tab", "LOG"] call _act, _tab isEqualTo "LOG"]
];
// Colonne de gauche (étroite) : deux rangées de trois onglets.
if (_leftMenus) then {
    _rows pushBack ["segment", "", _tabs select [0, 3]];
    _rows pushBack ["segment", "", _tabs select [3, 3]];
} else {
    _rows pushBack ["segment", "", _tabs];
};
_rows pushBack ["text", format ["<t size='0.78' color='#8a9a93'>%1%2</t>", (createHashMapFromArray [
    ["PILOT", "Caméra, ordres de vol, altitude et vitesse, veille, dépannage, identité du drone."],
    ["TASK", "Tâches sur un point de la carte, frappe d'un drone armé, recherche d'ennemis."],
    ["TARGETS", "Cibles marquées au viseur, à faire suivre par le drone dans l'ordre choisi ; détections automatiques de la caméra."],
    ["PHOTO", "Clichés pris avec la caméra du drone : fiche (titre, description, SSE, confidentiel) puis envoi vers Athena."],
    ["LOG", "Tout ce qui s'est passé pendant le vol, daté."],
    ["FLEET", "Plusieurs drones au même téléphone : rôle de chacun (suivi, CQB, surveillance, garde rapprochée), ordres à tout l'essaim."]
]) getOrDefault [_tab, ""], ["", "  <t color='#4da3ff'>· Suivi des cibles en cours</t>"] select _seqOn]];
if (!_camOn && {!_leftMenus} && {_tab isNotEqualTo "LOG"}) then {
    _rows pushBack ["buttons", [["OUVRIR LA CAMÉRA", ["cam"] call _act, true]]];
};

// Alliés à moins de 2 km du drone (suivi et escorte) : joueurs du même camp ou d'un camp ami, puis les équipiers IA
// du groupe (seul en test, ou groupe mixte). Le pilote lui-même a son bouton ME SUIVRE.
private _mySide = side group player;
private _cand = allPlayers select {
    alive _x && {_x isKindOf "CAManBase"} && {!(_x isKindOf "HeadlessClient_F")} && {_x isNotEqualTo player}
    && {(side group _x) isEqualTo _mySide || {(side group _x) isNotEqualTo civilian && {[_mySide, side group _x] call BIS_fnc_sideIsFriendly}}}
};
{ _cand pushBackUnique _x; } forEach ((units group player) select { alive _x && {_x isNotEqualTo player} });
private _others = (_cand select { (_x distance _d) < 2000 }) apply { [[1, 0] select (isPlayer _x), _x distance _d, _x] };
_others sort true;
_others = _others apply { [_x select 1, _x select 2] };
// Identifiant d'une unité pour les ordres : uid d'un joueur, netId d'un équipier IA.
private _idOf = { params ["_u"]; [netId _u, getPlayerUID _u] select (isPlayer _u) };
private _playerRow = {
    params ["_u", "_dist", "_label", "_on", "_code"];
    private _pic = [_u] call comspec_atak_native_fnc_avatarPath;
    private _veh = ["", format [" · à bord : %1", getText (configOf vehicle _u >> "displayName")]] select ((vehicle _u) isNotEqualTo _u);
    ["person", [_pic, "\z\comspec_atak_native\addons\main\data\app_profile.paa"] select (_pic isEqualTo ""),
        format ["<t font='COMSPEC_BarlowBold'>%1</t>%5<br/><t size='0.8' color='#8a9a93'>%2 · à %3 m du drone%4</t>", name _u, [_u, true] call comspec_atak_native_fnc_unitGroup, round _dist, _veh, ["  <t size='0.75' color='#8a9a93'>IA</t>", ""] select (isPlayer _u)],
        [[_label, _code, !_on]]]
};
switch (_tab) do {
    case "TASK": {
        private _task = _s getOrDefault ["droneTask", "GOTO"];
        private _cur = _d getVariable ["COMSPEC_DroneTask", []];
        private _curTxt = if ((count _cur) > 0) then {
            _cur params ["_k", "_p", "_r"];
            format ["<t color='#5cc76b'>En cours : %1%2</t>%3",
                (createHashMapFromArray [["GOTO", "aller au point"], ["LOITER", "orbite"], ["OBSERVE", "observation"], ["ESCORT", "escorte"]]) getOrDefault [_k, _k],
                [format [" en %1", [_p, 8] call comspec_atak_native_fnc_gridRef], format [" de %1", [name (_d getVariable ["COMSPEC_DroneFollow", objNull]), getText (configOf (_d getVariable ["COMSPEC_DroneFollow", objNull]) >> "displayName")] select !((_d getVariable ["COMSPEC_DroneFollow", objNull]) isKindOf "CAManBase")]] select (_k isEqualTo "ESCORT"),
                ["", format ["<br/><t size='0.85' color='#8a9a93'>Rayon %1 m · drone à %2 m du point</t>", _r, round (_d distance2D _p)]] select (_k in ["LOITER", "OBSERVE"])]
        } else { "<t color='#8a9a93'>Aucune tâche en cours.</t>" };
        _rows append [
            ["section", "Tâche sur point", "Le drone l'exécute seul ; l'altitude et la vitesse restent celles du pilotage"],
            ["text", _curTxt],
            ["segment", "Tâche", [
                ["ALLER", ["task", "GOTO"] call _act, _task isEqualTo "GOTO"],
                ["ORBITE", ["task", "LOITER"] call _act, _task isEqualTo "LOITER"],
                ["OBSERVER", ["task", "OBSERVE"] call _act, _task isEqualTo "OBSERVE"],
                ["ESCORTE", ["task", "ESCORT"] call _act, _task isEqualTo "ESCORT"]
            ], (createHashMapFromArray [["GOTO", "rejoint le point, puis stationnaire"], ["LOITER", "tourne autour du point"], ["OBSERVE", "orbite, caméra fixée sur le point"], ["ESCORT", "suit un allié, 30 m derrière"]]) get _task]
        ];
        if (_task isEqualTo "ESCORT") then {
            if ((count _others) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun allié à moins de 2 km du drone.</t>"]; };
            {
                _x params ["_dist", "_u"];
                private _on = _m isEqualTo "ESCORT" && {(_d getVariable ["COMSPEC_DroneFollow", objNull]) isEqualTo _u};
                _rows pushBack ([_u, _dist, ["ESCORTER", "ESCORTÉ"] select _on, _on, ["escort", [_u] call _idOf] call _act] call _playerRow);
            } forEach (_others select [0, 6]);
            _rows pushBack ["buttons", [["ANNULER LA TÂCHE", ["taskClear"] call _act, false, _m isEqualTo "ESCORT"]]];
        } else {
            _rows append [
                ["edit", "droneTaskGrid", "Point : grille à 8 ou 10 chiffres", _s getOrDefault ["droneTaskGridVal", ""]],
                ["buttons", [["CHOISIR SUR LA CARTE", ["pickMap"] call _act]]]
            ];
            if (_task in ["LOITER", "OBSERVE"]) then {
                private _tr = _s getOrDefault ["droneTaskRad", 150];
                _rows pushBack ["segment", "Rayon", ([50, 150, 300, 500] apply { [format ["%1 m", _x], ["taskRad", _x] call _act, _tr isEqualTo _x] })];
            };
            if (_cqb && {_task in ["LOITER", "OBSERVE"]}) then { _rows pushBack ["text", "<t size='0.85' color='#f2ab33'>Cette tâche fait quitter le profil CQB (remontée à 30 m).</t>"]; };
            _rows pushBack ["buttons", [["ENVOYER", ["taskSend"] call _act, true], ["EFFACER", ["taskClear"] call _act]]];
        };

        // Frappe : état de l'armement, puis trois étapes pour armer, ou les frappes possibles une fois armé.
        _rows pushBack ["section", "Frappe", "Drone kamikaze : il pique sur la cible et la charge explose à l'impact (drone perdu)"];
        if (!_strikeOk) then {
            _rows pushBack ["text", "<t color='#8a9a93'>Les drones armés sont désactivés sur ce serveur.</t>"];
        } else {
            private _armLabel = _d getVariable ["COMSPEC_DroneArmedLabel", "charge"];
            _rows pushBack ["hero", "\z\comspec_atak_native\addons\main\data\app_drone.paa", [
                "<t size='1.15' font='COMSPEC_BarlowBold' color='#8a9a93'>DRONE NON ARMÉ</t><br/><t size='0.85' color='#8a9a93'>Aucune charge fixée : frappe impossible, la recherche ne fait que signaler.</t>",
                format ["<t size='1.15' font='COMSPEC_BarlowBold' color='#e5483a'>ARMÉ · %1</t><br/><t size='0.85' color='#c9d4cf'>Prêt à frapper. La charge part avec le drone.</t>", toUpper _armLabel]
            ] select _armed];
            if (!_armed) then {
                private _dist = round (player distance _d);
                private _near = _dist <= 5;
                private _pl = ["payloads"] call comspec_atak_native_fnc_droneAction;
                private _pick = _s getOrDefault ["droneArmMag", ""];
                if ((_pl findIf { (_x select 0) isEqualTo _pick }) < 0) then { _pick = (_pl param [0, [""]]) select 0; };
                private _pickLbl = ((_pl select { (_x select 0) isEqualTo _pick }) param [0, ["", ""]]) select 1;
                private _ok = "<t color='#5cc76b' font='COMSPEC_BarlowBold'>✓</t>";
                private _no = "<t color='#e5483a' font='COMSPEC_BarlowBold'>✗</t>";
                _rows pushBack ["text", format ["%1 <t font='COMSPEC_BarlowBold'>1. Approchez-vous du drone à moins de 5 m</t><br/><t size='0.85' color='%2'>Vous êtes à %3 m du drone%4</t>",
                    [_no, _ok] select _near, ["#f2ab33", "#5cc76b"] select _near, _dist, ["", " : posez-le au sol près de vous (ATTERRIR, ou RETOUR puis ATTERRIR)"] select (!_near)]];
                _rows pushBack ["text", format ["%1 <t font='COMSPEC_BarlowBold'>2. Choisissez la charge</t>", [_no, _ok] select ((count _pl) > 0)]];
                if ((count _pl) isEqualTo 0) then {
                    _rows pushBack ["text", "<t size='0.85' color='#f2ab33'>Aucune charge compatible dans votre inventaire.</t><br/><t size='0.8' color='#8a9a93'>Acceptées : toute grenade (à main, fumigène, assourdissante, 40 mm), roquette, missile ou charge explosive, quel que soit le mod.</t>"];
                } else {
                    // Deux charges par ligne : avec les grenades de tous les mods, la liste peut être longue.
                    for "_c" from 0 to ((count _pl) - 1) step 2 do {
                        _rows pushBack ["segment", ["", "La charge choisie est retirée de votre inventaire et fixée sous le drone"] select (_c isEqualTo 0), (_pl select [_c, 2]) apply {
                            _x params ["_mag", "_lbl", "_n"];
                            [format ["%1 x%2", toUpper _lbl, _n], compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['droneArmMag', %1]; ['tab', 'TASK'] call comspec_atak_native_fnc_droneAction;", str _mag], _mag isEqualTo _pick, _lbl]
                        }];
                    };
                };
                private _can = _near && {_pick isNotEqualTo ""};
                _rows pushBack ["text", format ["%1 <t font='COMSPEC_BarlowBold'>3. Armez</t>%2", [_no, _ok] select _can,
                    ["", format ["<br/><t size='0.85' color='#8a9a93'>%1</t>", ["Terminez d'abord l'étape 1.", "Terminez d'abord l'étape 2."] select _near]] select (!_can)]];
                _rows pushBack ["buttons", [[[format ["ARMER : %1", toUpper _pickLbl], "ARMER"] select (_pickLbl isEqualTo ""), ["arm", _pick] call _act, true, _can]]];
            } else {
                _rows append [
                    ["text", "<t font='COMSPEC_BarlowBold'>Laser</t><br/><t size='0.85' color='#8a9a93'>Le drone pique sur le point laser allumé par un membre de votre groupe (désignateur, pointeur IR). Tir et oublie : si le laser s'éteint, il garde la dernière position.</t>"],
                    ["buttons", [[["PIQUER SUR LE LASER", "CONFIRMER : LASER"] select (["LASER"] call _confirming), ["strike", "LASER"] call _act, true]]],
                    ["text", "<t font='COMSPEC_BarlowBold'>Grille</t><br/><t size='0.85' color='#8a9a93'>Le drone pique sur le centre de la case saisie.</t>"],
                    ["edit", "droneGrid", "Grille de la cible (8 ou 10 chiffres)", _s getOrDefault ["droneGridVal", ""]],
                    ["buttons", [[["PIQUER SUR LA GRILLE", "CONFIRMER : GRILLE"] select (["GRID"] call _confirming), { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["droneGridVal", ["droneGrid", ""] call comspec_atak_native_fnc_formValue]; ["strike", "GRID"] call comspec_atak_native_fnc_droneAction; }, true]]],
                    ["text", "<t size='0.85' color='#8a9a93'>Chaque frappe demande un second appui dans les 5 s pour confirmer. Une cible vue par la caméra se frappe aussi depuis l'onglet CIBLES (FRAPPER).</t>"],
                    ["text", format ["<t font='COMSPEC_BarlowBold'>Désarmer</t><br/><t size='0.85' color='#8a9a93'>Retire la charge et la remet dans votre inventaire. Drone à moins de 5 m (vous êtes à %1 m).</t>", round (player distance _d)]],
                    ["buttons", [["DÉSARMER", ["disarm"] call _act, false, (player distance _d) <= 5 && {_m isNotEqualTo "STRIKE"}]]]
                ];
            };
        };
        _rows append [
            ["section", ["Recherche", "Recherche et frappe"] select (_strikeOk && {_armed}), [
                "Le drone balaie le rayon choisi autour de lui en bandes parallèles, puis croisées, signale le premier ennemi vu, puis l'observe.",
                "Le drone balaie le rayon choisi autour de lui en bandes parallèles, puis croisées, et pique sur le premier ennemi vu (drone perdu)."
            ] select (_strikeOk && {_armed})],
            ["text", format ["<t size='0.85' color='%1'>%2</t>", ["#8a9a93", "#e5483a"] select (_strikeOk && {_armed}),
                [["Mode signalement : armez le drone (ci-dessus) pour qu'il frappe.", "Drone non armé : signalement seulement."] select (!_strikeOk), format ["Drone armé (%1) : la recherche frappera.", _d getVariable ["COMSPEC_DroneArmedLabel", "charge"]]] select (_strikeOk && {_armed})]],
            ["segment", "Rayon", ([100, 250, 500] apply { [format ["%1 m", _x], ["radius", _x] call _act, _rad isEqualTo _x] })],
            ["buttons", [[["LANCER LA RECHERCHE", "RECHERCHE EN COURS"] select (_m isEqualTo "HUNT"), ["mode", "hunt"] call _act, _m isNotEqualTo "HUNT"]]]
        ];
    };
    case "TARGETS": {
        private _tracks = missionNamespace getVariable ["COMSPEC_ATAK_DroneTracks", createHashMap];
        private _aim = missionNamespace getVariable ["COMSPEC_ATAK_DroneCamTgt", ""];
        // Cibles marquées au viseur : choisies pour le suivi (AJOUTER), dans l'ordre de la liste, puis LANCER LE SUIVI.
        private _locks = ["list"] call comspec_atak_native_fnc_lockAction;
        private _seq = ["list"] call comspec_atak_native_fnc_droneSeq;
        (["status"] call comspec_atak_native_fnc_droneSeq) params ["_on", "_si", "_sk", "_left"];
        _rows pushBack ["section", format ["Cibles marquées : %1", count _locks], "Caméra : MARQUER UNE CIBLE puis clic sur la cible (ou la touche « Marquer la cible »). Le marqueur suit la cible sur la carte."];
        if ((count _locks) isEqualTo 0) then {
            _rows pushBack ["text", "<t color='#8a9a93'>Aucune cible marquée. Ouvrez la caméra, appuyez sur MARQUER UNE CIBLE, puis cliquez sur une personne ou un véhicule de l'image. Une détection ci-dessous peut aussi être marquée (MARQUER (LOCK)).</t>"];
        } else {
            // Suivi dans l'ordre : le drone passe d'une cible à la suivante (en boucle), caméra pointée sur la cible en cours.
            private _kindS = _s getOrDefault ["droneSeqKind", "FOLLOW"];
            private _dw = _s getOrDefault ["droneSeqDwell", 60];
            private _cur = (_locksAll getOrDefault [_sk, createHashMap]);
            _rows pushBack ["section", "Suivi par le drone", "Choisissez les cibles (AJOUTER) : le drone les suit l'une après l'autre, dans l'ordre de la liste, puis recommence"];
            _rows pushBack ["text", if (_on && {(count _cur) > 0}) then {
                format ["<t color='#4da3ff' font='COMSPEC_BarlowBold'>EN COURS · cible %1 sur %2 : LOCK %3 %4</t><br/><t size='0.8' color='#8a9a93'>%5</t>", _si + 1, count _seq, [_cur get "num", 3] call CBA_fnc_formatNumber, _cur get "name",
                    [format ["Cible suivante dans %1 s, ou plus tôt si celle-ci est neutralisée ou perdue 20 s", _left], "Cible suivante quand vous appuyez sur CIBLE SUIVANTE (ou si celle-ci est neutralisée ou perdue 20 s)"] select (_left < 0)]
            } else {
                format ["<t color='#8a9a93'>%1</t>", ["Aucune cible choisie : AJOUTER sur chaque cible à suivre.", format ["%1 cible(s) choisie(s), suivi arrêté.", count _seq]] select ((count _seq) > 0)]
            }];
            _rows pushBack ["segment", "Le drone doit", [
                ["FILER LA CIBLE", { ["kind", "FOLLOW"] call comspec_atak_native_fnc_droneSeq; }, _kindS isEqualTo "FOLLOW"],
                ["TOURNER AUTOUR", { ["kind", "ORBIT"] call comspec_atak_native_fnc_droneSeq; }, _kindS isEqualTo "ORBIT"]
            ], ["30 m derrière elle, à l'altitude choisie", "orbite au rayon choisi, recalée quand elle bouge"] select (_kindS isEqualTo "ORBIT")];
            if (_kindS isEqualTo "ORBIT") then {
                private _sr = _s getOrDefault ["droneSeqRad", 150];
                _rows pushBack ["segment", "Rayon de l'orbite", ([80, 150, 300] apply { [format ["%1 m", _x], compile format ["['rad', %1] call comspec_atak_native_fnc_droneSeq;", _x], _sr isEqualTo _x] })];
            };
            _rows pushBack ["segment", "Temps sur chaque cible", ([[0, "AU PILOTE"], [30, "30 s"], [60, "1 min"], [120, "2 min"]] apply {
                [_x select 1, compile format ["['dwell', %1] call comspec_atak_native_fnc_droneSeq;", _x select 0], _dw isEqualTo (_x select 0)]
            }), "AU PILOTE : bouton CIBLE SUIVANTE"];
            _rows pushBack ["buttons", [
                [["LANCER LE SUIVI", "CIBLE SUIVANTE"] select _on, [{ ["start"] call comspec_atak_native_fnc_droneSeq; }, { ["next"] call comspec_atak_native_fnc_droneSeq; }] select _on, true, (count _seq) > 0],
                ["ARRÊTER", { ["stop"] call comspec_atak_native_fnc_droneSeq; }, false, _on]
            ]];
        };
        {
            _x params ["_lk", "_e"];
            private _st = _e get "state";
            private _hex = (createHashMapFromArray [["TRACK", "#e5483a"], ["LOST", "#8a9a93"], ["DOWN", "#5cc76b"]]) getOrDefault [_st, "#c9d4cf"];
            private _ago = round (time - (_e get "seen"));
            private _n = _seq find _lk;
            private _now = _on && {_lk isEqualTo _sk};
            _rows pushBack ["text", format ["%8<t font='COMSPEC_BarlowBold'>LOCK %1 · %2</t>  <t size='0.8' color='%3'>%4</t><br/><t size='0.8' color='#8a9a93'>%5 · %6 · marqué à %7</t>",
                [_e get "num", 3] call CBA_fnc_formatNumber, _e get "name", _hex,
                (createHashMapFromArray [["TRACK", "ACCROCHÉ"], ["LOST", "PERDU"], ["DOWN", "NEUTRALISÉ"]]) getOrDefault [_st, _st],
                [_e get "pos", 8] call comspec_atak_native_fnc_gridRef, [format ["vu il y a %1 s", _ago], "en vue"] select (_ago < 2), _e get "clock",
                ["", format ["<t font='COMSPEC_BarlowBold' color='%1'>N° %2%3</t>  ", ["#c9d4cf", "#4da3ff"] select _now, _n + 1, ["", " · EN COURS"] select _now]] select (_n >= 0)]];
            // Suivi : ajouter / retirer, et place dans l'ordre.
            private _lb = [[["AJOUTER AU SUIVI", "RETIRER DU SUIVI"] select (_n >= 0), compile format ["['toggle', %1] call comspec_atak_native_fnc_droneSeq;", str _lk], _n < 0, _st isNotEqualTo "DOWN" || {_n >= 0}]];
            if (_n >= 0) then {
                _lb pushBack ["PLUS TÔT", compile format ["['move', [%1, -1]] call comspec_atak_native_fnc_droneSeq;", str _lk], false, _n > 0];
                _lb pushBack ["PLUS TARD", compile format ["['move', [%1, 1]] call comspec_atak_native_fnc_droneSeq;", str _lk], false, _n < ((count _seq) - 1)];
            };
            _rows pushBack ["buttons", _lb];
            private _lb2 = [[["CAMÉRA SUR LA CIBLE", "CAMÉRA SUR LA CIBLE (EN COURS)"] select (_aim isEqualTo format ["LOCK:%1", _lk]), ["camAim", format ["LOCK:%1", _lk]] call _act, false, _st isNotEqualTo "DOWN", "Pointe la caméra du drone sur cette cible et la garde dessus"]];
            if (_strikeOk && {_armed} && {_st isEqualTo "TRACK"} && {(_e get "tag") isNotEqualTo "AMI"}) then {
                private _tk = hashValue (_e get "obj");
                if (_tk in _tracks) then { _lb2 pushBack [["FRAPPER", "CONFIRMER LA FRAPPE"] select ([format ["TRK:%1", _tk]] call _confirming), ["strike", format ["TRK:%1", _tk]] call _act, true, true, "Le drone armé pique sur la cible (second appui pour confirmer)"]; };
            };
            _lb2 pushBack ["OUBLIER LA CIBLE", compile format ["['release', %1] call comspec_atak_native_fnc_lockAction; ['DRONE'] call comspec_atak_native_fnc_pageRender;", str _lk], false, true, "Retire le marquage (LOCK) de cette cible, sur le téléphone et sur la carte"];
            _rows pushBack ["buttons", _lb2];
            _rows pushBack ["buttons", [
                ["EN MARQUEUR", compile format ["['toMarker', %1] call comspec_atak_native_fnc_lockAction;", str _lk], false, true, "Pose un marqueur permanent à sa position (éditeur de marqueur prérempli)"],
                ["EN FICHE SSE", compile format ["['toSse', %1] call comspec_atak_native_fnc_lockAction;", str _lk], false, true, "Ouvre une fiche SSE d'observation (FRO) préremplie avec la cible"]
            ]];
        } forEach (_locks select [0, 12]);

        // Détections automatiques : ce que la caméra relève seule en vol (ex-onglet PISTES).
        _rows append [
            ["section", format ["Détections automatiques : %1", count _tracks], ["Désactivé sur ce serveur (réglage admin « Repérage automatique par drone ») : repérez et marquez vous-même à la caméra.", "Ce que la caméra du drone repère seule en vol : personnel jusqu'à 450 m, véhicules jusqu'à 700 m. MARQUER (LOCK) en fait une cible marquée."] select (missionNamespace getVariable ["comspec_atak_native_drone_autospot", false])],
            ["buttons", [
                ["CAMÉRA DEVANT", ["camAim", ""] call _act, false, _camOn && {_aim isNotEqualTo ""}, "Remet la caméra du drone droit devant"],
                ["EFFACER LES DÉTECTIONS", ["tracksClear"] call _act, false, (count _tracks) > 0, "Vide la liste des détections automatiques"]
            ]]
        ];
        if ((count _tracks) isEqualTo 0) then {
            _rows pushBack ["text", format ["<t color='#8a9a93'>%1</t>", ["Rien de détecté pour l'instant. Le drone relève ce qu'il voit dès qu'il vole avec la liaison.", "Rien de détecté : le drone doit voler (plus de 3 m) et garder la liaison."] select (_ground || {!(_link select 2)})]];
        };
        // Ennemis d'abord, puis les plus récentes ; les détections détruites en bas.
        private _list = (keys _tracks) apply { private _v = _tracks get _x; [[1, 0] select ((_v select 8) isEqualTo ""), ["ENNEMI", "INCONNU", "AMI"] find (_v select 5), -(_v select 3), _x, _v] };
        _list sort true;
        {
            private _key = _x select 3;
            (_x select 4) params ["_o", "_name", "_pos", "_seen", "_clock", "_tag", "_dist", "_num", "_state"];
            private _ago = round (time - _seen);
            private _hex = (createHashMapFromArray [["ENNEMI", "#e5483a"], ["INCONNU", "#f2ab33"], ["AMI", "#4da3ff"]]) getOrDefault [_tag, "#c9d4cf"];
            private _icon = switch (true) do {
                case (_o isKindOf "CAManBase"): { "\A3\ui_f\data\map\vehicleicons\iconMan_ca.paa" };
                case (_o isKindOf "Air"): { "\A3\ui_f\data\map\vehicleicons\iconHelicopter_ca.paa" };
                case (_o isKindOf "Ship"): { "\A3\ui_f\data\map\vehicleicons\iconShip_ca.paa" };
                default { "\A3\ui_f\data\map\vehicleicons\iconCar_ca.paa" };
            };
            _rows pushBack ["person", _icon,
                format ["<t font='COMSPEC_BarlowBold'>%1 · %2</t>  <t size='0.8' color='%3'>%4</t>%5<br/><t size='0.8' color='#8a9a93'>%6 · à %7 m du drone · %8</t>",
                    [_num, 2] call CBA_fnc_formatNumber, _name, _hex, _tag,
                    ["", "  <t size='0.8' color='#5cc76b'>DÉTRUIT</t>"] select (_state isNotEqualTo ""),
                    [_pos, 8] call comspec_atak_native_fnc_gridRef, round (_d distance _pos),
                    [format ["vue à %1 (il y a %2)", _clock, [format ["%1 s", _ago], format ["%1 min", floor (_ago / 60)]] select (_ago >= 120)], "en vue"] select (_ago < 3)],
                [], ([[1, 1, 1, 1], [0.9, 0.28, 0.23, 1], [0.95, 0.67, 0.2, 1], [0.3, 0.64, 1, 1]] select ((["ENNEMI", "INCONNU", "AMI"] find _tag) + 1))];
            private _b = [[["CAMÉRA SUR LA CIBLE", "CAMÉRA SUR LA CIBLE (EN COURS)"] select (_aim isEqualTo _key), ["camAim", _key] call _act, false, _aim isNotEqualTo _key, "Pointe la caméra du drone sur cette détection"]];
            if (alive _o && {_state isEqualTo ""} && {!(_key in _locksAll)}) then {
                _b pushBack ["MARQUER (LOCK)", compile format ["['mark', 'DRONE', ((missionNamespace getVariable ['COMSPEC_ATAK_DroneTracks', createHashMap]) getOrDefault [%1, [objNull]]) select 0] call comspec_atak_native_fnc_lockAction; ['DRONE'] call comspec_atak_native_fnc_pageRender;", str _key], true, true, "En fait une cible marquée, suivie sur la carte"];
            };
            if (_strikeOk && {_armed} && {_state isEqualTo ""} && {_tag isNotEqualTo "AMI"}) then {
                _b pushBack [["FRAPPER", "CONFIRMER LA FRAPPE"] select ([format ["TRK:%1", _key]] call _confirming), ["strike", format ["TRK:%1", _key]] call _act, true, true, "Le drone armé pique sur la détection (second appui pour confirmer)"];
            };
            _b pushBack ["MARQUEUR SUR LA CARTE", ["trackMark", _key] call _act, false, true, "Pose un marqueur sur la carte à la dernière position vue"];
            _rows pushBack ["buttons", _b];
        } forEach (_list select [0, 15]);
    };
    case "PHOTO": {
        _rows pushBack ["section", format ["Clichés du drone : %1", count _photos], "Gardés sur ce poste (dossier Screenshots), aussi visibles dans l'app Galerie"];
        _rows pushBack ["buttons", [["OUVRIR LE VISEUR PHOTO", { ["view"] call comspec_atak_native_fnc_dronePhoto; }, true, true, "Cadrer, zoomer, marquer et surligner avant de prendre la photo"]]];
        if ((count _photos) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun cliché. Bouton PHOTO sous l'image, ou ci-dessus.</t>"]; };
        {
            _x params ["_i", "_e"];
            _e params ["_file", "_title", "", "_sse", "_cf", "_grid", "_clock", "_drone", "_state"];
            private _stTxt = (createHashMapFromArray [["DRAFT", ["FICHE À COMPLÉTER", "#f2ab33"]], ["LOCAL", ["NON ENVOYÉ", "#8a9a93"]], ["SENDING", ["ENVOI EN COURS", "#4da3ff"]], ["SENT", ["ENVOYÉ", "#5cc76b"]], ["FAILED", ["ÉCHEC D'ENVOI", "#e5483a"]]]) getOrDefault [_state, [_state, "#8a9a93"]];
            _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold'>%1</t>%2%3  <t size='0.8' color='%5'>%4</t><br/><t size='0.8' color='#8a9a93'>%6 · %7 · %8</t>",
                [_title, "Sans titre"] select (_title isEqualTo ""), ["", "  <t size='0.75' color='#e5483a'>CONFIDENTIEL</t>"] select _cf, ["", "  <t size='0.75' color='#f2ab33'>SSE</t>"] select _sse,
                _stTxt select 0, _stTxt select 1, _grid, _clock, _drone]];
            _rows pushBack ["buttons", [
                [["ENVOYER", "RENVOYER"] select (_state in ["SENT", "FAILED"]), compile format ["['send', %1] call comspec_atak_native_fnc_dronePhoto;", _i], _state in ["DRAFT", "LOCAL"], _state isNotEqualTo "SENDING", "Envoie le cliché vers Athena avec sa fiche"],
                ["OUVRIR LA FICHE", compile format ["['edit', %1] call comspec_atak_native_fnc_dronePhoto;", _i], false, true, "Titre, description, SSE et confidentiel du cliché, puis envoi"],
                [["SUPPRIMER", "CONFIRMER LA SUPPRESSION"] select (((_s getOrDefault ["dronePhDel", [-1, -10]]) select 0) isEqualTo _i && {diag_tickTime - ((_s getOrDefault ["dronePhDel", [-1, -10]]) select 1) < 4}), compile format ["['forget', %1] call comspec_atak_native_fnc_dronePhoto;", _i], false, true, "Supprime le cliché du téléphone (second appui pour confirmer)"]
            ]];
        } forEach ((["list"] call comspec_atak_native_fnc_dronePhoto) select [0, 12]);
    };
    case "FLEET": {
        private _fleet = ["list"] call comspec_atak_native_fnc_droneFleet;
        private _fc = { params ["_a", "_x"]; compile format ["%1 call comspec_atak_native_fnc_droneFleet;", str _a] };
        private _roles = createHashMapFromArray [["", "aucun rôle"], ["ME", "me suit"], ["CQB", "me suit en CQB"], ["POINT", "surveille le point"], ["PLAYER", "surveille le joueur"], ["GUARD", "garde rapprochée"], ["HUNT", "recherche"]];
        _rows pushBack ["section", format ["Flotte : %1 drone%2", count _fleet, ["", "s"] select ((count _fleet) > 1)], "Jusqu'à 4 drones par téléphone. Un seul est piloté (caméra, onglets VOL, TÂCHES, CIBLES) ; les autres tiennent seuls leur rôle ou leur dernier ordre, et rentrent seuls si la liaison ou la batterie lâche."];
        {
            private _x0 = _x;
            private _nid = netId _x0;
            private _lk = ["link", _x0] call comspec_atak_native_fnc_droneFleet;
            private _r = _x0 getVariable ["COMSPEC_DroneRole", ""];
            private _isA = _x0 isEqualTo _d;
            private _rc = { params ["_role"]; compile format ["['role', [objectFromNetId %1, %2]] call comspec_atak_native_fnc_droneFleet;", str _nid, str _role] };
            _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold'>%1</t>%2  <t size='0.8' color='%3'>%4</t><br/><t size='0.8' color='#8a9a93'>%5 · %6 m sol · batterie %7 %% · à %8 m · liaison %9/4</t>",
                [_x0, [], "NAME"] call comspec_atak_native_fnc_droneOsd, ["", "  <t size='0.75' color='#4da3ff'>PILOTÉ</t>"] select _isA,
                ["#c9d4cf", "#5cc76b"] select (_r isNotEqualTo ""), toUpper (_roles getOrDefault [_r, _r]),
                toLower (_x0 getVariable ["COMSPEC_DroneMode", "HOVER"]), round ((getPosATL _x0) select 2), round (100 * fuel _x0), _lk select 1, _lk select 0]];
            _rows pushBack ["buttons", [
                ["ME SUIVRE", ["ME"] call _rc, _r isEqualTo "ME", true, "Suit le pilote, décalé sur le côté selon son rang dans la flotte"],
                ["CQB", ["CQB"] call _rc, _r isEqualTo "CQB", true, "Me suit en CQB : vol bas à 3 m, 10 km/h, arrêt devant obstacle"],
                ["GARDE", ["GUARD"] call _rc, _r isEqualTo "GUARD", missionNamespace getVariable ["comspec_atak_native_drone_autospot", false], "Garde rapprochée : suit le pilote, repère seul les ennemis proches, prévient et va observer le plus proche"]
            ]];
            _rows pushBack ["buttons", [
                ["SURVEILLER LE POINT", ["POINT"] call _rc, _r isEqualTo "POINT", true, "Orbite caméra sur le point choisi dans l'onglet TÂCHES (grille ou carte)"],
                ["SURVEILLER LE JOUEUR", ["PLAYER"] call _rc, _r isEqualTo "PLAYER", true, "Escorte le joueur choisi dans l'onglet VOL (30 m derrière)"],
                ["RECHERCHE", ["HUNT"] call _rc, _r isEqualTo "HUNT", true, "Cherche des ennemis autour du pilote (chaque drone son secteur)"]
            ]];
            private _b = [];
            if (!_isA) then { _b pushBack ["PILOTER", compile format ["['switch', objectFromNetId %1] call comspec_atak_native_fnc_droneFleet;", str _nid], true, true, "Prend ce drone en main (caméra, onglets) ; le drone piloté passe en arrière-plan et continue seul"]; };
            _b pushBack ["STATIONNAIRE", compile format ["(objectFromNetId %1) setVariable ['COMSPEC_DroneRole', '', true]; ['send', [objectFromNetId %1, 'hover', []]] call comspec_atak_native_fnc_droneFleet;", str _nid], false, true];
            _b pushBack ["RTB", compile format ["private _x = objectFromNetId %1; _x setVariable ['COMSPEC_DroneRole', '', true]; ['send', [_x, 'rth', [[_x getVariable ['COMSPEC_DroneHomePos', getPosASL player], missionNamespace getVariable ['COMSPEC_ATAK_DroneHome', getPosASL player]] select (_x isEqualTo (missionNamespace getVariable ['COMSPEC_ATAK_Drone', objNull]))]]] call comspec_atak_native_fnc_droneFleet;", str _nid], false, true, "Retour au point de décollage de ce drone"];
            _b pushBack ["LIBÉRER", compile format ["['drop', objectFromNetId %1] call comspec_atak_native_fnc_droneFleet;", str _nid], false, !_isA || {(count _fleet) > 1}, "Désappaire ce drone du téléphone"];
            _rows pushBack ["buttons", _b];
        } forEach _fleet;
        if ((count _fleet) > 1) then {
            _rows append [
                ["section", "Essaim", "Un appui, tous les drones de la flotte : chacun garde sa place (décalé sur le côté, orbites et secteurs de recherche différents)."],
                ["buttons", [
                    ["TOUS ME SUIVRE", [["swarm", "ME"]] call _fc, false, true],
                    ["TOUS EN CQB", [["swarm", "CQB"]] call _fc, false, true],
                    ["TOUS EN GARDE", [["swarm", "GUARD"]] call _fc, false, missionNamespace getVariable ["comspec_atak_native_drone_autospot", false]]
                ]],
                ["buttons", [
                    ["TOUS SUR LE POINT", [["swarm", "POINT"]] call _fc, false, true],
                    ["TOUS EN RECHERCHE", [["swarm", "HUNT"]] call _fc, false, true]
                ]],
                ["buttons", [
                    ["TOUS STATIONNAIRE", [["swarm", "HOVER"]] call _fc, false, true],
                    ["TOUS RTB", [["swarm", "RTH"]] call _fc, false, true],
                    ["TOUS ATTERRIR", [["swarm", "LAND"]] call _fc, false, true]
                ]]
            ];
        };
        // Télécommande sur le vrai téléphone du joueur : page Athena /telecommande-drone (QR code fixe de l'adresse du site
        // de production, tools/gen_remote_qr.py ; l'adresse écrite suit celle réglée pour Athena).
        _rows append [
            ["section", "Télécommande sur votre téléphone", "Scannez avec votre vrai téléphone, connecté à votre compte Athena : boutons du drone, rôles et essaim, ordres transmis par Athena en quelques secondes."],
            ["image", "\z\comspec_atak_native\addons\main\data\drone_remote_qr_ca.paa"],
            ["text", "<t size='0.85' color='#8a9a93'>https://athena-comspec.eu/public/telecommande-drone</t>"]
        ];
        // Ajout d'un drone : posé à moins de 15 m, libre.
        private _near = (player nearEntities [["Air", "Mavic_drone_base_F"], 15]) select { alive _x && {unitIsUAV _x} && {!(_x in _fleet)} && {(_x getVariable ["COMSPEC_DroneOwner", ""]) in ["", getPlayerUID player]} };
        _rows pushBack ["section", "Ajouter un drone", ["Posez un autre drone à vos pieds : le drone piloté continue seul, le nouveau est pris en main.", "Flotte complète (4 drones)."] select ((count _fleet) >= 4)];
        if ((count _near) isEqualTo 0 && {(count _fleet) < 4}) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun drone libre à moins de 15 m.</t>"]; };
        if ((count _fleet) < 4) then {
            {
                _rows pushBack ["person", getText (configOf _x >> "picture"),
                    format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='#8a9a93'>À %2 m · batterie %3 %%</t>", [_x, [], "NAME"] call comspec_atak_native_fnc_droneOsd, round (player distance _x), round (100 * fuel _x)],
                    [["APPAIRER", compile format ["['pair', objectFromNetId %1] call comspec_atak_native_fnc_droneAction;", str netId _x], true]]];
            } forEach _near;
        };
    };
    case "LOG": {
        private _lg = missionNamespace getVariable ["COMSPEC_ATAK_DroneLog", []];
        _rows pushBack ["section", "Journal", format ["%1 messages, le plus récent en haut. À droite les ordres du pilote, à gauche ce que le drone renvoie.", count _lg]];
        // Fil de discussion : ordres envoyés (« Ordre… », « Ordre refusé… ») à droite, retours du drone à gauche.
        private _lines = [];
        for "_i" from ((count _lg) - 1) to 0 step -1 do {
            (_lg select _i) params ["_t", "_txt", ["_c", ""]];
            if ((_txt find "Ordre") isEqualTo 0) then {
                _lines pushBack format ["<t align='right' size='0.72' color='#8a9a93'>PILOTE · %1</t><br/><t align='right' size='0.85' color='%3'>%2</t>", _t, _txt, ["#4da3ff", _c] select (_c isNotEqualTo "")];
            } else {
                _lines pushBack format ["<t align='left' size='0.72' color='#8a9a93'>DRONE · %1</t><br/><t align='left' size='0.85' color='%3'>%2</t>", _t, _txt, ["#c9d4cf", _c] select (_c isNotEqualTo "")];
            };
        };
        _rows pushBack ["text", ([["<t color='#8a9a93'>Rien à signaler pour l'instant.</t>"], _lines] select ((count _lines) > 0)) joinString "<br/>"];
    };
    default {
        if (_ground) then {
            _rows pushBack ["buttons", [["DÉMARRER ET DÉCOLLER", ["takeoff"] call _act, true]]];
            _rows pushBack ["text", format ["<t size='0.85' color='#8a9a93'>Montée à %1 m puis stationnaire. Tout ordre de vol donné au sol fait aussi décoller le drone.</t>", _alt]];
        };
        // Caméra : ouverture, cliché, qualité du flux, petit format, sens du glisser, source de l'image.
        private _q = ((profileNamespace getVariable ["COMSPEC_ATAK_DroneQuality", 0]) max 0) min 1;
        private _dm = profileNamespace getVariable ["COMSPEC_ATAK_DroneDragMode", 0];
        _rows append [
            ["section", "Caméra", "Image de la caméra, clichés et marquage des cibles"],
            ["buttons", [
                [["OUVRIR LA CAMÉRA", "FERMER LA CAMÉRA"] select _camOn, ["cam"] call _act, !_camOn],
                ["VISEUR PHOTO", { ["view"] call comspec_atak_native_fnc_dronePhoto; }, false, true, "Cadrer, zoomer, marquer et surligner avant de prendre la photo"]
            ]],
            ["segment", "Qualité du flux", [["BASSE", ["quality", 0] call _act, _q isEqualTo 0], ["HAUTE", ["quality", 1] call _act, _q isEqualTo 1]],
                "basse (512 px) : plus fluide · haute (1024 px) : plus net, plus lourd"],
            ["switch", "Vue totale en petit format", profileNamespace getVariable ["COMSPEC_ATAK_DroneMiniFull", true],
                { profileNamespace setVariable ["COMSPEC_ATAK_DroneMiniFull", !(profileNamespace getVariable ["COMSPEC_ATAK_DroneMiniFull", true])]; ["DRONE"] call comspec_atak_native_fnc_pageRender; },
                "Téléphone en petit (Ctrl+U) : l'image du drone occupe tout l'écran, incrustations réduites"],
            ["segment", "Glisser sur l'image", [
                ["SAISIR L'IMAGE", { profileNamespace setVariable ["COMSPEC_ATAK_DroneDragMode", 0]; ["DRONE"] call comspec_atak_native_fnc_pageRender; }, _dm isEqualTo 0],
                ["DIRIGER LA CAMÉRA", { profileNamespace setVariable ["COMSPEC_ATAK_DroneDragMode", 1]; ["DRONE"] call comspec_atak_native_fnc_pageRender; }, _dm isEqualTo 1]
            ], ["l'image suit la souris, comme sur un écran tactile", "la caméra tourne du côté où l'on glisse"] select (_dm isEqualTo 1)]
        ];
        // Vol : ordres immédiats.
        _rows pushBack ["section", "Vol", "Ordres immédiats ; l'altitude et la vitesse servent aussi aux tâches"];
        // Veille : posé ou stationnaire, peu de consommation, capteur coupé ; reprise d'un appui.
        if (_m isEqualTo "STANDBY") then {
            _rows pushBack ["text", format ["<t color='#8fb3c9'>En veille (%1) : consommation réduite, capteur et caméra coupés.</t>", ["stationnaire", "posé"] select ((_d getVariable ["COMSPEC_DroneStandby", "LAND"]) isEqualTo "LAND")]];
            _rows pushBack ["buttons", [["REPRENDRE", ["resume"] call _act, true]]];
        };
        if (_m isEqualTo "MANUAL") then { _rows pushBack ["text", "<t color='#f2ab33'>Pilotage manuel : vous seul pouvez connecter un terminal UAV à ce drone. Le moindre ordre du téléphone reprend la main et déconnecte le terminal.</t>"]; };
        if (_m isNotEqualTo "MANUAL") then { _rows pushBack ["text", "<t size='0.8' color='#8a9a93'>Votre terminal UAV (ou une tablette) reste utilisable : le connecter à ce drone passe en pilotage manuel.</t>"]; };
        _rows append [
            ["segment", "", [
                ["STATIONNAIRE", ["mode", "hover"] call _act, _m isEqualTo "HOVER" && {!_ground}],
                ["ME SUIVRE", ["mode", "me"] call _act, _m isEqualTo "FOLLOW" && {(_d getVariable ["COMSPEC_DroneFollow", objNull]) isEqualTo player}],
                ["REVENIR À MOI", ["mode", "home"] call _act, _m in ["HOME", "RTH"]],
                ["ATTERRIR", ["mode", "land"] call _act, _m isEqualTo "LAND"]
            ]],
            ["segment", "Altitude", (([[15, 30, 60, 100, 150], [2, 3, 5, 8]] select _cqb) apply { [format ["%1 m", _x], ["alt", _x] call _act, _alt isEqualTo _x] })],
            ["segment", "Vitesse", (([[20, 40, 60, 90], [5, 10, 15]] select _cqb) apply { [format ["%1 km/h", _x], ["speed", _x] call _act, _spd isEqualTo _x] })],
            ["switch", "Profil CQB", _cqb, ["cqb"] call _act, "Vol bas en intérieur : 2 à 8 m, 5 à 15 km/h, suivi à 5 m, arrêt devant un obstacle"]
        ];
        // Manette : pilotage direct depuis le téléphone, sans terminal UAV (retour de TTRD, 19/10 : « on doit vraiment
        // pouvoir le piloter totalement »). Boutons = crans d'un régulateur, touches tenues = manche (fn_droneAction "stickKey").
        _rows pushBack ["section", "Manette", "Pilotage direct du drone depuis le téléphone, comme au terminal UAV"];
        if (_m isEqualTo "STICK") then {
            (_d getVariable ["COMSPEC_DroneStick", [0, 0, 0, 0]]) params [["_sf", 0], ["_sr", 0], ["_su", 0], ["_sy", 0]];
            _rows pushBack ["text", format ["<t size='0.85' color='#5cc76b'>Manette prise · avant %1 %% · côté %2 %% · montée %3 %% · pivot %4 %% · vitesse max %5 km/h</t>",
                round (100 * _sf), round (100 * _sr), round (100 * _su), round (100 * _sy), _spd]];
            _rows append [
                ["buttons", [["PIVOTER À GAUCHE", ["stickStep", [3, -0.5]] call _act, false], ["AVANCER", ["stickStep", [0, 0.25]] call _act, _sf > 0], ["PIVOTER À DROITE", ["stickStep", [3, 0.5]] call _act, false]]],
                ["buttons", [["À GAUCHE", ["stickStep", [1, -0.25]] call _act, _sr < 0], ["STOP", ["stickStop"] call _act, "red"], ["À DROITE", ["stickStep", [1, 0.25]] call _act, _sr > 0]]],
                ["buttons", [["MONTER", ["stickStep", [2, 0.5]] call _act, _su > 0], ["RECULER", ["stickStep", [0, -0.25]] call _act, _sf < 0], ["DESCENDRE", ["stickStep", [2, -0.5]] call _act, _su < 0]]],
                ["text", "<t size='0.8' color='#8a9a93'>Clavier (téléphone en main, page Drone, ou en PLEIN ÉCRAN) : ZQSD (AZERTY) ou WASD (QWERTY) pour avancer, reculer et aller de côté, A / E (AZERTY) ou Q / E (QWERTY) pour pivoter, Espace pour monter, C pour descendre, tant que la touche est tenue. Les boutons ajoutent un cran, STOP remet tout à zéro. Altitude tenue seule, arrêt devant un obstacle. Tout autre ordre rend la main au pilote automatique.</t>"]
            ];
        } else {
            _rows pushBack ["buttons", [["PRENDRE LA MANETTE", ["stick"] call _act, true, true, "Pilotage direct : avancer, reculer, de côté, pivoter, monter, descendre"]]];
        };
        _rows append [
            ["section", "Dépannage", "Le drone ne bouge pas, ou vous voulez le piloter vous-même"],
            ["buttons", [["FORCER L'EXÉCUTION", ["force"] call _act, false], ["PILOTAGE MANUEL", ["manual"] call _act, _m isEqualTo "MANUAL"]]],
            ["text", format ["<t size='0.8' color='#8a9a93'>FORCER : renvoie « %1 », recrée l'IA de vol si besoin, efface ses points de passage, réapplique altitude et vitesse (fait seul une fois si l'ordre n'avance pas pendant 10 s). MANUEL : terminal UAV.</t>",
                ((createHashMapFromArray [["takeoff", "décoller"], ["hover", "stationnaire"], ["follow", "suivre"], ["home", "retour au pilote"], ["land", "atterrir"], ["hunt", "recherche"], ["goto", "aller au point"], ["loiter", "orbite"], ["observe", "observer"], ["escort", "escorte"], ["route", "route"], ["rth", "retour au point de décollage"], ["strike", "frappe"], ["manual", "pilotage manuel"], ["standby", "veille"], ["stick", "manette"]]) getOrDefault [(missionNamespace getVariable ["COMSPEC_ATAK_DroneOrder", []]) param [0, "hover"], "stationnaire"])]],
            ["section", "Suivre un joueur", ["Le drone se place 15 m derrière lui. FILMER : la caméra reste braquée sur lui", "Profil CQB : 5 m derrière lui, à hauteur d'homme. FILMER : la caméra reste braquée sur lui"] select _cqb]
        ];
        // Caméra braquée sur un joueur (clé UNIT:<netId>, boucle de la nacelle de fn_droneAction), le drone garde son
        // ordre de vol ; second appui : arrêt (retour de TTRD, 19/10 : « que la caméra suive un joueur »).
        private _aimU = missionNamespace getVariable ["COMSPEC_ATAK_DroneCamTgt", ""];
        private _filmBtn = {
            params ["_u", ["_lbls", ["FILMER", "FILMÉ"]]];
            private _k = "UNIT:" + netId _u;
            private _onF = _aimU isEqualTo _k;
            [_lbls select _onF, ["camAim", ["", _k] select !_onF] call _act, [false, "red"] select _onF, true,
                ["La caméra du drone reste braquée sur lui, où que vole le drone", "Appuyez pour arrêter : la caméra revient devant le drone"] select _onF]
        };
        _rows pushBack ["buttons", [[player, ["ME FILMER", "ARRÊTER DE ME FILMER"]] call _filmBtn]];
        if ((count _others) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun allié à moins de 2 km du drone.</t>"]; };
        {
            _x params ["_dist", "_u"];
            private _on = _m isEqualTo "FOLLOW" && {(_d getVariable ["COMSPEC_DroneFollow", objNull]) isEqualTo _u};
            private _row = [_u, _dist, ["SUIVRE", "SUIVI"] select _on, _on,
                compile format ["['player', %1] call comspec_atak_native_fnc_droneAction; ['mode', 'player'] call comspec_atak_native_fnc_droneAction;", str ([_u] call _idOf)]] call _playerRow;
            (_row select 3) pushBack ([_u] call _filmBtn);
            _rows pushBack _row;
        } forEach (_others select [0, 6]);
        // Veille (hors veille) : le pilote choisit se poser ou rester en l'air.
        if (_m isNotEqualTo "STANDBY") then {
            private _sk = _s getOrDefault ["droneStandbyKind", "LAND"];
            _rows append [
                ["section", "Veille", "Consommation réduite, capteur coupé ; reprise de l'ordre en cours d'un appui"],
                ["segment", "", [["SE POSER", ["standbyKind", "LAND"] call _act, _sk isEqualTo "LAND"], ["RESTER EN L'AIR", ["standbyKind", "HOVER"] call _act, _sk isEqualTo "HOVER"]]],
                ["buttons", [["METTRE EN VEILLE", ["standby"] call _act, false, _m isNotEqualTo "STRIKE"]]]
            ];
        };
        // Identité : nom et icône, vus sur la carte, dans l'en-tête, la vue caméra et par le poste de commandement.
        private _icon = [_d, [], "ICONID"] call comspec_atak_native_fnc_droneOsd;
        _rows append [
            ["section", "Identité", format ["Modèle : %1", getText (configOf _d >> "displayName")]],
            ["edit", "droneName", "Nom du drone (24 caractères, vide = nom du modèle)", _d getVariable ["COMSPEC_DroneName", ""]],
            ["buttons", [["RENOMMER", { ["rename", ["droneName", ""] call comspec_atak_native_fnc_formValue] call comspec_atak_native_fnc_droneAction; }]]],
            ["segment", "Icône", ([["QUADRI", "quad"], ["AILE", "fixed"], ["HEXA", "hexa"], ["NANO", "nano"], ["FPV", "fpv"]] apply { [_x select 0, ["icon", _x select 1] call _act, _icon isEqualTo (_x select 1)] })]
        ];
        // Transfert de l'appairage : alliés à moins de 50 m de vous, téléphone sur eux.
        private _mates = (_cand select { isPlayer _x && {(_x distance player) < 50} && {[_x] call comspec_atak_native_fnc_hasDevice} }) apply { [_x distance player, _x] };
        _mates sort true;
        _rows pushBack ["section", "Transférer l'appairage", "À un allié à moins de 50 m portant un téléphone ; second appui pour confirmer"];
        if ((count _mates) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun allié équipé d'un téléphone à moins de 50 m.</t>"]; };
        {
            _x params ["_dist", "_u"];
            private _ck = format ["XFER:%1", getPlayerUID _u];
            private _pic = [_u] call comspec_atak_native_fnc_avatarPath;
            _rows pushBack ["person", [_pic, "\z\comspec_atak_native\addons\main\data\app_profile.paa"] select (_pic isEqualTo ""),
                format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='#8a9a93'>%2 · à %3 m de vous</t>", name _u, [_u, true] call comspec_atak_native_fnc_unitGroup, round _dist],
                [[["TRANSFÉRER", "CONFIRMER"] select ([_ck] call _confirming), ["transfer", getPlayerUID _u] call _act, [_ck] call _confirming]]];
        } forEach (_mates select [0, 6]);
        _rows pushBack ["buttons", [["DÉSAPPAIRER", ["unpair"] call _act]]];
    };
};
[_rows, [[_ox, _top, _bw, (_bh - _top) max (_fs * 4)], _menuRect] select _leftMenus] call comspec_atak_native_fnc_formRender;
true
