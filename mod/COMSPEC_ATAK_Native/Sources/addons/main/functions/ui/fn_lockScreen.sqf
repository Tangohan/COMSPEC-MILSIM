/*
    Écran de verrouillage Android : grande heure, date (jour, carte, température), batterie et réseau,
    3 dernières notifications (historique de fn_notify, état « notifHistory »), « Touchez pour déverrouiller ».
    Affiché en main (souris) seulement, quand le téléphone est sorti :
      - pour la première fois de la mission, ou après plus de 60 s sans être affiché (ni en main ni porté) ;
      - après un redémarrage, une batterie vide ou un écran détruit (missionNamespace COMSPEC_ATAK_LockPending) :
        dès que l'écran se rallume.
    Réglage profil COMSPEC_ATAK_LockScreen (Réglages > Personnalisation). Porté (HUD), jamais de verrouillage.
    Déverrouillage : clic (ou glissé vers le haut) n'importe où, ou touche accueil de la coque ; l'écran remonte (0,25 s).
    Params : [action]
      "check"  : fn_displayLoad (ouverture) : verrouille si besoin       "tick" : chaque seconde (fn_statusUpdate)
      "show" / "build" / "unlock"
*/
params [["_act", "check"]];
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _interactive = _s getOrDefault ["interactive", false];
private _enabled = profileNamespace getVariable ["COMSPEC_ATAK_LockScreen", true];
private _kill = {
    { ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_LockCtrls", []]);
    uiNamespace setVariable ["COMSPEC_ATAK_LockCtrls", []];
    uiNamespace setVariable ["COMSPEC_ATAK_LockClock", []];
};
// Signature de l'historique (plafonné à 60) : nombre et dernière notification.
private _notifSig = {
    private _h = _s getOrDefault ["notifHistory", []];
    [count _h, if ((count _h) > 0) then { _h select ((count _h) - 1) } else { 0 }]
};
private _usable = {
    ((uiNamespace getVariable ["COMSPEC_ATAK_PowerFx", []]) param [1, -1]) < diag_tickTime
        && {!((([] call comspec_atak_native_fnc_deviceHealth) getOrDefault ["state", "OK"]) in ["OFF", "BROKEN"])}
};
switch (_act) do {
    case "check": {
        private _seen = missionNamespace getVariable ["COMSPEC_ATAK_LockSeen", -1];
        if (_seen < 0 || {diag_tickTime - _seen > 60}) then { missionNamespace setVariable ["COMSPEC_ATAK_LockPending", true]; };
        missionNamespace setVariable ["COMSPEC_ATAK_LockSeen", diag_tickTime];
        if (!_enabled) exitWith { missionNamespace setVariable ["COMSPEC_ATAK_LockPending", false]; false };
        if (isNull _d || {!_interactive} || {!(missionNamespace getVariable ["COMSPEC_ATAK_LockPending", true])} || {!(call _usable)}) exitWith { false };
        ["show"] call comspec_atak_native_fnc_lockScreen
    };
    case "tick": {
        missionNamespace setVariable ["COMSPEC_ATAK_LockSeen", diag_tickTime];
        if (isNull _d) exitWith { false };
        // Écran éteint ou détruit : verrouillé au rallumage.
        if ((([] call comspec_atak_native_fnc_deviceHealth) getOrDefault ["state", "OK"]) in ["OFF", "BROKEN"]) then { missionNamespace setVariable ["COMSPEC_ATAK_LockPending", true]; };
        if (uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false]) exitWith {
            if (!_enabled) exitWith { ["unlock"] call comspec_atak_native_fnc_lockScreen; };
            // Nouvelle notification : la liste est redessinée.
            if ((call _notifSig) isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_LockNotifN", []])) exitWith {
                ["build"] call comspec_atak_native_fnc_lockScreen;
                ["front"] call comspec_atak_native_fnc_fx;
            };
            (uiNamespace getVariable ["COMSPEC_ATAK_LockClock", []]) params [["_clock", controlNull], ["_stat", controlNull]];
            if (!isNull _clock) then { _clock ctrlSetText ([dayTime, "HH:MM"] call BIS_fnc_timeToString); };
            if (!isNull _stat) then { _stat ctrlSetStructuredText parseText (call (uiNamespace getVariable ["COMSPEC_ATAK_LockStatus", { "" }])); };
            true
        };
        if (_enabled && {_interactive} && {missionNamespace getVariable ["COMSPEC_ATAK_LockPending", false]} && {call _usable}) then {
            ["show"] call comspec_atak_native_fnc_lockScreen;
        };
        true
    };
    case "unlock": {
        missionNamespace setVariable ["COMSPEC_ATAK_LockPending", false];
        if !(uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false]) exitWith { false };
        uiNamespace setVariable ["COMSPEC_ATAK_LockUp", false];
        private _ctrls = uiNamespace getVariable ["COMSPEC_ATAK_LockCtrls", []];
        uiNamespace setVariable ["COMSPEC_ATAK_LockCtrls", []];
        uiNamespace setVariable ["COMSPEC_ATAK_LockClock", []];
        if (["anim"] call comspec_atak_native_fnc_fx) then {
            // L'écran de verrouillage remonte : le groupe rétrécit par le bas, son contenu glisse hors de vue.
            _ctrls params [["_grp", controlNull]];
            if (!isNull _grp) then {
                private _p = ctrlPosition _grp;
                _grp ctrlSetPosition [_p select 0, _p select 1, _p select 2, 0];
                _grp ctrlCommit 0.25;
            };
            [{ { ctrlDelete _x; } forEach _this; }, _ctrls, 0.27] call CBA_fnc_waitAndExecute;
        } else {
            { ctrlDelete _x; } forEach _ctrls;
        };
        true
    };
    case "show";
    case "build": {
        if (isNull _d || {!_interactive}) exitWith { uiNamespace setVariable ["COMSPEC_ATAK_LockUp", false]; call _kill; false };
        uiNamespace setVariable ["COMSPEC_ATAK_LockUp", true];
        call _kill;
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        (_l get "device") params ["_dx", "_dy", "_dw", "_dh"];
        private _font = _l get "font";
        private _fs = _l get "fontSmall";
        private _pad = (_l get "pad") * 2;
        private _land = _l get "landscape";
        private _grp = _d ctrlCreate ["COMSPEC_RscControlsGroup", -1];
        _grp ctrlSetPosition [_dx, _dy, _dw, _dh];
        _grp ctrlCommit 0;
        private _made = [_grp];
        private _mk = {
            params ["_class", "_pos", ["_text", ""]];
            private _c = _d ctrlCreate [_class, -1, _grp];
            _c ctrlSetPosition _pos;
            if (_text isNotEqualTo "") then { _c ctrlSetText _text; };
            _c ctrlCommit 0;
            _made pushBack _c;
            _c
        };
        // Fond : fond d'écran flouté de l'accueil (ou noir), assombri.
        private _bg = ["COMSPEC_RscText", [0, 0, _dw, _dh]] call _mk;
        _bg ctrlSetBackgroundColor [0.02, 0.03, 0.025, 1];
        private _wall = profileNamespace getVariable ["COMSPEC_ATAK_Wallpaper", "topo"];
        if (_wall in ["topo", "night", "desert", "olive", "dark", "soar", "athena", "ops"]) then {
            ["COMSPEC_RscPhone", [0, 0, _dw, _dh], format ["\z\comspec_atak_native\addons\main\data\wall_%1_blur_%2.paa", _wall, ["port", "land"] select _land]] call _mk;
        };
        private _veil = ["COMSPEC_RscText", [0, 0, _dw, _dh]] call _mk;
        _veil ctrlSetBackgroundColor [0, 0, 0, 0.45];
        // Heure, date, état.
        private _y = _dh * ([0.07, 0.05] select _land);
        private _clockH = _font * 3.4;
        private _clock = ["COMSPEC_RscTextCenter", [0, _y, _dw, _clockH], [dayTime, "HH:MM"] call BIS_fnc_timeToString] call _mk;
        _clock ctrlSetFont "RobotoCondensedLight";
        _clock ctrlSetFontHeight (_font * 3.2);
        _clock ctrlSetTextColor [0.96, 0.98, 0.96, 1];
        _y = _y + _clockH;
        date params ["_yy", "_mo", "_dd"];
        // Jour de la semaine (Sakamoto).
        private _yk = [_yy, _yy - 1] select (_mo < 3);
        private _wd = (_yk + floor (_yk / 4) - floor (_yk / 100) + floor (_yk / 400) + ([0, 3, 2, 5, 0, 3, 5, 1, 4, 6, 2, 4] select (_mo - 1)) + _dd) mod 7;
        private _world = getText (configFile >> "CfgWorlds" >> worldName >> "description");
        if (_world isEqualTo "") then { _world = worldName; };
        private _dateTxt = format ["%1 %2 %3 · %4 · %5 °C",
            ["dimanche", "lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi"] select _wd, _dd,
            ["janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre"] select (_mo - 1),
            _world, ([] call comspec_atak_native_fnc_weather) select 0];
        private _date = ["COMSPEC_RscTextCenter", [0, _y, _dw, _font * 1.3], _dateTxt] call _mk;
        _date ctrlSetFontHeight _fs;
        _date ctrlSetTextColor [0.82, 0.88, 0.84, 1];
        _y = _y + _font * 1.4;
        uiNamespace setVariable ["COMSPEC_ATAK_LockStatus", {
            private _lq = [] call comspec_atak_native_fnc_linkQuality;
            private _bat = floor (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]);
            private _net = if ([] call comspec_atak_native_fnc_airplaneMode) then { "Mode avion" } else { format ["Réseau %1 · %2/4", _lq getOrDefault ["label", "—"], _lq getOrDefault ["bars", 0]] };
            format ["<t align='center' size='0.85' color='#a9b8b0'>Batterie <t color='%1'>%2 %%</t>   ·   %3</t>", ["#e8eee9", "#e04038"] select (_bat <= 12), _bat, _net]
        }];
        private _stat = ["COMSPEC_RscStructuredText", [0, _y, _dw, _font * 1.3]] call _mk;
        _stat ctrlSetStructuredText parseText (call (uiNamespace getVariable "COMSPEC_ATAK_LockStatus"));
        _y = _y + _font * 1.3 + _pad;
        uiNamespace setVariable ["COMSPEC_ATAK_LockClock", [_clock, _stat]];
        // Dernières notifications (3).
        private _hist = _s getOrDefault ["notifHistory", []];
        uiNamespace setVariable ["COMSPEC_ATAK_LockNotifN", call _notifSig];
        private _last = [];
        for "_i" from ((count _hist) - 1) to (((count _hist) - 3) max 0) step -1 do { _last pushBack (_hist select _i); };
        private _cx = _dw * 0.06;
        private _cw = _dw * 0.88;
        private _ch = _font * 2.1;
        if ((count _last) isEqualTo 0) then {
            private _none = ["COMSPEC_RscTextCenter", [0, _y, _dw, _ch], "Aucune notification"] call _mk;
            _none ctrlSetFontHeight _fs;
            _none ctrlSetTextColor [0.58, 0.64, 0.60, 1];
        };
        {
            private _t = toUpper (_x getOrDefault ["type", "INFO"]);
            private _hex = switch (_t) do { case "WARNING": { "#f2ab33" }; case "ERROR"; case "TACTICAL": { "#e6614f" }; case "MESSAGE": { "#70b5e8" }; case "SUCCESS": { "#5cc76b" }; default { "#a8c7ba" }; };
            private _plain = ((_x getOrDefault ["message", ""]) regexReplace ["<[^>]*>", ""]) regexReplace ["[\r\n\t]+", " "];
            { _plain = [_plain, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&lt;", "<"], ["&gt;", ">"], ["&apos;", "'"], ["&quot;", """"], ["&amp;", "&"]];
            if ((count _plain) > 70) then { _plain = (_plain select [0, 68]) + "…"; };
            _plain = (_plain regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"];
            private _card = ["COMSPEC_RscStructuredText", [_cx, _y, _cw, _ch]] call _mk;
            _card ctrlSetBackgroundColor [0.07, 0.085, 0.075, 0.88];
            _card ctrlSetStructuredText parseText format ["<t size='0.75' color='%1'>%2</t><t size='0.75' color='#8a9a93'>  ·  %3</t><br/><t size='0.85' color='#e8eee9'>%4</t>",
                _hex, _t, _x getOrDefault ["time", ""], _plain];
            _y = _y + _ch + _pad * 0.4;
        } forEach _last;
        // Invite en bas.
        private _hintY = _dh - _font * 2.6;
        private _bar = ["COMSPEC_RscText", [_dw * 0.42, _hintY - _font * 0.5, _dw * 0.16, pixelH * 3]] call _mk;
        _bar ctrlSetBackgroundColor [0.85, 0.9, 0.87, 0.7];
        private _hint = ["COMSPEC_RscTextCenter", [0, _hintY, _dw, _font * 1.3], "Touchez ou glissez vers le haut pour déverrouiller"] call _mk;
        _hint ctrlSetFontHeight (_fs * 0.9);
        _hint ctrlSetTextColor [0.78, 0.84, 0.80, 1];
        // Tout l'écran déverrouille (clic, ou glissé : le relâchement sur le bouton compte comme un clic).
        private _btn = ["COMSPEC_RscButtonInvisible", [0, 0, _dw, _dh]] call _mk;
        _btn ctrlSetTooltip "Déverrouiller";
        _btn ctrlAddEventHandler ["ButtonClick", { [{ ["unlock"] call comspec_atak_native_fnc_lockScreen; }] call CBA_fnc_execNextFrame; }];
        uiNamespace setVariable ["COMSPEC_ATAK_LockCtrls", _made];
        // À l'ouverture : volet rapide refermé, effets d'écran (fêlures, filtre de nuit) remis devant.
        if (_act isEqualTo "show") then {
            if (uiNamespace getVariable ["COMSPEC_ATAK_ShadeOpen", false]) then { ["close"] call comspec_atak_native_fnc_quickSettings; };
            ["front"] call comspec_atak_native_fnc_fx;
            [] call comspec_atak_native_fnc_overlayFront;
        };
        true
    };
    default { false };
}
