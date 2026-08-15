private _state = call Iceman_fnc_roip_getState;
_state set ["uiRadioSignature", ""];
_state set ["uiTgSignature", ""];
_state set ["appliedSignature", "__REFRESH__"];
call Iceman_fnc_roip_applyLinks;
call Iceman_fnc_roip_updatePanel;
true
