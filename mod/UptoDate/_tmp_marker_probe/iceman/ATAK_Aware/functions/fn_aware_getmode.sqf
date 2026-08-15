private _mode = missionNamespace getVariable [
    "Iceman_Aware_detailMode",
    profileNamespace getVariable ["Iceman_Aware_detailMode", "default"]
];

if !(_mode in ["individual", "default"]) then {
    _mode = "default";
};

missionNamespace setVariable ["Iceman_Aware_detailMode", _mode];
_mode
