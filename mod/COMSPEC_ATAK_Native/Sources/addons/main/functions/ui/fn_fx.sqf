/*
    Mouvement et retour tactile du téléphone (« un téléphone qui réagit »).
    Params : [action, argument]
      "anim"   : animations permises ? (profil COMSPEC_ATAK_Animations, pas pendant un rejeu AAR)
      "tapFx"  : son et vibration des appuis permis ? (profil COMSPEC_ATAK_TapFeedback)
      "tap"    : [x, y] appui sur l'écran : onde verte sous le doigt, clic doux (pas en mode discrétion) et
                 légère vibration de la coque (1 px, 60 ms). Appelée par le relâchement du clic (fn_displayLoad).
      "open"   : (transitions activées, profil COMSPEC_ATAK_PageFx, actives par défaut) app qui s'ouvre : contenu en fondu
                 (0,26 s), titre qui arrive de la droite, icône de l'app qui grandit du point touché vers le centre en
                 s'effaçant (icônes réalistes, fn_iconTint).
      "home"   : retour à l'accueil : fondu court (les icônes du lanceur arrivent en cascade, fn_launcherRender).
      "back"   : retour arrière : fondu court, titre qui arrive de la gauche.
      "raise"  : après un rendu de page : écran de verrouillage, volet rapide, effets d'écran et toast remis devant.
      "front"  : effets d'écran (fn_deviceOverlay) recréés au premier plan.
    Tout passe par ctrlSetPosition / ctrlSetFade + ctrlCommit (animé par le moteur), sans boucle par image.
*/
params [["_act", "anim"], ["_arg", []]];
disableSerialization;
private _animOn = { (profileNamespace getVariable ["COMSPEC_ATAK_Animations", true]) && {!(missionNamespace getVariable ["COMSPEC_ATAK_Replaying", false])} };
// Transitions entre pages (Réglages > Animations et sons > Transitions entre les pages) : actives par défaut depuis
// qu'elles n'ajoutent plus de calque opaque (l'ancien voile sombre faisait un bref clignotement noir).
private _pageFxOn = { profileNamespace getVariable ["COMSPEC_ATAK_PageFx", true] };
switch (_act) do {
    case "anim": { call _animOn };
    case "tapFx": { profileNamespace getVariable ["COMSPEC_ATAK_TapFeedback", true] };
    case "tap": {
        private _d = [] call comspec_atak_native_fnc_display;
        if (isNull _d) exitWith { false };
        private _now = diag_tickTime;
        if (_now - (uiNamespace getVariable ["COMSPEC_ATAK_TapLast", -1]) < 0.06) exitWith { false };
        uiNamespace setVariable ["COMSPEC_ATAK_TapLast", _now];
        if (((uiNamespace getVariable ["COMSPEC_ATAK_PowerFx", []]) param [1, -1]) > _now) exitWith { false };
        if (((([] call comspec_atak_native_fnc_deviceHealth) getOrDefault ["state", "OK"]) in ["OFF", "BROKEN"])) exitWith { false };
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        (_l get "device") params ["_dx", "_dy", "_dw", "_dh"];
        _arg params [["_mx", -10], ["_my", -10]];
        if (_mx < _dx || {_mx > _dx + _dw} || {_my < _dy} || {_my > _dy + _dh}) exitWith { false };
        // Onde : disque vert (couleur d'accent) qui grandit et s'efface.
        if (call _animOn) then {
            private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
            private _r1 = _dw * 0.11;
            private _r0 = _r1 * 0.25;
            private _col = +(([] call comspec_atak_native_fnc_accent) select 0);
            _col set [3, 0.42];
            private _c = _d ctrlCreate ["COMSPEC_RscPhone", -1];
            _c ctrlSetText (("\a3\ui_f\data\map\markers\military\dot_ca.paa") call comspec_atak_native_fnc_tr);
            _c ctrlSetTextColor _col;
            _c ctrlEnable false;
            _c ctrlSetPosition [_mx - _r0, _my - _r0 * _ratio, _r0 * 2, _r0 * 2 * _ratio];
            _c ctrlCommit 0;
            _c ctrlSetPosition [_mx - _r1, _my - _r1 * _ratio, _r1 * 2, _r1 * 2 * _ratio];
            _c ctrlSetFade 1;
            _c ctrlCommit 0.35;
            [{ ctrlDelete _this; }, _c, 0.4] call CBA_fnc_waitAndExecute;
        };
        if (["tapFx"] call comspec_atak_native_fnc_fx) then {
            if !((profileNamespace getVariable ["COMSPEC_ATAK_Silent", false]) || {["mute", false] call comspec_atak_native_fnc_zeusFx}) then { playSound "ClickSoft"; };
            // Vibration légère : la coque descend d'un pixel et revient (pas pendant la vibration d'un message).
            if ((uiNamespace getVariable ["COMSPEC_ATAK_Vibrating", -1]) < 0) then {
                private _ph = _d displayCtrl 88509;
                (_l get "phone") params ["_px", "_py", "_pw", "_phh"];
                _ph ctrlSetPosition [_px + pixelW, _py + pixelH * 1.5, _pw, _phh];
                _ph ctrlCommit 0;
                _ph ctrlSetPosition [_px, _py, _pw, _phh];
                _ph ctrlCommit 0.06;
            };
        };
        true
    };
    case "open";
    case "home";
    case "back": {
        // Sans calque opaque par-dessus la page (l'ancien voile sombre faisait un flash noir à chaque changement d'app) :
        // le contenu de la nouvelle page apparaît en fondu, le titre glisse et, à l'ouverture d'une app, son icône
        // grandit depuis le point touché vers le centre en s'effaçant (écran de démarrage d'Android).
        if !(call _animOn && {call _pageFxOn}) exitWith { false };
        private _d = [] call comspec_atak_native_fnc_display;
        if (isNull _d) exitWith { false };
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        (_l get "body") params ["_bx", "_by", "_bw", "_bh"];
        // Zone de contenu (défilante) ou carte : seul le fondu est animé, la déplacer remettrait le défilement en haut.
        {
            if (!isNull _x && {ctrlShown _x}) then {
                _x ctrlSetFade ([0.85, 0.55] select (_act isEqualTo "back"));
                _x ctrlCommit 0;
                _x ctrlSetFade 0;
                _x ctrlCommit ([0.26, 0.16] select (_act isEqualTo "back"));
            };
        } forEach [_d displayCtrl 88531, [_d] call comspec_atak_native_fnc_mapCtrl];
        // Titre de la barre d'app : arrive de la droite (ouverture) ou de la gauche (retour). Réglage à part.
        if (_act isNotEqualTo "home" && {profileNamespace getVariable ["COMSPEC_ATAK_TitleSlide", true]}) then {
            private _t = _d displayCtrl 88518;
            private _tp = ctrlPosition _t;
            _t ctrlSetPosition [(_tp select 0) + _bw * ([0.06, -0.06] select (_act isEqualTo "back")), _tp select 1, _tp select 2, _tp select 3];
            _t ctrlSetFade 1;
            _t ctrlCommit 0;
            _t ctrlSetPosition _tp;
            _t ctrlSetFade 0;
            _t ctrlCommit 0.2;
        };
        // Icône qui grandit : réglage à part (Réglages > Animations et sons > Ouverture depuis l'icône).
        if (_act isEqualTo "open" && {["on"] call comspec_atak_native_fnc_iconTint} && {profileNamespace getVariable ["COMSPEC_ATAK_OpenSplash", true]}) then {
            private _page = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""];
            private _app = (([] call comspec_atak_native_fnc_appList) select { (_x get "page") isEqualTo _page }) param [0, createHashMap];
            if ((count _app) isEqualTo 0) exitWith {};
            // Origine : le point touché (icône du lanceur, du dock…), sinon le centre de la zone d'app.
            private _m = getMousePosition;
            (_l get "device") params ["_dx", "_dy", "_dw", "_dh"];
            private _inside = (_l get "interactive") && {(_m select 0) > _dx} && {(_m select 0) < _dx + _dw} && {(_m select 1) > _dy} && {(_m select 1) < _dy + _dh};
            if !(_inside) then { _m = [_bx + _bw / 2, _by + _bh / 2]; };
            private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
            // Carré arrondi (texture : carré = 80 % du côté, centré à 48 % de la hauteur) et glyphe à 58 % du carré.
            // Départ à la taille d'une icône du lanceur (7 % de la largeur), arrivée au tiers de l'écran.
            private _place = {
                params ["_c", "_cx", "_cy", "_side", "_k", "_yk"];
                private _h = _side * _k * _ratio;
                _c ctrlSetPosition [_cx - _side * _k / 2, _cy - _h * _yk, _side * _k, _h];
            };
            private _s0 = _bw * 0.07;
            private _s1 = (_bw * 0.34) min (_bh * 0.34 / _ratio);
            private _cx1 = _bx + _bw / 2;
            private _cy1 = _by + _bh * 0.45;
            private _sq = _d ctrlCreate ["COMSPEC_RscPicture", -1];
            _sq ctrlSetText (("\z\comspec_atak_native\addons\main\data\ui_icon_squircle.paa") call comspec_atak_native_fnc_tr);
            _sq ctrlSetTextColor (["color", _page] call comspec_atak_native_fnc_iconTint);
            private _gl = _d ctrlCreate ["COMSPEC_RscPicture", -1];
            _gl ctrlSetText (((_app get "icon")) call comspec_atak_native_fnc_tr);
            _gl ctrlSetTextColor [1, 1, 1, 1];
            { _x ctrlEnable false; } forEach [_sq, _gl];
            [_sq, _m select 0, _m select 1, _s0, 1.25, 0.48] call _place;
            [_gl, _m select 0, _m select 1, _s0, 0.58, 0.5] call _place;
            _sq ctrlCommit 0;
            _gl ctrlCommit 0;
            [_sq, _cx1, _cy1, _s1, 1.25, 0.48] call _place;
            [_gl, _cx1, _cy1, _s1, 0.58, 0.5] call _place;
            { _x ctrlSetFade 1; _x ctrlCommit 0.32; } forEach [_sq, _gl];
            [{ { ctrlDelete _x; } forEach _this; }, [_sq, _gl], 0.36] call CBA_fnc_waitAndExecute;
        };
        // Le contrôle focalisé (et son groupe) passe devant tout : focus rendu au bouton hors écran.
        [] call comspec_atak_native_fnc_overlayFront;
        true
    };
    case "front": {
        // Effets d'écran (fêlures, filtre de nuit, écran éteint) recréés pour repasser devant les calques ajoutés.
        {
            if (_x isEqualType controlNull) then { ctrlDelete _x; } else {
                if (_x isEqualType []) then { { if (_x isEqualType controlNull) then { ctrlDelete _x; }; } forEach _x; };
            };
        } forEach (uiNamespace getVariable ["COMSPEC_ATAK_DevOverlay", []]);
        uiNamespace setVariable ["COMSPEC_ATAK_DevOverlay", []];
        [] call comspec_atak_native_fnc_deviceOverlay;
        true
    };
    case "raise": {
        private _lock = uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false];
        private _shade = uiNamespace getVariable ["COMSPEC_ATAK_ShadeOpen", false];
        if (_lock) then { ["build"] call comspec_atak_native_fnc_lockScreen; };
        if (_shade) then { ["build"] call comspec_atak_native_fnc_quickSettings; };
        if (_lock || _shade) then { ["front"] call comspec_atak_native_fnc_fx; [] call comspec_atak_native_fnc_overlayFront; };
        [] call comspec_atak_native_fnc_toast;
        ["draw"] call comspec_atak_native_fnc_buzzAlert;
        ["draw"] call comspec_atak_native_fnc_fullAlert;
        true
    };
    default { false };
}
