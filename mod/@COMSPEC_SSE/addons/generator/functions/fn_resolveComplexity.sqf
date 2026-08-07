/*
    [_complexity] call comspec_sse_fnc_resolveComplexity
    -> [contactCount, smsCount, docCount, hasBio, intelCount, locCount]
*/
params [
    ["_complexity", "STANDARD", [""]]
];

switch (toUpper _complexity) do {
    case "LIGHT": { [4, 3, 1, false, 1, 2] };
    case "DETAILED": { [12, 12, 4, true, 3, 4] };
    case "HIGH_VALUE": { [20, 18, 7, true, 5, 6] };
    default { [8, 7, 2, true, 2, 3] }; // STANDARD
};
