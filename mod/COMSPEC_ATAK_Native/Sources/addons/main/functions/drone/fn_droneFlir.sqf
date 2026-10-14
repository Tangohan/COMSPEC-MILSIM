/*
    Vue drone façon nacelle militaire (FLIR) : image de la caméra du drone (texture de fn_droneAction camBind), réticule,
    coins de cadrage, incrustations (mode de vision, heure, cap, altitude, grille du drone et du point visé, distance)
    et cadres « LOCK » sur les cibles marquées, suivis image par image.
      ["build", [x, y, w, h], style, dans le groupe de page]   style : "FULL" (carte remplacée), "INSET" (incrustation
          sur la carte ou dans la miniature), "FEED" (vue caméra de l'app Drone : cadres seulement, incrustations déjà là) ;
      ["frame"]               mise à jour (gestionnaire par image, retiré seul quand les contrôles disparaissent) ;
      ["project", posASL]     → [u, v] dans l'image (0..1), [] derrière la caméra ;
      ["click", [x, y]]       clic sur l'image (coordonnées écran) : la nacelle vise ce point ; le rayon du clic est gardé
                              (uiNamespace COMSPEC_ATAK_DroneClickRay = [œil ASL, direction]) pour le marquage au clic.
    Projection : caméra de rendu au rapport COMSPEC_ATAK_DroneCamAspect (r2t, 1 comme l'app Vidéo Live), tangente du
    demi-angle vertical = champ de la caméra (camSetFov), horizontal = champ × rapport.
*/
params [["_act", "frame"], ["_arg", []], ["_style", "FULL"], ["_inGroup", false]];
disableSerialization;
private _f = uiNamespace getVariable ["COMSPEC_ATAK_Flir", createHashMap];
private _aspect = missionNamespace getVariable ["COMSPEC_ATAK_DroneCamAspect", 1];

// Repère de la caméra : position, direction, droite, haut, tangente verticale.
private _basis = {
    private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
    private _cam = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
    if (isNull _d || {isNull _cam}) exitWith { [] };
    private _eye = _cam modelToWorldVisualWorld [0, 0, 0];
    private _dir = missionNamespace getVariable ["COMSPEC_ATAK_DroneCamDir", []];
    if (_dir isEqualTo []) then { _dir = vectorDirVisual _cam; };
    _dir = vectorNormalized _dir;
    private _right = _dir vectorCrossProduct [0, 0, 1];
    _right = if ((vectorMagnitude _right) < 0.01) then { vectorNormalized (_dir vectorCrossProduct (vectorUpVisual _d)) } else { vectorNormalized _right };
    private _up = _right vectorCrossProduct _dir;
    [_eye, _dir, _right, _up, 0.7 / (missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1])]
};
private _project = {
    params ["_p", "_b"];
    _b params ["_eye", "_dir", "_right", "_up", "_tv"];
    private _v = _p vectorDiff _eye;
    private _z = _v vectorDotProduct _dir;
    if (_z < 0.5) exitWith { [] };
    [0.5 + ((_v vectorDotProduct _right) / _z) / (2 * _tv * _aspect), 0.5 - ((_v vectorDotProduct _up) / _z) / (2 * _tv)]
};

