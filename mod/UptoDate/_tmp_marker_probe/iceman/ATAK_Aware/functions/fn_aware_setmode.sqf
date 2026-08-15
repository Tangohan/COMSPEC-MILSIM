params [["_mode", "default"]];

if !(_mode in ["individual", "default"]) then {
    _mode = "default";
};

missionNamespace setVariable ["Iceman_Aware_detailMode", _mode];
profileNamespace setVariable ["Iceman_Aware_detailMode", _mode];
saveProfileNamespace;

call Iceman_fnc_aware_updatePanel;

if (_mode == "default") then {
    if !(isNil "cTab_fnc_updateLists") then {
        call cTab_fnc_updateLists;
    };
} else {
    call Iceman_fnc_aware_applyLists;
};

private _label = switch (_mode) do {
    case "individual": {"Individual detail"};
    default {"Standard detail"};
};

["AWARE", format ["%1 active.", _label], 3] call cTab_fnc_addNotification;
true
