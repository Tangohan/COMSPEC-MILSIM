/*
    Animations d'alimentation de l'écran, jouées par-dessus tout le reste. Params : [type]
      "empty"    : batterie vide. L'écran baisse, l'icône « batterie vide » clignote, noir, puis le téléphone est rangé.
      "broken"   : téléphone détruit. Coupures noires, « ERREUR MATÉRIELLE », extinction façon tube cathodique ; il reste
                   affiché, écran éclaté et inutilisable (fn_deviceOverlay), jusqu'à réparation ou changement.
      "shutdown" : arrêt avant redémarrage. « Arrêt… », extinction ; l'écran de démarrage suit (fn_deviceOverlay).
    Pendant l'animation, uiNamespace COMSPEC_ATAK_PowerFx = [type, heure de fin (diag_tickTime)] :
    fn_schedulerTick ne range pas le téléphone et fn_notificationsRender masque les toasts.
    Téléphone fermé : rien à montrer ; pour « empty », on s'assure qu'il reste rangé.
    Pendant l'animation, la zone de contenu (fenêtres d'app) est masquée et le focus rendu au bouton hors écran
    (fn_deviceOverlay, fn_overlayFront) : une app ouverte ne cache plus l'animation.
*/
params [["_kind", "empty"]];
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
private _stow = {
    uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", false];
    [] call comspec_atak_native_fnc_close;
};
if (isNull _d) exitWith { if (_kind isEqualTo "empty") then { call _stow; }; false };
if (((uiNamespace getVariable ["COMSPEC_ATAK_PowerFx", []]) param [1, -1]) > diag_tickTime) exitWith { false };
private _dur = createHashMapFromArray [["empty", 3.7], ["broken", 2.9], ["shutdown", 1.5]] getOrDefault [_kind, 2];
uiNamespace setVariable ["COMSPEC_ATAK_PowerFx", [_kind, diag_tickTime + _dur + 0.5]];

private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "device") params ["_rx", "_ry", "_rw", "_rh"];
private _ratio = pixelH / pixelW;
private _font = _l get "font";
private _mk = {
    params ["_class", "_pos"];
    private _c = _d ctrlCreate [_class, -1];
    _c ctrlSetPosition _pos;
    _c ctrlCommit 0;
    _c
};
// Bloque les clics pendant l'animation.
private _block = ["COMSPEC_RscButtonInvisible", [_rx, _ry, _rw, _rh]] call _mk;
private _dim = ["COMSPEC_RscText", [_rx, _ry, _rw, _rh]] call _mk;
_dim ctrlSetBackgroundColor [0, 0, 0, 1];
_dim ctrlSetFade 1; _dim ctrlCommit 0;
private _ih = _rh * ([0.2, 0.3] select (_l get "landscape"));
private _icon = ["COMSPEC_RscPhone", [_rx + (_rw - _ih / _ratio) / 2, _ry + _rh * 0.5 - _ih * 0.75, _ih / _ratio, _ih]] call _mk;
_icon ctrlSetText "\z\comspec_atak_native\addons\main\data\power_bat_empty.paa";
_icon ctrlShow false;
private _txt = ["COMSPEC_RscStructuredText", [_rx, _ry + _rh * 0.5 + _ih * 0.3, _rw, _font * 3]] call _mk;
_txt ctrlSetFade 1; _txt ctrlCommit 0;
private _crt = ["COMSPEC_RscText", [_rx, _ry, _rw, _rh]] call _mk;
_crt ctrlSetBackgroundColor [0.92, 0.97, 0.95, 0.9];
_crt ctrlShow false;
{ _x ctrlEnable false; } forEach [_dim, _icon, _txt, _crt];
private _ctrls = [_block, _dim, _icon, _txt, _crt];
uiNamespace setVariable ["COMSPEC_ATAK_PowerFxCtrls", _ctrls];
// Fenêtres d'app masquées et focus rendu : l'animation passe devant tout.
[] call comspec_atak_native_fnc_deviceOverlay;
[] call comspec_atak_native_fnc_notificationsRender;

