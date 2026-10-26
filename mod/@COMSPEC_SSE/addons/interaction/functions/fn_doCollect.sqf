/*
    [_target, _player, _seal] call comspec_sse_fnc_doCollect
    _seal = true : « Mettre sous scellé » (action ACE unique) : collecte puis scellé dans la foulée,
                   un sachet consommé (comspec_sse_fnc_bagEvidence).
*/
params [
    ["_target", objNull, [objNull]],
    ["_player", player, [objNull]],
    ["_seal", false, [false]]
];

if !([_player, "evidence_bag"] call comspec_sse_fnc_hasEquipment) exitWith {
    ["Sachet de preuve SSE (ou kit médical ACE compatible) requis.", "WARNING"] call comspec_sse_fnc_notify;
};
// Une seule collecte par entité (état COLLECTED posé par le serveur) : la collecte se répétait à l'infini, chaque
// fois avec une ligne de plus dans la chaîne de possession, le journal et le registre.
// La collecte est suivie par le drapeau « collected » (posé par le serveur) et non par l'état seul : une pièce
// TRANSMITTED depuis l'ATAK sans avoir été collectée doit rester collectable (chaîne de possession + garde).
private _isCollected = {
    params ["_e"];
    private _d = [_e] call comspec_sse_fnc_getData;
    if (isNil "_d") exitWith { false };
    ([_d, "collected", false] call comspec_sse_fnc_getPair) isEqualTo true
        || {([_d, "state", ""] call comspec_sse_fnc_getPair) isEqualTo "COLLECTED"}
};
if ([_target] call _isCollected) exitWith {
    // Déjà collectée mais pas encore scellée : le scellé seul reste à faire.
    if (_seal) exitWith { [_target, _player] call comspec_sse_fnc_bagEvidence; };
    ["Pièce déjà collectée : voir le registre des scellés.", "WARNING"] call comspec_sse_fnc_notify;
};
if (_seal && {!("COMSPEC_SSE_EvidenceBag" in (items _player))}) exitWith {
    ["Sachet de preuve SSE requis pour mettre la pièce sous scellé.", "WARNING"] call comspec_sse_fnc_notify;
};

[
    8,
    "Collecte SSE...",
    {
        params ["_target", "_player", ["_seal", false], ["_isCollected", {false}]];
        if ([_target] call _isCollected) exitWith {
            ["Pièce déjà collectée par un équipier.", "WARNING"] call comspec_sse_fnc_notify;
        };
        [_target] call comspec_sse_fnc_ensureGenerated;

        private _data = [_target] call comspec_sse_fnc_getData;
        private _uid = if (isNil "_data") then { ["SSE-EVD"] call comspec_sse_fnc_generateUID } else {
            format ["SSE-EVD-%1", [_data, "uid", "X"] call BIS_fnc_getFromPairs]
        };
        private _quality = [65, true, 1, 1, 1, _player, _target, "collect"] call comspec_sse_fnc_calcQuality;
        private _pos = getPosATL _target;

        private _entry = createHashMapFromArray [
            ["evidenceId", _uid],
            ["type", [_data, "type", typeOf _target] call BIS_fnc_getFromPairs],
            ["collector", name _player],
            ["callsign", name _player],
            ["time", time],
            ["missionTime", [daytime, "HH:MM:SS"] call BIS_fnc_timeToString],
            ["position", _pos],
            ["grid", mapGridPosition _pos],
            ["action", "COLLECT"],
            ["quality", _quality],
            ["container", [_player, "evidence_bag"] call comspec_sse_fnc_resolveEquipment]
        ];

        ["collect", _target, [_quality, _entry]] call comspec_sse_fnc_requestServerOp;
        if (!isNil "comspec_sse_fnc_custody") then { ["open", [_target, _player, format ["collecte, qualité %1 %%", _quality]]] call comspec_sse_fnc_custody; };
        if (_seal) then { [_target, _player] call comspec_sse_fnc_bagEvidence; };

        [format [
            "Preuve collectée\n%1\nPar : %2\nHeure : %3\nGrid : %4\nQualité : %5%%",
            _uid,
            name _player,
            _entry get "missionTime",
            _entry get "grid",
            _quality
        ], "SUCCESS"] call comspec_sse_fnc_notify;

        [
            _uid,
            "collect",
            [_data, "type", "OBJECT"] call BIS_fnc_getFromPairs,
            format ["Collecté grid %1", _entry get "grid"],
            _quality,
            "QUEUED"
        ] call comspec_sse_fnc_addJournalEntry;
    },
    [_target, _player, _seal, _isCollected]
] call comspec_sse_fnc_progressAction;
