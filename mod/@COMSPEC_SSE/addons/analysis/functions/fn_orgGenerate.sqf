/*
    Génère une organisation cohérente à partir d'un groupe de personnes (serveur).

    Jusqu'à 4 personnes : un seul groupe (chef, adjoint, spécialistes).
    Au-delà : un chef de réseau et son adjoint au sommet, le reste réparti en
    cellules de 3 à 5 ; chaque cellule a un chef et des rôles tirés de son métier
    (logistique, action, soutien). Même graine, même organisation.

    [_units, _name, _kind, _seed, _known, _threat] call comspec_sse_fnc_orgGenerate → id
      _known : 0 rien n'est connu · 1 le chef est soupçonné · 2 l'organisation est connue des services
*/
params [
    ["_units", [], [[]]],
    ["_name", "", [""]],
    ["_kind", "reseau", [""]],
    ["_seed", -1, [0]],
    ["_known", 0, [0]],
    ["_threat", "moyenne", [""]]
];
if (!isServer) exitWith { [_units, _name, _kind, _seed, _known, _threat] remoteExecCall ["comspec_sse_fnc_orgGenerate", 2]; "" };
// Demande venue d'un client (appel distant) : Zeus ou administrateur seulement.
if (isRemoteExecuted && {remoteExecutedOwner != 2} && {
    private _ro = remoteExecutedOwner;
    !([(allPlayers select { owner _x == _ro }) param [0, objNull]] call comspec_sse_fnc_isAuthority)
}) exitWith {
    diag_log text format ["[COMSPEC SSE] Organisations : génération refusée au poste %1 (ni Zeus ni administrateur).", remoteExecutedOwner];
    ""
};

_units = _units select { !isNull _x && {_x isKindOf "CAManBase"} };
if (_units isEqualTo []) exitWith { "" };
if (_seed < 0) then { _seed = floor (random 999999); };

private _rnd = {
    params ["_salt"];
    (([_seed, _salt] call comspec_sse_fnc_hash) mod 10000) / 10000
};
private _codes = +(([] call comspec_sse_fnc_orgTables) get "codes");
private _pickCode = {
    params ["_salt"];
    if (_codes isEqualTo []) exitWith { format ["%1", 10 + floor (([_salt] call _rnd) * 89)] };
    private _i = floor ((([_salt] call _rnd) * (count _codes)) min ((count _codes) - 1));
    _codes deleteAt _i
};

if ((trim _name) isEqualTo "") then {
    _name = format ["%1 %2", ["kindLabel", _kind] call comspec_sse_fnc_orgTables, ["name"] call _pickCode];
};

// Ordre stable : le même groupe donne la même organisation.
private _sorted = _units apply { [[_seed, [_x] call comspec_sse_fnc_bioPersonKey] call comspec_sse_fnc_hash, _x] };
_sorted = ([_sorted, [], { _x select 0 }, "ASCEND"] call BIS_fnc_sortBy) apply { _x select 1 };

// Diffusion regroupée ; appelée dans un lot plus large (module Organisation), la publication revient à l'appelant.
private _wasBatch = missionNamespace getVariable ["comspec_sse_orgBatch", false];
comspec_sse_orgBatch = true;
private _root = ["ensure", [_name, _kind, "", _threat]] call comspec_sse_fnc_orgOp;
private _assign = {
    params ["_u", "_org", "_role", "_cell"];
    ["member", [_u, _org, _role, "", _cell, 0]] call comspec_sse_fnc_orgOp;
};

private _n = count _sorted;
if (_n <= 4) then {
    private _roles = ["chef", "adjoint", "artificier", "logisticien"];
    { [_x, _name, _roles select _forEachIndex, ""] call _assign; } forEach _sorted;
} else {
    [_sorted select 0, _name, "chef", ""] call _assign;
    [_sorted select 1, _name, "adjoint", ""] call _assign;
    private _rest = _sorted select [2];
    private _cells = ceil ((count _rest) / 4);
    private _trades = [
        ["logisticien", "courrier", "guetteur", "financier", "membre"],
        ["artificier", "combattant", "guetteur", "combattant", "transmissions"],
        ["recruteur", "propagande", "facilitateur", "courrier", "sympathisant"]
    ];
    for "_c" from 0 to (_cells - 1) do {
        private _cellName = format ["Cellule %1", [format ["cell%1", _c]] call _pickCode];
        private _members = [];
        { if ((_forEachIndex mod _cells) isEqualTo _c) then { _members pushBack _x; }; } forEach _rest;
        private _trade = _trades select (floor (([format ["trade%1", _c]] call _rnd) * 3) min 2);
        {
            private _role = if (_forEachIndex isEqualTo 0) then { "chefcellule" } else { _trade select ((_forEachIndex - 1) mod (count _trade)) };
            [_x, _name, _role, _cellName] call _assign;
        } forEach _members;
    };
};

comspec_sse_orgBatch = _wasBatch;
if (!_wasBatch) then { ["publish", []] call comspec_sse_fnc_orgOp; };

// Ce que les services savent au début.
if (_known >= 2) then {
    ["revealOrg", [_root, 1, "connue des services"]] call comspec_sse_fnc_orgOp;
};
if (_known >= 1) then {
    ["reveal", [_sorted select 0, 1, "renseignement initial"]] call comspec_sse_fnc_orgOp;
};
_root
