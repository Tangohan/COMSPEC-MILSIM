/*
    Câble DL-7 dessiné autour du téléphone pendant une extraction : le câble sort du connecteur de la coque et file
    vers le module DL-7 (textures datalink_*, tools/gen_assets.py), lui-même relié au support visé.
    Le module est placé du côté de l'écran où il reste visible : au-dessus ou en dessous du téléphone en paysage
    (selon le coin choisi), à gauche ou à droite en portrait. Écran LCD et voyants PWR / AUTH / DATA animés.
    Appelé par fn_layoutApply (position) et le tick de l'extraction (voyants, écran). Sans liaison : tout est retiré.
*/
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
private _st = uiNamespace getVariable ["COMSPEC_ATAK_DataLink", createHashMap];
private _phase = _st getOrDefault ["phase", ""];
(uiNamespace getVariable ["COMSPEC_ATAK_DataOverlay", []]) params [["_img", controlNull], ["_lcd", controlNull], ["_leds", []], ["_variant", ""]];
if (isNull _d || {_phase isEqualTo ""}) exitWith {
    { ctrlDelete _x; } forEach ([_img, _lcd] + _leds);
    uiNamespace setVariable ["COMSPEC_ATAK_DataOverlay", []];
    false
};
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _anchor = toUpper (profileNamespace getVariable ["COMSPEC_ATAK_MiniAnchor", "BR"]);
private _want = if (_l get "landscape") then {
    ["datalink_land_up", "datalink_land_down"] select ((_l get "mini") && {(_anchor select [0, 1]) isEqualTo "T"})
} else {
    ["datalink_port_left", "datalink_port_right"] select ((_anchor select [1, 1]) isEqualTo "L")
};
// Cadre de la texture et module, en fractions du carré de la coque (mêmes valeurs que DATALINK dans gen_assets.py).
private _geo = createHashMapFromArray [
    ["datalink_land_up", [[0.5, 0, 0.5, 1], [1100, 180, 700, 290]]],
    ["datalink_land_down", [[0.5, 0.25, 0.5, 1], [1100, 1800, 700, 290]]],
    ["datalink_port_left", [[-0.5, 0.5, 1, 0.5], [-760, 1560, 700, 290]]],
    ["datalink_port_right", [[0.5, 0.5, 1, 0.5], [2110, 1560, 700, 290]]]
];
private _fresh = isNull _img || {(ctrlParent _img) isNotEqualTo _d};
if (_fresh) then {
    { ctrlDelete _x; } forEach ([_img, _lcd] + _leds);
    _img = _d ctrlCreate ["COMSPEC_RscPhone", -1];
    _img ctrlEnable false;
    _lcd = _d ctrlCreate ["COMSPEC_RscStructuredText", -1];
    _lcd ctrlEnable false;
    _leds = [0, 1, 2] apply {
        private _c = _d ctrlCreate ["COMSPEC_RscPicture", -1];
        _c ctrlSetText "\z\comspec_atak_native\addons\main\data\ui_disc.paa";
        _c ctrlEnable false;
        _c
    };
};
(_geo get _want) params ["_frame", "_mod"];
(_l get "phone") params ["_px", "_py", "_pw", "_ph"];
_frame params ["_fx", "_fy", "_fw", "_fh"];
// Couleur du module (réglage Personnaliser) : olive par défaut, noir ou tan.
private _mt = profileNamespace getVariable ["COMSPEC_ATAK_ModuleTint", "olive"];
private _tex = _want + (["", "_" + _mt] select (_mt in ["black", "tan"]));
if (_fresh || {_variant isNotEqualTo _tex}) then { _img ctrlSetText format ["\z\comspec_atak_native\addons\main\data\%1.paa", _tex]; };
_img ctrlSetPosition [_px + _pw * _fx, _py + _ph * _fy, _pw * _fw, _ph * _fh];
_img ctrlCommit 0;
_mod params ["_mx", "_my", "_mw", "_mh"];
private _mxx = _px + _pw * _mx / 2048;
private _myy = _py + _ph * _my / 2048;
private _mww = _pw * _mw / 2048;
private _mhh = _ph * _mh / 2048;
_lcd ctrlSetPosition [_mxx + _mww * 0.11, _myy + _mhh * 0.40, _mww * 0.44, _mhh * 0.38];
_lcd ctrlCommit 0;
{
    private _cx = _mxx + _mww * ([0.655, 0.77, 0.885] select _forEachIndex);
    private _r = _mww * 0.026;
    private _rh = _r * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
    _x ctrlSetPosition [_cx - _r, _myy + _mhh * 0.52 - _rh, _r * 2, _rh * 2];
    _x ctrlCommit 0;
} forEach _leds;
// Voyants : PWR fixe ; AUTH orange clignotant (négociation, mot de passe, forçage), rouge (refus, verrou), vert (accès) ;
// DATA vert clignotant pendant le transfert, fixe à la fin.
private _blink = (floor (diag_tickTime * 3)) mod 2 isEqualTo 0;
private _fast = (floor (diag_tickTime * 7)) mod 2 isEqualTo 0;
private _off = [0.10, 0.12, 0.10, 1];
private _green = [0.36, 0.95, 0.45, 1];
private _amber = [1, 0.66, 0.15, 1];
private _red = [1, 0.25, 0.2, 1];
private _failed = diag_tickTime - (_st getOrDefault ["failAt", -10]) < 1.5;
private _auth = switch (true) do {
    case (_failed || {_phase in ["LOCKED", "WIPED"]}): { _red };
    case (_phase in ["PLUG", "HANDSHAKE", "AUTH", "CRACK"]): { [_off, _amber] select _blink };
    default { _green };
};
private _data = switch (_phase) do {
    case "DL": { [_off, _green] select _fast };
    case "DONE": { _green };
    case "HANDSHAKE": { [_off, _amber] select _fast };
    default { _off };
};
(_leds select 0) ctrlSetTextColor ([_amber, _green] select (_phase isNotEqualTo "PLUG"));
(_leds select 1) ctrlSetTextColor _auth;
(_leds select 2) ctrlSetTextColor _data;
// Écran LCD du module.
private _now = diag_tickTime;
private _p = ((_now - (_st getOrDefault ["t0", _now])) / ((_st getOrDefault ["dur", 1]) max 0.1)) max 0 min 1;
private _port = switch (_st getOrDefault ["port", "usb"]) do { case "rj45": { "RJ45" }; case "serie": { "RS-232" }; default { "USB-C" }; };
private _line = switch (_phase) do {
    case "PLUG": { "CONNEXION" };
    case "HANDSHAKE": { format ["SYNC %1%2", round (_p * 100), "%"] };
    case "AUTH": { ["MOT DE PASSE ?", "REFUSÉ"] select _failed };
    case "LOCKED": { "VERROUILLÉ" };
    case "CRACK": { format ["FORÇAGE %1%2", round (_p * 100), "%"] };
    case "BROWSE": { "ACCÈS OK" };
    case "DL": { format ["DL %1%2", round (_p * 100), "%"] };
    case "DONE": { "TERMINÉ" };
    case "WIPED": { "VIDE" };
    default { "" };
};
private _fs = _mhh * 0.15;
_lcd ctrlSetStructuredText parseText format ["<t font='EtelkaMonospaceProBold' size='%1' color='#7dff9a'>DL-7 %2<br/>%3</t>", _fs / (0.024 * safeZoneH), _port, _line];
if (_fresh) then {
    // Câble qui apparaît au branchement.
    { _x ctrlSetFade 1; _x ctrlCommit 0; _x ctrlSetFade 0; _x ctrlCommit 0.35; } forEach ([_img, _lcd] + _leds);
};
uiNamespace setVariable ["COMSPEC_ATAK_DataOverlay", [_img, _lcd, _leds, _tex]];
true