switch (_act) do {
    case "project": {
        private _b = call _basis;
        if (_b isEqualTo []) exitWith { [] };
        [_arg, _b] call _project
    };
    case "click": {
        _arg params ["_mx", "_my"];
        (_f getOrDefault ["rect", [0, 0, 1, 1]]) params ["_rx", "_ry", "_rw", "_rh"];
        (_f getOrDefault ["offset", [0, 0]]) params ["_ox", "_oy"];
        private _u = (_mx - _rx - _ox) / _rw;
        private _v = (_my - _ry - _oy) / _rh;
        if (_u < 0 || {_u > 1} || {_v < 0} || {_v > 1}) exitWith { false };
        private _b = call _basis;
        if (_b isEqualTo []) exitWith { false };
        _b params ["_eye", "_dir", "_right", "_up", "_tv"];
        private _ray = vectorNormalized ((_dir vectorAdd (_right vectorMultiply ((_u - 0.5) * 2 * _tv * _aspect))) vectorAdd (_up vectorMultiply ((0.5 - _v) * 2 * _tv)));
        private _hit = lineIntersectsSurfaces [_eye, _eye vectorAdd (_ray vectorMultiply 3000), missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull], objNull, true, 1, "VIEW", "FIRE"];
        private _p = if ((count _hit) > 0) then { (_hit select 0) select 0 } else { _eye vectorAdd (_ray vectorMultiply 800) };
        uiNamespace setVariable ["COMSPEC_ATAK_DroneClickRay", [_eye, _ray]];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneCamPt", _p];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneCamTgt", "PT"];
        playSound "ClickSoft";
        true
    };
    case "build": {
        _arg params ["_rx", "_ry", "_w", "_h"];
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        private _fs = _l get "fontSmall";
        private _mini = _l get "mini";
        private _mk = { params ["_class", "_pos", ["_text", ""]]; [_class, _pos, _text, _inGroup] call comspec_atak_native_fnc_pageCtrl };
        private _bar = { params ["_pos", ["_c", [0.92, 0.95, 0.93, 0.9]]]; private _b = ["COMSPEC_RscText", _pos] call _mk; _b ctrlSetBackgroundColor _c; _b };
        private _px = pixelW * ([2, 1] select _mini);
        private _py = pixelH * ([2, 1] select _mini);
        private _f2 = createHashMapFromArray [["rect", [_rx, _ry, _w, _h]], ["style", _style], ["boxes", []], ["texts", []], ["next", 0]];
        private _disp = [] call comspec_atak_native_fnc_display;
        _f2 set ["offset", [[0, 0], [(ctrlPosition (_disp displayCtrl 88531)) select 0, (ctrlPosition (_disp displayCtrl 88531)) select 1]] select _inGroup];
        if (_style isNotEqualTo "FEED") then {
            private _bg = ["COMSPEC_RscText", [_rx, _ry, _w, _h]] call _mk;
            _bg ctrlSetBackgroundColor [0, 0, 0, 1];
            // Caméra relancée (ou recréée) avant que l'image soit posée : sinon image noire au retour sur la vue.
            private _pic = ["COMSPEC_RscPhone", [_rx, _ry, _w, _h], ["camBind"] call comspec_atak_native_fnc_droneAction] call _mk;
            _pic ctrlSetTextColor [1, 1, 1, 1];
            // Pas de flux (liaison perdue, drone perdu ou en veille, PiP coupée) : neige « NO DATA » et sa raison par-dessus
            // l'image ; liaison faible : défauts TNT ; image rebranchée si la caméra a été recréée (fn_droneStatic).
            ["build", [_rx, _ry, _w, _h, _pic], _inGroup] call comspec_atak_native_fnc_droneStatic;
            // Clic sur l'image (téléphone en main) : la nacelle vise le point cliqué.
            private _hit = ["COMSPEC_RscButtonInvisible", [_rx, _ry, _w, _h]] call _mk;
            _hit ctrlAddEventHandler ["MouseButtonDown", { params ["", "_btn", "_mx", "_my"]; if (_btn isEqualTo 0) then { ["click", [_mx, _my]] call comspec_atak_native_fnc_droneFlir; }; }];
            // Coins de cadrage.
            private _cl = (_w min (_h / pixelH * pixelW)) * 0.07;
            private _ch = _cl * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
            private _m = _w * 0.04;
            private _mv = _m * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
            {
                _x params ["_cx", "_cy", "_sx", "_sy"];
                [[[_cx, _cx - _cl] select (_sx < 0), _cy, _cl, _py]] call _bar;
                [[_cx, [_cy, _cy - _ch] select (_sy < 0), _px, _ch]] call _bar;
            } forEach [[_rx + _m, _ry + _mv, 1, 1], [_rx + _w - _m, _ry + _mv, -1, 1], [_rx + _m, _ry + _h - _mv, 1, -1], [_rx + _w - _m, _ry + _h - _mv, -1, -1]];
            // Réticule : croix ouverte au centre.
            private _cx = _rx + _w / 2;
            private _cy = _ry + _h / 2;
            private _rw = _w * 0.045;
            private _rh = _rw * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
            {
                [_x] call _bar;
            } forEach [
                [_cx - _rw * 1.5, _cy - _py / 2, _rw, _py], [_cx + _rw * 0.5, _cy - _py / 2, _rw, _py],
                [_cx - _px / 2, _cy - _rh * 1.5, _px, _rh], [_cx - _px / 2, _cy + _rh * 0.5, _px, _rh]
            ];
            // Incrustations : haut gauche, haut droite, haut centre, gauche, bas gauche, bas centre, bas droite.
            private _ts = _fs * ([0.78, 0.62] select (_mini || {_style isEqualTo "INSET"}));
            private _lh = _ts * 1.25;
            private _texts = [
                [_rx + _m, _ry + _mv, _w * 0.45, _lh * 2, 0], [_rx + _w * 0.55 - _m, _ry + _mv, _w * 0.45, _lh * 2, 1], [_rx + _w * 0.3, _ry + _mv, _w * 0.4, _lh, 2],
                [_rx + _m, _ry + _h * 0.38, _w * 0.3, _lh * 3, 0], [_rx + _m, _ry + _h - _mv - _lh * 2, _w * 0.45, _lh * 2, 0],
                [_rx + _w * 0.25, _ry + _h - _mv - _lh * 2, _w * 0.5, _lh * 2, 2], [_rx + _w * 0.55 - _m, _ry + _h - _mv - _lh * 2, _w * 0.45, _lh * 2, 1]
            ] apply {
                _x params ["_tx", "_ty", "_tw", "_th", "_al"];
                private _t = ["COMSPEC_RscStructuredText", [_tx, _ty, _tw, _th]] call _mk;
                _t ctrlSetBackgroundColor [0, 0, 0, 0];
                _t setVariable ["al", ["left", "right", "center"] select _al];
                _t ctrlSetFontHeight ((_ts) max COMSPEC_ATAK_FontMin);
                _t
            };
            if (_style isEqualTo "INSET") then { { _x ctrlShow (_forEachIndex in [0, 5]); } forEach _texts; };
            _f2 set ["texts", _texts];
            // Liseré de l'incrustation.
            if (_style isEqualTo "INSET") then {
                { [_x, [0.36, 0.78, 0.42, 0.8]] call _bar; } forEach [[_rx, _ry, _w, _py], [_rx, _ry + _h - _py, _w, _py], [_rx, _ry, _px, _h], [_rx + _w - _px, _ry, _px, _h]];
            };
        };
        // Cadres LOCK (8 au plus) et cadre « visé » (cible que marquerait un appui).
        private _boxes = [];
        for "_i" from 0 to 8 do {
            private _edges = [[0, 0, 0, 0], [0, 0, 0, 0], [0, 0, 0, 0], [0, 0, 0, 0]] apply { private _b = [_x, [0.95, 0.25, 0.2, 0.95]] call _bar; _b ctrlShow false; _b };
            private _lbl = ["COMSPEC_RscText", [0, 0, _w * 0.3, _fs * 0.9]] call _mk;
            _lbl ctrlSetFont "EtelkaMonospacePro";
            _lbl ctrlSetFontHeight ((_fs * ([0.7, 0.55] select (_mini || {_style isEqualTo "INSET"}))) max COMSPEC_ATAK_FontMin);
            _lbl ctrlSetTextColor [0.95, 0.25, 0.2, 1];
            _lbl ctrlShow false;
            _boxes pushBack [_edges, _lbl];
        };
        _f2 set ["boxes", _boxes];
        _f2 set ["px", [_px, _py]];
        uiNamespace setVariable ["COMSPEC_ATAK_Flir", _f2];
        if ((missionNamespace getVariable ["COMSPEC_ATAK_FlirPfh", -1]) < 0) then {
            missionNamespace setVariable ["COMSPEC_ATAK_FlirPfh", [{ private _t = diag_tickTime; ["frame"] call comspec_atak_native_fnc_droneFlir; ["cadres caméra (image)", _t] call comspec_atak_native_fnc_perfAcc; }, 0] call CBA_fnc_addPerFrameHandler];
        };
        ["frame"] call comspec_atak_native_fnc_droneFlir;
        true
    };
    case "frame": {
        // Cliché du drone en cours (fn_dronePhoto) : téléphone masqué, aucun cadre ne doit réapparaître sur l'image.
        if (missionNamespace getVariable ["COMSPEC_ATAK_DroneShotBusy", false]) exitWith {};
        private _boxes = _f getOrDefault ["boxes", []];
        if ((count _boxes) isEqualTo 0 || {isNull ((_boxes select 0) select 1)}) exitWith {
            [missionNamespace getVariable ["COMSPEC_ATAK_FlirPfh", -1]] call CBA_fnc_removePerFrameHandler;
            missionNamespace setVariable ["COMSPEC_ATAK_FlirPfh", -1];
            uiNamespace setVariable ["COMSPEC_ATAK_Flir", createHashMap];
        };
        (_f get "rect") params ["_rx", "_ry", "_w", "_h"];
        (_f get "px") params ["_px", "_py"];
        private _b = call _basis;
        private _used = 0;
        private _show = {
            params ["_box", "_u", "_v", "_hu", "_hv", "_c", "_txt"];
            _box params ["_edges", "_lbl"];
            private _bx = _rx + (_u - _hu / 2) * _w;
            private _by = _ry + (_v - _hv / 2) * _h;
            private _bw = (_hu * _w) max (_px * 6);
            private _bh = (_hv * _h) max (_py * 6);
            {
                _x ctrlSetPosition (switch (_forEachIndex) do {
                    case 0: { [_bx, _by, _bw, _py] };
                    case 1: { [_bx, _by + _bh - _py, _bw, _py] };
                    case 2: { [_bx, _by, _px, _bh] };
                    default { [_bx + _bw - _px, _by, _px, _bh] };
                });
                _x ctrlSetBackgroundColor _c;
                _x ctrlShow true;
                _x ctrlCommit 0;
            } forEach _edges;
            _lbl ctrlSetPosition [_bx, _by - (ctrlPosition _lbl select 3), (_w * 0.4), ctrlPosition _lbl select 3];
            // Texte posé seulement s'il change (traduction et rendu à chaque image sinon, pour chaque cadre).
            if ((_lbl getVariable ["txt", ""]) isNotEqualTo _txt) then {
                _lbl setVariable ["txt", _txt];
                _lbl ctrlSetText ((_txt) call comspec_atak_native_fnc_tr);
            };
            _lbl ctrlSetTextColor _c;
            _lbl ctrlShow true;
            _lbl ctrlCommit 0;
        };
        if (_b isNotEqualTo []) then {
            private _eye = _b select 0;
            // Taille du cadre : hauteur de l'objet projetée (1,9 m pour un homme, boîte englobante pour un véhicule).
            private _frameOf = {
                params ["_o", "_p"];
                private _uv = [_p, _b] call _project;
                if (_uv isEqualTo []) exitWith { [] };
                private _size = if (_o isKindOf "CAManBase") then { [0.7, 1.9] } else {
                    private _bb = boundingBoxReal _o;
                    [((_bb select 1) select 0) - ((_bb select 0) select 0), ((_bb select 1) select 2) - ((_bb select 0) select 2)]
                };
                private _top = [_p vectorAdd [0, 0, _size select 1], _b] call _project;
                if (_top isEqualTo []) exitWith { [] };
                private _hv = abs ((_top select 1) - (_uv select 1));
                // Même échelle en pixels sur les deux axes : largeur = hauteur × rapport de l'objet, ramenée à la largeur de l'image.
                private _hu = _hv * ((_size select 0) / ((_size select 1) max 0.1)) * (_h / pixelH) / (_w / pixelW);
                [(_uv select 0), (_uv select 1) - _hv / 2, _hu, _hv]
            };
            // Liste des cibles marquées relue quatre fois par seconde, pas à chaque image (elle est triée à chaque lecture) ;
            // les entrées sont partagées : état et position suivent quand même à chaque image.
            if (diag_tickTime >= (_f getOrDefault ["nextList", 0])) then {
                _f set ["nextList", diag_tickTime + 0.25];
                _f set ["locks", ["list"] call comspec_atak_native_fnc_lockAction];
            };
            {
                _x params ["_key", "_e"];
                if (_used >= 8) exitWith {};
                private _o = _e get "obj";
                private _state = _e get "state";
                if (_state isEqualTo "LOST" && {!(_e get "ghost")}) then { continue };
                private _live = _state isEqualTo "TRACK" && {!isNull _o};
                private _p = [_e get "pos", (getPosASLVisual _o) vectorAdd [0, 0, 0]] select _live;
                private _fr = [[objNull, _o] select _live, _p] call _frameOf;
                if (_fr isEqualTo [] || {(_fr select 0) < -0.05} || {(_fr select 0) > 1.05} || {(_fr select 1) < -0.05} || {(_fr select 1) > 1.05}) then { continue };
                private _c = switch (_state) do { case "TRACK": { [0.95, 0.25, 0.2, 0.95] }; case "DOWN": { [0.36, 0.78, 0.42, 0.9] }; default { [0.7, 0.72, 0.7, 0.6] }; };
                [_boxes select _used, _fr select 0, _fr select 1, _fr select 2, _fr select 3, _c,
                    format ["LOCK_%1 %2m%3", [_e get "num", 3] call CBA_fnc_formatNumber, round (_eye distance _p), ["", " PERDU", " NEUTR."] select ((["TRACK", "LOST", "DOWN"] find _state) max 0)]] call _show;
                _used = _used + 1;
            } forEach (_f getOrDefault ["locks", []]);
            // Cible sous le réticule (pas encore marquée) : cadre jaune, relevé quatre fois par seconde.
            if (diag_tickTime >= (_f getOrDefault ["nextAim", 0])) then {
                _f set ["nextAim", diag_tickTime + 0.25];
                _f set ["aimed", ["aimed", "DRONE"] call comspec_atak_native_fnc_lockAction];
            };
            private _a = _f getOrDefault ["aimed", objNull];
            if (!isNull _a && {alive _a} && {!((hashValue _a) in (missionNamespace getVariable ["COMSPEC_ATAK_Locks", createHashMap]))}) then {
                private _fr = [_a, getPosASLVisual _a] call _frameOf;
                if (_fr isNotEqualTo []) then {
                    [_boxes select 8, _fr select 0, _fr select 1, _fr select 2, _fr select 3, [0.98, 0.82, 0.2, 0.95], format ["VISÉ %1m", round (_eye distance _a)]] call _show;
                    _used = -1;
                };
            };
        };
        // Cadres inutilisés masqués (le neuvième est le cadre « visé »).
        {
            private _isAim = _forEachIndex isEqualTo 8;
            if ((_isAim && {_used isNotEqualTo -1}) || {!_isAim && {_forEachIndex >= (_used max 0)}}) then {
                // Déjà masqués : rien à refaire (45 contrôles touchés à chaque image sinon).
                if (ctrlShown (_x select 1)) then {
                    { _x ctrlShow false } forEach (_x select 0);
                    (_x select 1) ctrlShow false;
                };
            };
        } forEach _boxes;
        // Incrustations : quatre fois par seconde.
        private _texts = _f getOrDefault ["texts", []];
        if ((count _texts) isEqualTo 0 || {diag_tickTime < (_f getOrDefault ["next", 0])}) exitWith {};
        _f set ["next", diag_tickTime + 0.25];
        private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
        if (isNull _d) exitWith {};
        private _ln = ["link"] call comspec_atak_native_fnc_droneAction;
        private _vis = ["TV", "NV", "WHT"] select (missionNamespace getVariable ["COMSPEC_ATAK_DroneVision", 0]);
        private _zoom = missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1];
        private _tgt = "";
        if (_b isNotEqualTo []) then {
            private _hit = lineIntersectsSurfaces [_b select 0, (_b select 0) vectorAdd ((_b select 1) vectorMultiply 4000), _d, objNull, true, 1, "VIEW", "FIRE"];
            if ((count _hit) > 0) then {
                private _p = (_hit select 0) select 0;
                _tgt = format ["TGT %1<br/>RNG %2 m · EL %3 m", [_p, 10] call comspec_atak_native_fnc_gridRef, round ((_b select 0) distance _p), round (_p select 2)];
            } else { _tgt = "TGT ---<br/>RNG ---"; };
        };
        (date) params ["_yy", "_mo", "_dd"];
        private _lines = [
            format ["%1 FLIR · %2 · x%3<br/>%4", toUpper ([_d, [], "NAME"] call comspec_atak_native_fnc_droneOsd), _vis, _zoom, ["NFOV", "WFOV"] select (_zoom < 2)],
            format ["%1 %2 %3<br/>%4", [_dd, 2] call CBA_fnc_formatNumber, ["JAN", "FEV", "MAR", "AVR", "MAI", "JUN", "JUL", "AOU", "SEP", "OCT", "NOV", "DEC"] select ((_mo - 1) max 0), _yy, [dayTime, "HH:MM:SS"] call BIS_fnc_timeToString],
            format ["CAP %1°", [round (getDirVisual _d) mod 360, 3] call CBA_fnc_formatNumber],
            format ["ALT %1 m<br/>VIT %2 km/h<br/>BAT %3 %%", round ((getPosATL _d) select 2), round speed _d, round (100 * fuel _d)],
            format ["AV %1<br/>%2", [getPosASL _d, 10] call comspec_atak_native_fnc_gridRef, toUpper (_d getVariable ["COMSPEC_DroneMode", "HOVER"])],
            _tgt,
            format ["LOCK %1<br/>%2", count (missionNamespace getVariable ["COMSPEC_ATAK_Locks", createHashMap]), ["<t color='#e5483a'>HORS LIAISON</t>", format ["LIAISON %1/4", _ln select 0]] select (_ln select 2)]
        ];
        {
            if (ctrlShown _x && {(_x getVariable ["txt", ""]) isNotEqualTo (_lines select _forEachIndex)}) then {
                _x setVariable ["txt", _lines select _forEachIndex];
                _x ctrlSetStructuredText parseText ((format ["<t font='EtelkaMonospacePro' align='%1' shadow='2' shadowColor='#000000' color='#e8f0ea'>%2</t>", _x getVariable ["al", "left"], _lines select _forEachIndex]) call comspec_atak_native_fnc_tr);
            };
        } forEach _texts;
    };
    default { false };
};
