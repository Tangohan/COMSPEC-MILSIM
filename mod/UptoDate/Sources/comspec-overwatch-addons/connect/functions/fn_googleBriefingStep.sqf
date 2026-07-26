/*
    Navigation précédente / suivante dans un deck Google actif.
    Params: [_delta] (1 = suivante, -1 = précédente)
*/
params [["_delta", 1, [0]]];

if (!(missionNamespace getVariable ["COMSPEC_GoogleBriefingActive", false])) exitWith {
    [_delta] call comspec_overwatch_connect_fnc_briefingBoardStep;
};

private _total = missionNamespace getVariable ["COMSPEC_GoogleBriefingTotal", 0];
if (_total < 1) exitWith {};

private _index = missionNamespace getVariable ["COMSPEC_GoogleBriefingIndex", 0];
_index = (_index + _delta) mod _total;
if (_index < 0) then { _index = _index + _total; };

private _presentationId = missionNamespace getVariable ["COMSPEC_GoogleBriefingPresentationId", ""];
private _url = missionNamespace getVariable ["COMSPEC_GoogleBriefingUrl", ""];

if (_presentationId isEqualTo "") exitWith {
    if (_url isNotEqualTo "") then {
        [_url, _index, true] call comspec_overwatch_connect_fnc_loadGoogleBriefing;
    };
};

"COMSPECExtension" callExtension ["CancelGoogleDeck", []];

private _requestId = format [
    "gslide_%1_%2",
    clientOwner,
    floor (diag_tickTime * 1000)
];
missionNamespace setVariable ["COMSPEC_GoogleBriefingRequestId", _requestId];
missionNamespace setVariable ["COMSPEC_GoogleBriefingPendingIndex", _index];

private _raw = ["COMSPECExtension" callExtension [
    "LoadGoogleSlide",
    [_presentationId, str _index, _requestId]
]] call comspec_overwatch_connect_fnc_extResult;

private _parsed = parseSimpleArray _raw;
if ((_parsed param [0, ""]) isNotEqualTo "accepted") then {
    // Manifeste perdu : recharger le deck entier
    if (_url isNotEqualTo "") then {
        [_url, _index, true] call comspec_overwatch_connect_fnc_loadGoogleBriefing;
    };
} else {
    ["step", _url, _index] call comspec_overwatch_connect_fnc_broadcastGoogleBriefingState;
};
