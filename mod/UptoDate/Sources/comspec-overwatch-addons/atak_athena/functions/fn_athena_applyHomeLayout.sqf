/*
    Tuile Athena : formulaire connexion natif (hors liaison) ou fiche + actions (liée).
    Réserve toujours une zone safe au-dessus des boutons bas ; aère le bloc Appairer.
*/
private _group = [] call comspec_overwatch_atak_athena_fnc_athena_resolveAthenaGroup;
if (isNull _group || {!ctrlShown _group}) exitWith {};
private _page = toLower ((["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings) param [0, ""]);
if (_page isNotEqualTo "" && {_page isNotEqualTo "athena"}) exitWith {};

(ctrlPosition _group) params ["", "", "_w", "_h"];
if (_w < 0.04 || {_h < 0.08}) exitWith {};

private _pad = ((_w * 0.05) max 0.004);
private _gap = ((_h * 0.014) max 0.003);
private _titleH = ((_h * 0.072) max 0.018);
private _accentH = ((_h * 0.008) max 0.002);
private _btnH = ((_h * 0.08) max 0.024);
private _fullW = (_w - (2 * _pad)) max 0.04;
private _halfW = ((_fullW - _gap) / 2) max 0.04;
private _actionH = ((_h * 0.075) max 0.022);
private _rowH = ((_h * 0.055) max 0.018);
private _editH = ((_h * 0.058) max 0.019);

private _fncPos = {
    params ["_grp", "_idc", "_rect", "_show"];
    private _c = [_grp, _idc] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
    if (isNull _c) exitWith { controlNull };
    _c ctrlSetPosition _rect;
    _c ctrlShow _show;
    _c ctrlEnable _show;
    _c ctrlCommit 0;
    _c
};

private _linked = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
private _steamRaw = missionNamespace getVariable ["COMSPEC_SteamLinked", nil];
private _steamOk = if (isNil "_steamRaw") then { false } else { _steamRaw isEqualTo true };
private _athenaName = trim (str (missionNamespace getVariable ["comspec_profile_name", ""]));
if (_athenaName isEqualTo "" || {(toLower _athenaName) in ["<null>", "any", "nil"]}) then { _athenaName = ""; };
private _armaName = if (!isNull player) then { trim (name player) } else { "" };
if (_athenaName isNotEqualTo "" && {_armaName isNotEqualTo ""} && {(toLower _athenaName) isEqualTo (toLower _armaName)}) then {
    _athenaName = "";
};
// Compte connecté = prêt + identité Athena. Steam facultatif.
private _allOk = _linked && {_athenaName isNotEqualTo ""};
private _authState = missionNamespace getVariable ["comspec_overwatch_auth_state", ""];
private _ready = _authState isEqualTo "READY" || {_linked};

[_group, 9700, [0, 0, _w, _titleH], true] call _fncPos;
private _y = _titleH + _accentH + _gap;
[_group, 9734, [_pad, _y, _fullW, _btnH], true] call _fncPos;
_y = _y + _btnH + _gap;

private _bodyH = (_h - _y - _pad) max 0.08;

if (_allOk) then {
    // Deux rangées de boutons fixes en bas — la fiche scrollable s’arrête au-dessus.
    private _footerH = (2 * _actionH) + (3 * _gap);
    private _statusHostH = (_bodyH - _footerH) max 0.06;
    [_group, 9698, [_pad, _y, _fullW, _statusHostH], true] call _fncPos;
    private _statusTxt = [_group, 9701] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
    if (!isNull _statusTxt) then {
        private _innerH = (ctrlTextHeight _statusTxt) max _statusHostH;
        _statusTxt ctrlSetPosition [0, 0, _fullW, _innerH];
        _statusTxt ctrlShow true;
        _statusTxt ctrlCommit 0;
    };
    [_group, 9790, [0, _y, _w, _bodyH], false] call _fncPos;
    private _ay = _y + _statusHostH + _gap;
    [_group, 9805, [_pad, _ay, _halfW, _actionH], true] call _fncPos;
    [_group, 9806, [_pad + _halfW + _gap, _ay, _halfW, _actionH], true] call _fncPos;
    [_group, 9807, [_pad, _ay + _actionH + _gap, _fullW, _actionH], true] call _fncPos;
} else {
    [_group, 9698, [0, 0, 0, 0], false] call _fncPos;
    [_group, 9701, [0, 0, 0, 0], false] call _fncPos;
    [_group, 9790, [0, _y, _w, _bodyH], true] call _fncPos;
    [_group, 9805, [0, 0, 0, 0], false] call _fncPos;
    [_group, 9806, [0, 0, 0, 0], false] call _fncPos;
    [_group, 9807, [0, 0, 0, 0], false] call _fncPos;

    // Formulaire Appairer : hauteurs selon l’état (READY = plus de lignes d’état).
    private _form = [_group, 9790] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
    if (!isNull _form) then {
        (ctrlPosition _form) params ["", "", "_fw", "_fh"];
        private _fPad = ((_fw * 0.04) max 0.003);
        private _fGap = ((_fh * 0.012) max 0.0025);
        private _fFull = (_fw - (2 * _fPad)) max 0.04;
        private _fHalf = ((_fFull - _fGap) / 2) max 0.03;
        // Hauteurs plus généreuses : les aides 2 lignes ne doivent plus être coupées.
        private _hintH = if (_ready) then { (_fh * 0.17) max 0.040 } else { (_fh * 0.15) max 0.038 };
        private _pairH = (_fh * 0.12) max 0.030;
        private _acctH = (_fh * 0.12) max 0.032;
        private _eH = (_fh * 0.052) max 0.017;
        private _bH = (_fh * 0.050) max 0.016;
        private _fy = _fGap;

        private _fncForm = {
            params ["_idc", "_rect", ["_show", true]];
            private _c = [_group, _idc] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
            if (isNull _c) exitWith {};
            _c ctrlSetPosition _rect;
            if (_show) then { _c ctrlShow true; _c ctrlEnable true; };
            _c ctrlCommit 0;
        };

        [_group, 9791, [_fPad, _fy, _fFull, _hintH], true] call _fncPos;
        _fy = _fy + _hintH + _fGap;
        [_group, 9802, [_fPad, _fy, _fFull, _pairH], true] call _fncPos;
        _fy = _fy + _pairH + _fGap;
        [_group, 9799, [_fPad, _fy, _fFull * 0.60, _eH], true] call _fncPos;
        [_group, 9800, [_fPad + _fFull * 0.62, _fy, _fFull * 0.38, _eH], true] call _fncPos;
        _fy = _fy + _eH + _fGap;

        private _showEnter = _ready || {_linked};
        [_group, 9801, [_fPad, _fy, _fFull, _bH], _showEnter] call _fncPos;
        if (_showEnter) then { _fy = _fy + _bH + _fGap; };

        [_group, 9804, [_fPad, _fy, _fFull, _acctH], true] call _fncPos;
        _fy = _fy + _acctH + _fGap;
        [_group, 9792, [_fPad, _fy, _fFull, _eH], true] call _fncPos;
        _fy = _fy + _eH + (_fGap * 0.8);

        // Choix du mode : Mot de passe | Code e-mail
        [_group, 9810, [_fPad, _fy, _fHalf, _bH], true] call _fncPos;
        [_group, 9811, [_fPad + _fHalf + _fGap, _fy, _fHalf, _bH], true] call _fncPos;
        _fy = _fy + _bH + _fGap;

        private _loginMode = missionNamespace getVariable ["comspec_overwatch_auth_login_mode", ""];
        if (!(_loginMode isEqualType "")) then { _loginMode = ""; };
        _loginMode = toLower _loginMode;
        if (!(_loginMode in ["", "password", "otp"])) then { _loginMode = ""; };
        // Compat : ancien drapeau OTP
        if (_loginMode isEqualTo "" && {missionNamespace getVariable ["comspec_overwatch_auth_otp_mode", false]}) then {
            _loginMode = "otp";
            missionNamespace setVariable ["comspec_overwatch_auth_login_mode", "otp", false];
        };
        missionNamespace setVariable ["comspec_overwatch_auth_otp_mode", _loginMode isEqualTo "otp", false];

        private _modePass = _loginMode isEqualTo "password";
        private _modeOtp = _loginMode isEqualTo "otp";

        private _btnModePass = [_group, 9810] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
        private _btnModeOtp = [_group, 9811] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
        if (!isNull _btnModePass) then {
            _btnModePass ctrlSetText "Mot de passe";
            _btnModePass ctrlSetBackgroundColor (if (_modePass) then { [0.06, 0.28, 0.14, 1] } else { [0.16, 0.16, 0.16, 1] });
        };
        if (!isNull _btnModeOtp) then {
            _btnModeOtp ctrlSetText "Code e-mail";
            _btnModeOtp ctrlSetBackgroundColor (if (_modeOtp) then { [0.06, 0.28, 0.14, 1] } else { [0.16, 0.16, 0.16, 1] });
        };

        // Champ conditionnel + boutons d’action
        if (_modePass) then {
            [_group, 9793, [_fPad, _fy, _fFull, _eH], true] call _fncPos;
            [_group, 9794, [_fPad, _fy, _fFull, _eH], false] call _fncPos;
            _fy = _fy + _eH + _fGap;
            [_group, 9795, [_fPad, _fy, _fFull, _bH], true] call _fncPos;
            [_group, 9796, [_fPad, _fy, _fHalf, _bH], false] call _fncPos;
            [_group, 9797, [_fPad + _fHalf + _fGap, _fy, _fHalf, _bH], false] call _fncPos;
            _fy = _fy + _bH + _fGap;
        } else {
            if (_modeOtp) then {
                [_group, 9793, [_fPad, _fy, _fFull, _eH], false] call _fncPos;
                [_group, 9794, [_fPad, _fy, _fFull, _eH], true] call _fncPos;
                _fy = _fy + _eH + _fGap;
                private _otpAsk = [_group, 9796] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
                if (!isNull _otpAsk) then { _otpAsk ctrlSetText "Recevoir le code"; };
                [_group, 9795, [_fPad, _fy, _fFull, _bH], false] call _fncPos;
                [_group, 9796, [_fPad, _fy, _fHalf, _bH], true] call _fncPos;
                [_group, 9797, [_fPad + _fHalf + _fGap, _fy, _fHalf, _bH], true] call _fncPos;
                _fy = _fy + _bH + _fGap;
            } else {
                [_group, 9793, [_fPad, _fy, _fFull, _eH], false] call _fncPos;
                [_group, 9794, [_fPad, _fy, _fFull, _eH], false] call _fncPos;
                [_group, 9795, [_fPad, _fy, _fFull, _bH], false] call _fncPos;
                [_group, 9796, [_fPad, _fy, _fHalf, _bH], false] call _fncPos;
                [_group, 9797, [_fPad + _fHalf + _fGap, _fy, _fHalf, _bH], false] call _fncPos;
            };
        };

        [_group, 9798, [_fPad, _fy, _fFull, _bH], true] call _fncPos;
        _fy = _fy + _bH + _fGap;
        [_group, 9803, [_fPad, _fy, _fFull, _bH], true] call _fncPos;

        // Entrée = valider le champ actif (mot de passe / code / appairage)
        {
            private _ed = [_group, _x] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
            if (isNull _ed) then { continue };
            if (!isNil {_ed getVariable "COMSPEC_AuthEnterWired"}) then { continue };
            _ed setVariable ["COMSPEC_AuthEnterWired", true];
            _ed ctrlAddEventHandler ["KeyDown", {
                params ["_ctrl", "_key"];
                if (!(_key isEqualTo 28 || {_key isEqualTo 156})) exitWith { false };
                private _idc = ctrlIDC _ctrl;
                switch (_idc) do {
                    case 9799: { ["pair"] call comspec_overwatch_atak_athena_fnc_athena_authAction; };
                    case 9793: { ["password"] call comspec_overwatch_atak_athena_fnc_athena_authAction; };
                    case 9794: { ["otp_ok"] call comspec_overwatch_atak_athena_fnc_athena_authAction; };
                    case 9792: {
                        private _mode = missionNamespace getVariable ["comspec_overwatch_auth_login_mode", ""];
                        if (_mode isEqualTo "password") then {
                            ["password"] call comspec_overwatch_atak_athena_fnc_athena_authAction;
                        } else {
                            if (_mode isEqualTo "otp") then {
                                ["otp_ok"] call comspec_overwatch_atak_athena_fnc_athena_authAction;
                            };
                        };
                    };
                    default {};
                };
                true
            }];
        } forEach [9792, 9793, 9794, 9799];
    };
};

{
    [_group, _x, [0, 0, 0, 0], false] call _fncPos;
} forEach [9761, 9762, 9763, 9764, 9770, 9771, 9772, 9773, 9710, 9711, 9712, 9760, 9765, 9766, 9735];

private _linkBtn = [_group, 9734] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
if (!isNull _linkBtn) then {
    if (_allOk) then {
        private _linkState = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
        if (_linkState isEqualTo "linked") then {
            _linkBtn ctrlSetText "Connecté — canal ouvert";
            _linkBtn ctrlSetBackgroundColor [0.06, 0.28, 0.14, 1];
        } else {
            _linkBtn ctrlSetText "Compte connecté — canal interrompu";
            _linkBtn ctrlSetBackgroundColor [0.32, 0.18, 0.06, 1];
        };
    } else {
        if (_linked) then {
            _linkBtn ctrlSetText "En liaison — compte à ouvrir";
            _linkBtn ctrlSetBackgroundColor [0.28, 0.18, 0.06, 1];
        } else {
            if (_ready) then {
                _linkBtn ctrlSetText "Compte trouvé — Entrer";
                _linkBtn ctrlSetBackgroundColor [0.08, 0.22, 0.18, 1];
            } else {
                _linkBtn ctrlSetText "Connexion Athena";
                _linkBtn ctrlSetBackgroundColor [0.16, 0.16, 0.16, 1];
            };
        };
    };
};

// Entrer reste dans le formulaire (9790) — visible seulement hors liaison complète
private _enter = [_group, 9801] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
if (!isNull _enter) then {
    private _showEnter = !_allOk && {_ready || {_linked}};
    _enter ctrlShow _showEnter;
    _enter ctrlEnable _showEnter;
};
