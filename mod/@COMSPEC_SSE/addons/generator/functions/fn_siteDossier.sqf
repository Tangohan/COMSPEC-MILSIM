/*
    Dossier d'un site : ajoute à la pièce papier principale des documents, lieux et renseignements
    qui citent les grids réelles des autres sites (réseau) ou de lieux réels de la carte (site isolé).
    [_object, _site] call comspec_sse_fnc_siteDossier   (serveur, après generateData de l'objet)
    _site get "related" : [[grid, libellé], ...]
*/
params [
    ["_obj", objNull, [objNull]],
    ["_site", createHashMap, [createHashMap]]
];

if (!isServer || {isNull _obj}) exitWith { false };
private _related = _site getOrDefault ["related", []];
if (_related isEqualTo []) exitWith { false };

private _data = [_obj] call comspec_sse_fnc_getData;
if (isNil "_data") exitWith { false };

private _seed = _site getOrDefault ["seed", 0];
private _theme = (_site getOrDefault ["cluster", createHashMap]) getOrDefault ["theme", ""];
private _sections = [_data, "sections", createHashMap] call comspec_sse_fnc_getPair;
if !(_sections isEqualType createHashMap) then { _sections = createHashMap; };
private _docs = +(_sections getOrDefault ["documents", []]);
private _locs = +(_sections getOrDefault ["locations", []]);
private _intel = +(_sections getOrDefault ["intel", []]);

private _days = ["lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi", "dimanche"];
{
    _x params ["_grid", "_label"];
    // Fenêtre de passage déterministe : le dossier dit quand surveiller l'autre site.
    private _day = [_seed, format ["dday%1", _forEachIndex], _days] call comspec_sse_fnc_pickFromSeed;
    private _hour = 18 + (([_seed, format ["dhour%1", _forEachIndex]] call comspec_sse_fnc_hash) mod 6);
    _docs pushBack (createHashMapFromArray [
        ["uid", format ["SSE-DOC-%1-D%2", [_seed, format ["dossier%1", _forEachIndex], 9] call comspec_sse_fnc_idToken, _forEachIndex]],
        ["title", format ["Note manuscrite — %1", _label]],
        ["summary", format ["%1, grid %2. Passage le %3 entre %4h et %5h.", _label, _grid, _day, _hour, _hour + 2]],
        ["grid", _grid],
        ["theme", _theme],
        ["codeword", ""],
        ["noise", false]
    ]);
    _locs pushBack (createHashMapFromArray [["label", _label], ["grid", _grid], ["theme", _theme]]);
    _intel pushBack (createHashMapFromArray [
        ["text", format ["Autre site de la cellule : %1 (grid %2)", _label, _grid]],
        ["confidence", 0.65]
    ]);
} forEach _related;

_sections set ["documents", _docs];
_sections set ["locations", _locs];
_sections set ["intel", _intel];
_data = [_data, "sections", _sections] call comspec_sse_fnc_setPair;
[_obj, _data, true] call comspec_sse_fnc_setData;
true
