/*
    Caméra IP posée dans Eden / Zeus (classes COMSPEC_ATAK_Cam_*, cfg_cameras.hpp) : l'enregistre comme caméra du camp
    choisi (attribut « Camp », BLUFOR par défaut), visible dans l'app Vidéo live comme une caméra posée par un joueur.
    Serveur seulement. Params : [objet]
    Appelée à l'init de chaque caméra (XEH) et, en secours, sur toutes les caméras de la mission au postInit du serveur
    (towers/config.cpp) : une caméra n'est enregistrée qu'une fois.
*/
params [["_o", objNull]];
if (!isServer || {isNull _o}) exitWith { false };
[{ !isNil "comspec_atak_native_fnc_camAction" && {time > 0} }, {
    params ["_o"];
    if (isNull _o || {!alive _o}) exitWith {};
    if ((_o getVariable ["COMSPEC_ATAK_CamSide", ""]) isNotEqualTo "") exitWith {};
    // Camp : attribut Eden ; posée en Zeus (pas d'attribut) : le camp du joueur le plus proche, BLUFOR sans joueur.
    private _side = _o getVariable ["COMSPEC_CamSide", ""];
    if !(_side in ["WEST", "EAST", "GUER", "CIV"]) then {
        private _near = allPlayers select { alive _x && {(side group _x) in [west, east, independent, civilian]} };
        _near = [_near, [], { _x distance _o }] call BIS_fnc_sortBy;
        _side = if (_near isEqualTo []) then { "WEST" } else { str side group (_near select 0) };
    };
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
    // Pas de poseur, donc personne pour la partager : caméra du camp, visible de tout le camp (fn_camShare « can »).
    _o setVariable ["COMSPEC_ATAK_CamPublic", true, true];
    ["srvReg", _o, true] call comspec_atak_native_fnc_camAction;
    private _in = _o in (missionNamespace getVariable ["COMSPEC_ATAK_Cams", []]);
    private _why = (["visible", [_side, true, "", "", [], _side, "", "", true]] call comspec_atak_native_fnc_camList) select 1;
    diag_log text format ["[COMSPEC CAM] caméra | classe=%1 nom=%2 camp=%3 lives=%4 toc=%4 raison=%5", typeOf _o, _name, _side,
        ["non", "oui"] select _in, [_why, "refusée par le registre (classe hors COMSPEC_ATAK_CameraObj)"] select !_in];
}, [_o]] call CBA_fnc_waitUntilAndExecute;
true
