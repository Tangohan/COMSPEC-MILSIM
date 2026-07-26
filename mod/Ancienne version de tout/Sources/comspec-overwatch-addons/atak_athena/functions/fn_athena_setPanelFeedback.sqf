/*
    Affiche un retour métier dans le panneau Athena (zone Feedback),
    sans bandeau cTab qui se superpose au HUD carte.
    Params : ["message", "info"|"ok"|"warn"|"error", duréeSecondes]
*/
params [
    ["_message", "", [""]],
    ["_tone", "info", [""]],
    ["_duration", 6, [0]]
];

if (_message isEqualTo "") exitWith {};

private _color = switch (toLower _tone) do {
    case "ok": { "#9dffc4" };
    case "warn": { "#ffe08a" };
    case "error": { "#ffb0a0" };
    default { "#e8f4f0" };
};
private _bg = switch (toLower _tone) do {
    case "ok": { [0.05, 0.2, 0.14, 0.98] };
    case "warn": { [0.22, 0.16, 0.05, 0.98] };
    case "error": { [0.24, 0.08, 0.06, 0.98] };
    default { [0.04, 0.12, 0.14, 0.98] };
};

private _html = format [
    "<t size='0.82' color='%1'>%2</t>",
    _color,
    _message
];
private _token = diag_tickTime;
private _expires = _token + (0 max _duration);
missionNamespace setVariable [
    "COMSPEC_Athena_PanelFeedback",
    [_html, _bg, _expires, _token],
    false
];

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
if (!isNull _group) then {
    private _fb = _group controlsGroupCtrl 9712;
    if (!isNull _fb) then {
        _fb ctrlShow true;
        _fb ctrlSetBackgroundColor _bg;
        _fb ctrlSetStructuredText parseText _html;
        _fb ctrlSetFade 0;
        _fb ctrlCommit 0;
    };
};

if (_duration <= 0) exitWith {};

[_token, _duration] spawn {
    params ["_token", "_duration"];
    uiSleep _duration;
    private _cur = missionNamespace getVariable ["COMSPEC_Athena_PanelFeedback", []];
    if ((count _cur) < 4) exitWith {};
    if ((_cur select 3) isNotEqualTo _token) exitWith {};
    missionNamespace setVariable ["COMSPEC_Athena_PanelFeedback", nil, false];
    private _group = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
    if (isNull _group) exitWith {};
    private _fb = _group controlsGroupCtrl 9712;
    if (isNull _fb) exitWith {};
    _fb ctrlSetStructuredText parseText "";
    _fb ctrlShow false;
};
