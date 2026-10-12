/*
    Traitement serveur des demandes biométriques, réponse au demandeur.
    [_op, _packedPayload, _requestId, _requester] call comspec_sse_fnc_bioServerHandle
*/
params [["_op", ""], ["_packed", []], ["_id", ""], ["_requester", objNull]];
if (!isServer) exitWith {};

private _p = [_packed] call comspec_sse_fnc_bioUnpack;
if (!(_p isEqualType createHashMap)) then { _p = createHashMap; };
private _res = createHashMap;

// Les clés viennent du registre du serveur, jamais du poste qui demande.
{
    _x params ["_uField", "_kField"];
    private _u = _p getOrDefault [_uField, objNull];
    if (_u isEqualType objNull && {!isNull _u}) then { _p set [_kField, [_u] call comspec_sse_fnc_bioPersonKey]; };
} forEach [["unit", "key"], ["ownerUnit", "ownerKey"]];

// Avis Athena relayés par un terminal en liaison (la DLL vit côté joueur).
private _wanted = _p getOrDefault ["wanted", []];
if (_wanted isEqualType [] && {_wanted isNotEqualTo []}) then {
    missionNamespace setVariable ["comspec_sse_bioWanted", _wanted];
};

switch (toLower _op) do {
    case "search": {
        _res = [_p] call comspec_sse_fnc_bioSearch;
    };
    case "enroll": {
        private _ref = [_p] call comspec_sse_fnc_bioEnroll;
        _res = createHashMapFromArray [["ref", _ref]];
        private _dnaQ = _p getOrDefault ["dna", -1];
        if (_dnaQ >= 0) then {
            private _unit = _p getOrDefault ["unit", objNull];
            [_unit, _p getOrDefault ["key", ""], _dnaQ, _requester, _p getOrDefault ["dnaRef", ""]] call comspec_sse_fnc_bioDnaLab;
            _res set ["dnaPending", true];
        };
    };
    case "dna": {
        [_p getOrDefault ["unit", objNull], _p getOrDefault ["key", ""], _p getOrDefault ["dna", 80], _requester, _p getOrDefault ["dnaRef", ""]] call comspec_sse_fnc_bioDnaLab;
        _res = createHashMapFromArray [["dnaPending", true]];
    };
    case "latent": {
        // La latente entre dans la base de la mission, puis on cherche son auteur.
        private _latents = missionNamespace getVariable ["comspec_sse_bioLatents", []];
        private _n = ([format ["%1|%2|%3", _p getOrDefault ["ownerKey", ""], _p getOrDefault ["label", ""], count _latents]] call comspec_sse_fnc_bioHash) mod 9000;
        private _lid = format ["LAT-%1", 1000 + _n];
        _latents pushBack createHashMapFromArray [
            ["id", _lid],
            ["ownerKey", _p getOrDefault ["ownerKey", ""]],
            ["fingers", _p getOrDefault ["fingers", []]],
            ["label", _p getOrDefault ["label", ""]],
            ["grid", _p getOrDefault ["grid", ""]],
            ["when", [dayTime, "HH:MM"] call BIS_fnc_timeToString]
        ];
        missionNamespace setVariable ["comspec_sse_bioLatents", _latents];
        private _probe = createHashMapFromArray [
            ["unit", _p getOrDefault ["ownerUnit", objNull]],
            ["key", _p getOrDefault ["ownerKey", ""]],
            ["scope", _p getOrDefault ["scope", "local"]],
            ["nfiqs", _p getOrDefault ["nfiqs", []]],
            ["eyes", []], ["face", -1],
            ["attempt", _p getOrDefault ["attempt", 1]]
        ];
        _res = [_probe] call comspec_sse_fnc_bioSearch;
        // Une latente n'a pas d'identité déclarée : jamais d'alerte de faux papiers.
        _res set ["mismatch", false];
        _res set ["latents", []];
        if ((_res get "verdict") isEqualTo "latent") then { _res set ["verdict", "none"]; };
        _res set ["latentId", _lid];
    };
};

if (isNull _requester || {_requester isEqualTo player && {hasInterface}}) exitWith {
    [_id, [_res] call comspec_sse_fnc_bioPack] call comspec_sse_fnc_bioClientResult;
};
["comspec_sse_bioRes", [_id, [_res] call comspec_sse_fnc_bioPack], _requester] call CBA_fnc_targetEvent;
