/*
    Notification visuelle + son ATAK (mêmes points d’entrée que les alertes Overwatch).
    Usage identique à BIS_fnc_showNotification :
      ["COMSPEC_Info", ["Message"]] call comspec_overwatch_connect_fnc_showNotification;

    En mode discret : pas de bandeau BIS — redirection vers la tablette HTML.
*/
params ["_template", ["_args", []]];

private _msg = "";
if (_args isEqualType []) then {
    if ((count _args) > 0) then {
        private _a0 = _args select 0;
        _msg = if (_a0 isEqualType "") then { _a0 } else { str _a0 };
    };
} else {
    _msg = if (_args isEqualType "") then { _args } else { str _args };
};
_msg = trim _msg;

private _isWarn = ((toLower (str _template)) find "warn") >= 0;
private _priority = if (_isWarn) then { "warn" } else { "info" };
private _type = "system";
private _msgLow = toLower _msg;
if ((_msgLow find "médic") >= 0 || {(_msgLow find "medic") >= 0} || {(_msgLow find "cardiaque") >= 0} || {(_msgLow find "inconscient") >= 0} || {(_msgLow find "kia") >= 0} || {(_msgLow find "mort") >= 0}) then {
    _type = "medical";
    _priority = "critical";
} else {
    if ((_msgLow find "ordre") >= 0) then {
        _type = "order";
    } else {
        if ((_msgLow find "connect") >= 0 || {(_msgLow find "liaison") >= 0} || {(_msgLow find "athena") >= 0} || {(_msgLow find "compte") >= 0}) then {
            _type = "link";
        };
    };
};

if (!(_msg isEqualTo "")) then {
    private _title = if (_isWarn) then { "Attention" } else { "Information" };
    [_type, _title, _msg, _priority] call comspec_overwatch_connect_fnc_pushHtmlAlert;
};

private _quiet = missionNamespace getVariable ["comspec_overwatch_quiet_mode", false];
private _milsim = missionNamespace getVariable ["comspec_overwatch_milsim_ui", false];
if (!_quiet && {!_milsim}) then {
    [_template, _args] call BIS_fnc_showNotification;
};

// Son dédié pour l’urgence médicale (mort / inconscient), sinon bip de préférence.
// Mode discret : pas de bandeau BIS ci-dessus, mais le son CBA est conservé (sauf Muet).
private _soundEvent = "";
if (_type isEqualTo "medical") then {
    if (
        (_msgLow find "arrêt cardiaque") >= 0
        || {(_msgLow find "arret cardiaque") >= 0}
        || {(_msgLow find "rythme à zéro") >= 0}
        || {(_msgLow find "rythme a zero") >= 0}
        || {(_msgLow find "cardiaque") >= 0}
        || {(_msgLow find "kia") >= 0}
        || {(_msgLow find "hors combat") >= 0}
        || {(_msgLow find "mort") >= 0}
        || {(_msgLow find "dead") >= 0}
        || {(_msgLow find "fc=0") >= 0}
        || {(_msgLow find "fc 0") >= 0}
    ) then {
        _soundEvent = "death";
    } else {
        if ((_msgLow find "inconscient") >= 0 || {(_msgLow find "au sol") >= 0}) then {
            _soundEvent = "unconscious";
        };
    };
};
[_soundEvent] call comspec_overwatch_connect_fnc_playAtakNotification;
