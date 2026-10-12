/*
    Volet rapide Android, tiré de la barre d'état (clic sur l'heure / la batterie, ou sur la petite poignée sous l'heure).
    Réutilise les réglages existants, sans état parallèle :
      Luminosité  : profil COMSPEC_ATAK_Brightness (0,2 à 1 ; voile sombre de fn_deviceOverlay, lueur fn_screenGlow, batterie)
      Mode nuit   : profil COMSPEC_ATAK_NightMode (NORMAL / ROUGE / SOMBRE)
      Mode avion  : fn_airplaneMode             Silencieux : profil COMSPEC_ATAK_Silent
      Vibration   : profil COMSPEC_ATAK_Vibrate (verrouillable par la communauté, fn_pref)
      Bluetooth   : fn_btAction                 Balise IR  : fn_irBeacon (pas de lampe sur la coque : c'est la « lampe » IR)
      Réseau      : fn_linkQuality (ouvre l'app Réseau)   Batterie : COMSPEC_ATAK_Battery (ouvre l'app Statut)
    Params : [action, argument]
      "handle" : pose la zone cliquable de la barre d'état et la poignée (fn_layoutApply)
      "toggle" / "open" / "close" : volet animé (glisse depuis la barre d'état, remonte à la fermeture)
      "build"  : redessine sans animation (fn_fx « raise » après un rendu de page, changement d'un réglage)
      "tick"   : chaque seconde (fn_statusUpdate) : redessine si batterie, réseau ou un réglage ont changé
      "act" clé : action d'une tuile
*/
params [["_act", "toggle"], ["_arg", ""]];
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { false };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _interactive = _s getOrDefault ["interactive", false];
private _kill = {
    { ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_ShadeCtrls", []]);
    uiNamespace setVariable ["COMSPEC_ATAK_ShadeCtrls", []];
};
// Signature de ce qui est affiché : batterie, barres, avion, réglages.
private _sig = {
    private _lq = [] call comspec_atak_native_fnc_linkQuality;
    [floor (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]), _lq getOrDefault ["bars", 0], _lq getOrDefault ["label", ""], [] call comspec_atak_native_fnc_airplaneMode,
        profileNamespace getVariable ["COMSPEC_ATAK_NightMode", "OFF"], profileNamespace getVariable ["COMSPEC_ATAK_Silent", false],
        profileNamespace getVariable ["COMSPEC_ATAK_Vibrate", true], (missionNamespace getVariable ["COMSPEC_ATAK_Bt", createHashMap]) getOrDefault ["on", false],
        ["state"] call comspec_atak_native_fnc_irBeacon]
};
switch (_act) do {
    case "handle": {
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        (_l get "status") params ["_sx", "_sy", "_sw", "_sh"];
        (uiNamespace getVariable ["COMSPEC_ATAK_ShadeHandle", []]) params [["_btn", controlNull], ["_pill", controlNull]];
        if (isNull _btn || {(ctrlParent _btn) isNotEqualTo _d}) then {
            _pill = _d ctrlCreate ["COMSPEC_RscText", -1];
            _pill ctrlEnable false;
            _pill ctrlSetBackgroundColor [0.62, 0.68, 0.65, 0.75];
            _btn = _d ctrlCreate ["COMSPEC_RscButtonOverlay", -1];
            _btn ctrlSetTooltip "Volet rapide : luminosité, mode nuit, avion, silencieux…";
            _btn ctrlAddEventHandler ["ButtonClick", { [{ ["toggle"] call comspec_atak_native_fnc_quickSettings; }] call CBA_fnc_execNextFrame; }];
            uiNamespace setVariable ["COMSPEC_ATAK_ShadeHandle", [_btn, _pill]];
        };
        // Batterie, météo et heure (jusqu'à la cloche) : rien de cliquable dessous.
        _btn ctrlSetPosition [_sx, _sy, _sw * 0.58, _sh];
        _btn ctrlCommit 0;
        _pill ctrlSetPosition [_sx + _sw * 0.46, _sy + _sh - pixelH * 3, _sw * 0.08, pixelH * 2];
        _pill ctrlCommit 0;
        { _x ctrlShow _interactive; } forEach [_btn, _pill];
        true
    };
    case "toggle": {
        [["open", "close"] select (uiNamespace getVariable ["COMSPEC_ATAK_ShadeOpen", false])] call comspec_atak_native_fnc_quickSettings
    };
    case "close": {
        if !(uiNamespace getVariable ["COMSPEC_ATAK_ShadeOpen", false]) exitWith { false };
        uiNamespace setVariable ["COMSPEC_ATAK_ShadeOpen", false];
        saveProfileNamespace;
        private _ctrls = uiNamespace getVariable ["COMSPEC_ATAK_ShadeCtrls", []];
        uiNamespace setVariable ["COMSPEC_ATAK_ShadeCtrls", []];
        if (["anim"] call comspec_atak_native_fnc_fx) then {
            _ctrls params [["_dim", controlNull], ["_dimB", controlNull], ["_grp", controlNull]];
            if (!isNull _dimB) then { _dimB ctrlEnable false; };
            if (!isNull _grp) then { private _p = ctrlPosition _grp; _p set [3, 0]; _grp ctrlSetPosition _p; _grp ctrlCommit 0.15; };
            if (!isNull _dim) then { _dim ctrlSetFade 1; _dim ctrlCommit 0.15; };
            [{ { ctrlDelete _x; } forEach _this; }, _ctrls, 0.17] call CBA_fnc_waitAndExecute;
        } else {
            { ctrlDelete _x; } forEach _ctrls;
        };
        true
    };
    case "open";
    case "build": {
        if (!_interactive) exitWith { uiNamespace setVariable ["COMSPEC_ATAK_ShadeOpen", false]; call _kill; false };
        private _anim = _act isEqualTo "open" && {["anim"] call comspec_atak_native_fnc_fx};
        uiNamespace setVariable ["COMSPEC_ATAK_ShadeOpen", true];
        uiNamespace setVariable ["COMSPEC_ATAK_ShadeSig", call _sig];
        call _kill;
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        (_l get "device") params ["_dx", "_dy", "_dw", "_dh"];
        (_l get "status") params ["", "", "", "_sth"];
        private _pad = (_l get "pad") * 2;
        private _font = _l get "font";
        private _fs = _l get "fontSmall";
        private _ratio = pixelH / pixelW;
        private _acc = ([] call comspec_atak_native_fnc_accent) select 0;
        private _made = [];
        // Voile sur le reste de l'écran : un clic dessus referme le volet.
        private _dim = _d ctrlCreate ["COMSPEC_RscText", -1];
        _dim ctrlEnable false;
        _dim ctrlSetPosition [_dx, _dy + _sth, _dw, _dh - _sth];
        _dim ctrlSetBackgroundColor [0, 0, 0, 0.45];
        _dim ctrlCommit 0;
        private _dimB = _d ctrlCreate ["COMSPEC_RscButtonInvisible", -1];
        _dimB ctrlSetPosition [_dx, _dy + _sth, _dw, _dh - _sth];
        _dimB ctrlSetTooltip "Fermer le volet";
        _dimB ctrlAddEventHandler ["ButtonClick", { [{ ["close"] call comspec_atak_native_fnc_quickSettings; }] call CBA_fnc_execNextFrame; }];
        _dimB ctrlCommit 0;
        _made append [_dim, _dimB];
        private _grp = _d ctrlCreate ["COMSPEC_RscControlsGroup", -1];
        _made pushBack _grp;
        private _mk = {
            params ["_class", "_pos", ["_text", ""]];
            private _c = _d ctrlCreate [_class, -1, _grp];
            _c ctrlSetPosition _pos;
            if (_text isNotEqualTo "") then { _c ctrlSetText _text; };
            _c ctrlCommit 0;
            _made pushBack _c;
            _c
        };
        private _w = _dw;
        private _cols = [4, 6] select (_l get "landscape");
        private _gap = _pad * 0.6;
        private _tw = (_w - _pad * 2 - _gap * (_cols - 1)) / _cols;
        private _th = (_tw * _ratio * 0.62) max (_font * 2.6);
        // Contenu : en-tête, luminosité, tuiles, pied.
        private _headH = _font * 1.6;
        private _rowH = _font * 1.8;
        private _lq = [] call comspec_atak_native_fnc_linkQuality;
        private _bat = floor (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]);
        private _air = [] call comspec_atak_native_fnc_airplaneMode;
        private _night = profileNamespace getVariable ["COMSPEC_ATAK_NightMode", "OFF"];
        private _vib = ["COMSPEC_ATAK_Vibrate", true, "native_vibrate"] call comspec_atak_native_fnc_pref;
        private _tiles = [
            ["night", "Mode nuit", ["NORMAL", "ROUGE", "SOMBRE"] select ((["OFF", "RED", "DIM"] find _night) max 0), _night isNotEqualTo "OFF"],
            ["airplane", "Mode avion", ["DÉSACTIVÉ", "ACTIVÉ"] select _air, _air],
            ["silent", "Silencieux", ["SONS", "AUCUN SON"] select (profileNamespace getVariable ["COMSPEC_ATAK_Silent", false]), profileNamespace getVariable ["COMSPEC_ATAK_Silent", false]],
            ["vibrate", "Vibration", (["DÉSACTIVÉE", "ACTIVÉE"] select (_vib select 0)) + (["", " · IMPOSÉ"] select (_vib select 1)), _vib select 0],
            ["bt", "Bluetooth", ["DÉSACTIVÉ", "ACTIVÉ"] select ((missionNamespace getVariable ["COMSPEC_ATAK_Bt", createHashMap]) getOrDefault ["on", false]), (missionNamespace getVariable ["COMSPEC_ATAK_Bt", createHashMap]) getOrDefault ["on", false]],
            ["ir", "Balise IR", ["ÉTEINTE", "ALLUMÉE"] select (["state"] call comspec_atak_native_fnc_irBeacon), ["state"] call comspec_atak_native_fnc_irBeacon],
            ["net", "Réseau", if (_air) then { "MODE AVION" } else { format ["%1 · %2/4", toUpper (_lq getOrDefault ["label", "—"]), _lq getOrDefault ["bars", 0]] }, !_air && {(_lq getOrDefault ["bars", 0]) >= 1}],
            ["battery", "Batterie", format ["%1 %%", _bat], _bat > 20]
        ];
        private _rows = ceil ((count _tiles) / _cols);
        private _H = (_headH + _rowH + _rows * (_th + _gap) + _rowH + _pad * 2) min (_dh - _sth);
        private _gy = _dy + _sth;
        private _bg = ["COMSPEC_RscText", [0, 0, _w, _H]] call _mk;
        _bg ctrlSetBackgroundColor [0.035, 0.045, 0.040, 0.98];
        // En-tête : titre, heure et batterie.
        private _t = ["COMSPEC_RscText", [_pad, _pad * 0.5, _w * 0.6, _headH], "Réglages rapides"] call _mk;
        _t ctrlSetFont "RobotoCondensedBold";
        _t ctrlSetFontHeight _fs;
        _t ctrlSetTextColor [0.62, 0.70, 0.66, 1];
        private _hr = ["COMSPEC_RscTextRight", [_w * 0.4, _pad * 0.5, _w * 0.6 - _pad, _headH], format ["%1   ·   %2 %%", [dayTime, "HH:MM"] call BIS_fnc_timeToString, _bat]] call _mk;
        _hr ctrlSetFontHeight _fs;
        // Luminosité : curseur.
        private _y = _pad * 0.5 + _headH;
        private _ll = ["COMSPEC_RscText", [_pad, _y, _w * 0.28, _rowH], "Luminosité"] call _mk;
        _ll ctrlSetFontHeight _fs;
        private _sl = ["COMSPEC_RscSlider", [_pad + _w * 0.28, _y + _rowH * 0.2, _w - _pad * 2 - _w * 0.28, _rowH * 0.6]] call _mk;
        _sl sliderSetRange [0.2, 1];
        _sl sliderSetSpeed [0.05, 0.1];
        _sl sliderSetPosition ((profileNamespace getVariable ["COMSPEC_ATAK_Brightness", 1]) max 0.2 min 1);
        _sl ctrlAddEventHandler ["SliderPosChanged", {
            params ["", "_v"];
            profileNamespace setVariable ["COMSPEC_ATAK_Brightness", ((round (_v * 20)) / 20) max 0.2 min 1];
            // Voile appliqué au plus 10 fois par seconde pendant le glissé.
            if (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_BrightAt", -1]) > 0.1) then {
                uiNamespace setVariable ["COMSPEC_ATAK_BrightAt", diag_tickTime];
                [] call comspec_atak_native_fnc_deviceOverlay;
            };
        }];
        _y = _y + _rowH + _gap;
        // Tuiles.
        {
            _x params ["_key", "_label", "_state", "_on"];
            private _col = _forEachIndex mod _cols;
            private _row = floor (_forEachIndex / _cols);
            private _x0 = _pad + _col * (_tw + _gap);
            private _y0 = _y + _row * (_th + _gap);
            private _tb = ["COMSPEC_RscText", [_x0, _y0, _tw, _th]] call _mk;
            _tb ctrlSetBackgroundColor ([[0.11, 0.13, 0.12, 1], [(_acc select 0) * 0.55, (_acc select 1) * 0.55, (_acc select 2) * 0.55, 1]] select _on);
            private _lb = ["COMSPEC_RscTextCenter", [_x0, _y0 + _th * 0.14, _tw, _th * 0.42], _label] call _mk;
            _lb ctrlSetFont "RobotoCondensedBold";
            _lb ctrlSetFontHeight _fs;
            private _fh = _fs;
            while { (ctrlTextWidth _lb) > _tw * 0.94 && {_fh > _fs * 0.7} } do { _fh = _fh * 0.92; _lb ctrlSetFontHeight _fh; };
            private _st = ["COMSPEC_RscTextCenter", [_x0, _y0 + _th * 0.52, _tw, _th * 0.36], _state] call _mk;
            _st ctrlSetFontHeight (_fs * 0.82);
            _st ctrlSetTextColor ([[0.58, 0.64, 0.60, 1], [0.92, 0.97, 0.93, 1]] select _on);
            _fh = _fs * 0.82;
            while { (ctrlTextWidth _st) > _tw * 0.94 && {_fh > _fs * 0.55} } do { _fh = _fh * 0.92; _st ctrlSetFontHeight _fh; };
            private _b = ["COMSPEC_RscButtonOverlay", [_x0, _y0, _tw, _th]] call _mk;
            _b setVariable ["key", _key];
            _b ctrlSetTooltip format ["%1 : %2", _label, _state];
            _b ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [{ ["act", _this] call comspec_atak_native_fnc_quickSettings; }, _c getVariable ["key", ""]] call CBA_fnc_execNextFrame; }];
        } forEach _tiles;
        _y = _y + _rows * (_th + _gap);
        // Pied : Réglages complets, fermer.
        private _bw2 = (_w - _pad * 2 - _gap) / 2;
        private _b1 = ["COMSPEC_RscButton", [_pad, _y, _bw2, _rowH], "TOUS LES RÉGLAGES"] call _mk;
        _b1 ctrlSetFontHeight _fs;
        _b1 ctrlAddEventHandler ["ButtonClick", { [{ ["close"] call comspec_atak_native_fnc_quickSettings; ["SETTINGS"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame; }];
        private _b2 = ["COMSPEC_RscButton", [_pad + _bw2 + _gap, _y, _bw2, _rowH], "FERMER"] call _mk;
        _b2 ctrlSetFontHeight _fs;
        _b2 ctrlAddEventHandler ["ButtonClick", { [{ ["close"] call comspec_atak_native_fnc_quickSettings; }] call CBA_fnc_execNextFrame; }];
        // Poignée en bas du volet.
        private _hp = ["COMSPEC_RscText", [_w * 0.45, _H - pixelH * 4, _w * 0.1, pixelH * 2]] call _mk;
        _hp ctrlSetBackgroundColor [0.62, 0.68, 0.65, 0.8];
        // Ouverture : le volet descend de la barre d'état (le groupe s'agrandit, son contenu reste en place).
        if (_anim) then {
            _grp ctrlSetPosition [_dx, _gy, _w, 0];
            _grp ctrlCommit 0;
            _grp ctrlSetPosition [_dx, _gy, _w, _H];
            _grp ctrlCommit 0.2;
            _dim ctrlSetFade 1;
            _dim ctrlCommit 0;
            _dim ctrlSetFade 0;
            _dim ctrlCommit 0.2;
        } else {
            _grp ctrlSetPosition [_dx, _gy, _w, _H];
            _grp ctrlCommit 0;
        };
        uiNamespace setVariable ["COMSPEC_ATAK_ShadeCtrls", _made];
        if (_act isEqualTo "open") then {
            ["front"] call comspec_atak_native_fnc_fx;
            [] call comspec_atak_native_fnc_overlayFront;
        };
        true
    };
    case "tick": {
        if !(uiNamespace getVariable ["COMSPEC_ATAK_ShadeOpen", false]) exitWith { false };
        if ((call _sig) isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_ShadeSig", []])) exitWith { false };
        ["build"] call comspec_atak_native_fnc_quickSettings;
        ["front"] call comspec_atak_native_fnc_fx;
        true
    };
    case "act": {
        switch (_arg) do {
            case "night": {
                private _m = profileNamespace getVariable ["COMSPEC_ATAK_NightMode", "OFF"];
                private _n = ["OFF", "RED", "DIM"] select ((((["OFF", "RED", "DIM"] find _m) max 0) + 1) mod 3);
                profileNamespace setVariable ["COMSPEC_ATAK_NightMode", _n];
                [format ["Mode nuit : %1", ["normal", "filtre rouge", "écran sombre"] select (["OFF", "RED", "DIM"] find _n)], 2] call comspec_atak_native_fnc_toast;
            };
            case "airplane": { ["toggle"] call comspec_atak_native_fnc_airplaneMode; };
            case "silent": {
                private _on = !(profileNamespace getVariable ["COMSPEC_ATAK_Silent", false]);
                profileNamespace setVariable ["COMSPEC_ATAK_Silent", _on];
                [["Sons activés", "Silencieux : aucun son"] select _on, 2] call comspec_atak_native_fnc_toast;
            };
            case "vibrate": {
                if ((["COMSPEC_ATAK_Vibrate", true, "native_vibrate"] call comspec_atak_native_fnc_pref) select 1) exitWith {
                    ["Vibration imposée par votre communauté", 2.5] call comspec_atak_native_fnc_toast;
                };
                private _on = !(profileNamespace getVariable ["COMSPEC_ATAK_Vibrate", true]);
                profileNamespace setVariable ["COMSPEC_ATAK_Vibrate", _on];
                [["Vibration désactivée", "Vibration activée"] select _on, 2] call comspec_atak_native_fnc_toast;
                if (_on) then { [] call comspec_atak_native_fnc_vibrate; };
            };
            case "bt": { ["toggle"] call comspec_atak_native_fnc_btAction; };
            case "ir": { ["toggle"] call comspec_atak_native_fnc_irBeacon; };
            case "net": { ["close"] call comspec_atak_native_fnc_quickSettings; ["NETWORK"] call comspec_atak_native_fnc_navigate; };
            case "battery": { ["close"] call comspec_atak_native_fnc_quickSettings; ["STATUS"] call comspec_atak_native_fnc_navigate; };
        };
        if (uiNamespace getVariable ["COMSPEC_ATAK_ShadeOpen", false]) then {
            ["build"] call comspec_atak_native_fnc_quickSettings;
            ["front"] call comspec_atak_native_fnc_fx;
            // Le toast éventuel repasse devant le volet redessiné.
            [] call comspec_atak_native_fnc_toast;
        };
        [] call comspec_atak_native_fnc_deviceOverlay;
        true
    };
    default { false };
}
