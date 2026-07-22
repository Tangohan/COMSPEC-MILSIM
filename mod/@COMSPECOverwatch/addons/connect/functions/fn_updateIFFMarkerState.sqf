// Update local IFF marker state from C2 status. Param: [missionId]. Fetches IFF.Status and can drive marker colors (bleu/jaune/orange/rouge).
params [["_missionId", "mission_1_map_1", [""]]];
private _raw = ["COMSPECExtension" callExtension ["IFF.Status", [_missionId]]] call comspec_overwatch_connect_fnc_extResult;
if (_raw isEqualTo "") exitWith { [] };
private _parts = _raw splitString "|";
if (count _parts < 2 || (_parts select 0) != "OK") exitWith { [] };
missionNamespace setVariable ["COMSPEC_IFFAssets", _parts select 1, true];
[]
