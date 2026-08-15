params [["_talkgroup", 1]];

private _tg = (round _talkgroup) max 1 min 16;
private _slots = call Iceman_fnc_wr_getTxSlots;
private _idx = _slots find _tg;

if (_idx < 0) exitWith {0};
_idx + 1
