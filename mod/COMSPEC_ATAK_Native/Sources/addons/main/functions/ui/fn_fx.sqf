/*
    Mouvement et retour tactile du téléphone (« un téléphone qui réagit »).
    Params : [action, argument]
      "anim"   : animations permises ? (profil COMSPEC_ATAK_Animations, pas pendant un rejeu AAR)
      "tapFx"  : son et vibration des appuis permis ? (profil COMSPEC_ATAK_TapFeedback)
      "tap"    : [x, y] appui sur l'écran : onde verte sous le doigt, clic doux (pas en mode discrétion) et
                 légère vibration de la coque (1 px, 60 ms). Appelée par le relâchement du clic (fn_displayLoad).
      "open"   : (transitions activées, profil COMSPEC_ATAK_PageFx) app qui s'ouvre : une carte grandit depuis le point touché jusqu'à la zone de l'app (0,18 s) puis s'efface.
      "home"   : retour à l'accueil : fondu court.
      "back"   : retour arrière : la page quittée glisse vers la droite et découvre la précédente (0,18 s).
      "raise"  : après un rendu de page : écran de verrouillage, volet rapide, effets d'écran et toast remis devant.
      "front"  : effets d'écran (fn_deviceOverlay) recréés au premier plan.
    Tout passe par ctrlSetPosition / ctrlSetFade + ctrlCommit (animé par le moteur), sans boucle par image.
*/
params [["_act", "anim"], ["_arg", []]];
disableSerialization;
private _animOn = { (profileNamespace getVariable ["COMSPEC_ATAK_Animations", true]) && {!(missionNamespace getVariable ["COMSPEC_ATAK_Replaying", false])} };
// Transitions entre pages (voile sombre qui grandit, fondu, glissé) : désactivées par défaut, elles faisaient un bref
// clignotement noir à chaque changement d'app (Réglages > Animations et sons > Transitions entre les pages).
private _pageFxOn = { profileNamespace getVariable ["COMSPEC_ATAK_PageFx", false] };
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
    case "home": {
        if !(call _animOn && {call _pageFxOn}) exitWith { false };
        private _d = [] call comspec_atak_native_fnc_display;
        if (isNull _d) exitWith { false };
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        (_l get "body") params ["_bx", "_by", "_bw", "_bh"];
        private _c = _d ctrlCreate ["COMSPEC_RscText", -1];
        _c ctrlEnable false;
        _c ctrlSetBackgroundColor [0.045, 0.055, 0.048, 1];
        if (_act isEqualTo "home") then {
            _c ctrlSetPosition [_bx, _by, _bw, _bh];
            _c ctrlSetFade 0.15;
            _c ctrlCommit 0;
            _c ctrlSetFade 1;
            _c ctrlCommit 0.2;
            [{ ctrlDelete _this; }, _c, 0.25] call CBA_fnc_waitAndExecute;
        } else {
            // Origine : le point touché (icône du lanceur, du dock…), sinon le centre de la zone d'app.
            private _m = getMousePosition;
            (_l get "device") params ["_dx", "_dy", "_dw", "_dh"];
            private _inside = (_l get "interactive") && {(_m select 0) > _dx} && {(_m select 0) < _dx + _dw} && {(_m select 1) > _dy} && {(_m select 1) < _dy + _dh};
            if !(_inside) then { _m = [_bx + _bw / 2, _by + _bh / 2]; };
            private _sw = _bw * 0.16;
            private _sh = _sw * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
            _c ctrlSetPosition [(_m select 0) - _sw / 2, (_m select 1) - _sh / 2, _sw, _sh];
            _c ctrlSetBackgroundColor [0.07, 0.10, 0.08, 1];
            _c ctrlCommit 0;
            _c ctrlSetPosition [_bx, _by, _bw, _bh];
            _c ctrlCommit 0.18;
            [{
                if (isNull _this) exitWith {};
                _this ctrlSetFade 1;
                _this ctrlCommit 0.15;
                [{ ctrlDelete _this; }, _this, 0.18] call CBA_fnc_waitAndExecute;
            }, _c, 0.18] call CBA_fnc_waitAndExecute;
        };
        // Le contrôle focalisé (et son groupe) passe devant tout : focus rendu au bouton hors écran.
        [] call comspec_atak_native_fnc_overlayFront;
        true
    };
    case "back": {
        if !(call _animOn && {call _pageFxOn}) exitWith { false };
        private _d = [] call comspec_atak_native_fnc_display;
        if (isNull _d) exitWith { false };
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        (_l get "body") params ["_bx", "_by", "_bw", "_bh"];
        // Volet de la page quittée (fond d'app) avec un liseré d'accent sur son bord gauche, qui part vers la droite.
        private _c = _d ctrlCreate ["COMSPEC_RscText", -1];
        _c ctrlEnable false;
        _c ctrlSetBackgroundColor [0.045, 0.055, 0.048, 1];
        _c ctrlSetPosition [_bx, _by, _bw, _bh];
        _c ctrlCommit 0;
        private _e = _d ctrlCreate ["COMSPEC_RscText", -1];
        _e ctrlEnable false;
        private _col = +(([] call comspec_atak_native_fnc_accent) select 0);
        _col set [3, 0.7];
        _e ctrlSetBackgroundColor _col;
        _e ctrlSetPosition [_bx, _by, pixelW * 2, _bh];
        _e ctrlCommit 0;
        _c ctrlSetPosition [_bx + _bw, _by, 0, _bh];
        _c ctrlSetFade 0.3;
        _c ctrlCommit 0.18;
        _e ctrlSetPosition [_bx + _bw - pixelW * 2, _by, pixelW * 2, _bh];
        _e ctrlSetFade 1;
        _e ctrlCommit 0.18;
        [{ { ctrlDelete _x; } forEach _this; }, [_c, _e], 0.22] call CBA_fnc_waitAndExecute;
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
        true
    };
    default { false };
}
