/*
    Écran de verrouillage Android : grande heure, date en accent, pastilles (lieu et météo, batterie, réseau),
    dernières notifications en cartes (icône de l'app, titre « Source : … », heure, texte ; historique de fn_notify,
    état « notifHistory »), invite « Touchez pour déverrouiller » qui respire.
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
                // Toast et alertes (vibreur, commandement) remis devant l'écran recréé, comme fn_fx « raise ».
                [] call comspec_atak_native_fnc_toast;
                ["draw"] call comspec_atak_native_fnc_buzzAlert;
                ["draw"] call comspec_atak_native_fnc_fullAlert;
            };
            (uiNamespace getVariable ["COMSPEC_ATAK_LockClock", []]) params [["_clock", controlNull], ["_bat", controlNull], ["_net", controlNull], ["_hint", controlNull], ["_bar", controlNull]];
            if (!isNull _clock) then { _clock ctrlSetText ((([dayTime, "HH:MM"] call BIS_fnc_timeToString)) call comspec_atak_native_fnc_tr); };
            (call (uiNamespace getVariable ["COMSPEC_ATAK_LockStatus", { [] }])) params [["_bt", ""], ["_bc", [1, 1, 1, 1]], ["_nt", ""], ["_nc", [1, 1, 1, 1]]];
            if (!isNull _bat && {_bt isNotEqualTo ""}) then { _bat ctrlSetText ((_bt) call comspec_atak_native_fnc_tr); _bat ctrlSetTextColor _bc; };
            if (!isNull _net && {_nt isNotEqualTo ""}) then { _net ctrlSetText ((_nt) call comspec_atak_native_fnc_tr); _net ctrlSetTextColor _nc; };
            // Invite qui respire : fondu alterné d'une seconde.
            private _f = [0, 0.55] select (((floor diag_tickTime) mod 2) isEqualTo 0);
            { if (!isNull _x) then { _x ctrlSetFade _f; _x ctrlCommit 0.9; }; } forEach [_hint, _bar];
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
        // Dock (mini) ou rail des apps (plein écran) retirés pendant le verrouillage : remis.
        [] call comspec_atak_native_fnc_dockRender;
        if (["anim"] call comspec_atak_native_fnc_fx) then {
            // L'écran de verrouillage remonte : le groupe rétrécit par le bas, son contenu glisse hors de vue.
            _ctrls params [["_grp", controlNull]];
            if (!isNull _grp) then {
                private _p = ctrlPosition _grp;
                _grp ctrlSetPosition [_p select 0, _p select 1, _p select 2, 0];
                _grp ctrlCommit 0.25;
            };
            [{ { ctrlDelete _x; } forEach _this; }, _ctrls, 0.27] call comspec_atak_native_fnc_waitAndExec;
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
        // Dock et rail des apps retirés : rien de cliquable ne dépasse de l'écran de verrouillage.
        { ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_DockControls", []]);
        uiNamespace setVariable ["COMSPEC_ATAK_DockControls", []];
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
            if (_text isNotEqualTo "") then { _c ctrlSetText ((_text) call comspec_atak_native_fnc_tr); };
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
        // Hiérarchie : heure (grande, fine), date en accent, pastilles d'état, puis les notifications en cartes.
        ([] call comspec_atak_native_fnc_accent) params ["_acc", "_accHex"];
        private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
        private _y = _dh * ([0.08, 0.06] select _land);
        private _clockH = _font * 3.6;
        private _clock = ["COMSPEC_RscTextCenter", [0, _y, _dw, _clockH], [dayTime, "HH:MM"] call BIS_fnc_timeToString] call _mk;
        // Style de l'heure (Réglages > Personnalisation, COMSPEC_ATAK_LockClockStyle) : fine, grasse, militaire (accent).
        private _cs = profileNamespace getVariable ["COMSPEC_ATAK_LockClockStyle", "light"];
        _clock ctrlSetFont (createHashMapFromArray [["bold", (COMSPEC_ATAK_FontSet select 1)], ["mil", "EtelkaMonospaceProBold"]] getOrDefault [_cs, (COMSPEC_ATAK_FontSet select 2)]);
        _clock ctrlSetFontHeight ((_font * ([3.5, 2.9] select (_cs isEqualTo "mil"))) max COMSPEC_ATAK_FontMin);
        _clock ctrlSetTextColor ([[0.97, 0.99, 0.97, 1], _acc] select (_cs isEqualTo "mil"));
        _y = _y + _clockH;
        date params ["_yy", "_mo", "_dd"];
        // Jour de la semaine (Sakamoto).
        private _yk = [_yy, _yy - 1] select (_mo < 3);
        private _wd = (_yk + floor (_yk / 4) - floor (_yk / 100) + floor (_yk / 400) + ([0, 3, 2, 5, 0, 3, 5, 1, 4, 6, 2, 4] select (_mo - 1)) + _dd) mod 7;
        private _world = getText (configFile >> "CfgWorlds" >> worldName >> "description");
        if (_world isEqualTo "") then { _world = worldName; };
        // Date dans la langue du téléphone : « SAMEDI 24 FÉVRIER » en français, « SATURDAY, FEBRUARY 24 » sinon.
        private _dateTxt = if ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isEqualTo "fr") then {
            toUpper format ["%1 %2 %3",
                ["dimanche", "lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi"] select _wd, _dd,
                ["janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre"] select (_mo - 1)]
        } else {
            toUpper format ["%1, %3 %2",
                ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"] select _wd, _dd,
                ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"] select (_mo - 1)]
        };
        private _date = ["COMSPEC_RscTextCenter", [0, _y, _dw, _font * 1.2], _dateTxt] call _mk;
        _date ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
        _date ctrlSetFontHeight (_fs * 0.95);
        _date ctrlSetTextColor _acc;
        _y = _y + _font * 1.2 + _pad * 0.6;
        // Pastilles : lieu et météo, batterie, réseau (les deux dernières remises à jour chaque seconde par « tick »).
        uiNamespace setVariable ["COMSPEC_ATAK_LockStatus", {
            private _lq = [] call comspec_atak_native_fnc_linkQuality;
            private _bat = floor (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]);
            [
                format ["Batterie %1 %%", _bat], [[0.86, 0.91, 0.88, 1], [0.95, 0.30, 0.25, 1]] select (_bat <= 12),
                if ([] call comspec_atak_native_fnc_airplaneMode) then { "Mode avion" } else { format ["Réseau %1/4", _lq getOrDefault ["bars", 0]] },
                [[0.86, 0.91, 0.88, 1], [0.95, 0.67, 0.2, 1]] select ((_lq getOrDefault ["bars", 0]) <= 1)
            ]
        }];
        (call (uiNamespace getVariable "COMSPEC_ATAK_LockStatus")) params ["_batTxt", "_batCol", "_netTxt", "_netCol"];
        private _chipH = _font * 1.25;
        private _chips = [];
        {
            _x params ["_txt", "_col", "_sample"];
            private _c = ["COMSPEC_RscTextCenter", [0, _y, _dw * 0.3, _chipH], _sample] call _mk;
            _c ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
            private _w = (ctrlTextWidth _c) + _pad * 2.4;
            _c ctrlSetText ((_txt) call comspec_atak_native_fnc_tr);
            _c ctrlSetTextColor _col;
            _c ctrlSetBackgroundColor [1, 1, 1, 0.09];
            _chips pushBack [_c, _w];
        } forEach [
            [format ["%1 · %2 °C", _world, ([] call comspec_atak_native_fnc_weather) select 0], [0.86, 0.91, 0.88, 1], format ["%1 · %2 °C", _world, ([] call comspec_atak_native_fnc_weather) select 0]],
            [_batTxt, _batCol, "Batterie 100 %"],
            [_netTxt, _netCol, "Mode avion 0/4"]
        ];
        private _gap = _pad * 0.6;
        private _rowW = _gap * ((count _chips) - 1);
        { _rowW = _rowW + (_x select 1); } forEach _chips;
        private _cx0 = (_dw - _rowW) / 2;
        { _x params ["_c", "_w"]; _c ctrlSetPosition [_cx0, _y, _w, _chipH]; _c ctrlCommit 0; _cx0 = _cx0 + _w + _gap; } forEach _chips;
        _y = _y + _chipH + _pad * 1.6;
        // Dernières notifications, groupées par app (3.1.0) : une carte par source (Messages, Athena, Alertes…), la plus
        // récente en tête, avec le nombre de notifications du groupe, l'heure et le dernier texte ; point d'accent
        // devant les groupes qui contiennent du non lu. 3 cartes en paysage, 4 en portrait ; « + n autres » au-delà.
        // Alertes « vibreur seul » (fn_notify mode vibrate) : jamais affichées sur l'écran verrouillé.
        private _hist = (_s getOrDefault ["notifHistory", []]) select { !(_x getOrDefault ["discreet", false]) };
        uiNamespace setVariable ["COMSPEC_ATAK_LockNotifN", call _notifSig];
        private _maxN = [4, 3] select _land;
        private _unreadN = _s getOrDefault ["notifUnread", 0];
        private _groups = [];
        private _byTitle = createHashMap;
        for "_i" from ((count _hist) - 1) to 0 step -1 do {
            private _n = _hist select _i;
            private _meta = [_n] call comspec_atak_native_fnc_notifMeta;
            private _k = toLower (_meta get "title");
            private _fresh = _i >= ((count _hist) - _unreadN);
            if (_k in _byTitle) then {
                private _g = _groups select (_byTitle get _k);
                _g set [2, (_g select 2) + (_n getOrDefault ["count", 1])];
                if (_fresh) then { _g set [3, true]; };
            } else {
                _byTitle set [_k, count _groups];
                _groups pushBack [_n, _meta, _n getOrDefault ["count", 1], _fresh];
            };
        };
        private _cw = _dw * ([0.88, 0.62] select _land);
        private _cx = (_dw - _cw) / 2;
        private _ch = _font * 2.5;
        private _hintY = _dh - _font * 2.4;
        // Actions rapides au-dessus de l'invite : CARTE et MESSAGES (déverrouillent puis ouvrent l'app).
        private _qaH = _font * 1.5;
        private _qaY = _hintY - _font * 0.9 - _qaH;
        private _lbl = ["COMSPEC_RscText", [_cx, _y, _cw, _font], ["AUCUNE NOTIFICATION", format ["NOTIFICATIONS · %1", count _hist]] select ((count _hist) > 0)] call _mk;
        _lbl ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
        _lbl ctrlSetFontHeight ((_fs * 0.75) max COMSPEC_ATAK_FontMin);
        _lbl ctrlSetTextColor [0.62, 0.69, 0.65, 1];
        _y = _y + _font * 1.05;
        private _shown = 0;
        private _e = { params ["_v"]; (_v regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"] };
        {
            _x params ["_n", "_meta", "_times", "_fresh"];
            if (_y + _ch > _qaY - _font * 0.5) exitWith {};
            // Icône, titre (« Source : texte » ou le type), texte et couleur : mêmes règles que le centre de notifications.
            private _rgb = _meta get "rgb";
            private _title = _meta get "title";
            private _plain = _meta get "body";
            if ((count _plain) > 96) then { _plain = (_plain select [0, 94]) + "…"; };
            private _icon = _meta get "icon";
            private _bgc = ["COMSPEC_RscText", [_cx, _y, _cw, _ch]] call _mk;
            _bgc ctrlSetBackgroundColor [0.06, 0.075, 0.068, 0.9];
            private _strip = ["COMSPEC_RscText", [_cx, _y, pixelW * 3, _ch]] call _mk;
            _strip ctrlSetBackgroundColor _rgb;
            // Pastille de l'app : carré teinté, icône blanche.
            private _ih = _ch * 0.56;
            private _iw = _ih / _ratio;
            private _ix = _cx + _pad * 1.2;
            private _tile = ["COMSPEC_RscText", [_ix, _y + (_ch - _ih) / 2, _iw, _ih]] call _mk;
            _tile ctrlSetBackgroundColor [_rgb select 0, _rgb select 1, _rgb select 2, 0.22];
            private _pic = ["COMSPEC_RscPicture", [_ix + _iw * 0.15, _y + (_ch - _ih) / 2 + _ih * 0.15, _iw * 0.7, _ih * 0.7], _icon] call _mk;
            _pic ctrlSetTextColor [0.95, 0.98, 0.96, 1];
            private _tx = _ix + _iw + _pad;
            private _body = ["COMSPEC_RscStructuredText", [_tx, _y + _ch * 0.1, _cx + _cw - _tx - _pad, _ch * 0.85]] call _mk;
            _body ctrlSetStructuredText parseText ((format ["%4<t size='0.8' font='COMSPEC_BarlowBold' color='#eef3ef'>%1</t>%5<t size='0.72' align='right' color='#a9b6b0'>%2</t><br/><t size='0.8' color='#c4cfc9'>%3</t>",
                [_title] call _e, _n getOrDefault ["time", ""], [_plain] call _e,
                ["", format ["<t color='%1'>● </t>", _accHex]] select _fresh,
                ["", format [" <t size='0.72' color='#a9b6b0'>×%1</t>", _times]] select (_times > 1)]) call comspec_atak_native_fnc_tr);
            _y = _y + _ch + _pad * 0.5;
            _shown = _shown + 1;
        } forEach _groups;
        private _more = (count _groups) - _shown;
        if (_shown > 0 && {_more > 0} && {_y + _font < _qaY - _font * 0.3}) then {
            private _m = ["COMSPEC_RscTextCenter", [_cx, _y, _cw, _font], format ["+ %1 autre(s) dans le centre de notifications", _more]] call _mk;
            _m ctrlSetFontHeight ((_fs * 0.75) max COMSPEC_ATAK_FontMin);
            _m ctrlSetTextColor [0.55, 0.62, 0.58, 1];
        };
        // Invite en bas : poignée et texte qui « respirent » (fondu alterné par « tick »).
        private _bar = ["COMSPEC_RscText", [_dw * 0.44, _hintY - _font * 0.45, _dw * 0.12, pixelH * 4]] call _mk;
        _bar ctrlSetBackgroundColor [0.9, 0.94, 0.91, 0.75];
        private _hint = ["COMSPEC_RscTextCenter", [0, _hintY, _dw, _font * 1.2], "Touchez ou glissez vers le haut pour déverrouiller"] call _mk;
        _hint ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
        _hint ctrlSetTextColor [0.8, 0.86, 0.82, 1];
        uiNamespace setVariable ["COMSPEC_ATAK_LockClock", [_clock, (_chips select 1) select 0, (_chips select 2) select 0, _hint, _bar]];
        // Tout l'écran déverrouille (clic, ou glissé : le relâchement sur le bouton compte comme un clic).
        private _btn = ["COMSPEC_RscButtonInvisible", [0, 0, _dw, _dh]] call _mk;
        _btn ctrlSetTooltip (("Déverrouiller") call comspec_atak_native_fnc_tr);
        _btn ctrlAddEventHandler ["ButtonClick", { [{ ["unlock"] call comspec_atak_native_fnc_lockScreen; }] call comspec_atak_native_fnc_nextFrame; }];
        // Actions rapides (créées après le bouton plein écran : elles passent devant) : icône et libellé, fond relief.
        private _qa = [["app_map", "CARTE", "MAP", "Déverrouiller et ouvrir la carte"], ["app_chat", "MESSAGES", "CHAT", "Déverrouiller et ouvrir la messagerie"]];
        private _qaW = (_cw - _pad) / 2;
        {
            _x params ["_qi", "_ql", "_qp", "_qt"];
            private _qx = _cx + _forEachIndex * (_qaW + _pad);
            private _qb = ["COMSPEC_RscText", [_qx, _qaY, _qaW, _qaH]] call _mk;
            _qb ctrlSetBackgroundColor [1, 1, 1, 0.09];
            private _qis = _qaH * 0.6;
            private _qic = ["COMSPEC_RscPicture", [_qx + _pad, _qaY + (_qaH - _qis) / 2, _qis / _ratio, _qis], format ["\z\comspec_atak_native\addons\main\data\%1.paa", _qi]] call _mk;
            _qic ctrlSetTextColor [0.95, 0.98, 0.96, 1];
            private _qlb = ["COMSPEC_RscText", [_qx + _pad * 2 + _qis / _ratio, _qaY, _qaW - _pad * 2 - _qis / _ratio, _qaH], _ql] call _mk;
            _qlb ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
            _qlb ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
            _qlb ctrlSetTextColor [0.9, 0.94, 0.91, 1];
            private _qbt = ["COMSPEC_RscButtonOverlay", [_qx, _qaY, _qaW, _qaH]] call _mk;
            _qbt ctrlSetTooltip ((_qt) call comspec_atak_native_fnc_tr);
            _qbt setVariable ["page", _qp];
            _qbt ctrlAddEventHandler ["ButtonClick", {
                params ["_c"];
                [{ ["unlock"] call comspec_atak_native_fnc_lockScreen; [_this] call comspec_atak_native_fnc_navigate; }, _c getVariable ["page", "LAUNCHER"]] call comspec_atak_native_fnc_nextFrame;
            }];
        } forEach _qa;
        uiNamespace setVariable ["COMSPEC_ATAK_LockCtrls", _made];
        // À l'ouverture : volet rapide refermé, effets d'écran (fêlures, filtre de nuit) remis devant.
        if (_act isEqualTo "show") then {
            if (uiNamespace getVariable ["COMSPEC_ATAK_ShadeOpen", false]) then { ["close"] call comspec_atak_native_fnc_quickSettings; };
            ["front"] call comspec_atak_native_fnc_fx;
            // Toast et alertes (vibreur, commandement) dessinés avant l'écran de verrouillage (fn_displayLoad : rendu
            // puis « check ») : remis devant, sinon l'alerte du commandement expirait cachée dessous.
            [] call comspec_atak_native_fnc_toast;
            ["draw"] call comspec_atak_native_fnc_buzzAlert;
            ["draw"] call comspec_atak_native_fnc_fullAlert;
            [] call comspec_atak_native_fnc_overlayFront;
        };
        true
    };
    default { false };
}
