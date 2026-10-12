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

// Terminal ouvert sans personne visée (menu ATAK, solo) : la personne la plus proche
// devant l'opérateur devient le sujet de la fiche — le relevé lui est rattaché.
if (isNull _target) then {
    private _c = cursorObject;
    if (!isNull _c && {_c isKindOf "CAManBase"} && {_c != _player} && {(_player distance _c) < 5}) then {
        _target = _c;
    } else {
        // nearestObjects (et non nearEntities) : les corps comptent aussi.
        private _near = (nearestObjects [_player, ["CAManBase"], 3]) - [_player];
        _near = _near select { (vehicle _x) isEqualTo _x };
        private _best = 1e9;
        {
            private _d = _player distance _x;
            if (_d < _best) then { _best = _d; _target = _x; };
        } forEach _near;
    };
    if (!isNull _target) then { uiNamespace setVariable ["COMSPEC_SsePerson_Target", _target]; };
};

// Retour visible sur l'écran du SEEK : « announce » n'écrit que dans le journal
// HTML de la tablette — hors liaison / sans tablette, l'opérateur ne voyait rien.
private _fnc_screenMsg = {
    params ["_msg", ["_col", "#9a5b00"]];
    uiNamespace setVariable ["COMSPEC_SsePerson_BioMsg", format ["<t size='0.46' color='%1'>%2</t>", _col, _msg]];
    if (!isNil "comspec_overwatch_connect_fnc_ssePersonRefreshPanels") then {
        [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
    };
};

// Moteur biométrique (pack SSE) : relevé selon les conditions réelles. Un relevé
// médiocre se refait (il remplace le précédent) ; sans moteur, un seul par type.
private _engine = !isNil "comspec_sse_fnc_bioCapture" && {missionNamespace getVariable ["comspec_sse_bio_enabled", true]};
private _samples = uiNamespace getVariable ["COMSPEC_SsePerson_Samples", []];
if (!(_samples isEqualType [])) then { _samples = []; };
private _already = false;
{
    if ((_x isEqualType []) && {(count _x) > 0} && {(_x select 0) isEqualTo _kind}) exitWith { _already = true; };
} forEach _samples;
if (_already && {!_engine}) exitWith {
    [format ["%1 déjà enregistré pour cette personne.", _label], "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
    [format ["%1 déjà enregistré.", _label], "#1d6b34"] call _fnc_screenMsg;
};
// Blessures, amputations, prothèse, décès ancien : refus avant de s'agenouiller.
if (_engine && {!isNull _target}) then {
    private _dry = [_target, _player, _kind, "terminal", true] call comspec_sse_fnc_bioCapture;
    if !(_dry getOrDefault ["ok", false]) then { _already = false; uiNamespace setVariable ["COMSPEC_SseSampleBlocked", _dry getOrDefault ["blocked", "Relevé impossible."]]; } else { uiNamespace setVariable ["COMSPEC_SseSampleBlocked", ""]; };
} else {
    uiNamespace setVariable ["COMSPEC_SseSampleBlocked", ""];
};
private _blockedMsg = uiNamespace getVariable ["COMSPEC_SseSampleBlocked", ""];
if (_blockedMsg isNotEqualTo "") exitWith {
    [_blockedMsg, "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    [_blockedMsg, "#b3261e"] call _fnc_screenMsg;
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

// Le SEEK est lui-même un lecteur biométrique (capteur d'empreintes, objectif iris,
// écouvillon ADN du coffret) : l'objet du terminal COMSPEC_Item_SeekTerminal — ou
// l'accès sans objet (comspec_sse_require_item = false) — suffit. Auparavant seuls
// SEEKII / BII étaient reconnus : avec le SEEK COMSPEC en main, aucun relevé ne partait.
if (_kit isEqualTo "" && {[] call comspec_overwatch_connect_fnc_sseHasTerminalItem}) then {
    _kit = "terminal";
};

if (_kit isEqualTo "") exitWith {
    [_needTxt, "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    [_needTxt] call _fnc_screenMsg;
};

if (
    _takenVar isNotEqualTo ""
    && {_consume || {!_engine}}
    && {!isNull _target}
    && {_target getVariable [_takenVar, false]}
) exitWith {
    [format ["%1 déjà prélevé sur cette personne.", _label], "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
    [format ["%1 déjà prélevé sur cette personne.", _label], "#1d6b34"] call _fnc_screenMsg;
};
uiNamespace setVariable ["COMSPEC_SsePerson_BioMsg", ""];
// Le SEEK se rouvre sur l'écran de la modalité relevée : empreintes (3), iris (8), revue (9) pour l'ADN.
uiNamespace setVariable ["COMSPEC_SseSampleReopenPage", switch (_kind) do {
    case "iris": { 8 };
    case "adn": { 9 };
    default { 3 };
}];

uiNamespace setVariable ["COMSPEC_SseSampleFinish", {
    params ["_kind", "_label", "_kit", "_consume", "_give", "_takenVar", "_target"];

    // Relevé par le moteur : qualité selon le terrain, NFIQ par doigt, OD/OG, facteurs.
    private _cap = createHashMap;
    if (!isNull _target && {!isNil "comspec_sse_fnc_bioCapture"} && {missionNamespace getVariable ["comspec_sse_bio_enabled", true]}) then {
        private _device = switch (true) do {
            case (_kit in ["FingerprintScannerKit", "EyeScannerKit"]): { "bii" };
            case (_kit in ["FingerprintCollectionKit", "DNACollectionKit"]): { "kit" };
            default { "seek" };
        };
        _cap = [_target, player, _kind, _device] call comspec_sse_fnc_bioCapture;
        if !(_cap getOrDefault ["ok", false]) exitWith {
            private _why = _cap getOrDefault ["blocked", "Relevé impossible."];
            uiNamespace setVariable ["COMSPEC_SsePerson_BioMsg", format ["<t size='0.46' color='#b3261e'>%1</t>", _why]];
            [_why, "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
        };
    };
    if ((count _cap) > 0 && {!(_cap getOrDefault ["ok", false])}) exitWith {};

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
    private _detail = [];
    private _factors = [];
    if ((count _cap) > 0) then {
        _quality = _cap get "quality";
        _detail = switch (_kind) do { case "iris": { _cap get "eyes" }; case "adn": { [] }; default { _cap get "nfiqs" }; };
        _factors = _cap getOrDefault ["factors", []];
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
    if ((count _cap) > 0) then { _labRef = _cap get "labRef"; };
    // Un nouveau relevé remplace le précédent du même type (recapture).
    _samples = _samples select { !((_x isEqualType []) && {(count _x) > 0} && {(_x select 0) isEqualTo _kind}) };
    _samples pushBack [_kind, _quality, _labRef, _detail, _factors];
    uiNamespace setVariable ["COMSPEC_SsePerson_Samples", _samples];
    uiNamespace setVariable ["COMSPEC_SsePerson_BioPending", true];
    uiNamespace setVariable ["COMSPEC_SsePerson_BioMsg", ""];
    // Le relevé suit la personne : une fiche rouverte sur elle le retrouve (hors liaison aussi).
    if (!isNull _target) then {
        private _onTarget = _target getVariable ["comspec_sse_bioSamples", []];
        if (!(_onTarget isEqualType [])) then { _onTarget = []; };
        _onTarget = _onTarget select { (_x isEqualType []) && {(count _x) >= 3} && {(_x select 0) isNotEqualTo _kind} };
        _onTarget pushBack [_kind, _quality, _labRef, _detail, _factors];
        _target setVariable ["comspec_sse_bioSamples", _onTarget, true];
    };

    [
        format ["%1 : échantillon %5 (qualité %2%3) — réf. %4%6", _label, _quality, "%", _labRef,
            ["exploitable", "médiocre, recapture conseillée"] select (_quality < 65),
            ["", format [" · %1", _factors joinString ", "]] select (_factors isNotEqualTo [])],
        "tactical",
        ["info", "warn"] select (_quality < 65)
    ] call comspec_overwatch_connect_fnc_announce;
}];

uiNamespace setVariable ["COMSPEC_SseSampleReopen", {
    params [["_page", 3, [0]]];
    if (!isNil "comspec_sse_fnc_bioAnimate") then {
        [player, objNull, "", "stop"] call comspec_sse_fnc_bioAnimate;
    } else {
        if (vehicle player == player) then {
            player playMoveNow "AmovPknlMstpSnonWnonDnon";
        };
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

// Animation du relevé (pack SSE) : à genoux sur un corps, debout face à un sujet coopérant,
// appareil en main, sujet qui tend la main ou regarde le SEEK ; sinon l'ancienne posture.
if (!isNil "comspec_sse_fnc_bioAnimate") then {
    [_player, _target, _kind, "start"] call comspec_sse_fnc_bioAnimate;
} else {
    if (vehicle _player == _player) then {
        _player playMoveNow "AinvPknlMstpSnonWnonDnon_medicUp1";
    };
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
            [uiNamespace getVariable ["COMSPEC_SseSampleReopenPage", 3]] call (uiNamespace getVariable ["COMSPEC_SseSampleReopen", {}]);
        },
        {
            if (!isNil "comspec_sse_fnc_bioAnimate") then {
                [player, objNull, "", "stop"] call comspec_sse_fnc_bioAnimate;
            } else {
                if (vehicle player == player) then {
                    player playMoveNow "AmovPknlMstpSnonWnonDnon";
                };
            };
            ["Relevé interrompu.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
            uiNamespace setVariable ["COMSPEC_SsePerson_BioMsg", "<t size='0.46' color='#9a5b00'>Relevé interrompu : restez à moins de 5 m du sujet.</t>"];
            [uiNamespace getVariable ["COMSPEC_SseSampleReopenPage", 3]] call (uiNamespace getVariable ["COMSPEC_SseSampleReopen", {}]);
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
    [uiNamespace getVariable ["COMSPEC_SseSampleReopenPage", 3]] call (uiNamespace getVariable ["COMSPEC_SseSampleReopen", {}]);
}, _args, _duration] call CBA_fnc_waitAndExecute;
