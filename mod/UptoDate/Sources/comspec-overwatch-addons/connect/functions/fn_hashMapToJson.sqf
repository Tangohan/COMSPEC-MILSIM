/*
 * Auteur: COMSPEC
 * Convertit HashMap en string JSON
 * Helper function pour sérialisation avant envoi extension
 *
 * Arguments:
 * 0: HashMap <HASHMAP>
 *
 * Valeur de retour:
 * <STRING> - JSON string
 *
 * Exemple:
 * private _json = [_myHashMap] call comspec_overwatch_connect_fnc_hashMapToJson;
 */

params [
    ["_hashMap", createHashMap, [createHashMap]]
];

private _dq = toString [34];
private _bs = toString [92];

// Chaîne JSON : guillemets, antislash et caractères de contrôle échappés
// (un saut de ligne brut dans un nom rendait tout le corps illisible → HTTP 400 côté Athena).
private _fnc_str = {
    params ["_s"];
    // Caractère par caractère : splitString écarte les segments vides, si bien que
    // guillemets / antislashs en tête, en fin ou doublés disparaissaient du texte.
    private _out = [];
    {
        switch (true) do {
            case (_x == 34): { _out append [92, 34] };
            case (_x == 92): { _out append [92, 92] };
            case (_x == 10): { _out append [92, 110] };
            case (_x == 13): { _out append [92, 114] };
            case (_x == 9): { _out append [92, 116] };
            case (_x < 32): { _out pushBack 32 };
            default { _out pushBack _x };
        };
    } forEach (toArray _s);
    _dq + (toString _out) + _dq
};

// Valeur JSON (récursif : tableaux imbriqués et sous-hashmaps)
private _fnc_val = {
    params ["_v"];
    if (isNil "_v") exitWith { "null" };
    switch (typeName _v) do {
        case "STRING": { [_v] call _fnc_str };
        case "SCALAR": {
            // NaN / infini : jamais de « nan » dans le corps (JSON invalide → 400).
            // Point décimal invariant — `str` met une virgule FR et casse le JSON.
            if (!(finite _v)) then { "0" } else {
                if (_v == (floor _v) && {abs _v < 1e12}) then { str (round _v) } else { _v toFixed 4 }
            };
        };
        case "BOOL": { ["false", "true"] select _v };
        case "ARRAY": { format ["[%1]", (_v apply { [_x] call _fnc_val }) joinString ","] };
        case "HASHMAP": { [_v] call comspec_overwatch_connect_fnc_hashMapToJson };
        case "SIDE";
        case "OBJECT";
        case "GROUP": { [str _v] call _fnc_str };
        default { "null" };
    };
};

private _pairs = [];
{
    private _k = if (_x isEqualType "") then { _x } else { str _x };
    _pairs pushBack (([_k] call _fnc_str) + ":" + ([_y] call _fnc_val));
} forEach _hashMap;

format ["{%1}", _pairs joinString ","]
