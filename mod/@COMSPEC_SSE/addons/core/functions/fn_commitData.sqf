/*
    Greffier serveur : unique point d'écriture publique d'une fiche SSE.
    [_entity, _data, _owner, _fromClient] call comspec_sse_fnc_commitData -> ARRAY (fiche de référence)

    La fiche est découpée en champs : chaque paire racine ("p:state", "p:searched"…) et
    chaque section ("s:biometrics", "s:chainOfCustody"…). Le serveur tient pour chaque
    champ une révision (paire "rev", diffusée) et une empreinte texte (locale au serveur).
    - Écriture serveur : fait foi, les révisions des champs modifiés avancent.
    - Proposition client : un champ modifié n'est accepté que si le client l'a modifié
      à partir de la dernière révision connue (ou s'il en est lui-même le dernier auteur).
      Sinon sa copie est périmée et le champ est ignoré : une capture biométrique ne peut
      plus effacer la chaîne de traçabilité posée entre-temps par le serveur.
*/
params [
    ["_entity", objNull, [objNull]],
    ["_data", [], [[]]],
    ["_owner", 2, [0]],
    ["_fromClient", false, [false]]
];

if (!isServer || {isNull _entity}) exitWith { [] };

private _cur = _entity getVariable ["comspec_sse_data", nil];
private _hasCur = !isNil "_cur" && {_cur isEqualType []};
// Fiche absente (jamais créée ou effacée par Zeus) : on repart d'empreintes vides.
private _snap = if (_hasCur) then { _entity getVariable ["comspec_sse_snap", createHashMap] } else { createHashMap };
private _writers = if (_hasCur) then { _entity getVariable ["comspec_sse_writers", createHashMap] } else { createHashMap };

private _revs = if (_hasCur) then { [_cur, "rev", createHashMap] call comspec_sse_fnc_getPair } else { createHashMap };
_revs = if (_revs isEqualType createHashMap) then { +_revs } else { createHashMap };
private _inRevs = [_data, "rev", createHashMap] call comspec_sse_fnc_getPair;
if !(_inRevs isEqualType createHashMap) then { _inRevs = createHashMap; };

// Base : la proposition pour le serveur (il fait foi), la fiche courante pour un client.
private _base = if (_fromClient && {_hasCur}) then { _cur } else { _data };
private _outSec = createHashMap;
{ _outSec set [_x, _y]; } forEach ([_base, "sections", createHashMap] call comspec_sse_fnc_getPair);
private _out = _base apply { _x };

private _changed = [];
private _rejected = [];
private _consider = {
    params ["_k", "_v", "_isSection", "_name"];
    private _sv = str _v;
    if (_sv isEqualTo (_snap getOrDefault [_k, ""])) exitWith {};
    private _curR = _revs getOrDefault [_k, 0];
    if (_fromClient && {(_inRevs getOrDefault [_k, 0]) < _curR} && {(_writers getOrDefault [_k, -1]) != _owner}) exitWith {
        _rejected pushBack _k;
    };
    if (_isSection) then { _outSec set [_name, _v]; } else { _out = [_out, _name, _v] call comspec_sse_fnc_setPair; };
    _revs set [_k, _curR + 1];
    _snap set [_k, _sv];
    _writers set [_k, _owner];
    _changed pushBack _k;
};

{
    if (_x isEqualType [] && {count _x >= 2}) then {
        _x params ["_k", "_v"];
        if !(_k in ["sections", "rev", "updatedAt"]) then {
            [format ["p:%1", _k], _v, false, _k] call _consider;
        };
    };
} forEach _data;

private _inSec = [_data, "sections", createHashMap] call comspec_sse_fnc_getPair;
if (_inSec isEqualType createHashMap) then {
    { [format ["s:%1", _x], _y, true, _x] call _consider; } forEach _inSec;
};

_entity setVariable ["comspec_sse_snap", _snap];
_entity setVariable ["comspec_sse_writers", _writers];

if (_rejected isNotEqualTo []) then {
    [format ["commitData %1 : %2 champ(s) périmé(s) ignoré(s) de %3 : %4", _entity, count _rejected, _owner, _rejected], "INFO"] call comspec_sse_fnc_log;
};

if (_changed isEqualTo []) exitWith {
    // Rien de neuf : le client à la copie périmée reçoit la fiche de référence.
    if (_hasCur && {_rejected isNotEqualTo []} && {_owner > 2}) then {
        _entity setVariable ["comspec_sse_data", _cur, _owner];
    };
    if (_hasCur) then { _cur } else { _data }
};

_out = [_out, "sections", _outSec] call comspec_sse_fnc_setPair;
_out = [_out, "rev", _revs] call comspec_sse_fnc_setPair;
_out = [_out, "updatedAt", serverTime] call comspec_sse_fnc_setPair;

_entity setVariable ["comspec_sse_data", _out, true];
if !(_entity getVariable ["comspec_sse_enabled", false]) then {
    _entity setVariable ["comspec_sse_enabled", true, true];
};
// Signal léger (noms de champs seulement) : rafraîchissement des terminaux, menus des JIP.
["comspec_sse_recordChanged", [_entity, _changed]] call CBA_fnc_globalEvent;
_out
