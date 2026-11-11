/*
    comspec_fb_fnc_serverArea : « effet sur une zone » sans grenade (scénario, Zeus, Eden).
    Toutes les unités à moins de _radius m reçoivent l'effet à la force _strength (0..1), sans calcul de ligne de vue,
    N fois. _showFx : lumière, particules et bang au centre (sinon effet seul, silencieux).
    _lead : secondes avant la première détonation (délai du module + mèche choisie).
    Comme fn_serverDetonate : serveur ; client -> serveur s'il a le mod ; sinon le client fait autorité et diffuse.
    [_posASL, _radius, _strength, _n, _showFx, _lead] call comspec_fb_fnc_serverArea
*/
params [
    ["_posASL", [0, 0, 0], [[]], 3], ["_radius", 10, [0]], ["_strength", 0.8, [0]], ["_n", 1, [0]],
    ["_showFx", true, [true]], ["_lead", 0, [0]]
];
if (!isServer && {call comspec_fb_fnc_serverHasMod}) exitWith {
    ["comspec_fb_serverArea", _this] call CBA_fnc_serverEvent;
};
private _client = !isServer;
_n = ((round _n) max 1) min 9;
_radius = (_radius max 1) min 100;
_strength = (_strength max 0) min 1;
private _interval = missionNamespace getVariable ["comspec_fb_interval", 0.5];
private _jitter = missionNamespace getVariable ["comspec_fb_jitter", 0.12];
private _offsets = [];
private _t = 0;
for "_i" from 1 to _n do {
    _offsets pushBack _t;
    _t = _t + ((_interval + (random 2 - 1) * _jitter) max 0.12);
};
comspec_fb_seq = comspec_fb_seq + 1;
private _id = if (_client) then { format ["FBc%1_%2", clientOwner, comspec_fb_seq] } else { format ["FB%1", comspec_fb_seq] };
private _msg = [_id, "area", objNull, "", _posASL, _offsets, CBA_missionTime + 0.15 + (_lead max 0), sideUnknown, objNull, [_radius, _strength, _showFx]];
if (_client) then {
    ["client", format ["%1 zone r=%2 force=%3 x%4 dans %5 s : serveur sans le mod, message à toutes les machines", _id, _radius, _strength, _n, (0.15 + (_lead max 0)) toFixed 2]] call comspec_fb_fnc_log;
    ["comspec_fb_timeline", _msg] call CBA_fnc_globalEvent;
} else {
    private _owners = [_posASL] call comspec_fb_fnc_targets;
    ["serveur", format ["%1 zone r=%2 force=%3 x%4 dans %5 s machines=%6", _id, _radius, _strength, _n, (0.15 + (_lead max 0)) toFixed 2, _owners]] call comspec_fb_fnc_log;
    if (isMultiplayer) then {
        { ["comspec_fb_timeline", _msg, _x] call CBA_fnc_ownerEvent } forEach _owners;
    } else {
        _msg call comspec_fb_fnc_timeline;
    };
};
