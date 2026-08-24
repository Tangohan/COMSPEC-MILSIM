/*
    Arme le placeholder ACE avec le mode « Uniquement depuis ATAK ».
    Params: [_placeholder, _player]
*/
params [["_placeholder", objNull, [objNull]], ["_player", objNull, [objNull]]];
if (isNull _placeholder || {isNull _player}) exitWith { false };
if (isNil "ace_explosives_fnc_placeExplosive") exitWith { false };

private _mag = _placeholder getVariable ["ace_explosives_class", ""];
if (!(_mag isEqualType "") || {_mag isEqualTo ""}) exitWith { false };
private _dir = _placeholder getVariable ["ace_explosives_direction", getDir _placeholder];
if (!(_dir isEqualType 0)) then { _dir = getDir _placeholder; };

missionNamespace setVariable ["COMSPEC_ArmAsAtak", true, false];
private _pos = getPosATL _placeholder;
private _exp = [_player, _pos, _dir, _mag, "Command", [], _placeholder] call ace_explosives_fnc_placeExplosive;

if (isNull _exp) exitWith {
    missionNamespace setVariable ["COMSPEC_ArmAsAtak", false, false];
    ["Impossible d’armer cette charge pour ATAK.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};

[_exp, "atak", _player] call comspec_overwatch_connect_fnc_chargeSetTrigger;
["Charge raccordée à ATAK uniquement : tablette en jeu et poste de commandement. Le déclencheur local ne la fera plus sauter.", "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
true
