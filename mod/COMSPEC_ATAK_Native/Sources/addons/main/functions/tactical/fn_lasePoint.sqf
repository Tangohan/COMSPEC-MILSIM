/*
    Télémètre vers l'ATAK : le point mesuré devient un marqueur sur la carte du téléphone (palette du mode MARQUEUR :
    camp, type, couleur, canal et durée, fn_markerDrop), comme le transfert de données d'un Vector vers un GPS.
      ["vector", [distance m, azimut °, site °]]  mesure ACE Vector 21 transmise (événement CBA ace_vector_rangefinderData,
                                                  envoyé par ACE après une mesure azimut + distance)
      ["aim"]                                     touche « Envoyer le point visé à l'ATAK » : point du désignateur laser
                                                  en cours (laserTarget), sinon point visé dans une optique ou des jumelles
                                                  (jusqu'à 5 km)
    Réglage serveur comspec_atak_native_lase_link. Il faut un ATAK utilisable (fn_canUse) ; 2 s au moins entre deux points.
*/
params [["_mode", ""], ["_arg", []]];
if !(missionNamespace getVariable ["comspec_atak_native_lase_link", true]) exitWith { false };
if (([] call comspec_atak_native_fnc_canUse) isNotEqualTo "") exitWith { false };
if (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_LaseT", -10]) < 2) exitWith { false };
private _from = eyePos player;
private _pos = [];
switch (_mode) do {
    case "vector": {
        _arg params [["_d", 0, [0]], ["_az", 0, [0]], ["_inc", 0, [0]]];
        if (_d <= 0) exitWith {};
        private _h = _d * cos _inc;
        _pos = ASLToATL (_from vectorAdd [_h * sin _az, _h * cos _az, _d * sin _inc]);
    };
    case "aim": {
        private _lt = laserTarget player;
        if (isNull _lt) then { _lt = laserTarget (vehicle player); };
        if (!isNull _lt) exitWith { _pos = getPosATL _lt; };
        if (cameraView isNotEqualTo "GUNNER") exitWith {
            ["WARNING", "Télémètre : visez dans une optique ou des jumelles", 3, 20] call comspec_atak_native_fnc_notify;
        };
        private _dir = getCameraViewDirection player;
        private _hit = lineIntersectsSurfaces [_from, _from vectorAdd (_dir vectorMultiply 5000), player, vehicle player, true, 1, "VIEW", "FIRE"];
        if ((count _hit) isEqualTo 0) exitWith {
            ["WARNING", "Télémètre : pas d'écho (au-delà de 5 km ?)", 3, 20] call comspec_atak_native_fnc_notify;
        };
        _pos = ASLToATL ((_hit select 0) select 0);
    };
};
if ((count _pos) < 2) exitWith { false };
uiNamespace setVariable ["COMSPEC_ATAK_LaseT", diag_tickTime];
private _d = round (_from vectorDistance (ATLToASL _pos));
private _az = round ((player getDir _pos) mod 360);
["INFO", format ["Point télémétré : %1 m, azimut %2°", _d, _az], 3, 20] call comspec_atak_native_fnc_notify;
[_pos] call comspec_atak_native_fnc_markerDrop;
true
