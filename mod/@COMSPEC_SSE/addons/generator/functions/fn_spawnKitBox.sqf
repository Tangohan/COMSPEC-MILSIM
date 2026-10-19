/*
    Pose une caisse de kit SSE (serveur).
    [_kit, _posATL, _dir] call comspec_sse_fnc_spawnKitBox -> objet (objNull si kit inconnu)
      _kit : "TEAM" (kit SSE d'équipe) | "BIO" (biométrie) | "DIGITAL" (exploitation numérique) | "DOMEX" (valise de terrain)
    Contenu défini dans la config des caisses (addon evidence, TransportItems).
*/
params [
    ["_kit", "TEAM", [""]],
    ["_posATL", [0, 0, 0], [[]]],
    ["_dir", 0, [0]]
];

if (!isServer) exitWith { objNull };

private _cls = createHashMapFromArray [
    ["TEAM", "COMSPEC_SSE_Box_Team"],
    ["BIO", "COMSPEC_SSE_Box_Biometrics"],
    ["DIGITAL", "COMSPEC_SSE_Box_Digital"],
    ["DOMEX", "COMSPEC_SSE_DomexCase"]
] getOrDefault [toUpper _kit, ""];
if (_cls isEqualTo "" || {!isClass (configFile >> "CfgVehicles" >> _cls)}) exitWith {
    [format ["spawnKitBox : kit inconnu %1", _kit], "WARNING"] call comspec_sse_fnc_log;
    objNull
};

private _box = createVehicle [_cls, [0, 0, 0], [], 0, "CAN_COLLIDE"];
_box setDir _dir;
_box setPosATL [_posATL param [0, 0], _posATL param [1, 0], (_posATL param [2, 0]) max 0];
{ _x addCuratorEditableObjects [[_box], true]; } forEach allCurators;
[format ["spawnKitBox %1 (%2) grid %3", _kit, _cls, mapGridPosition _box]] call comspec_sse_fnc_log;
_box
