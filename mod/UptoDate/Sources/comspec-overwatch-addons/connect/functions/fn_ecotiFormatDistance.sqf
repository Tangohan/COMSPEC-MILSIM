params [["_dist", 0, [0]]];
if (_dist >= 1000) then {
    format ["%1 km", (_dist / 1000) toFixed 1]
} else {
    format ["%1 m", round _dist]
};
