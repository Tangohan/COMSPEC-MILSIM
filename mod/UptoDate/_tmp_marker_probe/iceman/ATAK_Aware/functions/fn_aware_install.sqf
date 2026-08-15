if (!hasInterface) exitWith {false};

private _mode = profileNamespace getVariable ["Iceman_Aware_detailMode", "default"];
if !(_mode in ["individual", "default"]) then {
    _mode = "default";
};
missionNamespace setVariable ["Iceman_Aware_detailMode", _mode];
missionNamespace setVariable ["Iceman_Aware_buildVersion", "2026.07.17-mini-follow", false];

if (isNil "Iceman_Aware_listsUpdatedEH") then {
    Iceman_Aware_listsUpdatedEH = ["cTab_listsUpdated", {
        call Iceman_fnc_aware_applyLists;
    }] call CBA_fnc_addEventHandler;
};

if (isNil "Iceman_Aware_listsUpdatedLowerEH") then {
    Iceman_Aware_listsUpdatedLowerEH = ["ctab_listsUpdated", {
        call Iceman_fnc_aware_applyLists;
    }] call CBA_fnc_addEventHandler;
};

if (isNil "Iceman_Aware_pfh") then {
    Iceman_Aware_pfh = [{
        call Iceman_fnc_aware_attachMapControls;
        call Iceman_fnc_aware_applyLists;
    }, 0.25] call CBA_fnc_addPerFrameHandler;
};

call Iceman_fnc_aware_attachMapControls;
call Iceman_fnc_aware_applyLists;

true
