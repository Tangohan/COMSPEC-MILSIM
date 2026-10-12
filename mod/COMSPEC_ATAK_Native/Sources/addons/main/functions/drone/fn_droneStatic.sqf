/*
    Neige de télévision « NO DATA » posée sur l'image de la caméra (app Drone, vue drone de la carte) quand il n'y a pas
    de flux, et défauts de réception façon TNT (DVB) quand la liaison faiblit.
      ["build", [x, y, w, h, image], dans le groupe de page]   pose le fond gris, quatre couches de bruit, l'étiquette et
                   les contrôles des défauts (masqués) ; image = le RscPicture du flux (texture r2t de camBind) ;
      ["reason"]   → "" si le flux passe, sinon la raison affichée sous NO DATA ;
      ["frame"]    gestionnaire par image (environ 15 fois par seconde) : montre ou masque, anime la neige et les défauts ;
                   retiré seul quand les contrôles disparaissent.
    NO DATA seulement pour une vraie raison : drone perdu ou en veille, liaison perdue, PiP coupée, caméra casque hors
    ligne. Caméra du drone absente ou image posée sur une ancienne cible de rendu (caméra libérée puis recréée sans que
    la page soit redessinée) : la caméra est relancée et l'image rebranchée sur place (camBind), sans neige.
    Neige : textures procédurales perlinNoise 1024 x 512 (grain fin, aucun fichier), une seule couche visible à la fois,
    tournée d'un demi-tour une fois sur deux ; sous elle un fond gris, pour que l'image reste grise même sans texture.
    Défauts TNT (qualité de liaison q de ["link"] de fn_droneAction, 0 à 1 ; rien au-dessus de 0,6, de plus en plus
    fréquents en dessous) : par salves de 0,15 à 0,8 s, image figée (COMSPEC_ATAK_DroneFeedFreeze, la caméra cesse de
    bouger), macroblocs gris, blocs « étalés » (morceau d'image agrandi), bandes horizontales décalées (déchirure),
    blocs de couleur brefs ; liaison presque perdue : éclairs de neige. Puis NO DATA quand la liaison tombe.
    État : uiNamespace COMSPEC_ATAK_DroneStatic ([HashMap par image...]), COMSPEC_ATAK_DroneStaticWhy / Q (relus deux fois
    par seconde), COMSPEC_ATAK_DroneGlitch ([fin de salve, gravité]) ; missionNamespace COMSPEC_ATAK_DroneStaticPfh.
*/
params [["_act", "frame"], ["_arg", []], ["_inGroup", true]];
disableSerialization;
switch (_act) do {
    case "reason": {
        private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
        private _src = missionNamespace getVariable ["COMSPEC_ATAK_DroneFeedSrc", "DRONE"];
        uiNamespace setVariable ["COMSPEC_ATAK_DroneStaticQ", 1];
        if !(isPiPEnabled) exitWith { "IMAGE DANS L'IMAGE (PIP) COUPÉE : OPTIONS > VIDÉO" };
        if (_src isEqualTo "HELMET") exitWith {
            private _u = missionNamespace getVariable ["COMSPEC_ATAK_DroneFeedUnit", objNull];
            if (!alive _u) exitWith { "CAMÉRA CASQUE HORS LIGNE" };
            private _dist = _u distance player;
            uiNamespace setVariable ["COMSPEC_ATAK_DroneStaticQ", (1 - _dist / 3000) max 0];
            ["", "PORTEUR TROP LOIN"] select (_dist > 3000)
        };
        if (_src isEqualTo "PHONE") exitWith { "" };
        if (isNull _d || {!alive _d}) exitWith { "DRONE PERDU" };
        if ((_d getVariable ["COMSPEC_DroneMode", ""]) isEqualTo "STANDBY") exitWith { "DRONE EN VEILLE : CAMÉRA COUPÉE" };
        (["link"] call comspec_atak_native_fnc_droneAction) params ["", "_dist", "_ok", ["_q", 1]];
        if (!_ok) exitWith { format ["LIAISON PERDUE À %1 M", _dist] };
        uiNamespace setVariable ["COMSPEC_ATAK_DroneStaticQ", _q];
        ""
    };
    case "build": {
        _arg params ["_px", "_py", "_pw", "_ph", ["_pic", controlNull]];
        private _fs = ([] call comspec_atak_native_fnc_layoutGet) get "fontSmall";
        private _disp = [] call comspec_atak_native_fnc_display;
        private _mk = { params ["_class", "_pos", ["_text", ""]]; [_class, _pos, _text, _inGroup] call comspec_atak_native_fnc_pageCtrl };
        private _hide = { { _x ctrlShow false; _x ctrlEnable false; } forEach _this; };
        // Défauts TNT sous la neige : macroblocs, blocs étalés et bandes déchirées (groupe sans barre de défilement qui
        // découpe une copie de l'image, décalée ou agrandie).
        private _clip = {
            params ["_zoom"];
            private _g = ["RscControlsGroupNoScrollbars", [_px, _py, _pw / 8, _ph / 8]] call _mk;
            private _p = _disp ctrlCreate ["RscPicture", -1, _g];
            _p ctrlSetPosition [0, 0, _pw * _zoom, _ph * _zoom];
            _p ctrlCommit 0;
            [_g, _p] call _hide;
            [_g, _p]
        };
        private _bands = [1, 1, 1] apply { [_x] call _clip };
        private _smears = [5, 7, 9] apply { [_x] call _clip };
        private _blocks = [];
        for "_i" from 1 to 9 do {
            private _b = ["COMSPEC_RscText", [_px, _py, _pw / 16, _ph / 9]] call _mk;
            [_b] call _hide;
            _blocks pushBack _b;
        };
        private _bg = ["COMSPEC_RscText", [_px, _py, _pw, _ph]] call _mk;
        _bg ctrlSetBackgroundColor [0.2, 0.2, 0.2, 1];
        [_bg] call _hide;
        // Grain fin (neige de télévision) : texture 1024 x 512, motif de 2 à 3 texels.
        private _layers = ["perlinNoise(2,2,0,1)", "perlinNoise(3,2,0,1)", "perlinNoise(2,3,0,1)", "perlinNoise(3,3,0,1)"] apply {
            private _p = ["RscPicture", [_px, _py, _pw, _ph], "#(ai,1024,512,1)" + _x] call _mk;
            _p ctrlSetTextColor [0.85, 0.85, 0.85, 1];
            [_p] call _hide;
            _p
        };
        // Étiquette : hauteur mesurée sur son texte (NO DATA et la raison toujours visibles), centrée sur l'image.
        private _lb = ["COMSPEC_RscStructuredText", [_px, _py + _ph * 0.4, _pw, _ph * 0.3]] call _mk;
        _lb ctrlSetBackgroundColor [0, 0, 0, 0.45];
        [_lb] call _hide;
        private _all = (uiNamespace getVariable ["COMSPEC_ATAK_DroneStatic", []]) select { !isNull (_x get "bg") };
        _all pushBack (createHashMapFromArray [["bg", _bg], ["layers", _layers], ["lb", _lb], ["last", "§"], ["pic", _pic],
            ["tex", [ctrlText _pic, ""] select (isNull _pic)], ["rect", [_px, _py, _pw, _ph]], ["fs", _fs], ["blocks", _blocks], ["bands", _bands], ["smears", _smears], ["fx", false]]);
        uiNamespace setVariable ["COMSPEC_ATAK_DroneStatic", _all];
        if ((missionNamespace getVariable ["COMSPEC_ATAK_DroneStaticPfh", -1]) < 0) then {
            missionNamespace setVariable ["COMSPEC_ATAK_DroneStaticPfh", [{ ["frame"] call comspec_atak_native_fnc_droneStatic; }, 0.066] call CBA_fnc_addPerFrameHandler];
        };
        // Raison relue tout de suite (une raison gardée d'un autre écran ne doit pas s'afficher).
        uiNamespace setVariable ["COMSPEC_ATAK_DroneStaticNext", 0];
        ["frame"] call comspec_atak_native_fnc_droneStatic;
    };
    case "frame": {
        private _all = (uiNamespace getVariable ["COMSPEC_ATAK_DroneStatic", []]) select { !isNull (_x get "bg") };
        uiNamespace setVariable ["COMSPEC_ATAK_DroneStatic", _all];
        if ((count _all) isEqualTo 0) exitWith {
            [missionNamespace getVariable ["COMSPEC_ATAK_DroneStaticPfh", -1]] call CBA_fnc_removePerFrameHandler;
            missionNamespace setVariable ["COMSPEC_ATAK_DroneStaticPfh", -1];
            missionNamespace setVariable ["COMSPEC_ATAK_DroneFeedFreeze", 0];
        };
        // Raison relue deux fois par seconde (calcul de liaison), neige et défauts animés à chaque passage.
        if (diag_tickTime >= (uiNamespace getVariable ["COMSPEC_ATAK_DroneStaticNext", 0])) then {
            uiNamespace setVariable ["COMSPEC_ATAK_DroneStaticNext", diag_tickTime + 0.5];
            private _why = ["reason"] call comspec_atak_native_fnc_droneStatic;
            // Flux attendu mais caméra absente, ou image restée sur une ancienne cible de rendu : caméra relancée et image
            // rebranchée sur la texture du moment (pas de neige pour ça : ce n'est pas une perte de flux).
            if (_why isEqualTo "") then {
                private _cam = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
                private _r2t = format ["(%1,", missionNamespace getVariable ["COMSPEC_ATAK_DroneR2T", ""]];
                private _stale = _all select { !isNull (_x get "pic") && {((_x get "tex") find _r2t) < 0} };
                if (isNull _cam || {(count _stale) > 0}) then {
                    private _tex = ["camBind"] call comspec_atak_native_fnc_droneAction;
                    if (_tex isEqualTo "") exitWith { _why = "CAMÉRA DU DRONE INDISPONIBLE"; };
                    { if (!isNull (_x get "pic") && {(_x get "tex") isNotEqualTo _tex}) then { (_x get "pic") ctrlSetText _tex; _x set ["tex", _tex]; }; } forEach _all; // i18n:brut
                };
            };
            uiNamespace setVariable ["COMSPEC_ATAK_DroneStaticWhy", _why];
        };
        private _why = uiNamespace getVariable ["COMSPEC_ATAK_DroneStaticWhy", ""];
        private _busy = missionNamespace getVariable ["COMSPEC_ATAK_DroneShotBusy", false];
        private _on = _why isNotEqualTo "" && {!_busy};
        // Défauts TNT : gravité 0 (liaison bonne) à 1 (presque perdue) ; salves aléatoires, plus longues et plus
        // fréquentes quand la liaison faiblit.
        private _sev = (((0.6 - (uiNamespace getVariable ["COMSPEC_ATAK_DroneStaticQ", 1])) / 0.6) max 0) min 1;
        (uiNamespace getVariable ["COMSPEC_ATAK_DroneGlitch", [0, 0]]) params ["_gEnd", "_gSev"];
        private _now = diag_tickTime;
        if (!_on && {!_busy} && {_sev > 0} && {_now >= _gEnd} && {(random 1) < (0.012 + 0.16 * _sev * _sev)}) then {
            _gEnd = _now + 0.15 + random (0.25 + 0.4 * _sev);
            _gSev = _sev;
            uiNamespace setVariable ["COMSPEC_ATAK_DroneGlitch", [_gEnd, _gSev]];
            // Image figée : la caméra ne bouge plus le temps de la salve (et un peu plus quand c'est grave).
            if ((random 1) < (0.2 + 0.5 * _sev)) then { missionNamespace setVariable ["COMSPEC_ATAK_DroneFeedFreeze", _gEnd + random (1.2 * _sev)]; };
        };
        private _glitch = !_on && {!_busy} && {_now < _gEnd};
        // Éclair de neige (liaison presque perdue) : une ou deux images.
        private _flash = _glitch && {_gSev > 0.75} && {(random 1) < 0.18};
        private _pick = floor random 4;
        private _flip = (random 1) < 0.5;
        {
            private _e = _x;
            private _bg = _e get "bg";
            private _layers = _e get "layers";
            private _lb = _e get "lb";
            private _show = _on || _flash;
            if (_show isNotEqualTo (ctrlShown _bg)) then {
                _bg ctrlShow _show;
                if (!_show) then { { _x ctrlShow false; } forEach _layers; };
            };
            if (_on isNotEqualTo (ctrlShown _lb)) then { _lb ctrlShow _on; };
            if (_show) then {
                {
                    _x ctrlShow (_forEachIndex isEqualTo _pick);
                    if (_forEachIndex isEqualTo _pick) then {
                        _x ctrlSetAngle [[0, 180] select _flip, 0.5, 0.5];
                        _x ctrlSetTextColor [1, 1, 1, 0.75 + random 0.25];
                    };
                } forEach _layers;
            };
            // Étiquette : NO DATA, puis la raison (taille de police calée sur celle du téléphone, hauteur mesurée).
            if (_on && {(_e get "last") isNotEqualTo _why}) then {
                _e set ["last", _why];
                (_e get "rect") params ["_px", "_py", "_pw", "_ph"];
                private _k = (_e get "fs") / (0.024 * safeZoneH);
                _lb ctrlSetPosition [_px, _py + _ph * 0.4, _pw, _ph * 0.5];
                _lb ctrlCommit 0;
                _lb ctrlSetStructuredText parseText ((format ["<t align='center' font='EtelkaMonospacePro' shadow='2' shadowColor='#000000' color='#f2f2f2'><t size='%2'>NO DATA</t><br/><t size='%3' color='#ffd28a'>%1</t></t>", _why, 1.5 * _k, 0.8 * _k]) call comspec_atak_native_fnc_tr);
                private _th = (ctrlTextHeight _lb) min _ph;
                _lb ctrlSetPosition [_px, _py + (_ph - _th) / 2, _pw, _th];
                _lb ctrlCommit 0;
            };
            // Défauts de réception (salve en cours) : redessinés à chaque passage, masqués sinon.
            if (_glitch) then {
                _e set ["fx", true];
                (_e get "rect") params ["_px", "_py", "_pw", "_ph"];
                private _tex = _e get "tex";
                private _cw = _pw / 16;
                private _ch = _ph / 9;
                // Macroblocs : gris figés ou blocs de couleur (vert, magenta) alignés sur la grille 16 x 9.
                private _nb = floor (random (2 + 7 * _gSev));
                {
                    if (_forEachIndex < _nb) then {
                        private _bw = 1 + floor random (1 + 3 * _gSev);
                        private _bh = 1 + floor random 2;
                        private _cx = floor random (17 - _bw);
                        private _cy = floor random (10 - _bh);
                        _x ctrlSetPosition [_px + _cx * _cw, _py + _cy * _ch, _bw * _cw, _bh * _ch];
                        _x ctrlCommit 0;
                        private _g = 0.25 + random 0.5;
                        _x ctrlSetBackgroundColor (selectRandomWeighted [[_g, _g, _g, 0.9], 0.7, [0.15, 0.55 + random 0.3, 0.2, 0.85], 0.15 * _gSev, [0.6 + random 0.3, 0.12, 0.55, 0.85], 0.15 * _gSev]);
                        _x ctrlShow true;
                    } else { _x ctrlShow false; };
                } forEach (_e get "blocks");
                // Blocs étalés : un morceau d'image fortement agrandi (bouillie de pixels).
                private _ns = [0, floor random (1 + 3 * _gSev)] select (_tex isNotEqualTo "");
                {
                    _x params ["_g", "_p"];
                    if (_forEachIndex < _ns) then {
                        private _bw = 2 + floor random 3;
                        private _bh = 1 + floor random 2;
                        private _cx = floor random (17 - _bw);
                        private _cy = floor random (10 - _bh);
                        _g ctrlSetPosition [_px + _cx * _cw, _py + _cy * _ch, _bw * _cw, _bh * _ch];
                        _g ctrlCommit 0;
                        (ctrlPosition _p) params ["", "", "_zw", "_zh"];
                        if ((ctrlText _p) isNotEqualTo _tex) then { _p ctrlSetText _tex; }; // i18n:brut
                        _p ctrlSetPosition [-(_cx * _cw) * (_zw / _pw) - random (_bw * _cw), -(_cy * _ch) * (_zh / _ph) - random (_bh * _ch), _zw, _zh];
                        _p ctrlCommit 0;
                        _g ctrlShow true;
                        _p ctrlShow true;
                    } else { _g ctrlShow false; };
                } forEach (_e get "smears");
                // Bandes horizontales décalées (déchirure de l'image).
                private _nt = [0, floor random (1 + 3 * _gSev)] select (_tex isNotEqualTo "");
                {
                    _x params ["_g", "_p"];
                    if (_forEachIndex < _nt) then {
                        private _by = random (_ph * 0.92);
                        private _bh = (_ph * (0.02 + random (0.06 + 0.1 * _gSev))) min (_ph - _by);
                        _g ctrlSetPosition [_px, _py + _by, _pw, _bh];
                        _g ctrlCommit 0;
                        if ((ctrlText _p) isNotEqualTo _tex) then { _p ctrlSetText _tex; }; // i18n:brut
                        _p ctrlSetPosition [(random 2 - 1) * _pw * (0.04 + 0.2 * _gSev), -_by, _pw, _ph];
                        _p ctrlSetTextColor ([[1, 1, 1, 1], [0.8, 1, 0.8, 1], [1, 0.85, 1, 1]] select (floor random 3));
                        _p ctrlCommit 0;
                        _g ctrlShow true;
                        _p ctrlShow true;
                    } else { _g ctrlShow false; };
                } forEach (_e get "bands");
            } else {
                if (_e get "fx") then {
                    _e set ["fx", false];
                    { _x ctrlShow false; } forEach (_e get "blocks");
                    { (_x select 0) ctrlShow false; } forEach ((_e get "smears") + (_e get "bands"));
                };
            };
        } forEach _all;
    };
};

// Pas de valeur finale : ["reason"] renvoie la raison du switch (un « true » ici affichait NO DATA en permanence).
