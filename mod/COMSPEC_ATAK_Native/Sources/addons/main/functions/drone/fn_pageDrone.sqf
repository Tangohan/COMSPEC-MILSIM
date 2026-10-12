/*
    App Drone : pilotage d'un drone posé par le joueur (quadricoptère ou drone de sac à dos).
    Appairage au pied du pilote, puis cinq onglets (clé droneTab de l'état) :
      VOL      (PILOT)   : caméra (qualité, vue totale, sens du glisser, source du flux), décollage, stationnaire, suivi,
                           retour, atterrissage, altitude, vitesse, profil CQB, veille, dépannage, identité, transfert ;
      TÂCHES   (TASK)    : tâche sur point (grille ou carte) : aller, orbite, observer, escorte ; frappe ; recherche ;
      CIBLES   (TARGETS) : cibles marquées au viseur (LOCK) à suivre l'une après l'autre dans l'ordre choisi (fn_droneSeq),
                           puis les détections automatiques de la caméra (ex-onglet PISTES) : voir, accrocher, carte, frapper ;
      PHOTOS   (PHOTO)   : fiche du cliché en cours (titre, description, SSE, confidentiel) et clichés pris (fn_dronePhoto) ;
      JOURNAL  (LOG)     : chaque événement daté.
    Vue caméra (camBind de fn_droneAction : la caméra rend avant que l'image soit posée) :
      PLEIN        : télémétrie, image 16/9, barre de commandes groupée (CAMÉRA, CIBLES, VOL, AFFICHAGE) ;
      MENUS        : image réduite en haut, barre compacte, onglets dessous (clé droneCamSplit) ;
      VUE TOTALE   : téléphone en petit format (profil COMSPEC_ATAK_DroneMiniFull, oui par défaut) : l'image occupe toute
                     la page, deux incrustations courtes, une rangée de boutons si le téléphone est en main.
    Sans flux (liaison perdue, drone perdu ou en veille, PiP coupée) : neige « NO DATA » sur l'image (fn_droneStatic).
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
            format ["<t font='RobotoCondensedBold'>%1</t><br/><t size='0.8' color='#8a9a93'>À %2 m · batterie %3 %%</t>", [_x, [], "NAME"] call comspec_atak_native_fnc_droneOsd, round (player distance _x), round (100 * fuel _x)],
            [["APPAIRER", compile format ["['pair', objectFromNetId %1] call comspec_atak_native_fnc_droneAction;", str netId _x], true]]];
    } forEach _near;
    _rows pushBack ["buttons", [["ACTUALISER", { [{ ["DRONE"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }]]];
    private _lg = missionNamespace getVariable ["COMSPEC_ATAK_DroneLog", []];
    if ((count _lg) > 0) then {
        _rows pushBack ["section", "Journal du dernier vol", format ["%1 événements", count _lg]];
        _rows pushBack ["text", ((_lg select [((count _lg) - 8) max 0]) apply { format ["<t size='0.8'><t color='#8a9a93'>%1</t>  <t color='%3'>%2</t></t>", _x select 0, _x select 1, ["#c9d4cf", _x select 2] select ((_x select 2) isNotEqualTo "")] }) joinString "<br/>"];
    };
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
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
private _src = missionNamespace getVariable ["COMSPEC_ATAK_DroneFeedSrc", "DRONE"];
// Vue à l'écran : "" (menus seuls), "SPLIT" (image réduite et menus), "FULL" (image et barre), "TOTAL" (image seule, petit format).
private _view = switch (true) do {
    case (!_camOn || {_tab isEqualTo "LOG"}): { "" };
    case (_split): { "SPLIT" };
    case (_mini && {profileNamespace getVariable ["COMSPEC_ATAK_DroneMiniFull", true]}): { "TOTAL" };
    default { "FULL" };
};

// Télémétrie en haut (deux lignes), rafraîchie chaque seconde. Petit format avec l'image : les incrustations la portent.
private _top = 0;
if (_view isEqualTo "" || {!_mini && {_view isNotEqualTo "TOTAL"}}) then {
    private _osd = ["COMSPEC_RscMapPanel", [0, 0, _bw, _fs * 2.6]] call comspec_atak_native_fnc_pageCtrl;
    _osd ctrlSetStructuredText parseText ([_d, _link] call comspec_atak_native_fnc_droneOsd);
    private _osdH = ((ctrlTextHeight _osd) + _fs * 0.5) max (_fs * 2.6);
    _osd ctrlSetPosition [0, 0, _bw, _osdH];
    _osd ctrlCommit 0;
    uiNamespace setVariable ["COMSPEC_ATAK_DroneOsd", _osd];
    _top = _osdH;
};

// Vue caméra : tient dans l'écran du téléphone sans défilement. Image 16/9 centrée dans la place restante, incrustations
// posées sur l'image, barre de commandes dessous.
// Nacelle : glisser sur l'image (cap et site), molette = zoom continu, flèches ou ZQSD/WASD quand la souris est sur
// l'image, RECENTRER ; un clic sans glisser vise le point (et marque la cible en mode MARQUAGE).
if (_view isNotEqualTo "") then {
    private _zoom = missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1];
    private _aim = missionNamespace getVariable ["COMSPEC_ATAK_DroneCamTgt", ""];
    private _markMode = uiNamespace getVariable ["COMSPEC_ATAK_DroneMarkMode", false];
    private _vis = missionNamespace getVariable ["COMSPEC_ATAK_DroneVision", 0];
    private _q = ((profileNamespace getVariable ["COMSPEC_ATAK_DroneQuality", 1]) max 0) min 2;
    private _alts = [[15, 30, 60, 100, 150], [2, 3, 5, 8]] select _cqb;
    private _up = (_alts select { _x > _alt }) param [0, -1];
    private _dn = (_alts select { _x < _alt }) param [(count (_alts select { _x < _alt })) - 1, -1];
    private _zn = ([2, 4, 8] select { _x > _zoom + 0.05 }) param [0, 1];
    private _toTab = { params ["_t"]; compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['droneCamSplit', true]; ['tab', %1] call comspec_atak_native_fnc_droneAction;", str _t] };
    // Boutons : [libellé, code, état ("" | "on" vert | "red" rouge), aide, actif].
    private _bPhoto = ["PHOTO", { ["take"] call comspec_atak_native_fnc_dronePhoto; }, "", "Prendre un cliché de l'image : sa fiche (titre, description, SSE, confidentiel) s'ouvre avant tout envoi"];
    private _bZoom = [format ["ZOOM x%1", _zoom], ["zoom", _zn] call _act, ["", "on"] select (_zoom > 1), "Zoom x1 → x2 → x4 → x8 (molette sur l'image : zoom continu)"];
    private _bVis = [["JOUR", "NUIT", "THERMIQUE"] select _vis, ["vision"] call _act, ["", "on"] select (_vis > 0), "Vision : jour → nuit (intensification) → thermique"];
    private _bNadir = [["VERTICALE", "DEVANT"] select (_aim isEqualTo "NADIR"), ["camAim", ["NADIR", ""] select (_aim isEqualTo "NADIR")] call _act, "", ["Nacelle à la verticale, sous le drone", "Nacelle remise devant le drone"] select (_aim isEqualTo "NADIR"), _src isEqualTo "DRONE"];
    private _bCenter = ["RECENTRER", { ["recenter"] call comspec_atak_native_fnc_droneAction; ["gimbalHud"] call comspec_atak_native_fnc_droneAction; }, "", "Nacelle remise devant le drone, 37° vers le bas", _src isEqualTo "DRONE"];
    private _bMark = [["MARQUER", "MARQUAGE ACTIF"] select _markMode, ["markMode"] call _act, ["", "red"] select _markMode, "Mode marquage : chaque clic sur une personne ou un véhicule de l'image la marque (LOCK). Rappuyez pour en sortir"];
    private _bTargets = [format ["CIBLES (%1)", count _locksAll], ["TARGETS"] call _toTab, ["", "on"] select ((["status"] call comspec_atak_native_fnc_droneSeq) select 0), "Cibles marquées : les faire suivre par le drone, dans l'ordre choisi"];
    private _bUp = [[format ["MONTER %1 m", _up], "MONTER"] select (_up < 0), ["alt", _up] call _act, "", format ["Altitude actuelle %1 m : monter au palier suivant", _alt], _up > 0];
    private _bDn = [[format ["DESCENDRE %1 m", _dn], "DESCENDRE"] select (_dn < 0), ["alt", _dn] call _act, "", format ["Altitude actuelle %1 m : descendre au palier précédent", _alt], _dn > 0];
    private _bHover = ["STATIONNAIRE", ["mode", "hover"] call _act, ["", "on"] select (_m isEqualTo "HOVER"), "Le drone s'arrête là où il est"];
    private _bSrc = [["SOURCE : DRONE", "SOURCE : ATAK", "SOURCE : CASQUE"] select (["DRONE", "PHONE", "HELMET"] find _src), ["feedSrc", ["PHONE", "HELMET", "DRONE"] select (["DRONE", "PHONE", "HELMET"] find _src)] call _act, ["on", ""] select (_src isEqualTo "DRONE"), "Image affichée : drone → caméra du téléphone (devant vous) → caméra casque (choix du porteur dans l'onglet VOL)"];
    private _bQ = [format ["QUALITÉ %1", ["BASSE", "MOY.", "HAUTE"] select _q], ["quality", (_q + 1) mod 3] call _act, "", "Qualité du flux : basse (fluide) → moyenne → haute (net, plus lourd)"];
    private _bMenus = [["MENUS", "IMAGE SEULE"] select _split, { private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _st set ["droneCamSplit", !(_st getOrDefault ["droneCamSplit", false])]; ["DRONE"] call comspec_atak_native_fnc_pageRender; }, "", "MENUS : image réduite et onglets dessous · IMAGE SEULE : l'image à la taille de l'écran"];
    private _bTotal = [["VUE TOTALE", "RÉDUIRE"] select (_view isEqualTo "TOTAL"), { profileNamespace setVariable ["COMSPEC_ATAK_DroneMiniFull", !(profileNamespace getVariable ["COMSPEC_ATAK_DroneMiniFull", true])]; ["DRONE"] call comspec_atak_native_fnc_pageRender; }, "", "Petit format : l'image du drone occupe tout l'écran du téléphone"];
    private _bClose = ["FERMER", ["cam"] call _act, "", "Fermer la caméra (coupe le rendu de l'image)"];
    // Groupes de la barre : [titre, boutons] ; titres affichés en grand format seulement.
    private _labels = !_mini && {_view isEqualTo "FULL"};
    private _groups = switch (true) do {
        case (_view isEqualTo "TOTAL"): { [["", [_bPhoto, _bMark, _bZoom, _bVis, _bTotal]]] select [0, [0, 1] select _inter] };
        case (_mini): { [["", [_bPhoto, _bMark, _bZoom, _bVis]], ["", [_bTotal, _bMenus, _bClose]]] };
        case (_view isEqualTo "SPLIT"): { [["", [_bPhoto, _bMark, _bZoom, _bVis, _bSrc]], ["", [_bUp, _bDn, _bHover, _bMenus, _bClose]]] };
        default { [["CAMÉRA", [_bPhoto, _bZoom, _bVis, _bNadir, _bCenter]], ["CIBLES", [_bMark, _bTargets]], ["VOL", [_bUp, _bDn, _bHover]], ["AFFICHAGE", [_bSrc, _bQ, _bMenus, _bClose]]] };
    };
    // Rangées : les groupes se suivent tant que les boutons gardent une largeur lisible, sinon rangée suivante.
    private _btnH = _fs * ([1.5, 1.3] select _mini);
    private _lh = [0, _fs * 1.1] select _labels; // titre size 0.72 + interligne
    private _maxN = (floor ((_bw - _pad) / (_fs * ([4.4, 3.6] select _mini)))) max 1;
    private _barRows = [];
    private _cnt = 0;
    {
        private _n = count (_x select 1);
        if ((count _barRows) isEqualTo 0 || {_cnt > 0 && {_cnt + _n > _maxN}}) then { _barRows pushBack []; _cnt = 0; };
        (_barRows select ((count _barRows) - 1)) pushBack _x;
        _cnt = _cnt + _n;
    } forEach _groups;
    private _barH = [0, (count _barRows) * (_lh + _btnH + _pad * 0.3) + _pad * 0.3] select ((count _barRows) > 0);
    private _k169 = 0.5625 * pixelH / pixelW;
    private _areaH = if (_view isEqualTo "SPLIT") then { (_bw * _k169) min (_bh * 0.45) } else { _bh - _top - _barH };
    // Image 16/9 ajustée à la place disponible (bandes noires sur les côtés ou dessus / dessous).
    private _iw = _bw min (_areaH / _k169);
    private _ih = _iw * _k169;
    private _ix = (_bw - _iw) / 2;
    private _iy = _top + (_areaH - _ih) / 2;
    private _bg = ["COMSPEC_RscText", [0, _top, _bw, _areaH + _barH]] call comspec_atak_native_fnc_pageCtrl;
    _bg ctrlSetBackgroundColor [0, 0, 0, 1];
    // Texture de la caméra : rendu lancé (caméra recréée au besoin) avant que l'image soit posée.
    ["RscPicture", [_ix, _iy, _iw, _ih], ["camBind"] call comspec_atak_native_fnc_droneAction] call comspec_atak_native_fnc_pageCtrl;
    // Pas de flux : neige « NO DATA » (montrée ou masquée par fn_droneStatic selon la liaison).
    ["build", [_ix, _iy, _iw, _ih]] call comspec_atak_native_fnc_droneStatic;
    // Réticule fin au centre.
    private _cx = _ix + _iw / 2;
    private _cy = _iy + _ih / 2;
    private _rw = _iw * 0.035;
    private _rh = _rw * pixelH / pixelW;
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
    private _txt = _places apply {
        private _t = ["COMSPEC_RscStructuredText", _x] call comspec_atak_native_fnc_pageCtrl;
        _t ctrlSetBackgroundColor [0, 0, 0, 0];
        _t ctrlEnable false;
        if ((_x select 2) isEqualTo 0) then { _t ctrlShow false; };
        _t
    };
    private _feed = [_d, _link, _kind] call comspec_atak_native_fnc_droneOsd;
    { _x ctrlSetStructuredText parseText (_feed select _forEachIndex); } forEach _txt;
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
    private _hit = ["COMSPEC_RscButtonInvisible", [_ix, _iy, _iw, _ih]] call comspec_atak_native_fnc_pageCtrl;
    _hit setVariable ["img", [_iw, _ih]];
    _hit ctrlSetTooltip (["Glisser : orienter la nacelle · molette : zoom · clic : viser le point · flèches / ZQSD : nacelle (Maj : pas fin)", "MARQUAGE : cliquez sur la personne ou le véhicule à marquer · glisser : orienter la nacelle"] select _markMode);
    _hit ctrlAddEventHandler ["MouseButtonDown", {
        params ["_c", "_btn", "_mx", "_my"];
        if (_btn isNotEqualTo 0) exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_DroneDrag", [_mx, _my, ["gimbalGet"] call comspec_atak_native_fnc_droneAction, false]];
    }];
    _hit ctrlAddEventHandler ["MouseMoving", {
        params ["_c", "_mx", "_my"];
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
        private _fovH = 2 * atan (_tv * 1.7778);
        ["gimbal", [(_g0 select 0) - _sg * _dx / _w * _fovH, (_g0 select 1) + _sg * _dy / _h * _fovV]] call comspec_atak_native_fnc_droneAction;
        ["gimbalHud"] call comspec_atak_native_fnc_droneAction;
    }];
    _hit ctrlAddEventHandler ["MouseButtonUp", {
        params ["_c", "_btn", "_mx", "_my"];
        if (_btn isNotEqualTo 0) exitWith {};
        private _dr = uiNamespace getVariable ["COMSPEC_ATAK_DroneDrag", []];
        uiNamespace setVariable ["COMSPEC_ATAK_DroneDrag", []];
        if ((count _dr) >= 4 && {!(_dr select 3)}) then { ["imgClick", [_mx, _my]] call comspec_atak_native_fnc_droneAction; };
    }];
    _hit ctrlAddEventHandler ["MouseEnter", { uiNamespace setVariable ["COMSPEC_ATAK_DroneHover", true]; }];
    _hit ctrlAddEventHandler ["MouseExit", { uiNamespace setVariable ["COMSPEC_ATAK_DroneHover", false]; uiNamespace setVariable ["COMSPEC_ATAK_DroneDrag", []]; }];
    _hit ctrlAddEventHandler ["MouseZChanged", {
        params ["", "_z"];
        ["zoomBy", 1.15 ^ ([-1, 1] select (_z > 0))] call comspec_atak_native_fnc_droneAction;
        ["gimbalHud"] call comspec_atak_native_fnc_droneAction;
        true
    }];
    // Clavier sur l'image : un gestionnaire par écran du téléphone (retiré avec lui). Pas proportionnel au champ :
    // un appui tourne d'environ un huitième de l'image, quel que soit le zoom (Maj : un quart de ce pas).
    private _disp = [] call comspec_atak_native_fnc_display;
    if ((_disp getVariable ["COMSPEC_DroneKeyEh", -1]) < 0) then {
        _disp setVariable ["COMSPEC_DroneKeyEh", _disp displayAddEventHandler ["KeyDown", {
            params ["", "_key", "_shift"];
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
            if (_key in [201, 209]) exitWith { ["zoomBy", [1 / 1.15, 1.15] select (_key isEqualTo 201)] call comspec_atak_native_fnc_droneAction; ["gimbalHud"] call comspec_atak_native_fnc_droneAction; true };
            if (_v isEqualTo []) exitWith { false };
            ["gimbalNudge", _v] call comspec_atak_native_fnc_droneAction;
            ["gimbalHud"] call comspec_atak_native_fnc_droneAction;
            true
        }]];
    };
    // Barre de commandes sous l'image : groupes titrés (grand format), bouton actif en vert, marquage en rouge.
    private _y = _top + _areaH + _pad * 0.3;
    {
        private _row = _x;
        private _n = 0;
        { _n = _n + (count (_x select 1)); } forEach _row;
        private _gw = _pad * 0.8;
        private _bwid = (_bw - _pad - ((count _row) - 1) * _gw) / (_n max 1);
        private _x0 = _pad / 2;
        {
            _x params ["_glabel", "_btns"];
            private _span = _bwid * (count _btns);
            if (_lh > 0) then {
                private _t = ["COMSPEC_RscStructuredText", [_x0, _y, _span, _lh]] call comspec_atak_native_fnc_pageCtrl;
                _t ctrlSetStructuredText parseText format ["<t size='0.72' font='RobotoCondensedBold' color='#8a9a93'>%1</t>", _glabel];
                _t ctrlEnable false;
                private _ln = ["COMSPEC_RscText", [_x0, _y + _lh - pixelH * 3, _span - _pad / 3, pixelH * 2]] call comspec_atak_native_fnc_pageCtrl;
                _ln ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.45];
            };
            {
                _x params ["_lbl", "_code", ["_state", ""], ["_tip", ""], ["_en", true]];
                private _b = ["COMSPEC_RscButton", [_x0 + _forEachIndex * _bwid, _y + _lh, _bwid - _pad / 3, _btnH], _lbl] call comspec_atak_native_fnc_pageCtrl;
                _b ctrlSetFontHeight (_fs * ([0.76, 0.66] select _mini));
                switch (_state) do {
                    case "on": { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
                    case "red": { _b ctrlSetBackgroundColor [0.78, 0.2, 0.16, 0.95]; _b ctrlSetTextColor [1, 1, 1, 1]; };
                    default { _b ctrlSetBackgroundColor [0.12, 0.145, 0.13, 1]; _b ctrlSetTextColor ([[0.45, 0.5, 0.47, 1], [0.92, 0.96, 0.93, 1]] select _en); };
                };
                if (_tip isNotEqualTo "") then { _b ctrlSetTooltip _tip; };
                _b ctrlEnable _en;
                _b ctrlAddEventHandler ["ButtonClick", _code];
            } forEach _btns;
            _x0 = _x0 + _span + _gw;
        } forEach _row;
        _y = _y + _lh + _btnH + _pad * 0.3;
    } forEach _barRows;
    _top = _top + _areaH + _barH;
};
if (_view in ["FULL", "TOTAL"]) exitWith { true };
uiNamespace setVariable ["COMSPEC_ATAK_DroneMenusShown", true];

// Onglets : libellé court et rappel de ce qu'on y trouve.
private _seqOn = (["status"] call comspec_atak_native_fnc_droneSeq) select 0;
_rows pushBack ["segment", "", [
    ["VOL", ["tab", "PILOT"] call _act, _tab isEqualTo "PILOT"],
    ["TÂCHES", ["tab", "TASK"] call _act, _tab isEqualTo "TASK"],
    [format ["CIBLES (%1)", count _locksAll], ["tab", "TARGETS"] call _act, _tab isEqualTo "TARGETS"],
    [format ["PHOTOS (%1)", count _photos], ["tab", "PHOTO"] call _act, _tab isEqualTo "PHOTO"],
    ["JOURNAL", ["tab", "LOG"] call _act, _tab isEqualTo "LOG"]
]];
_rows pushBack ["text", format ["<t size='0.78' color='#8a9a93'>%1%2</t>", (createHashMapFromArray [
    ["PILOT", "Caméra, ordres de vol, altitude et vitesse, veille, dépannage, identité du drone."],
    ["TASK", "Tâches sur un point de la carte, frappe d'un drone armé, recherche d'ennemis."],
    ["TARGETS", "Cibles marquées au viseur, à faire suivre par le drone dans l'ordre choisi ; détections automatiques de la caméra."],
    ["PHOTO", "Clichés pris avec la caméra du drone : fiche (titre, description, SSE, confidentiel) puis envoi vers Athena."],
    ["LOG", "Tout ce qui s'est passé pendant le vol, daté."]
]) getOrDefault [_tab, ""], ["", "  <t color='#4da3ff'>· Suivi des cibles en cours</t>"] select _seqOn]];
if (!_camOn && {_tab isNotEqualTo "LOG"}) then {
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
        format ["<t font='RobotoCondensedBold'>%1</t>%5<br/><t size='0.8' color='#8a9a93'>%2 · à %3 m du drone%4</t>", name _u, [_u, true] call comspec_atak_native_fnc_unitGroup, round _dist, _veh, ["  <t size='0.75' color='#8a9a93'>IA</t>", ""] select (isPlayer _u)],
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
                "<t size='1.15' font='RobotoCondensedBold' color='#8a9a93'>DRONE NON ARMÉ</t><br/><t size='0.85' color='#8a9a93'>Aucune charge fixée : frappe impossible, la recherche ne fait que signaler.</t>",
                format ["<t size='1.15' font='RobotoCondensedBold' color='#e5483a'>ARMÉ · %1</t><br/><t size='0.85' color='#c9d4cf'>Prêt à frapper. La charge part avec le drone.</t>", toUpper _armLabel]
            ] select _armed];
            if (!_armed) then {
                private _dist = round (player distance _d);
                private _near = _dist <= 5;
                private _pl = ["payloads"] call comspec_atak_native_fnc_droneAction;
                private _pick = _s getOrDefault ["droneArmMag", ""];
                if ((_pl findIf { (_x select 0) isEqualTo _pick }) < 0) then { _pick = (_pl param [0, [""]]) select 0; };
                private _pickLbl = ((_pl select { (_x select 0) isEqualTo _pick }) param [0, ["", ""]]) select 1;
                private _ok = "<t color='#5cc76b' font='RobotoCondensedBold'>✓</t>";
                private _no = "<t color='#e5483a' font='RobotoCondensedBold'>✗</t>";
                _rows pushBack ["text", format ["%1 <t font='RobotoCondensedBold'>1. Approchez-vous du drone à moins de 5 m</t><br/><t size='0.85' color='%2'>Vous êtes à %3 m du drone%4</t>",
                    [_no, _ok] select _near, ["#f2ab33", "#5cc76b"] select _near, _dist, ["", " : posez-le au sol près de vous (ATTERRIR, ou RETOUR puis ATTERRIR)"] select (!_near)]];
                _rows pushBack ["text", format ["%1 <t font='RobotoCondensedBold'>2. Choisissez la charge</t>", [_no, _ok] select ((count _pl) > 0)]];
                if ((count _pl) isEqualTo 0) then {
                    _rows pushBack ["text", "<t size='0.85' color='#f2ab33'>Aucune charge compatible dans votre inventaire.</t><br/><t size='0.8' color='#8a9a93'>Acceptées : roquette RPG-7 ou RPG-42, charge de démolition, grenade RGO ou RGN.</t>"];
                } else {
                    _rows pushBack ["segment", "", _pl apply {
                        _x params ["_mag", "_lbl", "_n"];
                        [format ["%1 x%2", toUpper _lbl, _n], compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['droneArmMag', %1]; ['tab', 'TASK'] call comspec_atak_native_fnc_droneAction;", str _mag], _mag isEqualTo _pick]
                    }, "La charge choisie est retirée de votre inventaire et fixée sous le drone"];
                };
                private _can = _near && {_pick isNotEqualTo ""};
                _rows pushBack ["text", format ["%1 <t font='RobotoCondensedBold'>3. Armez</t>%2", [_no, _ok] select _can,
                    ["", format ["<br/><t size='0.85' color='#8a9a93'>%1</t>", ["Terminez d'abord l'étape 1.", "Terminez d'abord l'étape 2."] select _near]] select (!_can)]];
                _rows pushBack ["buttons", [[[format ["ARMER : %1", toUpper _pickLbl], "ARMER"] select (_pickLbl isEqualTo ""), ["arm", _pick] call _act, true, _can]]];
            } else {
                _rows append [
                    ["text", "<t font='RobotoCondensedBold'>Laser</t><br/><t size='0.85' color='#8a9a93'>Le drone pique sur le point laser allumé par un membre de votre groupe (désignateur, pointeur IR). Tir et oublie : si le laser s'éteint, il garde la dernière position.</t>"],
                    ["buttons", [[["PIQUER SUR LE LASER", "CONFIRMER : LASER"] select (["LASER"] call _confirming), ["strike", "LASER"] call _act, true]]],
                    ["text", "<t font='RobotoCondensedBold'>Grille</t><br/><t size='0.85' color='#8a9a93'>Le drone pique sur le centre de la case saisie.</t>"],
                    ["edit", "droneGrid", "Grille de la cible (8 ou 10 chiffres)", _s getOrDefault ["droneGridVal", ""]],
                    ["buttons", [[["PIQUER SUR LA GRILLE", "CONFIRMER : GRILLE"] select (["GRID"] call _confirming), { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["droneGridVal", ["droneGrid", ""] call comspec_atak_native_fnc_formValue]; ["strike", "GRID"] call comspec_atak_native_fnc_droneAction; }, true]]],
                    ["text", "<t size='0.85' color='#8a9a93'>Chaque frappe demande un second appui dans les 5 s pour confirmer. Une cible vue par la caméra se frappe aussi depuis l'onglet CIBLES (FRAPPER).</t>"],
                    ["text", format ["<t font='RobotoCondensedBold'>Désarmer</t><br/><t size='0.85' color='#8a9a93'>Retire la charge et la remet dans votre inventaire. Drone à moins de 5 m (vous êtes à %1 m).</t>", round (player distance _d)]],
                    ["buttons", [["DÉSARMER", ["disarm"] call _act, false, (player distance _d) <= 5 && {_m isNotEqualTo "STRIKE"}]]]
                ];
            };
        };
        _rows append [
            ["section", ["Recherche", "Recherche et frappe"] select (_strikeOk && {_armed}), [
                "Le drone tourne autour de sa position dans le rayon choisi, signale le premier ennemi vu, puis l'observe.",
                "Le drone tourne autour de sa position dans le rayon choisi et pique sur le premier ennemi vu (drone perdu)."
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
        _rows pushBack ["section", format ["Cibles marquées : %1", count _locks], "Caméra : MARQUER puis clic sur la cible (ou la touche « Marquer la cible »). Le marqueur suit la cible sur la carte."];
        if ((count _locks) isEqualTo 0) then {
            _rows pushBack ["text", "<t color='#8a9a93'>Aucune cible marquée. Ouvrez la caméra, appuyez sur MARQUER, puis cliquez sur une personne ou un véhicule de l'image. Une détection ci-dessous peut aussi être accrochée.</t>"];
        } else {
            // Suivi dans l'ordre : le drone passe d'une cible à la suivante (en boucle), caméra pointée sur la cible en cours.
            private _kindS = _s getOrDefault ["droneSeqKind", "FOLLOW"];
            private _dw = _s getOrDefault ["droneSeqDwell", 60];
            private _cur = (_locksAll getOrDefault [_sk, createHashMap]);
            _rows pushBack ["section", "Suivi par le drone", "Choisissez les cibles (AJOUTER) : le drone les suit l'une après l'autre, dans l'ordre de la liste, puis recommence"];
            _rows pushBack ["text", if (_on && {(count _cur) > 0}) then {
                format ["<t color='#4da3ff' font='RobotoCondensedBold'>EN COURS · cible %1 sur %2 : LOCK %3 %4</t><br/><t size='0.8' color='#8a9a93'>%5</t>", _si + 1, count _seq, [_cur get "num", 3] call CBA_fnc_formatNumber, _cur get "name",
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
            _rows pushBack ["text", format ["%8<t font='RobotoCondensedBold'>LOCK %1 · %2</t>  <t size='0.8' color='%3'>%4</t><br/><t size='0.8' color='#8a9a93'>%5 · %6 · marqué à %7</t>",
                [_e get "num", 3] call CBA_fnc_formatNumber, _e get "name", _hex,
                (createHashMapFromArray [["TRACK", "ACCROCHÉ"], ["LOST", "PERDU"], ["DOWN", "NEUTRALISÉ"]]) getOrDefault [_st, _st],
                [_e get "pos", 8] call comspec_atak_native_fnc_gridRef, [format ["vu il y a %1 s", _ago], "en vue"] select (_ago < 2), _e get "clock",
                ["", format ["<t font='RobotoCondensedBold' color='%1'>N° %2%3</t>  ", ["#c9d4cf", "#4da3ff"] select _now, _n + 1, ["", " · EN COURS"] select _now]] select (_n >= 0)]];
            // Suivi : ajouter / retirer, et place dans l'ordre.
            private _lb = [[["AJOUTER AU SUIVI", "RETIRER DU SUIVI"] select (_n >= 0), compile format ["['toggle', %1] call comspec_atak_native_fnc_droneSeq;", str _lk], _n < 0, _st isNotEqualTo "DOWN" || {_n >= 0}]];
            if (_n >= 0) then {
                _lb pushBack ["PLUS TÔT", compile format ["['move', [%1, -1]] call comspec_atak_native_fnc_droneSeq;", str _lk], false, _n > 0];
                _lb pushBack ["PLUS TARD", compile format ["['move', [%1, 1]] call comspec_atak_native_fnc_droneSeq;", str _lk], false, _n < ((count _seq) - 1)];
            };
            _rows pushBack ["buttons", _lb];
            private _lb2 = [[["VOIR", "VUE"] select (_aim isEqualTo format ["LOCK:%1", _lk]), ["camAim", format ["LOCK:%1", _lk]] call _act, false, _st isNotEqualTo "DOWN"]];
            if (_strikeOk && {_armed} && {_st isEqualTo "TRACK"} && {(_e get "tag") isNotEqualTo "AMI"}) then {
                private _tk = hashValue (_e get "obj");
                if (_tk in _tracks) then { _lb2 pushBack [["FRAPPER", "CONFIRMER"] select ([format ["TRK:%1", _tk]] call _confirming), ["strike", format ["TRK:%1", _tk]] call _act, true]; };
            };
            _lb2 pushBack ["LIBÉRER", compile format ["['release', %1] call comspec_atak_native_fnc_lockAction; ['DRONE'] call comspec_atak_native_fnc_pageRender;", str _lk]];
            _rows pushBack ["buttons", _lb2];
        } forEach (_locks select [0, 12]);

        // Détections automatiques : ce que la caméra relève seule en vol (ex-onglet PISTES).
        _rows append [
            ["section", format ["Détections automatiques : %1", count _tracks], "Ce que la caméra du drone repère seule en vol : personnel jusqu'à 450 m, véhicules jusqu'à 700 m. ACCROCHER en fait une cible marquée."],
            ["buttons", [
                ["NACELLE DEVANT", ["camAim", ""] call _act, false, _camOn && {_aim isNotEqualTo ""}],
                ["EFFACER LES DÉTECTIONS", ["tracksClear"] call _act, false, (count _tracks) > 0]
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
                format ["<t font='RobotoCondensedBold'>%1 · %2</t>  <t size='0.8' color='%3'>%4</t>%5<br/><t size='0.8' color='#8a9a93'>%6 · à %7 m du drone · %8</t>",
                    [_num, 2] call CBA_fnc_formatNumber, _name, _hex, _tag,
                    ["", "  <t size='0.8' color='#5cc76b'>DÉTRUIT</t>"] select (_state isNotEqualTo ""),
                    [_pos, 8] call comspec_atak_native_fnc_gridRef, round (_d distance _pos),
                    [format ["vue à %1 (il y a %2)", _clock, [format ["%1 s", _ago], format ["%1 min", floor (_ago / 60)]] select (_ago >= 120)], "en vue"] select (_ago < 3)],
                [], ([[1, 1, 1, 1], [0.9, 0.28, 0.23, 1], [0.95, 0.67, 0.2, 1], [0.3, 0.64, 1, 1]] select ((["ENNEMI", "INCONNU", "AMI"] find _tag) + 1))];
            private _b = [[["VOIR", "VUE"] select (_aim isEqualTo _key), ["camAim", _key] call _act, false, _aim isNotEqualTo _key]];
            if (alive _o && {_state isEqualTo ""} && {!(_key in _locksAll)}) then {
                _b pushBack ["ACCROCHER", compile format ["['mark', 'DRONE', ((missionNamespace getVariable ['COMSPEC_ATAK_DroneTracks', createHashMap]) getOrDefault [%1, [objNull]]) select 0] call comspec_atak_native_fnc_lockAction; ['DRONE'] call comspec_atak_native_fnc_pageRender;", str _key], true];
            };
            if (_strikeOk && {_armed} && {_state isEqualTo ""} && {_tag isNotEqualTo "AMI"}) then {
                _b pushBack [["FRAPPER", "CONFIRMER"] select ([format ["TRK:%1", _key]] call _confirming), ["strike", format ["TRK:%1", _key]] call _act, true];
            };
            _b pushBack ["SUR LA CARTE", ["trackMark", _key] call _act];
            _rows pushBack ["buttons", _b];
        } forEach (_list select [0, 15]);
    };
    case "PHOTO": {
        // Fiche du cliché en cours : rien ne part vers Athena sans ENVOYER.
        private _ed = _s getOrDefault ["dronePhEdit", -1];
        if (_ed >= 0 && {_ed < count _photos}) then {
            (_photos select _ed) params ["_file", "_title", "_desc", "_sse", "_cf", "_grid", "_clock", "_drone", "_state", ["_case", ""], ["_alt", 0], ["_hdg", 0], ["_date", ""]];
            private _active = missionNamespace getVariable ["COMSPEC_SSE_ActiveCase", ""];
            if !(_active isEqualType "") then { _active = ""; };
            _rows append [
                ["section", "Fiche du cliché", "Complétez-la avant l'envoi : rien ne part vers Athena sans ENVOYER"],
                ["info", "Pris à", format ["%1 %2 · %3", _date, _clock, _drone]],
                ["info", "Position du drone", format ["%1 · %2 m sol · cap %3°", _grid, _alt, _hdg]],
                ["info", "Fichier", format ["<t size='0.8'>%1</t>", _file]],
                ["edit", "dronePhTitle", "Titre (60 caractères)", _title],
                ["memo", "dronePhDesc", "Description", _desc, 3],
                ["switch", "SSE (exploitation de site)", _sse, { ["flag", "sse"] call comspec_atak_native_fnc_dronePhoto; },
                    [format ["Rattaché au dossier %1", [_active, _case] select (_sse && {_case isNotEqualTo ""})], "Aucun dossier SSE actif (app SSE) : marqué SSE sans dossier"] select (([_active, _case] select _sse) isEqualTo "")],
                ["switch", "Confidentiel", _cf, { ["flag", "conf"] call comspec_atak_native_fnc_dronePhoto; }, "Marqué [CONFIDENTIEL] en tête de la légende sur Athena"],
                ["text", format ["<t size='0.8' color='#8a9a93'>Légende envoyée : %1</t>", ["caption", _photos select _ed] call comspec_atak_native_fnc_dronePhoto]],
                ["buttons", [
                    ["ENVOYER À ATHENA", compile format ["['send', %1] call comspec_atak_native_fnc_dronePhoto;", _ed], true, _state isNotEqualTo "SENDING"],
                    ["GARDER SANS ENVOYER", { ["keep"] call comspec_atak_native_fnc_dronePhoto; }]
                ]],
                ["buttons", [[[ "SUPPRIMER LE CLICHÉ", "CONFIRMER LA SUPPRESSION"] select (((_s getOrDefault ["dronePhDel", [-1, -10]]) select 0) isEqualTo _ed && {diag_tickTime - ((_s getOrDefault ["dronePhDel", [-1, -10]]) select 1) < 4}), compile format ["['forget', %1] call comspec_atak_native_fnc_dronePhoto;", _ed]]]]
            ];
        };
        _rows pushBack ["section", format ["Clichés du drone : %1", count _photos], "Gardés sur ce poste (dossier Screenshots), aussi visibles dans l'app Photos"];
        _rows pushBack ["buttons", [[["OUVRIR LA CAMÉRA", "PRENDRE UN CLICHÉ"] select _camOn, [["cam"] call _act, { ["take"] call comspec_atak_native_fnc_dronePhoto; }] select _camOn, true]]];
        if ((count _photos) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun cliché. Caméra ouverte : bouton PHOTO sous l'image.</t>"]; };
        {
            _x params ["_i", "_e"];
            _e params ["_file", "_title", "", "_sse", "_cf", "_grid", "_clock", "_drone", "_state"];
            private _stTxt = (createHashMapFromArray [["DRAFT", ["FICHE À COMPLÉTER", "#f2ab33"]], ["LOCAL", ["NON ENVOYÉ", "#8a9a93"]], ["SENDING", ["ENVOI EN COURS", "#4da3ff"]], ["SENT", ["ENVOYÉ", "#5cc76b"]], ["FAILED", ["ÉCHEC D'ENVOI", "#e5483a"]]]) getOrDefault [_state, [_state, "#8a9a93"]];
            _rows pushBack ["text", format ["<t font='RobotoCondensedBold'>%1</t>%2%3  <t size='0.8' color='%5'>%4</t><br/><t size='0.8' color='#8a9a93'>%6 · %7 · %8</t>",
                [_title, "Sans titre"] select (_title isEqualTo ""), ["", "  <t size='0.75' color='#e5483a'>CONFIDENTIEL</t>"] select _cf, ["", "  <t size='0.75' color='#f2ab33'>SSE</t>"] select _sse,
                _stTxt select 0, _stTxt select 1, _grid, _clock, _drone]];
            _rows pushBack ["buttons", [
                [["ENVOYER", "RENVOYER"] select (_state in ["SENT", "FAILED"]), compile format ["['send', %1] call comspec_atak_native_fnc_dronePhoto;", _i], _state in ["DRAFT", "LOCAL"], _state isNotEqualTo "SENDING"],
                ["FICHE", compile format ["['edit', %1] call comspec_atak_native_fnc_dronePhoto;", _i], false, _i isNotEqualTo (_s getOrDefault ["dronePhEdit", -1])],
                [["SUPPRIMER", "CONFIRMER"] select (((_s getOrDefault ["dronePhDel", [-1, -10]]) select 0) isEqualTo _i && {diag_tickTime - ((_s getOrDefault ["dronePhDel", [-1, -10]]) select 1) < 4}), compile format ["['forget', %1] call comspec_atak_native_fnc_dronePhoto;", _i]]
            ]];
        } forEach ((["list"] call comspec_atak_native_fnc_dronePhoto) select [0, 12]);
    };
    case "LOG": {
        private _lg = missionNamespace getVariable ["COMSPEC_ATAK_DroneLog", []];
        _rows pushBack ["section", "Journal", format ["%1 événements, le plus récent en haut", count _lg]];
        private _lines = [];
        for "_i" from ((count _lg) - 1) to 0 step -1 do {
            (_lg select _i) params ["_t", "_txt", ["_c", ""]];
            _lines pushBack format ["<t size='0.85'><t color='#8a9a93'>%1</t>  <t color='%3'>%2</t></t>", _t, _txt, ["#c9d4cf", _c] select (_c isNotEqualTo "")];
        };
        _rows pushBack ["text", ([["<t color='#8a9a93'>Rien à signaler pour l'instant.</t>"], _lines] select ((count _lines) > 0)) joinString "<br/>"];
    };
    default {
        if (_ground) then {
            _rows pushBack ["buttons", [["DÉMARRER ET DÉCOLLER", ["takeoff"] call _act, true]]];
            _rows pushBack ["text", format ["<t size='0.85' color='#8a9a93'>Montée à %1 m puis stationnaire. Tout ordre de vol donné au sol fait aussi décoller le drone.</t>", _alt]];
        };
        // Caméra : ouverture, cliché, qualité du flux, petit format, sens du glisser, source de l'image.
        private _q = ((profileNamespace getVariable ["COMSPEC_ATAK_DroneQuality", 1]) max 0) min 2;
        private _dm = profileNamespace getVariable ["COMSPEC_ATAK_DroneDragMode", 0];
        _rows append [
            ["section", "Caméra", "Image de la nacelle, clichés et marquage des cibles"],
            ["buttons", [
                [["OUVRIR LA CAMÉRA", "FERMER LA CAMÉRA"] select _camOn, ["cam"] call _act, !_camOn],
                ["PRENDRE UN CLICHÉ", { ["take"] call comspec_atak_native_fnc_dronePhoto; }, false, _camOn]
            ]],
            ["segment", "Qualité du flux", [["BASSE", ["quality", 0] call _act, _q isEqualTo 0], ["MOYENNE", ["quality", 1] call _act, _q isEqualTo 1], ["HAUTE", ["quality", 2] call _act, _q isEqualTo 2]],
                "basse : plus fluide · haute : plus net, plus lourd"],
            ["switch", "Vue totale en petit format", profileNamespace getVariable ["COMSPEC_ATAK_DroneMiniFull", true],
                { profileNamespace setVariable ["COMSPEC_ATAK_DroneMiniFull", !(profileNamespace getVariable ["COMSPEC_ATAK_DroneMiniFull", true])]; ["DRONE"] call comspec_atak_native_fnc_pageRender; },
                "Téléphone en petit (Ctrl+U) : l'image du drone occupe tout l'écran, incrustations réduites"],
            ["segment", "Glisser sur l'image", [
                ["SAISIR L'IMAGE", { profileNamespace setVariable ["COMSPEC_ATAK_DroneDragMode", 0]; ["DRONE"] call comspec_atak_native_fnc_pageRender; }, _dm isEqualTo 0],
                ["DIRIGER LA NACELLE", { profileNamespace setVariable ["COMSPEC_ATAK_DroneDragMode", 1]; ["DRONE"] call comspec_atak_native_fnc_pageRender; }, _dm isEqualTo 1]
            ], ["l'image suit la souris, comme sur un écran tactile", "la nacelle tourne du côté où l'on glisse"] select (_dm isEqualTo 1)],
            ["segment", "Source de l'image", [
                ["DRONE", ["feedSrc", "DRONE"] call _act, _src isEqualTo "DRONE"],
                ["CAMÉRA ATAK", ["feedSrc", "PHONE"] call _act, _src isEqualTo "PHONE"],
                ["CAMÉRA CASQUE", ["feedSrc", "HELMET"] call _act, _src isEqualTo "HELMET"]
            ], "ATAK : le téléphone filme devant vous · casque : regard du porteur"]
        ];
        if (_src isEqualTo "HELMET") then {
            // Porteur de la caméra casque : soi-même ou un équipier du groupe à moins de 3 km.
            private _fu = missionNamespace getVariable ["COMSPEC_ATAK_DroneFeedUnit", player];
            private _mates = [player] + ((units group player) select { alive _x && {_x isNotEqualTo player} && {(_x distance player) < 3000} });
            _rows pushBack ["segment", "Caméra casque de", (_mates select [0, 5]) apply {
                [[name _x, "MOI"] select (_x isEqualTo player), compile format ["['feedUnit', %1] call comspec_atak_native_fnc_droneAction;", str ([netId _x, getPlayerUID _x] select (isPlayer _x))], _x isEqualTo _fu]
            }];
        };
        // Vol : ordres immédiats.
        _rows pushBack ["section", "Vol", "Ordres immédiats ; l'altitude et la vitesse servent aussi aux tâches"];
        // Veille : posé ou stationnaire, peu de consommation, capteur coupé ; reprise d'un appui.
        if (_m isEqualTo "STANDBY") then {
            _rows pushBack ["text", format ["<t color='#8fb3c9'>En veille (%1) : consommation réduite, capteur et caméra coupés.</t>", ["stationnaire", "posé"] select ((_d getVariable ["COMSPEC_DroneStandby", "LAND"]) isEqualTo "LAND")]];
            _rows pushBack ["buttons", [["REPRENDRE", ["resume"] call _act, true]]];
        };
        if (_m isEqualTo "MANUAL") then { _rows pushBack ["text", "<t color='#f2ab33'>Pilotage manuel : vous seul pouvez connecter un terminal UAV à ce drone. Le moindre ordre du téléphone reprend la main et déconnecte le terminal.</t>"]; };
        _rows append [
            ["segment", "", [
                ["STATIONNAIRE", ["mode", "hover"] call _act, _m isEqualTo "HOVER" && {!_ground}],
                ["ME SUIVRE", ["mode", "me"] call _act, _m isEqualTo "FOLLOW" && {(_d getVariable ["COMSPEC_DroneFollow", objNull]) isEqualTo player}],
                ["REVENIR À MOI", ["mode", "home"] call _act, _m in ["HOME", "RTH"]],
                ["ATTERRIR", ["mode", "land"] call _act, _m isEqualTo "LAND"]
            ]],
            ["segment", "Altitude", (([[15, 30, 60, 100, 150], [2, 3, 5, 8]] select _cqb) apply { [format ["%1 m", _x], ["alt", _x] call _act, _alt isEqualTo _x] })],
            ["segment", "Vitesse", (([[20, 40, 60, 90], [5, 10, 15]] select _cqb) apply { [format ["%1 km/h", _x], ["speed", _x] call _act, _spd isEqualTo _x] })],
            ["switch", "Profil CQB", _cqb, ["cqb"] call _act, "Vol bas en intérieur : 2 à 8 m, 5 à 15 km/h, suivi à 5 m, arrêt devant un obstacle"],
            ["section", "Dépannage", "Le drone ne bouge pas, ou vous voulez le piloter vous-même"],
            ["buttons", [["FORCER L'EXÉCUTION", ["force"] call _act, false], ["PILOTAGE MANUEL", ["manual"] call _act, _m isEqualTo "MANUAL"]]],
            ["text", format ["<t size='0.8' color='#8a9a93'>FORCER : renvoie « %1 », recrée l'IA de vol si besoin, efface ses points de passage, réapplique altitude et vitesse (fait seul une fois si l'ordre n'avance pas pendant 10 s). MANUEL : terminal UAV.</t>",
                ((createHashMapFromArray [["takeoff", "décoller"], ["hover", "stationnaire"], ["follow", "suivre"], ["home", "retour au pilote"], ["land", "atterrir"], ["hunt", "recherche"], ["goto", "aller au point"], ["loiter", "orbite"], ["observe", "observer"], ["escort", "escorte"], ["route", "route"], ["rth", "retour au point de décollage"], ["strike", "frappe"], ["manual", "pilotage manuel"], ["standby", "veille"]]) getOrDefault [(missionNamespace getVariable ["COMSPEC_ATAK_DroneOrder", []]) param [0, "hover"], "stationnaire"])]],
            ["section", "Suivre un joueur", ["Le drone se place 15 m derrière lui", "Profil CQB : 5 m derrière lui, à hauteur d'homme"] select _cqb]
        ];
        if ((count _others) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun allié à moins de 2 km du drone.</t>"]; };
        {
            _x params ["_dist", "_u"];
            private _on = _m isEqualTo "FOLLOW" && {(_d getVariable ["COMSPEC_DroneFollow", objNull]) isEqualTo _u};
            _rows pushBack ([_u, _dist, ["SUIVRE", "SUIVI"] select _on, _on,
                compile format ["['player', %1] call comspec_atak_native_fnc_droneAction; ['mode', 'player'] call comspec_atak_native_fnc_droneAction;", str ([_u] call _idOf)]] call _playerRow);
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
                format ["<t font='RobotoCondensedBold'>%1</t><br/><t size='0.8' color='#8a9a93'>%2 · à %3 m de vous</t>", name _u, [_u, true] call comspec_atak_native_fnc_unitGroup, round _dist],
                [[["TRANSFÉRER", "CONFIRMER"] select ([_ck] call _confirming), ["transfer", getPlayerUID _u] call _act, [_ck] call _confirming]]];
        } forEach (_mates select [0, 6]);
        _rows pushBack ["buttons", [["DÉSAPPAIRER", ["unpair"] call _act]]];
    };
};
[_rows, [0, _top, _bw, (_bh - _top) max (_fs * 4)]] call comspec_atak_native_fnc_formRender;
true
