/*
    Génère des profils SSE sur une liste de cibles (file étalée).
    Partagé par le module « Générer un profil SSE » (Eden) et son dialogue Zeus.

    [_targets, _profile, _complexity, _options] call comspec_sse_fnc_zeusGenerateTargets
      _options (HashMap, tout optionnel) :
        identity  (bool, défaut true)  false = nom SSE inventé (ignore l'identité Arma/Eden)
        phone     (bool, défaut true)  false = pas de téléphone généré
        documents (bool, défaut true)  false = pas de documents
        bio       (bool, défaut true)  false = pas de biométrie
        network   (bool, défaut false) true = lie les cibles entre elles (ASSOCIATE)
        noise     (nombre 0–100)       probabilité de données inutiles (%)
    Retour : nombre de cibles mises en file.
*/
params [
    ["_targets", [], [[]]],
    ["_profile", "INSURGENT", [""]],
    ["_complexity", "STANDARD", [""]],
    ["_options", createHashMap, [createHashMap]]
];

_targets = _targets select { _x isEqualType objNull && {!isNull _x} };
if (_targets isEqualTo []) exitWith { 0 };

// Bruit propre à ces cibles (lu par generateData) : le réglage de mission comspec_sse_noiseProbability n'est plus
// modifié, sinon un module changeait le bruit de toutes les générations suivantes de la mission.
private _noise = _options getOrDefault ["noise", -1];
if (_noise isEqualType 0 && {_noise >= 0}) then {
    { _x setVariable ["comspec_sse_noiseOverride", ((_noise / 100) max 0) min 1]; } forEach _targets;
};

// Les NONE posés par Zeus sont notés (comspec_sse_zeusModes) : une case recochée lors d'une régénération
// les remet à AUTO, sans toucher aux modes NONE saisis dans Eden.
{
    private _ent = _x;
    private _zeusModes = +(_ent getVariable ["comspec_sse_zeusModes", []]);
    {
        _x params ["_key", "_var"];
        if !(_options getOrDefault [_key, true]) then {
            // Un NONE déjà présent (Eden) n'est pas repris à son compte par Zeus.
            if ((_var in _zeusModes) || {(_ent getVariable [_var, "AUTO"]) isNotEqualTo "NONE"}) then { _zeusModes pushBackUnique _var; };
            _ent setVariable [_var, "NONE", true];
        } else {
            if (_var in _zeusModes) then {
                if ((_ent getVariable [_var, "AUTO"]) isEqualTo "NONE") then { _ent setVariable [_var, nil, true]; };
                _zeusModes = _zeusModes - [_var];
            };
        };
    } forEach [
        ["identity", "comspec_sse_identityMode"],
        ["phone", "comspec_sse_phoneMode"],
        ["documents", "comspec_sse_documentsMode"],
        ["bio", "comspec_sse_bioMode"]
    ];
    _ent setVariable ["comspec_sse_zeusModes", _zeusModes, true];
} forEach _targets;

private _interval = 0.28;
private _jobs = _targets apply { [_x, _profile, _complexity, "ZEUS"] };
[
    _jobs,
    {
        params ["_ent", "_profile", "_complexity", "_by"];
        if (isNull _ent) exitWith {};
        if (_ent getVariable ["comspec_sse_generating", false]) exitWith {};
        [_ent, _profile, _complexity, _by] call comspec_sse_fnc_generateData;
    },
    _interval
] call comspec_sse_fnc_queueEntityJobs;

private _settle = (count _jobs) * _interval + 1.5;
[_targets, _settle] call comspec_sse_fnc_zeusBroadcastEnabled;

if ((_options getOrDefault ["network", false]) && {count _targets > 1} && {!isNil "CBA_fnc_waitAndExecute"}) then {
    [{
        params ["_list"];
        for "_i" from 1 to ((count _list) - 1) do {
            [_list select (_i - 1), _list select _i, "ASSOCIATE", 0.7, "ZEUS"] call comspec_sse_fnc_zeusLink;
        };
    }, [_targets], _settle] call CBA_fnc_waitAndExecute;
};

count _jobs
