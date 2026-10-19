/*
    Graine déterministe d'une entité : graine de mission + classe + position (décimètre).
    [_entity, _salt] call comspec_sse_fnc_entitySeed -> NUMBER
    Même résultat sur le serveur et les clients tant que l'objet n'a pas bougé.
*/
params [
    ["_entity", objNull, [objNull]],
    ["_salt", "", [""]]
];

if (isNull _entity) exitWith { [[] call comspec_sse_fnc_missionSeed, _salt] call comspec_sse_fnc_hash };

private _pos = getPosASL _entity;
[[] call comspec_sse_fnc_missionSeed, format ["%1|%2|%3|%4",
    typeOf _entity,
    round ((_pos select 0) * 10),
    round ((_pos select 1) * 10),
    _salt
]] call comspec_sse_fnc_hash
