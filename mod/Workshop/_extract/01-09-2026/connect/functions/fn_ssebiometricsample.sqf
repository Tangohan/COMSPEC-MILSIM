/*
    Prélèvement biométrique simulé (roleplay) — empreintes, iris ou ADN.

    Si le pack BII (kits [SSE]) est chargé et que l’opérateur a le bon objet,
    le terminal se referme, l’animation d’agenouillement du pack se joue, puis
    le SEEK se rouvre sur la page biométrie.
*/
params [["_kind", "empreintes", [""]]];

if (!hasInterface) exitWith {};

_kind = toLower _kind;
if (!(_kind in ["empreintes", "iris", "adn"])) then { _kind = "empreintes"; };

private _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9991; };

private _label = switch (_kind) do {
    case "iris": { "Relevé iris" };
    case "adn": { "Prélèvement ADN" };
    default { "Relevé d’empreintes" };
};

private _target = uiNamespace getVariable ["COMSPEC_SsePerson_Target", objNull];
private _player = player;

// Un seul échantillon par type sur cette fiche.
private _samples = uiNamespace getVariable ["COMSPEC_SsePerson_Samples", []];
if (!(_samples isEqualType [])) then { _samples = []; };
private _already = false;
{
    if ((_x isEqualType []) && {(count _x) > 0} && {(_x select 0) isEqualTo _kind}) exitWith { _already = true; };
} forEach _samples;
if (_already) exitWith {
    [format ["%1 déjà enregistré pour cette personne.", _label], "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
};

private _gear = (items _player) + (assignedItems _player);
private _fnc_has = {
    params ["_cls"];
    if (_cls isEqualTo "") exitWith { false };
    (_cls in _gear)
};

// Kits BII [SSE] d’abord, puis matériel COMSPEC.
private _kit = "";
private _sound = "";
private _duration = 8;
private _consume = false;
private _give = "";
private _takenVar = "";
private _needTxt = "";

switch (_kind) do {
    case "iris": {
        _needTxt = "Il vous faut le scanner oculaire dans vos poches pour relever l’iris.";
        _duration = 10;
        if (["EyeScannerKit"] call _fnc_has) then {
            _kit = "EyeScannerKit";
            _sound = "EyeScannerDraw";
            _duration = 5;
            _give = "EyeScannerSample";
            _takenVar = "EyeScannerTaken";
        } else {
            if (["COMSPEC_SSE_SEEKII"] call _fnc_has || {["BII_Identifi_Device"] call _fnc_has}) then {
                _kit = "terminal";
            };
        };
    };
    case "adn": {
        _needTxt = "Il vous faut le kit de prélèvement ADN dans vos poches.";
        _duration = 20;
        if (["DNACollectionKit"] call _fnc_has) then {
            _kit = "DNACollectionKit";
            _sound = "DNADraw";
            _duration = 20;
            _consume = true;
            _give = "DNASample";
            _takenVar = "DNATaken";
        } else {
            if (["COMSPEC_SSE_DNKit"] call _fnc_has || {["COMSPEC_SSE_SEEKII"] call _fnc_has} || {["BII_Identifi_Device"] call _fnc_has}) then {
                _kit = "terminal";
            };
        };
    };
    default {
        _needTxt = "Il vous faut le kit ou le scanner d’empreintes dans vos poches.";
        _duration = 8;
        if (["FingerprintScannerKit"] call _fnc_has) then {
            _kit = "FingerprintScannerKit";
            _sound = "FingerprintScannerDraw";
            _duration = 5;
            _give = "FingerprintScannerSample";
            _takenVar = "FingerPrintScannerTaken";
        } else {
            if (["FingerprintCollectionKit"] call _fnc_has) then {
                _kit = "FingerprintCollectionKit";
                _sound = "FingerprintDraw";
                _duration = 20;
                _consume = true;
                _give = "FingerprintSample";
                _takenVar = "FingerPrintTaken";
            } else {
                if (["COMSPEC_SSE_FingerprintKit"] call _fnc_has || {["COMSPEC_SSE_SEEKII"] call _fnc_has} || {["BII_Identifi_Device"] call _fnc_has}) then {
                    _kit = "terminal";
                };
            };
        };
    };
};

if (_kit isEqualTo "") exitWith {
    [_needTxt, "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
};

if (
    _takenVar isNotEqualTo ""
    && {!isNull _target}
    && {_target getVariable [_takenVar, false]}
) exitWith {
    [format ["%1 déjà prélevé sur cette personne.", _label], "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
};

uiNamespace setVariable ["COMSPEC_SseSampleFinish", {
    params ["_kind", "_label", "_kit", "_consume", "_give", "_takenVar", "_target"];

    private _player = player;
    if (_consume && {_kit isNotEqualTo ""} && {_kit isNotEqualTo "terminal"}) then {
        _player removeItem _kit;
    };
    if (_give isNotEqualTo "" && {isClass (configFile >> "CfgWeapons" >> _give)}) then {
        _player addItem _give;
    };
    if (_takenVar isNotEqualTo "" && {!isNull _target}) then {
        _target setVariable [_takenVar, true, true];
    };

    private _quality = switch (_kind) do {
        case "adn": { 88 + floor (random 11) };
        case "iris": { 61 + floor (random 30) };
        default { 70 + floor (random 26) };
    };
    private _prefix = switch (_kind) do {
        case "adn": { "ADN" };
        case "iris": { "IRI" };
        default { "EMP" };
    };
    private _labRef = format [
        "LAB-%1-%2%3",
        (date select 0),
        _prefix,
        1000 + floor (random 9000)
    ];

    private _samples = uiNamespace getVariable ["COMSPEC_SsePerson_Samples", []];
    if (!(_samples isEqualType [])) then { _samples = []; };
    _samples pushBack [_kind, _quality, _labRef];
    uiNamespace setVariable ["COMSPEC_SsePerson_Samples", _samples];
    uiNamespace setVariable ["COMSPEC_SsePerson_BioPending", true];

    [
        format ["%1 : échantillon exploitable (qualité %2%3) — réf. %4", _label, _quality, "%", _labRef],
        "tactical",
        "info"
    ] call comspec_overwatch_connect_fnc_announce;
}];

uiNamespace setVariable ["COMSPEC_SseSampleReopen", {
    params [["_page", 3, [0]]];
    if (vehicle player == player) then {
        player playMoveNow "AmovPknlMstpSnonWnonDnon";
    };
    uiNamespace setVariable ["COMSPEC_SsePerson_ResumeCollect", true];
    [{
        params ["_page"];
        private _ok = [] call comspec_overwatch_connect_fnc_ssePersonDialogShow;
        if (_ok) then {
            [_page] call comspec_overwatch_connect_fnc_sseTerminalPage;
            if (!isNil "comspec_overwatch_connect_fnc_ssePersonRefreshPanels") then {
                [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
            };
        };
    }, [_page], 0.35] call CBA_fnc_waitAndExecute;
}];

// Mémoriser les champs visibles avant de fermer l’écran.
if (!isNull _disp) then {
    uiNamespace setVariable ["COMSPEC_SsePerson_IdentityCache", [
        trim (ctrlText (_disp displayCtrl 9501)),
        trim (ctrlText (_disp displayCtrl 9502)),
        trim (ctrlText (_disp displayCtrl 9503)),
        trim (ctrlText (_disp displayCtrl 9504)),
        trim (ctrlText (_disp displayCtrl 9507)),
        trim (ctrlText (_disp displayCtrl 9508))
    ]];
    uiNamespace setVariable ["COMSPEC_SsePerson_SuspendUnload", true];
    _disp closeDisplay 2;
};

if (vehicle _player == _player) then {
    _player playMoveNow "AinvPknlMstpSnonWnonDnon_medicUp1";
};
if (_sound isNotEqualTo "" && {isClass (configFile >> "CfgSounds" >> _sound)}) then {
    playSound _sound;
};

private _args = [_kind, _label, _kit, _consume, _give, _takenVar, _target];

if (!isNil "ace_common_fnc_progressBar") exitWith {
    [
        _duration,
        _args,
        {
            (_this select 0) params ["_kind", "_label", "_kit", "_consume", "_give", "_takenVar", "_target"];
            [_kind, _label, _kit, _consume, _give, _takenVar, _target] call (uiNamespace getVariable ["COMSPEC_SseSampleFinish", {}]);
            [3] call (uiNamespace getVariable ["COMSPEC_SseSampleReopen", {}]);
        },
        {
            if (vehicle player == player) then {
                player playMoveNow "AmovPknlMstpSnonWnonDnon";
            };
            ["Relevé interrompu.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
            [3] call (uiNamespace getVariable ["COMSPEC_SseSampleReopen", {}]);
        },
        format ["%1…", _label],
        {
            private _t = uiNamespace getVariable ["COMSPEC_SsePerson_Target", objNull];
            isNull _t || {(player distance _t) < 5}
        },
        ["isNotInside"]
    ] call ace_common_fnc_progressBar;
};

[format ["%1 en cours…", _label], "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
[{
    _this params ["_kind", "_label", "_kit", "_consume", "_give", "_takenVar", "_target"];
    [_kind, _label, _kit, _consume, _give, _takenVar, _target] call (uiNamespace getVariable ["COMSPEC_SseSampleFinish", {}]);
    [3] call (uiNamespace getVariable ["COMSPEC_SseSampleReopen", {}]);
}, _args, _duration] call CBA_fnc_waitAndExecute;
