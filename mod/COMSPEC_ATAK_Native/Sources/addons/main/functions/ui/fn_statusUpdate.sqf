/* Barre d'état (indicatif, position, réseau, cap, heure), pastilles et rafraîchissement des pages vivantes. */
disableSerialization;
private _d = findDisplay 88500;
if (isNull _d) exitWith {};
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];

private _legacy = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
private _net = switch (toLower _legacy) do {
    case "linked": { "CONNECTED" };
    case "connecting";
    case "degraded": { "DEGRADED" };
    default { _state getOrDefault ["networkState", "OFFLINE"] };
};
_state set ["networkState", _net];
private _netLabel = switch (_net) do { case "CONNECTED": { "ATHENA OK" }; case "DEGRADED": { "ATHENA DÉGRADÉ" }; default { "HORS LIGNE" }; };
private _netColor = switch (_net) do { case "CONNECTED": { [0.36, 0.78, 0.42, 1] }; case "DEGRADED": { [0.95, 0.67, 0.20, 1] }; default { [0.58, 0.64, 0.60, 1] }; };

private _callsign = if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then { [] call comspec_overwatch_connect_fnc_getCallsign } else { groupId group player };
(_d displayCtrl 88512) ctrlSetText format ["%1   %2", _callsign, mapGridPosition player];
private _right = _d displayCtrl 88513;
_right ctrlSetText format ["%1   %2°   %3", _netLabel, round getDir player, [daytime, "HH:MM"] call BIS_fnc_timeToString];
_right ctrlSetTextColor _netColor;

private _page = _state getOrDefault ["activePage", "LAUNCHER"];

// Pastilles : on ne reconstruit le dock (et le lanceur) que si un compteur change.
private _badges = [["CHAT"] call comspec_atak_native_fnc_appBadge, ["TASK"] call comspec_atak_native_fnc_appBadge];
if (_badges isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_BadgeSig", []])) then {
    uiNamespace setVariable ["COMSPEC_ATAK_BadgeSig", _badges];
    if (_page isEqualTo "LAUNCHER") then { [_page] call comspec_atak_native_fnc_pageRender; } else { [] call comspec_atak_native_fnc_dockRender; };
};

// Pages vivantes : re-rendu quand leurs données changent (le brouillon de message est conservé).
private _sig = switch (_page) do {
    case "CHAT": { [count (_data getOrDefault ["messages", []]), count (_data getOrDefault ["p2p", []])] };
    case "TASK": { [(_data getOrDefault ["revisions", createHashMap]) getOrDefault ["tasks", 0], (simpleTasks player) apply { taskState _x }] };
    case "GROUP": { (units group player) apply { [name _x, alive _x, lifeState _x, round ((damage _x) * 4)] } };
    default { [] };
};
if ((_state getOrDefault ["pageSigPage", ""]) isEqualTo _page && {_sig isNotEqualTo (_state getOrDefault ["pageSig", []])}) then {
    [_page] call comspec_atak_native_fnc_pageRender;
};
_state set ["pageSig", _sig];
_state set ["pageSigPage", _page];
