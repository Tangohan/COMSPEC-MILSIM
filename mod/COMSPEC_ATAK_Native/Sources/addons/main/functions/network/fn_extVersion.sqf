/*
    Version de la DLL réellement chargée : COMSPEC Link (COMSPECExtension) si l'addon connect est présent, sinon la DLL native.
    Lue par GetExtensionVersion (« OK|COMSPECExtension 2.0.80 »), gardée dans missionNamespace « COMSPEC_ExtensionVersion »
    une fois trouvée. Renvoie "" si la DLL ne répond pas (absente, bloquée par BattlEye, ou trop ancienne).
    Params : [relire] (true : ignore la valeur gardée).
*/
params [["_fresh", false]];
private _known = missionNamespace getVariable ["COMSPEC_ExtensionVersion", ""];
if (!_fresh && {_known isEqualType ""} && {!(_known in ["", "unknown"])}) exitWith { _known };
private _ext = ["COMSPECATAKNativeExtension", "COMSPECExtension"] select ([] call comspec_atak_native_fnc_bridge);
private _v = "";
{
    private _r = _ext callExtension [_x, []];
    if (_r isEqualType []) then { _r = _r param [0, ""]; };
    if !(_r isEqualType "") then { _r = str _r; };
    if ((_r select [0, 3]) isEqualTo "OK|") exitWith {
        private _w = _r splitString " |";
        _v = _w param [(count _w) - 1, ""];
    };
} forEach ["GetExtensionVersion", "Ping"];
if (_v isNotEqualTo "") then { missionNamespace setVariable ["COMSPEC_ExtensionVersion", _v]; };
_v
