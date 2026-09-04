/*
    Empile un événement timeline (max 24).
*/
params [["_prio", "INFO", [""]], ["_text", "", [""]]];
if (_text isEqualTo "") exitWith {};
private _ev = missionNamespace getVariable ["COMSPEC_MapTimeline", []];
if (!(_ev isEqualType [])) then { _ev = []; };
_ev pushBack [_prio, _text, time];
if ((count _ev) > 24) then { _ev deleteAt 0; };
missionNamespace setVariable ["COMSPEC_MapTimeline", _ev, false];
