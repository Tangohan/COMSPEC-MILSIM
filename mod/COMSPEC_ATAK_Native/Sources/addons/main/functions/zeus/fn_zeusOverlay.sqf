/*
    Effets Zeus sur l'écran du téléphone, par-dessus les dégâts (appelé à la fin de fn_deviceOverlay) :
      verrou à distance (fn_zeusFx "lock") : écran rouge sombre, cadenas, message du commandement, clics bloqués ;
      écran qui bugue (fn_zeusClient "glitch", uiNamespace COMSPEC_ATAK_ZeusGlitch) : bandes de couleur et coupures
      redessinées toutes les 0,1 s par une boucle qui s'arrête seule ;
      surchauffe (fn_zeusFx "heat" 1-2) : voile orange et bandeau ; niveau 2, l'écran saute de temps en temps.
    Renvoie true si un effet est affiché (fn_deviceOverlay rend alors le focus au bouton hors écran).
*/
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { false };
private _rect = ([] call comspec_atak_native_fnc_layoutGet) get "device";
_rect params ["_rx", "_ry", "_rw", "_rh"];
(uiNamespace getVariable ["COMSPEC_ATAK_ZeusOv", []]) params [["_heat", controlNull], ["_heatTxt", controlNull], ["_lockBg", controlNull], ["_lockTxt", controlNull], ["_lockBlock", controlNull], ["_bars", []], ["_sig", ""]];
private _sigNow = str _rect;
if (isNull _heat || {isNull _lockBg} || {isNull _lockBlock} || {_sig isNotEqualTo _sigNow}) then {
    { ctrlDelete _x; } forEach ([_heat, _heatTxt, _lockBg, _lockTxt, _lockBlock] + _bars);
    _heat = ["COMSPEC_RscText", _rect, "", false] call comspec_atak_native_fnc_pageCtrl;
    _heat ctrlEnable false;
    _heatTxt = ["COMSPEC_RscStructuredText", [_rx, _ry + _rh * 0.06, _rw, _rh * 0.05], "", false] call comspec_atak_native_fnc_pageCtrl;
    _heatTxt ctrlEnable false;
    _bars = [];
    for "_i" from 0 to 7 do {
        private _b = ["COMSPEC_RscText", [_rx, _ry, _rw, 0], "", false] call comspec_atak_native_fnc_pageCtrl;
        _b ctrlEnable false;
        _bars pushBack _b;
    };
    _lockBg = ["COMSPEC_RscText", _rect, "", false] call comspec_atak_native_fnc_pageCtrl;
    _lockBg ctrlSetBackgroundColor [0.10, 0.02, 0.02, 0.97];
    _lockBg ctrlEnable false;
    _lockTxt = ["COMSPEC_RscStructuredText", [_rx + _rw * 0.06, _ry + _rh * 0.28, _rw * 0.88, _rh * 0.5], "", false] call comspec_atak_native_fnc_pageCtrl;
    _lockTxt ctrlEnable false;
    _lockBlock = ["COMSPEC_RscButtonOverlay", _rect, "", false] call comspec_atak_native_fnc_pageCtrl;
    _lockBlock ctrlAddEventHandler ["ButtonClick", { playSound "addItemFailed"; }];
    _sig = _sigNow;
};
// Surchauffe.
private _hl = ["heat", 0] call comspec_atak_native_fnc_zeusFx;
if !(_hl isEqualType 0) then { _hl = 0; };
private _hot = _hl > 0;
{ _x ctrlShow _hot; } forEach [_heat, _heatTxt];
if (_hot) then {
    _heat ctrlSetBackgroundColor [0.9, 0.35, 0.05, [0.08, 0.16, 0.2] select ((_hl min 3) - 1)];
    _heatTxt ctrlSetStructuredText parseText ((format ["<t align='center' size='0.9' color='#ffb070'>%1</t>",
        ((["APPAREIL CHAUD", "SURCHAUFFE : LAISSEZ REFROIDIR", "SURCHAUFFE CRITIQUE"] select ((_hl min 3) - 1))) call comspec_atak_native_fnc_tr]) call comspec_atak_native_fnc_tr);
};
// Verrou à distance.
private _lock = ["lock", ""] call comspec_atak_native_fnc_zeusFx;
if !(_lock isEqualType "") then { _lock = ""; };
private _locked = _lock isNotEqualTo "";
{ _x ctrlShow _locked; } forEach [_lockBg, _lockTxt, _lockBlock];
if (_locked) then {
    _lockBlock ctrlSetTooltip (("Terminal verrouillé à distance") call comspec_atak_native_fnc_tr);
    _lockTxt ctrlSetStructuredText parseText ((format ["<t align='center'><img size='3' color='#e5483a' image='\A3\ui_f\data\igui\cfg\simpletasks\types\danger_ca.paa'/></t><br/><t align='center' size='1.25' color='#ffffff'>%1</t><br/><t align='center' size='0.9' color='#e8b4ae'>%2</t><br/><br/><t align='center' size='0.95' color='#ffffff'>%3</t>",
        ("TERMINAL VERROUILLÉ") call comspec_atak_native_fnc_tr, ("Verrouillé à distance par le commandement") call comspec_atak_native_fnc_tr, [[_lock, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace]) call comspec_atak_native_fnc_tr);
};
// Écran qui bugue : boucle rapide tant que l'effet dure (une seule à la fois).
private _glitchOn = (uiNamespace getVariable ["COMSPEC_ATAK_ZeusGlitch", 0]) > diag_tickTime || {_hl >= 2};
if (_glitchOn && {(uiNamespace getVariable ["COMSPEC_ATAK_ZeusGlitchPfh", -1]) < 0}) then {
    uiNamespace setVariable ["COMSPEC_ATAK_ZeusGlitchPfh", [{
        params ["", "_h"];
        disableSerialization;
        private _bars = (uiNamespace getVariable ["COMSPEC_ATAK_ZeusOv", []]) param [5, []];
        private _rect = ([] call comspec_atak_native_fnc_layoutGet) get "device";
        _rect params ["_rx", "_ry", "_rw", "_rh"];
        private _hl = ["heat", 0] call comspec_atak_native_fnc_zeusFx;
        if !(_hl isEqualType 0) then { _hl = 0; };
        private _full = (uiNamespace getVariable ["COMSPEC_ATAK_ZeusGlitch", 0]) > diag_tickTime;
        if (isNull ([] call comspec_atak_native_fnc_display) || {!_full && {_hl < 2}}) exitWith {
            { if (!isNull _x) then { _x ctrlShow false; }; } forEach _bars;
            uiNamespace setVariable ["COMSPEC_ATAK_ZeusGlitchPfh", -1];
            [_h] call CBA_fnc_removePerFrameHandler;
        };
        // Surchauffe brûlante : une coupure de temps en temps ; écran qui bugue : bandes partout.
        private _p = [0.06, 0.55] select _full;
        {
            if (isNull _x) then { continue; };
            if (random 1 < _p) then {
                private _bh = _rh * (0.004 + random ([0.03, 0.12] select _full));
                _x ctrlSetPosition [_rx, _ry + random (_rh - _bh), _rw, _bh];
                _x ctrlSetBackgroundColor (selectRandom [[1, 0, 0.4, 0.7], [0, 1, 0.9, 0.6], [1, 1, 1, 0.8], [0, 0, 0, 1], [0.2, 1, 0.2, 0.5]]);
                _x ctrlShow true;
                _x ctrlCommit 0;
            } else { _x ctrlShow false; };
        } forEach _bars;
    }, 0.1] call CBA_fnc_addPerFrameHandler];
};
uiNamespace setVariable ["COMSPEC_ATAK_ZeusOv", [_heat, _heatTxt, _lockBg, _lockTxt, _lockBlock, _bars, _sig]];
_locked || _hot || _glitchOn
