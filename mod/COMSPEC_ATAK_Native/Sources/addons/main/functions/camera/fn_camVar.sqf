/*
    Variables d'une caméra de script (objet créé par camCreate) : setVariable / getVariable ne marchent pas sur ces
    objets (TEST VIDÉO de TTRD, 18/10 : chaque relevé lisait la valeur par défaut). L'état du flux du drone était ainsi
    perdu à chaque image : caméra recréée trois fois par seconde (« rendering » lu faux) et image posée sur la cible
    par défaut comspec_dronelive au lieu de comspec_droneliveh (qualité haute) : case vide, aucune vidéo.
    Les valeurs sont rangées dans une table de la mission, une entrée par caméra (hashValue), effacée à la destruction.

      [caméra, clé, défaut] call comspec_atak_native_fnc_camVar          → valeur (défaut si absente ou caméra nulle)
      [caméra, clé, valeur, true] call comspec_atak_native_fnc_camVar    → pose la valeur (nil l'efface)
      [caméra] call comspec_atak_native_fnc_camVar                       → oublie toutes les valeurs de la caméra
*/
params [["_cam", objNull], ["_key", ""], "_val", ["_set", false]];
private _all = missionNamespace getVariable "COMSPEC_ATAK_CamVars";
if (isNil "_all") then { _all = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_CamVars", _all]; };
if (isNull _cam) exitWith { if (_set) then { nil } else { _val } };
private _id = hashValue _cam;
if (_key isEqualTo "") exitWith { _all deleteAt _id; nil };
// Caméras détruites : entrées retirées au passage (camDestroy ne prévient pas).
if ((count _all) > 32) then { { if (isNull (_y getOrDefault ["#obj", objNull])) then { _all deleteAt _x; }; } forEach +_all; };
if (_set) exitWith {
    private _vars = _all getOrDefault [_id, createHashMap];
    if ((count _vars) isEqualTo 0) then { _vars set ["#obj", _cam]; _all set [_id, _vars]; };
    if (isNil "_val") then { _vars deleteAt _key; } else { _vars set [_key, _val]; };
    nil
};
(_all getOrDefault [_id, createHashMap]) getOrDefault [_key, _val]
