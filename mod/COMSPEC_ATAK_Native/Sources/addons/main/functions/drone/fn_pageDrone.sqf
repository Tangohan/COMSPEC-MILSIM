/*
    App Drone : pilotage d'un drone posé par le joueur (quadricoptère ou drone de sac à dos).
    Appairage au pied du pilote, puis quatre onglets (clé droneTab de l'état) :
      PILOTAGE : décollage, stationnaire, suivi, retour, atterrissage, veille, FORCER L'EXÉCUTION, profil CQB, altitude,
                 vitesse, caméra, manuel ; nom, icône et transfert de l'appairage à un allié proche ;
      TÂCHES   : tâche sur point (grille ou carte) : aller, orbite, observer, escorte ; frappe ; recherche et frappe ;
      PISTES   : ce que voit la caméra du drone (pistes capteur) : viser, frapper, marquer ;
      JOURNAL  : chaque événement daté.
    En tête : télémétrie (deux lignes), puis la vue caméra en 16/9 avec ses incrustations quand elle est ouverte.
    Ordres : fn_droneAction ; exécution : fn_droneCmd.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _fs = _l get "fontSmall";
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
private _act = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_droneAction;", str _a, str _v] };
private _rows = [];
uiNamespace setVariable ["COMSPEC_ATAK_DroneFeedTxt", []];

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
// Télémétrie en haut (deux lignes), hauteur ajustée au texte, rafraîchie chaque seconde.
private _osd = ["COMSPEC_RscMapPanel", [0, 0, _bw, _fs * 2.6]] call comspec_atak_native_fnc_pageCtrl;
_osd ctrlSetStructuredText parseText ([_d, _link] call comspec_atak_native_fnc_droneOsd);
private _osdH = ((ctrlTextHeight _osd) + _fs * 0.5) max (_fs * 2.6);
_osd ctrlSetPosition [0, 0, _bw, _osdH];
_osd ctrlCommit 0;
uiNamespace setVariable ["COMSPEC_ATAK_DroneOsd", _osd];

private _tab = _s getOrDefault ["droneTab", "PILOT"];
private _m = _d getVariable ["COMSPEC_DroneMode", "HOVER"];
private _cqb = _d getVariable ["COMSPEC_DroneCqb", false];
private _alt = _s getOrDefault ["droneAlt", 40];
private _spd = _s getOrDefault ["droneSpd", 40];
private _rad = _s getOrDefault ["droneHuntRad", 250];
private _armed = (_d getVariable ["COMSPEC_DroneArmed", ""]) isNotEqualTo "";
private _strikeOk = missionNamespace getVariable ["comspec_atak_native_drone_strike", true];
private _camOn = !isNull (missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull]);
private _ground = ((getPosATL _d) select 2) < 1 && {_m isNotEqualTo "STRIKE"};
private _conf = _s getOrDefault ["droneConfirm", ["", 0]];
private _confirming = { params ["_k"]; (_conf select 0) isEqualTo _k && {diag_tickTime - (_conf select 1) < 5} };
private _top = _osdH;

// Vue caméra : tient dans l'écran du téléphone sans défilement. Image 16/9 centrée dans la place restante sous la
// télémétrie, incrustations posées sur l'image, une barre de commandes dessous. Mode LISTE (droneCamSplit) : image
// réduite en haut et onglets dessous (pistes, tâches), comme avant.
// Nacelle : glisser sur l'image (cap et site), molette = zoom continu, flèches ou ZQSD/WASD quand la souris est sur
// l'image, CENTRER ; un clic sans glisser vise le point (et le marque en mode MARQUAGE).
private _camFull = _camOn && {_tab isNotEqualTo "LOG"} && {!(_s getOrDefault ["droneCamSplit", false])};
if (_camOn && {_tab isNotEqualTo "LOG"}) then {
    private _zoom = missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1];
    private _aim = missionNamespace getVariable ["COMSPEC_ATAK_DroneCamTgt", ""];
    private _markMode = uiNamespace getVariable ["COMSPEC_ATAK_DroneMarkMode", false];
    private _alts = [[15, 30, 60, 100, 150], [2, 3, 5, 8]] select _cqb;
    private _up = (_alts select { _x > _alt }) param [0, -1];
    private _dn = (_alts select { _x < _alt }) param [(count (_alts select { _x < _alt })) - 1, -1];
    private _btns = [
        [["MARQ.", "MARQ. ●"] select _markMode, ["markMode"] call _act, _markMode, "Mode marquage : un clic sur l'image vise ce point et marque la cible (touche « Marquer la cible » : marque sous le réticule)", [0.75, 0.18, 0.15, 0.9]],
        [format ["x%1", _zoom], ["zoom", [[2, 4] select (_zoom >= 2), 1] select (_zoom >= 4)] call _act, false, "Zoom x1 → x2 → x4 (molette sur l'image : zoom continu jusqu'à x8)"],
        [["JOUR", "NUIT", "THERM."] select (missionNamespace getVariable ["COMSPEC_ATAK_DroneVision", 0]), ["vision"] call _act, false, "Vision : jour, nuit, thermique"],
        [["NADIR", "DEVANT"] select (_aim isEqualTo "NADIR"), ["camAim", ["NADIR", ""] select (_aim isEqualTo "NADIR")] call _act, _aim isEqualTo "NADIR", "Nacelle à la verticale sous le drone"],
        ["CENTRER", { ["recenter"] call comspec_atak_native_fnc_droneAction; }, false, "Nacelle remise devant (cap du drone, 37° vers le bas)"],
        ["▲ ALT", ["alt", _up] call _act, false, format ["Monter à %1 m", _up], [], _up > 0],
        ["▼ ALT", ["alt", _dn] call _act, false, format ["Descendre à %1 m", _dn], [], _dn > 0],
        ["STAT.", ["mode", "hover"] call _act, _m isEqualTo "HOVER", "Stationnaire : le drone s'arrête là où il est"],
        [["LISTE", "PLEIN"] select (_s getOrDefault ["droneCamSplit", false]), { private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _st set ["droneCamSplit", !(_st getOrDefault ["droneCamSplit", false])]; ["DRONE"] call comspec_atak_native_fnc_pageRender; }, false, "LISTE : image réduite et onglets dessous · PLEIN : image seule, à la taille de l'écran"],
        ["✕", ["cam"] call _act, false, "Fermer la caméra"]
    ];
    private _nb = count _btns;
    private _rowsN = [1, 2] select (((_bw - _pad) / _nb) < _fs * 2.6);
    private _perRow = ceil (_nb / _rowsN);
    private _bh2 = _fs * 1.5;
    private _barH = _rowsN * (_bh2 + _pad * 0.3) + _pad * 0.3;
    private _areaH = if (_camFull) then { _bh - _top - _barH } else { ((_bw * 0.5625 * pixelH / pixelW) min (_bh * 0.45)) };
    // Image 16/9 ajustée à la place disponible (bandes noires sur les côtés ou dessus / dessous).
    private _k169 = 0.5625 * pixelH / pixelW;
    private _iw = _bw min (_areaH / _k169);
    private _ih = _iw * _k169;
    private _ix = (_bw - _iw) / 2;
    private _iy = _top + (_areaH - _ih) / 2;
    private _bg = ["COMSPEC_RscText", [0, _top, _bw, _areaH + _barH]] call comspec_atak_native_fnc_pageCtrl;
    _bg ctrlSetBackgroundColor [0, 0, 0, 1];
    // Texture de rendu : largeur et hauteur en puissances de 2 (576 ne l'est pas : texture jamais créée, image noire) ;
    // le rapport 16/9 de la caméra est donné par le dernier paramètre.
    ["RscPicture", [_ix, _iy, _iw, _ih], "#(argb,1024,512,1)r2t(comspec_dronecam,1.7778)"] call comspec_atak_native_fnc_pageCtrl;
    if !(isPiPEnabled) then {
        private _w = ["COMSPEC_RscStructuredText", [_ix, _iy + _ih * 0.4, _iw, _fs * 2.6]] call comspec_atak_native_fnc_pageCtrl;
        _w ctrlSetStructuredText parseText "<t align='center' color='#f2ab33'>Image dans l'image (PiP) désactivée :<br/>Options > Vidéo > Image dans l'image</t>";
    };
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
    private _ih2 = _fs * 2.2;
    private _txt = [
        [_ix + _pad, _iy + _pad / 2, _iw * 0.25, _ih2], [_ix + _iw * 0.2, _iy + _pad / 2, _iw * 0.6, _ih2], [_ix + _iw * 0.7 - _pad, _iy + _pad / 2, _iw * 0.3, _ih2],
        [_ix + _pad, _iy + _ih - _fs * 1.3, _iw * 0.5, _fs * 1.2], [_ix + _iw * 0.45 - _pad, _iy + _ih - _fs * 1.3, _iw * 0.55, _fs * 1.2]
    ] apply {
        private _t = ["COMSPEC_RscStructuredText", _x] call comspec_atak_native_fnc_pageCtrl;
        _t ctrlSetBackgroundColor [0, 0, 0, 0];
        _t ctrlEnable false;
        _t
    };
    private _feed = [_d, _link, "FEED"] call comspec_atak_native_fnc_droneOsd;
    { _x ctrlSetStructuredText parseText (_feed select _forEachIndex); } forEach _txt;
    uiNamespace setVariable ["COMSPEC_ATAK_DroneFeedTxt", _txt];
    // Angles de la nacelle et aide, au-dessus des incrustations du bas (mis à jour à chaque mouvement).
    private _gim = ["COMSPEC_RscStructuredText", [_ix + _iw * 0.2, _iy + _ih - _fs * 2.5, _iw * 0.6, _fs * 1.2]] call comspec_atak_native_fnc_pageCtrl;
    _gim ctrlEnable false;
    uiNamespace setVariable ["COMSPEC_ATAK_DroneGimTxt", [_gim, ""]];
    ["gimbalHud"] call comspec_atak_native_fnc_droneAction;
    // Cadres LOCK des cibles marquées et cadre « visé » sous le réticule, suivis image par image.
    ["build", [_ix, _iy, _iw, _ih], "FEED", true] call comspec_atak_native_fnc_droneFlir;
    // Surface de commande de la nacelle (au-dessus de l'image et des cadres).
    private _hit = ["COMSPEC_RscButtonInvisible", [_ix, _iy, _iw, _ih]] call comspec_atak_native_fnc_pageCtrl;
    _hit setVariable ["img", [_iw, _ih]];
    _hit ctrlSetTooltip "Glisser : orienter la nacelle · molette : zoom · clic : viser le point (MARQ. : le marquer) · flèches / ZQSD : nacelle";
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
        // Glisser d'une largeur d'image = un champ de vision : le point saisi reste sous la souris.
        private _tv = 0.7 / (missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1]);
        private _fovV = 2 * atan _tv;
        private _fovH = 2 * atan (_tv * 1.7778);
        ["gimbal", [(_g0 select 0) - _dx / _w * _fovH, (_g0 select 1) + _dy / _h * _fovV]] call comspec_atak_native_fnc_droneAction;
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
    // Clavier sur l'image : un gestionnaire par écran du téléphone (retiré avec lui).
    private _disp = [] call comspec_atak_native_fnc_display;
    if ((_disp getVariable ["COMSPEC_DroneKeyEh", -1]) < 0) then {
        _disp setVariable ["COMSPEC_DroneKeyEh", _disp displayAddEventHandler ["KeyDown", {
            params ["", "_key", "_shift"];
            if !(uiNamespace getVariable ["COMSPEC_ATAK_DroneHover", false]) exitWith { false };
            if (isNull (missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull])) exitWith { false };
            if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isNotEqualTo "DRONE") exitWith { false };
            private _st = [4, 1] select _shift;
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
    // Barre de commandes sous l'image (une ou deux rangées selon la largeur).
    private _by0 = _top + _areaH + _pad * 0.3;
    private _bwid = (_bw - _pad) / _perRow;
    {
        _x params ["_lbl", "_code", "_on", ["_tip", ""], ["_onCol", []], ["_en", true]];
        private _r = floor (_forEachIndex / _perRow);
        private _cI = _forEachIndex mod _perRow;
        private _b = ["COMSPEC_RscButton", [_pad / 2 + _cI * _bwid, _by0 + _r * (_bh2 + _pad * 0.3), _bwid - _pad / 3, _bh2], _lbl] call comspec_atak_native_fnc_pageCtrl;
        _b ctrlSetFontHeight (_fs * 0.78);
        _b ctrlSetBackgroundColor ([[0.12, 0.14, 0.13, 1], [_onCol, [0.36, 0.78, 0.42, 0.85]] select (_onCol isEqualTo [])] select _on);
        if (_tip isNotEqualTo "") then { _b ctrlSetTooltip _tip; };
        _b ctrlEnable _en;
        _b ctrlAddEventHandler ["ButtonClick", _code];
    } forEach _btns;
    _top = _top + _areaH + _barH;
};
if (_camFull) exitWith { true };

_rows pushBack ["segment", "", [
    ["PILOTAGE", ["tab", "PILOT"] call _act, _tab isEqualTo "PILOT"],
    ["TÂCHES", ["tab", "TASK"] call _act, _tab isEqualTo "TASK"],
    ["PISTES", ["tab", "TRACKS"] call _act, _tab isEqualTo "TRACKS"],
    ["JOURNAL", ["tab", "LOG"] call _act, _tab isEqualTo "LOG"]
]];

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
                [format [" en %1", [_p, 8] call comspec_atak_native_fnc_gridRef], format [" de %1", name (_d getVariable ["COMSPEC_DroneFollow", objNull])]] select (_k isEqualTo "ESCORT"),
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
                    ["text", "<t size='0.85' color='#8a9a93'>Chaque frappe demande un second appui dans les 5 s pour confirmer. Une piste vue par la caméra se frappe aussi depuis l'onglet PISTES (FRAPPER).</t>"],
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
    case "TRACKS": {
        private _tracks = missionNamespace getVariable ["COMSPEC_ATAK_DroneTracks", createHashMap];
        private _aim = missionNamespace getVariable ["COMSPEC_ATAK_DroneCamTgt", ""];
        _rows append [
            ["section", format ["Pistes capteur : %1", count _tracks], "Ce que la caméra voit en vue directe, jusqu'à 450 m (personnel) et 700 m (véhicules)"],
            ["buttons", [
                [["OUVRIR LA CAMÉRA", "CAMÉRA DEVANT"] select _camOn, [["cam"] call _act, ["camAim", ""] call _act] select _camOn, false, !_camOn || {_aim isNotEqualTo ""}],
                ["EFFACER LES PISTES", ["tracksClear"] call _act, false, (count _tracks) > 0]
            ]]
        ];
        // Cibles marquées au viseur : suivies tant que la caméra les voit, puis perdues selon le réglage serveur.
        private _locks = ["list"] call comspec_atak_native_fnc_lockAction;
        _rows pushBack ["section", format ["Cibles marquées : %1", count _locks], "Réticule sur la cible, puis MARQUER (ou la touche « Marquer la cible »)"];
        if ((count _locks) isEqualTo 0) then {
            _rows pushBack ["text", "<t color='#8a9a93'>Aucune cible marquée. Ouvrez la caméra, cliquez l'image pour pointer la nacelle, puis MARQUER.</t>"];
        };
        {
            _x params ["_lk", "_e"];
            private _o = _e get "obj";
            private _st = _e get "state";
            private _hex = (createHashMapFromArray [["TRACK", "#e5483a"], ["LOST", "#8a9a93"], ["DOWN", "#5cc76b"]]) getOrDefault [_st, "#c9d4cf"];
            private _ago = round (time - (_e get "seen"));
            _rows pushBack ["text", format ["<t font='RobotoCondensedBold'>LOCK %1 · %2</t>  <t size='0.8' color='%3'>%4</t><br/><t size='0.8' color='#8a9a93'>%5 · %6 · marqué à %7</t>",
                [_e get "num", 3] call CBA_fnc_formatNumber, _e get "name", _hex,
                (createHashMapFromArray [["TRACK", "ACCROCHÉ"], ["LOST", "PERDU"], ["DOWN", "NEUTRALISÉ"]]) getOrDefault [_st, _st],
                [_e get "pos", 8] call comspec_atak_native_fnc_gridRef, [format ["vu il y a %1 s", _ago], "en vue"] select (_ago < 2), _e get "clock"]];
            private _lb = [["SUIVRE", ["camAim", format ["LOCK:%1", _lk]] call _act, false, _st isNotEqualTo "DOWN"]];
            if (_strikeOk && {_armed} && {_st isEqualTo "TRACK"} && {(_e get "tag") isNotEqualTo "AMI"}) then {
                private _tk = hashValue _o;
                if (_tk in _tracks) then { _lb pushBack [["FRAPPER", "CONFIRMER"] select ([format ["TRK:%1", _tk]] call _confirming), ["strike", format ["TRK:%1", _tk]] call _act, true]; };
            };
            _lb pushBack ["LIBÉRER", compile format ["['release', %1] call comspec_atak_native_fnc_lockAction; ['DRONE'] call comspec_atak_native_fnc_pageRender;", str _lk]];
            _rows pushBack ["buttons", _lb];
        } forEach (_locks select [0, 8]);
        if ((count _tracks) isEqualTo 0) then {
            _rows pushBack ["text", format ["<t color='#8a9a93'>%1</t>", ["Aucune piste pour l'instant. Le drone relève ce qu'il voit dès qu'il vole avec la liaison.", "Aucune piste : le drone doit voler et garder la liaison pour relever ce qu'il voit."] select (_ground || {!(_link select 2)})]];
        };
        // Ennemis d'abord, puis les plus récentes ; les pistes détruites en bas.
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
            private _b = [[["CAMÉRA", "VISÉE"] select (_aim isEqualTo _key), ["camAim", _key] call _act, false, _aim isNotEqualTo _key]];
            if (_strikeOk && {_armed} && {_state isEqualTo ""} && {_tag isNotEqualTo "AMI"}) then {
                _b pushBack [["FRAPPER", "CONFIRMER"] select ([format ["TRK:%1", _key]] call _confirming), ["strike", format ["TRK:%1", _key]] call _act, true];
            };
            _b pushBack ["MARQUER", ["trackMark", _key] call _act];
            _rows pushBack ["buttons", _b];
        } forEach (_list select [0, 15]);
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
        // Veille : posé ou stationnaire, peu de consommation, capteur coupé ; reprise d'un appui.
        if (_m isEqualTo "STANDBY") then {
            _rows pushBack ["text", format ["<t color='#8fb3c9'>En veille (%1) : consommation réduite, capteur et caméra coupés.</t>", ["stationnaire", "posé"] select ((_d getVariable ["COMSPEC_DroneStandby", "LAND"]) isEqualTo "LAND")]];
            _rows pushBack ["buttons", [["REPRENDRE", ["resume"] call _act, true]]];
        };
        if (_m isEqualTo "MANUAL") then { _rows pushBack ["text", "<t color='#f2ab33'>Pilotage manuel : vous seul pouvez connecter un terminal UAV à ce drone. Le moindre ordre du téléphone reprend la main et déconnecte le terminal.</t>"]; };
        _rows append [
            ["segment", "Vol", [
                ["STATIONNAIRE", ["mode", "hover"] call _act, _m isEqualTo "HOVER" && {!_ground}],
                ["ME SUIVRE", ["mode", "me"] call _act, _m isEqualTo "FOLLOW" && {(_d getVariable ["COMSPEC_DroneFollow", objNull]) isEqualTo player}],
                ["RETOUR", ["mode", "home"] call _act, _m in ["HOME", "RTH"]],
                ["ATTERRIR", ["mode", "land"] call _act, _m isEqualTo "LAND"]
            ]],
            ["switch", "Profil CQB", _cqb, ["cqb"] call _act, "Vol bas en intérieur : 2 à 8 m, 5 à 15 km/h, suivi à 5 m, arrêt devant un obstacle"],
            ["segment", "Altitude", (([[15, 30, 60, 100, 150], [2, 3, 5, 8]] select _cqb) apply { [format ["%1 m", _x], ["alt", _x] call _act, _alt isEqualTo _x] })],
            ["segment", "Vitesse", (([[20, 40, 60, 90], [5, 10, 15]] select _cqb) apply { [format ["%1 km/h", _x], ["speed", _x] call _act, _spd isEqualTo _x] })],
            ["buttons", [[["CAMÉRA", "FERMER LA CAMÉRA"] select _camOn, ["cam"] call _act, _camOn], ["PILOTAGE MANUEL", ["manual"] call _act, _m isEqualTo "MANUAL"]]],
            ["buttons", [["FORCER L'EXÉCUTION", ["force"] call _act, false]]],
            ["text", format ["<t size='0.8' color='#8a9a93'>Le drone ne bouge pas ou n'obéit pas ? Renvoie « %1 », recrée l'IA de vol si besoin, efface ses points de passage, réapplique altitude et vitesse. Fait seul une fois si l'ordre n'avance pas pendant 10 s.</t>",
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
