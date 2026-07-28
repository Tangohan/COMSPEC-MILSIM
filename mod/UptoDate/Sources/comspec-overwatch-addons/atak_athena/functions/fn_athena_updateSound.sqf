/*
    Rafraîchit l’UI de l’app Sons ATAK (libellés + état).
*/
if (!hasInterface) exitWith {};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Sound_group", controlNull];
if (isNull _group) exitWith {};

private _pct = {
    params ["_v"];
    if (!(_v isEqualType 0)) then { _v = 0; };
    format ["%1%%", round (((_v max 0) min 1) * 100)]
};

private _style = missionNamespace getVariable ["comspec_overwatch_notif_sound", "silent_vib"];
if (!(_style isEqualType "")) then { _style = "silent_vib"; };
private _styleLabel = switch (toLower _style) do {
    case "stalker": { "Ambiance tension" };
    case "health": { "Signal médical" };
    case "mute": { "Silencieux — sans vibration" };
    default { "Silencieux — vibration seule" };
};

private _master = missionNamespace getVariable ["comspec_overwatch_sound_master", 1];
private _notif = missionNamespace getVariable ["comspec_overwatch_sound_notif_vol", 1];
private _vib = missionNamespace getVariable ["comspec_overwatch_sound_vibrate_vol", 1];
private _fx = missionNamespace getVariable ["comspec_overwatch_sound_fx_vol", 0.8];
private _quiet = missionNamespace getVariable ["comspec_overwatch_quiet_mode", false];
private _screen = missionNamespace getVariable ["comspec_overwatch_screen_notifications", false];
private _roleplay = missionNamespace getVariable ["comspec_overwatch_roleplay_visual_effects", false];

private _summary = _group controlsGroupCtrl 9821;
if (!isNull _summary) then {
    _summary ctrlSetStructuredText parseText format [
        "<t align='center' size='0.95'>%1</t><br/><t align='center' color='#8ec9a0' size='0.82'>Général %2 · Alertes %3 · Vibration %4</t>",
        _styleLabel,
        [_master] call _pct,
        [_notif] call _pct,
        [_vib] call _pct
    ];
};

private _btnStyle = _group controlsGroupCtrl 9822;
if (!isNull _btnStyle) then {
    _btnStyle ctrlSetText format ["Style : %1", _styleLabel];
};

{
    _x params ["_idc", "_label", "_val"];
    private _c = _group controlsGroupCtrl _idc;
    if (!isNull _c) then {
        _c ctrlSetStructuredText parseText format [
            "<t align='left' valign='middle'>  %1  <t color='#8ec9a0'>%2</t></t>",
            _label,
            [_val] call _pct
        ];
    };
} forEach [
    [9823, "Volume général", _master],
    [9826, "Volume alertes", _notif],
    [9829, "Volume vibration", _vib],
    [9832, "Volume effets", _fx]
];

private _btnQuiet = _group controlsGroupCtrl 9835;
if (!isNull _btnQuiet) then {
    _btnQuiet ctrlSetText (["Mode discret : off", "Mode discret : on"] select _quiet);
};

private _btnScreen = _group controlsGroupCtrl 9836;
if (!isNull _btnScreen) then {
    _btnScreen ctrlSetText (["Notifs écran : off", "Notifs écran : on"] select _screen);
};

private _btnRp = _group controlsGroupCtrl 9837;
if (!isNull _btnRp) then {
    _btnRp ctrlSetText (["Effets de zone : off", "Effets de zone : on"] select _roleplay);
};

private _help = _group controlsGroupCtrl 9840;
if (!isNull _help) then {
    _help ctrlSetStructuredText parseText (
        "<t>Le volume général multiplie tous les sons du terminal.</t><br/>" +
        "<t>Style d’alerte : cycle entre vibration seule, tension, signal médical, ou silence total.</t><br/>" +
        "<t>Les urgences médicales restent prioritaires sauf silence total ou volume à 0.</t>"
    );
};
