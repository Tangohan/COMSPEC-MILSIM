/*
    Enrôlement d'une rencontre (serveur) : le sujet transmis par un SEEK entre dans
    la base de la mission avec les modalités réellement relevées. Au contrôle
    suivant, il ressort « déjà rencontré le … à … par … ».

    Params: [_probe] HashMap — unit, key, nfiqs, eyes, face, dna, by, grid
    Returns: String — référence du dossier
*/
params [["_probe", createHashMap, [createHashMap]]];
if (!isServer) exitWith { "" };

private _unit = _probe getOrDefault ["unit", objNull];
if (!(_unit isEqualType objNull)) then { _unit = objNull; };
private _key = _probe getOrDefault ["key", ""];
// Sur le serveur, la clé de l'unité est celle du registre (jamais celle calculée par un poste).
if (_key isEqualTo "" || {!isNull _unit}) then { _key = [_unit] call comspec_sse_fnc_bioPersonKey; };
if (_key isEqualTo "") exitWith { "" };

private _runtime = missionNamespace getVariable ["comspec_sse_bioRuntime", createHashMap];
if (!(_runtime isEqualType createHashMap)) then { _runtime = createHashMap; };
private _e = _runtime getOrDefault [_key, createHashMap];

private _nfiqs = _probe getOrDefault ["nfiqs", []];
private _fps = _e getOrDefault ["fpFingers", []];
private _fpScores = [];
{
    if (_x >= 1 && {_x <= 4}) then {
        _fps pushBackUnique _forEachIndex;
        _fpScores pushBack ([0, 95, 85, 70, 50] select _x);
    };
} forEach _nfiqs;
_e set ["fpFingers", _fps];
if (_fpScores isNotEqualTo []) then {
    private _s = 0;
    { _s = _s + _x; } forEach _fpScores;
    _e set ["fpQuality", (_e getOrDefault ["fpQuality", 0]) max (round (_s / (count _fpScores)))];
};
private _eyes = (_probe getOrDefault ["eyes", []]) select { _x >= 40 };
if (_eyes isNotEqualTo []) then {
    _e set ["iris", true];
    _e set ["irisQuality", (_e getOrDefault ["irisQuality", 0]) max (selectMax _eyes)];
};
private _faceQ = _probe getOrDefault ["face", -1];
if (_faceQ >= 35) then {
    _e set ["face", true];
    _e set ["faceQuality", (_e getOrDefault ["faceQuality", 0]) max _faceQ];
};
if ((_probe getOrDefault ["dna", -1]) >= 40) then { _e set ["dna", true]; };

private _when = format ["%1/%2 %3", (date select 2), (date select 1), [dayTime, "HH:MM"] call BIS_fnc_timeToString];
private _enc = _e getOrDefault ["encounters", []];
_enc pushBack [_when, _probe getOrDefault ["grid", ""], _probe getOrDefault ["by", ""]];
_e set ["encounters", _enc];
_runtime set [_key, _e];
missionNamespace setVariable ["comspec_sse_bioRuntime", _runtime];

// Campagne : les personnages à identifiant fixé gardent leurs rencontres.
if ((missionNamespace getVariable ["comspec_sse_bio_campaignMemory", false]) && {!isNull _unit} && {((_unit getVariable ["COMSPEC_SSE_BioId", ""]) isNotEqualTo "") || {(_unit getVariable ["BII_Identifi_bioKey", ""]) isNotEqualTo ""}}) then {
    private _store = format ["comspec_sse_bioCampaign_%1", [missionNamespace getVariable ["comspec_sse_bio_campaignSeed", ""], missionName] select ((missionNamespace getVariable ["comspec_sse_bio_campaignSeed", ""]) isEqualTo "")];
    private _saved = profileNamespace getVariable [_store, []];
    if (!(_saved isEqualType [])) then { _saved = []; };
    private _i = _saved findIf { (_x select 0) isEqualTo _key };
    private _packed = [_e] call comspec_sse_fnc_bioPack;
    if (_i >= 0) then { _saved set [_i, [_key, _packed]]; } else { _saved pushBack [_key, _packed]; };
    profileNamespace setVariable [_store, _saved];
    saveProfileNamespace;
};

([_unit, _key] call comspec_sse_fnc_bioRecord) getOrDefault ["id", ""]
