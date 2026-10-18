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
                                l'image est rebranchée sur la texture une image, deux images et 0,6 s plus tard (une
                                texture posée avant le premier rendu de la caméra peut rester noire) et l'état est
                                relevé à 2 s (caméra, image, texture) ;
      ["rebind", [image, texture]]   pose de nouveau la texture sur l'image (si elle est toujours là).
      ["test"]                TEST VIDÉO (apps Drone et Photo) : une grille de 9 images, lettres A à I, pendant 30 s, dans une
                                fenêtre ouverte par-dessus le téléphone. A reprend le premier test (qui s'affiche chez TTRD,
                                13/10 21:20), chaque autre lettre ajoute une seule différence avec les vues Photo et Drone
                                (noires) : texture 1024, image 16/9, caméra déplacée comme l'app Photo, déplacée par
                                position seule, accrochée comme le drone, posée 30 m plus haut, thermique, flux réel du
                                drone. Les lettres noires désignent la cause ; le journal note où chaque caméra se trouve.
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
        private _parent = [findDisplay 46, _phone] select (!isNull _phone);
        if (isNull _parent) exitWith {};
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
            ["H", "thermique", 512, 1, "thermal"]
        ];
        private _top = _parent createDisplay "RscDisplayEmpty";
        if (isNull _top) exitWith { ["WARNING", "TEST VIDÉO : fenêtre de test impossible à ouvrir"] call _say; };
        private _cellW = 0.30 * safeZoneW;
        private _cellH = 0.27 * safeZoneH;
        private _cams = [];
        private _ctrls = [];
        {
            _x params ["_k", "_what", "_size", "_aspect", "_mode"];
            private _col = _forEachIndex mod 3;
            private _row = floor (_forEachIndex / 3);
            private _cx = safeZoneX + 0.04 * safeZoneW + _col * (_cellW + 0.01 * safeZoneW);
            private _cy = safeZoneY + 0.08 * safeZoneH + _row * (_cellH + 0.02 * safeZoneH);
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
                // Comme le drone : rendu lancé d'abord, puis accrochée et orientée.
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
                _name setPiPEffect [[0, 2] select (_mode isEqualTo "thermal")];
                _cam setVariable ["pipTest", [_k, _mode, _pos]];
                _tex = format ["#(argb,%1,%1,1)r2t(%2,%3)", _size, _name, _aspect];
            };
            // Image aux proportions de la texture (pixels carrés), la plus grande qui tient dans la case.
            private _ph = _cellH - 0.05 * safeZoneH;
            private _pw = (_ph / pixelH) * _aspect * pixelW;
            if (_pw > _cellW) then { _pw = _cellW; _ph = (_pw / pixelW) / _aspect * pixelH; };
            private _bg = _top ctrlCreate ["RscText", -1];
            _bg ctrlSetPosition [_cx, _cy, _cellW, _cellH];
            _bg ctrlSetBackgroundColor [0.15, 0.02, 0.02, 1];
            _bg ctrlCommit 0;
            private _pic = _top ctrlCreate ["RscPicture", -1];
            _pic ctrlSetPosition [_cx + (_cellW - _pw) / 2, _cy + 0.05 * safeZoneH, _pw, _ph];
            if (_tex isNotEqualTo "") then { _pic ctrlSetText _tex; }; // i18n:brut
            _pic ctrlCommit 0;
            private _lb = _top ctrlCreate ["RscStructuredText", -1];
            _lb ctrlSetPosition [_cx, _cy, _cellW, 0.05 * safeZoneH];
            _lb ctrlSetBackgroundColor [0, 0, 0, 0.85];
            _lb ctrlSetStructuredText parseText (((format ["<t size='1.3' color='#ffd400' font='PuristaBold'>%1</t> <t size='0.9' color='#ffffff'>%2%3</t>", _k, _what call comspec_atak_native_fnc_tr, ["", " : " + (_note call comspec_atak_native_fnc_tr)] select (_note isNotEqualTo "")])) call comspec_atak_native_fnc_tr);
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
                (_x getVariable ["pipTest", ["", "", []]]) params ["", "_mode"];
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
                (_x getVariable ["pipTest", ["", "", []]]) params ["_k", "_mode", "_want"];
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
                private _k = (_x getVariable ["pipTest", ["a"]]) select 0;
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
        // Rebranchements : la cible de rendu n'existe qu'après le premier rendu de la caméra (fin d'image).
        if (!isNull _ctrl && {_tex isNotEqualTo ""}) then {
            [{ ["rebind", _this] call comspec_atak_native_fnc_pipCheck; }, [_ctrl, _tex]] call CBA_fnc_execNextFrame;
            [{ [{ ["rebind", _this] call comspec_atak_native_fnc_pipCheck; }, _this] call CBA_fnc_execNextFrame; }, [_ctrl, _tex]] call CBA_fnc_execNextFrame;
            [{ ["rebind", _this] call comspec_atak_native_fnc_pipCheck; }, [_ctrl, _tex], 0.6] call CBA_fnc_waitAndExecute;
        };
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
