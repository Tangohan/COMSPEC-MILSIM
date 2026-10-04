/*
    App Drone : pilotage d'un drone posé par le joueur (quadricoptère ou drone de sac à dos).
    Appairage au pied du pilote, puis quatre onglets (clé droneTab de l'état) :
      PILOTAGE : décollage, stationnaire, suivi, retour, atterrissage, profil CQB, altitude, vitesse, caméra, manuel ;
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
            format ["<t font='RobotoCondensedBold'>%1</t><br/><t size='0.8' color='#8a9a93'>À %2 m · batterie %3 %%</t>", getText (configOf _x >> "displayName"), round (player distance _x), round (100 * fuel _x)],
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

// Vue caméra : pleine largeur en 16/9, incrustations façon DJI, boutons posés sur l'image.
if (_camOn && {_tab isNotEqualTo "LOG"}) then {
    private _fh = _bw * 0.5625 * pixelH / pixelW;
    private _bg = ["COMSPEC_RscText", [0, _top, _bw, _fh]] call comspec_atak_native_fnc_pageCtrl;
    _bg ctrlSetBackgroundColor [0, 0, 0, 1];
    ["RscPicture", [0, _top, _bw, _fh], "#(argb,1024,576,1)r2t(comspec_dronecam,1.7778)"] call comspec_atak_native_fnc_pageCtrl;
    // Réticule fin au centre.
    private _cx = _bw / 2;
    private _cy = _top + _fh / 2;
    private _rw = _bw * 0.035;
    private _rh = _rw * pixelH / pixelW;
    {
        private _r = ["COMSPEC_RscText", _x] call comspec_atak_native_fnc_pageCtrl;
        _r ctrlSetBackgroundColor [1, 1, 1, 0.85];
    } forEach [
        [_cx - _rw * 1.4, _cy - pixelH, _rw, pixelH * 2], [_cx + _rw * 0.4, _cy - pixelH, _rw, pixelH * 2],
        [_cx - pixelW, _cy - _rh * 1.4, pixelW * 2, _rh], [_cx - pixelW, _cy + _rh * 0.4, pixelW * 2, _rh]
    ];
    // Incrustations : haut gauche, haut centre, haut droite, bas gauche, bas droite (mises à jour chaque seconde).
    private _ih = _fs * 2.2;
    private _bh2 = _fs * 1.35;
    private _btnY = _top + _fh - _bh2 - _pad / 2;
    private _txt = [
        [_pad, _top + _pad / 2, _bw * 0.25, _ih], [_bw * 0.2, _top + _pad / 2, _bw * 0.6, _ih], [_bw * 0.7 - _pad, _top + _pad / 2, _bw * 0.3, _ih],
        [_pad, _btnY - _fs * 1.25, _bw * 0.5, _fs * 1.2], [_bw * 0.45 - _pad, _btnY - _fs * 1.25, _bw * 0.55, _fs * 1.2]
    ] apply {
        private _t = ["COMSPEC_RscStructuredText", _x] call comspec_atak_native_fnc_pageCtrl;
        _t ctrlSetBackgroundColor [0, 0, 0, 0];
        _t
    };
    private _feed = [_d, _link, "FEED"] call comspec_atak_native_fnc_droneOsd;
    { _x ctrlSetStructuredText parseText (_feed select _forEachIndex); } forEach _txt;
    uiNamespace setVariable ["COMSPEC_ATAK_DroneFeedTxt", _txt];
    // Boutons sur l'image : zoom, vision, nadir / devant, fermer.
    private _zoom = missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1];
    private _aim = missionNamespace getVariable ["COMSPEC_ATAK_DroneCamTgt", ""];
    private _btns = [
        ["x1", ["zoom", 1] call _act, _zoom isEqualTo 1], ["x2", ["zoom", 2] call _act, _zoom isEqualTo 2], ["x4", ["zoom", 4] call _act, _zoom isEqualTo 4],
        [["JOUR", "NUIT", "THERM."] select (missionNamespace getVariable ["COMSPEC_ATAK_DroneVision", 0]), ["vision"] call _act, false],
        [["NADIR", "DEVANT"] select (_aim isEqualTo "NADIR"), ["camAim", ["NADIR", ""] select (_aim isEqualTo "NADIR")] call _act, false],
        ["FERMER", ["cam"] call _act, false]
    ];
    private _bwid = (_bw - _pad * 2) / (count _btns);
    {
        _x params ["_lbl", "_code", "_on"];
        private _b = ["COMSPEC_RscButton", [_pad + _forEachIndex * _bwid, _btnY, _bwid - _pad / 3, _bh2], _lbl] call comspec_atak_native_fnc_pageCtrl;
        _b ctrlSetFontHeight (_fs * 0.85);
        _b ctrlSetBackgroundColor ([[0, 0, 0, 0.55], [0.36, 0.78, 0.42, 0.85]] select _on);
        _b ctrlAddEventHandler ["ButtonClick", _code];
    } forEach _btns;
    _top = _top + _fh;
};

_rows pushBack ["segment", "", [
    ["PILOTAGE", ["tab", "PILOT"] call _act, _tab isEqualTo "PILOT"],
    ["TÂCHES", ["tab", "TASK"] call _act, _tab isEqualTo "TASK"],
    ["PISTES", ["tab", "TRACKS"] call _act, _tab isEqualTo "TRACKS"],
    ["JOURNAL", ["tab", "LOG"] call _act, _tab isEqualTo "LOG"]
]];

// Joueurs alliés à moins de 2 km du drone (suivi et escorte).
private _others = (allPlayers select { alive _x && {_x isNotEqualTo player} && {(side group _x) isEqualTo (side group player)} && {(_x distance _d) < 2000} }) apply { [_x distance _d, _x] };
_others sort true;
private _playerRow = {
    params ["_u", "_dist", "_label", "_on", "_code"];
    private _pic = [_u] call comspec_atak_native_fnc_avatarPath;
    private _veh = ["", format [" · à bord : %1", getText (configOf vehicle _u >> "displayName")]] select ((vehicle _u) isNotEqualTo _u);
    ["person", [_pic, "\z\comspec_atak_native\addons\main\data\app_profile.paa"] select (_pic isEqualTo ""),
        format ["<t font='RobotoCondensedBold'>%1</t><br/><t size='0.8' color='#8a9a93'>%2 · à %3 m du drone%4</t>", name _u, [_u] call comspec_atak_native_fnc_unitGroup, round _dist, _veh],
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
                _rows pushBack ([_u, _dist, ["ESCORTER", "ESCORTÉ"] select _on, _on, ["escort", getPlayerUID _u] call _act] call _playerRow);
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

        _rows pushBack ["section", "Frappe", ["Fixez une charge : le drone doit être à moins de 5 m de vous", format ["Charge fixée : %1", _d getVariable ["COMSPEC_DroneArmedLabel", "charge"]]] select _armed];
        if (!_strikeOk) then {
            _rows pushBack ["text", "<t color='#8a9a93'>Les drones armés sont désactivés sur ce serveur.</t>"];
        } else {
            if (!_armed) then {
                _rows pushBack ["buttons", [["ARMER", ["arm"] call _act, true]]];
                _rows pushBack ["text", "<t size='0.85' color='#8a9a93'>Roquette RPG, charge de démolition ou grenade, prise dans votre inventaire.</t>"];
            } else {
                _rows append [
                    ["buttons", [[["TIR ET OUBLIE : LASER", "CONFIRMER : LASER"] select (["LASER"] call _confirming), ["strike", "LASER"] call _act, true]]],
                    ["edit", "droneGrid", "Grille de la cible (8 ou 10 chiffres)", _s getOrDefault ["droneGridVal", ""]],
                    ["buttons", [[["TIR ET OUBLIE : GRILLE", "CONFIRMER : GRILLE"] select (["GRID"] call _confirming), { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["droneGridVal", ["droneGrid", ""] call comspec_atak_native_fnc_formValue]; ["strike", "GRID"] call comspec_atak_native_fnc_droneAction; }, true]]],
                    ["text", "<t size='0.85' color='#8a9a93'>Une piste vue par la caméra se frappe aussi depuis l'onglet PISTES.</t>"]
                ];
            };
            _rows append [
                ["section", "Recherche et frappe", ["Orbite autour du drone et signale le premier ennemi vu", "Orbite autour du drone et frappe le premier ennemi vu"] select _armed],
                ["segment", "Rayon", ([100, 250, 500] apply { [format ["%1 m", _x], ["radius", _x] call _act, _rad isEqualTo _x] })],
                ["buttons", [[["LANCER LA RECHERCHE", "RECHERCHE EN COURS"] select (_m isEqualTo "HUNT"), ["mode", "hunt"] call _act, _m isNotEqualTo "HUNT"]]]
            ];
        };
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
            ["section", "Suivre un joueur", ["Le drone se place 15 m derrière lui", "Profil CQB : 5 m derrière lui, à hauteur d'homme"] select _cqb]
        ];
        if ((count _others) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun allié à moins de 2 km du drone.</t>"]; };
        {
            _x params ["_dist", "_u"];
            private _on = _m isEqualTo "FOLLOW" && {(_d getVariable ["COMSPEC_DroneFollow", objNull]) isEqualTo _u};
            _rows pushBack ([_u, _dist, ["SUIVRE", "SUIVI"] select _on, _on,
                compile format ["['player', %1] call comspec_atak_native_fnc_droneAction; ['mode', 'player'] call comspec_atak_native_fnc_droneAction;", str getPlayerUID _u]] call _playerRow);
        } forEach (_others select [0, 6]);
        _rows pushBack ["buttons", [["DÉSAPPAIRER", ["unpair"] call _act]]];
    };
};
[_rows, [0, _top, _bw, (_bh - _top) max (_fs * 4)]] call comspec_atak_native_fnc_formRender;
true
