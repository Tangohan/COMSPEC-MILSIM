// Fetch current IFF challenge from C2. Param: [missionId]. Stores result in COMSPEC_IFFChallenge.
params [["_missionId", "mission_1_map_1", [""]]];
private _raw = ["COMSPECExtension" callExtension ["IFF.Current", [_missionId]]] call comspec_overwatch_connect_fnc_extResult;
if (_raw isEqualTo "") exitWith { nil };
private _parts = _raw splitString "|";
if (count _parts < 2 || (_parts select 0) != "OK") exitWith { nil };
missionNamespace setVariable ["COMSPEC_IFFChallenge", _parts select 1, true];
_parts select 1