// Étapes : [délai depuis le début (s), code]. Chaque code reçoit [contrôles, rectangle, police].
private _collapse = {
    params ["_c", "_r"];
    _r params ["_cx", "_cy", "_cw", "_ch"];
    private _crt = _c select 4;
    if (isNull _crt) exitWith {};
    (_c select 1) ctrlSetFade 0; (_c select 1) ctrlCommit 0;
    _crt ctrlShow true; _crt ctrlSetFade 0; _crt ctrlSetPosition [_cx, _cy, _cw, _ch]; _crt ctrlCommit 0;
    _crt ctrlSetPosition [_cx, _cy + _ch * 0.496, _cw, _ch * 0.008]; _crt ctrlCommit 0.18;
    [{
        params ["_crt", "_cx", "_cy", "_cw", "_ch"];
        if (isNull _crt) exitWith {};
        _crt ctrlSetPosition [_cx + _cw * 0.5, _cy + _ch * 0.496, 0, _ch * 0.008]; _crt ctrlSetFade 0.6; _crt ctrlCommit 0.16;
    }, [_crt, _cx, _cy, _cw, _ch], 0.2] call CBA_fnc_waitAndExecute;
};
private _steps = switch (_kind) do {
    case "empty": {[
        [0, { params ["_c"]; (_c select 1) ctrlSetFade 0.35; (_c select 1) ctrlCommit 1.2; playSound "ClickSoft"; }],
        [1.3, { params ["_c", "", "_f"];
            (_c select 1) ctrlSetFade 0; (_c select 1) ctrlCommit 0.25;
            (_c select 2) ctrlShow true; (_c select 2) ctrlSetFade 1; (_c select 2) ctrlCommit 0; (_c select 2) ctrlSetFade 0; (_c select 2) ctrlCommit 0.3;
            (_c select 3) ctrlSetStructuredText parseText "<t align='center' font='RobotoCondensedBold' color='#e5483a'>BATTERIE VIDE</t><br/><t align='center' size='0.8' color='#8a9a93'>Arrêt du téléphone</t>";
            (_c select 3) ctrlSetFade 0; (_c select 3) ctrlCommit 0.3;
        }],
        [2.0, { params ["_c"]; (_c select 2) ctrlSetFade 0.7; (_c select 2) ctrlCommit 0.35; }],
        [2.4, { params ["_c"]; (_c select 2) ctrlSetFade 0; (_c select 2) ctrlCommit 0.35; }],
        [3.0, { params ["_c"]; { _x ctrlSetFade 1; _x ctrlCommit 0.5; } forEach [_c select 2, _c select 3]; }]
    ]};
    case "broken": {[
        [0, { params ["_c"]; (_c select 1) ctrlSetFade 0; (_c select 1) ctrlCommit 0; (_c select 1) ctrlSetFade 1; (_c select 1) ctrlCommit 0.12; playSound "ClickSoft"; }],
        [0.35, { params ["_c"]; (_c select 1) ctrlSetFade 0.1; (_c select 1) ctrlCommit 0; (_c select 1) ctrlSetFade 0.8; (_c select 1) ctrlCommit 0.2; }],
        [0.8, { params ["_c"]; (_c select 1) ctrlSetFade 0; (_c select 1) ctrlCommit 0; (_c select 1) ctrlSetFade 1; (_c select 1) ctrlCommit 0.08; }],
        [1.1, { params ["_c"];
            (_c select 1) ctrlSetFade 0; (_c select 1) ctrlCommit 0;
            (_c select 3) ctrlSetStructuredText parseText "<t align='center' font='EtelkaMonospacePro' color='#e5483a'>ERREUR MATÉRIELLE</t><br/><t align='center' size='0.8' font='EtelkaMonospacePro' color='#8a9a93'>display_panel: no response</t>";
            (_c select 3) ctrlSetFade 0; (_c select 3) ctrlCommit 0;
        }],
        [1.5, { params ["_c"]; (_c select 3) ctrlSetFade 0.6; (_c select 3) ctrlCommit 0; (_c select 3) ctrlSetFade 0; (_c select 3) ctrlCommit 0.1; }],
        [1.9, { params ["_c", "_r"]; (_c select 3) ctrlSetFade 1; (_c select 3) ctrlCommit 0; [_c, _r] call (uiNamespace getVariable ["COMSPEC_ATAK_PowerCollapse", {}]); }]
    ]};
    default {[
        [0, { params ["_c", "", "_f"];
            (_c select 1) ctrlSetFade 0; (_c select 1) ctrlCommit 0.6;
            (_c select 3) ctrlSetStructuredText parseText "<t align='center' color='#c9d4cf'>Arrêt…</t>";
            (_c select 3) ctrlSetFade 0; (_c select 3) ctrlCommit 0.2;
        }],
        [0.8, { params ["_c", "_r"]; (_c select 3) ctrlSetFade 1; (_c select 3) ctrlCommit 0; [_c, _r] call (uiNamespace getVariable ["COMSPEC_ATAK_PowerCollapse", {}]); }]
    ]};
};
uiNamespace setVariable ["COMSPEC_ATAK_PowerCollapse", _collapse];
{
    _x params ["_t", "_code"];
    [{
        params ["_code", "_c", "_r", "_f"];
        if (isNull (_c select 0)) exitWith {};
        [_c, _r, _f] call _code;
    }, [_code, _ctrls, [_rx, _ry, _rw, _rh], _font], _t] call CBA_fnc_waitAndExecute;
} forEach _steps;
// Fin : contrôles supprimés ; batterie vide : téléphone rangé ; casse : écran éclaté (fn_deviceOverlay).
[{
    params ["_kind", "_ctrls"];
    { ctrlDelete _x; } forEach _ctrls;
    uiNamespace setVariable ["COMSPEC_ATAK_PowerFx", []];
    uiNamespace setVariable ["COMSPEC_ATAK_PowerFxCtrls", []];
    if (_kind isEqualTo "empty") then {
        uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", false];
        [] call comspec_atak_native_fnc_close;
    } else {
        [] call comspec_atak_native_fnc_deviceOverlay;
    };
}, [_kind, _ctrls], _dur] call CBA_fnc_waitAndExecute;
true
