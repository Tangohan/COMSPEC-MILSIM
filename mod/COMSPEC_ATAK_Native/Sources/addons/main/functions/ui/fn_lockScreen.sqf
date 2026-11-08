/*
    Écran de verrouillage : fond d'écran net sous un voile dégradé, grande heure, date et lieu, pastilles météo, batterie et
    réseau, notifications groupées par app (liste défilante, texte sur deux lignes au plus, hauteur mesurée), raccourcis
    CARTE et MESSAGES, bouton de déverrouillage au centre. Paysage : deux colonnes, toute la largeur de l'écran.
    Affiché en main (souris) seulement, quand le téléphone est sorti :
      - pour la première fois de la mission, ou après plus de 60 s sans être affiché (ni en main ni porté) ;
      - après un redémarrage, une batterie vide ou un écran détruit (missionNamespace COMSPEC_ATAK_LockPending) :
        dès que l'écran se rallume.
    Réglage profil COMSPEC_ATAK_LockScreen (Réglages > Personnalisation). Porté (HUD), jamais de verrouillage.
    Déverrouillage : bouton, clic ou glissé vers le haut ailleurs sur l'écran, ou touche accueil de la coque ; code demandé
    d'abord s'il est réglé (fn_phoneOwn, clavier fn_phonePad). Raccourci ou notification : ouvre l'app une fois déverrouillé.
    Aucune info-bulle : celle du bouton plein écran suivait le curseur et flottait sur les notifications (« Déverrouiller »).
    Params : [action]
      "check"  : fn_displayLoad (ouverture) : verrouille si besoin       "tick" : chaque seconde (fn_statusUpdate)
      "show" / "build" / "unlock"
*/
params [["_act", "check"]];
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _interactive = _s getOrDefault ["interactive", false];
// Code demandé (fn_phoneOwn) : l'écran de verrouillage s'affiche même si le joueur l'a coupé dans les réglages.
private _enabled = (profileNamespace getVariable ["COMSPEC_ATAK_LockScreen", true]) || {(profileNamespace getVariable ["COMSPEC_ATAK_PinAsk", false]) isEqualTo true};
// Téléphone d'un autre à l'écran : fn_pagePhones a son propre clavier ; mon écran de verrouillage n'a rien à y faire.
if (_act in ["check", "tick"] && {(missionNamespace getVariable ["COMSPEC_ATAK_PhoneActive", ""]) isNotEqualTo ""}) exitWith { false };
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
            // Nouvelle notification ou jumelles de vision nocturne mises ou retirées : l'écran est redessiné.
            if ((call _notifSig) isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_LockNotifN", []])
                || {(currentVisionMode player isEqualTo 1) isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_LockNvg", false])}) exitWith {
                ["build"] call comspec_atak_native_fnc_lockScreen;
                ["front"] call comspec_atak_native_fnc_fx;
                // Toast et alertes (vibreur, commandement) remis devant l'écran recréé, comme fn_fx « raise ».
                [] call comspec_atak_native_fnc_toast;
                ["draw"] call comspec_atak_native_fnc_buzzAlert;
                ["draw"] call comspec_atak_native_fnc_fullAlert;
            };
            (uiNamespace getVariable ["COMSPEC_ATAK_LockClock", []]) params [["_clock", controlNull], ["_bat", controlNull], ["_net", controlNull], ["_hint", controlNull], ["_batIc", controlNull], ["_netIc", controlNull]];
            if (!isNull _clock) then { _clock ctrlSetText ([dayTime, "HH:MM"] call BIS_fnc_timeToString); }; // i18n:brut
            (call (uiNamespace getVariable ["COMSPEC_ATAK_LockStatus", { [] }])) params [["_bt", ""], ["_bc", [1, 1, 1, 1]], ["_nt", ""], ["_nc", [1, 1, 1, 1]], ["_bi", ""], ["_ni", ""]];
            if (!isNull _bat && {_bt isNotEqualTo ""}) then {
                _bat ctrlSetText _bt; // i18n:brut
                _bat ctrlSetTextColor _bc;
                _batIc ctrlSetText _bi; // i18n:brut
                _batIc ctrlSetTextColor _bc;
            };
            if (!isNull _net && {_nt isNotEqualTo ""}) then {
                _net ctrlSetText ((_nt) call comspec_atak_native_fnc_tr);
                _net ctrlSetTextColor _nc;
                _netIc ctrlSetText _ni; // i18n:brut
                _netIc ctrlSetTextColor _nc;
            };
            // Invite qui respire : fondu alterné d'une seconde.
            if (!isNull _hint) then { _hint ctrlSetFade ([0, 0.45] select (((floor diag_tickTime) mod 2) isEqualTo 0)); _hint ctrlCommit 0.9; };
            true
        };
        if (_enabled && {_interactive} && {missionNamespace getVariable ["COMSPEC_ATAK_LockPending", false]} && {call _usable}) then {
            ["show"] call comspec_atak_native_fnc_lockScreen;
        };
        true
    };
    case "unlock": {
        // Code demandé : clavier d'abord, l'écran reste verrouillé tant que le bon code n'est pas tapé. Clavier déjà
        // affiché (touche accueil de la coque) : rien, les chiffres déjà tapés restent.
        if ((uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false]) && {["needPin"] call comspec_atak_native_fnc_phoneOwn}) exitWith {
            if !(["up"] call comspec_atak_native_fnc_phonePad) then { ["ownPad"] call comspec_atak_native_fnc_phoneOwn; };
            false
        };
        missionNamespace setVariable ["COMSPEC_ATAK_LockPending", false];
        if !(uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false]) exitWith { false };
        uiNamespace setVariable ["COMSPEC_ATAK_LockUp", false];
        // Rappel « sans code » vu une fois : plus affiché aux ouvertures suivantes.
        if (uiNamespace getVariable ["COMSPEC_ATAK_LockTipShown", false]) then {
            uiNamespace setVariable ["COMSPEC_ATAK_LockTipShown", false];
            profileNamespace setVariable ["COMSPEC_ATAK_LockPinTip", true];
            [] call comspec_atak_native_fnc_saveProfile;
        };
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
        // Raccourci ou notification touchés avant le code : l'app s'ouvre maintenant.
        private _after = uiNamespace getVariable ["COMSPEC_ATAK_LockAfter", ""];
        uiNamespace setVariable ["COMSPEC_ATAK_LockAfter", ""];
        if (_after isNotEqualTo "") then { [{ [_this] call comspec_atak_native_fnc_navigate; }, _after] call comspec_atak_native_fnc_nextFrame; };
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
        private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
        private _dir = "\z\comspec_atak_native\addons\main\data\";
        private _lockDir = "\z\comspec_atak_native\addons\items\data\lock\";
        private _grp = _d ctrlCreate ["COMSPEC_RscControlsGroup", -1];
        _grp ctrlSetPosition [_dx, _dy, _dw, _dh];
        _grp ctrlCommit 0;
        private _made = [_grp];
        private _host = _grp;
        private _mk = {
            params ["_class", "_pos", ["_text", ""]];
            private _c = _d ctrlCreate [_class, -1, _host];
            _c ctrlSetPosition _pos;
            if (_text isNotEqualTo "") then { _c ctrlSetText ((_text) call comspec_atak_native_fnc_tr); };
            _c ctrlCommit 0;
            _made pushBack _c;
            _c
        };
        // Image étirée et teintée : carte, pastille, voile (COMSPEC_RscPhone : style 48, sans proportions imposées).
        private _tex = {
            params ["_name", "_pos", "_col"];
            private _c = ["COMSPEC_RscPhone", _pos, _lockDir + _name + ".paa"] call _mk;
            _c ctrlSetTextColor _col;
            _c ctrlEnable false;
            _c
        };
        // Couleurs : jetons du thème (fn_theme). JOUR : cartes claires ; SOMBRE et NUIT JVN : cartes sombres. Sur le fond
        // d'écran voilé, l'heure reste claire, sauf en NUIT JVN (jamais de blanc) ou sans fond d'écran en thème JOUR.
        private _th = [] call comspec_atak_native_fnc_theme;
        private _day = (_th get "name") isEqualTo "DAY";
        private _nvgTheme = (_th get "name") isEqualTo "NVG";
        private _nvgWorn = currentVisionMode player isEqualTo 1;
        uiNamespace setVariable ["COMSPEC_ATAK_LockNvg", _nvgWorn];
        private _wall = ["path", [profileNamespace getVariable ["COMSPEC_ATAK_Wallpaper", "topo"], profileNamespace getVariable ["COMSPEC_ATAK_WallBlur", false], _land]] call comspec_atak_native_fnc_wallPath;
        private _hasWall = _wall isNotEqualTo "";
        private _ink = [[0.97, 0.99, 0.97, 1], _th get "text"] select (_nvgTheme || {_day && {!_hasWall}});
        private _inkDim = [[0.86, 0.91, 0.88, 1], _th get "textDim"] select (_nvgTheme || {_day && {!_hasWall}});
        private _dateCol = [_th get "accentHi", _th get "accentInk"] select (_nvgTheme || {_day && {!_hasWall}});
        private _cardCol = ((_th get "surface") select [0, 3]) + [[0.86, 0.94] select _day];
        private _chipCol = [[0, 0, 0, 0.62], [0.985, 0.99, 0.985, 0.90]] select _day;
        private _chipInk = _th get "text";
        private _alert = _th get "alert";
        private _warn = _th get "warn";

        // Fond : couleur du thème, fond d'écran (teinté en vert phosphore en vision nocturne), voile.
        private _bg = ["COMSPEC_RscText", [0, 0, _dw, _dh]] call _mk;
        _bg ctrlSetBackgroundColor (_th get "bg0");
        if (_hasWall) then {
            private _wp = ["COMSPEC_RscPhone", [0, 0, _dw, _dh], _wall] call _mk;
            _wp ctrlEnable false;
            if (_nvgTheme || _nvgWorn) then { _wp ctrlSetTextColor [0.30, 0.62, 0.36, 1]; };
            ["veil", [0, 0, _dw, _dh], [1, 1, 1, [1, 0.8] select _day]] call _tex;
            // Sous des jumelles de vision nocturne, l'écran éblouit : fond nettement assombri.
            if (_nvgWorn) then { (["COMSPEC_RscText", [0, 0, _dw, _dh]] call _mk) ctrlSetBackgroundColor [0, 0, 0, 0.55]; };
        };
        // Tout l'écran déverrouille (clic, ou glissé : le relâchement sur le bouton compte comme un clic). Créé avant le
        // reste : la liste des notifications reste au-dessus et reçoit la molette.
        private _all = ["COMSPEC_RscButtonInvisible", [0, 0, _dw, _dh]] call _mk;
        _all ctrlAddEventHandler ["ButtonClick", {
            uiNamespace setVariable ["COMSPEC_ATAK_LockAfter", ""];
            [{ ["unlock"] call comspec_atak_native_fnc_lockScreen; }] call comspec_atak_native_fnc_nextFrame;
        }];

        // Bas de l'écran : invite, puis raccourcis et bouton de déverrouillage (une rangée en paysage, deux en portrait).
        private _margin = _pad * 1.5;
        private _hintH = _font * 1.15;
        private _hintY = _dh - _hintH - _pad * 0.5;
        // 4.1.1 : boutons rectangulaires aux proportions fixes, comme le reste du téléphone. Avant, la texture « pill »
        // étirée sur 46 % de la largeur (paysage) déformait ses bouts arrondis en une ellipse aplatie.
        private _btnH = _font * 2.1;
        private _gap = _pad * 0.8;
        private _rowW = _dw - _margin * 2;
        private _needPin = ["needPin"] call comspec_atak_native_fnc_phoneOwn;
        private _bottom = _hintY - _pad * 0.5;
        private _unlockRect = [];
        private _qaRects = [];
        // Largeurs plafonnées (en hauteurs de police, converties en largeur d'écran) : jamais une barre d'un bord à l'autre.
        private _uCap = _font * 12 / _ratio;
        private _qCap = _font * 8 / _ratio;
        if (_land) then {
            // Une rangée centrée : CARTE | DÉVERROUILLER | MESSAGES, même hauteur, même ligne.
            private _qaW = (_rowW * 0.24) min _qCap;
            private _uw = (_rowW - (_qaW + _gap) * 2) min _uCap;
            private _x0 = _margin + (_rowW - _uw - (_qaW + _gap) * 2) / 2;
            private _by = _bottom - _btnH;
            _qaRects = [[_x0, _by, _qaW, _btnH], [_x0 + _qaW + _gap * 2 + _uw, _by, _qaW, _btnH]];
            _unlockRect = [_x0 + _qaW + _gap, _by, _uw, _btnH];
            _bottom = _by;
        } else {
            // Deux rangées de même largeur, centrées : raccourcis au-dessus, déverrouillage dessous.
            private _w = _rowW min _uCap;
            private _x0 = _margin + (_rowW - _w) / 2;
            private _uy = _bottom - _btnH;
            private _qy = _uy - _gap - _btnH;
            private _qaW = (_w - _gap) / 2;
            _qaRects = [[_x0, _qy, _qaW, _btnH], [_x0 + _qaW + _gap, _qy, _qaW, _btnH]];
            _unlockRect = [_x0, _uy, _w, _btnH];
            _bottom = _qy;
        };
        // Fond plein et filet d'un pixel ; renvoie [fond, [filets]].
        private _box = {
            params ["_pos", "_fill", "_edge"];
            _pos params ["_bx", "_by", "_bw", "_bh"];
            private _f = ["COMSPEC_RscText", _pos] call _mk;
            _f ctrlSetBackgroundColor _fill;
            _f ctrlEnable false;
            private _px = pixelW; private _py = pixelH;
            private _edges = [[_bx, _by, _bw, _py], [_bx, _by + _bh - _py, _bw, _py], [_bx, _by, _px, _bh], [_bx + _bw - _px, _by, _px, _bh]] apply {
                private _e = ["COMSPEC_RscText", _x] call _mk;
                _e ctrlSetBackgroundColor _edge;
                _e ctrlEnable false;
                _e
            };
            [_f, _edges]
        };
        private _goTo = {
            params ["_c", "_page"];
            _c setVariable ["page", _page];
            _c ctrlAddEventHandler ["ButtonClick", {
                params ["_c"];
                uiNamespace setVariable ["COMSPEC_ATAK_LockAfter", _c getVariable ["page", ""]];
                [{ ["unlock"] call comspec_atak_native_fnc_lockScreen; }] call comspec_atak_native_fnc_nextFrame;
            }];
        };
        // État d'un bouton (survol, focus clavier, pressé) : fond et filets recolorés. Données rangées sur le bouton.
        private _wire = {
            params ["_b", "_fill", "_edges", "_cols"];
            _b setVariable ["cs_lk", [_fill, _edges, _cols]];
            private _paint = {
                params ["_b", "_st"];
                (_b getVariable ["cs_lk", []]) params [["_f", controlNull], ["_es", []], ["_c", []]];
                if (isNull _f) exitWith {};
                // _c : [fond, fond survolé, fond pressé, filet, filet focus]
                private _i = switch (_st) do { case "down": { 2 }; case "hover"; case "focus": { 1 }; default { 0 }; };
                _f ctrlSetBackgroundColor (_c select _i);
                { _x ctrlSetBackgroundColor (_c select ([3, 4] select (_i > 0))); } forEach _es;
            };
            _b setVariable ["cs_paint", _paint];
            _b ctrlAddEventHandler ["MouseEnter", { params ["_b"]; [_b, "hover"] call (_b getVariable "cs_paint"); }];
            _b ctrlAddEventHandler ["MouseExit", { params ["_b"]; [_b, ["idle", "focus"] select (_b getVariable ["cs_focus", false])] call (_b getVariable "cs_paint"); }];
            _b ctrlAddEventHandler ["SetFocus", { params ["_b"]; _b setVariable ["cs_focus", true]; [_b, "focus"] call (_b getVariable "cs_paint"); }];
            _b ctrlAddEventHandler ["KillFocus", { params ["_b"]; _b setVariable ["cs_focus", false]; [_b, "idle"] call (_b getVariable "cs_paint"); }];
            _b ctrlAddEventHandler ["ButtonDown", { params ["_b"]; [_b, "down"] call (_b getVariable "cs_paint"); }];
            _b ctrlAddEventHandler ["ButtonUp", { params ["_b"]; [_b, "hover"] call (_b getVariable "cs_paint"); }];
        };
        // Raccourcis : rectangle neutre (pastille du thème), icône et libellé au contraste du texte courant.
        private _chipHi = ((_th get "surfaceHi") select [0, 3]) + [[0.80, 0.96] select _day];
        {
            _x params ["_qi", "_ql", "_qp"];
            (_qaRects select _forEachIndex) params ["_qx", "_qy", "_qw", "_qh"];
            ([[_qx, _qy, _qw, _qh], _chipCol, _th get "border"] call _box) params ["_qf", "_qe"];
            private _qis = _qh * 0.56;
            private _qiw = _qis / _ratio;
            private _ql2 = ["COMSPEC_RscTextCenter", [_qx + _qiw * 0.6, _qy, _qw - _qiw * 0.6, _qh], _ql] call _mk;
            _ql2 ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
            _ql2 ctrlSetFontHeight ((_fs * 0.95) max COMSPEC_ATAK_FontMin);
            _ql2 ctrlSetTextColor _chipInk;
            private _qt = ctrlTextWidth _ql2;
            private _qic = ["COMSPEC_RscPhone", [_qx + _qw / 2 + _qiw * 0.3 - _qt / 2 - _qiw * 1.2, _qy + (_qh - _qis) / 2, _qiw, _qis], format ["%1%2.paa", _dir, _qi]] call _mk;
            _qic ctrlSetTextColor _chipInk;
            _qic ctrlEnable false;
            private _qb = ["COMSPEC_RscButtonInvisible", [_qx, _qy, _qw, _qh]] call _mk;
            [_qb, _qp] call _goTo;
            [_qb, _qf, _qe, [_chipCol, _chipHi, _chipHi, _th get "border", _th get "textDim"]] call _wire;
        } forEach [["app_map", "CARTE", "MAP"], ["app_chat", "MESSAGES", "CHAT"]];
        // Déverrouiller : bouton tonal à l'accent du joueur (fond teinté, encre d'accent, filet d'accent), sobre et lisible
        // dans les trois thèmes ; cadenas fermé quand un code sera demandé. Survolé, focus ou pressé : fond plus teinté, filet d'accent plein.
        _unlockRect params ["_ux", "_uy", "_uw", "_uh"];
        private _acc = _th get "accent";
        ([_unlockRect, _th get "accentSoft", (_acc select [0, 3]) + [0.55]] call _box) params ["_uf", "_ue"];
        private _uis = _uh * 0.5;
        private _uiw = _uis / _ratio;
        private _ul = ["COMSPEC_RscTextCenter", [_ux + _uiw * 0.6, _uy, _uw - _uiw * 0.6, _uh], ["DÉVERROUILLER", "SAISIR LE CODE"] select _needPin] call _mk;
        _ul ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
        _ul ctrlSetFontHeight _font;
        _ul ctrlSetTextColor (_th get "onAccentSoft");
        _ul ctrlEnable false;
        private _uic = ["COMSPEC_RscPhone", [_ux + _uw / 2 + _uiw * 0.3 - (ctrlTextWidth _ul) / 2 - _uiw * 1.2, _uy + (_uh - _uis) / 2, _uiw, _uis], format ["%1%2.paa", _lockDir, ["lock_open", "lock_closed"] select _needPin]] call _mk;
        _uic ctrlSetTextColor (_th get "onAccentSoft");
        _uic ctrlEnable false;
        uiNamespace setVariable ["COMSPEC_ATAK_LockBtn", _ul];
        private _ub = ["COMSPEC_RscButtonInvisible", _unlockRect] call _mk;
        _ub ctrlAddEventHandler ["ButtonClick", {
            uiNamespace setVariable ["COMSPEC_ATAK_LockAfter", ""];
            [{ ["unlock"] call comspec_atak_native_fnc_lockScreen; }] call comspec_atak_native_fnc_nextFrame;
        }];
        [_ub, _uf, _ue, [_th get "accentSoft", _th get "accentSoftHi", _th get "accentSoftHi", (_acc select [0, 3]) + [0.55], _acc]] call _wire;
        // Invite. Le rappel « sans code » n'apparaît qu'une fois (profil COMSPEC_ATAK_LockPinTip, marqué au déverrouillage) :
        // il restait affiché à chaque ouverture ; le réglage du code reste dans Réglages > Personnalisation.
        private _hasPin = ["hasPin"] call comspec_atak_native_fnc_phoneOwn;
        private _tip = !_hasPin && {!(profileNamespace getVariable ["COMSPEC_ATAK_LockPinTip", false])};
        uiNamespace setVariable ["COMSPEC_ATAK_LockTipShown", _tip];
        private _hint = ["COMSPEC_RscTextCenter", [0, _hintY, _dw, _hintH], ["Touchez ou glissez vers le haut pour déverrouiller", "Sans code : réglez-en un dans Réglages > Personnalisation"] select _tip] call _mk;
        _hint ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
        _hint ctrlSetTextColor _inkDim;
        _hint ctrlEnable false;

        // Colonnes : en paysage, heure et état à gauche, notifications à droite ; en portrait, les unes sous les autres.
        private _leftW = [_rowW, _rowW * 0.40] select _land;
        private _y = _dh * ([0.05, 0.09] select _land);
        private _clockH = _font * 4.2;
        private _clock = ["COMSPEC_RscTextCenter", [_margin, _y, _leftW, _clockH], [dayTime, "HH:MM"] call BIS_fnc_timeToString] call _mk;
        // Style de l'heure (Réglages > Personnalisation, COMSPEC_ATAK_LockClockStyle) : fine, grasse, militaire (accent).
        private _cs = profileNamespace getVariable ["COMSPEC_ATAK_LockClockStyle", "light"];
        _clock ctrlSetFont (createHashMapFromArray [["bold", (COMSPEC_ATAK_FontSet select 1)], ["mil", "EtelkaMonospaceProBold"]] getOrDefault [_cs, (COMSPEC_ATAK_FontSet select 2)]);
        _clock ctrlSetFontHeight ((_font * ([4.1, 3.4] select (_cs isEqualTo "mil"))) max COMSPEC_ATAK_FontMin);
        _clock ctrlSetTextColor ([_ink, _dateCol] select (_cs isEqualTo "mil"));
        _clock ctrlEnable false;
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
        private _date = ["COMSPEC_RscTextCenter", [_margin, _y, _leftW, _font * 1.3], format ["%1 · %2", _dateTxt, toUpper _world]] call _mk;
        _date ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
        _date ctrlSetFontHeight ((_fs * 1.02) max COMSPEC_ATAK_FontMin);
        _date ctrlSetTextColor _dateCol;
        _date ctrlEnable false;
        _y = _y + _font * 1.3 + _pad * 0.8;
        // Pastilles : météo, batterie, réseau (ces deux dernières remises à jour chaque seconde par « tick »).
        uiNamespace setVariable ["COMSPEC_ATAK_LockStatus", compile format ['
            private _lq = [] call comspec_atak_native_fnc_linkQuality;
            private _bat = floor (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]);
            private _bars = _lq getOrDefault ["bars", 0];
            private _air = [] call comspec_atak_native_fnc_airplaneMode;
            private _lvl = (([10, 25, 50, 75, 100] select { _x >= _bat }) param [0, 100]);
            [
                format ["%%1 %%%%", _bat], [%1, %2] select (_bat <= 12),
                if (_air) then { "Mode avion" } else { format ["%%1/4", _bars] }, [%1, %3] select (_bars <= 1 || _air),
                format ["\z\comspec_atak_native\addons\main\data\bat_%%1.paa", _lvl],
                format ["\z\comspec_atak_native\addons\main\data\sig_%%1.paa", [(_bars max 0) min 4, 0] select _air]
            ]
        ', str _chipInk, str _alert, str _warn]];
        (call (uiNamespace getVariable "COMSPEC_ATAK_LockStatus")) params ["_batTxt", "_batCol", "_netTxt", "_netCol", "_batIcon", "_netIcon"];
        ([] call comspec_atak_native_fnc_weather) params ["_wxT", "_wxS", "_wxD"];
        private _chipH = _font * 1.6;
        private _chipGap = _pad * 0.5;
        private _chipW = (_leftW - _chipGap * 2) / 3;
        private _chips = [];
        {
            _x params ["_txt", "_col", "_icon"];
            private _cx = _margin + _forEachIndex * (_chipW + _chipGap);
            ["pill", [_cx, _y, _chipW, _chipH], _chipCol] call _tex;
            private _cis = _chipH * 0.6;
            private _ciw = _cis / _ratio;
            private _cic = ["COMSPEC_RscPhone", [_cx + _pad * 0.7, _y + (_chipH - _cis) / 2, _ciw, _cis], _icon] call _mk;
            _cic ctrlSetTextColor _col;
            _cic ctrlEnable false;
            private _tx = _cx + _pad * 0.7 + _ciw;
            private _c = ["COMSPEC_RscTextCenter", [_tx, _y, _cx + _chipW - _tx - _pad * 0.3, _chipH], _txt] call _mk;
            _c ctrlSetFontHeight ((_fs * 0.92) max COMSPEC_ATAK_FontMin);
            _c ctrlSetTextColor _col;
            _c ctrlEnable false;
            _chips pushBack [_c, _cic];
        } forEach [
            [format ["%1 °C · %2 %3", _wxT, _wxD, round _wxS], _chipInk, _dir + "ui_weather.paa"],
            [_batTxt, _batCol, _batIcon],
            [_netTxt, _netCol, _netIcon]
        ];
        _y = _y + _chipH + _pad;
        uiNamespace setVariable ["COMSPEC_ATAK_LockClock", [_clock, (_chips select 1) select 0, (_chips select 2) select 0, _hint, (_chips select 1) select 1, (_chips select 2) select 1]];

        // Notifications, groupées par app (3.1.0) : une carte par source, la plus récente en tête, avec le nombre de
        // notifications du groupe, l'heure et le dernier texte ; point d'accent devant les groupes qui ont du non lu.
        // Liste défilante (molette) tant qu'il y a des groupes : elle s'arrête au-dessus des raccourcis.
        // Alertes « vibreur seul » (fn_notify mode vibrate) : jamais affichées sur l'écran verrouillé.
        private _hist = (_s getOrDefault ["notifHistory", []]) select { !(_x getOrDefault ["discreet", false]) };
        uiNamespace setVariable ["COMSPEC_ATAK_LockNotifN", call _notifSig];
        private _unreadN = _s getOrDefault ["notifUnread", 0];
        private _groups = [];
        private _byTitle = createHashMap;
        private _total = count _hist;
        for "_i" from (_total - 1) to 0 step -1 do {
            private _n = _hist select _i;
            private _meta = [_n] call comspec_atak_native_fnc_notifMeta;
            private _k = toLower (_meta get "title");
            private _fresh = _i >= (_total - _unreadN);
            if (_k in _byTitle) then {
                private _g = _groups select (_byTitle get _k);
                _g set [2, (_g select 2) + (_n getOrDefault ["count", 1])];
                if (_fresh) then { _g set [3, true]; };
            } else {
                _byTitle set [_k, count _groups];
                _groups pushBack [_n, _meta, _n getOrDefault ["count", 1], _fresh];
            };
        };
        // 12 cartes au plus : l'écran est créé d'un coup (à chaque notification reçue) ; la suite est au centre de notifications.
        private _maxCards = 12;
        private _lx = [_margin, _margin + _leftW + _pad * 1.5] select _land;
        private _lw = [_rowW, _rowW - _leftW - _pad * 1.5] select _land;
        private _ly = [_y, _pad * 1.2] select _land;
        private _head = ["COMSPEC_RscText", [_lx, _ly, _lw, _font * 1.1], ["AUCUNE NOTIFICATION", format ["NOTIFICATIONS · %1", _total]] select (_total > 0)] call _mk;
        _head ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
        _head ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
        _head ctrlSetTextColor _inkDim;
        _head ctrlEnable false;
        _ly = _ly + _font * 1.15;
        private _more = (count _groups) - _maxCards;
        private _footH = [0, _font * 1.1] select (_more > 0);
        private _listH = (_bottom - _pad * 0.6 - _ly - _footH) max (_font * 2);
        private _list = ["COMSPEC_RscControlsGroup", [_lx, _ly, _lw, _listH]] call _mk;
        _host = _list;
        private _cw = _lw - 0.014;
        private _yy2 = 0;
        private _titleF = (_fs * 1.02) max COMSPEC_ATAK_FontMin;
        private _bodyF = (_fs * 0.92) max COMSPEC_ATAK_FontMin;
        private _timeF = (_fs * 0.85) max COMSPEC_ATAK_FontMin;
        private _cardInk = _th get "text";
        private _cardDim = _th get "textDim";
        private _e = { params ["_v"]; (_v regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"] };
        {
            _x params ["_n", "_meta", "_times", "_fresh"];
            private _rgb = _meta get "rgb";
            private _tileH = _font * 1.6;
            private _tileW = _tileH / _ratio;
            private _inPad = _pad * 0.8;
            private _tx = _inPad + _tileW + _inPad;
            private _timeW = _font * 2.2 / _ratio;
            private _textW = _cw - _tx - _inPad;
            // Texte mesuré sur un contrôle d'essai, deux lignes au plus (coupé avec « … ») : la carte prend sa hauteur.
            private _plain = ((_meta get "body") select [0, 200]) call comspec_atak_native_fnc_tr;
            private _titleH = _titleF * 1.2;
            private _probe = ["COMSPEC_RscStructuredText", [_tx, _yy2, _textW, _bodyF * 2.6]] call _mk;
            _probe ctrlSetFontHeight _bodyF;
            private _markup = { params ["_t"]; parseText format ["<t color='%1'>%2</t>", _th get "hexTextDim", [_t] call _e] };
            private _final = _plain;
            _probe ctrlSetStructuredText ([_final] call _markup); // i18n:brut
            private _bodyH = ctrlTextHeight _probe;
            private _two = _bodyF * 2.5;
            if (_bodyH > _two) then {
                private _codes = toArray _plain;
                private _keep = (floor ((count _codes) * _two / _bodyH)) max 1;
                while {_keep > 1} do {
                    _final = (toString (_codes select [0, _keep])) + "…";
                    _probe ctrlSetStructuredText ([_final] call _markup); // i18n:brut
                    _bodyH = ctrlTextHeight _probe;
                    if (_bodyH <= _two) exitWith {};
                    _keep = _keep - 3;
                };
            };
            ctrlDelete _probe;
            _made deleteAt (_made find _probe);
            if (_plain isEqualTo "") then { _bodyH = 0; };
            private _ch = (_inPad * 1.2 + _titleH + _bodyH) max (_tileH + _inPad * 1.2);
            ["card", [0, _yy2, _cw, _ch], _cardCol] call _tex;
            ["key", [_inPad, _yy2 + (_ch - _tileH) / 2, _tileW, _tileH], (_rgb select [0, 3]) + [0.3]] call _tex;
            private _pic = ["COMSPEC_RscPhone", [_inPad + _tileW * 0.2, _yy2 + (_ch - _tileH) / 2 + _tileH * 0.2, _tileW * 0.6, _tileH * 0.6], _meta get "icon"] call _mk;
            _pic ctrlSetTextColor _cardInk;
            _pic ctrlEnable false;
            private _title = ["COMSPEC_RscText", [_tx, _yy2 + _inPad * 0.6, _textW - _timeW, _titleH], [_meta get "title", format ["%1 ×%2", _meta get "title", _times]] select (_times > 1)] call _mk;
            _title ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
            _title ctrlSetFontHeight _titleF;
            _title ctrlSetTextColor _cardInk;
            _title ctrlEnable false;
            private _time = ["COMSPEC_RscTextRight", [_cw - _inPad - _timeW, _yy2 + _inPad * 0.6, _timeW, _titleH], _n getOrDefault ["time", ""]] call _mk;
            _time ctrlSetFontHeight _timeF;
            _time ctrlSetTextColor _cardDim;
            _time ctrlEnable false;
            if (_fresh) then {
                private _ds = _font * 0.42;
                ["dot", [_cw - _inPad - _timeW - _ds / _ratio, _yy2 + _inPad * 0.6 + (_titleH - _ds) / 2, _ds / _ratio, _ds], _th get "accent"] call _tex;
            };
            if (_final isNotEqualTo "") then {
                private _b2 = ["COMSPEC_RscStructuredText", [_tx, _yy2 + _inPad * 0.6 + _titleH, _textW, _bodyH + _bodyF * 0.25]] call _mk;
                _b2 ctrlSetFontHeight _bodyF;
                _b2 ctrlEnable false;
                _b2 ctrlSetStructuredText ([_final] call _markup); // i18n:brut
            };
            // Toucher une notification : déverrouille, puis ouvre son app quand on la connaît.
            [["COMSPEC_RscButtonInvisible", [0, _yy2, _cw, _ch]] call _mk, _meta get "page"] call _goTo;
            _yy2 = _yy2 + _ch + _pad * 0.45;
        } forEach (_groups select [0, _maxCards]);
        _host = _grp;
        if (_more > 0) then {
            private _m = ["COMSPEC_RscTextCenter", [_lx, _ly + _listH, _lw, _footH], format ["+ %1 autre(s) dans le centre de notifications", _more]] call _mk;
            _m ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
            _m ctrlSetTextColor _inkDim;
            _m ctrlEnable false;
        };
        uiNamespace setVariable ["COMSPEC_ATAK_LockCtrls", _made];
        // Clavier du code gardé devant quand l'écran de verrouillage se redessine (notification reçue).
        if (["up"] call comspec_atak_native_fnc_phonePad) then { ["draw"] call comspec_atak_native_fnc_phonePad; };
        // À l'ouverture : volet rapide refermé, effets d'écran (fêlures, filtre de nuit) remis devant.
        if (_act isEqualTo "show") then {
            missionNamespace setVariable ["COMSPEC_ATAK_PinOk", false];
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
