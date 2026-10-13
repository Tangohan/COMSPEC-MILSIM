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
    source : "DRONE" ou "PHOTO" (préfixe des lignes du journal).
*/
params [["_act", "check"], ["_arg", ""]];
disableSerialization;
private _say = { params ["_lvl", "_msg"]; [_lvl, "CAMERA", _msg] call comspec_atak_native_fnc_log; };
switch (_act) do {
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
