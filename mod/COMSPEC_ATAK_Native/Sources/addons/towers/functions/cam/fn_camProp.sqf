/*
    Caméra IP posée dans Eden / Zeus (classes COMSPEC_ATAK_Cam_*, cfg_cameras.hpp) : l'enregistre comme caméra du camp
    choisi (attribut « Camp », BLUFOR par défaut), visible dans l'app Vidéo live comme une caméra posée par un joueur.
    Serveur seulement. Params : [objet]
*/
params [["_o", objNull]];
if (!isServer || {isNull _o}) exitWith { false };
[{ !isNil "comspec_atak_native_fnc_camAction" && {time > 0} }, {
    params ["_o"];
    if (isNull _o || {!alive _o}) exitWith {};
    if ((_o getVariable ["COMSPEC_ATAK_CamSide", ""]) isNotEqualTo "") exitWith {};
    private _side = _o getVariable ["COMSPEC_CamSide", "WEST"];
    if !(_side in ["WEST", "EAST", "GUER", "CIV"]) then { _side = "WEST"; };
    private _name = _o getVariable ["COMSPEC_CamName", ""];
    if !(_name isEqualType "") then { _name = ""; };
    _name = ((_name splitString "<>&""'") joinString "") select [0, 16];
    if (_name isEqualTo "") then {
        private _names = ((missionNamespace getVariable ["COMSPEC_ATAK_Cams", []]) select { !isNull _x }) apply { _x getVariable ["COMSPEC_ATAK_CamName", ""] };
        private _i = 1;
        while { (format ["CAM-%1", _i]) in _names } do { _i = _i + 1; };
        _name = format ["CAM-%1", _i];
    };
    _o setVariable ["COMSPEC_ATAK_CamSide", _side, true];
    _o setVariable ["COMSPEC_ATAK_CamName", _name, true];
    _o setVariable ["COMSPEC_ATAK_CamOwner", "", true];
    _o setVariable ["COMSPEC_ATAK_CamOwnerUid", "", true];
    _o setVariable ["COMSPEC_ATAK_CamGroup", "", true];
    ["srvReg", _o, true] call comspec_atak_native_fnc_camAction;
}, [_o]] call CBA_fnc_waitUntilAndExecute;
true
