disableSerialization;
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
private _display = _state getOrDefault ["display",displayNull];
if (isNull _display) exitWith { [] call comspec_atak_native_fnc_schedulerStop };
private _now = diag_tickTime;
if ((_now - (_state getOrDefault ["lastFast",0])) >= 0.25) then { _state set ["lastFast",_now]; [] call comspec_atak_native_fnc_localDataRefresh; };
// Animation d'extinction en cours (batterie vide, casse) : c'est fn_powerFx qui range le téléphone à la fin.
private _why = if ((_now - (_state getOrDefault ["lastSecond",0])) >= 1 && {((uiNamespace getVariable ["COMSPEC_ATAK_PowerFx", []]) param [1, -1]) <= _now}) then { [] call comspec_atak_native_fnc_canUse } else { "" };
if (_why isNotEqualTo "") exitWith {
    // Téléphone perdu, retiré, batterie vide ou détruit : on range tout.
    uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", false];
    [] call comspec_atak_native_fnc_close;
    [_why] call comspec_atak_native_fnc_deviceDenied;
};
if ((_now - (_state getOrDefault ["lastSecond",0])) >= 1) then { _state set ["lastSecond",_now]; [] call comspec_atak_native_fnc_statusUpdate; [] call comspec_atak_native_fnc_notificationsRender; };
private _refresh = profileNamespace getVariable ["COMSPEC_ATAK_BftRefresh",3];
if ((_now - (_state getOrDefault ["lastRemote",0])) >= _refresh) then { _state set ["lastRemote",_now]; [] call comspec_atak_native_fnc_remoteSync; };
if ((_now - (_state getOrDefault ["lastSlow",0])) >= 10) then { _state set ["lastSlow",_now]; [] call comspec_atak_native_fnc_importLegacyData; };
