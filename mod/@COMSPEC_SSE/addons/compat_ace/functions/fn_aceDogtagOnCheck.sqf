/*
    Après vérification de plaque ACE : alimente le dossier SSE (identité apparente).
    [_player, _target] call comspec_sse_fnc_aceDogtagOnCheck
*/
params [
    ["_player", objNull, [objNull]],
    ["_target", objNull, [objNull]]
];

if (isNull _target) exitWith { false };
if !(missionNamespace getVariable ["comspec_sse_aceDogtagBridgeEnabled", true]) exitWith { false };

private _hasSse = !isNil {[_target] call comspec_sse_fnc_getData}
    || {_target getVariable ["comspec_sse_enabled", false]};
if (!_hasSse) exitWith { false };

if (!isNil "comspec_sse_fnc_ensureGenerated") then {
    [_target] call comspec_sse_fnc_ensureGenerated;
};

[_target] call comspec_sse_fnc_aceDogtagSync;

if (!isNil "comspec_sse_fnc_requestServerOp") then {
    ["setstate", _target, ["DISCOVERED"]] call comspec_sse_fnc_requestServerOp;
};

private _quality = 55;
if (!isNil "comspec_sse_fnc_calcQuality") then {
    _quality = [55, true, 0.85, 1] call comspec_sse_fnc_calcQuality;
};

private _fog = [_target, "dogtag", _quality] call comspec_sse_fnc_revealFog;
private _lines = _fog getOrDefault ["lines", []];

if (!isNil "comspec_sse_fnc_addJournalEntry") then {
    [
        _fog getOrDefault ["uid", "?"],
        "dogtag",
        typeOf _target,
        _lines joinString " | ",
        _quality,
        "LOCAL"
    ] call comspec_sse_fnc_addJournalEntry;
};

// Hint discret en plus de l’overlay ACE (nom SSE déjà sur la plaque)
if ((count _lines) > 0 && {!(missionNamespace getVariable ["comspec_sse_milsimQuiet", false])}) then {
    private _extra = _lines select {(_x find "Identité") >= 0 || {(_x find "Nationalité") >= 0} || {(_x find "Groupe") >= 0}};
    if ((count _extra) == 0) then { _extra = _lines select [0, (count _lines) min 2]; };
    if ((count _extra) > 0) then {
        hintSilent format ["SSE — plaque :\n%1", _extra joinString "\n"];
    };
};

true
