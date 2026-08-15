params [["_target", objNull]];

if (isNull _target) exitWith {false};
if (_target isKindOf "CAManBase") exitWith {false};
if (_target distance player > 6) exitWith {false};

private _modelInfo = getModelInfo _target;
private _model = _modelInfo param [0, ""];
private _text = toLower ((typeOf _target) + " " + (getText (configOf _target >> "displayName")) + " " + _model);
private _keywords = [
    "screen",
    "video",
    "videoprojector",
    "monitor",
    "computer",
    "laptop",
    "terminal",
    "display",
    "tv",
    "projector",
    "projection",
    "tripod",
    "whiteboard",
    "board",
    "map",
    "briefing",
    "console",
    "pc",
    "rugged",
    "tablet"
];

(_keywords findIf {_text find _x > -1}) > -1
