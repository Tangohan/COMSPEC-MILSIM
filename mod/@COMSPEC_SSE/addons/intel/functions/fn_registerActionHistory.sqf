params [
    ["_entity", objNull, [objNull]],
    ["_player", objNull, [objNull]],
    ["_action", "", [""]],
    ["_detail", "", [""]]
];
if (isNil "comspec_sse_actionHistory") then { comspec_sse_actionHistory = []; };
comspec_sse_actionHistory pushBack (createHashMapFromArray [
    ["at", time],
    ["entity", if (isNull _entity) then {""} else {netId _entity}],
    ["player", if (isNull _player) then {""} else {name _player}],
    ["action", _action],
    ["detail", _detail]
]);
if (count comspec_sse_actionHistory > 500) then {
    comspec_sse_actionHistory deleteRange [0, count comspec_sse_actionHistory - 500];
};
true
