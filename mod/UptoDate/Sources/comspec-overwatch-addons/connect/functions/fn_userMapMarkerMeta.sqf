/*
    Métadonnées d’un repère posé à la main sur la carte Arma
    (_USER_DEFINED #propriétaire/index/canal).
    Retour : HashMap channel_id, channel, owner_id, placed_by, placed_at
*/
params [["_markerName", "", [""]]];

private _out = createHashMap;
_out set ["channel_id", -1];
_out set ["channel", ""];
_out set ["owner_id", -1];
_out set ["placed_by", ""];
_out set ["placed_at", ""];
if (_markerName isEqualTo "") exitWith { _out };

private _stored = missionNamespace getVariable [format ["COMSPEC_UserMkMeta_%1", _markerName], []];
if (_stored isEqualType [] && {(count _stored) >= 1}) then {
    _out set ["channel_id", _stored param [0, -1]];
    _out set ["placed_by", _stored param [1, ""]];
    _out set ["placed_at", _stored param [2, ""]];
    _out set ["owner_id", _stored param [3, -1]];
};

private _tail = _markerName;
private _cut = _markerName find "#";
if (_cut >= 0) then {
    _tail = _markerName select [_cut + 1, (count _markerName) - _cut];
} else {
    private _enc = _markerName find "__H__";
    if (_enc >= 0) then {
        _tail = _markerName select [_enc + 5, (count _markerName) - _enc];
    };
};
private _bits = _tail splitString "/";
if ((count _bits) >= 3) then {
    private _own = parseNumber (_bits select 0);
    private _ch = parseNumber (_bits select 2);
    if (_own isEqualType 0) then { _out set ["owner_id", _own]; };
    if (_ch isEqualType 0) then { _out set ["channel_id", _ch]; };
};

private _chId = _out getOrDefault ["channel_id", -1];
private _chName = switch (_chId) do {
    case 0: { "Canal global" };
    case 1: { "Canal latéral" };
    case 2: { "Canal commandement" };
    case 3: { "Canal groupe" };
    case 4: { "Canal véhicule" };
    case 5: { "Canal direct" };
    default { "" };
};
_out set ["channel", _chName];

if ((_out getOrDefault ["placed_by", ""]) isEqualTo "") then {
    private _own = _out getOrDefault ["owner_id", -1];
    if (!isMultiplayer || {_own < 0} || {_own isEqualTo clientOwner}) then {
        private _nm = if (!isNull player) then { name player } else { "" };
        if (_nm isEqualTo "") then {
            _nm = [] call comspec_overwatch_connect_fnc_getCallsign;
        };
        _out set ["placed_by", _nm];
    };
};

if ((_out getOrDefault ["placed_at", ""]) isEqualTo "") then {
    private _st = systemTime;
    private _hh = str (_st select 3);
    private _mm = str (_st select 4);
    if ((count _hh) < 2) then { _hh = "0" + _hh; };
    if ((count _mm) < 2) then { _mm = "0" + _mm; };
    private _at = format ["%1:%2", _hh, _mm];
    _out set ["placed_at", _at];
    missionNamespace setVariable [
        format ["COMSPEC_UserMkMeta_%1", _markerName],
        [
            _out getOrDefault ["channel_id", -1],
            _out getOrDefault ["placed_by", ""],
            _at,
            _out getOrDefault ["owner_id", -1]
        ],
        false
    ];
};

_out
