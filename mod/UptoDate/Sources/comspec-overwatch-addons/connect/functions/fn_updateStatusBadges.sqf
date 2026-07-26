/*
    Met à jour les badges version / liaison sur le hub et le terminal ouverts.
*/
if (!hasInterface) exitWith {};

private _version = [] call comspec_overwatch_connect_fnc_getModVersion;
private _state = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
private _detail = missionNamespace getVariable ["COMSPEC_LinkDetail", ""];
private _lastSync = missionNamespace getVariable ["COMSPEC_LastPositionSync", -1];
private _ms = missionNamespace getVariable ["COMSPEC_LastLatencyMs", -1];

private _syncLabel = switch (_state) do {
    case "linked": { "Linked to Athena" };
    case "connecting": { "Connexion…" };
    case "disabled": { "Overwatch disabled" };
    default { "Not connected" };
};

private _syncColor = switch (_state) do {
    case "linked": { "#7dffb3" };
    case "connecting": { "#ffd27a" };
    case "disabled": { "#8899aa" };
    default { "#ff8a7a" };
};

private _ago = "";
if (_lastSync >= 0) then {
    private _sec = round (diag_tickTime - _lastSync);
    if (_sec < 0) then { _sec = 0; };
    _ago = if (_sec < 60) then {
        format ["Position · il y a %1 s", _sec]
    } else {
        if (_sec < 3600) then {
            format ["Position · il y a %1 min", round (_sec / 60)]
        } else {
            format ["Position · il y a %1 h", round (_sec / 3600)]
        };
    };
} else {
    _ago = "Position · not yet sent";
};

if (_ms >= 0) then {
    _ago = format ["%1 · %2 ms", _ago, _ms];
};

if (_detail != "" && {_state != "linked"}) then {
    _ago = _detail;
};

private _versionHtml = format [
    "<t align='left' size='0.62' color='#8aa0b4'>Mod  <t color='#c8d8e8'>%1</t>  <t color='#e8b84a'>BÊTA</t></t>",
    _version
];
private _syncHtml = format [
    "<t align='right' size='0.62'><t color='%1'>●</t>  <t color='#d0dce8'>%2</t></t>",
    _syncColor,
    _syncLabel
];
private _detailHtml = format [
    "<t align='center' size='0.55' color='#7a8c9e'>%1</t>",
    _ago
];

private _hub = uiNamespace getVariable ["COMSPEC_Hub_Display", displayNull];
if (isNull _hub) then { _hub = findDisplay 9969; };
if (!isNull _hub) then {
    private _vCtrl = _hub displayCtrl 9110;
    private _sCtrl = _hub displayCtrl 9111;
    private _dCtrl = _hub displayCtrl 9112;
    if (!isNull _vCtrl) then { _vCtrl ctrlSetStructuredText parseText _versionHtml; };
    if (!isNull _sCtrl) then { _sCtrl ctrlSetStructuredText parseText _syncHtml; };
    if (!isNull _dCtrl) then { _dCtrl ctrlSetStructuredText parseText _detailHtml; };
};

private _chat = uiNamespace getVariable ["COMSPEC_Chat_Display", displayNull];
if (isNull _chat) then { _chat = findDisplay 9999; };
if (!isNull _chat) then {
    private _vCtrl = _chat displayCtrl 1397;
    private _sCtrl = _chat displayCtrl 1396;
    private _dCtrl = _chat displayCtrl 1395;
    if (!isNull _vCtrl) then { _vCtrl ctrlSetStructuredText parseText _versionHtml; };
    if (!isNull _sCtrl) then { _sCtrl ctrlSetStructuredText parseText _syncHtml; };
    if (!isNull _dCtrl) then { _dCtrl ctrlSetStructuredText parseText _detailHtml; };
};
