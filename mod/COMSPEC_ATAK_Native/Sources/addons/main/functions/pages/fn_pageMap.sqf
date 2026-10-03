/* Carte : contrôle carte natif + boutons flottants (moi, zoom). Inspecteur à droite en plein écran. */
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["_bx", "_by", "_bw", ""];
private _inspW = _l get "inspW";
private _bh = ((_l get "appbar") select 3) * 0.9;
private _bw2 = _bh * pixelW / pixelH * 1.5;
private _x0 = _bx + _bw - _inspW - _bw2 - (_l get "pad") * 2;

{
    _x params ["_text", "_tip", "_code"];
    private _b = ["COMSPEC_RscButton", [_x0, _by + (_l get "pad") * 2 + _forEachIndex * _bh * 1.15, _bw2, _bh], _text, false] call comspec_atak_native_fnc_pageCtrl;
    _b ctrlSetFontHeight (_l get "fontSmall");
    _b ctrlSetTooltip _tip;
    _b ctrlAddEventHandler ["ButtonClick", _code];
} forEach [
    ["MOI", "Centrer sur ma position", { [player] call comspec_atak_native_fnc_mapCenter; }],
    ["+", "Zoom avant", { private _m = (findDisplay 88500) displayCtrl 88530; [_m ctrlMapScreenToWorld [(ctrlPosition _m select 0) + (ctrlPosition _m select 2) / 2, (ctrlPosition _m select 1) + (ctrlPosition _m select 3) / 2], ((ctrlMapScale _m) * 0.5) max 0.001] call comspec_atak_native_fnc_mapCenter; }],
    ["-", "Zoom arrière", { private _m = (findDisplay 88500) displayCtrl 88530; [_m ctrlMapScreenToWorld [(ctrlPosition _m select 0) + (ctrlPosition _m select 2) / 2, (ctrlPosition _m select 1) + (ctrlPosition _m select 3) / 2], ((ctrlMapScale _m) * 2) min 1] call comspec_atak_native_fnc_mapCenter; }]
];

private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
if !(_s getOrDefault ["mapCentered", false]) then {
    _s set ["mapCentered", true];
    [player, 0.08] call comspec_atak_native_fnc_mapCenter;
};
if !(_l get "mini") then { [] call comspec_atak_native_fnc_inspectorUpdate; };
true
