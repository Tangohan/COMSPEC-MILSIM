/*
    Rendu d'une caméra dans une image du téléphone (PiP, texture r2t) : contrôle avant d'ouvrir le flux et journal
    étape par étape dans le RPT (lignes [COMSPEC ATAK NATIVE][INFO|WARNING][CAMERA]) pour savoir pourquoi une vue reste noire.
    Retour de TTRD (13/10, 12:35 en jeu, plein jour) : vue caméra de l'app Drone ET viseur de l'app Photo noirs, sans
    message, alors que la PiP est déclarée active (sinon NO DATA / VISEUR INDISPONIBLE s'affichent). Cause commune aux
    deux apps, pas encore confirmée en jeu ; ce journal dit à quelle étape le rendu échoue.
      ["check", source]       → "" si le rendu peut marcher, sinon la raison à afficher (PiP coupée dans Options > Vidéo).
                                Relève aussi une distance de vue PiP trop courte (un autre mod ou la mission a pu la
                                baisser : la caméra ne dessine alors rien) ;
      ["watch", [source, caméra, nom r2t, texture, image]]   journal de l'ouverture du flux (étapes 1 à 5), puis
                                l'état est relevé à 2 s (caméra, image, texture) ;
      ["state", [source, caméra, porteur]]   ligne d'état RPT d'un flux à l'écran (drone, toutes les 15 s) ;
      ["rebind", [image, texture]]   pose de nouveau la texture sur l'image (si elle est toujours là).
      ["test"]                TEST VIDÉO (apps Drone et Photo) : une grille de 9 images, lettres A à I, pendant 30 s, dans une
                                fenêtre tracée dans l'écran du téléphone (rien par-dessus l'écran du jeu). A reprend le premier test (qui s'affiche chez TTRD,
                                13/10 21:20), chaque autre lettre ajoute une seule différence avec les vues Photo et Drone
                                (noires) : texture 1024, image 16/9, caméra déplacée comme l'app Photo, déplacée par
                                position seule, accrochée comme le drone, posée 30 m plus haut, caméra de test accrochée au
                                drone (J), flux réel du drone (I). Les lettres noires désignent la cause ; le journal note où chaque caméra se trouve.
    source : "DRONE" ou "PHOTO" (préfixe des lignes du journal).
*/
params [["_act", "check"], ["_arg", ""]];
disableSerialization;
private _say = { params ["_lvl", "_msg"]; [_lvl, "CAMERA", _msg] call comspec_atak_native_fnc_log; };
switch (_act) do {
    case "test": {
        private _old = missionNamespace getVariable ["COMSPEC_ATAK_PipTest", []];
        if ((count _old) > 0) exitWith { ["testEnd"] call comspec_atak_native_fnc_pipCheck; };
        private _phone = [] call comspec_atak_native_fnc_display;
        // Test tracé dans l'écran du téléphone seul, jamais par-dessus l'écran du jeu.
        if (isNull _phone) exitWith {};
        private _parent = _phone;
        // Pose de départ : yeux du joueur, direction de sa vue.
        private _eye = eyePos player;
        private _dir = getCameraViewDirection player;
        private _flat = vectorNormalized [_dir select 0, _dir select 1, 0];
        if ((vectorMagnitude _flat) < 0.01) then { _flat = [0, 1, 0]; };
        missionNamespace setVariable ["COMSPEC_ATAK_PipTestPose", [_eye, _flat]];
        // Variantes : [lettre, ce qui change par rapport à A, texture, rapport, mode]. Chaque différence entre le premier
        // TEST VIDÉO (qui s'affiche) et les vues de l'app Photo et de l'app Drone (noires) a sa lettre.
        // Le flux réel du drone (I) passe en premier : au test du 14/10, les huit caméras de script A à H s'affichaient
        // toutes et seule I restait vide. Créée avant les autres, elle dit si c'est la caméra du drone elle-même ou le
        // nombre de rendus simultanés (I était la 9e cible r2t ouverte).
        private _variants = [
            ["I", "flux réel du drone", 0, 1.7778, "drone"],
            ["A", "référence (premier test)", 512, 1, "static"],
            ["B", "texture 1024", 1024, 1, "static"],
            ["C", "image 16/9", 512, 1.7778, "static"],
            ["D", "suit la vue comme l'app Photo", 512, 1, "photo"],
            ["E", "suit la vue (position seule)", 512, 1, "pos"],
            ["F", "accrochée comme le drone", 512, 1, "attach"],
            ["G", "30 m au-dessus, vers le sol", 512, 1, "high"],
            ["J", "caméra de test accrochée au drone", 512, 1, "dronetest"]
        ];
        private _top = _parent createDisplay "RscDisplayEmpty";
        if (isNull _top) exitWith { ["WARNING", "TEST VIDÉO : fenêtre de test impossible à ouvrir"] call _say; };
        (([] call comspec_atak_native_fnc_layoutGet) get "device") params ["_rx", "_ry", "_rw", "_rh"];
        private _gx = 0.02 * _rw;
        private _gy = 0.02 * _rh;
        private _cellW = (_rw - 4 * _gx) / 3;
        private _cellH = (_rh - 0.04 * _rh - 4 * _gy) / 3;
        private _lbH = 0.22 * _cellH;
        private _cams = [];
        private _ctrls = [];
        {
            _x params ["_k", "_what", "_size", "_aspect", "_mode"];
            private _col = _forEachIndex mod 3;
            private _row = floor (_forEachIndex / 3);
            private _cx = _rx + _gx + _col * (_cellW + _gx);
            private _cy = _ry + 0.04 * _rh + _gy + _row * (_cellH + _gy);
            private _name = "comspecpiptest" + toLower _k;
            private _tex = "";
            private _cam = objNull;
            private _note = "";
            if (_mode isEqualTo "drone") then {
                private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
                if (isNull _d || {!alive _d}) then { _note = "aucun drone appairé"; } else {
                    _tex = ["camBind"] call comspec_atak_native_fnc_droneAction;
                    _cam = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
                    if (_tex isEqualTo "") then { _note = "caméra du drone indisponible"; };
                };
            } else {
                private _pos = _eye vectorAdd (_flat vectorMultiply 0.5);
                private _cd = _flat;
                private _cu = [0, 0, 1];
                if (_mode isEqualTo "high") then { _pos = _eye vectorAdd [0, 0, 30]; _cd = [0, 0, -1]; _cu = _flat; };
                _cam = "camera" camCreate [0, 0, 0];
                private _dt = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
                if (_mode isEqualTo "dronetest" && {isNull _dt || {!alive _dt}}) then { _mode = "static"; _note = "aucun drone appairé : vue fixe"; };
                // J : caméra de script toute simple accrochée au drone, sous lui, vers l'avant et le bas. Si J s'affiche et
                // I reste noire, c'est notre caméra du flux ; si J reste noire aussi, c'est le drone lui-même.
                if (_mode isEqualTo "dronetest") then {
                    (boundingBoxReal _dt) params ["_b0", "_b1"];
                    private _jo = [0, (_b1 select 1) + 0.3, (_b0 select 2) - 0.3];
                    _pos = _dt modelToWorldVisualWorld _jo;
                    _cam setPosASL _pos;
                    _cam cameraEffect ["Internal", "Back", _name];
                    _cam camSetFov 0.75;
                    _cam camCommit 0;
                    _cam attachTo [_dt, _jo];
                    _cam setVectorDirAndUp [[0, 0.8, -0.6], [0, 0.6, 0.8]];
                } else {
                if (_mode isEqualTo "attach") then {
                    _cam setPosASL _pos;
                    _cam cameraEffect ["Internal", "Back", _name];
                    _cam camSetFov 0.75;
                    _cam camCommit 0;
                    _cam attachTo [player, [0, 0.5, 1.7]];
                    _cam setVectorDirAndUp [player vectorWorldToModelVisual _flat, [0, 0, 1]];
                } else {
                    _cam setPosASL _pos;
                    _cam setVectorDirAndUp [_cd, _cu];
                    _cam cameraEffect ["Internal", "Back", _name];
                    _cam camSetFov 0.75;
                    _cam camCommit 0;
                };
                };
                _name setPiPEffect [[0, 2] select (_mode isEqualTo "thermal")];
                [_cam, "pipTest", [_k, _mode, _pos], true] call comspec_atak_native_fnc_camVar;
                _tex = format ["#(argb,%1,%1,1)r2t(%2,%3)", _size, _name, _aspect];
            };
            // Image aux proportions de la texture (pixels carrés), la plus grande qui tient dans la case.
            private _ph = _cellH - _lbH;
            private _pw = (_ph / pixelH) * _aspect * pixelW;
            if (_pw > _cellW) then { _pw = _cellW; _ph = (_pw / pixelW) / _aspect * pixelH; };
            private _bg = _top ctrlCreate ["RscText", -1];
            _bg ctrlSetPosition [_cx, _cy, _cellW, _cellH];
            _bg ctrlSetBackgroundColor [0.15, 0.02, 0.02, 1];
            _bg ctrlCommit 0;
            private _pic = _top ctrlCreate ["RscPicture", -1];
            _pic ctrlSetPosition [_cx + (_cellW - _pw) / 2, _cy + _lbH, _pw, _ph];
            if (_tex isNotEqualTo "") then { _pic ctrlSetText _tex; }; // i18n:brut
            _pic ctrlCommit 0;
            private _lb = _top ctrlCreate ["RscStructuredText", -1];
            _lb ctrlSetPosition [_cx, _cy, _cellW, _lbH];
            _lb ctrlSetBackgroundColor [0, 0, 0, 0.85];
            _lb ctrlSetStructuredText parseText (((format ["<t size='1' color='#ffd400' font='PuristaBold'>%1</t> <t size='0.7' color='#ffffff'>%2%3</t>", _k, _what call comspec_atak_native_fnc_tr, ["", " : " + (_note call comspec_atak_native_fnc_tr)] select (_note isNotEqualTo "")])) call comspec_atak_native_fnc_tr);
            _lb ctrlCommit 0;
            _ctrls append [_bg, _pic, _lb];
            if (!isNull _cam && {_mode isNotEqualTo "drone"}) then { _cams pushBack _cam; };
            ["INFO", format ["TEST VIDÉO %1 (%2) : cible %3, texture %4, caméra %5", _k, _what, _name, [_tex, "-"] select (_tex isEqualTo ""), ["absente", "créée"] select (!isNull _cam)]] call _say;
        } forEach _variants;
        // Variantes D, E, F : déplacées à chaque image comme les vraies caméras (D : appel exact de l'app Photo).
        private _pfh = [{
            (missionNamespace getVariable ["COMSPEC_ATAK_PipTestPose", []]) params [["_eye0", []], ["_flat0", [0, 1, 0]]];
            private _eye = eyePos player;
            private _dir = getCameraViewDirection player;
            {
                (([_x, "pipTest", ["", "", []]] call comspec_atak_native_fnc_camVar)) params ["", "_mode"];
                switch (_mode) do {
                    case "photo": {
                        private _p = _eye vectorAdd (_dir vectorMultiply 0.3);
                        private _r = _dir vectorCrossProduct [0, 0, 1];
                        private _u = if ((vectorMagnitude _r) < 0.01) then { [0, 1, 0] } else { vectorNormalized (_r vectorCrossProduct _dir) };
                        _x camSetPos (ASLToAGL _p);
                        _x camSetFov 0.75;
                        _x camCommit 0;
                        _x setPosASL _p;
                        _x setVectorDirAndUp [_dir, _u];
                    };
                    case "pos": {
                        _x setPosASL (_eye vectorAdd (_dir vectorMultiply 0.5));
                        _x setVectorDirAndUp [_dir, [0, 0, 1]];
                    };
                    case "attach": { _x setVectorDirAndUp [player vectorWorldToModelVisual _dir, [0, 0, 1]]; };
                    default {};
                };
            } forEach ((missionNamespace getVariable ["COMSPEC_ATAK_PipTest", []]) param [0, []]);
        }, 0] call CBA_fnc_addPerFrameHandler;
        missionNamespace setVariable ["COMSPEC_ATAK_PipTest", [_cams, _ctrls, _top, _pfh]];
        ["INFO", format ["TEST VIDÉO : PiP %1, distance de vue PiP %2 m, options %3, %4 caméras ouvertes", ["DÉSACTIVÉE", "active"] select isPiPEnabled, round getPiPViewDistance, getVideoOptions, count _cams]] call _say;
        // Relevé à 2 s : où chaque caméra se trouve vraiment (le premier test montrait le joueur de dos au lieu de la vue
        // devant lui : la caméra n'était pas là où le script l'avait posée).
        [{
            {
                (([_x, "pipTest", ["", "", []]] call comspec_atak_native_fnc_camVar)) params ["_k", "_mode", "_want"];
                private _at = getPosASL _x;
                ["INFO", "CAMERA", format ["TEST VIDÉO %1 : caméra à %2 m du joueur, %3 m au-dessus du sol, à %4 m de la position posée au départ, regarde %5",
                    _k, round ((_at distance (eyePos player)) * 10) / 10, round (((_at select 2) - getTerrainHeightASL _at) * 10) / 10,
                    round ((_at distance _want) * 10) / 10, (vectorDir _x) apply { (round (_x * 100)) / 100 }]] call comspec_atak_native_fnc_log;
            } forEach ((missionNamespace getVariable ["COMSPEC_ATAK_PipTest", []]) param [0, []]);
        }, [], 2] call CBA_fnc_waitAndExecute;
        ["INFO", "TEST VIDÉO : notez les lettres qui restent noires (fermeture dans 30 s, Échap ferme plus tôt)", 30, 40] call comspec_atak_native_fnc_notify;
        _top displayAddEventHandler ["Unload", { [{ if ((count (missionNamespace getVariable ["COMSPEC_ATAK_PipTest", []])) > 0) then { ["testEnd"] call comspec_atak_native_fnc_pipCheck; }; }] call CBA_fnc_execNextFrame; }];
        [{ ["testEnd"] call comspec_atak_native_fnc_pipCheck; }, [], 30] call CBA_fnc_waitAndExecute;
        true
    };
    case "testEnd": {
        (missionNamespace getVariable ["COMSPEC_ATAK_PipTest", []]) params [["_cams", []], ["_ctrls", []], ["_top", displayNull], ["_pfh", -1]];
        if ((count (missionNamespace getVariable ["COMSPEC_ATAK_PipTest", []])) isEqualTo 0) exitWith { false };
        missionNamespace setVariable ["COMSPEC_ATAK_PipTest", []];
        if (_pfh >= 0) then { [_pfh] call CBA_fnc_removePerFrameHandler; };
        { ctrlDelete _x; } forEach _ctrls;
        if (!isNull _top) then { _top closeDisplay 2; };
        {
            if (!isNull _x) then {
                private _k = (([_x, "pipTest", ["a"]] call comspec_atak_native_fnc_camVar)) select 0;
                _x cameraEffect ["Terminate", "Back", "comspecpiptest" + toLower _k];
                camDestroy _x;
            };
        } forEach _cams;
        ["INFO", "CAMERA", "TEST VIDÉO terminé : caméras de test fermées"] call comspec_atak_native_fnc_log;
        true
    };
    case "check": {
        private _src = [_arg, "?"] select (_arg isEqualTo "");
        if !(isPiPEnabled) exitWith {
            // Une fois par source et par minute : le flux est redemandé à chaque dessin de page.
            private _k = "COMSPEC_ATAK_PipWarnAt_" + _src;
            if (diag_tickTime >= (missionNamespace getVariable [_k, 0])) then {
                missionNamespace setVariable [_k, diag_tickTime + 60];
                ["WARNING", format ["%1 : vue noire, étape 1 en échec : « Qualité PiP » est sur Désactivé dans Options > Vidéo d'Arma (isPiPEnabled = false). Aucune caméra ne peut s'afficher dans le téléphone.", _src]] call _say;
                ["WARNING", "Vidéo du téléphone coupée : réglez « Qualité PiP » (Options > Vidéo > Qualité) sur Basse ou plus", 8, 40] call comspec_atak_native_fnc_notify;
            };
            "PIP DÉSACTIVÉE : OPTIONS > VIDÉO > QUALITÉ PIP"
        };
        // Distance de vue PiP : au-delà, la caméra ne dessine plus rien (sol compris). Remontée à la distance de vue
        // du joueur (bornée à 3000 m) si elle est sous 300 m.
        private _pvd = getPiPViewDistance;
        if (_pvd < 300) then {
            private _to = (viewDistance min 3000) max 1000;
            setPiPViewDistance _to;
            ["WARNING", format ["%1 : distance de vue PiP à %2 m (trop courte, image vide) : remontée à %3 m", _src, round _pvd, _to]] call _say;
        };
        ""
    };
    // État d'un flux à l'écran (boucle de la nacelle du drone, toutes les 15 s) : une ligne RPT
    // « [CAMERA] DRONE état » avec ce qui peut rendre l'image noire. Params : [source, caméra, objet porteur].
    case "state": {
        _arg params [["_src", "?"], ["_cam", objNull], ["_host", objNull]];
        if (isNull _cam) exitWith { ["WARNING", format ["%1 état : caméra absente", _src]] call _say; false };
        private _asl = getPosASLVisual _cam;
        private _gnd = getTerrainHeightASL _asl;
        private _name = ([_cam, "r2t", ""] call comspec_atak_native_fnc_camVar);
        private _vd = vectorDirVisual _cam;
        private _bad = [];
        if !(isPiPEnabled) then { _bad pushBack "PiP coupée"; };
        if ((_asl select 2) < _gnd - 0.2) then { _bad pushBack "caméra sous le sol"; };
        if (!isNull _host && {(_cam distance _host) > 5}) then { _bad pushBack "caméra loin du drone"; };
        if !([_cam, "rendering", true] call comspec_atak_native_fnc_camVar) then { _bad pushBack "rendu coupé"; };
        if ((vectorMagnitude _vd) < 0.5 || {!finite (_vd select 0)}) then { _bad pushBack "orientation invalide"; };
        [["INFO", "WARNING"] select (_bad isNotEqualTo []), format ["%1 état : cible %2 (créée il y a %3 s), rendu %4, caméra à %5 m du sol et %6 m du drone, accrochée à %7, regarde %8, champ x%9, vision %10, PiP %11 à %12 m, %13 i/s%14",
            _src, _name, round (diag_tickTime - ([_cam, "born", diag_tickTime] call comspec_atak_native_fnc_camVar)), ["COUPÉ", "actif"] select ([_cam, "rendering", true] call comspec_atak_native_fnc_camVar),
            round (((_asl select 2) - _gnd) * 10) / 10, [round ((_cam distance _host) * 10) / 10, "-"] select (isNull _host),
            [typeOf (attachedTo _cam), "rien"] select (isNull (attachedTo _cam)), _vd apply { (round (_x * 100)) / 100 },
            missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1], ["JOUR", "NUIT", "THERMIQUE"] select ((missionNamespace getVariable ["COMSPEC_ATAK_DroneVision", 0]) max 0 min 2),
            ["DÉSACTIVÉE", "active"] select isPiPEnabled, round getPiPViewDistance, round diag_fps,
            ["", " ; PROBLÈME : " + (_bad joinString ", ")] select (_bad isNotEqualTo [])]] call _say;
        true
    };
    case "rebind": {
        _arg params [["_ctrl", controlNull], ["_tex", ""]];
        if (isNull _ctrl || {_tex isEqualTo ""}) exitWith { false };
        _ctrl ctrlSetText _tex; // i18n:brut
        true
    };
    case "watch": {
        _arg params [["_src", "?"], ["_cam", objNull], ["_name", ""], ["_tex", ""], ["_ctrl", controlNull]];
        private _asl = getPosASL _cam;
        private _gnd = getTerrainHeightASL _asl;
        ["INFO", format ["%1 : ouverture du flux. Étape 1 PiP active : %2 ; distance de vue PiP %3 m (vue du joueur %4 m)", _src, isPiPEnabled, round getPiPViewDistance, round viewDistance]] call _say;
        ["INFO", format ["%1 : étape 2 caméra : %2 ; position ASL %3 ; %4 m au-dessus du terrain%5 ; direction %6 ; accrochée à %7",
            _src, ["ABSENTE", "créée"] select (!isNull _cam), _asl apply { round _x }, round ((_asl select 2) - _gnd),
            ["", " (SOUS LE SOL : image noire)"] select ((_asl select 2) < _gnd - 0.2), (vectorDir _cam) apply { (round (_x * 100)) / 100 },
            [typeOf (attachedTo _cam), "rien"] select (isNull (attachedTo _cam))]] call _say;
        ["INFO", format ["%1 : étape 3 cible de rendu « %2 » ; étape 4 texture %3", _src, _name, _tex]] call _say;
        ["INFO", format ["%1 : étape 5 image : %2 ; texte de l'image %3", _src, ["ABSENTE", "posée"] select (!isNull _ctrl),
            [str (ctrlText _ctrl), "-"] select (isNull _ctrl)]] call _say;
        ["INFO", format ["%1 : options vidéo %2", _src, getVideoOptions]] call _say;
        // Plus de rebranchement de la texture après coup (2.1.1) : seules les vues qui la reposaient (Drone, Photo)
        // restaient noires ; Vidéo Live et le TEST VIDÉO, qui la posent une fois, s'affichent.
        // Relevé à 2 s : ce qui a changé entre-temps (caméra détruite, image retirée ou repointée, PiP coupée).
        [{
            params ["_src", "_cam", "_name", "_tex", "_ctrl"];
            private _say = { params ["_lvl", "_msg"]; [_lvl, "CAMERA", _msg] call comspec_atak_native_fnc_log; };
            private _bad = [];
            if !(isPiPEnabled) then { _bad pushBack "PiP coupée"; };
            if (isNull _cam) then { _bad pushBack "caméra détruite (page quittée ou flux recréé)"; };
            if (isNull _ctrl) then { _bad pushBack "image retirée (page redessinée)"; } else {
                if !(ctrlShown _ctrl) then { _bad pushBack "image masquée"; };
                if ((ctrlFade _ctrl) > 0.5) then { _bad pushBack "image transparente"; };
            };
            if (!isNull _cam && {((getPosASL _cam) select 2) < (getTerrainHeightASL (getPosASL _cam)) - 0.2}) then { _bad pushBack "caméra sous le sol"; };
            if (_bad isNotEqualTo []) then {
                ["WARNING", format ["%1 : relevé à 2 s (« %2 ») : %3", _src, _name, _bad joinString " ; "]] call _say;
            } else {
                ["INFO", format ["%1 : relevé à 2 s (« %2 ») : caméra en place, image visible, texture posée, PiP active. Si la vue reste noire malgré tout, le moteur ne dessine pas la cible de rendu : essayer « Qualité PiP » plus haute et la VUE DIRECTE de l'app Drone, puis envoyer ce RPT.", _src, _name]] call _say;
            };
        }, [_src, _cam, _name, _tex, _ctrl], 2] call CBA_fnc_waitAndExecute;
        true
    };
};
