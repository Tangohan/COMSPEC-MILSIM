params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

uiNamespace setVariable ["Iceman_ATAK_ROIP_group", _group];
private _state = call Iceman_fnc_roip_getState;
_state set ["uiRadioSignature", ""];
_state set ["uiTgSignature", ""];
call Iceman_fnc_roip_updatePanel;

[{
    call Iceman_fnc_roip_updatePanel;
}, 0.05] call CBA_fnc_waitAndExecute;
