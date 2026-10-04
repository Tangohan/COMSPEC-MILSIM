/*
    Toasts en haut de la zone de contenu, trois au plus. Ils sont posés dans leur propre groupe de contrôles,
    recréé en dernier : sinon les groupes des apps sont dessinés par-dessus. Hauteur selon la longueur du texte.
*/
disableSerialization;
private _d = ([] call comspec_atak_native_fnc_display);
if (isNull _d) exitWith {};
{ ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_ToastControls", []]);
uiNamespace setVariable ["COMSPEC_ATAK_ToastControls", []];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _q = (_s getOrDefault ["notifications", []]) select { (_x getOrDefault ["expires", 0]) > diag_tickTime };
_s set ["notifications", _q];
if ((count _q) isEqualTo 0) exitWith {};
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["_bx", "_by", "_bw", "_bh"];
private _pad = _l get "pad";
private _minH = (_l get "font") * 2.4;
private _w = [(_bw * 0.45) max ((_l get "font") * 14), _bw] select (_l get "mini");
_w = _w min _bw;
private _labels = createHashMapFromArray [["INFO", "INFO"], ["SUCCESS", "RÉUSSI"], ["WARNING", "ATTENTION"], ["ERROR", "ERREUR"], ["MESSAGE", "MESSAGE"], ["TACTICAL", "TACTIQUE"]];
private _grp = _d ctrlCreate ["COMSPEC_RscControlsGroup", -1];
_grp ctrlSetPosition [_bx + _bw - _w, _by, _w, _bh];
_grp ctrlCommit 0;
private _y = _pad;
{
    if (_forEachIndex >= 3) then { continue };
    private _t = _x getOrDefault ["type", "INFO"];
    private _color = switch (_t) do {
        case "WARNING": { "#f2ab33" };
        case "ERROR";
        case "TACTICAL": { "#e5604f" };
        case "MESSAGE": { "#6fb6e8" };
        default { "#5cc76b" };
    };
    private _c = _d ctrlCreate ["COMSPEC_RscCard", -1, _grp];
    _c ctrlSetBackgroundColor [0.06, 0.08, 0.075, 0.97];
    _c ctrlSetPosition [_pad, _y, _w - 2 * _pad, _minH];
    _c ctrlSetStructuredText parseText format ["<t size='0.75' color='%1' font='RobotoCondensedBold'>%2</t><br/><t size='0.9'>%3</t>", _color, _labels getOrDefault [_t, _t], _x getOrDefault ["message", ""]];
    _c ctrlCommit 0;
    private _h = (ctrlTextHeight _c + _pad * 0.5) max _minH;
    _c ctrlSetPosition [_pad, _y, _w - 2 * _pad, _h];
    _c ctrlCommit 0;
    // Liseré de couleur à gauche.
    private _bar = _d ctrlCreate ["COMSPEC_RscText", -1, _grp];
    _bar ctrlSetBackgroundColor ((_color call BIS_fnc_HEXtoRGB) + [1]);
    _bar ctrlSetPosition [_pad, _y, _pad * 0.5, _h];
    _bar ctrlCommit 0;
    _y = _y + _h + _pad;
} forEach _q;
// Le groupe ne couvre que les toasts : le reste de l'app reste cliquable.
_grp ctrlSetPosition [_bx + _bw - _w, _by, _w, _y];
_grp ctrlCommit 0;
uiNamespace setVariable ["COMSPEC_ATAK_ToastControls", [_grp]];
